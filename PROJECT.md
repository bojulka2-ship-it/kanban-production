# PROJECT.md — Канбан-доска производственных этапов

Внутренний инструмент визуального контроля производственных проектов: строка = проект, столбцы = 5 этапов, в каждой ячейке 4 задачи. Учебный проект, деплой на Railway.

## Стек

- Laravel 12, PHP 8.4, SQLite, Blade
- Frontend: Tailwind Play CDN, Alpine.js 3.x CDN, SortableJS CDN — **ZERO-BUILD** (node/npm/vite не используются, `package.json` в сборке не участвует)
- Аутентификация: самописные сессии Laravel (Auth facade), login only, без регистрации
- Роли: колонка `role` + enum `App\Enums\UserRole`, права — через Policies/Gate (этап 2)
- Часовой пояс: `APP_TIMEZONE=Europe/Moscow` (UTC+3)

## Схема БД

| Таблица | Ключевые поля | Назначение |
|---|---|---|
| `users` | `role` (manager/employee), `is_active` | Пользователи (стандартная Laravel + 2 поля) |
| `projects` | title(100), start_date, due_date, status(active/paused/completed/cancelled), is_archived | Проекты |
| `stages` | name, sort_order | Справочник этапов (5): Планирование, Закупка, Производство, Контроль качества, Отгрузка |
| `task_types` | name, sort_order | Справочник задач (4): Конструкторская документация, Материалы и комплектующие, Изготовление, Испытания и приёмка |
| `project_tasks` | project_id, task_type_id, responsible_id, **unique(project_id, task_type_id)** | 4 задачи проекта |
| `stage_statuses` | project_task_id, stage_id, status(pending/in_progress/done), entered_at, **unique(project_task_id, stage_id)** | Карточка задачи в колонке этапа (матрица 4×5 = 20 на проект) |
| `task_history` | project_task_id, user_id, stage_id, action(stage_started/stage_completed/stage_reopened) | Журнал перемещений |
| `comments` | project_task_id, user_id, body | Комментарии к задаче |

Отношения: Project → hasMany ProjectTask → hasMany StageStatus / TaskHistory / Comment; ProjectTask belongsTo TaskType, User (responsible); StageStatus/TaskHistory belongsTo Stage.

**Правило:** при создании `project_task` событие `created` автоматически создаёт 5 `stage_statuses` со статусом `pending` (`App\Models\ProjectTask::booted`).

## Роуты

Группа `guest` (только неавторизованные):
- `GET /login` — форма входа
- `POST /login` — вход

Группа `auth` (все остальное):
- `POST /logout` — выход (CSRF)
- `GET /` — главная: список активных проектов (+ модалка «Новый проект», manager)
- `GET /projects/{project}/edit` — редактирование проекта (manager)
- `POST /projects` — создание проекта (manager)
- `PUT /projects/{project}` — обновление проекта (manager)
- `POST /projects/{project}/archive` | `restore` — архив/восстановление (manager)
- `GET /password`, `PUT /password` — смена собственного пароля
- `GET /users`, `POST /users`, `PATCH /users/{user}` — раздел «Пользователи» (только manager)
- `POST /users/{user}/reset-password` — сброс пароля пользователя
- `POST /users/{user}/toggle-active` — деактивация/активация

Права: разделы «Проекты» и «Пользователи» — временная inline-проверка роли (`App\Http\Controllers\Concerns\AuthorizesManager`), этап прав заменит на Policy/Gate.

Проект: создание/редактирование в транзакции (project + 4 project_tasks), при создании задачи stage_statuses создаются событием `ProjectTask::created` (20 на проект). Валидация — FormRequest (`StoreProjectRequest`, `UpdateProjectRequest`): due_date `after_or_equal:start_date`, ответственность по 4 задачам — только активные пользователи.

## Соглашения

- Язык: ответы на русском, код/команды на английском, комментарии в коде на русском
- Демо-доступы: `manager@demo.ru` / `password` (руководитель), 5 сотрудников `*@demo.ru` / `password`
- Статусы доски: карточка `pending` — серая, `in_progress` — акцентная + дата входа (`entered_at`), `done` — зелёная галочка
- Параллельная работа: у одной задачи может быть несколько `in_progress` этапов одновременно (каждый со своим `entered_at`)
- Дизайн — по `input/brandbook.md` (акцент `#2563EB`, фон `#F1F5F9`, Inter, радиусы 8/12px)
- Конфигурация — в `PROJECT.md`; `AGENTS.md`, `input/`, `references/`, `data/` — только чтение
- Сборки фронтенда нет: никаких npm-команд, `package.json` не используется

## Демо-данные (seed)

- 6 пользователей (1 manager + 5 employees), 4 проекта:
  1. «Модернизация линии упаковки» — только начат (2 задачи in_progress на этапе 1)
  2. «Серийные корпусные детали» — середина, у задачи «Материалы» 2 этапа параллельно in_progress
  3. «Ремонт конвейера №2» — просроченный (due_date −5 дней, статус active)
  4. «Партия кронштейнов (закрыта)» — завершённый (все этапы done, статус completed)
- 80 stage_statuses (4 × 4 × 5), task_history отражает путь каждой задачи

## Статус работ

- [x] **Этап 1** — модели, миграции, сиды; `php artisan migrate:fresh --seed` проходит, StageStatus::count() = 80
- [x] **Этап 2** — авторизация (login/out), layout, смена пароля, раздел «Пользователи», заглушка главной. Проверено: вход/выход, гость → /login, деактивированный не входит («Аккаунт деактивирован»), employee на /users → 403, смена пароля работает
- [x] **Этап 3** — проекты: создание/редактирование/архив (модалка + страница редактирования). Проверено: создание с 20 stage_statuses, валидация дат, архив прячет/восстанавливает, employee без кнопки и 403
- [ ] Этап 4 — доска (матрица 5×4), drag-n-drop, история
