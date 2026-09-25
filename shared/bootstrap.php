<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/ui.php';

date_default_timezone_set(config('timezone', 'UTC'));
