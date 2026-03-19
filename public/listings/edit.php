<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/src/Models/Listing.php';
require_once dirname(__DIR__, 2) . '/src/Models/Category.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/upload.php';
require_once dirname(__DIR__, 2) . '/src/Auth/Auth.php';

new Auth();
requireLogin();

$id           = (int)($_GET['id'] ?? 0);
$model        = new Listing();
$listing      = $model->find($id);

// 只有本人或管理員可以編輯
if (!$listing || (($_SESSION['user_id'] ?? 0) != $listing['user_id'] && !isAdmin())) {
    http_response_code(403);
    setFlash('error', '沒有權限編輯此刊登');
    redirect('/my-listings.php');
}

$catModel = new Category();
$cats     = $catModel->flatList();
$uploader = new ImageUploader();
$errors   = [];

$cities = ['台北市','新北市','基隆市','桃園市','新竹市','新竹縣','苗栗縣',
           '台中市','彰化縣','南投縣','雲林縣','嘉義市','嘉義縣',
           '台南市','高雄市','屏東縣','宜蘭縣','花蓮縣','台東縣',
           '澎湖縣','金門縣','連江縣'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // 刪除指定圖片
    if (!empty($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $imgId) {
            $filename = $model->deleteImage((int)$imgId, $id);
            if ($filename) {
                $filePath = dirname(__DIR__, 2) . '/public/uploads/listings/' . $id . '/' . $filename;
                if (file_exists($filePath)) unlink($filePath);
            }
        }
    }

    // 驗證
    if (empty(trim($_POST['title'] ?? '')))        $errors[] = '請填寫儀器名稱';
    if (empty($_POST['category_id']))              $errors[] = '請選擇分類';
    if (!in_array($_POST['condition'] ?? '', ['like_new','good','fair','for_parts'])) $errors[] = '請選擇成色';
    if (!is_numeric($_POST['price'] ?? '') || (int)$_POST['price'] <= 0) $errors[] = '請輸入有效售價';
    if (empty(trim($_POST['description'] ?? '')))  $errors[] = '請填寫儀器描述';

    if (empty($errors)) {
        $model->update($id, (int)$_SESSION['user_id'], $_POST);

        // 新增圖片
        if (!empty($_FILES['images']['name'][0])) {
            $existingCount = count($model->getImages($id));
            if ($existingCount < 5) {
                $upload = $uploader->handleMultiple($_FILES['images'], $id);
                foreach ($upload['errors'] as $e) $errors[] = $e;
                foreach ($upload['saved'] as $i => $filename) {
                    $model->addImage($id, $filename, $_FILES['images']['name'][$i] ?? '', $existingCount + $i);
                    if ($existingCount === 0 && $i === 0) {
                        $model->setCover($id, $filename);
                    }
                }
            }
        }

        // 更新封面
        $remaining = $model->getImages($id);
        if (!empty($remaining)) {
            $model->setCover($id, $remaining[0]['filename']);
        }

        setFlash('success', '刊登已更新，將重新送審。');
        redirect('/listings/show.php?id=' . $id);
    }

    // 重新讀取（可能有圖片被刪除）
    $listing = $model->find($id);
}

