<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProductRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;

/**
 * Service calculating role-specific real-time dynamic dashboard metrics (DASH-01).
 * All metrics are backed by aggregate SQL queries without any static hardcoded figures.
 */
class DashboardService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private PurchaseOrderRepositoryInterface $poRepository,
        private SalesOrderRepositoryInterface $soRepository
    ) {
    }

    /**
     * Aggregation metrics for Admin Dashboard (DASH-01).
     *
     * @return array{
     *     total_inventory_valuation: float,
     *     low_stock_count: int,
     *     total_products_count: int,
     *     po_counts: array<string, int>,
     *     so_counts: array<string, int>,
     *     pending_orders_summary: array<string, int>,
     *     low_stock_products: array
     * }
     */
    public function getAdminDashboardMetrics(): array
    {
        $valuation = $this->productRepository->getTotalInventoryValuation();
        $lowStockCount = $this->productRepository->getLowStockCount();
        $totalProducts = $this->productRepository->countAllWithStock();
        $poCounts = $this->poRepository->getOrderCountsByStatus();
        $soCounts = $this->soRepository->getOrderCountsByStatus();
        $lowStockProducts = $this->productRepository->getLowStockProducts(5);

        return [
            'total_inventory_valuation' => $valuation,
            'low_stock_count'           => $lowStockCount,
            'total_products_count'      => $totalProducts,
            'po_counts'                 => $poCounts,
            'so_counts'                 => $soCounts,
            'pending_orders_summary'    => [
                'po_ordered'            => $poCounts['Ordered'] ?? 0,
                'po_partially_received' => $poCounts['PartiallyReceived'] ?? 0,
                'so_pending_approval'   => $soCounts['PendingApproval'] ?? 0,
                'so_draft'              => $soCounts['Draft'] ?? 0,
            ],
            'low_stock_products'        => $lowStockProducts,
        ];
    }

    /**
     * Aggregation metrics for Sales Dashboard (DASH-01).
     * Scoped strictly to the authenticated sales staff ($salesUserId).
     *
     * @return array{
     *     my_so_counts: array<string, int>,
     *     my_total_sales_amount: float,
     *     my_recent_orders: \App\Entity\SalesOrder[]
     * }
     */
    public function getSalesDashboardMetrics(int $salesUserId): array
    {
        $soCounts = $this->soRepository->getOrderCountsByStatus($salesUserId);
        $totalSales = $this->soRepository->getTotalSalesAmount($salesUserId);
        $recentOrders = $this->soRepository->findAll(null, null, null, $salesUserId, 'DESC', 1, 5);

        return [
            'my_so_counts'          => $soCounts,
            'my_total_sales_amount' => $totalSales,
            'my_recent_orders'      => $recentOrders,
        ];
    }

    /**
     * Aggregation metrics for Warehouse Staff Dashboard (DASH-01).
     *
     * @return array{
     *     pending_receipt_queue: \App\Entity\PurchaseOrder[],
     *     pending_issue_queue: \App\Entity\SalesOrder[],
     *     low_stock_products: \App\Entity\Product[],
     *     low_stock_count: int
     * }
     */
    public function getWarehouseDashboardMetrics(): array
    {
        $pendingReceipts = $this->poRepository->getPendingReceiptQueue(10);
        $pendingIssues = $this->soRepository->getPendingIssueQueue(10);
        $lowStockProducts = $this->productRepository->getLowStockProducts(10);
        $lowStockCount = $this->productRepository->getLowStockCount();

        return [
            'pending_receipt_queue' => $pendingReceipts,
            'pending_issue_queue'   => $pendingIssues,
            'low_stock_products'    => $lowStockProducts,
            'low_stock_count'       => $lowStockCount,
        ];
    }
}
