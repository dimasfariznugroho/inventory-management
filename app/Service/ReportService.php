<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;

/**
 * Service generating standardized CSV report streams (REPORT-01).
 * Uses identical parameterized queries matching the reporting/dashboard domains.
 */
class ReportService
{
    public function __construct(
        private StockLedgerRepositoryInterface $stockLedgerRepository,
        private PurchaseOrderRepositoryInterface $poRepository,
        private SalesOrderRepositoryInterface $soRepository
    ) {
    }

    public function exportStockLedgerCsv(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $transactionType = null,
        ?int $warehouseId = null
    ): string {
        $records = $this->stockLedgerRepository->findForReport($startDate, $endDate, $transactionType, $warehouseId);

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        // CSV Header
        fputcsv($fp, [
            'ID',
            'Tanggal',
            'SKU',
            'Nama Produk',
            'Gudang',
            'Tipe Transaksi',
            'Kuantitas',
            'Satuan',
            'Tipe Referensi',
            'ID Referensi',
            'Dicatat Oleh',
            'Catatan',
        ]);

        foreach ($records as $r) {
            fputcsv($fp, [
                $r->getId(),
                $r->getCreatedAt() ?? '',
                $r->getProductSku(),
                $r->getProductName(),
                $r->getWarehouseName(),
                $r->getTransactionType(),
                $r->getQuantity(),
                $r->getProductUnit(),
                $r->getReferenceType(),
                $r->getReferenceId(),
                $r->getCreatedByName(),
                $r->getNotes() ?? '',
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }

    public function exportOrdersCsv(
        string $type,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null
    ): string {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        if (strtolower($type) === 'po' || strtolower($type) === 'purchase') {
            $orders = $this->poRepository->findForReport($startDate, $endDate, $status);

            fputcsv($fp, [
                'ID',
                'Nomor PO',
                'Tanggal Order',
                'Pemasok',
                'Gudang',
                'Status',
                'Dibuat Oleh',
                'Total Item',
                'Total Nilai',
            ]);

            foreach ($orders as $order) {
                $totalItems = count($order->getItems());
                $totalValue = 0.0;
                foreach ($order->getItems() as $item) {
                    $totalValue += $item->getQuantityOrdered() * $item->getUnitPrice();
                }

                fputcsv($fp, [
                    $order->getId(),
                    $order->getPoNumber(),
                    $order->getOrderDate(),
                    $order->getSupplierName(),
                    $order->getWarehouseName(),
                    $order->getStatus(),
                    $order->getCreatedByName(),
                    $totalItems,
                    number_format($totalValue, 2, '.', ''),
                ]);
            }
        } else {
            $orders = $this->soRepository->findForReport($startDate, $endDate, $status);

            fputcsv($fp, [
                'ID',
                'Nomor SO',
                'Tanggal Order',
                'Pelanggan',
                'Gudang',
                'Status',
                'Dibuat Oleh',
                'Disetujui Oleh',
                'Total Item',
                'Total Nilai',
            ]);

            foreach ($orders as $order) {
                $totalItems = count($order->getItems());
                $totalValue = 0.0;
                foreach ($order->getItems() as $item) {
                    $totalValue += $item->getQuantityOrdered() * $item->getUnitPrice();
                }

                fputcsv($fp, [
                    $order->getId(),
                    $order->getSoNumber(),
                    $order->getOrderDate(),
                    $order->getCustomerName(),
                    $order->getWarehouseName(),
                    $order->getStatus(),
                    $order->getCreatedByName(),
                    $order->getApprovedByName() ?? '-',
                    $totalItems,
                    number_format($totalValue, 2, '.', ''),
                ]);
            }
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }
}
