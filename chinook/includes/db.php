<?php
/**
 * db.php
 * Database connection configuration for the Chinook Album Manager.
 * Uses PDO for secure, prepared-statement-based database access.
 *
 * @author  Chinook Dev Team
 * @version 1.0
 */

// ── Database credentials ───────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'chinook');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection to the Chinook database.
 * Throws a PDOException (caught below) if the connection fails.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                     // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this error rather than displaying it
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
