<?php $pageTitle = '404 找不到頁面'; include dirname(__DIR__) . '/layout/header.php'; ?>
<div class="text-center py-24">
  <div class="text-7xl mb-4">🔍</div>
  <h1 class="text-2xl font-bold text-gray-800 mb-2">找不到此頁面</h1>
  <p class="text-gray-400 mb-6">您要找的頁面不存在或已被移除</p>
  <a href="/index.php" class="bg-primary text-white px-6 py-3 rounded-xl hover:bg-primary-light transition">回首頁</a>
</div>
<?php include dirname(__DIR__) . '/layout/footer.php'; ?>
