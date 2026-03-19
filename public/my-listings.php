<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Helpers/functions.php';
require_once dirname(__DIR__) . '/src/Models/Listing.php';
require_once dirname(__DIR__) . '/src/Auth/Auth.php';

new Auth();
requireLogin();

$model  = new Listing();
$userId = (int)$_SESSION['user_id'];

// 下架處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate'])) {
    verifyCsrf();
    $model->deactivate((int)$_POST['listing_id'], $userId);
    setFlash('success', '刊登已下架');
    redirect('/my-listings.php');
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$result = $model->byUser($userId, $page);
$items  = $result['items'];
$total  = $result['total'];
$pagination = paginate($total, 20, $page);

$pageTitle = '我的刊登 | ZeroGrav';
include dirname(__DIR__) . '/templates/layout/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">我的刊登</h1>
    <a href="/listings/create.php"
       class="bg-primary text-white font-semibold px-5 py-2.5 rounded-xl hover:bg-primary-light transition text-sm">
      + 新增刊登
    </a>
  </div>

  <?php if (empty($items)): ?>
  <div class="bg-white rounded-2xl shadow-sm p-16 text-center text-gray-400">
    <div class="text-5xl mb-3">📋</div>
    <p class="font-medium">你還沒有任何刊登</p>
    <a href="/listings/create.php" class="mt-4 inline-block text-primary hover:underline text-sm">立即刊登第一件儀器</a>
  </div>
  <?php else: ?>

  <!-- 統計 -->
  <?php
  $statusCounts = array_count_values(array_column($items, 'status'));
  ?>
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    <?php foreach ([
        'active' => ['label' => '上架中', 'color' => 'green'],
        'pending'  => ['label' => '待審核', 'color' => 'yellow'],
        'draft'    => ['label' => '草稿',   'color' => 'gray'],
        'inactive' => ['label' => '已下架', 'color' => 'gray'],
        'rejected' => ['label' => '已拒絕', 'color' => 'red'],
    ] as $st => $info): ?>
    <div class="bg-white rounded-xl p-3 text-center shadow-sm">
      <p class="text-xl font-bold text-<?= $info['color'] ?>-600"><?= $statusCounts[$st] ?? 0 ?></p>
      <p class="text-xs text-gray-500"><?= $info['label'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- 列表 -->
  <div class="space-y-3">
    <?php foreach ($items as $item): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 flex gap-4 items-start">
      <!-- 縮圖 -->
      <a href="/listings/show.php?id=<?= (int)$item['id'] ?>"
         class="shrink-0 w-20 h-16 rounded-lg overflow-hidden bg-gray-100">
        <?php if ($item['cover_image']): ?>
        <img src="<?= e(uploadUrl($item['cover_image'], $item['id'])) ?>"
             alt="" class="w-full h-full object-cover">
        <?php else: ?>
        <div class="w-full h-full flex items-center justify-center text-2xl text-gray-300">🔬</div>
        <?php endif; ?>
      </a>

      <!-- 資訊 -->
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <div>
            <a href="/listings/show.php?id=<?= (int)$item['id'] ?>"
               class="font-semibold text-gray-800 hover:text-primary transition text-sm">
              <?= e($item['title']) ?>
            </a>
            <div class="flex items-center gap-2 mt-1">
              <?php
              $stColor = match($item['status']) {
                  'active'   => 'green',
                  'pending'  => 'yellow',
                  'rejected' => 'red',
                  default    => 'gray',
              };
              ?>
              <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $stColor ?>-100 text-<?= $stColor ?>-700">
                <?= statusLabel($item['status']) ?>
              </span>
              <span class="text-xs text-gray-400"><?= e($item['category_name']) ?></span>
              <span class="text-xs text-gray-400">👁 <?= number_format($item['views']) ?></span>
            </div>
            <?php if ($item['status'] === 'rejected' && $item['reject_reason']): ?>
            <p class="text-xs text-red-500 mt-1">拒絕原因：<?= e($item['reject_reason']) ?></p>
            <?php endif; ?>
          </div>
          <div class="text-right shrink-0">
            <p class="font-bold text-primary text-sm"><?= formatPrice((int)$item['price']) ?></p>
            <p class="text-xs text-gray-400"><?= formatDate($item['created_at']) ?></p>
          </div>
        </div>
      </div>

      <!-- 操作 -->
      <div class="shrink-0 flex flex-col gap-2">
        <a href="/listings/edit.php?id=<?= (int)$item['id'] ?>"
           class="text-xs text-center px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
          編輯
        </a>
        <?php if ($item['status'] === 'active'): ?>
        <form method="POST" onsubmit="return confirm('確定要下架此刊登嗎？')">
          <?= csrfField() ?>
          <input type="hidden" name="listing_id" value="<?= (int)$item['id'] ?>">
          <button type="submit" name="deactivate"
                  class="w-full text-xs px-3 py-1.5 border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition">
            下架
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- 分頁 -->
  <?php if ($pagination['total_pages'] > 1): ?>
  <div class="mt-6 flex justify-center gap-2">
    <?php if ($pagination['has_prev']): ?>
    <a href="?page=<?= $pagination['prev_page'] ?>" class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">←</a>
    <?php endif; ?>
    <span class="px-4 py-2 text-sm text-gray-500">
      第 <?= $pagination['current_page'] ?> / <?= $pagination['total_pages'] ?> 頁
    </span>
    <?php if ($pagination['has_next']): ?>
    <a href="?page=<?= $pagination['next_page'] ?>" class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">→</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/templates/layout/footer.php'; ?>
