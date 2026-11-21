<?php
declare(strict_types=1);

namespace App\Libraries\Security;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Owner of a top-level account/governance mutation transaction.
 *
 * AuditTrail::append() uses the same MySQL advisory lock and nested CI4
 * transaction. Holding the outer advisory lock until the OUTER commit closes
 * the gap where a concurrent audit append could observe an uncommitted head.
 *
 * Must be invoked as the outermost transaction on the same DB connection.
 * Never perform network delivery or slow external work inside this callback.
 * The callback MUST append a durable AuditTrail event before it returns.
 */
final class AtomicAuditedMutation
{
    private const LOCK_NAME = 'iedpu_audit_chain_v1';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @template T
     * @param callable():T $action
     * @return T
     */
    public function run(callable $action): mixed
    {
        // Reject an already active transaction. An outer rollback after this
        // method returned would otherwise invalidate its audit-head promise.
        $row = $this->db->query('SELECT @@in_transaction AS active')->getRowArray();
        if ($row === null || (int) ($row['active'] ?? 1) !== 0) {
            throw new RuntimeException('Audited mutation requires an outermost transaction.');
        }
        $lock = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', [self::LOCK_NAME])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Audited mutation lock unavailable.');
        }

        $started = false;
        try {
            if (!$this->db->transBegin()) {
                throw new RuntimeException('Could not begin audited mutation.');
            }
            $started = true;
            $result = $action();
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Audited mutation could not commit.');
            }
            $started = false;
            return $result;
        } catch (Throwable $exception) {
            if ($started) {
                $this->db->transRollback();
            }
            throw $exception;
        } finally {
            // The outer transaction is committed/rolled back before unlock.
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }
    }
}
