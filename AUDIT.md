# Аудит этапов 1–9

Дата: 08.10.2026. Область: этапы 1–9 VIBECODE (MVP без деплоя и этапов 11–12).
Методы: инвентаризация репозитория → код-аудит ядра → автотесты (PHPUnit) → HTTP-аудит реального сервера → `composer audit`.

---

## Итог одной строкой

Критичных утечек нет, безопасность базы и прав доступа держится; найдены **2 важные находки (XSS в комментариях, нет throttle на логине)** и 2 средние, 4 мелкие. Автотесты: **42 passed / 1 failed** (падающий — осознанная фиксация XSS).

---

## Что проверено и результат

| Проверка | Результат |
|---|---|
| Секреты в git (`.env`, `database.sqlite`, ключи) | ✅ не отслеживаются, `.gitignore` корректен |
| `composer audit` (уязвимости зависимостей) | ✅ 0 advisory |
| CSRF: POST без токена | ✅ 419 |
| `.env` / `.git/config` / `database.sqlite` по HTTP | ✅ 404 |
| SQL-инъекция в фильтре `q` (5 payload'ов, HTTP + тесты) | ✅ безвредна, 200, ничего не подмешивает |
| XSS в названии проекта | ✅ экранируется blade (`&lt;script&gt;`) |
| Mass assignment (`is_archived`, `status`, `id` в POST /projects) | ✅ игнорируются валидацией |
| Пароли: хэширование, смена с `current_password`, `min:8`+`confirmed` | ✅ |
| Матрица прав: employee → `/users`, `/projects/*/edit`, `POST /projects`, archive/restore, чужие move/complete/comment, чужой комментарий → 403 | ✅ |
| Архив read-only (move/comment/update у manager → 403) | ✅ |
| Валидации: даты, `responsible` size:4, inactive-user, status-whitelist, длина комментария, пароли | ✅ |
| Логин: неверный пароль, деактивированный аккаунт, logout, гость→302 | ✅ |
| Новые проекты: 4 задачи × 5 stage_statuses = 20 | ✅ |
| Health `/up` | ✅ 200 |

**Автотесты:** `php artisan test` → 43 теста, **42 passed / 1 failed** — `SecurityTest::html_in_comment_body_is_not_rendered` падает намеренно: фиксирует находку №1 (см. ниже). Запуск: `php artisan test`.
**HTTP-аудит:** 7/9 OK (2 FAIL — находка №3).

---

## Находки

### Важные (чинить до продакшена)

**1. Stored XSS в комментариях**
- Где: `resources/views/board.blade.php:532` — `x-html="nl2brHtml(c.body)"`; сервер отдаёт тело сырым (`app/Http/Controllers/CommentController.php:93` — `'body' => $comment->body`).
- Суть: комментарий `<img src=x onerror=…>` вставляется в DOM как HTML и выполняется у каждого, кто откроет вкладку «Комментарии» (подтверждено падающим тестом: JSON содержит `{"body":"<img src=x onerror=alert(1)>"}`).
- Кто эксплуатирует: ответственный за задачу или руководитель (могут писать комментарии) → действует от имени жертвы в её сессии.
- Фикс (1 функция, клиент): эскейпить в `nl2brHtml` до подстановки `<br>`:
  ```js
  const esc = (text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  return esc.replace(/\n/g, '<br>');
  ```
  Тест `SecurityTest::html_in_comment_body_is_not_rendered` после фикса зеленеет.

**2. Нет ограничения попыток входа (brute force)**
- Где: `routes/web.php:14` — `POST /login` без `throttle`.
- Суть: пароль можно перебирать без ограничений (лимит только по здравому смыслу).
- Фикс: `Route::post('/login', ...)->middleware('throttle:5,1');`

### Средние

**3. Нет security-заголовков** — HTTP-аудит: `X-Content-Type-Options` и `X-Frame-Options` отсутствуют. Фикс: middleware в `bootstrap/app.php` (append: `nosniff`, `SAMEORIGIN`, плюс `Referrer-Policy`).

**4. Деактивация не разрывает живую сессию** — `is_active` проверяется только при входе (`AuthController.php:34`), действующая сессия деактивированного живёт до `SESSION_LIFETIME` (120 мин). Фикс: глобальный middleware-проверка `is_active` на каждый запрос (или `throttle`+перелогин).

### Мелкие (гигиена)

5. `routes/web.php:45` — устаревший комментарий «этап прав заменит на Policy» — уже заменено на `authorize('manage', …)`.
6. `app/Policies/ProjectPolicy.php` — мёртвые методы `delete`/`forceDelete` (роутов удаления проектов нет).
7. `composer.json` — шаблонные скрипты `setup`/`dev` с `npm install`/`npm run build` противоречат ZERO-BUILD; для этапа 10 (деплой) не использовать.
8. `tests/Feature/ExampleTest.php` — шаблонный тест падал (ожидал 200 на `/` за гостем); в аудите исправлен на `assertRedirect('/login')`.

### Не находки (по ТЗ)

- Отдельная ошибка «Аккаунт деактивирован» при логине — требование VIBECODE этап 2 (осознанная утечка существования аккаунта).
- Просмотр чужих задач/доски всеми авторизованными — по ТЗ (view в политиках = true).

---

## Статус этапов VIBECODE

| Этап | Статус |
|---|---|
| 1–9 | ✅ закоммичены (`9c6f37d` … `390de50`) |
| 10 (деплой Railway) | ⬜ не начат |
| 11–12 (дедлайны задач/чек-листы, «Мои задачи») | ⬜ не начаты (по правилу VIBECODE — только после этапа 10) |

---

## Что осталось ручным (вне аудита)

Блок И из `input/Tests.md`: проверка 4 ширин в F12 (1920/1366/768/390), скриншоты desktop+mobile, длинное название/комментарий, drag после горизонтального скролла на 1366.

---

## Новые тесты (созданы в аудите)

| Файл | Что |
|---|---|
| `tests/Feature/BoardTestCase.php` | общая база: справочники, 3 пользователя, 2 проекта |
| `tests/Feature/AuthFlowTest.php` | 10 тестов: вход/выход/деактивация/смена пароля/хэши |
| `tests/Feature/RoleAccessTest.php` | 15 тестов: матрица ролей, архив, комментарии, переносы |
| `tests/Feature/ValidationTest.php` | 9 тестов: формы проектов/комментариев/пользователей/паролей |
| `tests/Feature/SecurityTest.php` | 7 тестов: XSS, SQLi, mass assignment, IDOR, служебные файлы |

Запуск: `php artisan test` (in-memory SQLite, `phpunit.xml` уже настроен).
