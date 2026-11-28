<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

/**
 * Pure fail-closed session predicate for a future invitation issuance route.
 *
 * The HTTP/CLI boundary must obtain authenticatedUserId from Shield, verify
 * SessionVersion::isCurrent() against the database, and read the MFA session
 * marker from the server-side session. Never accept these values from a
 * request body or a caller-controlled header.
 *
 * This gate does NOT issue tokens, grant roles, or enable any route.
 */
final class InvitationIssuerSessionGate
{
    public static function allows(
        int $authenticatedUserId,
        int $requestedIssuerUserId,
        bool $currentSessionVersion,
        mixed $mfaVerifiedUserId,
        bool $mfaRequired
    ): bool {
        if ($authenticatedUserId < 1 || $requestedIssuerUserId < 1
            || $authenticatedUserId !== $requestedIssuerUserId
            || !$currentSessionVersion) {
            return false;
        }

        // For privileged invitations, require an independently completed MFA
        // challenge for THIS user; a truthy value or another user's marker
        // must never be accepted.
        if ($mfaRequired) {
            return is_int($mfaVerifiedUserId)
                && $mfaVerifiedUserId === $authenticatedUserId;
        }

        return true;
    }
}
