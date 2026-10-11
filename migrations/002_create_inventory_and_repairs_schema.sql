-- TV Repair Workshop Management System - Inventory, Repairs, Payments & Finance Schema

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    alt_phone VARCHAR(30) NULL,
    address TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
);

CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);

CREATE TABLE IF NOT EXISTS part_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
);

CREATE TABLE IF NOT EXISTS parts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name VARCHAR(150) NOT NULL,
    serial_number VARCHAR(100) NULL,
    quantity INTEGER NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    min_stock INTEGER NOT NULL DEFAULT 1,
    cost_price BIGINT NULL, -- NULL means cost not set
    cost_currency CHAR(3) NOT NULL DEFAULT 'USD',
    selling_price BIGINT NOT NULL DEFAULT 0,
    selling_currency CHAR(3) NOT NULL DEFAULT 'USD',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES part_categories(id)
);

CREATE INDEX IF NOT EXISTS idx_parts_category ON parts(category_id);
CREATE INDEX IF NOT EXISTS idx_parts_name ON parts(name);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    part_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    repair_id INTEGER NULL,
    quantity_change INTEGER NOT NULL,
    quantity_after INTEGER NOT NULL,
    movement_type VARCHAR(30) NOT NULL CHECK (movement_type IN ('PURCHASE', 'REPAIR_USE', 'REPAIR_RETURN', 'MANUAL_ADJUST')),
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (part_id) REFERENCES parts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS exchange_rates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rate_usd_to_iqd BIGINT NOT NULL, -- e.g. 1500 dinars per 1 USD
    set_by_user_id INTEGER NOT NULL,
    effective_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (set_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS repairs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    technician_id INTEGER NULL,
    debt_approved_by_user_id INTEGER NULL,
    tv_brand VARCHAR(50) NOT NULL,
    tv_model VARCHAR(50) NOT NULL,
    tv_serial VARCHAR(50) NULL,
    fault_description TEXT NOT NULL,
    diagnosis TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'CHECK_IN' CHECK (status IN (
        'CHECK_IN', 'PENDING_ACCEPTANCE', 'PENDING_PARTS', 'IN_PROGRESS', 
        'READY_FOR_PICKUP', 'REPAIRED', 'PICKED_UP', 'DIDNT_AGREE', 'DIDNT_REPAIR'
    )),
    currency CHAR(3) NOT NULL DEFAULT 'IQD' CHECK (currency IN ('IQD', 'USD')),
    labor_fee BIGINT NOT NULL DEFAULT 0,
    total_charged BIGINT NOT NULL DEFAULT 0, -- Server computed: labor + sum(parts)
    version INTEGER NOT NULL DEFAULT 1,
    ready_at DATETIME NULL,
    picked_up_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (technician_id) REFERENCES users(id),
    FOREIGN KEY (debt_approved_by_user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_repairs_status ON repairs(status);
CREATE INDEX IF NOT EXISTS idx_repairs_customer ON repairs(customer_id);
CREATE INDEX IF NOT EXISTS idx_repairs_tech ON repairs(technician_id);

CREATE TABLE IF NOT EXISTS repair_parts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    part_id INTEGER NOT NULL,
    added_by_user_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_selling_price_original BIGINT NOT NULL,
    selling_currency_original CHAR(3) NOT NULL,
    unit_selling_price_charged BIGINT NOT NULL, -- in repair's currency
    unit_cost_original BIGINT NULL, -- in original currency, NULL if not set
    cost_currency_original CHAR(3) NULL,
    unit_cost_converted BIGINT NULL, -- in repair's currency, NULL if not set
    exchange_rate_snapshot BIGINT NULL,
    price_edit_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id),
    FOREIGN KEY (added_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS repair_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    from_status VARCHAR(30) NOT NULL,
    to_status VARCHAR(30) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    amount_paid BIGINT NOT NULL, -- in paid_currency
    paid_currency CHAR(3) NOT NULL CHECK (paid_currency IN ('IQD', 'USD')),
    exchange_rate_snapshot BIGINT NOT NULL, -- Snapshot of Owner rate USD to IQD
    amount_credited BIGINT NOT NULL, -- credited to repair in repair's currency
    payment_method VARCHAR(30) NOT NULL CHECK (payment_method IN ('CASH', 'CARD', 'BANK_TRANSFER')),
    is_reversal TINYINT(1) NOT NULL DEFAULT 0,
    reverses_payment_id INTEGER NULL UNIQUE, -- Only voided once!
    reason TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reverses_payment_id) REFERENCES payments(id)
);

CREATE TABLE IF NOT EXISTS debt_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    promised_pay_date DATE NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS debt_writeoffs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    amount_written_off BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS repair_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NOT NULL,
    uploaded_by_user_id INTEGER NOT NULL,
    file_name VARCHAR(100) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INTEGER NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS part_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    repair_id INTEGER NULL,
    part_id INTEGER NULL,
    requested_by_user_id INTEGER NOT NULL,
    managed_by_user_id INTEGER NULL,
    part_name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    photo_path VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'REQUESTED' CHECK (status IN ('REQUESTED', 'ORDERED', 'RECEIVED', 'CANCELLED')),
    resolution_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE SET NULL,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
    FOREIGN KEY (managed_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entered_by_user_id INTEGER NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(30) NOT NULL CHECK (category IN ('RENT', 'SALARIES', 'ELECTRICITY', 'SUPPLIES', 'OTHER')),
    amount BIGINT NOT NULL,
    currency CHAR(3) NOT NULL CHECK (currency IN ('IQD', 'USD')),
    note TEXT NOT NULL,
    is_reversal TINYINT(1) NOT NULL DEFAULT 0,
    reverses_expense_id INTEGER NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entered_by_user_id) REFERENCES users(id),
    FOREIGN KEY (reverses_expense_id) REFERENCES expenses(id)
);
