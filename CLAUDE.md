# Tournaments

Платформа для проведения турниров. Laravel 13, PostgreSQL 18, Inertia + Vue 3, Tailwind 4. DDD.

## Окружение

Всё в Docker, локального PHP нет. Любые команды PHP, Composer, npm выполняются через контейнеры:

```bash
docker compose exec app php artisan ...
docker compose exec app composer ...
docker compose exec node npm ...
```

Или через `make`: `make test`, `make test-domain`, `make stan`, `make lint`, `make check`.
Приложение на http://localhost:8000. В оболочке выставлен прокси, для curl к localhost добавляй `--noproxy '*'`.

## Архитектура

- `src/` — доменный код по ограниченным контекстам, корневой namespace `Tournaments\`.
- `src/<Context>/Domain` — чистый PHP. Никаких зависимостей от Laravel, Eloquent, фасадов, helpers.
- `src/<Context>/Application` — команды и обработчики, запросы на чтение. Зависит только от Domain.
- `src/<Context>/Infrastructure` — репозитории на Query Builder, маппинг. Eloquent в домене не используем.
- `app/` — только Laravel: контроллеры, middleware, провайдеры, привязка интерфейсов.
- Чтение для страниц идёт отдельными Query-классами с DTO прямо из SQL, минуя агрегаты.

Контексты: `Tournament` (ядро, делаем сейчас), позже `Scheduling`, `Statistics`, `Identity`.

## Домен

- Виды спорта: волейбол, пляжный волейбол, настольный теннис. Шахматы в перспективе.
- Результат матча — интерфейс `MatchOutcome`: `SetBasedOutcome` (партии) и `PointOutcome` (победа/ничья/поражение).
- Агрегаты: `Tournament` (участники, правила, доступ) и `Stage` (формат, матчи, продвижение). `Match` — сущность внутри `Stage`.
- Форматы — доменные сервисы `FormatGenerator`: SingleElimination, RoundRobin, позже DoubleElimination, Swiss.

## Правила работы

- Доменные тесты в `tests/Domain`, без базы. Application на in-memory репозиториях.
- Перед завершением задачи: `make check` должен проходить.
- PHPStan уровень 9 на `app/` и `src/`.
- Язык интерфейса и документации — русский. Код, имена, коммиты — английский.
