<?php

declare(strict_types=1);

$user = require_permission('snd.access');

render_page('Sound', function () use ($user): void {
    echo '<div class="card show-context"><div class="card-body"><h3 class="card-title">Sound App</h3><p class="text-secondary">Shop orders, revisions, labels, cable mapping, and bundles are scaffolded for SND.</p></div></div>';
}, $user);
