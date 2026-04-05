<?php
// ================================================================
// TICKET MODEL — Entidade de domínio com validação e hydration
// ================================================================
declare(strict_types=1);

namespace App\Models;

/**
 * Representa uma Ordem de Serviço (ticket) no sistema.
 * Encapsula dados, regras de validação e serialização JSON.
 */
final class Ticket
{
    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALID_STATUSES   = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED];
    public const VALID_PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

    public ?int $id;
    public string $title;
    public string $description;
    public string $status;
    public string $priority;
    public int $userId;
    public ?string $assignedTo;
    public ?string $category;
    public ?string $resolvedAt;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        ?int    $id,
        string  $title,
        string  $description,
        string  $status,
        string  $priority,
        int     $userId,
        ?string $assignedTo,
        ?string $category,
        ?string $resolvedAt,
        string  $createdAt,
        string  $updatedAt
    ) {
        $this->id          = $id;
        $this->title       = $title;
        $this->description = $description;
        $this->status      = $status;
        $this->priority    = $priority;
        $this->userId      = $userId;
        $this->assignedTo  = $assignedTo;
        $this->category    = $category;
        $this->resolvedAt  = $resolvedAt;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
    }

    /**
     * Cria uma instância a partir de um array (ex: row do PDO).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int) $data['id'] : null,
            (string) (isset($data['title'])       ? $data['title']       : ''),
            (string) (isset($data['description'])  ? $data['description'] : ''),
            (string) (isset($data['status'])       ? $data['status']      : self::STATUS_OPEN),
            (string) (isset($data['priority'])     ? $data['priority']    : self::PRIORITY_MEDIUM),
            (int)    (isset($data['user_id'])      ? $data['user_id']     : 0),
            isset($data['assigned_to']) ? (string) $data['assigned_to'] : null,
            isset($data['category'])    ? (string) $data['category']    : null,
            isset($data['resolved_at']) ? (string) $data['resolved_at'] : null,
            (string) (isset($data['created_at'])   ? $data['created_at']  : date('Y-m-d H:i:s')),
            (string) (isset($data['updated_at'])   ? $data['updated_at']  : date('Y-m-d H:i:s'))
        );
    }

    /**
     * Valida os dados de entrada antes de criar/atualizar um ticket.
     *
     * @param array<string, mixed> $data
     * @return array<string> Lista de erros (vazia se válido)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['title']) || strlen(trim((string) $data['title'])) < 3) {
            $errors[] = 'O campo "title" é obrigatório e deve ter no mínimo 3 caracteres.';
        }

        if (empty($data['description']) || strlen(trim((string) $data['description'])) < 10) {
            $errors[] = 'O campo "description" é obrigatório e deve ter no mínimo 10 caracteres.';
        }

        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $errors[] = 'Status inválido. Use: ' . implode(', ', self::VALID_STATUSES);
        }

        if (isset($data['priority']) && !in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            $errors[] = 'Priority inválida. Use: ' . implode(', ', self::VALID_PRIORITIES);
        }

        return $errors;
    }

    /**
     * Serializa o ticket para JSON (camelCase para a API).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'userId'      => $this->userId,
            'assignedTo'  => $this->assignedTo,
            'category'    => $this->category,
            'resolvedAt'  => $this->resolvedAt,
            'createdAt'   => $this->createdAt,
            'updatedAt'   => $this->updatedAt,
        ];
    }
}
