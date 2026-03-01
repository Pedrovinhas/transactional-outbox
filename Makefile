.PHONY: help setup up down logs clean install test

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: ## Initial setup - install dependencies for all services
	@echo "📦 Installing dependencies for outbox/relay-service..."
	@cd outbox/relay-service && composer install
	@echo "📦 Installing dependencies for inbox/relay-service..."
	@cd inbox/relay-service && composer install
	@echo "📦 Installing dependencies for inbox/post-processing-service..."
	@cd inbox/post-processing-service && composer install
	@echo "📦 Installing dependencies for outbox/orders-service..."
	@cd outbox/orders-service && composer install
	@echo "✓ Setup complete!"

up: ## Start all services (outbox + inbox)
	@echo "🚀 Starting Outbox services..."
	@cd outbox && docker-compose up -d
	@echo "⏳ Waiting for RabbitMQ to be ready..."
	@sleep 5
	@echo "🚀 Starting Inbox services..."
	@cd inbox && docker-compose up -d
	@echo ""
	@echo "✓ All services started!"
	@echo ""
	@echo "📍 Services available at:"
	@echo "  - Orders API: http://localhost:8080"
	@echo "  - RabbitMQ Management: http://localhost:15672 (guest/guest)"
	@echo "  - Jaeger UI (Tracing): http://localhost:16686"
	@echo "  - Orders DB: localhost:5432"
	@echo "  - Inbox DB: localhost:5433"

up-outbox: ## Start only outbox services
	@echo "🚀 Starting Outbox services..."
	@cd outbox && docker-compose up -d
	@echo "✓ Outbox services started!"

up-inbox: ## Start only inbox services
	@echo "🚀 Starting Inbox services..."
	@cd inbox && docker-compose up -d
	@echo "✓ Inbox services started!"

down: ## Stop all services
	@echo "⏹️  Stopping Inbox services..."
	@cd inbox && docker-compose down
	@echo "⏹️  Stopping Outbox services..."
	@cd outbox && docker-compose down
	@echo "✓ All services stopped!"

down-outbox: ## Stop only outbox services
	@cd outbox && docker-compose down

down-inbox: ## Stop only inbox services
	@cd inbox && docker-compose down

restart: ## Restart all services
	@$(MAKE) down
	@$(MAKE) up

restart-outbox: ## Restart outbox services
	@cd outbox && docker-compose restart

restart-inbox: ## Restart inbox services
	@cd inbox && docker-compose restart

logs: ## Show logs from all services
	@echo "📋 Showing logs (Ctrl+C to exit)..."
	@docker-compose -f outbox/docker-compose.yml -f inbox/docker-compose.yml logs -f

logs-outbox: ## Show logs from outbox services
	@cd outbox && docker-compose logs -f

logs-inbox: ## Show logs from inbox services
	@cd inbox && docker-compose logs -f

logs-orders: ## Show logs from orders service
	@docker logs -f orders-service

logs-outbox-relay: ## Show logs from outbox relay
	@docker logs -f outbox-relay

logs-post-processing: ## Show logs from post-processing service
	@docker logs -f post-processing

logs-inbox-relay: ## Show logs from inbox relay
	@docker logs -f inbox-relay

logs-jaeger: ## Show logs from Jaeger
	@docker logs -f jaeger

clean: ## Stop and remove all containers, volumes, and networks
	@echo "🧹 Cleaning up..."
	@cd inbox && docker-compose down -v 2>/dev/null || true
	@cd outbox && docker-compose down -v 2>/dev/null || true
	@docker network rm transactional-outbox-network 2>/dev/null || true
	@echo "✓ All containers, volumes, and networks removed!"

rebuild: ## Rebuild all services
	@echo "🔨 Rebuilding all services..."
	@cd outbox && docker-compose build --no-cache
	@cd inbox && docker-compose build --no-cache
	@$(MAKE) up

rebuild-outbox: ## Rebuild outbox services
	@cd outbox && docker-compose build --no-cache
	@cd outbox && docker-compose up -d

rebuild-inbox: ## Rebuild inbox services
	@cd inbox && docker-compose build --no-cache
	@cd inbox && docker-compose up -d

ps: ## Show running containers
	@echo "📊 Outbox services:"
	@cd outbox && docker-compose ps
	@echo ""
	@echo "📊 Inbox services:"
	@cd inbox && docker-compose ps

shell-orders-db: ## Access orders database shell
	@docker exec -it orders-postgres psql -U postgres -d orders_db

shell-inbox-db: ## Access inbox database shell
	@docker exec -it inbox-postgres psql -U postgres -d inbox_db

test-order: ## Create a test order
	@echo "📝 Creating test order..."
	@curl -X POST http://localhost:8080/orders \
		-H "Content-Type: application/json" \
		-d '{"customer_name": "John Doe", "customer_email": "john@example.com", "total_amount": 99.99}' \
		&& echo "" || echo "❌ Failed to create order"

jaeger-health: ## Check Jaeger health
	@echo "🏥 Checking Jaeger health..."
	@curl -s http://localhost:14269/ > /dev/null && echo "✓ Jaeger is healthy" || echo "❌ Jaeger is not responding"
