<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Models/Listing.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth();
requireAdmin();

$model     = new Listing();
$adminId   = (int)$_SESSION['user_id'];
$db        = getPDO();

// ── 審核動作（POST）──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $listingId = (int)($_POST['listing_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $reason    = trim($_POST['reject_reason'] ?? '');

    if ($listingId && in_array($action, ['approve', 'reject'])) {
        if ($action === 'reject' && empty($reason)) {
            setFlash('error', '請填寫拒絕原因');
        } else {
            $ok = $model->review($listingId, $adminId, $action, $reason);

            // 寫入管理日誌
            if ($ok) {
                $db->prepare(
                    "INSERT INTO admin_logs (admin_id, action, target_type, target_id, note, ip)
                     VALUES (?, ?, 'listing', ?, ?, ?)"
                )->execute([
                    $adminId,
                    $action === 'approve' ? 'approve_listing' : 'reject_listing',
                    $listingId,
                    $reason,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                ]);

                setFlash('success', $action === 'approve' ? '已核准刊登' : '已拒絕刊登');
            }
        }
    }

    redirect('/admin/listings.php?status=' . ($_GET['status'] ?? 'pending'));
}

// ── 查詢 ─────────────────────────────────────────────────────
$status     = $_GET['status'] ?? 'pending';
$page       = max(1, (int)($_GET['page'] ?? 1));
$result     = $model->adminList($status, $page, 30);
$items      = $result['items'];
$pagination = paginate($result['total'], 30, $page);

// 各狀態數量
$statusCounts = [];
foreach (['pending','active','inactive','rejected'] as $st) {
    $statusCounts[$st] = (int)$db->query(
        "SELECT COUNT(*) FROM listings WHERE status = '{$st}'"
    )->fetchColumn();
}

// 查看特定刊登詳情（審核用）
$reviewListing = null;
if (!empty($_GET['review'])) {
    $reviewListing = $model->find((int)$_GET['review']);
}

