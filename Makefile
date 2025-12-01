.PHONY: help build up down restart logs shell test migrate seed

# Default target
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build Docker containers
	docker-compose build

up: ## Start all services
	docker-compose up -d

down: ## Stop all services
	docker-compose down

restart: ## Restart all services
	docker-compose restart

logs: ## Show logs from all services
	docker-compose logs -f

logs-app: ## Show logs from app service only
	docker-compose logs -f app

shell: ## Access app container shell
	docker-compose exec app sh

mysql: ## Access MySQL shell
	docker-compose exec mysql mysql -u root -proot pix_withdrawal

redis: ## Access Redis CLI
	docker-compose exec redis redis-cli

install: ## Install composer dependencies
	docker-compose exec app composer install

test: ## Run tests
	docker-compose exec app composer test

migrate: ## Run database migrations
	docker-compose exec app php bin/hyperf.php migrate

migrate-rollback: ## Rollback database migrations
	docker-compose exec app php bin/hyperf.php migrate:rollback

seed: ## Run database seeders
	docker-compose exec app php bin/hyperf.php db:seed

fresh: ## Fresh database with migrations and seeds
	docker-compose exec app php bin/hyperf.php migrate:fresh --seed

analyse: ## Run static analysis
	docker-compose exec app composer analyse

cs-fix: ## Fix code style
	docker-compose exec app composer cs-fix

setup: build up install migrate seed ## Complete setup for development

clean: ## Clean up containers and volumes
	docker-compose down -v --remove-orphans
	docker system prune -f