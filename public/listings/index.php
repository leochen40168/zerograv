<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Models/Listing.php';
require_once dirname(__DIR__, 2) . '/src/Models/Category.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth(); // 啟動 session

$listingModel  = new Listing();
$categoryModel = new Category();

// ── 參數收集 ─────────────────────────────────────────────────
$params = [
    'q'         => trim($_GET['q'] ?? ''),
    'category'  => trim($_GET['category'] ?? ''),
    'condition' => trim($_GET['condition'] ?? ''),
    'min_price' => (int)($_GET['min_price'] ?? 0) ?: null,
    'max_price' => (int)($_GET['max_price'] ?? 0) ?: null,
    'location'  => trim($_GET['location'] ?? ''),
    'sort'      => $_GET['sort'] ?? 'newest',
    'page'      => max(1, (int)($_GET['page'] ?? 1)),
    'per_page'  => 20,
];

$result     = $listingModel->search($params);
$items      = $result['items'];
$pagination = paginate($result['total'], $result['per_page'], $result['page']);
$categories = $categoryModel->allWithChildren();

// ── 台灣縣市清單 ─────────────────────────────────────────────
$cities = ['台北市','新北市','基隆市','桃園市','新竹市','新竹縣','苗栗縣',
           '台中市','彰化縣','南投縣','雲林縣','嘉義市','嘉義縣',
           '台南市','高雄市','屏東縣','宜蘭縣','花蓮縣','台東縣',
           '澎湖縣','金門縣','連江縣'];

