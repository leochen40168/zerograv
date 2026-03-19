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

$errors   = [];
$old      = [];
$uploader = new ImageUploader();
$model    = new Listing();
$catModel = new Category();
$cats     = $catModel->flatList();

$cities = ['台北市','新北市','基隆市','桃園市','新竹市','新竹縣','苗栗縣',
           '台中市','彰化縣','南投縣','雲林縣','嘉義市','嘉義縣',
           '台南市','高雄市','屏東縣','宜蘭縣','花蓮縣','台東縣',
           '澎湖縣','金門縣','連江縣'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $_POST;

    // ── 驗證 ─────────────────────────────────────────────────
    if (empty(trim($_POST['title'] ?? '')))          $errors[] = '請填寫儀器名稱';
    if (empty($_POST['category_id']))                $errors[] = '請選擇分類';
    if (!in_array($_POST['condition'] ?? '', ['like_new','good','fair','for_parts'])) $errors[] = '請選擇成色';
    if (!is_numeric($_POST['price'] ?? '') || (int)$_POST['price'] <= 0) $errors[] = '請輸入有效售價';
    if (empty(trim($_POST['description'] ?? '')))    $errors[] = '請填寫儀器描述';

    if (empty($errors)) {
        $listingId = $model->create($_POST, (int)$_SESSION['user_id']);

        // ── 圖片上傳 ─────────────────────────────────────────
        if (!empty($_FILES['images']['name'][0])) {
            $upload = $uploader->handleMultiple($_FILES['images'], $listingId);
            foreach ($upload['errors'] as $e) {
                $errors[] = $e; // 非致命，刊登已建立
            }
            foreach ($upload['saved'] as $i => $filename) {
                $origName = $_FILES['images']['name'][$i] ?? '';
                $model->addImage($listingId, $filename, $origName, $i);
                if ($i === 0) {
                    $model->setCover($listingId, $filename);
                }
            }
        }

        setFlash('success', '刊登成功！審核通過後將自動上架，通常於 1 個工作日內完成。');
        redirect('/my-listings.php');
    }
}

$pageTitle = '刊登儀器 | ZeroGrav';
include dirname(__DIR__, 2) . '/templates/layout/header.php';
?>

<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold text-gray-800 mb-6">刊登二手儀器</h1>

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
               value="<?= e($old['title'] ?? '') ?>"
               placeholder="例：Agilent 7890B 氣相色譜儀"
               class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">分類 <span class="text-red-500">*</span></label>
          <select name="category_id" required
                  class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
            <option value="">請選擇分類</option>
            <?php
            $currentParent = null;
            foreach ($cats as $cat):
                if ($cat['parent_id'] === null):
                    if ($currentParent !== null) echo '</optgroup>';
                    $currentParent = $cat['id'];
                    echo '<optgroup label="' . e($cat['name']) . '">';
                else:
            ?>
            <option value="<?= (int)$cat['id'] ?>"
                    <?= ($old['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
              <?= e($cat['name']) ?>
            </option>
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
            <option value="">請選擇</option>
            <option value="like_new" <?= ($old['condition'] ?? '') === 'like_new' ? 'selected' : '' ?>>九成新 - 幾乎未使用</option>
            <option value="good"     <?= ($old['condition'] ?? '') === 'good'     ? 'selected' : '' ?>>良好 - 正常使用痕跡</option>
            <option value="fair"     <?= ($old['condition'] ?? '') === 'fair'     ? 'selected' : '' ?>>尚可 - 有明顯使用痕跡</option>
            <option value="for_parts"<?= ($old['condition'] ?? '') === 'for_parts'? 'selected' : '' ?>>零件用 - 故障/不完整</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">品牌</label>
          <input type="text" name="brand" maxlength="100"
                 value="<?= e($old['brand'] ?? '') ?>" placeholder="例：Agilent"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">型號</label>
          <input type="text" name="model" maxlength="100"
                 value="<?= e($old['model'] ?? '') ?>" placeholder="例：7890B"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">出廠年份</label>
          <input type="number" name="year" min="1970" max="<?= date('Y') ?>"
                 value="<?= e($old['year'] ?? '') ?>" placeholder="<?= date('Y') ?>"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">售價（NT$）<span class="text-red-500">*</span></label>
          <input type="number" name="price" required min="1"
                 value="<?= e($old['price'] ?? '') ?>" placeholder="0"
                 class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div class="flex items-end gap-3">
          <label class="flex items-center gap-2 cursor-pointer pb-2.5">
            <input type="checkbox" name="price_negotiable" value="1"
                   <?= !empty($old['price_negotiable']) ? 'checked' : '' ?>
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
          <option value="<?= e($city) ?>" <?= ($old['location'] ?? '') === $city ? 'selected' : '' ?>>
            <?= e($city) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- 描述 -->
    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
      <h2 class="font-bold text-gray-700 border-b pb-2">詳細描述</h2>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">儀器描述 <span class="text-red-500">*</span></label>
        <textarea name="description" required rows="8"
                  placeholder="請詳細描述儀器的功能、狀況、附件、使用記錄等..."
                  class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
        ><?= e($old['description'] ?? '') ?></textarea>
        <p class="text-xs text-gray-400 mt-1">建議描述：功能狀態、外觀狀況、附件清單、校驗記錄等</p>
      </div>
    </div>

    <!-- 圖片上傳 -->
    <div class="bg-white rounded-2xl shadow-sm p-6"
         x-data="{
           previews: [],
           handleFiles(event) {
             const files = Array.from(event.target.files).slice(0, 5);
             this.previews = [];
             files.forEach(file => {
               const reader = new FileReader();
               reader.onload = e => this.previews.push(e.target.result);
               reader.readAsDataURL(file);
             });
           }
         }">
      <h2 class="font-bold text-gray-700 border-b pb-2 mb-4">上傳圖片</h2>

      <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary transition cursor-pointer"
           @click="$refs.fileInput.click()">
        <div class="text-4xl mb-2">📷</div>
        <p class="text-sm text-gray-600 font-medium">點擊或拖曳上傳圖片</p>
        <p class="text-xs text-gray-400 mt-1">最多 5 張 · JPG / PNG / WebP · 每張最大 5MB</p>
        <p class="text-xs text-gray-400">第一張圖片將作為封面</p>
        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
               x-ref="fileInput" class="hidden"
               @change="handleFiles($event)">
      </div>

      <!-- 預覽 -->
      <div x-show="previews.length > 0" x-cloak class="mt-4 grid grid-cols-5 gap-2">
        <template x-for="(src, i) in previews" :key="i">
          <div class="relative aspect-square">
            <img :src="src" class="w-full h-full object-cover rounded-lg">
            <div x-show="i === 0"
                 class="absolute top-1 left-1 bg-primary text-white text-xs px-1 rounded">封面</div>
          </div>
        </template>
      </div>
    </div>

    <!-- 提交 -->
    <div class="flex gap-3">
      <button type="submit"
              class="flex-1 bg-primary text-white font-bold py-3.5 rounded-xl hover:bg-primary-light transition text-sm">
        送出刊登申請
      </button>
      <a href="/my-listings.php"
         class="px-6 py-3.5 border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-50 transition text-sm">
        取消
      </a>
    </div>
    <p class="text-xs text-gray-400 text-center">刊登送出後將由管理員審核，審核通過後自動上架</p>
  </form>
</div>

<?php include dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
