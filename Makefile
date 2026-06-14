# Makefile for rxmuk Docker commands
# Usage: make help

.PHONY: help up down restart logs shell db-shell clean build

help:
	@echo "=== rxmuk Docker Commands ==="
	@echo ""
	@echo "Usage: make [command]"
	@echo ""
	@echo "Commands:"
	@echo "  make up              - Start all containers"
	@echo "  make down            - Stop all containers"
	@echo "  make restart         - Restart all containers"
	@echo "  make build           - Build images"
	@echo "  make rebuild         - Rebuild images from scratch"
	@echo "  make logs            - View container logs"
	@echo "  make shell           - Access app container shell"
	@echo "  make db-shell        - Access MySQL shell"
	@echo "  make db-backup       - Backup database"
	@echo "  make db-restore      - Restore database"
	@echo "  make clean           - Remove all containers and data"
	@echo "  make ps              - Show running containers"
	@echo "  make status          - Check container health"
	@echo ""

up:
	docker-compose up -d
	@echo "✓ Containers started"
	@echo ""
	@echo "Access:"
	@echo "  Website:    http://localhost"
	@echo "  PHPMyAdmin: http://localhost:8080"
	@echo "  Database:   localhost:3306"

down:
	docker-compose down
	@echo "✓ Containers stopped"

restart: down up
	@echo "✓ Containers restarted"

build:
	docker-compose build
	@echo "✓ Images built"

rebuild:
	docker-compose build --no-cache
	@echo "✓ Images rebuilt"

logs:
	docker-compose logs -f

logs-app:
	docker-compose logs -f app

logs-db:
	docker-compose logs -f db

shell:
	docker-compose exec app bash

php:
	docker-compose exec app php

db-shell:
	docker-compose exec db mysql -u rxmuk_user -p rxmuk_pass123 rxmuk_db

db-root:
	docker-compose exec db mysql -u root -p rootpass123

db-backup:
	@mkdir -p ./backups
	docker-compose exec -T db mysqldump -u rxmuk_user -p rxmuk_pass123 rxmuk_db > ./backups/rxmuk_backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✓ Database backed up to ./backups/"

db-restore:
	@if [ -z "$(FILE)" ]; then \
		echo "Usage: make db-restore FILE=path/to/backup.sql"; \
		exit 1; \
	fi
	docker-compose exec -T db mysql -u rxmuk_user -p rxmuk_pass123 rxmuk_db < $(FILE)
	@echo "✓ Database restored from $(FILE)"

ps:
	docker-compose ps

status:
	@echo "=== Containers Status ==="
	docker-compose ps
	@echo ""
	@echo "=== Disk Usage ==="
	docker system df

clean:
	docker-compose down -v
	@echo "✓ All containers and volumes removed"

clean-images:
	docker rmi rxmuk_app
	@echo "✓ App image removed"

test:
	docker-compose exec app curl -f http://localhost/ || echo "Application not responding"

test-db:
	docker-compose exec db mysqladmin ping -u rxmuk_user -p rxmuk_pass123 || echo "Database not responding"

prune:
	docker system prune -a
	@echo "✓ Cleaned up unused Docker resources"

stats:
	docker stats

version:
	@docker --version
	@docker-compose --version

.DEFAULT_GOAL := help
