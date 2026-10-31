# Slim + Vue starter

Небольшой стартер на PHP 8.5, Slim, Doctrine и Vue, делался для себя.

Хотелось собрать собственный мини-фреймворк на Slim. С Symfony работаю впервые, поэтому сделано все несколько кривенько.

В качестве примера сделал личный кабинет с авторизацией, уведомлениями и управление пользователями (скорее демонстрация возможностей). В дальнейшем его желательно доработать.

## Стек

### Бэкенд:
- PHP 8.5
- Slim 4 
- Doctrine
- JWT
- Redis
- Postgre
- Twig для шаблонов писем
- Centrifugo

### Фронтенд:
- Vue 3
- Pinia
- Tailwind

### Инфраструктура, инструменты:
- Docker compose
- PHP CS Fixer
- PHP Unit
- ESLint + Prettier
- Swagger
- Clockwork
- Mailpit
- Symfony TUI

## Запуск

```
./run setup
```
Команда подготовит .env, сгенерирует ключи и создаст нужные каталоги. При повторном запуске существующие настройки не перезаписываются.

Затем:
```
./run up -d --build
./run composer install
./run migrate --no-interaction
./run console db:seed
./run --profile queue up -d worker
```
Фикстуры необязательны. Они создают локальные демо-аккаунты с паролем`ChangeMe12345!` 

| Email | Аккаунт |
| --- | --- |
| `admin@example.test` | Администратор |
| `member@example.test` | Пользователь |
| `pending@example.test` | Без подтверждённой почты |

Письма отправляются через очередь, поэтому для подтверждения почты нужен запущенный worker. Сами письма можно посмотреть в Mailpit.

Фронтенд можно запустить на хосте:

```
npm --prefix frontend ci
npm --prefix frontend run dev
```

Или через Docker, если Node.js на хосте не нужен:

```
./run --profile frontend run --rm --no-deps frontend npm ci
./run --profile frontend up -d frontend
```

В Docker прокси Vite обращается к `http://nginx`, на хосте - к `http://127.0.0.1:8080`. 
В обоих случаях http://localhost:5173.

`frontend/.env.example` показывает настройку адреса API; по умолчанию используется `/api`.

| Сервис | Адрес |
| --- | --- |
| Приложение | http://localhost:5173 |
| API | http://localhost:8080 |
| Swagger | http://localhost:8081 |
| Mailpit | http://localhost:8025 |
| Clockwork | http://localhost:8080/clockwork/ |

Clockwork работает в локальном режиме при APP_ENV=local и APP_DEBUG=true.

## Структура

```
app/                   общие компоненты стартера
src/Entity/            сущности
src/Modules/Auth/      авторизация и сессии
src/Modules/Account/   личный кабинет
config/settings/       настройки
config/definitions/    сборка сервисов через DI
config/registrations/  команды, задания, события, Voters и расписание
routes/api.php         API-маршруты
frontend/              Vue приложение
templates/emails/      шаблоны писем
database/migrations/   миграции
tools/art-editor/      веб-редактор для терминальных артов
```

Для зависимостей используется DI-контейнер. 
Doctrine можно внедрять в сервисы и репозитории, а доступ к действиям проверяется через Symfony Voters.

Присутствует route model binding.

## Авторизация

Сделал авторизацию с короткоживущими JWT и серверными сессиями.

Access-токен действует 15 минут, refresh-токен хранится в HttpOnly-cookie и обновляется при использовании.

Есть:
- Вход и выход
- «Запомнить меня»
- Подтверждение email
- Сброс пароля
- Отзыв сессий
- Проверка прав пользователей

При обычном входе серверная сессия живёт до 12 часов без обновления. С опцией «Запомнить меня» срок может продлеваться до года. Без неё refresh-cookie действует до закрытия браузерной сессии.

При выходе отзываются токены текущей сессии, а при сбросе пароля - все сессии пользователя.

По умолчанию неподтверждённая почта ограничивает доступ к защищённым действиям. Это можно изменить через EMAIL_VERIFICATION_REQUIRED.

## Команды

`./run` - обёртка над Docker Compose с несколькими сокращениями.

