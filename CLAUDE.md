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
- Чтение для страниц: `TournamentFinder` собирает DTO из агрегатов. Отдельные SQL-модели чтения появятся для списков и статистики.
- Обработчики команд работают внутри `TransactionManager`, репозитории берут строку агрегата через `lockForUpdate`. События публикуются после коммита.

Контексты: `Tournament` (ядро) и `Scheduling` (площадки и время). Позже `Statistics`, `Identity`.

Между контекстами нет прямых ссылок и внешних ключей. `Scheduling` объявляет порт `PlannableMatches`,
его реализация в `src/Scheduling/Infrastructure/Tournament` переводит матчи стадий на язык расписания.
Обратная связь идёт событиями: слушатель `KeepScheduleInSync` в `app/Listeners`.

## Домен

- Виды спорта: волейбол, пляжный волейбол, настольный теннис. Шахматы в перспективе.
- Результат матча — интерфейс `MatchOutcome`: `SetBasedOutcome` (партии) и `PointOutcome` (победа/ничья/поражение).
- Агрегаты: `Tournament` (участники, правила, доступ) и `Stage` (формат, матчи, продвижение). `Match` — сущность внутри `Stage`.
- Форматы — доменные сервисы `FormatGenerator`: SingleElimination, DoubleElimination, RoundRobin, Swiss.
- Швейцарская система достраивается по ходу: генератор делает только первый тур, дальше `Stage::pairNextRound`
  с сервисом `SwissPairing`. Стадия хранит исходный посев (`Stage::$seeding`), он нужен жеребьёвке.
- Двойная сетка помечает матчи веткой (`BracketSide`) и нумерует раунды сквозной лентой: верхняя, нижняя, гранд-финал.
- Группы и плей-офф — это этапы (`Stage::$phase`), а не формат: групповой этап это несколько круговых стадий
  с именами, плей-офф отдельная стадия второго этапа. Посев считают `GroupSplitter` и `PlayoffSeeder`.
- Расписание: агрегат `Schedule` (площадки, параметры, назначения) и `GreedyPlanner`. Подробности в `docs/scheduling.md`.

## Интерфейс

- Inertia + Vue 3, страницы в `resources/js/pages` (строчная буква: так их ищет Inertia), компоненты в `resources/js/Components`.
- Ссылки: `/` создание, `/manage/{token}` организатор, `/t/{slug}` зрители. Токен превращает в турнир middleware `ResolveOrganizerTournament`.
- Доменные исключения превращаются в ошибку формы `errors.domain` через `DomainErrorTranslator`, тексты на русском.
- Скриншоты для проверки вёрстки: `docker run --rm --network host -v $OUT:/out zenika/alpine-chrome --headless --no-sandbox --screenshot=/out/x.png --window-size=1280,1000 URL`.

## Правила работы

- Доменные тесты в `tests/Domain`, без базы. Application на in-memory репозиториях.
- Перед завершением задачи: `make check` должен проходить.
- PHPStan уровень 9 на `app/` и `src/`.
- Язык интерфейса и документации — русский. Код, имена, коммиты — английский.
