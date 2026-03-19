<?php
// search.php 轉址到 listings/index.php 帶查詢參數
declare(strict_types=1);

$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$params = [];
if ($q)        $params['q']        = $q;
if ($category) $params['category'] = $category;

$url = '/listings/index.php' . ($params ? '?' . http_build_query($params) : '');
header('Location: ' . $url, true, 302);
exit;