$pageTitle = '刊登管理 | ZeroGrav 後台';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="max-w-7xl mx-auto">

  <!-- 後台導覽 -->
  <div class="bg-gray-800 text-white rounded-2xl p-4 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <span class="font-bold text-lg">🔧 後台管理</span>
      <nav class="flex gap-3 text-sm">
        <a href="/admin/index.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">儀表板</a>
        <span class="bg-white/20 px-3 py-1 rounded-lg">刊登管理</span>
        <a href="/admin/banners.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">廣告管理</a>
      </nav>
    </div>
    <a href="/index.php" class="text-sm text-gray-300 hover:text-white transition">← 回前台</a>
  </div>

  <!-- 狀態篩選 Tab -->
  <div class="flex gap-2 mb-5 flex-wrap">
    <?php foreach ([
      ''         => ['label' => '全部', 'color' => 'gray'],
      'pending'  => ['label' => '待審核', 'color' => 'yellow'],
      'active'   => ['label' => '上架中', 'color' => 'green'],
      'inactive' => ['label' => '已下架', 'color' => 'gray'],
      'rejected' => ['label' => '已拒絕', 'color' => 'red'],
    ] as $st => $info): ?>
    <a href="/admin/listings.php?status=<?= $st ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium transition
              <?= $status === $st
                  ? 'bg-primary text-white shadow'
                  : 'bg-white text-gray-600 hover:bg-gray-50 border' ?>">
      <?= $info['label'] ?>
      <?php if ($st && isset($statusCounts[$st])): ?>
      <span class="ml-1 text-xs <?= $status === $st ? 'bg-white/20' : 'bg-gray-100' ?> px-1.5 rounded-full">
        <?= $statusCounts[$st] ?>
      </span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- 審核詳情彈窗（透過 GET review= 觸發） -->
  <?php if ($reviewListing): ?>
  <div x-data="{ open: true }" x-show="open"
       class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
       @keydown.escape.window="open = false">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto"
         @click.stop>
      <div class="p-6">
        <div class="flex items-start justify-between mb-4">
          <h2 class="font-bold text-xl text-gray-800">審核刊登 #<?= (int)$reviewListing['id'] ?></h2>
          <a href="/admin/listings.php?status=<?= $status ?>"
             class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</a>
        </div>

        <!-- 基本資訊 -->
        <div class="space-y-3 text-sm mb-5">
          <div class="grid grid-cols-2 gap-3">
            <div><span class="text-gray-400">賣家：</span><span class="font-medium"><?= e($reviewListing['seller_name']) ?></span></div>
            <div><span class="text-gray-400">Email：</span><?= e($reviewListing['seller_email'] ?? '') ?></div>
            <div><span class="text-gray-400">分類：</span><?= e($reviewListing['category_name']) ?></div>
            <div><span class="text-gray-400">成色：</span><?= conditionLabel($reviewListing['condition']) ?></div>
            <div><span class="text-gray-400">售價：</span><span class="font-bold text-primary"><?= formatPrice((int)$reviewListing['price']) ?></span></div>
            <div><span class="text-gray-400">地點：</span><?= e($reviewListing['location'] ?? '-') ?></div>
          </div>
          <div>
            <span class="text-gray-400">標題：</span>
            <span class="font-medium"><?= e($reviewListing['title']) ?></span>
          </div>
          <div>
            <span class="text-gray-400 block mb-1">描述：</span>
            <div class="bg-gray-50 rounded-lg p-3 text-gray-700 leading-relaxed max-h-36 overflow-y-auto whitespace-pre-wrap text-xs">
              <?= e($reviewListing['description']) ?>
            </div>
          </div>
        </div>

        <!-- 圖片 -->
        <?php if (!empty($reviewListing['images'])): ?>
        <div class="mb-5">
          <p class="text-sm text-gray-400 mb-2">圖片（<?= count($reviewListing['images']) ?> 張）</p>
          <div class="flex gap-2 overflow-x-auto pb-1">
            <?php foreach ($reviewListing['images'] as $img): ?>
            <img src="<?= e(uploadUrl($img['filename'], $reviewListing['id'])) ?>"
                 class="h-28 w-auto rounded-lg object-cover shrink-0">
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- 審核按鈕 -->
        <?php if ($reviewListing['status'] === 'pending'): ?>
        <div class="border-t pt-4">
          <!-- 核准 -->
          <form method="POST" class="mb-3" onsubmit="return confirm('確定要核准此刊登？')">
            <?= csrfField() ?>
            <input type="hidden" name="listing_id" value="<?= (int)$reviewListing['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit"
                    class="w-full bg-green-500 text-white font-bold py-3 rounded-xl hover:bg-green-600 transition">
              ✅ 核准上架
            </button>
          </form>
          <!-- 拒絕 -->
          <form method="POST" x-data="{ show: false }" onsubmit="return confirm('確定要拒絕此刊登？')">
            <?= csrfField() ?>
            <input type="hidden" name="listing_id" value="<?= (int)$reviewListing['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <div x-show="!show">
              <button type="button" @click="show = true"
                      class="w-full border border-red-300 text-red-500 font-medium py-2.5 rounded-xl hover:bg-red-50 transition">
                ✕ 拒絕刊登
              </button>
            </div>
            <div x-show="show" x-cloak class="space-y-2">
              <textarea name="reject_reason" required rows="3" placeholder="請填寫拒絕原因（賣家可見）"
                        class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"></textarea>
              <button type="submit"
                      class="w-full bg-red-500 text-white font-bold py-2.5 rounded-xl hover:bg-red-600 transition">
                確認拒絕
              </button>
            </div>
          </form>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 rounded-xl p-3 text-center text-sm text-gray-500">
          此刊登狀態：<?= statusLabel($reviewListing['status']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 刊登列表 -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="text-left px-4 py-3 text-gray-500 font-medium">ID</th>
          <th class="text-left px-4 py-3 text-gray-500 font-medium">標題</th>
          <th class="text-left px-4 py-3 text-gray-500 font-medium hidden md:table-cell">賣家</th>
          <th class="text-left px-4 py-3 text-gray-500 font-medium hidden lg:table-cell">分類</th>
          <th class="text-right px-4 py-3 text-gray-500 font-medium hidden lg:table-cell">售價</th>
          <th class="text-center px-4 py-3 text-gray-500 font-medium">狀態</th>
          <th class="text-left px-4 py-3 text-gray-500 font-medium hidden md:table-cell">日期</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if (empty($items)): ?>
        <tr><td colspan="8" class="text-center py-12 text-gray-400">沒有符合條件的刊登</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
        <tr class="hover:bg-gray-50 transition">
          <td class="px-4 py-3 text-gray-400">#<?= (int)$item['id'] ?></td>
          <td class="px-4 py-3 max-w-[200px]">
            <p class="font-medium text-gray-800 truncate"><?= e($item['title']) ?></p>
            <?php if ($item['brand']): ?>
            <p class="text-xs text-gray-400"><?= e($item['brand']) ?> <?= e($item['model'] ?? '') ?></p>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-gray-600 hidden md:table-cell"><?= e($item['seller_name']) ?></td>
          <td class="px-4 py-3 text-gray-500 hidden lg:table-cell text-xs"><?= e($item['category_name']) ?></td>
          <td class="px-4 py-3 text-right font-medium text-primary hidden lg:table-cell">
            <?= formatPrice((int)$item['price']) ?>
          </td>
          <td class="px-4 py-3 text-center">
            <?php
            $stColor = match($item['status']) {
                'active'   => 'green', 'pending' => 'yellow',
                'rejected' => 'red',   default   => 'gray',
            };
            ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $stColor ?>-100 text-<?= $stColor ?>-700">
              <?= statusLabel($item['status']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs hidden md:table-cell">
            <?= formatDate($item['created_at'], 'Y/m/d') ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-2 justify-end">
              <a href="/listings/show.php?id=<?= (int)$item['id'] ?>"
                 target="_blank"
                 class="text-xs px-2 py-1 border rounded hover:bg-gray-50 transition">檢視</a>
              <?php if ($item['status'] === 'pending'): ?>
              <a href="?status=<?= e($status) ?>&review=<?= (int)$item['id'] ?>"
                 class="text-xs px-2 py-1 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded hover:bg-yellow-100 transition">
                審核
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- 分頁 -->
  <?php if ($pagination['total_pages'] > 1): ?>
  <div class="mt-5 flex justify-center gap-2">
    <?php if ($pagination['has_prev']): ?>
    <a href="?status=<?= e($status) ?>&page=<?= $pagination['prev_page'] ?>"
       class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">←</a>
    <?php endif; ?>
    <span class="px-4 py-2 text-sm text-gray-500">
      <?= $pagination['current_page'] ?> / <?= $pagination['total_pages'] ?>
    </span>
    <?php if ($pagination['has_next']): ?>
    <a href="?status=<?= e($status) ?>&page=<?= $pagination['next_page'] ?>"
       class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm">→</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
