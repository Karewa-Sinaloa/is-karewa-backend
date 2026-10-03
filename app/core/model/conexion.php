<?php
namespace App\Model;
use PDO;

abstract class DB {
  /**
   * Optional injected connection/prefix used by tests. Null means production
   * behavior (build the connection from the MYSQL_* constants).
   */
  private static ?PDO $overrideConnection = null;
  private static ?string $overridePrefix = null;

  /**
   * Inject a connection for the current process. Inert unless called.
   */
  public static function setConnection(?PDO $connection): void {
    self::$overrideConnection = $connection;
  }

  /**
   * Inject a table prefix for the current process. Inert unless called.
   */
  public static function setPrefix(?string $prefix): void {
    self::$overridePrefix = $prefix;
  }

  /**
   * Drop any injected connection/prefix and return to production behavior.
   */
  public static function reset(): void {
    self::$overrideConnection = null;
    self::$overridePrefix = null;
  }

  public static function prefix(): string {
    return self::$overridePrefix ?? MYSQL_PREFIX;
  }

  public static function connection() {
    if (self::$overrideConnection instanceof PDO) {
      return self::$overrideConnection;
    }
    try {
      $dbconn = new PDO('mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DB . ';port=' . MYSQL_PORT . ';charset=' . MYSQL_CHARSET, MYSQL_USER, MYSQL_PSWD);
      $dbconn->exec('SET CHARACTER SET ' . MYSQL_CHARSET);
      $dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $dbconn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $dbconn->setAttribute(PDO::ATTR_PERSISTENT, true);
    } catch (\PDOException $e) {
      http_response_code(500);
      error_logs(['Database connection: ', $e->getMessage()], ERROR_LOG_FILE);
      die(json_encode(['message' => 'Unable to connect to database']));
    }
    return $dbconn;
  }
}
?>
