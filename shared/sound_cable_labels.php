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

$colorMap = [];
try {
    $hasHexColumnStmt = db()->query("SHOW COLUMNS FROM sound_cable_colors LIKE 'hex_code'");
    $hasHexColumn = (bool) $hasHexColumnStmt->fetch();
    if ($hasHexColumn) {
        $colorRows = db()->query("SELECT name, COALESCE(hex_code, '#1f9ad1') AS hex_code FROM sound_cable_colors")->fetchAll();
        foreach ($colorRows as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            $hex = trim((string) ($row['hex_code'] ?? ''));
            if ($name !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                $colorMap[$name] = $hex;
            }
        }
    }
} catch (Throwable) {
    $colorMap = [];
}

$cableStmt = db()->prepare('SELECT * FROM sound_cables WHERE show_id = ? ORDER BY sort_order, id');
$cableStmt->execute([$showId]);
$cables = $cableStmt->fetchAll();

$labels = [];
$seenCableIds = [];
foreach ($cables as $cable) {
    $cableId = (int) ($cable['id'] ?? 0);
    if ($cableId > 0) {
        if (isset($seenCableIds[$cableId])) {
            continue;
        }
        $seenCableIds[$cableId] = true;
    }
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
        'end_marker' => $fromSuffix !== '' ? $fromSuffix : 'FROM',
        'at_value' => $from !== '' ? $from : 'FROM',
        'color' => $color,
        'color_text' => strtoupper($color),
    ];
    $labels[] = [
        'overall' => $overall,
        'type' => $type,
        'length' => $length,
        'end_marker' => $toSuffix !== '' ? $toSuffix : 'TO',
        'at_value' => $to !== '' ? $to : 'TO',
        'color' => $color,
        'color_text' => strtoupper($color),
    ];
}

$labelsPerSheet = 80; // 4 across x 20 down
$labelPages = array_chunk($labels, $labelsPerSheet);
if (!$labelPages) {
    $labelPages = [[]];
}
foreach ($labelPages as &$pageLabels) {
    while (count($pageLabels) < $labelsPerSheet) {
        $pageLabels[] = null;
    }
}
unset($pageLabels);

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
    .labels-grid { display: grid; grid-template-columns: repeat(4, 1.875in); grid-template-rows: repeat(20, .5in); column-gap: .125in; row-gap: 0; justify-content: center; grid-auto-flow: column; }
    .label {
      border: 1px solid #b6b6b6;
      border-radius: .12in;
      box-sizing: border-box;
      overflow: hidden;
      padding: .02in .05in .03in .05in;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: .015in;
    }
    .label-line-top,
    .label-line-main,
    .label-line-bottom {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
      color: #060a10;
      font-weight: 700;
      line-height: 1.05;
    }
    .label-line-top { font-size: 7.2pt; }
    .label-line-main {
      font-size: 8pt;
      padding: .02in .02in;
      border-radius: .01in;
      background: var(--stripe-color, #1f9ad1);
      color: #081015;
    }
    .label-line-bottom {
      font-size: 7pt;
      font-weight: 600;
    }
    .label-empty { border: 1px dashed transparent; }
    .sheet-break-indicator {
      width: 8.5in;
      margin: .25rem auto;
      text-align: center;
      color: #6c757d;
      font-size: 12px;
      border-top: 1px dashed #b5b5b5;
      padding-top: .25rem;
    }
    .sheet-page-break {
      page-break-before: always;
      break-before: page;
    }
    @media print {
      .toolbar { display: none; }
      .sheet { margin: 0; width: auto; min-height: auto; padding: .5in .2in .3in .2in; }
      .sheet-break-indicator { display: none; }
      .sheet-page-break { page-break-before: always; break-before: page; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">Print</button>
    <span><?= e($showName) ?> · Avery 5167 · <?= count($labels) ?> labels</span>
  </div>
  <?php foreach ($labelPages as $pageIndex => $pageLabels): ?>
    <?php if ($pageIndex > 0): ?>
      <div class="sheet-break-indicator">Page break — next label sheet (<?= $pageIndex + 1 ?>)</div>
    <?php endif; ?>
    <div class="sheet <?= $pageIndex > 0 ? 'sheet-page-break' : '' ?>">
      <div class="labels-grid">
        <?php foreach ($pageLabels as $label): ?>
          <?php if (!is_array($label)): ?>
            <div class="label label-empty"></div>
            <?php continue; ?>
          <?php endif; ?>
          <?php
          $stripeColor = trim((string) ($label['color'] ?? ''));
          $safeStripe = '#1f9ad1';
          $stripeLookup = $colorMap[strtolower($stripeColor)] ?? null;
          if (is_string($stripeLookup) && preg_match('/^#[0-9a-fA-F]{6}$/', $stripeLookup)) {
              $safeStripe = $stripeLookup;
          } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $stripeColor)) {
              $safeStripe = $stripeColor;
          }
          ?>
          <div class="label" style="--stripe-color: <?= e($safeStripe) ?>">
            <div class="label-line-top"><?= e(trim($label['type'] . ($label['length'] !== '' ? (' (' . $label['length'] . ')') : '') . ' / ' . $label['end_marker'])) ?></div>
            <div class="label-line-main"><?= e($label['overall']) ?></div>
            <div class="label-line-bottom"><?= e('@' . $label['at_value'] . ($label['color_text'] !== '' ? (' · ' . $label['color_text']) : '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</body>
</html>