$pageTitle = ($params['q'] ? "「{$params['q']}」搜尋結果" : '儀器列表') . ' | ZeroGrav';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="flex gap-6">

  <!-- ── 左側篩選面板 ─────────────────────────────────────── -->
  <aside class="hidden lg:block w-56 shrink-0" x-data="{ openCats: {} }">
    <form id="filterForm" method="GET" action="/listings/index.php">
      <?php if ($params['q']): ?>
      <input type="hidden" name="q" value="<?= e($params['q']) ?>">
      <?php endif; ?>

      <div class="bg-white rounded-xl shadow-sm p-4 space-y-5">

        <!-- 分類 -->
        <div>
          <h3 class="font-semibold text-sm text-gray-700 mb-2">分類</h3>
          <div class="space-y-1 text-sm">
            <label class="flex items-center gap-2 cursor-pointer hover:text-primary">
              <input type="radio" name="category" value=""
                     <?= $params['category'] === '' ? 'checked' : '' ?>
                     onchange="document.getElementById('filterForm').submit()">
              <span>全部分類</span>
            </label>
            <?php foreach ($categories as $parent): ?>
            <div>
              <button type="button"
                      @click="openCats['<?= e($parent['slug']) ?>'] = !openCats['<?= e($parent['slug']) ?>']"
                      class="flex items-center justify-between w-full hover:text-primary py-0.5">
                <span><?= e($parent['icon'] ?? '') ?> <?= e($parent['name']) ?></span>
                <span x-text="openCats['<?= e($parent['slug']) ?>'] ? '▲' : '▼'" class="text-xs text-gray-400"></span>
              </button>
              <div x-show="openCats['<?= e($parent['slug']) ?>']" x-cloak class="ml-4 space-y-0.5 mt-1">
                <label class="flex items-center gap-2 cursor-pointer hover:text-primary">
                  <input type="radio" name="category" value="<?= e($parent['slug']) ?>"
                         <?= $params['category'] === $parent['slug'] ? 'checked' : '' ?>
                         onchange="document.getElementById('filterForm').submit()">
                  <span>全部<?= e($parent['name']) ?></span>
                </label>
                <?php foreach ($parent['children'] as $child): ?>
                <label class="flex items-center gap-2 cursor-pointer hover:text-primary">
                  <input type="radio" name="category" value="<?= e($child['slug']) ?>"
                         <?= $params['category'] === $child['slug'] ? 'checked' : '' ?>
                         onchange="document.getElementById('filterForm').submit()">
                  <span><?= e($child['name']) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 成色 -->
        <div>
          <h3 class="font-semibold text-sm text-gray-700 mb-2">成色</h3>
          <div class="space-y-1 text-sm">
            <?php foreach (['' => '全部', 'like_new' => '九成新', 'good' => '良好', 'fair' => '尚可', 'for_parts' => '零件用'] as $val => $label): ?>
            <label class="flex items-center gap-2 cursor-pointer hover:text-primary">
              <input type="radio" name="condition" value="<?= e($val) ?>"
                     <?= $params['condition'] === $val ? 'checked' : '' ?>
                     onchange="document.getElementById('filterForm').submit()">
              <span><?= $label ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 價格區間 -->
        <div>
          <h3 class="font-semibold text-sm text-gray-700 mb-2">價格（NT$）</h3>
          <div class="flex items-center gap-2">
            <input type="number" name="min_price" placeholder="最低"
                   value="<?= e($params['min_price'] ?? '') ?>"
                   class="w-full border rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary">
            <span class="text-gray-400 text-xs">~</span>
            <input type="number" name="max_price" placeholder="最高"
                   value="<?= e($params['max_price'] ?? '') ?>"
                   class="w-full border rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary">
          </div>
          <button type="submit" class="mt-2 w-full text-xs bg-gray-100 hover:bg-gray-200 py-1.5 rounded transition">
            套用價格
          </button>
        </div>

        <!-- 所在地 -->
        <div>
          <h3 class="font-semibold text-sm text-gray-700 mb-2">所在地</h3>
          <select name="location" onchange="document.getElementById('filterForm').submit()"
                  class="w-full border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
            <option value="">全部地區</option>
            <?php foreach ($cities as $city): ?>
            <option value="<?= e($city) ?>" <?= $params['location'] === $city ? 'selected' : '' ?>>
              <?= e($city) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 清除篩選 -->
        <?php if (array_filter([$params['category'], $params['condition'], $params['min_price'], $params['max_price'], $params['location']])): ?>
        <a href="/listings/index.php<?= $params['q'] ? '?q=' . urlencode($params['q']) : '' ?>"
           class="block text-xs text-center text-gray-400 hover:text-red-500 transition">
          ✕ 清除所有篩選
        </a>
        <?php endif; ?>
      </div>
    </form>
  </aside>

  <!-- ── 右側主區域 ────────────────────────────────────────── -->
  <div class="flex-1 min-w-0">

    <!-- 標題列 + 排序 -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h1 class="text-lg font-bold text-gray-700">
          <?php if ($params['q']): ?>
            「<?= e($params['q']) ?>」的搜尋結果
          <?php elseif ($params['category']): ?>
            <?= e($params['category']) ?>
          <?php else: ?>
            全部儀器
          <?php endif; ?>
        </h1>
        <p class="text-sm text-gray-400">共 <?= number_format($result['total']) ?> 筆</p>
      </div>

      <!-- 排序（手機也有） -->
      <form method="GET" action="/listings/index.php" class="flex items-center gap-2">
        <?php foreach (['q','category','condition','min_price','max_price','location'] as $k): ?>
        <?php if (!empty($params[$k])): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($params[$k]) ?>">
        <?php endif; ?>
        <?php endforeach; ?>
        <select name="sort" onchange="this.form.submit()"
                class="border rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary bg-white">
          <option value="newest"     <?= $params['sort'] === 'newest'     ? 'selected' : '' ?>>最新上架</option>
          <option value="price_asc"  <?= $params['sort'] === 'price_asc'  ? 'selected' : '' ?>>價格低→高</option>
          <option value="price_desc" <?= $params['sort'] === 'price_desc' ? 'selected' : '' ?>>價格高→低</option>
          <option value="views"      <?= $params['sort'] === 'views'      ? 'selected' : '' ?>>熱門優先</option>
        </select>
      </form>
    </div>

    <!-- 刊登列表 -->
    <?php if (empty($items)): ?>
    <div class="bg-white rounded-xl p-16 text-center text-gray-400">
      <div class="text-5xl mb-3">🔍</div>
      <p class="font-medium">找不到符合條件的儀器</p>
      <p class="text-sm mt-1">請嘗試調整搜尋條件</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php foreach ($items as $item): ?>
      <a href="/listings/show.php?id=<?= (int)$item['id'] ?>"
         class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition group">
        <div class="aspect-[4/3] bg-gray-100 overflow-hidden relative">
          <?php if ($item['cover_image']): ?>
          <img src="<?= e(uploadUrl($item['cover_image'], $item['id'])) ?>"
               alt="<?= e($item['title']) ?>"
               class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
               loading="lazy">
          <?php else: ?>
          <div class="w-full h-full flex items-center justify-center text-5xl text-gray-200">🔬</div>
          <?php endif; ?>
          <?php if ($item['is_featured']): ?>
          <span class="absolute top-2 left-2 bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded">精選</span>
          <?php endif; ?>
        </div>
        <div class="p-3">
          <p class="text-xs text-gray-400 mb-0.5"><?= e($item['category_name']) ?></p>
          <h3 class="font-semibold text-sm text-gray-800 line-clamp-2 group-hover:text-primary transition leading-snug">
            <?= e($item['title']) ?>
          </h3>
          <?php if ($item['brand'] || $item['model']): ?>
          <p class="text-xs text-gray-400 mt-0.5"><?= e($item['brand']) ?> <?= e($item['model']) ?></p>
          <?php endif; ?>
          <div class="mt-2 flex items-end justify-between">
            <div>
              <span class="text-primary font-bold text-sm"><?= formatPrice((int)$item['price']) ?></span>
              <?php if ($item['price_negotiable']): ?>
              <span class="text-xs text-gray-400 ml-1">可議</span>
              <?php endif; ?>
            </div>
            <span class="text-xs px-1.5 py-0.5 rounded-full
                         <?= $item['condition'] === 'like_new' ? 'bg-green-100 text-green-700' :
                            ($item['condition'] === 'good'     ? 'bg-blue-100 text-blue-700'   :
                            ($item['condition'] === 'fair'     ? 'bg-yellow-100 text-yellow-700' :
                                                                  'bg-gray-100 text-gray-600')) ?>">
              <?= conditionLabel($item['condition']) ?>
            </span>
          </div>
          <div class="flex items-center justify-between mt-1.5 text-xs text-gray-400">
            <span><?= e($item['location'] ?? '') ?></span>
            <span><?= timeAgo($item['created_at']) ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- 分頁 -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="mt-8 flex justify-center">
      <nav class="flex items-center gap-1">
        <?php
        $baseUrl = '/listings/index.php?' . http_build_query(array_filter(
            array_merge($params, ['page' => null, 'per_page' => null]),
            fn($v) => $v !== null && $v !== ''
        ));
        ?>

        <?php if ($pagination['has_prev']): ?>
        <a href="<?= $baseUrl ?>&page=<?= $pagination['prev_page'] ?>"
           class="px-3 py-2 rounded-lg bg-white border hover:bg-gray-50 text-sm transition">←</a>
        <?php endif; ?>

        <?php
        $start = max(1, $pagination['current_page'] - 2);
        $end   = min($pagination['total_pages'], $pagination['current_page'] + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $p ?>"
           class="px-3 py-2 rounded-lg border text-sm transition
                  <?= $p === $pagination['current_page']
                      ? 'bg-primary text-white border-primary'
                      : 'bg-white hover:bg-gray-50' ?>">
          <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($pagination['has_next']): ?>
        <a href="<?= $baseUrl ?>&page=<?= $pagination['next_page'] ?>"
           class="px-3 py-2 rounded-lg bg-white border hover:bg-gray-50 text-sm transition">→</a>
        <?php endif; ?>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
