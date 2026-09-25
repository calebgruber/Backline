<?php

declare(strict_types=1);

auth_logout();
flash_set('info', 'Signed out.');
redirect('/auth/login');
