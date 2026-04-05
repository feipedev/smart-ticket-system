<?php
// ================================================================
// TICKET CONTROLLER — Lógica HTTP dos endpoints de tickets
// ================================================================
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\JwtAuth;
use App\Models\Ticket;
use App\Repositories\TicketRepository;

/**
 * Controller RESTful para o recurso /api/tickets.
 * Depende apenas de abstrações (RepositoryInterface via TicketRepository).
 */
final class TicketController
{
    /** @var TicketRepository */
    private $repository;

    public function __construct(TicketRepository $repository)
    {
        $this->repository = $repository;
    }

    // ── GET /api/tickets ──────────────────────────────────────────

    public function index(): void
    {
        JwtAuth::requireAuth();

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 15)));

        $filters = array_filter([
            'status'   => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? '',
        ]);

        if (!empty($filters)) {
            $tickets = $this->repository->findByFilters($filters);
            $this->respond(200, [
                'data' => array_map(function (Ticket $t) { return $t->toArray(); }, $tickets),
                'meta' => ['total' => count($tickets)],
            ]);
            return;
        }

        $this->respond(200, $this->repository->all($page, $perPage));
    }

    // ── GET /api/tickets/{id} ─────────────────────────────────────

    public function show(int $id): void
    {
        JwtAuth::requireAuth();

        $ticket = $this->repository->findById($id);
        if ($ticket) {
            $this->respond(200, ['data' => $ticket->toArray()]);
        } else {
            $this->respond(404, ['error' => true, 'message' => "Ticket #{$id} não encontrado."]);
        }
    }

    // ── POST /api/tickets ─────────────────────────────────────────

    public function store(): void
    {
        $payload = JwtAuth::requireAuth();
        $body    = $this->parseBody();

        // Injeta user_id do token autenticado
        $body['user_id'] = $payload->user_id ?? 0;

        $errors = Ticket::validate($body);
        if ($errors) {
            $this->respond(422, ['error' => true, 'messages' => $errors]);
        }

        $ticket = $this->repository->create($body);
        $this->respond(201, [
            'message' => 'Ticket criado com sucesso.',
            'data'    => $ticket->toArray(),
        ]);
    }

    // ── PUT /api/tickets/{id} ─────────────────────────────────────

    public function update(int $id): void
    {
        $payload = JwtAuth::requireAuth();
        $body    = $this->parseBody();

        $existing = $this->repository->findById($id);
        if (!$existing) {
            $this->respond(404, ['error' => true, 'message' => "Ticket #{$id} não encontrado."]);
        }

        // Apenas o criador ou admin pode editar
        $payloadUserId = $payload->user_id ?? -1;
        $payloadRole   = $payload->role ?? '';
        if ($existing->userId !== $payloadUserId && $payloadRole !== 'admin') {
            $this->respond(403, ['error' => true, 'message' => 'Você não tem permissão para editar este ticket.']);
        }

        $errors = Ticket::validate(array_merge($existing->toArray(), $body));
        if ($errors) {
            $this->respond(422, ['error' => true, 'messages' => $errors]);
        }

        $updated = $this->repository->update($id, $body);
        $this->respond(200, [
            'message' => 'Ticket atualizado.',
            'data'    => $updated !== null ? $updated->toArray() : null,
        ]);
    }

    // ── DELETE /api/tickets/{id} ──────────────────────────────────

    public function destroy(int $id): void
    {
        $payload = JwtAuth::requireAuth();
        JwtAuth::requireRole($payload, 'admin');

        $deleted = $this->repository->delete($id);
        if ($deleted) {
            $this->respond(200, ['message' => "Ticket #{$id} removido."]);
        } else {
            $this->respond(404, ['error' => true, 'message' => "Ticket #{$id} não encontrado."]);
        }
    }

    // ── HELPERS ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->respond(400, ['error' => true, 'message' => 'JSON inválido no corpo da requisição.']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function respond(int $code, array $data): void
    {
        http_response_code($code);
        echo json_encode(array_merge(['status' => $code], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
