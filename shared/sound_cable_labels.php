<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

$user = require_auth();
if (!user_has_permission($user, 'snd.access') && !user_has_permission($user, 'snd.shop') && !user_has_permission($user, 'admin.access')) {
    http_response_code(403);
    exit('Forbidden');
}

$showId = (int) ($_GET['show_id'] ?? 0);
if ($showId <= 0) {
    http_response_code(400);
    exit('Missing show_id');
}

if (!user_has_permission($user, 'admin.access')) {
    if (show_user_access_table_exists()) {
        $accessStmt = db()->prepare('SELECT id FROM shows s WHERE s.id = ? AND s.deleted_at IS NULL AND (s.owner_user_id = ? OR EXISTS (SELECT 1 FROM show_user_access sua WHERE sua.show_id = s.id AND sua.user_id = ?)) LIMIT 1');
        $accessStmt->execute([$showId, (int) $user['id'], (int) $user['id']]);
    } else {
        $accessStmt = db()->prepare('SELECT id FROM shows WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL LIMIT 1');
        $accessStmt->execute([$showId, (int) $user['id']]);
    }
    if (!$accessStmt->fetchColumn()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$showStmt = db()->prepare('SELECT show_name FROM shows WHERE id = ? LIMIT 1');
$showStmt->execute([$showId]);
$showName = (string) ($showStmt->fetchColumn() ?: 'Show');

$cableStmt = db()->prepare('SELECT * FROM sound_cables WHERE show_id = ? ORDER BY sort_order, id');
$cableStmt->execute([$showId]);
$cables = $cableStmt->fetchAll();

$labels = [];
foreach ($cables as $cable) {
    $type = trim((string) ($cable['cable_type'] ?? ''));
    $typeLower = strtolower($type);
    $from = trim((string) ($cable['source_text'] ?? ''));
    $to = trim((string) ($cable['destination_text'] ?? ''));
    $overall = trim((string) (($cable['overall_label'] ?? '') ?: ($cable['display_name'] ?? '') ?: $type));
    $length = trim((string) ($cable['cable_length'] ?? ''));
    $adapter = trim((string) ($cable['adapter_text'] ?? ''));
    $color = trim((string) ($cable['color'] ?? ''));
    $loom = trim((string) ($cable['loom_name'] ?? ''));
    $loomId = trim((string) ($cable['loom_id_text'] ?? ''));
    $category = trim((string) ($cable['category_name'] ?? ''));

    $fromSuffix = '';
    $toSuffix = '';
    if (str_contains($typeLower, 'powercon')) {
        $fromSuffix = 'GRAY';
        $toSuffix = 'BLUE';
    } elseif (!str_contains($typeLower, 'rj45') && !str_contains($typeLower, 'bnc')) {
        $fromSuffix = 'M';
        $toSuffix = 'F';
    }

    $labels[] = [
        'overall' => $overall,
        'type' => $type,
        'length' => $length,
        'end_title' => 'FROM',
        'end_value' => $from . ($fromSuffix !== '' ? (' · ' . $fromSuffix) : ''),
        'mate_value' => $to,
        'adapter' => $adapter,
        'color' => $color,
        'loom' => $loom,
        'loom_id' => $loomId,
        'category' => $category,
    ];
    $labels[] = [
        'overall' => $overall,
        'type' => $type,
        'length' => $length,
        'end_title' => 'TO',
        'end_value' => $to . ($toSuffix !== '' ? (' · ' . $toSuffix) : ''),
        'mate_value' => $from,
        'adapter' => $adapter,
        'color' => $color,
        'loom' => $loom,
        'loom_id' => $loomId,
        'category' => $category,
    ];
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($showName) ?> Cable Labels</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #fff; color: #111; }
    .toolbar { padding: 10px 14px; border-bottom: 1px solid #ddd; display: flex; gap: 8px; align-items: center; }
    .sheet { width: 8.5in; min-height: 11in; margin: 0 auto; padding: .5in .2in .3in .2in; box-sizing: border-box; }
    .labels-grid { display: grid; grid-template-columns: repeat(4, 1.875in); grid-auto-rows: .5in; column-gap: .125in; row-gap: 0; justify-content: center; }
    .label { border: 1px dashed transparent; padding: .04in .06in; box-sizing: border-box; overflow: hidden; font-size: 8pt; line-height: 1.05; }
    .label-title { font-weight: 700; font-size: 8.2pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label-meta { font-size: 7pt; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label-end { font-weight: 700; }
    @media print {
      .toolbar { display: none; }
      .sheet { margin: 0; width: auto; min-height: auto; padding: .5in .2in .3in .2in; }
      .label { border-color: transparent !important; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">Print</button>
    <span><?= e($showName) ?> · Avery 5167 · <?= count($labels) ?> labels</span>
  </div>
  <div class="sheet">
    <div class="labels-grid">
      <?php foreach ($labels as $label): ?>
        <div class="label">
          <div class="label-title"><?= e($label['overall']) ?></div>
          <div class="label-meta"><?= e(trim($label['type'] . ' ' . $label['length'])) ?></div>
          <div class="label-meta label-end"><?= e($label['end_title'] . ': ' . $label['end_value']) ?></div>
          <div class="label-meta"><?= e('MATE: ' . $label['mate_value']) ?></div>
          <?php if ($label['adapter'] !== ''): ?><div class="label-meta"><?= e('ADAPTER: ' . $label['adapter']) ?></div><?php endif; ?>
          <?php if ($label['color'] !== '' || $label['loom'] !== '' || $label['loom_id'] !== ''): ?>
            <div class="label-meta"><?= e(trim(($label['color'] !== '' ? ('COLOR ' . $label['color']) : '') . ($label['loom'] !== '' ? (' · LOOM ' . $label['loom']) : '') . ($label['loom_id'] !== '' ? (' (' . $label['loom_id'] . ')') : ''))) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
