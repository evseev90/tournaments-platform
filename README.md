# Tournaments

Платформа для создания и проведения турниров: участники, сетки, расписание, статистика.
Первый этап — быстрый турнир без регистрации для волейбола, пляжного волейбола и настольного тенниса.

## Стек

PHP 8.4, Laravel 13, PostgreSQL 18, Inertia + Vue 3, Tailwind 4. Всё окружение живёт в Docker,
локально нужны только Docker и `make`.

## Запуск на новой машине

```bash
make setup
```

Команда скопирует `.env.example` в `.env`, соберёт образы, поставит зависимости, сгенерирует ключ,
прогонит миграции и поднимет контейнеры. Дальше:

- приложение: http://localhost:8000
- Vite с горячей перезагрузкой: http://localhost:5173 (поднимается сам, обращаться напрямую не нужно)
- PostgreSQL: `localhost:5432`, база `tournaments`, пользователь и пароль в `.env`

Порты меняются через переменные `APP_PORT`, `VITE_PORT`, `DB_PORT_HOST` в `.env`.
Если UID пользователя не 1000, задай `UID` и `GID` в `.env`, чтобы файлы из контейнера принадлежали тебе.

## Повседневные команды

```bash
make up            # поднять
make down          # остановить
make test          # все тесты
make test-domain   # только доменные, без базы
make stan          # PHPStan
make lint          # Pint
make check         # lint + stan + test
make sh            # shell в контейнере приложения
make artisan cmd="migrate:status"
make npm cmd="install some-package"
```

## Структура

```
app/            Laravel: контроллеры, middleware, провайдеры
src/            Доменный код по ограниченным контекстам
  Shared/         базовые типы, общие для контекстов
  Tournament/     контекст «Турнир»
    Domain/         сущности, объекты-значения, события, интерфейсы репозиториев
    Application/    команды, обработчики, запросы
    Infrastructure/ репозитории, маппинг в базу
resources/js/   Vue-страницы и компоненты
tests/          Domain, Application, Unit, Feature
docker/         Dockerfile PHP и init-скрипты базы
```

Подробнее об архитектуре и терминах — в `docs/`.
