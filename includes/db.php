<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensure_schema(): void
{
    try {
        db()->query('SELECT 1 FROM conversions LIMIT 1');
    } catch (Throwable $e) {
        $sqlFile = APP_ROOT . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (!is_file($sqlFile)) {
            return;
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return;
        }

        $bootstrap = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $bootstrap->exec($statement);
            }
        }
    }

    migrate_schema();
}

function migrate_schema(): void
{
    try {
        $cols = db()->query('SHOW COLUMNS FROM conversions LIKE \'direction\'')->fetchAll();
        if ($cols === []) {
            db()->exec(
                "ALTER TABLE conversions
                 ADD COLUMN direction ENUM('pdf_to_word','word_to_pdf') NOT NULL DEFAULT 'pdf_to_word'
                 AFTER original_name"
            );
        }
    } catch (Throwable $e) {
        // Table may not exist yet
    }
}
