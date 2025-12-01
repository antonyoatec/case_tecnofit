#!/bin/sh
set -e

echo "Waiting for MySQL to be ready..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    echo "MySQL is unavailable - sleeping"
    sleep 2
done

echo "MySQL is up - executing migrations"

# Create database if not exists
php -r "
\$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');
\$pdo->exec('CREATE DATABASE IF NOT EXISTS ${DB_DATABASE}');
echo 'Database ${DB_DATABASE} ready\n';
"

# Execute SQL setup directly
php -r "
\$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');

// Create account table
\$pdo->exec(\"
CREATE TABLE IF NOT EXISTS account (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    name VARCHAR(255) NOT NULL COMMENT 'Account holder name',
    balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Account balance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    INDEX idx_account_balance (balance),
    INDEX idx_account_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Account table for digital account management'
\");

// Create account_withdraw table
\$pdo->exec(\"
CREATE TABLE IF NOT EXISTS account_withdraw (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    account_id CHAR(36) NOT NULL COMMENT 'Foreign key to account',
    method VARCHAR(50) NOT NULL COMMENT 'Withdrawal method (PIX, TED, etc)',
    amount DECIMAL(15, 2) NOT NULL COMMENT 'Withdrawal amount',
    scheduled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Is this a scheduled withdrawal',
    scheduled_for DATETIME NULL COMMENT 'When to process scheduled withdrawal',
    status ENUM('PENDING', 'PROCESSING', 'DONE', 'REJECTED') NOT NULL DEFAULT 'PENDING' COMMENT 'Withdrawal status',
    error_reason TEXT NULL COMMENT 'Error message if rejected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    processed_at TIMESTAMP NULL COMMENT 'When withdrawal was processed',
    FOREIGN KEY (account_id) REFERENCES account(id) ON DELETE CASCADE,
    INDEX idx_status_scheduled (status, scheduled_for),
    INDEX idx_account_status (account_id, status),
    INDEX idx_processing_queue (status, scheduled_for, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal transactions table'
\");

// Create account_withdraw_pix table
\$pdo->exec(\"
CREATE TABLE IF NOT EXISTS account_withdraw_pix (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    account_withdraw_id CHAR(36) NOT NULL COMMENT 'Foreign key to account_withdraw',
    type ENUM('CPF', 'EMAIL', 'PHONE', 'RANDOM') NOT NULL COMMENT 'PIX key type',
    pix_key VARCHAR(255) NOT NULL COMMENT 'PIX key value',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    FOREIGN KEY (account_withdraw_id) REFERENCES account_withdraw(id) ON DELETE CASCADE,
    INDEX idx_withdraw_id (account_withdraw_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PIX withdrawal details table'
\");

// Insert test account if not exists
\$stmt = \$pdo->query(\"SELECT COUNT(*) FROM account WHERE id = '550e8400-e29b-41d4-a716-446655440000'\");
if (\$stmt->fetchColumn() == 0) {
    \$pdo->exec(\"
        INSERT INTO account (id, name, balance) VALUES 
        ('550e8400-e29b-41d4-a716-446655440000', 'Test Account', 1000.00)
    \");
    echo 'Test account created with balance R$ 1000.00\n';
}

echo 'Database setup completed successfully\n';
"

echo "Starting Hyperf application..."
exec php bin/hyperf.php start
