-- Initialize database with proper charset and collation
CREATE DATABASE IF NOT EXISTS pix_withdrawal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create additional user for application
CREATE USER IF NOT EXISTS 'pix_user'@'%' IDENTIFIED BY 'pix_password';
GRANT ALL PRIVILEGES ON pix_withdrawal.* TO 'pix_user'@'%';

-- Set MySQL configurations for better performance
SET GLOBAL innodb_buffer_pool_size = 134217728; -- 128MB
SET GLOBAL max_connections = 200;
SET GLOBAL innodb_log_file_size = 50331648; -- 48MB

FLUSH PRIVILEGES;


-- Switch to the application database
USE pix_withdrawal;

-- Create account table
CREATE TABLE IF NOT EXISTS account (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    name VARCHAR(255) NOT NULL COMMENT 'Account holder name',
    balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Account balance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    INDEX idx_account_balance (balance),
    INDEX idx_account_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Account table for digital account management';

-- Create account_withdraw table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal transactions table';

-- Create account_withdraw_pix table
CREATE TABLE IF NOT EXISTS account_withdraw_pix (
    id CHAR(36) PRIMARY KEY COMMENT 'UUID primary key',
    account_withdraw_id CHAR(36) NOT NULL COMMENT 'Foreign key to account_withdraw',
    type ENUM('CPF', 'EMAIL', 'PHONE', 'RANDOM') NOT NULL COMMENT 'PIX key type',
    `key` VARCHAR(255) NOT NULL COMMENT 'PIX key value',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
    FOREIGN KEY (account_withdraw_id) REFERENCES account_withdraw(id) ON DELETE CASCADE,
    INDEX idx_withdraw_id (account_withdraw_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PIX withdrawal details table';

-- Insert test accounts with different balances for testing
INSERT INTO account (id, name, balance) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'João Silva', 1000.00),
('550e8400-e29b-41d4-a716-446655440001', 'Maria Santos', 5000.00),
('550e8400-e29b-41d4-a716-446655440002', 'Pedro Costa', 100.00);
