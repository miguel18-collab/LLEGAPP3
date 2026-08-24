<?php
/**
 * LLEGAPP 3 - Conexión a base de datos (PDO singleton)
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'llegapp3');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtiene la conexión PDO compartida de la petición
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('LLEGAPP3 BD: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos. Verifica que MySQL esté activo y exista la BD "' . DB_NAME . '".');
        }
    }

    return $pdo;
}

/**
 * Ejecuta una consulta preparada y devuelve el statement
 */
function dbq(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Devuelve todas las filas de una consulta
 */
function dbAll(string $sql, array $params = []): array {
    return dbq($sql, $params)->fetchAll();
}

/**
 * Devuelve la primera fila o null
 */
function dbOne(string $sql, array $params = []): ?array {
    $row = dbq($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Devuelve el primer valor escalar de la consulta
 */
function dbVal(string $sql, array $params = [], $default = null) {
    $val = dbq($sql, $params)->fetchColumn();
    return $val === false ? $default : $val;
}
