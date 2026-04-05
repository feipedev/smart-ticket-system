<?php
// ================================================================
// AUTH CONTROLLER — Login + Register com JWT
// ================================================================
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\JwtAuth;
use App\Config\Database;
use PDO;

/**
 * Controller de autenticação.
 * POST /api/auth/register — Cria nova conta
 * POST /api/auth/login    — Retorna JWT
 * GET  /api/auth/me       — Perfil do usuário autenticado
 */
final class AuthController
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    // ── POST /api/auth/register ───────────────────────────────────

    public function register(): void
    {
        $body = $this->parseBody();

        $errors = [];
        if (empty($body['name']) || strlen(trim($body['name'])) < 2)   $errors[] = '"name" obrigatório (mínimo 2 chars).';
        if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) $errors[] = '"email" inválido.';
        if (empty($body['password']) || strlen($body['password']) < 8) $errors[] = '"password" deve ter no mínimo 8 caracteres.';

        if ($errors) $this->respond(422, ['error' => true, 'messages' => $errors]);

        // Verifica e-mail duplicado
        $chk = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $chk->execute([':email' => strtolower(trim($body['email']))]);
        if ($chk->fetch()) {
            $this->respond(409, ['error' => true, 'message' => 'E-mail já cadastrado.']);
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO users (name, email, password_hash, role, created_at)
            VALUES (:name, :email, :hash, :role, :now)
        ');
        $stmt->execute([
            ':name'  => trim($body['name']),
            ':email' => strtolower(trim($body['email'])),
            ':hash'  => password_hash($body['password'], PASSWORD_BCRYPT),
            ':role'  => 'user',
            ':now'   => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $token  = JwtAuth::generate(['user_id' => $userId, 'role' => 'user']);

        $this->respond(201, [
            'message' => 'Conta criada com sucesso.',
            'token'   => $token,
        ]);
    }

    // ── POST /api/auth/login ──────────────────────────────────────

    public function login(): void
    {
        $body = $this->parseBody();

        if (empty($body['email']) || empty($body['password'])) {
            $this->respond(400, ['error' => true, 'message' => '"email" e "password" são obrigatórios.']);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($body['email']))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            // Mensagem genérica para não revelar se e-mail existe
            $this->respond(401, ['error' => true, 'message' => 'Credenciais inválidas.']);
        }

        $token = JwtAuth::generate([
            'user_id' => (int) $user['id'],
            'role'    => $user['role'],
        ]);

        $this->respond(200, [
            'message' => 'Login realizado.',
            'token'   => $token,
            'user'    => [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    // ── GET /api/auth/me ──────────────────────────────────────────

    public function me(): void
    {
        $payload = JwtAuth::requireAuth();

        $stmt = $this->pdo->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $payload->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $this->respond(200, ['data' => $user]);
        } else {
            $this->respond(404, ['error' => true, 'message' => 'Usuário não encontrado.']);
        }
    }

    // ── HELPERS ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw !== false ? $raw : '{}', true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        echo json_encode(array_merge(['status' => $code], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
