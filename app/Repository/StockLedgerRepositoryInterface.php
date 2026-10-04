<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedger;

/**
 * Interface contract for immutable Stock Ledger audit records.
 */
interface StockLedgerRepositoryInterface
{
    public function recordReceipt(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        int $createdBy
    ): int;

    public function recordIssue(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $notes,
        int $createdBy
    ): int;


    /**
     * @return StockLedger[]
     */
    public function findByReference(string $referenceType, int $referenceId): array;

    /**
     * @return StockLedger[]
     */
    public function findAll(?int $limit = 100): array;

    /**
     * @return StockLedger[]
     */
    public function findForReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $transactionType = null,
        ?int $warehouseId = null
    ): array;
}
