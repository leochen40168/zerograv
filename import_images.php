<?php
/**
 * ZeroGrav - 圖片匯入工具
 *
 * 步驟 1：?token=zg_img_2026&step=1  → 查詢刊登 ID，顯示上傳路徑對照表
 * 步驟 2：?token=zg_img_2026&step=2  → 圖片上傳完畢後，執行資料庫寫入
 *
 * 使用完畢請立即刪除此檔案！
 */

declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (($_GET['token'] ?? '') !== 'zg_img_2026') {
    http_response_code(403); die('403');
}

require_once dirname(__DIR__) . '/config/database.php';

$step = (int)($_GET['step'] ?? 1);
$db   = getPDO();

// ── 圖片對照表（標題關鍵字 → 圖片檔名清單，第一張為封面）────
$imageMap = [
    'Mitutoyo 三豐 515-322' => [
        'mitutoyo-515-322-1.jpg',
        'mitutoyo-515-322-2.jpg',
    ],
    '建暐 手動三次元量測儀' => [
        'chienwei-manual-cmm-1.jpg',
    ],
    '建暐 CE-504A' => [
        'chienwei-ce504a-1.jpg',
    ],
    'Instron WizHard' => [
        'instron-wizhard-1.jpg',
    ],
    '微克氏硬度計' => [
        'micro-vickers-1.jpg',
    ],
    'TESA Micro-Hite 3D' => [
        'tesa-microhite-1.jpg',
        'tesa-microhite-2.jpg',
    ],
    'WEI 威創 WEI-3020' => [
        'wei-3020-1.jpg',
        'wei-3020-2.jpg',
    ],
    'CARMAR VMM-3020C' => [
        'carmar-vmm3020c-1.jpg',
        'carmar-vmm3020c-2.jpg',
    ],
    'UPRtek MK350' => [
        'uprtek-mk350-1.jpg',
    ],
    'Leica S6' => [
        'leica-s6-1.jpg',
        'leica-s6-2.jpg',
    ],
];

// ── 查詢每筆刊登的 ID ─────────────────────────────────────────
$resolved = [];   // ['id'=>, 'title'=>, 'images'=>[...]]
$notFound = [];

