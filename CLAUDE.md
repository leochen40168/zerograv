# ZeroGrav 二手儀器交易平台

## 專案概述

- **網站名稱**：ZeroGrav 二手儀器交易平台
- **網址**：zerograv.com.tw
- **定位**：台灣版「儀器信息網」，專注二手科學儀器買賣
- **部署環境**：PHP 虛擬主機（無 Composer、無 CLI）

## 技術棧

- **後端**：PHP 8.x（原生，無框架）
- **資料庫**：MySQL 8.x
- **前端**：HTML5 + Tailwind CSS（CDN）+ Alpine.js（CDN）
- **圖片上傳**：PHP GD / ImageMagick
- **Session**：PHP 原生 session

## 目錄結構

```
zerograv/
├── CLAUDE.md                  # 本文件
├── .env                       # 環境設定（不入 git）
├── .env.example               # 環境設定範本
├── .gitignore
├── sql/
│   └── schema.sql             # 資料庫建表語句
├── config/
│   └── database.php           # DB 連線設定
├── public/                    # 網站根目錄（webroot）
│   ├── index.php              # 首頁
│   ├── .htaccess              # URL rewrite 規則
│   ├── assets/
│   │   ├── css/               # 自訂 CSS
│   │   ├── js/                # 自訂 JS
│   │   └── images/            # 靜態圖片（logo 等）
│   └── uploads/               # 使用者上傳圖片（軟連結或直接放這）
├── src/
│   ├── Auth/
│   │   └── Auth.php           # 登入/註冊/Session 邏輯
│   ├── Models/
│   │   ├── User.php
│   │   ├── Listing.php
│   │   ├── Category.php
│   │   └── Banner.php
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── ListingController.php
│   │   ├── SearchController.php
│   │   └── AdminController.php
│   └── Helpers/
│       ├── functions.php      # 通用函式
│       └── upload.php         # 圖片上傳處理
├── templates/
│   ├── layout/
│   │   ├── header.php
│   │   └── footer.php
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   ├── listings/
│   │   ├── index.php          # 列表頁
│   │   ├── show.php           # 詳情頁
│   │   ├── create.php         # 刊登表單
│   │   └── edit.php           # 編輯刊登
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── listings.php       # 審核管理
│   │   └── banners.php        # 廣告管理
│   └── errors/
│       ├── 403.php
│       └── 404.php
└── admin/
    └── index.php              # 後台入口
```

## 資料庫

- **資料庫名稱**：`cycleflo_zerograv`
- **用戶名**：`cycleflo_zerograv`
- **字元集**：utf8mb4
- **Collation**：utf8mb4_unicode_ci

## 主要功能模組

### 前台
1. **首頁** - Banner 廣告、最新刊登、分類快捷
2. **列表頁** - 分頁（每頁 20 筆）、縮圖、價格、分類篩選、關鍵字搜尋
3. **詳情頁** - 圖片輪播（Alpine.js）、規格、賣家資訊、聯絡方式
4. **刊登** - 多圖上傳（最多 5 張）、分類、規格填寫
5. **我的刊登** - 列表、編輯、下架

### 後台（/admin）
1. **儀器審核** - 待審核列表、核准/拒絕
2. **廣告管理** - Banner 上下架、側欄廣告

### 聯絡賣家
- 一鍵撥號（tel: 連結）
- 複製電話按鈕
- 開啟 Line（line://）

## 安全規範

- 所有 SQL 查詢使用 PDO Prepared Statements
- 使用者輸入一律 `htmlspecialchars()` 輸出
- 密碼使用 `password_hash()` bcrypt 加密
- 圖片上傳驗證：MIME type、副檔名白名單、大小限制 5MB
- Session 採用 `session_regenerate_id()` 防 fixation
- CSRF token 保護所有表單
- 管理員路由需二次驗證 role = 'admin'

## 圖片上傳規則

- 最多 5 張／每筆刊登
- 格式：JPG、PNG、WebP
- 大小：最大 5MB／張
- 儲存路徑：`public/uploads/listings/{listing_id}/`
- 縮圖：自動產生 400x300 縮圖

## 刊登狀態流程

```
draft（草稿）→ pending（待審核）→ active（上架中）
                              → rejected（已拒絕）
active → inactive（下架）
```

## 廣告版位

- **頂部 Banner**：1200x120px，首頁 + 列表頁
- **側欄廣告**：300x250px，詳情頁側欄
- 廣告欄位可設定連結網址、有效期限

## 開發注意事項

- 虛擬主機無法執行 CLI，schema 需手動在 phpMyAdmin 執行
- 上傳目錄需設定 755 權限
- `.env` 絕對不能進 git
- 使用 PHP_EOL 和 date_default_timezone_set('Asia/Taipei')
