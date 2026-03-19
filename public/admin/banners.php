<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth();
requireAdmin();

$db      = getPDO();
$errors  = [];

// ── 新增 / 編輯 / 刪除 ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE banners SET is_active = NOT is_active WHERE id = ?")
           ->execute([$id]);
        setFlash('success', '廣告狀態已更新');
        redirect('/admin/banners.php');
    }

    if ($action === 'delete') {
        $id  = (int)$_POST['id'];
        $row = $db->prepare("SELECT image_path FROM banners WHERE id = ?");
        $row->execute([$id]);
        $banner = $row->fetch();
        if ($banner) {
            $path = dirname(__DIR__, 2) . '/public' . $banner['image_path'];
            if (file_exists($path)) unlink($path);
            $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
            setFlash('success', '廣告已刪除');
        }
        redirect('/admin/banners.php');
    }

    if (in_array($action, ['create', 'edit'])) {
        $title    = trim($_POST['title'] ?? '');
        $linkUrl  = trim($_POST['link_url'] ?? '');
        $position = $_POST['position'] ?? 'top';
        $startsAt = $_POST['starts_at'] ?: null;
        $endsAt   = $_POST['ends_at']   ?: null;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if (empty($title)) $errors[] = '請填寫廣告標題';
        if (!in_array($position, ['top', 'sidebar'])) $errors[] = '請選擇版位';

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
                $errors[] = '廣告圖片格式不支援';
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $errors[] = '廣告圖片不得超過 2MB';
            } else {
                $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dir      = dirname(__DIR__, 2) . '/public/uploads/banners';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $filename);
                $imagePath = '/uploads/banners/' . $filename;
            }
        }

        if (empty($errors)) {
            if ($action === 'create') {
                if (!$imagePath) { $errors[] = '請上傳廣告圖片'; goto done; }
                $db->prepare(
                    "INSERT INTO banners (title, image_path, link_url, position, sort_order, starts_at, ends_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([$title, $imagePath, $linkUrl, $position, $sortOrder, $startsAt, $endsAt]);
                setFlash('success', '廣告已新增');
            } else {
                $editId = (int)$_POST['edit_id'];
                $sets   = "title=?, link_url=?, position=?, sort_order=?, starts_at=?, ends_at=?";
                $vals   = [$title, $linkUrl, $position, $sortOrder, $startsAt, $endsAt];
                if ($imagePath) {
                    $sets .= ', image_path=?';
                    $vals[] = $imagePath;
                    // 刪除舊圖
                    $old = $db->prepare("SELECT image_path FROM banners WHERE id=?");
                    $old->execute([$editId]);
                    $oldRow = $old->fetch();
                    if ($oldRow) {
                        $oldPath = dirname(__DIR__, 2) . '/public' . $oldRow['image_path'];
                        if (file_exists($oldPath)) unlink($oldPath);
                    }
                }
                $vals[] = $editId;
                $db->prepare("UPDATE banners SET {$sets} WHERE id=?")->execute($vals);
                setFlash('success', '廣告已更新');
            }
            done:
            if (empty($errors)) redirect('/admin/banners.php');
        }
    }
}

$banners = $db->query(
    "SELECT * FROM banners ORDER BY position, sort_order, id DESC"
)->fetchAll();

$pageTitle = '廣告管理 | ZeroGrav 後台';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="max-w-5xl mx-auto">

  <!-- 後台導覽 -->
  <div class="bg-gray-800 text-white rounded-2xl p-4 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <span class="font-bold text-lg">🔧 後台管理</span>
      <nav class="flex gap-3 text-sm">
        <a href="/admin/index.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">儀表板</a>
        <a href="/admin/listings.php" class="hover:bg-white/10 px-3 py-1 rounded-lg transition">刊登管理</a>
        <span class="bg-white/20 px-3 py-1 rounded-lg">廣告管理</span>
      </nav>
    </div>
    <a href="/index.php" class="text-sm text-gray-300 hover:text-white transition">← 回前台</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-5 py-4 text-sm mb-5 space-y-1">
    <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 新增廣告表單 -->
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-6" x-data="{ open: false }">
    <button @click="open = !open"
            class="flex items-center gap-2 font-bold text-gray-700 w-full text-left">
      <span>+ 新增廣告</span>
      <svg class="w-5 h-5 ml-auto transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div x-show="open" x-cloak class="mt-4 border-t pt-4">
      <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">廣告標題 *</label>
          <input type="text" name="title" required
                 class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">連結網址</label>
          <input type="url" name="link_url" placeholder="https://"
                 class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">版位 *</label>
          <select name="position" required
                  class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="top">頂部 Banner（1200×120）</option>
            <option value="sidebar">側欄（300×250）</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">排序</label>
          <input type="number" name="sort_order" value="0" min="0"
                 class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">開始日期</label>
          <input type="date" name="starts_at"
                 class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">結束日期</label>
          <input type="date" name="ends_at"
                 class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">廣告圖片 * (最大 2MB)</label>
          <input type="file" name="image" required accept="image/*"
                 class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
        </div>
        <div class="sm:col-span-2">
          <button type="submit"
                  class="bg-primary text-white font-semibold px-6 py-2.5 rounded-xl hover:bg-primary-light transition text-sm">
            新增廣告
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 廣告列表 -->
  <div class="space-y-4">
    <?php if (empty($banners)): ?>
    <div class="bg-white rounded-2xl p-12 text-center text-gray-400">尚無廣告</div>
    <?php endif; ?>

    <?php foreach ($banners as $banner): ?>
    <div class="bg-white rounded-2xl shadow-sm p-4 flex gap-4 items-center">
      <!-- 預覽圖 -->
      <div class="shrink-0">
        <img src="<?= e($banner['image_path']) ?>" alt="<?= e($banner['title']) ?>"
             class="h-16 w-auto max-w-[160px] object-contain rounded-lg border bg-gray-50">
      </div>

      <!-- 資訊 -->
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-gray-800"><?= e($banner['title']) ?></p>
        <div class="flex flex-wrap gap-2 mt-1 text-xs text-gray-500">
          <span class="bg-gray-100 px-2 py-0.5 rounded">
            <?= $banner['position'] === 'top' ? '頂部 Banner' : '側欄' ?>
          </span>
          <?php if ($banner['ends_at']): ?>
          <span>到期：<?= e($banner['ends_at']) ?></span>
          <?php endif; ?>
          <span>點擊：<?= number_format($banner['click_count']) ?> 次</span>
        </div>
      </div>

      <!-- 狀態 + 操作 -->
      <div class="shrink-0 flex items-center gap-3">
        <!-- 啟用/停用 toggle -->
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
          <button type="submit"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition
                         <?= $banner['is_active'] ? 'bg-primary' : 'bg-gray-300' ?>">
            <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform
                         <?= $banner['is_active'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
          </button>
        </form>

        <!-- 刪除 -->
        <form method="POST" onsubmit="return confirm('確定刪除此廣告？')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
          <button type="submit" class="text-red-400 hover:text-red-600 transition text-sm">刪除</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
