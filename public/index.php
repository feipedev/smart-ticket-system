<?php
// ================================================================
// FRONT CONTROLLER — Smart Ticket System
// Ponto único de entrada. Carrega autoloader, configura CORS,
// define headers REST e roteia para o arquivo de rotas.
// ================================================================

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('SRC_PATH', APP_ROOT . '/src');

// Carrega Composer Autoloader (PSR-4)
require_once APP_ROOT . '/vendor/autoload.php';

// ── Configuração de Erros ────────────────────────────────────────
$env = getenv('APP_ENV') ?: 'development';

if ($env === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ── Headers REST / CORS ──────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Resposta para preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Pre-Roteador Frontend vs API ───────────────────────────────
$uri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && $basePath !== '\\' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Se a requisição NÃO for para /api, servimos a interface HTML
if (strpos($uri, '/api') !== 0) {
    // Força o Content-Type para HTML (sobrescrevendo o JSON definido no topo)
    header('Content-Type: text/html; charset=UTF-8');
    require_once APP_ROOT . '/public/index.html';
    exit;
}

// ── Roteador da API ─────────────────────────────────────────────
require_once SRC_PATH . '/Routes/api.php';