foreach ($imageMap as $keyword => $files) {
    $stmt = $db->prepare(
        "SELECT id, title FROM listings WHERE title LIKE ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute(['%' . $keyword . '%']);
    $row = $stmt->fetch();

    if ($row) {
        $resolved[] = [
            'id'     => (int)$row['id'],
            'title'  => $row['title'],
            'images' => $files,
        ];
    } else {
        $notFound[] = $keyword;
    }
}

// ================================================================
// Step 1：顯示上傳路徑對照表
// ================================================================
if ($step === 1):
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
  <meta charset="UTF-8">
  <title>圖片匯入 - 步驟 1</title>
  <style>
    body  { font-family:sans-serif; max-width:900px; margin:40px auto; padding:0 20px; font-size:.9rem; }
    h1    { font-size:1.3rem; }
    h2    { font-size:1.1rem; margin-top:28px; border-bottom:2px solid #e5e7eb; padding-bottom:6px; }
    table { width:100%; border-collapse:collapse; margin-top:8px; }
    th,td { padding:8px 10px; border:1px solid #e5e7eb; text-align:left; vertical-align:top; }
    th    { background:#f9fafb; white-space:nowrap; }
    code  { background:#f3f4f6; padding:2px 6px; border-radius:4px; font-size:.85rem; word-break:break-all; }
    .cover { color:#0f4c81; font-weight:bold; }
    .warn  { background:#fef3c7; border:1px solid #fbbf24; border-radius:8px; padding:12px 16px; margin:20px 0; }
    .err   { background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:12px 16px; margin:20px 0; }
    .btn   { display:inline-block; background:#0f4c81; color:#fff; padding:10px 24px;
             border-radius:8px; text-decoration:none; font-weight:bold; margin-top:24px; }
  </style>
</head>
<body>
<h1>圖片匯入工具 - 步驟 1：上傳路徑對照表</h1>

<?php if (!empty($notFound)): ?>
<div class="err">
  ⚠️ 以下刊登在資料庫中找不到，請確認已執行 import_batch.php：<br>
  <?php foreach ($notFound as $k): ?>
  <code><?= htmlspecialchars($k) ?></code><br>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="warn">
  📋 請依下表將圖片上傳到伺服器對應路徑，完成後再點「步驟 2」寫入資料庫。<br>
  上傳路徑為 FTP 路徑，從 <code>public_html/</code> 開始算。
</div>

<h2>上傳路徑對照表</h2>
<table>
  <thead>
    <tr>
      <th>刊登 ID</th>
      <th>標題</th>
      <th>圖片檔名</th>
      <th>上傳到（FTP 路徑）</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($resolved as $item): ?>
    <?php foreach ($item['images'] as $idx => $filename): ?>
    <tr>
      <?php if ($idx === 0): ?>
      <td rowspan="<?= count($item['images']) ?>" style="text-align:center; font-weight:bold; font-size:1.1rem;">
        #<?= $item['id'] ?>
      </td>
      <td rowspan="<?= count($item['images']) ?>"><?= htmlspecialchars($item['title']) ?></td>
      <?php endif; ?>
      <td>
        <?php if ($idx === 0): ?>
        <span class="cover">★ <?= htmlspecialchars($filename) ?></span><br>
        <small>（封面）</small>
        <?php else: ?>
        <?= htmlspecialchars($filename) ?>
        <?php endif; ?>
      </td>
      <td>
        <code>public_html/uploads/listings/<?= $item['id'] ?>/<?= htmlspecialchars($filename) ?></code>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>FTP 操作步驟</h2>
<ol>
  <li>用 FTP 連線到伺服器</li>
  <li>進入 <code>public_html/uploads/listings/</code></li>
  <li>依對照表建立子目錄（資料夾名稱為刊登 ID 數字）</li>
  <li>將 <code>pic/renamed/</code> 裡對應的圖片上傳到各自目錄</li>
  <li>全部上傳完畢後，點下方「步驟 2」寫入資料庫</li>
</ol>

<a href="?token=zg_img_2026&step=2" class="btn">
  ✅ 圖片已上傳完畢，執行步驟 2 →
</a>

</body>
</html>

<?php
// ================================================================
// Step 2：寫入 listing_images 與 cover_image
// ================================================================
else:

$webRoot = (function() {
    $base = dirname(__DIR__);
    foreach (['public_html','public','www','htdocs'] as $d) {
        if (is_dir($base . '/' . $d)) return $base . '/' . $d;
    }
    return $base . '/public_html';
})();

$done    = [];
$skipped = [];
$errors  = [];

foreach ($resolved as $item) {
    $lid = $item['id'];

    foreach ($item['images'] as $sortOrder => $filename) {
        $filePath = $webRoot . '/uploads/listings/' . $lid . '/' . $filename;

        if (!file_exists($filePath)) {
            $skipped[] = "#$lid / $filename（檔案不存在，跳過）";
            continue;
        }

        // 避免重複寫入
        $check = $db->prepare(
            "SELECT id FROM listing_images WHERE listing_id = ? AND filename = ?"
        );
        $check->execute([$lid, $filename]);
        if ($check->fetch()) {
            $skipped[] = "#$lid / $filename（已存在，跳過）";
            continue;
        }

        try {
            $db->prepare(
                "INSERT INTO listing_images (listing_id, filename, original_name, sort_order)
                 VALUES (?, ?, ?, ?)"
            )->execute([$lid, $filename, $filename, $sortOrder]);

            // 第一張設為封面
            if ($sortOrder === 0) {
                $db->prepare(
                    "UPDATE listings SET cover_image = ? WHERE id = ?"
                )->execute([$filename, $lid]);
            }

            $done[] = "#$lid / $filename" . ($sortOrder === 0 ? ' ★封面' : '');
        } catch (PDOException $e) {
            $errors[] = "#$lid / $filename：" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
  <meta charset="UTF-8">
  <title>圖片匯入 - 步驟 2</title>
  <style>
    body  { font-family:sans-serif; max-width:860px; margin:40px auto; padding:0 20px; font-size:.9rem; }
    h1    { font-size:1.3rem; }
    .box  { border-radius:8px; padding:14px 18px; margin:14px 0; }
    .ok   { background:#f0fdf4; border:1px solid #86efac; }
    .skip { background:#f9fafb; border:1px solid #e5e7eb; }
    .err  { background:#fee2e2; border:1px solid #fca5a5; }
    .warn { background:#fef3c7; border:1px solid #fbbf24; }
    li    { margin:4px 0; }
    code  { background:#f3f4f6; padding:2px 6px; border-radius:4px; }
    .btn  { display:inline-block; background:#0f4c81; color:#fff; padding:10px 24px;
            border-radius:8px; text-decoration:none; font-weight:bold; }
  </style>
</head>
<body>
<h1>圖片匯入工具 - 步驟 2：資料庫寫入結果</h1>

<?php if (!empty($done)): ?>
<div class="box ok">
  <strong>✅ 成功寫入 <?= count($done) ?> 筆</strong>
  <ul><?php foreach ($done as $d): ?><li><?= htmlspecialchars($d) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!empty($skipped)): ?>
<div class="box skip">
  <strong>⏭ 跳過 <?= count($skipped) ?> 筆</strong>
  <ul><?php foreach ($skipped as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="box err">
  <strong>❌ 錯誤 <?= count($errors) ?> 筆</strong>
  <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="box warn">
  ⚠️ <strong>請立即刪除此檔案！</strong>
  <code>import_images.php</code>
</div>

<a href="/listings/index.php" class="btn" target="_blank">前往儀器列表確認</a>

</body>
</html>
<?php endif; ?>
