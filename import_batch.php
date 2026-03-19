<?php
/**
 * ZeroGrav - 批次匯入二手儀器資料
 * 執行方式：瀏覽器開啟 https://zerograv.com.tw/import_batch.php?token=zg_import_2026
 * 執行完畢請立即刪除此檔案！
 */

declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

// ── 安全保護 ─────────────────────────────────────────────────
if (($_GET['token'] ?? '') !== 'zg_import_2026') {
    http_response_code(403);
    die('403 Forbidden');
}

require_once __DIR__ . '/config/database.php';

// ── 分類 ID（對應 categories 資料表）────────────────────────
// 2 = 測試測量, 3 = 光學儀器
// 成色 ENUM: like_new=九成新, good=八成新/良好, fair=尚可, for_parts=零件用
// 狀態：active = 上架中（原需求 approved）

$instruments = [
    [
        'title'            => 'Mitutoyo 三豐 515-322 萬能高度基準器',
        'category_id'      => 2,
        'brand'            => 'Mitutoyo',
        'model'            => '515-322',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 60000,
        'price_negotiable' => 0,
        'description'      => '515 系列高精度萬能高度基準器（Universal Height Master），適用於實驗室或精密加工現場，可作為校正高度規、深度計或其他量測儀器的基準。含配送。',
    ],
    [
        'title'            => '建暐 手動三次元量測儀 行程 500×500×400mm',
        'category_id'      => 2,
        'brand'            => 'ChienWei（建暐）',
        'model'            => '',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 0,             // 價格請聯繫
        'price_negotiable' => 1,
        'description'      => '手動三次元量測儀，行程 500×500×400mm。含配送、安裝、教學及校正服務。價格請聯繫。',
    ],
    [
        'title'            => '建暐 CE-504A CNC 全自動三次元座標量測儀',
        'category_id'      => 2,
        'brand'            => 'ChienWei（建暐）',
        'model'            => 'CE-504A-CNC',
        'condition'        => 'like_new',    // 九成新 → like_new
        'price'            => 0,             // 價格請聯繫
        'price_negotiable' => 1,
        'description'      => '全自動 CNC 三次元座標量測儀。測量範圍 500×400×300mm，精度 U1=3+L/200μm，花崗岩工作台 720×1000mm，最大負載 150kg。配備 Renishaw MH20I 測頭及 MSU3D PRO 量測軟體。含配送、安裝、教學及校正。價格請聯繫。',
    ],
    [
        'title'            => 'Instron WizHard 洛氏硬度計',
        'category_id'      => 2,
        'brand'            => 'Instron',
        'model'            => 'WizHard',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 42000,
        'price_negotiable' => 1,
        'description'      => '洛氏硬度計，附觸控螢幕數位顯示，功能正常可直接使用。中古良品，含配送及教學。',
    ],
    [
        'title'            => '微克氏硬度計（含電腦影像分析系統）',
        'category_id'      => 2,
        'brand'            => '',
        'model'            => '',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 42000,
        'price_negotiable' => 1,
        'description'      => '微克氏（Micro Vickers）硬度計，搭配電腦影像分析軟體，可自動讀取壓痕並換算 HV、HRC、HRA 等多種硬度值。含螢幕及主機，中古良品，含配送及教學。',
    ],
    [
        'title'            => 'TESA Micro-Hite 3D 手動三次元量測儀',
        'category_id'      => 2,
        'brand'            => 'TESA',
        'model'            => 'Micro-Hite 3D',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 0,             // 價格請聯繫
        'price_negotiable' => 1,
        'description'      => '瑞士 TESA Micro-Hite 3D 手動三次元座標量測儀，測量行程 460×510×430mm，花崗岩工作台，配備 MH 3D 控制器及螢幕。外觀保存良好，含配送、安裝及教學。價格請聯繫。',
    ],
    [
        'title'            => 'WEI 威創 WEI-3020 手動 2.5D 影像量測儀',
        'category_id'      => 2,
        'brand'            => 'WEI（威創）',
        'model'            => 'WEI-3020',
        'condition'        => 'like_new',    // 九成新 → like_new
        'price'            => 135000,
        'price_negotiable' => 1,
        'description'      => '手動 2.5D 影像量測系統（Video Measuring System），量測範圍 300×200mm，僅使用 2 個月，狀況極佳。附電腦、螢幕、鍵盤滑鼠及量測軟體，含配送、安裝、教學及校正。',
    ],
    [
        'title'            => 'CARMAR VMM-3020C 全自動 CNC 2.5D 影像量測儀',
        'category_id'      => 2,
        'brand'            => 'CARMAR',
        'model'            => 'VMM-3020C',
        'condition'        => 'like_new',    // 九成新 → like_new
        'price'            => 378000,
        'price_negotiable' => 1,
        'description'      => '2025 年出廠，近全新品。全自動 CNC 2.5D 影像量測儀，行程 300×200mm，配備 200 萬畫素數位 CCD 及全自動變倍鏡頭。附電腦、螢幕及量測軟體，歡迎現場看機。',
    ],
    [
        'title'            => 'UPRtek MK350 LED 手持式光譜計',
        'category_id'      => 3,
        'brand'            => 'UPRtek',
        'model'            => 'MK350',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 0,             // 價格請聯繫
        'price_negotiable' => 1,
        'description'      => '手持式 LED 光譜計，可量測光譜分佈、CIE 色座標、色溫等參數，觸控螢幕操作，支援繁體中文介面。功能正常，含原廠使用說明書及鋁合金攜帶箱。價格請聯繫。',
    ],
    [
        'title'            => 'Leica S6 立體顯微鏡 6.3-40 倍',
        'category_id'      => 3,
        'brand'            => 'Leica',
        'model'            => 'S6',
        'condition'        => 'good',        // 八成新 → good
        'price'            => 15000,
        'price_negotiable' => 0,
        'description'      => '德國萊卡 S6 立體顯微鏡，變倍範圍 6.3-40 倍，雙目觀察，附底座及 LED 照明。適用於電子維修、品管檢測等。含配送。',
    ],
];

