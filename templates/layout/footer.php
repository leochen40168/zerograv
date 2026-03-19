</main>

<!-- ── 頁尾 ──────────────────────────────────────────────── -->
<footer class="bg-gray-800 text-gray-300 mt-12">
  <div class="max-w-7xl mx-auto px-4 py-10">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div>
        <h3 class="text-white font-bold text-lg mb-3">ZeroGrav</h3>
        <p class="text-sm text-gray-400 leading-relaxed">
          台灣專業二手儀器交易平台<br>
          精密分析、測試測量、光學儀器<br>
          安全、快速、可靠
        </p>
      </div>
      <div>
        <h3 class="text-white font-semibold mb-3">快速連結</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="/listings/index.php" class="hover:text-white transition">儀器列表</a></li>
          <li><a href="/listings/create.php" class="hover:text-white transition">刊登儀器</a></li>
          <li><a href="/about.php" class="hover:text-white transition">關於我們</a></li>
          <li><a href="/contact.php" class="hover:text-white transition">聯絡我們</a></li>
        </ul>
      </div>
      <div>
        <h3 class="text-white font-semibold mb-3">熱門分類</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="/search.php?category=analytical" class="hover:text-white transition">分析儀器</a></li>
          <li><a href="/search.php?category=test-measurement" class="hover:text-white transition">測試測量</a></li>
          <li><a href="/search.php?category=optical" class="hover:text-white transition">光學儀器</a></li>
          <li><a href="/search.php?category=lab-equipment" class="hover:text-white transition">實驗室設備</a></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-gray-700 mt-8 pt-6 text-center text-xs text-gray-500">
      &copy; <?= date('Y') ?> ZeroGrav 二手儀器交易平台 &nbsp;|&nbsp; zerograv.com.tw
    </div>
  </div>
</footer>

<script>
// 廣告點擊追蹤
function trackBannerClick(id) {
  fetch('/api/banner-click.php?id=' + id, { method: 'POST' }).catch(() => {});
}
</script>
</body>
</html>
