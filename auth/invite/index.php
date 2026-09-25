<?php

declare(strict_types=1);

// Invite acceptance reuses reset flow.
redirect('/auth/reset?token=' . urlencode((string) ($_GET['token'] ?? '')));
