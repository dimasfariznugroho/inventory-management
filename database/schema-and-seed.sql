-- ============================================================================
-- Database Schema and Seed Script
-- Project: Inventory & Order Management System
-- Database: MySQL 8.0+
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS stock_ledger;
DROP TABLE IF EXISTS sales_order_items;
DROP TABLE IF EXISTS sales_orders;
DROP TABLE IF EXISTS purchase_order_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS product_stock;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS warehouses;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. Table: users
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Sales', 'WarehouseStaff') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: warehouses
-- ----------------------------------------------------------------------------
CREATE TABLE warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_warehouses_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: categories
-- ----------------------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: products
-- ----------------------------------------------------------------------------
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    purchase_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    reorder_point INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT chk_products_purchase_price CHECK (purchase_price >= 0),
    CONSTRAINT chk_products_selling_price CHECK (selling_price >= 0),
    CONSTRAINT chk_products_reorder_point CHECK (reorder_point >= 0),
    INDEX idx_products_is_active (is_active),
    INDEX idx_products_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: product_stock
-- ----------------------------------------------------------------------------
CREATE TABLE product_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT,
    CONSTRAINT chk_stock_quantity CHECK (quantity >= 0),
    UNIQUE KEY uk_product_warehouse (product_id, warehouse_id),
    INDEX idx_stock_product (product_id),
    INDEX idx_stock_warehouse (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: suppliers
-- ----------------------------------------------------------------------------
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact VARCHAR(100) NULL,
    address TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: customers
-- ----------------------------------------------------------------------------
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact VARCHAR(100) NULL,
    address TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: purchase_orders
-- ----------------------------------------------------------------------------
CREATE TABLE purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled') NOT NULL DEFAULT 'Draft',
    created_by INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_po_status (status),
    INDEX idx_po_order_date (order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Table: purchase_order_items
-- ----------------------------------------------------------------------------
CREATE TABLE purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT chk_poi_qty_ordered CHECK (quantity_ordered > 0),
    CONSTRAINT chk_poi_qty_received CHECK (quantity_received >= 0),
    CONSTRAINT chk_poi_unit_price CHECK (unit_price >= 0),
    INDEX idx_poi_order (purchase_order_id),
    INDEX idx_poi_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. Table: sales_orders
-- ----------------------------------------------------------------------------
CREATE TABLE sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    so_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    status ENUM('Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled') NOT NULL DEFAULT 'Draft',
    created_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    order_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_so_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_so_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_so_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_so_status (status),
    INDEX idx_so_order_date (order_date),
    INDEX idx_so_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Table: sales_order_items
-- ----------------------------------------------------------------------------
CREATE TABLE sales_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_fulfilled INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_soi_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT chk_soi_qty_ordered CHECK (quantity_ordered > 0),
    CONSTRAINT chk_soi_qty_fulfilled CHECK (quantity_fulfilled >= 0),
    CONSTRAINT chk_soi_unit_price CHECK (unit_price >= 0),
    INDEX idx_soi_order (sales_order_id),
    INDEX idx_soi_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Table: stock_ledger
-- ----------------------------------------------------------------------------
CREATE TABLE stock_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('Receipt', 'Issue', 'Adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id INT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ledger_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_ledger_product_wh (product_id, warehouse_id),
    INDEX idx_ledger_created_at (created_at),
    INDEX idx_ledger_ref (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA (Phase 1 Initial Accounts)
-- Default Password for all seeded users: password123 (hashed with bcrypt)
-- ============================================================================

INSERT INTO users (id, name, email, password, role, is_active) VALUES
(1, 'Administrator System', 'admin@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'Admin', 1),
(2, 'Sarah Jenkins (Sales)', 'sales1@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'Sales', 1),
(3, 'Michael Wong (Sales)', 'sales2@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'Sales', 1),
(4, 'Budi Santoso (Warehouse)', 'warehouse1@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'WarehouseStaff', 1),
(5, 'Siti Rahma (Warehouse)', 'warehouse2@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'WarehouseStaff', 1),
(6, 'Inactive User (Deactivated)', 'inactive@inventory.local', '$2y$10$4ndKN7s6e7EFRA2mY3NaUOsmRlZsBHg3DOXdglsy2hpmoLzw6hQIq', 'Sales', 0);

-- ----------------------------------------------------------------------------
-- Phase 2 Master Data Seeds
-- ----------------------------------------------------------------------------

-- Categories
INSERT INTO categories (id, name, description) VALUES
(1, 'Elektronik & Gadget', 'Perangkat komputasi, laptop, layar monitor, dan periferal elektronik utama.'),
(2, 'Alat Tulis Kantor', 'Kertas HVS, alat tulis, binder, dan perlengkapan administrasi kantor.'),
(3, 'Perkakas & Hardware', 'Alat pertukangan, bor listrik, baut, perkakas teknik, dan perlengkapan utilitas.'),
(4, 'Aksesoris Komputer', 'Mouse, keyboard, kabel adapter, headset, dan aksesoris komputer penunjang.');

-- Warehouses (Multi-Location)
INSERT INTO warehouses (id, name, location, is_active) VALUES
(1, 'Gudang Utama Jakarta', 'Kawasan Industri Daan Mogot KM 12, Jakarta Barat', 1),
(2, 'Gudang Logistik Surabaya', 'Kawasan Industri SIER Rungkut, Surabaya Timur', 1),
(3, 'Gudang Distribusi Medan', 'Kawasan Industri Medan (KIM) II, Deli Serdang', 1);

-- Suppliers
INSERT INTO suppliers (id, name, contact, address, is_active) VALUES
(1, 'PT Maju Bersama Komputindo', '0812-3456-7890 (Bpk. Handoko)', 'Jl. Mangga Dua Raya No. 45, Jakarta Pusat', 1),
(2, 'CV Sentosa Stationary Abadi', '0819-8765-4321 (Ibu Melisa)', 'Jl. Rungkut Industri Raya No. 12, Surabaya', 1),
(3, 'PT Sumber Berkat Perkakas', '0811-2233-4455 (Bpk. Herman)', 'Jl. Gatot Subroto No. 88, Medan', 1);

-- Customers
INSERT INTO customers (id, name, contact, address, is_active) VALUES
(1, 'PT Retail Nusantara Megah', '0821-1122-3344 (Procurement Dept)', 'Jl. Sudirman Kav. 52, Jakarta Selatan', 1),
(2, 'Toko Makmur Elektronik', '0822-2233-4455 (Bpk. Gunawan)', 'Jl. Tunjungan No. 34, Surabaya', 1),
(3, 'CV Berkah Komputer Mandiri', '0823-3344-5566 (Ibu Rahayu)', 'Jl. Brigjend Katamso No. 15, Medan', 1);

-- Products (PRD-01)
INSERT INTO products (id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active) VALUES
(1, 'PRD-LAP-001', 'Laptop Pro Ultra 14 Inch', 1, 'unit', 12000000.00, 14500000.00, 15, NULL, 1),
(2, 'PRD-MOU-002', 'Mouse Wireless Silent Ergonomic', 4, 'pcs', 150000.00, 225000.00, 30, NULL, 1),
(3, 'PRD-KBD-003', 'Mechanical Keyboard RGB TKL', 4, 'pcs', 450000.00, 650000.00, 20, NULL, 1),
(4, 'PRD-PAP-004', 'Kertas HVS A4 80gsm 500 Lembar', 2, 'rim', 42000.00, 52000.00, 50, NULL, 1),
(5, 'PRD-DRL-005', 'Mesin Bor Cordless 12V Multifungsi', 3, 'unit', 380000.00, 520000.00, 10, NULL, 1),
(6, 'PRD-OLD-006', 'Monitor Tabung CRT 15 Inch (Arsip)', 1, 'unit', 100000.00, 150000.00, 5, NULL, 0),
(7, 'PRD-MON-007', 'Monitor LED 24 Inch Full HD IPS', 1, 'unit', 1800000.00, 2200000.00, 10, NULL, 1),
(8, 'PRD-PRN-008', 'Printer Inkjet Wireless All-in-One', 1, 'unit', 2100000.00, 2600000.00, 8, NULL, 1),
(9, 'PRD-UPS-009', 'UPS 1200VA Line Interactive', 1, 'unit', 1400000.00, 1750000.00, 5, NULL, 1),
(10, 'PRD-ROU-010', 'Router WiFi 6 Dual Band Gigabit', 1, 'unit', 650000.00, 850000.00, 12, NULL, 1),
(11, 'PRD-SWI-011', 'Switch Hub 16 Port Gigabit Managed', 1, 'unit', 1100000.00, 1450000.00, 6, NULL, 1),
(12, 'PRD-EXT-012', 'Harddisk Eksternal 2TB USB 3.0', 1, 'unit', 850000.00, 1100000.00, 15, NULL, 1),
(13, 'PRD-SSD-013', 'SSD NVMe M.2 1TB PCIe Gen4', 1, 'unit', 950000.00, 1250000.00, 20, NULL, 1),
(14, 'PRD-BOL-014', 'Pulpen Gel Hitam 0.5mm Box Isi 12', 2, 'box', 36000.00, 48000.00, 25, NULL, 1),
(15, 'PRD-BIN-015', 'Binder File Bantex Folio 7cm', 2, 'pcs', 28000.00, 38000.00, 30, NULL, 1),
(16, 'PRD-STP-016', 'Stapler Heavy Duty Max HD-50', 2, 'pcs', 45000.00, 62000.00, 15, NULL, 1),
(17, 'PRD-ENV-017', 'Amplop Coklat Tali Folio Pak 100', 2, 'pak', 65000.00, 85000.00, 20, NULL, 1),
(18, 'PRD-NOT-018', 'Sticky Notes Pastel 3x3 Inch', 2, 'pad', 12000.00, 18000.00, 40, NULL, 1),
(19, 'PRD-SCI-019', 'Gunting Kertas Stainless 8 Inch', 2, 'pcs', 18000.00, 26000.00, 20, NULL, 1),
(20, 'PRD-COR-020', 'Correction Tape 5mm x 12m', 2, 'pcs', 14000.00, 20000.00, 35, NULL, 1),
(21, 'PRD-MET-021', 'Meteran Baja Roll 5 Meter Rubber', 3, 'pcs', 32000.00, 45000.00, 15, NULL, 1),
(22, 'PRD-PLI-022', 'Tang Kombinasi 8 Inch Heavy Duty', 3, 'pcs', 55000.00, 78000.00, 12, NULL, 1),
(23, 'PRD-SCR-023', 'Obeng Set Presisi 32-in-1 Magnetik', 3, 'set', 75000.00, 105000.00, 10, NULL, 1),
(24, 'PRD-HAM-024', 'Palu Kambing Baja Gagang Fiber 16oz', 3, 'pcs', 48000.00, 68000.00, 15, NULL, 1),
(25, 'PRD-WRE-025', 'Kunci Inggris 10 Inch Chrome Vanadium', 3, 'pcs', 65000.00, 92000.00, 8, NULL, 1),
(26, 'PRD-SAW-026', 'Gergaji Kayu Hand Saw 18 Inch', 3, 'pcs', 52000.00, 74000.00, 10, NULL, 1),
(27, 'PRD-USB-027', 'Flashdisk USB 3.2 64GB Metal', 4, 'pcs', 65000.00, 90000.00, 25, NULL, 1),
(28, 'PRD-WEB-028', 'Webcam Full HD 1080p with Mic', 4, 'unit', 250000.00, 340000.00, 12, NULL, 1),
(29, 'PRD-HDS-029', 'Headset Gaming USB 7.1 Surround', 4, 'unit', 320000.00, 440000.00, 10, NULL, 1),
(30, 'PRD-HUB-030', 'USB Type-C Hub 7-in-1 HDMI LAN', 4, 'pcs', 185000.00, 260000.00, 15, NULL, 1),
(31, 'PRD-PAD-031', 'Mousepad Desk Mat Extended 80x30cm', 4, 'pcs', 45000.00, 65000.00, 30, NULL, 1),
(32, 'PRD-CAB-032', 'Kabel HDMI 2.1 8K Braided 2 Meter', 4, 'pcs', 55000.00, 80000.00, 20, NULL, 1);

-- Product Stock (WH-01 Multi-location stock per warehouse, version=1)
-- Products 1-6 Baseline Preserved
INSERT INTO product_stock (product_id, warehouse_id, quantity, version) VALUES
(1, 1, 25, 1),
(1, 2, 10, 1),
(1, 3, 0, 1),
(2, 1, 100, 1),
(2, 2, 50, 1),
(2, 3, 20, 1),
(3, 1, 8, 1),
(3, 2, 5, 1),
(3, 3, 0, 1),
(4, 1, 120, 1),
(4, 2, 80, 1),
(5, 1, 15, 1),
(5, 2, 5, 1),
(6, 1, 0, 1),
(6, 2, 0, 1),
-- Products 7-32 Multi-Warehouse Stock
(7, 1, 4, 1), (7, 2, 2, 1), (7, 3, 0, 1),
(8, 1, 2, 1), (8, 2, 1, 1), (8, 3, 0, 1),
(9, 1, 1, 1), (9, 2, 0, 1), (9, 3, 1, 1),
(10, 1, 25, 1), (10, 2, 15, 1), (10, 3, 5, 1),
(11, 1, 10, 1), (11, 2, 5, 1), (11, 3, 5, 1),
(12, 1, 30, 1), (12, 2, 20, 1), (12, 3, 10, 1),
(13, 1, 40, 1), (13, 2, 25, 1), (13, 3, 15, 1),
(14, 1, 60, 1), (14, 2, 40, 1), (14, 3, 20, 1),
(15, 1, 10, 1), (15, 2, 5, 1), (15, 3, 5, 1),
(16, 1, 15, 1), (16, 2, 10, 1), (16, 3, 5, 1),
(17, 1, 20, 1), (17, 2, 15, 1), (17, 3, 10, 1),
(18, 1, 50, 1), (18, 2, 30, 1), (18, 3, 20, 1),
(19, 1, 25, 1), (19, 2, 15, 1), (19, 3, 10, 1),
(20, 1, 40, 1), (20, 2, 25, 1), (20, 3, 15, 1),
(21, 1, 20, 1), (21, 2, 15, 1), (21, 3, 10, 1),
(22, 1, 5, 1), (22, 2, 3, 1), (22, 3, 0, 1),
(23, 1, 15, 1), (23, 2, 10, 1), (23, 3, 5, 1),
(24, 1, 20, 1), (24, 2, 10, 1), (24, 3, 5, 1),
(25, 1, 12, 1), (25, 2, 8, 1), (25, 3, 5, 1),
(26, 1, 15, 1), (26, 2, 10, 1), (26, 3, 5, 1),
(27, 1, 40, 1), (27, 2, 30, 1), (27, 3, 20, 1),
(28, 1, 5, 1), (28, 2, 2, 1), (28, 3, 1, 1),
(29, 1, 15, 1), (29, 2, 10, 1), (29, 3, 5, 1),
(30, 1, 20, 1), (30, 2, 15, 1), (30, 3, 10, 1),
(31, 1, 35, 1), (31, 2, 25, 1), (31, 3, 15, 1),
(32, 1, 40, 1), (32, 2, 30, 1), (32, 3, 20, 1);

-- ----------------------------------------------------------------------------
-- Phase 3 & 4 Initial Orders Seeds (14 Purchase Orders + 14 Sales Orders)
-- ----------------------------------------------------------------------------

-- Purchase Orders
INSERT INTO purchase_orders (id, po_number, supplier_id, warehouse_id, status, created_by, order_date) VALUES
(1, 'PO-20260901-0001', 1, 1, 'Draft', 4, '2026-09-01'),
(2, 'PO-20260902-0002', 2, 2, 'Draft', 4, '2026-09-02'),
(3, 'PO-20260903-0003', 3, 1, 'Draft', 5, '2026-09-03'),
(4, 'PO-20260904-0004', 1, 1, 'Ordered', 4, '2026-09-04'),
(5, 'PO-20260905-0005', 2, 2, 'Ordered', 4, '2026-09-05'),
(6, 'PO-20260906-0006', 3, 3, 'Ordered', 5, '2026-09-06'),
(7, 'PO-20260907-0007', 1, 1, 'PartiallyReceived', 4, '2026-09-07'),
(8, 'PO-20260908-0008', 2, 2, 'PartiallyReceived', 5, '2026-09-08'),
(9, 'PO-20260909-0009', 1, 1, 'Received', 4, '2026-09-09'),
(10, 'PO-20260910-0010', 2, 2, 'Received', 4, '2026-09-10'),
(11, 'PO-20260911-0011', 3, 3, 'Received', 5, '2026-09-11'),
(12, 'PO-20260912-0012', 1, 1, 'Received', 4, '2026-09-12'),
(13, 'PO-20260913-0013', 2, 2, 'Cancelled', 4, '2026-09-13'),
(14, 'PO-20260914-0014', 3, 1, 'Cancelled', 5, '2026-09-14');

-- Purchase Order Items
INSERT INTO purchase_order_items (id, purchase_order_id, product_id, quantity_ordered, quantity_received, unit_price) VALUES
(1, 1, 10, 10, 0, 650000.00),
(2, 2, 14, 20, 0, 36000.00),
(3, 3, 21, 15, 0, 32000.00),
(4, 4, 11, 5, 0, 1100000.00),
(5, 5, 16, 10, 0, 45000.00),
(6, 6, 23, 8, 0, 75000.00),
(7, 7, 12, 20, 10, 850000.00),
(8, 8, 17, 30, 15, 65000.00),
(9, 9, 10, 15, 15, 650000.00),
(10, 10, 14, 25, 25, 36000.00),
(11, 11, 21, 20, 20, 32000.00),
(12, 12, 27, 30, 30, 65000.00),
(13, 13, 13, 10, 0, 950000.00),
(14, 14, 18, 50, 0, 12000.00);

-- Sales Orders
INSERT INTO sales_orders (id, so_number, customer_id, warehouse_id, status, created_by, approved_by, order_date) VALUES
(1, 'SO-20260901-0001', 1, 1, 'Draft', 2, NULL, '2026-09-01'),
(2, 'SO-20260902-0002', 2, 2, 'Draft', 3, NULL, '2026-09-02'),
(3, 'SO-20260903-0003', 3, 1, 'Draft', 2, NULL, '2026-09-03'),
(4, 'SO-20260904-0004', 1, 1, 'PendingApproval', 2, NULL, '2026-09-04'),
(5, 'SO-20260905-0005', 2, 2, 'PendingApproval', 3, NULL, '2026-09-05'),
(6, 'SO-20260906-0006', 3, 3, 'PendingApproval', 2, NULL, '2026-09-06'),
(7, 'SO-20260907-0007', 1, 1, 'Approved', 2, 1, '2026-09-07'),
(8, 'SO-20260908-0008', 2, 2, 'Approved', 3, 1, '2026-09-08'),
(9, 'SO-20260909-0009', 3, 1, 'Approved', 2, 1, '2026-09-09'),
(10, 'SO-20260910-0010', 1, 1, 'Fulfilled', 2, 1, '2026-09-10'),
(11, 'SO-20260911-0011', 2, 2, 'Fulfilled', 3, 1, '2026-09-11'),
(12, 'SO-20260912-0012', 3, 3, 'Fulfilled', 2, 1, '2026-09-12'),
(13, 'SO-20260913-0013', 1, 1, 'Cancelled', 2, NULL, '2026-09-13'),
(14, 'SO-20260914-0014', 2, 2, 'Cancelled', 3, NULL, '2026-09-14');

-- Sales Order Items
INSERT INTO sales_order_items (id, sales_order_id, product_id, quantity_ordered, quantity_fulfilled, unit_price) VALUES
(1, 1, 10, 2, 0, 850000.00),
(2, 2, 14, 5, 0, 48000.00),
(3, 3, 21, 3, 0, 45000.00),
(4, 4, 11, 1, 0, 1450000.00),
(5, 5, 16, 2, 0, 62000.00),
(6, 6, 23, 2, 0, 105000.00),
(7, 7, 12, 3, 0, 1100000.00),
(8, 8, 17, 5, 0, 85000.00),
(9, 9, 27, 4, 0, 90000.00),
(10, 10, 10, 2, 2, 850000.00),
(11, 11, 14, 4, 4, 48000.00),
(12, 12, 21, 2, 2, 45000.00),
(13, 13, 13, 1, 0, 1250000.00),
(14, 14, 18, 10, 0, 18000.00);

-- Initial Stock Ledger Entries for Received POs and Fulfilled SOs
INSERT INTO stock_ledger (product_id, warehouse_id, transaction_type, quantity, reference_type, reference_id, notes, created_by, created_at) VALUES
(12, 1, 'Receipt', 10, 'PurchaseOrder', 7, 'Penerimaan parsial barang PO-20260907-0007', 4, '2026-09-07 10:00:00'),
(17, 2, 'Receipt', 15, 'PurchaseOrder', 8, 'Penerimaan parsial barang PO-20260908-0008', 5, '2026-09-08 11:00:00'),
(10, 1, 'Receipt', 15, 'PurchaseOrder', 9, 'Penerimaan penuh barang PO-20260909-0009', 4, '2026-09-09 14:00:00'),
(14, 2, 'Receipt', 25, 'PurchaseOrder', 10, 'Penerimaan penuh barang PO-20260910-0010', 4, '2026-09-10 15:30:00'),
(21, 3, 'Receipt', 20, 'PurchaseOrder', 11, 'Penerimaan penuh barang PO-20260911-0011', 5, '2026-09-11 09:15:00'),
(27, 1, 'Receipt', 30, 'PurchaseOrder', 12, 'Penerimaan penuh barang PO-20260912-0012', 4, '2026-09-12 16:45:00'),
(10, 1, 'Issue', 2, 'SalesOrder', 10, 'Pengeluaran barang pemenuhan SO-20260910-0010', 4, '2026-09-10 16:00:00'),
(14, 2, 'Issue', 4, 'SalesOrder', 11, 'Pengeluaran barang pemenuhan SO-20260911-0011', 5, '2026-09-11 11:20:00'),
(21, 3, 'Issue', 2, 'SalesOrder', 12, 'Pengeluaran barang pemenuhan SO-20260912-0012', 4, '2026-09-12 14:10:00');


