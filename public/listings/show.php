<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Models/Listing.php';
require_once dirname(__DIR__, 2) . '/src/Models/Category.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /listings/index.php'); exit; }

$listingModel = new Listing();
$listing = $listingModel->find($id);

if (!$listing || ($listing['status'] !== 'active' && (!isLoggedIn() || ($_SESSION['user_id'] ?? 0) != $listing['user_id'] && !isAdmin()))) {
    http_response_code(404);
    $pageTitle = '找不到此刊登';
    include dirname(__DIR__, 2) . '/templates/layout/header.php';
    echo '<div class="text-center py-20 text-gray-400"><div class="text-6xl mb-4">🔍</div><p class="text-xl">找不到此刊登，可能已下架</p><a href="/listings/index.php" class="mt-4 inline-block text-primary hover:underline">← 回列表</a></div>';
    include dirname(__DIR__, 2) . '/templates/layout/footer.php';
    exit;
}

// 記錄瀏覽
$listingModel->incrementViews($id);

// 側欄廣告
$db = getPDO();
$sidebarBanners = $db->query(
    "SELECT * FROM banners WHERE position='sidebar' AND is_active=1
     AND (starts_at IS NULL OR starts_at <= CURDATE())
     AND (ends_at IS NULL OR ends_at >= CURDATE())
     ORDER BY sort_order LIMIT 3"
)->fetchAll();

$images  = $listing['images'];
$isOwner = isLoggedIn() && ($_SESSION['user_id'] ?? 0) == $listing['user_id'];

// 規格 JSON 解析
$specs = [];
if (!empty($listing['specs'])) {
    $decoded = json_decode($listing['specs'], true);
    if (is_array($decoded)) $specs = $decoded;
}

$pageTitle = e($listing['title']) . ' | ZeroGrav';
$pageDesc  = mb_substr(strip_tags($listing['description']), 0, 150);
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<!-- 麵包屑 -->
<nav class="text-sm text-gray-400 mb-4 flex items-center gap-1">
  <a href="/index.php" class="hover:text-primary">首頁</a>
  <span>/</span>
  <a href="/listings/index.php" class="hover:text-primary">儀器列表</a>
  <span>/</span>
  <a href="/listings/index.php?category=<?= e($listing['category_slug']) ?>" class="hover:text-primary">
    <?= e($listing['category_name']) ?>
  </a>
  <span>/</span>
  <span class="text-gray-600 truncate max-w-[200px]"><?= e($listing['title']) ?></span>
</nav>

