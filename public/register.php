<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Helpers/functions.php';
require_once dirname(__DIR__) . '/src/Auth/Auth.php';

$auth = new Auth();

if (isLoggedIn()) redirect('/index.php');

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old    = $_POST;
    $result = $auth->register($_POST);
    if ($result['success']) {
        setFlash('success', '註冊成功！請登入。');
        redirect('/login.php');
    }
    $errors = $result['errors'];
}

$pageTitle = '註冊 | ZeroGrav';
include dirname(__DIR__) . '/templates/layout/header.php';
?>

<div class="min-h-[60vh] flex items-center justify-center -my-6 py-8">
  <div class="w-full max-w-lg">
    <div class="bg-white rounded-2xl shadow-lg p-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-1">建立帳號</h1>
      <p class="text-sm text-gray-400 mb-6">已有帳號？<a href="/login.php" class="text-primary hover:underline">直接登入</a></p>

      <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm mb-5 space-y-1">
        <?php foreach ($errors as $err): ?>
        <div>• <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">姓名 <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100"
                   value="<?= e($old['name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">公司/機構（選填）</label>
            <input type="text" name="company" maxlength="200"
                   value="<?= e($old['company'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
          <input type="email" name="email" required autocomplete="email"
                 value="<?= e($old['email'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">聯絡電話（選填）</label>
            <input type="tel" name="phone" maxlength="20"
                   value="<?= e($old['phone'] ?? '') ?>"
                   placeholder="09XX-XXX-XXX"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Line ID（選填）</label>
            <input type="text" name="line_id" maxlength="100"
                   value="<?= e($old['line_id'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          </div>
        </div>

        <div x-data="{ show: false }">
          <label class="block text-sm font-medium text-gray-700 mb-1">密碼 <span class="text-red-500">*</span></label>
          <div class="relative">
            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary pr-10">
            <button type="button" @click="show = !show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">
              <span x-text="show ? '隱藏' : '顯示'"></span>
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-1">至少 8 個字元，需包含英文與數字</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">確認密碼 <span class="text-red-500">*</span></label>
          <input type="password" name="password_confirm" required autocomplete="new-password"
                 class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>

        <button type="submit"
                class="w-full bg-primary text-white font-semibold py-2.5 rounded-lg hover:bg-primary-light transition text-sm">
          建立帳號
        </button>

        <p class="text-xs text-gray-400 text-center">
          點擊「建立帳號」即表示您同意我們的服務條款與隱私政策
        </p>
      </form>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/templates/layout/footer.php'; ?>
