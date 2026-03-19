<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth();
requireAdmin();

$db = getPDO();

// ── 統計數字 ─────────────────────────────────────────────────
$stats = [];
foreach ([
    'total_users'    => "SELECT COUNT(*) FROM users WHERE role='user'",
    'total_listings' => "SELECT COUNT(*) FROM listings",
    'pending'        => "SELECT COUNT(*) FROM listings WHERE status='pending'",
    'active'         => "SELECT COUNT(*) FROM listings WHERE status='active'",
    'today_views'    => "SELECT COALESCE(SUM(views),0) FROM listings",
    'contact_today'  => "SELECT COUNT(*) FROM contact_logs WHERE DATE(created_at)=CURDATE()",
] as $key => $sql) {
    $stats[$key] = (int)$db->query($sql)->fetchColumn();
}

// ── 最新待審（5筆）──────────────────────────────────────────
$pendingItems = $db->query(
    "SELECT l.id, l.title, u.name AS seller_name, l.created_at
     FROM listings l JOIN users u ON u.id = l.user_id
     WHERE l.status='pending' ORDER BY l.created_at ASC LIMIT 5"
)->fetchAll();

// ── 最新會員（5筆）──────────────────────────────────────────
$newUsers = $db->query(
    "SELECT id, name, email, created_at FROM users WHERE role='user'
     ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$pageTitle = '後台管理 | ZeroGrav';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="max-w-6xl mx-auto">

  <!-- 後台頂部導覽 -->
  <div class="bg-gray-800 text-white rounded-2xl p-4 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <span class="font-bold text-lg">🔧 後台管理</span>
      <nav class="flex gap-3 text-sm">
        <span class="bg-white/20 px-3 py-1 rounded-lg">儀表板</span>
        <a href="/admin/listings.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">刊登管理</a>
        <a href="/admin/banners.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">廣告管理</a>
        <a href="/admin/users.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">會員管理</a>
      </nav>
    </div>
    <a href="/index.php" class="text-sm text-gray-300 hover:text-white transition">← 回前台</a>
  </div>

  <!-- 統計卡片 -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <?php foreach ([
      ['label' => '總會員數',   'value' => $stats['total_users'],    'color' => 'blue',   'icon' => '👥'],
      ['label' => '總刊登數',   'value' => $stats['total_listings'],  'color' => 'indigo', 'icon' => '📋'],
      ['label' => '待審核',     'value' => $stats['pending'],         'color' => 'yellow', 'icon' => '⏳'],
      ['label' => '上架中',     'value' => $stats['active'],          'color' => 'green',  'icon' => '✅'],
      ['label' => '總瀏覽',     'value' => number_format($stats['today_views']), 'color' => 'purple', 'icon' => '👁'],
      ['label' => '今日聯絡',   'value' => $stats['contact_today'],   'color' => 'pink',   'icon' => '📞'],
    ] as $card): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
      <div class="text-2xl mb-1"><?= $card['icon'] ?></div>
      <div class="text-2xl font-bold text-<?= $card['color'] ?>-600"><?= $card['value'] ?></div>
      <div class="text-xs text-gray-500 mt-0.5"><?= $card['label'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- 待審核刊登 -->
    <div class="bg-white rounded-2xl shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-gray-700">待審核刊登</h2>
        <a href="/admin/listings.php?status=pending" class="text-sm text-primary hover:underline">查看全部</a>
      </div>
      <?php if (empty($pendingItems)): ?>
      <p class="text-sm text-gray-400 text-center py-8">目前沒有待審核的刊登 🎉</p>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($pendingItems as $item): ?>
        <div class="flex items-center justify-between gap-3 py-2 border-b last:border-0">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-800 truncate"><?= e($item['title']) ?></p>
            <p class="text-xs text-gray-400"><?= e($item['seller_name']) ?> · <?= timeAgo($item['created_at']) ?></p>
          </div>
          <a href="/admin/listings.php?review=<?= (int)$item['id'] ?>"
             class="shrink-0 text-xs bg-yellow-50 text-yellow-700 border border-yellow-200 px-3 py-1 rounded-lg hover:bg-yellow-100 transition">
            審核
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 最新會員 -->
    <div class="bg-white rounded-2xl shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-gray-700">最新會員</h2>
        <a href="/admin/users.php" class="text-sm text-primary hover:underline">查看全部</a>
      </div>
      <div class="space-y-3">
        <?php foreach ($newUsers as $user): ?>
        <div class="flex items-center gap-3 py-2 border-b last:border-0">
          <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold shrink-0">
            <?= mb_substr($user['name'], 0, 1) ?>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-800"><?= e($user['name']) ?></p>
            <p class="text-xs text-gray-400"><?= e($user['email']) ?></p>
          </div>
          <p class="text-xs text-gray-400 shrink-0"><?= timeAgo($user['created_at']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
