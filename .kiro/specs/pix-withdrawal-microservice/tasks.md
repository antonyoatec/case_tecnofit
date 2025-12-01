# Implementation Plan

- [x] 1. Set up project structure and Docker environment
  - Create Hyperf 3.x project structure with proper directory organization
  - Configure Docker and Docker Compose with PHP 8.2+, MySQL 8, and Mailhog containers
  - Set up development environment with proper volume mounting and networking
  - Configure Hyperf application with Swoole engine and basic middleware
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [x] 2. Create database migrations and base models
  - Write migration for account table with UUID primary key and balance field
  - Write migration for account_withdraw table with status enum and scheduling fields
  - Write migration for account_withdraw_pix table with PIX key type and value
  - Create Eloquent models for Account, AccountWithdraw, and AccountWithdrawPix entities
  - Configure model relationships and implement UUID generation
  - _Requirements: 1.1, 1.3, 2.1, 2.2_

- [x] 3. Implement core DTOs and validation classes
  - Create WithdrawRequestDto with validation rules for amount, PIX email key, and scheduling
  - Create ProcessResultDto for service layer response handling
  - Implement PIX email validation (simple email format validation only)
  - Create ValidationResult class for structured validation responses
  - Write unit tests for all DTO classes and validation logic
  - _Requirements: 4.1, 4.2, 4.6_

- [x] 4. Create repository interfaces and implementations
  - Define AccountRepositoryInterface with findByIdForUpdate method for pessimistic locking
  - Define AccountWithdrawRepositoryInterface with atomic status update methods
  - Define AccountWithdrawPixRepositoryInterface for PIX-specific data operations
  - Implement concrete repository classes with proper database transaction handling
  - Write unit tests for repository methods using database transactions
  - _Requirements: 3.1, 3.2, 3.3, 3.5_

- [x] 5. Implement Strategy pattern for withdrawal methods
  - Create WithdrawMethodInterface with supports, validate, and process methods
  - Implement PixWithdrawStrategy with PIX-specific validation and processing logic
  - Create strategy factory/registry for dynamic strategy resolution
  - Configure dependency injection container to register withdrawal strategies
  - Write unit tests for strategy pattern implementation and PIX strategy
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 6. Develop core service layer with concurrency control
  - Implement WithdrawService with pessimistic locking using SELECT ... FOR UPDATE
  - Create AccountService for balance validation and account operations
  - Implement database transaction management with proper rollback handling
  - Add concurrency exception handling for race condition scenarios
  - Write unit tests for service layer with mocked repositories and transaction scenarios
  - _Requirements: 3.1, 3.2, 3.3, 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 7. Implement immediate withdrawal processing
  - Create immediate withdrawal flow in WithdrawService using strategy pattern
  - Implement balance validation and atomic balance updates
  - Add proper error handling for insufficient balance and validation failures
  - Integrate PIX key validation with withdrawal processing
  - Write integration tests for immediate withdrawal scenarios including edge cases
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 8. Implement scheduled withdrawal functionality
  - Create scheduled withdrawal creation logic with future date validation
  - Implement PENDING status assignment for scheduled withdrawals
  - Add validation to prevent scheduling withdrawals in the past
  - Create database queries for retrieving scheduled withdrawals efficiently
  - Write unit tests for scheduled withdrawal creation and validation logic
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 9. Create REST API controller and routes
  - Implement AccountController with POST /account/{id}/balance/withdraw endpoint
  - Add request validation middleware for JSON payload validation
  - Implement proper HTTP response formatting for success and error cases
  - Add route parameter validation for account ID format
  - Write API integration tests for all endpoint scenarios and error conditions
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 4.6_