```
./run console list
./run console make:controller Report --module=Account
./run console make:service Report --module=Account
./run console make:job Export --module=Account
./run console user:role member@example.test admin
./run doctrine migrations:diff
./run migrate --no-interaction
./run logs --tail=50 app worker
```

Генераторы не перезаписывают существующие файлы. Новые маршруты, обработчики
и подписчики подключаются явно в `routes/` и `config/registrations/`.

После изменения .env контейнеры приложения и воркера можно пересоздать:

```
./run --profile queue up -d --force-recreate app worker
```

## Очередь и Scheduler

Очередь пока в постгресе, обработчики зарегистрированы в `config/registrations/jobs.php`.

```
./run console queue:stats
./run console queue:failed
./run console queue:retry 1
./run console schedule:run -vv
```

Scheduler пока занимается очисткой истёкших сессий и напоминаниями о подтверждении почты.

Само расписание находится в config/registrations/schedule.php.

Для автоматического запуска можно добавить в `crontab -e`:

```
* * * * * cd /path/to/project && /usr/bin/docker compose exec -T app php bin/console schedule:run --no-interaction >> var/scheduler.log 2>&1
```

Для фоновой работы должны быть запущены docker, worker и cron. 

## Кэш и файлы

Для кэша есть небольшая обёртка над Redis: `App\Cache\Cache`
Поддерживаются get, set, delete, remember и rememberForever.

Для файлов `App\Storage\Storage`:

```
$storage->put('reports/demo.txt', 'hello');
$url = $storage->url('reports/demo.txt');
$storage->disk('private')->put('reports/private.txt', 'secret');
```

Есть публичный и приватный диски. Публичные файлы доступны через /media/, приватные напрямую через Nginx не отдаются.
Настройки лежат в config/settings/cache.php и config/settings/storage.php.

## Вебхуки и WebSocket

Получатели вебхуков задаются через `WEBHOOK_USER_URL` и`WEBHOOK_NOTIFICATION_URL`, каждому нужен свой секрет. 

Пустой URL отключает получателя. 

События настраиваются в `config/settings/webhooks.php`. Доставка идёт через очередь, попытки сохраняются в БД.

```sh
./run console webhooks:deliveries
```

Для локальных тестов есть простой получатель tools/webhook-receiver.php, который умеет проверять подпись.

Нужно включить в .env`BROADCAST_ENABLED=true`, затем:

```
./run --profile realtime up -d centrifugo
./run --profile queue up -d --force-recreate app worker
./run console broadcast:check
```

На фронтенде подключение находится в frontend/src/services/realtime.ts, также есть composable useRealtime.

У каждого пользователя свой канал уведомлений.

## TUI и арты

Арты брал здесь: [MikuSays](https://github.com/xxanqw/mikusays)

```sh
./run ui
```

Панель запускается на хосте и показывает контейнеры, очередь, scheduler, вебхуки и логи. 
В `commands` можно запускать готовые действия и вводить свои команды для хоста, app или frontend.

Арты лежат в `resources/console/art/`, палитры - в `app/Console/UI/MikuColors.php`, привязка к разделам и цитаты - в `DashboardTheme.php`.

```sh
./run art:editor
```

Редактор откроется на http://localhost:8090. Парсятся txt файлы из каталога артов. Там интерфейс интуитивно понятен, пару биндов:

- `Ctrl + mwheel` - изменить масштаб над редактором.
- `B` - кисть, `G` - заливка, `I` - пипетка, `E` - убрать цвет.
- `ПКМ` или `Alt + ЛКМ` - взять цвет с ячейки.
- Зажатая `ЛКМ` - рисовать кистью или убирать цвет при движении мыши.
- `Ctrl + Z` - отменить, `Ctrl + Shift + Z` или `Ctrl + Y` - повторить.

Стоило бы вынести это в отдельный пакет.

## Проверки

```sh
./run composer validate --no-check-publish
./run format:check --sequential
./run test:all
npm --prefix frontend run check:lint
npm --prefix frontend run build
```

Исправить форматирование:

```sh
./run format --sequential
npm --prefix frontend run format
```


`./run test` запускает изолированные проверки без внешних сервисов. 

`./run test:integration` готовит отдельную Postgres-базу и проверяет API и бизнес-сценарии.

`./run test:all` запускает оба набора.

Настройки - в `phpunit.xml` и `tests/Support/` 