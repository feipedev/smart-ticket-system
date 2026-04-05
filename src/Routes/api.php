<?php
// ================================================================
// ROUTER API — Roteamento manual leve, sem framework
// Define as rotas REST e despacha para o Controller correto
// ================================================================
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Repositories\TicketRepository;
use App\Config\Database;

// ── Helpers ──────────────────────────────────────────────────────

/**
 * Parser simples de URL com suporte a parâmetros dinâmicos.
 * Ex: matchRoute('/api/tickets/42', '/api/tickets/{id}') => ['id' => 42]
 *
 * @return array<string, string>|false
 */
function matchRoute(string $uri, string $pattern)
{
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    if (preg_match('#^' . $regex . '$#', $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

function notFound(): void
{
    http_response_code(404);
    echo json_encode(['status' => 404, 'error' => true, 'message' => 'Rota não encontrada.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function methodNotAllowed(): void
{
    http_response_code(405);
    echo json_encode(['status' => 405, 'error' => true, 'message' => 'Método HTTP não permitido nesta rota.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Bootstrap ────────────────────────────────────────────────────
$pdo    = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$uri    = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Resolve o problema de rodar em subdiretórios (ex: XAMPP)
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && $basePath !== '\\') {
    if (strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }
}
$uri = rtrim($uri, '/') ?: '/';

$ticketRepo       = new TicketRepository($pdo);
$ticketController = new TicketController($ticketRepo);
$authController   = new AuthController();

// ── Rotas de Autenticação ─────────────────────────────────────────
//   POST   /api/auth/register
//   POST   /api/auth/login
//   GET    /api/auth/me

if ($uri === '/api/auth/register' && $method === 'POST') {
    $authController->register();
}

if ($uri === '/api/auth/login' && $method === 'POST') {
    $authController->login();
}

if ($uri === '/api/auth/me' && $method === 'GET') {
    $authController->me();
}

// ── Rotas de Tickets ──────────────────────────────────────────────
//   GET    /api/tickets            → index (paginado, com filtros)
//   POST   /api/tickets            → store
//   GET    /api/tickets/{id}       → show
//   PUT    /api/tickets/{id}       → update
//   DELETE /api/tickets/{id}       → destroy

if ($uri === '/api/tickets') {
    if ($method === 'GET') {
        $ticketController->index();
    } elseif ($method === 'POST') {
        $ticketController->store();
    } else {
        methodNotAllowed();
    }
}

if ($params = matchRoute($uri, '/api/tickets/{id}')) {
    $id = (int) $params['id'];
    if ($method === 'GET') {
        $ticketController->show($id);
    } elseif ($method === 'PUT') {
        $ticketController->update($id);
    } elseif ($method === 'DELETE') {
        $ticketController->destroy($id);
    } else {
        methodNotAllowed();
    }
}

// ── Health Check ──────────────────────────────────────────────────

if ($uri === '/api/health' && $method === 'GET') {
    echo json_encode([
        'status'  => 200,
        'service' => 'Smart Ticket System',
        'version' => '1.0.0',
        'time'    => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// ── 404 Fallback ──────────────────────────────────────────────────
notFound();