// ── 執行匯入 ─────────────────────────────────────────────────
$db = getPDO();

$sql = "INSERT INTO listings
        (user_id, category_id, title, brand, model, `condition`,
         price, price_negotiable, description, status,
         reviewed_by, reviewed_at)
        VALUES
        (1, :category_id, :title, :brand, :model, :condition,
         :price, :price_negotiable, :description, 'active',
         1, NOW())";

$stmt = $db->prepare($sql);

$success = 0;
$failed  = 0;
$results = [];

foreach ($instruments as $i => $item) {
    try {
        $stmt->execute([
            ':category_id'      => $item['category_id'],
            ':title'            => $item['title'],
            ':brand'            => $item['brand'],
            ':model'            => $item['model'],
            ':condition'        => $item['condition'],
            ':price'            => $item['price'],
            ':price_negotiable' => $item['price_negotiable'],
            ':description'      => $item['description'],
        ]);
        $newId = $db->lastInsertId();
        $success++;
        $results[] = ['ok' => true, 'no' => $i + 1, 'id' => $newId, 'title' => $item['title']];
    } catch (PDOException $e) {
        $failed++;
        $results[] = ['ok' => false, 'no' => $i + 1, 'title' => $item['title'], 'error' => $e->getMessage()];
    }
}

// ── 輸出結果 ─────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
  <meta charset="UTF-8">
  <title>批次匯入結果</title>
  <style>
    body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
    h1   { font-size: 1.4rem; margin-bottom: 4px; }
    .summary { background:#f3f4f6; border-radius:8px; padding:16px; margin:16px 0;
               display:flex; gap:32px; }
    .summary b { font-size:2rem; }
    .ok   { color:#16a34a; }
    .fail { color:#dc2626; }
    table { width:100%; border-collapse:collapse; font-size:.9rem; }
    th,td { padding:8px 12px; border:1px solid #e5e7eb; text-align:left; }
    th    { background:#f9fafb; }
    tr.ok-row   td:first-child { border-left:4px solid #16a34a; }
    tr.fail-row td:first-child { border-left:4px solid #dc2626; }
    .warn { background:#fef3c7; border:1px solid #fbbf24; border-radius:8px;
            padding:12px 16px; margin-top:24px; font-size:.875rem; }
  </style>
</head>
<body>

<h1>ZeroGrav 批次匯入結果</h1>

<div class="summary">
  <div><b class="ok"><?= $success ?></b><br>成功</div>
  <div><b class="<?= $failed > 0 ? 'fail' : 'ok' ?>"><?= $failed ?></b><br>失敗</div>
  <div><b><?= $success + $failed ?></b><br>總計</div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>標題</th>
      <th>結果</th>
      <th>ID / 錯誤</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($results as $r): ?>
    <tr class="<?= $r['ok'] ? 'ok-row' : 'fail-row' ?>">
      <td><?= $r['no'] ?></td>
      <td><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= $r['ok'] ? '✅ 成功' : '❌ 失敗' ?></td>
      <td>
        <?php if ($r['ok']): ?>
          <a href="/listings/show.php?id=<?= $r['id'] ?>" target="_blank">
            #<?= $r['id'] ?> 查看
          </a>
        <?php else: ?>
          <?= htmlspecialchars($r['error'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="warn">
  ⚠️ <strong>請立即刪除此檔案！</strong><br>
  <code>import_batch.php</code> 含有資料庫寫入權限，使用完畢後請從伺服器刪除，
  避免被重複執行或惡意存取。
</div>

<?php if ($success > 0): ?>
<p style="margin-top:16px;">
  <a href="/listings/index.php" target="_blank"
     style="background:#0f4c81;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">
    前往儀器列表查看
  </a>
</p>
<?php endif; ?>

</body>
</html>
