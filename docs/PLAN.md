# Chats Backend — Plan

## Context
`chats-be` is a fresh Laravel 13 skeleton (PHP 8.5, no app code yet). It will be the backend for a mobile chat app:
- Mobile users talk to a REST API plus a WebSocket.
- Admins log in through a web panel.

Decisions made:
- 1-on-1 and group chats
- Filament for the admin panel
- S3-compatible storage for media
- MySQL/MariaDB as the database

**Answers to the open questions**
- **WebSocket:** yes. Use **Laravel Reverb**, the first-party WebSocket server. It speaks the Pusher protocol, so mobile apps connect with any Pusher client SDK (Android, iOS or Flutter).
- **Push:** yes. Use **Firebase Cloud Messaging** through `laravel-notification-channels/fcm` (built on `kreait/laravel-firebase`). FCM covers Android and iOS; iOS is delivered through APNs, configured once in the Firebase console.

## Stack / packages
| Concern | Package |
|---|---|
| Agent setup (CLAUDE.md requirement) | `laravel/boost --dev` → `php artisan boost:install` (do this first) |
| Mobile auth | `laravel/sanctum` (`php artisan install:api`): personal access tokens |
| Realtime | `laravel/reverb` (`php artisan install:broadcasting --reverb`) |
| Push | `laravel-notification-channels/fcm` |
| Admin | `filament/filament` (latest), panel at `/admin` |
| Media | `league/flysystem-aws-s3-v3`, `intervention/image-laravel` (image thumbnails) |
| API docs | `dedoc/scramble`: auto OpenAPI at `/docs/api`. Its JSON can feed your Android data-layer codegen. |

Infra: MySQL plus Redis (queue, cache, Reverb scaling), all containerized. See the Docker section below.

## Roles
- Add a `role` column to `users` (`App\Enums\UserRole`: `user` or `admin`). Also add `is_banned`, `avatar_path` and `last_seen_at`.
- Mobile API: any non-banned user, via the Sanctum token guard.
- Web admin: implement `FilamentUser` on `App\Models\User`, with `canAccessPanel()` returning `role === admin`. Filament uses the normal session `web` guard.
- Add an `EnsureNotBanned` middleware on the API group. Banning a user also revokes their tokens.

## Data model (migrations)
- **conversations**: `id`, `type` (direct|group), `name`, `avatar_path` (both nullable, group only), `created_by`, `last_message_id` (nullable), timestamps.
  - Direct chats get a unique `direct_key` (`"minId:maxId"`) so two users never end up with duplicate 1-on-1 chats.
- **conversation_participants**: `conversation_id`, `user_id`, `role` (owner|admin|member), `last_read_message_id`, `muted_until`, `joined_at`, `left_at`. Unique on (`conversation_id`, `user_id`).
- **messages**: `id`, `conversation_id`, `sender_id`, `type` (text|image|audio|system), `body` (nullable), `reply_to_id`, `client_uuid` (unique per sender, so retries are idempotent), `edited_at`, soft deletes, timestamps. Index on (`conversation_id`, `id`).
- **attachments**: `id` (ULID), `user_id`, `message_id` (nullable until sent), `disk`, `path`, `mime`, `size`, `width`, `height`, `duration_ms`, `thumbnail_path`, timestamps.
- **device_tokens**: `user_id`, `token` (unique), `platform` (android|ios), `last_used_at`.

Models: `Conversation`, `Message`, `Attachment`, `DeviceToken`, with factories for each.
Policies: `ConversationPolicy` (is participant / is group admin) and `MessagePolicy` (sender can edit or delete).

## API (`routes/api.php`, prefix `/api/v1`)
Auth:
- `POST auth/register`, `POST auth/login` (returns a token), `POST auth/logout`
- `GET/PATCH me`, `POST me/avatar`
- `POST me/devices` (registers or updates an FCM token), `DELETE me/devices/{token}`

Users: `GET users?search=` (to start chats)

Conversations:
- `GET conversations`: cursor-paginated, sorted by last message, includes `unread_count`
- `POST conversations`: direct is find-or-create; group takes a name and members
- `GET/PATCH/DELETE conversations/{id}`
- `POST/DELETE conversations/{id}/participants`
- `POST conversations/{id}/mute`

Messages:
- `GET conversations/{id}/messages?before=`: cursor pagination, newest first
- `POST conversations/{id}/messages`: takes `type`, `body`, `attachment_ids[]`, `reply_to_id`, `client_uuid`
- `PATCH/DELETE messages/{id}`
- `POST conversations/{id}/read`: takes `message_id`

