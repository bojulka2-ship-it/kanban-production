# ПРОИЗВОДСТВО · Трекер

Канбан-трекер производственных задач: доска «проекты × этапы × задачи», роли
(руководитель/сотрудник), комментарии, история перемещений, дедлайны, приоритеты,
чек-листы и личная страница «Мои задачи».

Полное описание этапов и модели данных — в [PROJECT.md](PROJECT.md).
Результаты проверок — в [AUDIT.md](AUDIT.md).
Инструкция для конечного пользователя — в [ПАМЯТКА_ПОЛЬЗОВАТЕЛЯ.md](ПАМЯТКА_ПОЛЬЗОВАТЕЛЯ.md).

## Возможности

- **Вход и роли.** Авторизация по email/паролю, разграничение прав (руководитель и сотрудник), смена собственного пароля.
- **Канбан-доска** (главная): строки — проекты, колонки — 5 этапов, в каждой ячейке 4 карточки задач. Статусы карточек: «Ожидает», «В работе» (с датой), «Завершено».
- **Перемещение задач:** drag-and-drop (только «В работе» и только для ответственного/руководителя) и меню `⋮` («Перенести на этап», «Завершить этап»). Действия пишутся в историю.
- **Модалка задачи:** вкладки «Этапы», «Чек-лист», «История», «Комментарии»; описание, дедлайн и приоритет (трекер, v1.1).
- **Комментарии** к задаче: пишут ответственный или руководитель; удаляет автор или руководитель.
- **Трекер задачи:** описание, дедлайн, приоритет (низкий/обычный/высокий), чек-лист с прогрессом «N/M».
- **«Мои задачи»:** список задач, где вы ответственный, с прогрессом этапов «X/5», чек-листом, бейджем дедлайна и фильтрами (проект, приоритет, только просроченные). В шапке — счётчик просроченных.
- **Фильтры доски** (серверные, комбинируются): поиск по названию, ответственный, статус проекта, только просроченные, переключатель «Активные/Архив».
- **Проекты:** создание и редактирование (руководитель), архив и восстановление.
- **Пользователи** (руководитель): создание, редактирование, сброс пароля, деактивация.
- **Адаптив:** десктоп (таблица) и мобильные (<768px — список проектов и полноэкранные модалки), тач-цели ≥44px.

## Стек

- PHP 8.4, Laravel 12
- SQLite (файл `database/database.sqlite`)
- Blade-шаблоны, **zero-build**: Tailwind CSS и Alpine.js подключаются через CDN, сборка (`npm`) не требуется
- PHPUnit 11

## Требования

- PHP **8.4** с расширениями `pdo_sqlite`, `mbstring`, `openssl`
- Composer
- Доступ в интернет при открытии страниц (Tailwind/Alpine/шрифты грузятся с CDN; после первого раза браузер кэширует)

## Установка и запуск

```powershell
composer install                 # зависимости (если vendor/ отсутствует)
copy .env.example .env           # если .env ещё нет
php artisan key:generate         # если ключ не сгенерирован
php artisan migrate --seed       # схема + демо-данные
php artisan serve                # http://127.0.0.1:8000
```

Полный сброс демо-данных: `php artisan migrate:fresh --seed`.

> Приложение — zero-build. Команды `npm install` / `npm run build` из шаблонных
> скриптов Composer **не нужны** (используются только в этапе деплоя).

## Демо-доступы

| Роль | Email | Пароль |
|---|---|---|
| Руководитель | `manager@demo.ru` | `password` |
| Сотрудники | `petrov@demo.ru`, `sidorova@demo.ru`, `kuznetsova@demo.ru`, `smirnov@demo.ru`, `volkov@demo.ru` | `password` |

Демо: 4 проекта, 80 карточек (4 задачи × 5 этапов × 4 проекта), история, комментарии,
дедлайны/приоритеты/чек-листы на проекте «Серийные корпусные детали».
`manager@demo.ru` — ответственный за просроченную задачу (для страницы «Мои задачи»).

## Тесты

```powershell
php artisan test                 # всё (62 passed)
php artisan test --filter=MyTasksTest
php artisan test --filter=TaskTrackerTest
```

## Деплой на Railway

В репозитории есть `Dockerfile` (PHP 8.2 + `pdo_sqlite`) и `railway.json` (healthcheck `/login`, рестарт при сбое). База SQLite хранится на томе и переживает рестарты.

1. Railway → **New Project → Deploy from GitHub repo** → выбрать `kanban-production` (ветка `master`).
2. Сервис → **Variables** — заполнить по таблице ниже. `APP_KEY` получить командой `php artisan key:generate --show`.
3. Сервис → **Volumes** — смонтировать том в **`/data`** (именно `/data`, а не `/app/database`: том перекрыл бы папки `migrations/`/`seeders/`, и миграции не нашлись бы).
4. **Deploy**. Контейнер при старте выполняет `php artisan migrate --force`, затем `php artisan db:seed --force` (идемпотентно — только на пустой БД) и поднимает сервер на `$PORT`.
5. Сервис → **Settings → Networking → Generate Domain** → скопировать адрес в `APP_URL` и передеплоить.

| Переменная | Значение |
|---|---|
| `APP_NAME` | `Kanban` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | вывод `php artisan key:generate --show` |
| `APP_URL` | публичный адрес, напр. `https://kanban-production.up.railway.app` |
| `APP_TIMEZONE` | `Europe/Moscow` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/data/database.sqlite` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_LEVEL` | `warning` |

Проверка после деплоя: вход `manager@demo.ru` / `password` → перемещение карточки → F5 (изменение сохранилось).

**Обновление:** `git push` в `master` → Railway сам пересоберёт и передеплоит (авто-деплой включён по умолчанию); ручной пересбор — кнопка **Redeploy** во вкладке Deployments.

**Сброс БД к демо-данным:** удалить том `/data` → Redeploy (файл БД создастся и засеется заново). Внимание: все реальные данные при этом пропадут.

## Полезные маршруты

| Маршрут | Назначение |
|---|---|
| `GET /` | Канбан-доска |
| `GET /my-tasks` | «Мои задачи» |
| `GET /project-tasks/{id}/detail` | JSON модалки задачи |
| `PATCH /project-tasks/{id}` | Описание/дедлайн/приоритет |
| `…/items` | Чек-лист (GET/POST/PATCH/DELETE) |
| `GET /users` | Пользователи (руководитель) |
| `GET /password` | Смена своего пароля |

Полный список: `php artisan route:list`.

## Структура (основное)

```
app/Http/Controllers/     ProjectController, ProjectTaskController, MyTasksController, …
app/Http/Requests/        StoreProjectRequest, UpdateProjectRequest
app/Models/               Project, ProjectTask, TaskItem, Stage, StageStatus, User, …
app/Policies/             ProjectPolicy, ProjectTaskPolicy, UserPolicy
resources/views/          board, my-tasks, projects/, users/, auth/, layouts/, partials/
database/migrations/      схема БД
database/seeders/         ReferenceSeeder, UserSeeder, ProjectSeeder, TaskTrackerSeeder
tests/Feature/            AuthFlow, RoleAccess, Validation, Security, TaskTracker, MyTasks
```

## Статус

Этапы 1–9, 11, 12 реализованы и покрыты тестами. Этап 10 (деплой на Railway)
подготовлен: `Dockerfile`, `railway.json` и инструкция в разделе «Деплой на Railway».
