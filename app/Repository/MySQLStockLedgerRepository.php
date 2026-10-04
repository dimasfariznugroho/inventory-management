<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedger;
use PDO;

/**
 * MySQL implementation for immutable Stock Ledger logging.
 */
class MySQLStockLedgerRepository implements StockLedgerRepositoryInterface
{
    public function __construct(
        private Database $database
    ) {
    }

    public function recordReceipt(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        int $createdBy
    ): int {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO stock_ledger (
                product_id, warehouse_id, transaction_type, quantity,
                reference_type, reference_id, notes, created_by
            ) VALUES (
                :product_id, :warehouse_id, :transaction_type, :quantity,
                :reference_type, :reference_id, :notes, :created_by
            )
        ');

        $stmt->execute([
            'product_id'       => $productId,
            'warehouse_id'     => $warehouseId,
            'transaction_type' => StockLedger::TYPE_RECEIPT,
            'quantity'         => $quantity,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'notes'            => $notes,
            'created_by'       => $createdBy,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function recordIssue(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        int $createdBy
    ): int {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO stock_ledger (
                product_id, warehouse_id, transaction_type, quantity,
                reference_type, reference_id, notes, created_by
            ) VALUES (
                :product_id, :warehouse_id, :transaction_type, :quantity,
                :reference_type, :reference_id, :notes, :created_by
            )
        ');

        $stmt->execute([
            'product_id'       => $productId,
            'warehouse_id'     => $warehouseId,
            'transaction_type' => StockLedger::TYPE_ISSUE,
            'quantity'         => $quantity,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'notes'            => $notes,
            'created_by'       => $createdBy,
        ]);

        return (int) $pdo->lastInsertId();
    }


    /**
     * @return StockLedger[]
     */
    public function findByReference(string $referenceType, int $referenceId): array
    {
        $pdo = $this->database->getConnection();
        $stmt = $pdo->prepare('
            SELECT sl.*, 
                   p.sku AS product_sku, p.name AS product_name, p.unit AS product_unit,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM stock_ledger sl
            JOIN products p ON p.id = sl.product_id
            JOIN warehouses w ON w.id = sl.warehouse_id
            JOIN users u ON u.id = sl.created_by
            WHERE sl.reference_type = :reference_type AND sl.reference_id = :reference_id
            ORDER BY sl.id ASC
        ');
        $stmt->execute([
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    /**
     * @return StockLedger[]
     */
    public function findAll(?int $limit = 100): array
    {
        $pdo = $this->database->getConnection();
        $limitClause = $limit !== null ? 'LIMIT ' . (int) $limit : '';
        $stmt = $pdo->query("
            SELECT sl.*, 
                   p.sku AS product_sku, p.name AS product_name, p.unit AS product_unit,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM stock_ledger sl
            JOIN products p ON p.id = sl.product_id
            JOIN warehouses w ON w.id = sl.warehouse_id
            JOIN users u ON u.id = sl.created_by
            ORDER BY sl.id DESC
            {$limitClause}
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findForReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $transactionType = null,
        ?int $warehouseId = null
    ): array {
        $pdo = $this->database->getConnection();
        $conditions = [];
        $params = [];

        if ($startDate !== null && trim($startDate) !== '') {
            $conditions[] = 'DATE(sl.created_at) >= ?';
            $params[] = trim($startDate);
        }

        if ($endDate !== null && trim($endDate) !== '') {
            $conditions[] = 'DATE(sl.created_at) <= ?';
            $params[] = trim($endDate);
        }

        if ($transactionType !== null && trim($transactionType) !== '') {
            $conditions[] = 'sl.transaction_type = ?';
            $params[] = trim($transactionType);
        }

        if ($warehouseId !== null && (int) $warehouseId > 0) {
            $conditions[] = 'sl.warehouse_id = ?';
            $params[] = (int) $warehouseId;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT sl.*, 
                   p.sku AS product_sku, p.name AS product_name, p.unit AS product_unit,
                   w.name AS warehouse_name,
                   u.name AS created_by_name
            FROM stock_ledger sl
            JOIN products p ON p.id = sl.product_id
            JOIN warehouses w ON w.id = sl.warehouse_id
            JOIN users u ON u.id = sl.created_by
            {$whereClause}
            ORDER BY sl.created_at DESC, sl.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    private function mapRowToEntity(array $row): StockLedger
    {
        return new StockLedger(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            productSku: (string) ($row['product_sku'] ?? ''),
            productName: (string) ($row['product_name'] ?? ''),
            productUnit: (string) ($row['product_unit'] ?? 'pcs'),
            warehouseId: (int) $row['warehouse_id'],
            warehouseName: (string) ($row['warehouse_name'] ?? ''),
            transactionType: (string) $row['transaction_type'],
            quantity: (int) $row['quantity'],
            referenceType: (string) $row['reference_type'],
            referenceId: (int) $row['reference_id'],
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            createdBy: (int) $row['created_by'],
            createdByName: (string) ($row['created_by_name'] ?? ''),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null
        );
    }
}
