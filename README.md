# Backline

Starter pure-PHP/MySQL baseline for Backline with route folders:

- `/admin/dash`
- `/admin/settings`
- `/auth/login`
- `/lx`
- `/snd`
- `/resources`

Includes:

- modern shared UI shell with preloader
- local settings storage (`BACKLINE_STORAGE_PATH/system_settings.php`, defaults outside web root) for DB + branding
- migration runner in Admin Settings
- initial schema migration at `/migrations/202609250001_initial_schema.sql`
