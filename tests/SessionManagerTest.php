<?php

require_once __DIR__ . '/Support/OrTestCase.php';
require_once CORE_PATH . 'helpers/session_manager.php';

/**
 * Covers the single-active-session and token-blacklist store against an
 * in-memory database: last-write-wins replacement, active lookup, cancellation,
 * blacklist idempotency, and expiry-based pruning.
 */
final class SessionManagerTest extends OrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo->exec(
            'CREATE TABLE user_sessions ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' user_id INTEGER NOT NULL,'
            . ' jti TEXT NOT NULL,'
            . ' issued_at INTEGER NOT NULL,'
            . ' expires_at INTEGER NOT NULL,'
            . ' UNIQUE (user_id)'
            . ')'
        );
        $this->pdo->exec(
            'CREATE TABLE token_blacklist ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' jti TEXT NOT NULL,'
            . ' user_id INTEGER NOT NULL,'
            . ' expires_at INTEGER NOT NULL,'
            . ' reason TEXT NOT NULL DEFAULT "revoked",'
            . ' revoked_at INTEGER NOT NULL,'
            . ' UNIQUE (jti)'
            . ')'
        );
    }

    private function sessionRows(): array
    {
        return $this->pdo->query('SELECT * FROM user_sessions')->fetchAll();
    }

    public function testReplaceKeepsOneActiveSessionPerUser(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-a', $now, $now + 3600);
        SessionManager::replace(1, 'jti-b', $now, $now + 3600);

        $rows = $this->sessionRows();
        $this->assertCount(1, $rows, 'A user must never have more than one session row');
        $this->assertSame('jti-b', $rows[0]['jti'], 'The latest login wins');
    }

    public function testReplaceAllowsIndependentUsers(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-a', $now, $now + 3600);
        SessionManager::replace(2, 'jti-b', $now, $now + 3600);

        $this->assertCount(2, $this->sessionRows());
    }

    public function testIsActiveMatchesOnlyTheCurrentUnexpiredToken(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-a', $now, $now + 3600);

        $this->assertTrue(SessionManager::isActive(1, 'jti-a'));
        $this->assertFalse(SessionManager::isActive(1, 'jti-other'));
        $this->assertFalse(SessionManager::isActive(2, 'jti-a'));
    }

    public function testIsActiveRejectsExpiredSession(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-a', $now - 7200, $now - 3600);

        $this->assertFalse(SessionManager::isActive(1, 'jti-a'));
    }

    public function testCancelRemovesTheActiveSession(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-a', $now, $now + 3600);
        SessionManager::cancel(1);

        $this->assertCount(0, $this->sessionRows());
        $this->assertFalse(SessionManager::isActive(1, 'jti-a'));
    }

    public function testBlacklistIsEffectiveAndIdempotent(): void
    {
        $now = time();
        SessionManager::blacklist('jti-a', 1, $now + 3600, 'logout');
        SessionManager::blacklist('jti-a', 1, $now + 3600, 'logout');

        $this->assertTrue(SessionManager::isBlacklisted('jti-a'));
        $this->assertFalse(SessionManager::isBlacklisted('jti-other'));

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM token_blacklist')->fetchColumn();
        $this->assertSame(1, $count, 'Re-revoking the same jti must not duplicate the row');
    }

    public function testExpiredBlacklistEntryIsNoLongerEffective(): void
    {
        $now = time();
        SessionManager::blacklist('jti-a', 1, $now - 3600, 'logout');

        $this->assertFalse(SessionManager::isBlacklisted('jti-a'));
    }

    public function testPruneRemovesExpiredSessionsAndBlacklistEntries(): void
    {
        $now = time();
        SessionManager::replace(1, 'jti-live', $now, $now + 3600);
        SessionManager::replace(2, 'jti-dead', $now - 7200, $now - 3600);
        SessionManager::blacklist('jti-live', 1, $now + 3600, 'logout');
        SessionManager::blacklist('jti-dead', 2, $now - 3600, 'logout');

        SessionManager::prune();

        $sessions = $this->sessionRows();
        $this->assertCount(1, $sessions);
        $this->assertSame('jti-live', $sessions[0]['jti']);

        $blacklisted = $this->pdo->query('SELECT jti FROM token_blacklist')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['jti-live'], $blacklisted);
    }
}
