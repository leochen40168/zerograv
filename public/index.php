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

// ── 輪播廣告（banners 資料表 position='top'）────────────────
$carouselBanners = $db->query(
    "SELECT * FROM banners
     WHERE position = 'top' AND is_active = 1
       AND (starts_at IS NULL OR starts_at <= CURDATE())
       AND (ends_at   IS NULL OR ends_at   >= CURDATE())
     ORDER BY sort_order"
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

<!-- ── 廣告 Banner 輪播 ──────────────────────────────────── -->
<?php if (!empty($carouselBanners)): ?>
<?php $total = count($carouselBanners); ?>
<section class="mb-8 rounded-2xl overflow-hidden shadow-sm"
         id="hero-carousel"
         x-data="{
           cur: 0,
           total: <?= $total ?>,
           timer: null,
           start() {
             this.timer = setInterval(() => { this.next(); }, 5000);
           },
           stop() {
             clearInterval(this.timer);
           },
           next() {
             this.cur = (this.cur + 1) % this.total;
             this.syncStyles();
           },
           prev() {
             this.cur = (this.cur - 1 + this.total) % this.total;
             this.syncStyles();
           },
           goto(n) {
             this.cur = n;
             this.syncStyles();
           },
           syncStyles() {
             const slides = document.querySelectorAll('#hero-carousel .banner-slide');
             const dots   = document.querySelectorAll('#hero-carousel .banner-dot');
             slides.forEach((el, i) => {
               el.style.opacity  = i === this.cur ? '1' : '0';
               el.style.zIndex   = i === this.cur ? '10' : '0';
             });
             dots.forEach((el, i) => {
               el.style.opacity = i === this.cur ? '1' : '0.4';
               el.style.transform = i === this.cur ? 'scale(1.3)' : 'scale(1)';
             });
           }
         }"
         x-init="start(); syncStyles();"
         @mouseenter="stop()"
         @mouseleave="start()">

  <!-- 圖片層 -->
  <div class="relative w-full" style="aspect-ratio:4/1; min-height:120px;">

    <?php foreach ($carouselBanners as $i => $banner): ?>
    <a href="<?= e($banner['link_url'] ?? '#') ?>"
       target="<?= $banner['link_url'] ? '_blank' : '_self' ?>"
       rel="noopener"
       onclick="<?= $banner['link_url'] ? "fetch('/api/banner-click.php?id={$banner['id']}',{method:'POST'})" : '' ?>"
       class="banner-slide absolute inset-0 block transition-opacity duration-700"
       style="opacity:<?= $i === 0 ? '1' : '0' ?>; z-index:<?= $i === 0 ? '10' : '0' ?>;">
      <img src="<?= e($banner['image_path']) ?>"
           alt="<?= e($banner['title']) ?>"
           class="w-full h-full object-cover">
    </a>
    <?php endforeach; ?>

    <?php if ($total > 1): ?>
    <!-- 左箭頭 -->
    <button @click.prevent="prev()"
            class="absolute left-3 top-1/2 -translate-y-1/2 z-20
                   bg-black/30 hover:bg-black/50 text-white
                   w-9 h-9 rounded-full flex items-center justify-center
                   text-xl leading-none transition select-none">
      &#8249;
    </button>
    <!-- 右箭頭 -->
    <button @click.prevent="next()"
            class="absolute right-3 top-1/2 -translate-y-1/2 z-20
                   bg-black/30 hover:bg-black/50 text-white
                   w-9 h-9 rounded-full flex items-center justify-center
                   text-xl leading-none transition select-none">
      &#8250;
    </button>
    <?php endif; ?>
  </div>

  <?php if ($total > 1): ?>
  <!-- 圓點指示器 -->
  <div class="flex justify-center items-center gap-2 py-2 bg-white">
    <?php foreach ($carouselBanners as $i => $banner): ?>
    <button @click="goto(<?= $i ?>)"
            class="banner-dot w-2.5 h-2.5 rounded-full bg-primary transition-all duration-300"
            style="opacity:<?= $i === 0 ? '1' : '0.4' ?>; transform:<?= $i === 0 ? 'scale(1.3)' : 'scale(1)' ?>;">
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</section>
<?php endif; ?>

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
