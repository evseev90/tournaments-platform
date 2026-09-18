.DEFAULT_GOAL := help
COMPOSE := docker compose
APP := $(COMPOSE) exec app
NODE := $(COMPOSE) exec node

help: ## Список команд
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

setup: ## Первый запуск на новой машине: .env, образы, зависимости, ключ, миграции
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d db
	$(COMPOSE) run --rm --no-deps app composer install
	$(COMPOSE) run --rm --no-deps app php artisan key:generate
	$(COMPOSE) run --rm app php artisan migrate --force
	$(COMPOSE) up -d

up: ## Поднять контейнеры
	$(COMPOSE) up -d

down: ## Остановить контейнеры
	$(COMPOSE) down

logs: ## Логи всех сервисов
	$(COMPOSE) logs -f

sh: ## Оболочка в контейнере приложения
	$(APP) sh

artisan: ## make artisan cmd="migrate:status"
	$(APP) php artisan $(cmd)

migrate: ## Прогнать миграции
	$(APP) php artisan migrate

fresh: ## Пересоздать базу с нуля
	$(APP) php artisan migrate:fresh

test: ## Все тесты
	$(APP) composer test

test-domain: ## Только доменные тесты, без базы
	$(APP) php artisan test --testsuite=Domain

stan: ## Статический анализ
	$(APP) composer stan

lint: ## Проверка стиля кода
	$(APP) composer lint

fix: ## Автоисправление стиля
	$(APP) composer fix

check: lint stan test ## Всё, что гоняется перед коммитом

npm: ## make npm cmd="install foo"
	$(NODE) npm $(cmd)

.PHONY: help setup up down logs sh artisan migrate fresh test test-domain stan lint fix check npm
