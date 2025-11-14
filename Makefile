# Makefile for Mobility Inventory Management System

# Default target
.PHONY: help
help:
	@echo "Mobility Inventory Management System - Docker Commands"
	@echo ""
	@echo "Usage:"
	@echo "  make up          Start all services in detached mode"
	@echo "  make down        Stop all services"
	@echo "  make build       Build all services"
	@echo "  make rebuild     Rebuild all services without using cache"
	@echo "  make logs        View logs for all services"
	@echo "  make logs-web    View logs for web service"
	@echo "  make logs-db     View logs for database service"
	@echo "  make shell-web   Open shell in web container"
	@echo "  make shell-db    Open shell in database container"
	@echo "  make test        Run tests"
	@echo "  make clean       Remove all containers, networks, and volumes"

# Start services
.PHONY: up
up:
	docker-compose up -d

# Stop services
.PHONY: down
down:
	docker-compose down

# Build services
.PHONY: build
build:
	docker-compose build

# Rebuild services without cache
.PHONY: rebuild
rebuild:
	docker-compose build --no-cache

# View logs
.PHONY: logs
logs:
	docker-compose logs -f

# View web service logs
.PHONY: logs-web
logs-web:
	docker-compose logs -f web

# View database service logs
.PHONY: logs-db
logs-db:
	docker-compose logs -f db

# Open shell in web container
.PHONY: shell-web
shell-web:
	docker-compose exec web bash

# Open shell in database container
.PHONY: shell-db
shell-db:
	docker-compose exec db bash

# Run tests
.PHONY: test
test:
	docker-compose exec web php vendor/bin/phpunit

# Clean up everything
.PHONY: clean
clean:
	docker-compose down -v --remove-orphans