<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthSession;
use App\Service\ProductService;

/**
 * Controller handling JSON API endpoints (API-01).
 * Strictly enforces session authentication and JSON response structure.
 */
class ApiController
{
    public function __construct(
        private ProductService $productService
    ) {
    }

    /**
     * GET /api/products/{sku}/availability
     * Returns multi-warehouse stock breakdown for a specific product SKU.
     */
    public function productAvailability(string $sku): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Enforce session authentication (same as web pages)
        if (!AuthSession::isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'error'   => 'Unauthorized',
                'message' => 'Autentikasi sesi diperlukan untuk mengakses endpoint ketersediaan produk.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $availability = $this->productService->getProductAvailabilityBySku(trim($sku));
        if ($availability === null) {
            http_response_code(404);
            echo json_encode([
                'status'  => 'error',
                'code'    => 404,
                'error'   => 'Not Found',
                'message' => sprintf("Produk dengan SKU '%s' tidak ditemukan dalam katalog.", htmlspecialchars($sku)),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'code'   => 200,
            'data'   => $availability,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
