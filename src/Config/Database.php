<?php
// ================================================================
// DATABASE CONFIG — Singleton de conexão PDO com SQLite
// ================================================================
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Gerencia a conexão singleton com o banco de dados SQLite.
 * Usar SQLite garante zero custo de infraestrutura para deploy/demo.
 * Para produção real, basta alterar os parâmetros do PDO (MySQL/PostgreSQL).
 */
final class Database
{
    /** @var PDO|null */
    private static $instance = null;

    private function __construct() {} // Previne instanciação direta

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dbPath = getenv('DB_PATH') ?: APP_ROOT . '/database/tickets.sqlite';

        // Garante que o diretório existe
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Não foi possível criar o diretório do banco: {$dir}");
        }

        try {
            self::$instance = new PDO("sqlite:{$dbPath}", null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Habilita WAL mode para melhor performance de escrita concorrente
            self::$instance->exec('PRAGMA journal_mode=WAL;');
            self::$instance->exec('PRAGMA foreign_keys=ON;');

            // Migração automática na primeira execução
            self::migrate(self::$instance, $dbPath);

        } catch (PDOException $e) {
            throw new RuntimeException('Falha ao conectar ao banco de dados: ' . $e->getMessage());
        }

        return self::$instance;
    }

    /**
     * Executa o schema SQL apenas se o DB for novo.
     */
    private static function migrate(PDO $pdo, string $dbPath): void
    {
        $schemaPath = APP_ROOT . '/database/schema.sql';

        // Verifica se a tabela principal já existe
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tickets'")->fetchColumn();
        if ($tableExists) return;

        if (!file_exists($schemaPath)) {
            throw new RuntimeException("Schema SQL não encontrado: {$schemaPath}");
        }

        $pdo->exec(file_get_contents($schemaPath));
    }
}
