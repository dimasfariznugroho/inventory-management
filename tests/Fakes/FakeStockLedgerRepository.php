<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Entity\StockLedger;
use App\Repository\StockLedgerRepositoryInterface;

/**
 * Pure in-memory Fake for StockLedgerRepositoryInterface.
 */
class FakeStockLedgerRepository implements StockLedgerRepositoryInterface
{
    /** @var array<int, StockLedger> */
    private array $records = [];

    private int $nextId = 1;

    public function recordReceipt(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        int $createdBy
    ): int {
        $id = $this->nextId++;
        $this->records[$id] = new StockLedger(
            id: $id,
            productId: $productId,
            productSku: null,
            productName: null,
            productUnit: null,
            warehouseId: $warehouseId,
            warehouseName: null,
            transactionType: 'Receipt',
            quantity: $quantity,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $notes,
            createdBy: $createdBy,
            createdByName: null,
            createdAt: date('Y-m-d H:i:s')
        );

        return $id;
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
        $id = $this->nextId++;
        $this->records[$id] = new StockLedger(
            id: $id,
            productId: $productId,
            productSku: null,
            productName: null,
            productUnit: null,
            warehouseId: $warehouseId,
            warehouseName: null,
            transactionType: 'Issue',
            quantity: $quantity,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $notes,
            createdBy: $createdBy,
            createdByName: null,
            createdAt: date('Y-m-d H:i:s')
        );

        return $id;
    }

    public function findByReference(string $referenceType, int $referenceId): array
    {
        return array_values(array_filter($this->records, function (StockLedger $ledger) use ($referenceType, $referenceId) {
            return $ledger->getReferenceType() === $referenceType && $ledger->getReferenceId() === $referenceId;
        }));
    }

    public function findAll(?int $limit = 100): array
    {
        return array_slice(array_values($this->records), 0, $limit ?? 100);
    }

    public function findForReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $transactionType = null,
        ?int $warehouseId = null
    ): array {
        return array_values($this->records);
    }

    /**
     * @return StockLedger[]
     */
    public function getAllRecords(): array
    {
        return array_values($this->records);
    }
}
