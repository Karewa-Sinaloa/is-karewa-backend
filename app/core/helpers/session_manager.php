<?php

use App\Model\DB;

/**
 * Single-active-session and token-blacklist store.
 *
 * Backs the "one active session per user" invariant with `{prefix}user_sessions`
 * (unique `user_id`) and a persistent `{prefix}token_blacklist` (unique `jti`).
 * Both tables are keyed by the `jti` claim carried by each JWT, so a token can be
 * bound to the user's active session and revoked individually.
 *
 * All statements are parameterized and driver-aware: MySQL/MariaDB use
 * `ON DUPLICATE KEY UPDATE`, SQLite (used by the test suite) uses
 * `ON CONFLICT ... DO UPDATE`. The class has no output side effects so it can be
 * unit tested against an injected connection via App\Model\DB.
 */
abstract class SessionManager
{
    /**
     * Replace the user's active session with a new token, atomically.
     *
     * Last write wins: the unique key on `user_id` guarantees at most one row,
     * so a new login overwrites the previous session. Expired rows are pruned
     * opportunistically.
     */
    public static function replace(int $userId, string $jti, int $issuedAt, int $expiresAt): void
    {
        $db    = DB::connection();
        $table = DB::prefix() . 'user_sessions';

        if (self::driver($db) === 'sqlite') {
            $sql = 'INSERT INTO ' . $table . ' (user_id, jti, issued_at, expires_at)'
                 . ' VALUES (:user_id, :jti, :issued_at, :expires_at)'
                 . ' ON CONFLICT (user_id) DO UPDATE SET'
                 . ' jti = excluded.jti,'
                 . ' issued_at = excluded.issued_at,'
                 . ' expires_at = excluded.expires_at';
        } else {
            $sql = 'INSERT INTO ' . $table . ' (user_id, jti, issued_at, expires_at)'
                 . ' VALUES (:user_id, :jti, :issued_at, :expires_at)'
                 . ' ON DUPLICATE KEY UPDATE'
                 . ' jti = VALUES(jti),'
                 . ' issued_at = VALUES(issued_at),'
                 . ' expires_at = VALUES(expires_at)';
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
            $stmt->bindValue(':issued_at', $issuedAt, PDO::PARAM_INT);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \AppException('Could not store active session: ' . $e->getMessage(), 902000);
        }

        self::prune();
    }

    /**
     * Whether `$jti` is the user's currently active, unexpired session.
     */
    public static function isActive(int $userId, string $jti): bool
    {
        $db    = DB::connection();
        $table = DB::prefix() . 'user_sessions';

        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM ' . $table
                . ' WHERE user_id = :user_id AND jti = :jti AND expires_at >= :now'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
            $stmt->bindValue(':now', time(), PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            throw new \AppException('Could not read active session: ' . $e->getMessage(), 902000);
        }
    }

    /**
     * Cancel the user's active session, if any.
     */
    public static function cancel(int $userId): void
    {
        $db    = DB::connection();
        $table = DB::prefix() . 'user_sessions';

        try {
            $stmt = $db->prepare('DELETE FROM ' . $table . ' WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \AppException('Could not cancel session: ' . $e->getMessage(), 902000);
        }
    }

    /**
     * Whether a `jti` is on the blacklist. Entries remain effective until the
     * revoked token's expiry (plus any configured retention).
     */
    public static function isBlacklisted(string $jti): bool
    {
        $db    = DB::connection();
        $table = DB::prefix() . 'token_blacklist';

        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM ' . $table
                . ' WHERE jti = :jti AND expires_at >= :now'
            );
            $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
            $stmt->bindValue(':now', time(), PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            throw new \AppException('Could not read token blacklist: ' . $e->getMessage(), 902000);
        }
    }

    /**
     * Add a revoked token to the blacklist. Idempotent: re-revoking the same
     * `jti` keeps the earliest revocation record.
     */
    public static function blacklist(string $jti, int $userId, int $expiresAt, string $reason = 'revoked'): void
    {
        $db    = DB::connection();
        $table = DB::prefix() . 'token_blacklist';
        $now   = time();

        if (self::driver($db) === 'sqlite') {
            $sql = 'INSERT INTO ' . $table . ' (jti, user_id, expires_at, reason, revoked_at)'
                 . ' VALUES (:jti, :user_id, :expires_at, :reason, :revoked_at)'
                 . ' ON CONFLICT (jti) DO NOTHING';
        } else {
            $sql = 'INSERT INTO ' . $table . ' (jti, user_id, expires_at, reason, revoked_at)'
                 . ' VALUES (:jti, :user_id, :expires_at, :reason, :revoked_at)'
                 . ' ON DUPLICATE KEY UPDATE jti = jti';
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindValue(':revoked_at', $now, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \AppException('Could not blacklist token: ' . $e->getMessage(), 902000);
        }

        self::prune();
    }

    /**
     * Remove expired rows from both tables. A blacklist entry is kept until the
     * revoked token's expiry plus SESSION_BLACKLIST_RETENTION seconds.
     */
    public static function prune(): void
    {
        $db  = DB::connection();
        $now = time();
        $retention = defined('SESSION_BLACKLIST_RETENTION') ? (int) SESSION_BLACKLIST_RETENTION : 0;

        try {
            $sessionTable = DB::prefix() . 'user_sessions';
            $stmt = $db->prepare('DELETE FROM ' . $sessionTable . ' WHERE expires_at < :now');
            $stmt->bindValue(':now', $now, PDO::PARAM_INT);
            $stmt->execute();

            $blacklistTable = DB::prefix() . 'token_blacklist';
            $stmt = $db->prepare('DELETE FROM ' . $blacklistTable . ' WHERE expires_at + :retention < :now');
            $stmt->bindValue(':retention', $retention, PDO::PARAM_INT);
            $stmt->bindValue(':now', $now, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \AppException('Could not prune sessions: ' . $e->getMessage(), 902000);
        }
    }

    private static function driver(PDO $db): string
    {
        return (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
