<?php
// ================================================================
// JWT MIDDLEWARE — Autenticação stateless via Bearer Token
// firebase/php-jwt v5.x — compatível com PHP 7.4
// ================================================================
declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use UnexpectedValueException;

/**
 * Middleware de autenticação JWT.
 *
 * Uso:
 *   $payload = JwtAuth::requireAuth(); // Bloqueia se inválido
 *   $token   = JwtAuth::generate(['user_id' => 1, 'role' => 'admin']);
 */
final class JwtAuth
{
    private static function secret(): string
    {
        $secret = getenv('JWT_SECRET') ?: 'chang3_th1s_in_pr0duct10n_fast_123!';
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET deve ter pelo menos 32 caracteres.');
        }
        return $secret;
    }

    /**
     * Gera um token JWT assinado.
     *
     * @param array<string, mixed> $payload
     * @param int $expiresInSeconds Padrão: 8 horas
     */
    public static function generate(array $payload, int $expiresInSeconds = 28800): string
    {
        $now = time();

        $claims = array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expiresInSeconds,
            'iss' => 'smart-ticket-system',
        ]);

        return JWT::encode($claims, self::secret());
    }

    /**
     * Valida o token do header Authorization: Bearer <token>.
     * Interrompe a execução com 401 se inválido ou ausente.
     *
     * @return stdClass Payload decodificado
     */
    public static function requireAuth(): stdClass
    {
        $header = isset($_SERVER['HTTP_AUTHORIZATION'])
            ? $_SERVER['HTTP_AUTHORIZATION']
            : (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '');

        if (strpos($header, 'Bearer ') !== 0) {
            self::abort(401, 'Token de autenticação ausente ou mal formatado.');
        }

        $token = substr($header, 7);

        try {
            return JWT::decode($token, self::secret(), ['HS256']);
        } catch (ExpiredException $e) {
            self::abort(401, 'Token expirado. Faça login novamente.');
        } catch (SignatureInvalidException $e) {
            self::abort(401, 'Assinatura do token inválida.');
        } catch (UnexpectedValueException $e) {
            self::abort(401, 'Token inválido: ' . $e->getMessage());
        }

        // Nunca chegará aqui — satisfaz o type checker
        throw new \RuntimeException('Unreachable');
    }

    /**
     * Valida sem interromper a execução — retorna null se inválido.
     */
    public static function optionalAuth(): ?stdClass
    {
        try {
            return self::requireAuth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Garante que o usuário autenticado tenha o role esperado.
     *
     * @param stdClass $payload
     * @param string   $role
     */
    public static function requireRole(stdClass $payload, string $role): void
    {
        $payloadRole = isset($payload->role) ? $payload->role : '';
        if ($payloadRole !== $role) {
            self::abort(403, "Acesso negado. Role necessário: {$role}.");
        }
    }

    private static function abort(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['error' => true, 'message' => $message]);
        exit;
    }
}