<div class="flex gap-6">

  <!-- ── 主內容 ──────────────────────────────────────────── -->
  <div class="flex-1 min-w-0">

    <!-- 圖片輪播（純 JS，不依賴 Alpine） -->
    <?php if (!empty($images)): ?>
    <?php $totalImages = count($images); ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-5">

      <!-- 主圖區 -->
      <div class="relative bg-gray-100 overflow-hidden" style="aspect-ratio:16/10;">

        <?php foreach ($images as $i => $img): ?>
        <img src="<?= e(uploadUrl($img['filename'], $id)) ?>"
             alt="<?= e($listing['title']) ?> 圖片 <?= $i + 1 ?>"
             id="slide-<?= $id ?>-<?= $i ?>"
             class="absolute inset-0 w-full h-full object-contain transition-opacity duration-300"
             style="opacity:<?= $i === 0 ? '1' : '0' ?>; z-index:<?= $i === 0 ? '10' : '0' ?>;">
        <?php endforeach; ?>

        <?php if ($totalImages > 1): ?>
        <button onclick="zgSlide(<?= $id ?>, -1)"
                class="absolute left-3 top-1/2 -translate-y-1/2 z-20 bg-black/40 hover:bg-black/60 text-white w-10 h-10 rounded-full flex items-center justify-center text-2xl leading-none transition select-none">
          &#8249;
        </button>
        <button onclick="zgSlide(<?= $id ?>, 1)"
                class="absolute right-3 top-1/2 -translate-y-1/2 z-20 bg-black/40 hover:bg-black/60 text-white w-10 h-10 rounded-full flex items-center justify-center text-2xl leading-none transition select-none">
          &#8250;
        </button>
        <div class="absolute bottom-3 right-3 z-20 bg-black/50 text-white text-xs px-2 py-1 rounded-full">
          <span id="slide-counter-<?= $id ?>">1</span> / <?= $totalImages ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 縮圖列 -->
      <?php if ($totalImages > 1): ?>
      <div class="flex gap-2 p-3 overflow-x-auto">
        <?php foreach ($images as $i => $img): ?>
        <button onclick="zgGoto(<?= $id ?>, <?= $i ?>)"
                id="thumb-<?= $id ?>-<?= $i ?>"
                class="shrink-0 w-16 h-12 rounded-lg overflow-hidden transition border-2
                       <?= $i === 0 ? 'border-primary opacity-100' : 'border-transparent opacity-50' ?>">
          <img src="<?= e(uploadUrl($img['filename'], $id)) ?>"
               class="w-full h-full object-cover" alt="縮圖 <?= $i + 1 ?>">
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <script>
    (function () {
      var state = {};

      window.zgGoto = function (id, n) {
        if (!state[id]) state[id] = { current: 0, total: <?= $totalImages ?> };
        var s = state[id];
        var prev = s.current;
        s.current = ((n % s.total) + s.total) % s.total;

        // 大圖切換
        var prevEl = document.getElementById('slide-' + id + '-' + prev);
        var nextEl = document.getElementById('slide-' + id + '-' + s.current);
        if (prevEl) { prevEl.style.opacity = '0'; prevEl.style.zIndex = '0'; }
        if (nextEl) { nextEl.style.opacity = '1'; nextEl.style.zIndex = '10'; }

        // 計數器
        var counter = document.getElementById('slide-counter-' + id);
        if (counter) counter.textContent = s.current + 1;

        // 縮圖 highlight
        for (var i = 0; i < s.total; i++) {
          var t = document.getElementById('thumb-' + id + '-' + i);
          if (!t) continue;
          if (i === s.current) {
            t.style.borderColor = '#0f4c81';
            t.style.opacity = '1';
          } else {
            t.style.borderColor = 'transparent';
            t.style.opacity = '0.5';
          }
        }
      };

      window.zgSlide = function (id, dir) {
        if (!state[id]) state[id] = { current: 0, total: <?= $totalImages ?> };
        zgGoto(id, state[id].current + dir);
      };
    })();
    </script>

    <?php else: ?>
    <div class="bg-gray-100 rounded-2xl flex items-center justify-center text-8xl text-gray-300 mb-5" style="aspect-ratio:16/10;">🔬</div>
    <?php endif; ?>

    <!-- 標題 + 狀態 -->
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
          <?php if ($listing['status'] !== 'active'): ?>
          <span class="inline-block bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded mb-2">
            <?= statusLabel($listing['status']) ?>
          </span>
          <?php endif; ?>
          <h1 class="text-xl md:text-2xl font-bold text-gray-800"><?= e($listing['title']) ?></h1>
          <div class="flex flex-wrap items-center gap-3 mt-2">
            <span class="px-2 py-0.5 rounded-full text-xs
                         <?= $listing['condition'] === 'like_new' ? 'bg-green-100 text-green-700' :
                            ($listing['condition'] === 'good'     ? 'bg-blue-100 text-blue-700'   :
                            ($listing['condition'] === 'fair'     ? 'bg-yellow-100 text-yellow-700' :
                                                                     'bg-gray-100 text-gray-600')) ?>">
              <?= conditionLabel($listing['condition']) ?>
            </span>
            <span class="text-gray-400 text-xs">
              <?= e($listing['category_name']) ?>
            </span>
            <?php if ($listing['location']): ?>
            <span class="text-gray-400 text-xs">📍 <?= e($listing['location']) ?></span>
            <?php endif; ?>
            <span class="text-gray-400 text-xs">👁 <?= number_format($listing['views']) ?> 次瀏覽</span>
          </div>
        </div>
        <?php if ($isOwner): ?>
        <div class="flex gap-2 shrink-0">
          <a href="/listings/edit.php?id=<?= $id ?>"
             class="text-sm px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition">編輯</a>
        </div>
        <?php endif; ?>
      </div>

      <!-- 售價 -->
      <div class="mt-4 flex items-baseline gap-3">
        <span class="text-3xl font-bold text-primary"><?= formatPrice((int)$listing['price']) ?></span>
        <?php if ($listing['price_negotiable']): ?>
        <span class="text-sm text-gray-500 bg-gray-100 px-2 py-0.5 rounded">可議價</span>
        <?php endif; ?>
      </div>

      <!-- 品牌/型號/年份 -->
      <?php if ($listing['brand'] || $listing['model'] || $listing['year']): ?>
      <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <?php if ($listing['brand']): ?>
        <div class="bg-gray-50 rounded-lg p-2 text-center">
          <p class="text-xs text-gray-400 mb-0.5">品牌</p>
          <p class="font-medium text-gray-700"><?= e($listing['brand']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($listing['model']): ?>
        <div class="bg-gray-50 rounded-lg p-2 text-center">
          <p class="text-xs text-gray-400 mb-0.5">型號</p>
          <p class="font-medium text-gray-700"><?= e($listing['model']) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($listing['year']): ?>
        <div class="bg-gray-50 rounded-lg p-2 text-center">
          <p class="text-xs text-gray-400 mb-0.5">出廠年份</p>
          <p class="font-medium text-gray-700"><?= e($listing['year']) ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 詳細描述 -->
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
      <h2 class="font-bold text-gray-700 mb-3">儀器描述</h2>
      <div class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap"><?= e($listing['description']) ?></div>
    </div>

    <!-- 規格表 -->
    <?php if (!empty($specs)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-5">
      <h2 class="font-bold text-gray-700 mb-3">規格</h2>
      <table class="w-full text-sm">
        <?php foreach ($specs as $key => $val): ?>
        <tr class="border-b last:border-0">
          <td class="py-2 pr-4 text-gray-500 w-1/3"><?= e($key) ?></td>
          <td class="py-2 text-gray-700"><?= e($val) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- 刊登資訊 -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h2 class="font-bold text-gray-700 mb-2">刊登資訊</h2>
      <p class="text-xs text-gray-400">刊登日期：<?= formatDate($listing['created_at'], 'Y/m/d H:i') ?></p>
      <p class="text-xs text-gray-400">刊登編號：#<?= $id ?></p>
    </div>

  </div>

  <!-- ── 右側欄 ──────────────────────────────────────────── -->
  <aside class="hidden lg:block w-72 shrink-0">

    <!-- 聯絡賣家卡片 -->
    <div class="bg-white rounded-2xl shadow-sm p-5 mb-4 sticky top-4">
      <h2 class="font-bold text-gray-700 mb-3">聯絡賣家</h2>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center font-bold text-lg">
          <?= mb_substr($listing['seller_name'], 0, 1) ?>
        </div>
        <div>
          <p class="font-semibold text-sm text-gray-800"><?= e($listing['seller_name']) ?></p>
          <?php if ($listing['seller_company']): ?>
          <p class="text-xs text-gray-400"><?= e($listing['seller_company']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($listing['seller_phone']): ?>
      <!-- 電話 -->
      <a href="tel:<?= e(preg_replace('/\D/', '', $listing['seller_phone'])) ?>"
         onclick="logContact(<?= $id ?>, 'phone')"
         class="flex items-center justify-center gap-2 w-full bg-primary text-white font-semibold py-3 rounded-xl hover:bg-primary-light transition mb-3 text-sm">
        📞 立即撥打
      </a>

      <!-- 複製電話 -->
      <button onclick="copyPhone('<?= e($listing['seller_phone']) ?>')"
              x-data="{ copied: false }"
              @click="copied = true; setTimeout(() => copied = false, 2000)"
              class="flex items-center justify-center gap-2 w-full border border-gray-300 text-gray-700 font-medium py-2.5 rounded-xl hover:bg-gray-50 transition mb-3 text-sm">
        <span x-show="!copied">📋 複製電話</span>
        <span x-show="copied" x-cloak>✓ 已複製！</span>
      </button>
      <?php endif; ?>

      <?php if ($listing['seller_line']): ?>
      <!-- Line -->
      <a href="https://line.me/ti/p/~<?= urlencode($listing['seller_line']) ?>"
         target="_blank" rel="noopener"
         onclick="logContact(<?= $id ?>, 'line')"
         class="flex items-center justify-center gap-2 w-full bg-green-500 text-white font-semibold py-2.5 rounded-xl hover:bg-green-600 transition text-sm">
        💬 透過 Line 聯繫
      </a>
      <?php endif; ?>

      <?php if (!$listing['seller_phone'] && !$listing['seller_line']): ?>
      <p class="text-sm text-gray-400 text-center py-4">賣家未提供聯絡方式</p>
      <?php endif; ?>
    </div>

    <!-- 側欄廣告 -->
    <?php foreach ($sidebarBanners as $banner): ?>
    <div class="mb-4">
      <a href="<?= e($banner['link_url'] ?? '#') ?>" target="_blank" rel="noopener"
         onclick="trackBannerClick(<?= (int)$banner['id'] ?>)">
        <img src="<?= e($banner['image_path']) ?>" alt="<?= e($banner['title']) ?>"
             class="w-full rounded-xl shadow-sm">
      </a>
    </div>
    <?php endforeach; ?>

  </aside>
</div>

<!-- 手機版聯絡按鈕（固定底部） -->
<?php if ($listing['seller_phone'] || $listing['seller_line']): ?>
<div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg p-3 flex gap-3 z-40">
  <?php if ($listing['seller_phone']): ?>
  <a href="tel:<?= e(preg_replace('/\D/', '', $listing['seller_phone'])) ?>"
     onclick="logContact(<?= $id ?>, 'phone')"
     class="flex-1 bg-primary text-white text-center font-semibold py-3 rounded-xl text-sm">
    📞 撥打電話
  </a>
  <?php endif; ?>
  <?php if ($listing['seller_line']): ?>
  <a href="https://line.me/ti/p/~<?= urlencode($listing['seller_line']) ?>"
     target="_blank" rel="noopener"
     class="flex-1 bg-green-500 text-white text-center font-semibold py-3 rounded-xl text-sm">
    💬 Line 聯繫
  </a>
  <?php endif; ?>
</div>
<div class="lg:hidden h-20"></div>
<?php endif; ?>

<script>
function copyPhone(phone) {
  navigator.clipboard.writeText(phone).catch(() => {
    // 舊瀏覽器 fallback
    const el = document.createElement('input');
    el.value = phone;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
  });
  logContact(<?= $id ?>, 'copy');
}

function logContact(listingId, type) {
  fetch('/api/contact-log.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ listing_id: listingId, type: type })
  }).catch(() => {});
}
</script>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
