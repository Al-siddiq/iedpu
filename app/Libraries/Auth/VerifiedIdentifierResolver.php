<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Resolves only active, possession-verified email OR phone to one Shield user.
 * Shield 1.4.1 stores the single password hash on an email_password identity;
 * its "secret" can hold the account's verified primary login identifier
 * (E.164 phone for phone-only accounts), never a fabricated email address.
 *
 * Account provisioning is gated until its transactional invitation and
 * ownership verification flow has been accepted.
 */
final class VerifiedIdentifierResolver
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{user_id:int,shield_secret:string}|null */
    public function resolve(string $submitted): ?array
    {
        try {
            $identifier = LoginIdentifier::normalise($submitted);
        } catch (Throwable) {
            return null;
        }

        $record = $this->db->table('portal_login_identifiers i')
            ->select('i.user_id')
            ->join('users u', 'u.id=i.user_id')
            ->where('i.identifier_type', $identifier['type'])
            ->where('i.normalised_value', $identifier['value'])
            ->where('i.status', 'active')
            ->where('i.verified_at IS NOT NULL', null, false)
            ->where('i.revoked_at', null)
            ->where('u.active', 1)
            ->get()->getRowArray();
        if ($record === null) {
            return null;
        }
        $userId = (int) $record['user_id'];
        $primary = $this->db->table('auth_identities')
            ->select('secret,secret2')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()->getRowArray();
        if ($primary === null || !is_string($primary['secret']) || !is_string($primary['secret2'])
            || $primary['secret2'] === '') {
            return null;
        }
        try {
            $primaryId = LoginIdentifier::normalise($primary['secret']);
        } catch (Throwable) {
            return null;
        }
        $primaryActive = $this->db->table('portal_login_identifiers')
            ->where('user_id', $userId)
            ->where('identifier_type', $primaryId['type'])
            ->where('normalised_value', $primaryId['value'])
            ->where('status', 'active')
            ->where('verified_at IS NOT NULL', null, false)
            ->where('revoked_at', null)
            ->countAllResults();
        if ($primaryActive !== 1) {
            return null;
        }
        return ['user_id' => $userId, 'shield_secret' => $primary['secret']];
    }

    public function hasVerifiedIdentifier(int $userId): bool
    {
        return $this->db->table('portal_login_identifiers')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('verified_at IS NOT NULL', null, false)
            ->where('revoked_at', null)
            ->countAllResults() > 0;
    }
}
