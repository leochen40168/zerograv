<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Helpers/functions.php';
require_once dirname(__DIR__) . '/src/Auth/Auth.php';

(new Auth())->logout();
setFlash('success', '已成功登出');
redirect('/index.php');
