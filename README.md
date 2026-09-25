# Backline

Backline is a pure-PHP, Tabler-based theatre shop order platform for LX and SND workflows.

## Requirements
- PHP 8.1+
- MySQL 8+
- cPanel-compatible filesystem write access for `config/local.php` and `uploads/`

## Quick start
1. Deploy files to your web root.
2. Open `/setup`.
3. Enter database settings and bootstrap the first admin user.
4. Login at `/auth/login`.

## Routing
- `/` (marketing homepage)
- `/admin/dash`
- `/auth/login`
- `/profile`
- `/lx`
- `/snd`
- `/resources`

## Styling
- Tabler UI Admin loaded from CDN.
- Header uses JetBrains Mono.
- `shared/assets/custom.css` is loaded last for user overrides.