Media:
- `POST attachments`: multipart. Returns the attachment id and a signed URL.

Implementation notes:
- Use Form Requests for validation and API Resources for every response.
- Put business logic in actions/services: `SendMessage`, `CreateConversation`, `MarkAsRead`. Controllers stay thin.

## Realtime (Reverb)
Register broadcasting auth for Sanctum in `bootstrap/app.php`: `->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])`.

Channels (`routes/channels.php`):
- `private-user.{id}`: per-user feed (new conversation, conversation updated, unread badge)
- `private-conversation.{id}`: messages, edits, deletes, read receipts. Authorized only for participants.
- `presence-online`: online status, optional. Typing indicators use client whispers on the conversation channel (`client-typing`), so they cost no server work.

Events (`ShouldBroadcast`, queued): `MessageSent`, `MessageUpdated`, `MessageDeleted`, `MessageRead`, `ConversationUpdated`.
- Call `broadcast(...)->toOthers()`. The mobile client sends `X-Socket-ID` so the sender doesn't receive its own echo.
- The client matches its optimistic message using `client_uuid`.

## Push notifications (FCM)
- `NewMessageNotification` (queued, `FcmChannel`) goes to every other participant who is not muted and has device tokens. `User::routeNotificationForFcm()` returns the user's tokens.
- The payload carries a notification (title = sender or group name; body = text, "📷 Photo" or "🎤 Voice message") plus data (`conversation_id`, `message_id`). The mobile app hides the push when that conversation is already open.
- Add a listener on `NotificationFailed` that deletes invalid or unregistered tokens.
- Config: `FIREBASE_CREDENTIALS` points to the service-account JSON, which must not be committed.

## Media flow
1. The client uploads with `POST attachments`. Validation:
   - Images: jpeg, png, webp, heic, max 10 MB.
   - Audio: m4a/aac, ogg/opus, mp3, max 10 MB, plus the client-supplied `duration_ms`.
2. The file is stored on the `s3` disk under `attachments/{ulid}`. For images, a queued `GenerateThumbnail` job reads dimensions and creates a thumbnail.
3. The client sends a message with `attachment_ids`. The server checks that the attachments belong to the sender and are not used yet, then links them.
4. Resources return `Storage::temporaryUrl()` links (signed, about 60 min). Media is never public.
5. A scheduled command cleans up orphaned attachments older than 24 h (`routes/console.php`).

Dev setup: MinIO, or the `local` disk with `serve: true` for temporary URLs. `Storage::fake()` in tests.

## Send-message pipeline (`App\Actions\SendMessage`)
1. In a DB transaction: idempotency check on `client_uuid`, create the message, link attachments, update `conversations.last_message_id`, and advance the sender's `last_read_message_id`.
2. After commit: `broadcast(new MessageSent($message))->toOthers()`, and notify recipients with `NewMessageNotification`. Both are queued.

## Admin panel (Filament)
- Resources:
  - **Users**: list, search, change role, ban/unban (revokes tokens)
  - **Conversations**: view participants and messages, read-only
  - **Messages/Attachments**: moderation, soft delete
- A dashboard with stats widgets: users, messages per day, active today.
- `AdminUserSeeder`, or the `php artisan make:filament-user` command, plus setting `role=admin`.

## Docker (dev + production) and CI/CD
**One image, several roles.** A multi-stage `Dockerfile` builds a single app image. The container's command decides its role (web, queue worker, scheduler or Reverb). Dev and prod run the same image.

- **Base image:** `serversideup/php:8.5-fpm-nginx` (production-tuned PHP-FPM + Nginx, runs as non-root). If the 8.5 tag isn't published yet, fall back to 8.4. PHP extensions to add: `pdo_mysql`, `redis`, `intl`, `gd`/`imagick` (thumbnails), `pcntl` (Reverb and queue signal handling).
- **Stages:**
  1. `composer` stage: `composer install --no-dev --optimize-autoloader`
  2. `node` stage: `npm ci && npm run build` (Filament/Vite assets)
  3. `production` stage: copies the app, vendor and `public/build`. Runs `php artisan optimize` at container start, not at build time, so env vars are applied.
  4. `development` target: adds Xdebug and dev dependencies, and mounts the source as a volume.