- [x] 10. Implement event system for asynchronous notifications
  - Create WithdrawProcessedEvent with withdrawal details for email notifications
  - Implement event listener for handling withdrawal notification events
  - Configure Hyperf event dispatcher for asynchronous event processing
  - Create NotificationService for email composition and sending
  - Write unit tests for event system and notification service
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 11. Develop cron job for scheduled withdrawal processing
  - Create ProcessScheduledWithdraw cron process class in Hyperf
  - Implement atomic status updates to prevent duplicate processing across containers
  - Add batch processing logic with configurable limits (50 withdrawals per batch)
  - When insufficient balance is detected, mark withdrawal as PROCESSED with status REJECTED and error_reason "saldo insuficiente"
  - When withdrawal succeeds, mark as PROCESSED with status DONE
  - Implement proper error handling and logging for failed scheduled withdrawals
  - Write integration tests for cron job processing and multi-container safety
  - _Requirements: 3.4, 3.5, 2.3, 2.4, 2.5, 2.6_

- [x] 12. Configure email integration with Mailhog
  - Configure Hyperf mailer component to use Mailhog SMTP in development
  - Implement email templates for withdrawal confirmation notifications
  - Add email content with transaction date, amount, and PIX key information
  - Configure production-ready email settings with environment-based configuration
  - Write integration tests for email sending using Mailhog test environment
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 13. Add comprehensive logging and monitoring
  - Implement structured logging for all withdrawal operations using Hyperf logger
  - Add transaction boundary logging for debugging database operations
  - Create performance metrics logging for withdrawal processing times
  - Implement error logging with stack traces and contextual information
  - Write tests to verify logging output and log levels
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 14. Implement comprehensive error handling
  - Create custom exception hierarchy for withdrawal-specific errors
  - Implement global exception handler for API error responses
  - Add validation error formatting with detailed field-level messages
  - Create error response DTOs with consistent error code structure
  - Write unit tests for exception handling and error response formatting
  - _Requirements: 1.5, 2.5, 4.6, 7.2, 7.5_

- [x] 15. Create database seeders and test data
  - Create account seeder with test accounts having various balance amounts
  - Create withdrawal history seeder for testing scheduled withdrawal scenarios
  - Implement factory classes for generating test data in different scenarios
  - Add database reset functionality for clean test environments
  - Write helper methods for creating test scenarios in integration tests
  - _Requirements: 1.1, 2.1, 3.1_

- [ ] 16. Write comprehensive integration tests
  - Create end-to-end tests for complete withdrawal workflows
  - Implement concurrency tests using multiple simultaneous requests
  - Add tests for scheduled withdrawal processing with time manipulation
  - Create database transaction rollback tests for error scenarios
  - Write performance tests for high-load withdrawal processing
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 1.1, 1.2, 1.3, 2.3, 2.4_

- [ ] 17. Configure production deployment setup
  - Optimize Docker images for production with multi-stage builds
  - Configure environment-specific settings for database and email
  - Set up health check endpoints for container orchestration
  - Configure proper logging levels and log rotation for production
  - Add graceful shutdown handling for Swoole server and database connections
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [ ] 18. Implement performance optimizations
  - Add database connection pooling configuration for high concurrency
  - Optimize database queries with proper indexing and query analysis
  - Configure Swoole worker processes for optimal performance
  - Implement caching strategies for frequently accessed account data
  - Write performance benchmarks and load testing scenarios
  - _Requirements: 3.1, 3.2, 3.3, 8.5_

- [ ] 19. Add security measures and validation
  - Implement input sanitization and SQL injection prevention
  - Add rate limiting for withdrawal endpoints to prevent abuse
  - Configure CORS policies for API access control
  - Implement request/response logging for security auditing
  - Write security tests for common attack vectors
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 7.1, 7.4_

- [x] 20. Implement case-specific quality requirements
  - Add performance benchmarking with sub-200ms response time validation
  - Implement comprehensive observability with structured logging and metrics
  - Create horizontal scalability tests with multiple container instances
  - Add security hardening with input validation and SQL injection prevention
  - Ensure complete Docker environment independence from host system
  - Document architectural decisions and alternatives in README.md
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

- [ ] 21. Final integration and system testing
  - Run complete system tests with all components integrated
  - Perform load testing with multiple containers and database stress
  - Validate email notifications work correctly in full workflow
  - Test graceful degradation scenarios and error recovery
  - Verify Docker environment works from scratch without host dependencies
  - Verify all requirements are met through comprehensive test suite execution
  - _Requirements: All requirements validation_