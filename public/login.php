<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Taipei');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Helpers/functions.php';
require_once dirname(__DIR__) . '/src/Auth/Auth.php';

$auth = new Auth();

// 已登入者導回首頁
if (isLoggedIn()) redirect('/index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $result = $auth->login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($result['success']) {
        setFlash('success', '歡迎回來！');
        redirect($_GET['redirect'] ?? '/index.php');
    }
    $errors[] = $result['message'];
}

$pageTitle = '登入 | ZeroGrav';
include dirname(__DIR__) . '/templates/layout/header.php';
?>

<div class="min-h-[60vh] flex items-center justify-center -my-6">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-lg p-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-1">登入</h1>
      <p class="text-sm text-gray-400 mb-6">還沒有帳號？<a href="/register.php" class="text-primary hover:underline">立即註冊</a></p>

      <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm mb-5">
        <?= e($errors[0]) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" required autocomplete="email"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
        </div>

        <div x-data="{ show: false }">
          <label class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
          <div class="relative">
            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent pr-10">
            <button type="button" @click="show = !show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">
              <span x-text="show ? '隱藏' : '顯示'"></span>
            </button>
          </div>
        </div>

        <button type="submit"
                class="w-full bg-primary text-white font-semibold py-2.5 rounded-lg hover:bg-primary-light transition text-sm">
          登入
        </button>
      </form>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/templates/layout/footer.php'; ?>
