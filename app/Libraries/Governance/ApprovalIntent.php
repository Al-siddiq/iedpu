<?php
declare(strict_types=1);

namespace App\Libraries\Governance;

use InvalidArgumentException;
use JsonException;

/**
 * Canonical intent digest for a future maker-checker workflow.
 * A digest is NOT an approval or permission to execute a mutation.
 */
final class ApprovalIntent
{
    /** The supported intent types are deliberately finite, not client-supplied SQL. */
    private const ACTIONS = [
        'account.invite',
        'account.recovery',
        'account.suspend',
        'role.assign',
        'role.revoke',
        'role.update',
    ];

    /**
     * @param array<string,mixed> $proposedChange
     */
    public static function digest(
        string $action,
        string $subjectType,
        int $subjectId,
        string $scopeType,
        ?int $zoneId,
        ?int $branchId,
        string $expectedStateHash,
        array $proposedChange
    ): string {
        if (!in_array($action, self::ACTIONS, true)
            || !preg_match('/\A[a-z_]{2,60}\z/D', $subjectType)
            || $subjectId < 1
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedStateHash)
            || !self::validScope($scopeType, $zoneId, $branchId)) {
            throw new InvalidArgumentException('Invalid approval intent metadata.');
        }
        $payload = [
            'v' => 1,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'scope_type' => $scopeType,
            'zone_id' => $zoneId,
            'branch_id' => $branchId,
            'expected_state_sha256' => $expectedStateHash,
            'proposed_change' => self::canonical($proposedChange),
        ];
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unserialisable approval intent.', 0, $exception);
        }
    }

    private static function validScope(string $scope, ?int $zone, ?int $branch): bool
    {
        return match ($scope) {
            'global' => $zone === null && $branch === null,
            'zone' => $zone !== null && $zone > 0 && $branch === null,
            'branch' => $branch !== null && $branch > 0 && ($zone === null || $zone > 0),
            default => false,
        };
    }

    /** Recursively sort associative keys while preserving list order. */
    private static function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item);
            } elseif (!is_null($item) && !is_bool($item) && !is_int($item)
                && !is_string($item)) {
                // Floats and arbitrary objects are not stable enough for
                // privileged mutation intent comparison.
                throw new InvalidArgumentException('Invalid approval intent value.');
            }
        }
        return $value;
    }
}
