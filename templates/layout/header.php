<!DOCTYPE html>
<html lang="zh-Hant-TW" x-data>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'ZeroGrav 二手儀器交易平台') ?></title>
  <meta name="description" content="<?= e($pageDesc ?? '台灣最專業的二手儀器交易平台，精密分析、測量、光學儀器買賣') ?>">

  <!-- Tailwind CSS (CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { DEFAULT: '#0f4c81', light: '#1a6ab3', dark: '#0a3560' },
          }
        }
      }
    }
  </script>

  <!-- Alpine.js (CDN) -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    [x-cloak] { display: none !important; }
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<!-- 頂部靜態 Banner 已移至首頁輪播（index.php） -->

<!-- ── 導覽列 ────────────────────────────────────────────── -->
<nav class="bg-primary text-white shadow-md" x-data="{ open: false }">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between h-16">

      <!-- Logo -->
      <a href="/index.php" class="flex items-center gap-2">
        <span class="text-2xl font-bold tracking-tight">ZeroGrav</span>
        <span class="hidden sm:block text-xs text-blue-200">二手儀器交易平台</span>
      </a>

      <!-- 搜尋欄（桌面） -->
      <form action="/search.php" method="GET" class="hidden md:flex flex-1 max-w-xl mx-6">
        <input type="text" name="q" placeholder="搜尋儀器品牌、型號..."
               value="<?= e($_GET['q'] ?? '') ?>"
               class="flex-1 px-4 py-2 text-gray-800 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-300">
        <button type="submit"
                class="bg-primary-dark hover:bg-primary-light px-4 py-2 rounded-r-lg border-l border-blue-400 transition">
          🔍
        </button>
      </form>

      <!-- 右側選單（桌面） -->
      <div class="hidden md:flex items-center gap-4 text-sm">
        <a href="/listings/index.php" class="hover:text-blue-200 transition">儀器列表</a>
        <?php if (isLoggedIn()): ?>
          <a href="/listings/create.php"
             class="bg-yellow-400 text-gray-900 font-semibold px-4 py-1.5 rounded-lg hover:bg-yellow-300 transition">
            + 刊登
          </a>
          <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="hover:text-blue-200 transition flex items-center gap-1">
              <?= e($_SESSION['user_name'] ?? '我的帳號') ?> ▾
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                 class="absolute right-0 mt-2 w-44 bg-white text-gray-800 rounded-lg shadow-lg py-1 z-50">
              <a href="/my-listings.php" class="block px-4 py-2 hover:bg-gray-100">我的刊登</a>
              <a href="/profile.php" class="block px-4 py-2 hover:bg-gray-100">帳號設定</a>
              <?php if (isAdmin()): ?>
              <hr class="my-1">
              <a href="/admin/index.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">後台管理</a>
              <?php endif; ?>
              <hr class="my-1">
              <a href="/logout.php" class="block px-4 py-2 text-gray-500 hover:bg-gray-100">登出</a>
            </div>
          </div>
        <?php else: ?>
          <a href="/login.php" class="hover:text-blue-200 transition">登入</a>
          <a href="/register.php"
             class="bg-white text-primary font-semibold px-4 py-1.5 rounded-lg hover:bg-blue-50 transition">
            註冊
          </a>
        <?php endif; ?>
      </div>

      <!-- 漢堡選單（手機） -->
      <button @click="open = !open" class="md:hidden p-2 rounded hover:bg-primary-light transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
    </div>

    <!-- 手機選單 -->
    <div x-show="open" x-cloak class="md:hidden pb-3 border-t border-blue-700 mt-1 pt-3">
      <form action="/search.php" method="GET" class="flex mb-3">
        <input type="text" name="q" placeholder="搜尋儀器..."
               class="flex-1 px-3 py-2 text-gray-800 rounded-l-lg text-sm">
        <button class="bg-primary-dark px-3 py-2 rounded-r-lg">🔍</button>
      </form>
      <a href="/listings/index.php" class="block py-2 hover:text-blue-200">儀器列表</a>
      <?php if (isLoggedIn()): ?>
        <a href="/listings/create.php" class="block py-2 text-yellow-300">+ 刊登儀器</a>
        <a href="/my-listings.php" class="block py-2 hover:text-blue-200">我的刊登</a>
        <a href="/logout.php" class="block py-2 text-gray-300">登出</a>
      <?php else: ?>
        <a href="/login.php" class="block py-2 hover:text-blue-200">登入</a>
        <a href="/register.php" class="block py-2 hover:text-blue-200">註冊</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ── Flash 訊息 ─────────────────────────────────────────── -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<?php
  $alertClass = match ($flash['type']) {
    'success' => 'bg-green-100 border-green-400 text-green-800',
    'error'   => 'bg-red-100 border-red-400 text-red-800',
    'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
    default   => 'bg-blue-100 border-blue-400 text-blue-800',
  };
?>
<div x-data="{ show: true }" x-show="show"
     class="<?= $alertClass ?> border px-4 py-3 text-sm flex justify-between items-center">
  <?= e($flash['message']) ?>
  <button @click="show = false" class="text-current opacity-50 hover:opacity-100 text-lg leading-none">&times;</button>
</div>
<?php endif; ?>

<!-- ── 主內容 ─────────────────────────────────────────────── -->
<main class="max-w-7xl mx-auto px-4 py-6">
