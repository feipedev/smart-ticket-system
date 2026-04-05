<?php
// ================================================================
// INTERFACES — Contratos do sistema (SOLID: Dependency Inversion)
// ================================================================
declare(strict_types=1);

namespace App\Interfaces;

/**
 * Contrato genérico de repositório.
 * Implementar este contrato garante que qualquer repositório
 * concreto exponha as operações CRUD padrão, independente
 * do banco subjacente (SQLite, MySQL, PostgreSQL, etc.).
 *
 * @template T
 */
interface RepositoryInterface
{
    /**
     * Retorna todos os registros com paginação opcional.
     *
     * @param int $page    Página atual (iniciando em 1)
     * @param int $perPage Registros por página
     * @return array
     */
    public function all(int $page = 1, int $perPage = 15): array;

    /**
     * Busca um registro pelo seu identificador primário.
     *
     * @param int $id
     * @return mixed
     */
    public function findById(int $id);

    /**
     * Persiste um novo registro.
     *
     * @param array<string, mixed> $data
     * @return mixed
     */
    public function create(array $data);

    /**
     * Atualiza um registro existente.
     *
     * @param int                  $id
     * @param array<string, mixed> $data
     * @return mixed
     */
    public function update(int $id, array $data);

    /**
     * Remove um registro pelo ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