$pageTitle = '編輯刊登 | ZeroGrav';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold text-gray-800 mb-6">編輯刊登</h1>

  <?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-5 py-4 text-sm mb-6 space-y-1">
    <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-6">
    <?= csrfField() ?>

    <!-- 基本資訊 -->
    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
      <h2 class="font-bold text-gray-700 border-b pb-2">基本資訊</h2>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">儀器名稱 <span class="text-red-500">*</span></label>
        <input type="text" name="title" required maxlength="200"
               value="<?= e($_POST['title'] ?? $listing['title']) ?>"
               class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">分類 <span class="text-red-500">*</span></label>
          <select name="category_id" required
                  class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
            <?php
            $currentParent = null;
            foreach ($cats as $cat):
                if ($cat['parent_id'] === null):
                    if ($currentParent !== null) echo '</optgroup>';
                    $currentParent = $cat['id'];
                    echo '<optgroup label="' . e($cat['name']) . '">';
                else:
                    $sel = ($listing['category_id'] == $cat['id']) ? 'selected' : '';
            ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $sel ?>><?= e($cat['name']) ?></option>
            <?php
                endif;
            endforeach;
            if ($currentParent !== null) echo '</optgroup>';
            ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">成色 <span class="text-red-500">*</span></label>
          <select name="condition" required
                  class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
            <?php foreach (['like_new'=>'九成新','good'=>'良好','fair'=>'尚可','for_parts'=>'零件用'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $listing['condition'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">品牌</label>
          <input type="text" name="brand" maxlength="100"
                 value="<?= e($_POST['brand'] ?? $listing['brand']) ?>"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">型號</label>
          <input type="text" name="model" maxlength="100"
                 value="<?= e($_POST['model'] ?? $listing['model']) ?>"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">出廠年份</label>
          <input type="number" name="year" min="1970" max="<?= date('Y') ?>"
                 value="<?= e($_POST['year'] ?? $listing['year']) ?>"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">售價（NT$）<span class="text-red-500">*</span></label>
          <input type="number" name="price" required min="1"
                 value="<?= e($_POST['price'] ?? $listing['price']) ?>"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex items-end pb-2.5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="price_negotiable" value="1"
                   <?= $listing['price_negotiable'] ? 'checked' : '' ?>
                   class="w-4 h-4 text-primary rounded">
            <span class="text-sm text-gray-700">接受議價</span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">所在地</label>
        <select name="location"
                class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
          <option value="">請選擇縣市</option>
          <?php foreach ($cities as $city): ?>
          <option value="<?= e($city) ?>" <?= $listing['location'] === $city ? 'selected' : '' ?>><?= e($city) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- 描述 -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h2 class="font-bold text-gray-700 border-b pb-2 mb-4">詳細描述</h2>
      <textarea name="description" required rows="8"
                class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
      ><?= e($_POST['description'] ?? $listing['description']) ?></textarea>
    </div>

    <!-- 現有圖片 -->
    <?php $currentImages = $listing['images']; ?>
    <?php if (!empty($currentImages)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h2 class="font-bold text-gray-700 border-b pb-2 mb-4">現有圖片</h2>
      <p class="text-xs text-gray-400 mb-3">勾選要刪除的圖片</p>
      <div class="grid grid-cols-5 gap-3">
        <?php foreach ($currentImages as $i => $img): ?>
        <label class="relative cursor-pointer group" x-data="{ checked: false }">
          <input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>"
                 @change="checked = $event.target.checked" class="sr-only">
          <img src="<?= e(uploadUrl($img['filename'], $id)) ?>"
               class="w-full aspect-square object-cover rounded-lg border-2 transition"
               :class="checked ? 'border-red-500 opacity-50' : 'border-transparent'">
          <div x-show="checked" x-cloak
               class="absolute inset-0 flex items-center justify-center bg-red-500/20 rounded-lg">
            <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded">刪除</span>
          </div>
          <?php if ($i === 0): ?>
          <span class="absolute top-1 left-1 bg-primary text-white text-xs px-1 rounded">封面</span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 新增圖片 -->
    <?php $remaining = 5 - count($currentImages); ?>
    <?php if ($remaining > 0): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h2 class="font-bold text-gray-700 border-b pb-2 mb-4">新增圖片（最多還可上傳 <?= $remaining ?> 張）</h2>
      <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-primary transition">
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
        <p class="text-xs text-gray-400 mt-2">JPG / PNG / WebP · 每張最大 5MB</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- 按鈕 -->
    <div class="flex gap-3">
      <button type="submit"
              class="flex-1 bg-primary text-white font-bold py-3.5 rounded-xl hover:bg-primary-light transition text-sm">
        儲存修改
      </button>
      <a href="/listings/show.php?id=<?= $id ?>"
         class="px-6 py-3.5 border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-50 transition text-sm">
        取消
      </a>
    </div>
  </form>
</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