- **`docker/` folder:** `entrypoint.sh` (optional `migrate --force` when `AUTORUN_MIGRATIONS=true`, then `optimize`), PHP ini overrides (`upload_max_filesize`/`post_max_size` = 20M for media), and an Nginx snippet if needed.
- **`.dockerignore`:** exclude `vendor`, `node_modules`, `.env`, `storage/logs`, `.git` and `tests` (tests are excluded for the prod target only).

**`compose.yaml` (local dev):**
| Service | Command / image | Port |
|---|---|---|
| `app` | image (dev target), web | 8000 |
| `queue` | `php artisan queue:work` (switch to Horizon later if needed) | – |
| `scheduler` | `php artisan schedule:work` | – |
| `reverb` | `php artisan reverb:start --host=0.0.0.0 --port=8080` | 8080 |
| `mysql` | `mysql:8.4` + volume + healthcheck | 3306 |
| `redis` | `redis:7-alpine` | 6379 |
| `minio` | `minio/minio` (S3 API + console) + a bucket-init job | 9000/9001 |
| `mailpit` | `axllent/mailpit` | 8025 |

`.env.example` is updated with Docker defaults:
- `DB_HOST=mysql`, `REDIS_HOST=redis`, `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`
- `AWS_ENDPOINT=http://minio:9000`, `AWS_USE_PATH_STYLE_ENDPOINT=true`
- `REVERB_HOST`, and `FIREBASE_CREDENTIALS=/var/www/html/storage/app/firebase.json` (mounted, git-ignored)

**Production compose example:** `compose.prod.yaml` uses the same services, pulls the image from GHCR, and has no bind mounts. Reverb sits behind the reverse proxy so mobile clients connect over `wss://`. The proxy setup is documented in the README.

**GitHub Actions (`.github/workflows/`):**
- `ci.yml` runs on PRs and pushes. Steps: PHP 8.5 setup, `composer install`, `vendor/bin/pint --test`, then `php artisan test` against a MySQL service container.
- `docker.yml` runs on push to `main` and on `v*` tags:
  - `docker/setup-buildx-action` + `docker/login-action` (GHCR, `GITHUB_TOKEN`)
  - `docker/metadata-action` for tags: `sha`, `latest` on main, semver on tags
  - `docker/build-push-action` with `target: production` and GHA layer cache
- A deploy step (SSH → `docker compose pull && up -d`, or your platform's hook) is left as a stub until the hosting target is chosen.

## Implementation order
0. Docker setup: `Dockerfile`, `compose.yaml`, `.dockerignore`, `docker/` and the `.env.example` updates. Confirm `docker compose up` serves the Laravel welcome page with MySQL connected. Add the GitHub Actions workflows.
1. Install Boost, then re-read `AGENTS.md`. Install Sanctum, Scramble and Filament.
2. Roles and auth API, plus the admin panel login.
3. Migrations, models, factories and policies for conversations and messages, plus the conversation/message REST endpoints.
4. Reverb broadcasting: channels, events, Sanctum broadcast auth.
5. Attachments and S3 media flow.
6. FCM device tokens and push notifications.
7. Filament resources and dashboard.
8. Write feature tests alongside each step.

## Verification
- `php artisan test`: feature tests (PHPUnit, `RefreshDatabase`) for:
  - auth and roles (a non-admin is blocked from `/admin`, a banned user gets 403)
  - creating direct chats (no duplicates) and groups
  - send, list and read messages, plus the idempotent `client_uuid`
  - authorization (non-participants get 403 on messages and on channel auth)
  - `Event::fake()` asserts `MessageSent`, `Notification::fake()` asserts FCM recipients (excluding the sender and muted users), `Storage::fake('s3')` covers uploads
- Docker:
  - `docker compose up -d --build` brings all services up healthy.
  - `docker compose exec app php artisan migrate` succeeds.
  - Uploads land in the MinIO bucket, and `ws://localhost:8080` accepts connections.
  - `docker build --target production .` succeeds locally.
  - The CI workflow passes on the first PR, and the image appears in GHCR after merging to `main`.
- Manual: with the Docker stack running (app, reverb and queue containers), Log in with curl or Postman, subscribe with a Pusher client (or `wscat`) to `private-conversation.{id}`, send a message, and confirm the event arrives and the FCM push reaches a test device.
- `/docs/api` (Scramble) shows the full contract for the mobile team. Run `vendor/bin/pint` before committing.
