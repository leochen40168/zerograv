<?php
/**
 * ZeroGrav - 首頁
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Helpers/functions.php';

session_name(env('SESSION_NAME', 'zerograv_sess'));
session_start();

$db = getPDO();

// ── 頂部 Banner ──────────────────────────────────────────────
$topBanners = $db->query(
    "SELECT * FROM banners
     WHERE position = 'top' AND is_active = 1
       AND (starts_at IS NULL OR starts_at <= CURDATE())
       AND (ends_at   IS NULL OR ends_at   >= CURDATE())
     ORDER BY sort_order LIMIT 3"
)->fetchAll();

// ── 最新刊登（12 筆）────────────────────────────────────────
$latestListings = $db->query(
    "SELECT l.*, c.name AS category_name, u.name AS seller_name
     FROM listings l
     JOIN categories c ON c.id = l.category_id
     JOIN users u ON u.id = l.user_id
     WHERE l.status = 'active'
     ORDER BY l.is_featured DESC, l.created_at DESC
     LIMIT 12"
)->fetchAll();

// ── 頂層分類 ─────────────────────────────────────────────────
$categories = $db->query(
    "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1
     ORDER BY sort_order"
)->fetchAll();

$pageTitle = 'ZeroGrav 二手儀器交易平台';
$pageDesc  = '台灣最專業的二手儀器交易平台，精密分析、測試測量、光學儀器買賣';

include dirname(__DIR__) . '/templates/layout/header.php';
?>

<!-- Hero 區塊 -->
<section class="bg-gradient-to-r from-primary to-primary-light text-white rounded-2xl p-8 mb-8 -mx-4 sm:mx-0">
  <div class="max-w-2xl">
    <h1 class="text-3xl md:text-4xl font-bold mb-3">
      專業二手儀器<br>安心買賣
    </h1>
    <p class="text-blue-100 mb-6">分析儀器、測試測量、光學設備，讓閒置儀器重新發揮價值</p>
    <div class="flex flex-wrap gap-3">
      <a href="/listings/create.php"
         class="bg-yellow-400 text-gray-900 font-bold px-6 py-3 rounded-xl hover:bg-yellow-300 transition text-sm">
        + 立即刊登
      </a>
      <a href="/listings/index.php"
         class="bg-white/20 hover:bg-white/30 px-6 py-3 rounded-xl transition text-sm border border-white/30">
        瀏覽所有儀器
      </a>
    </div>
  </div>
</section>

<!-- 分類快捷 -->
<section class="mb-10">
  <h2 class="text-xl font-bold text-gray-700 mb-4">儀器分類</h2>
  <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-9 gap-3">
    <?php foreach ($categories as $cat): ?>
    <a href="/search.php?category=<?= e($cat['slug']) ?>"
       class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md hover:-translate-y-0.5 transition group">
      <div class="text-2xl mb-1"><?= $cat['icon'] ?? '📦' ?></div>
      <div class="text-xs text-gray-600 group-hover:text-primary transition line-clamp-2">
        <?= e($cat['name']) ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- 最新刊登 -->
<section>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-xl font-bold text-gray-700">最新刊登</h2>
    <a href="/listings/index.php" class="text-sm text-primary hover:underline">查看全部 →</a>
  </div>

  <?php if (empty($latestListings)): ?>
  <div class="bg-white rounded-xl p-12 text-center text-gray-400">
    目前尚無刊登，<a href="/listings/create.php" class="text-primary hover:underline">成為第一位刊登者</a>！
  </div>
  <?php else: ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php foreach ($latestListings as $item): ?>
    <a href="/listings/show.php?id=<?= (int)$item['id'] ?>"
       class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition group">
      <!-- 縮圖 -->
      <div class="aspect-[4/3] bg-gray-100 overflow-hidden">
        <?php if ($item['cover_image']): ?>
        <img src="<?= e(uploadUrl($item['cover_image'], $item['id'])) ?>"
             alt="<?= e($item['title']) ?>"
             class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
        <?php else: ?>
        <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">🔬</div>
        <?php endif; ?>
      </div>
      <!-- 資訊 -->
      <div class="p-3">
        <?php if ($item['is_featured']): ?>
        <span class="inline-block bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded mb-1">精選</span>
        <?php endif; ?>
        <h3 class="font-semibold text-sm text-gray-800 line-clamp-2 group-hover:text-primary transition">
          <?= e($item['title']) ?>
        </h3>
        <p class="text-xs text-gray-400 mt-1"><?= e($item['brand'] ?? '') ?> <?= e($item['model'] ?? '') ?></p>
        <div class="mt-2 flex items-center justify-between">
          <span class="text-primary font-bold text-sm"><?= formatPrice((int)$item['price']) ?></span>
          <?php if ($item['price_negotiable']): ?>
          <span class="text-xs text-gray-400">可議</span>
          <?php endif; ?>
        </div>
        <div class="flex items-center justify-between mt-1">
          <span class="text-xs px-2 py-0.5 rounded-full bg-<?= conditionColor($item['condition']) ?>-100 text-<?= conditionColor($item['condition']) ?>-700">
            <?= conditionLabel($item['condition']) ?>
          </span>
          <span class="text-xs text-gray-400"><?= e($item['location'] ?? '') ?></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php include dirname(__DIR__) . '/templates/layout/footer.php'; ?>
