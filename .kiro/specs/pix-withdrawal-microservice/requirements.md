# Requirements Document

## Introduction

This document outlines the requirements for a production-ready digital account microservice focused on PIX withdrawals. The system will be built using PHP 8.2+ with the Hyperf 3.x framework, utilizing Swoole engine for high performance. The microservice will handle both immediate and scheduled PIX withdrawals with strict concurrency control to prevent race conditions and ensure account balance integrity.

**CRITICAL FOCUS AREAS:**
- **Performance**: High-throughput withdrawal processing
- **Observability**: Comprehensive logging and monitoring
- **Horizontal Scalability**: Multi-container deployment support
- **Security**: Race condition prevention and data integrity
- **Complete Dockerization**: Zero dependency on host environment

## Requirements

### Requirement 1

**User Story:** As a digital account holder, I want to perform immediate PIX withdrawals from my account, so that I can access my funds instantly when needed.

#### Acceptance Criteria

1. WHEN a user submits a withdrawal request without a scheduled date THEN the system SHALL process the withdrawal immediately
2. WHEN processing an immediate withdrawal THEN the system SHALL validate that the account has sufficient balance before proceeding
3. WHEN an immediate withdrawal is successful THEN the system SHALL update the account balance atomically
4. WHEN an immediate withdrawal is successful THEN the system SHALL send a confirmation email asynchronously
5. IF the account has insufficient balance THEN the system SHALL reject the withdrawal and return an appropriate error message

### Requirement 2

**User Story:** As a digital account holder, I want to schedule PIX withdrawals for future dates, so that I can plan my financial transactions in advance.

#### Acceptance Criteria

1. WHEN a user submits a withdrawal request with a future scheduled date THEN the system SHALL store the request with PENDING status
2. WHEN a user attempts to schedule a withdrawal for a past date THEN the system SHALL reject the request with a validation error
3. WHEN a scheduled withdrawal time arrives THEN the system SHALL process the withdrawal automatically via cron job
4. WHEN processing a scheduled withdrawal THEN the system SHALL validate account balance at processing time, not at scheduling time
5. WHEN a scheduled withdrawal fails due to insufficient balance THEN the system SHALL mark it as PROCESSED with status REJECTED and record "saldo insuficiente" as error reason
6. WHEN a scheduled withdrawal is processed successfully THEN the system SHALL mark it as PROCESSED with status DONE

### Requirement 3

**User Story:** As a system administrator, I want the withdrawal system to handle concurrent requests safely, so that account balances never become negative due to race conditions.

#### Acceptance Criteria

1. WHEN multiple withdrawal requests are processed simultaneously for the same account THEN the system SHALL use pessimistic locking to prevent race conditions
2. WHEN processing any withdrawal THEN the system SHALL use SELECT ... FOR UPDATE within a database transaction
3. WHEN a withdrawal transaction begins THEN the system SHALL lock the account record until the transaction completes
4. WHEN multiple containers process scheduled withdrawals THEN the system SHALL prevent duplicate processing of the same withdrawal
5. WHEN updating withdrawal status from PENDING to PROCESSING THEN the system SHALL use atomic operations to reserve records

### Requirement 4

**User Story:** As a system operator, I want PIX email key validation to ensure withdrawal requests are properly formatted, so that invalid transactions are caught early.

#### Acceptance Criteria

1. WHEN a user submits a PIX withdrawal request THEN the system SHALL validate the PIX email key format
2. WHEN an email PIX key is provided THEN the system SHALL validate it follows standard email format rules
6. IF PIX email validation fails THEN the system SHALL reject the request with specific validation error messages

### Requirement 5

**User Story:** As a digital account holder, I want to receive email notifications when my withdrawals are processed, so that I can track my transactions.

#### Acceptance Criteria

1. WHEN a withdrawal is successfully processed THEN the system SHALL send an email notification asynchronously
2. WHEN sending withdrawal notifications THEN the system SHALL include transaction date, amount, and PIX key in the email
3. WHEN the email sending process fails THEN the system SHALL NOT affect the withdrawal transaction success
4. WHEN using the notification system THEN the system SHALL use Hyperf's event/listener pattern for asynchronous processing
5. WHEN in development/testing environment THEN the system SHALL send emails through Mailhog for testing purposes

### Requirement 6

**User Story:** As a system architect, I want the withdrawal system to be extensible for future payment methods, so that new withdrawal types can be added without modifying existing code.

#### Acceptance Criteria

1. WHEN implementing withdrawal logic THEN the system SHALL use Strategy pattern with WithdrawMethodInterface
2. WHEN adding new withdrawal methods THEN the system SHALL NOT require changes to the core WithdrawService
3. WHEN processing withdrawals THEN the system SHALL use dependency injection to resolve the appropriate strategy
4. WHEN the system initializes THEN the system SHALL register all available withdrawal strategies
5. WHEN a new withdrawal method is needed THEN the system SHALL allow implementation through a new strategy class

### Requirement 7

**User Story:** As a system administrator, I want comprehensive logging and monitoring of withdrawal operations, so that I can track system performance and troubleshoot issues.

#### Acceptance Criteria

1. WHEN any withdrawal operation occurs THEN the system SHALL log the operation with appropriate detail level
2. WHEN a withdrawal fails THEN the system SHALL log the failure reason and relevant context
3. WHEN processing scheduled withdrawals THEN the system SHALL log batch processing statistics
4. WHEN database transactions occur THEN the system SHALL log transaction boundaries for debugging
5. WHEN system errors occur THEN the system SHALL log stack traces and error context for troubleshooting

### Requirement 8

**User Story:** As a DevOps engineer, I want the entire system to be containerized and production-ready, so that it can be deployed consistently across environments.

#### Acceptance Criteria

1. WHEN deploying the system THEN the system SHALL run entirely within Docker containers
2. WHEN setting up the environment THEN the system SHALL use Docker Compose for orchestration
3. WHEN the application starts THEN the system SHALL connect to MySQL 8 database container
4. WHEN running tests THEN the system SHALL use Mailhog container for email testing
5. WHEN scaling horizontally THEN the system SHALL support multiple application container instances
6. WHEN containers restart THEN the system SHALL maintain data persistence through proper volume mounting

### Requirement 9

**User Story:** As a system architect, I want the system to meet production-grade quality standards, so that it can handle real-world workloads safely and efficiently.

#### Acceptance Criteria

1. WHEN the system is deployed THEN it SHALL demonstrate high performance with sub-200ms response times for withdrawal requests
2. WHEN the system operates THEN it SHALL provide comprehensive observability through structured logging, metrics, and health checks
3. WHEN multiple application instances run THEN the system SHALL maintain horizontal scalability without data corruption or duplicate processing
4. WHEN the system handles sensitive operations THEN it SHALL implement security best practices including input validation, SQL injection prevention, and secure error handling
5. WHEN the Docker environment is built from scratch THEN it SHALL work without any dependencies on the host environment
6. WHEN architectural decisions are made THEN they SHALL be documented in README.md with rationale and alternative approaches considered