<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * BaseModel
 *
 * Provides a shared PDO connection and generic CRUD helpers.
 * All models extend this class.
 */
abstract class BaseModel
{
    protected PDO $db;

    /** Subclasses must declare the table name */
    protected string $table = '';

    /** Primary key column */
    protected string $primaryKey = 'id';

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    // ─── Generic Finders ──────────────────────────────────────────────────────

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findAll(string $orderBy = 'created_at DESC', int $limit = 1000): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findWhere(array $conditions, string $orderBy = 'created_at DESC'): array
    {
        $clauses = [];
        $params  = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "{$col} = ?";
            $params[]  = $val;
        }
        $where = implode(' AND ', $clauses);
        $stmt  = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findOneWhere(array $conditions): array|false
    {
        $rows = $this->findWhere($conditions);
        return $rows[0] ?? false;
    }

    // ─── Generic Write Ops ────────────────────────────────────────────────────

    public function create(array $data): int|false
    {
        $cols   = array_keys($data);
        $marks  = array_fill(0, count($cols), '?');
        $sql    = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $cols),
            implode(', ', $marks)
        );
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($data));
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("[BaseModel::create] {$e->getMessage()}");
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $sets  = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]  = "{$col} = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("[BaseModel::update] {$e->getMessage()}");
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?"
            );
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("[BaseModel::delete] {$e->getMessage()}");
            return false;
        }
    }

    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int) $this->db->query(
                "SELECT COUNT(*) FROM {$this->table}"
            )->fetchColumn();
        }
        $clauses = [];
        $params  = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "{$col} = ?";
            $params[]  = $val;
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $clauses)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ─── Pagination Helper ────────────────────────────────────────────────────

    /**
     * Paginate a raw SQL query.
     *
     * @return array{ data: array, total: int, totalPages: int, page: int, perPage: int }
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 12): array
    {
        // Count total rows
        $countSql  = preg_replace('/SELECT\s+.+?\s+FROM\s+/is', 'SELECT COUNT(*) FROM ', $sql);
        // Strip ORDER BY from count query
        $countSql  = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', $countSql);
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("{$sql} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return compact('data', 'total', 'totalPages', 'page', 'perPage');
    }
}
