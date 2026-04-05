<?php
// ================================================================
// TICKET REPOSITORY — Implementação concreta (SQLite via PDO)
// Implementa RepositoryInterface — troca de banco sem alterar Controllers
// ================================================================
declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\RepositoryInterface;
use App\Models\Ticket;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Repositório concreto de Tickets usando PDO + SQLite.
 * Isolamento total de acesso a dados: Controllers não conhecem PDO.
 *
 * @implements RepositoryInterface<Ticket>
 */
final class TicketRepository implements RepositoryInterface
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── READ ──────────────────────────────────────────────────────

    public function all(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM tickets')
            ->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM tickets ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = array_map(function (array $row) { return Ticket::fromArray($row)->toArray(); }, $rows);

        return [
            'data' => $data,
            'meta' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function findById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Ticket::fromArray($row) : null;
    }

    /**
     * Filtra tickets por status e/ou prioridade.
     *
     * @param array<string, string> $filters
     * @return Ticket[]
     */
    public function findByFilters(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'priority = :priority';
            $params[':priority'] = $filters['priority'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $this->pdo->prepare("SELECT * FROM tickets {$where} ORDER BY created_at DESC");
        $stmt->execute($params);

        return array_map(
            function (array $row) { return Ticket::fromArray($row); },
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    // ── WRITE ─────────────────────────────────────────────────────

    public function create(array $data): Ticket
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('
            INSERT INTO tickets
                (title, description, status, priority, user_id, assigned_to, category, created_at, updated_at)
            VALUES
                (:title, :description, :status, :priority, :user_id, :assigned_to, :category, :created_at, :updated_at)
        ');

        $stmt->execute([
            ':title'       => trim($data['title']),
            ':description' => trim($data['description']),
            ':status'      => isset($data['status'])      ? $data['status']      : Ticket::STATUS_OPEN,
            ':priority'    => isset($data['priority'])    ? $data['priority']    : Ticket::PRIORITY_MEDIUM,
            ':user_id'     => isset($data['user_id'])     ? $data['user_id']     : 0,
            ':assigned_to' => isset($data['assigned_to']) ? $data['assigned_to'] : null,
            ':category'    => isset($data['category'])    ? $data['category']    : null,
            ':created_at'  => $now,
            ':updated_at'  => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $ticket = $this->findById($id);
        if ($ticket === null) {
            throw new RuntimeException("Ticket #{$id} não encontrado após criação.");
        }
        return $ticket;
    }

    public function update(int $id, array $data): ?Ticket
    {
        $ticket = $this->findById($id);
        if (!$ticket) return null;

        $fields = [];
        $params = [':id' => $id, ':updated_at' => date('Y-m-d H:i:s')];

        $allowed = ['title', 'description', 'status', 'priority', 'assigned_to', 'category'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Marca resolved_at quando status muda para resolved
        if (isset($data['status']) && $data['status'] === Ticket::STATUS_RESOLVED) {
            $fields[] = 'resolved_at = :resolved_at';
            $params[':resolved_at'] = date('Y-m-d H:i:s');
        }

        if (empty($fields)) return $ticket;

        $fields[] = 'updated_at = :updated_at';
        $sql = 'UPDATE tickets SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
