# Backline

Starter pure-PHP/MySQL baseline for Backline with route folders:

- `/admin/dash`
- `/admin/settings`
- `/auth/login`
- `/setup` (one-time installer)
- `/lx`
- `/snd`
- `/resources`

Includes:

- modern shared UI shell with preloader
- local settings storage (`BACKLINE_STORAGE_PATH/system_settings.php`, defaults to `/storage` in this project; set `BACKLINE_STORAGE_PATH` to move it outside web root)
- migration runner in Admin Settings
- initial schema migration at `/migrations/202609250001_initial_schema.sql`
- first-run setup that writes DB settings and creates the initial admin account
