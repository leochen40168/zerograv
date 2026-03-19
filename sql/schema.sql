-- ============================================================
-- ZeroGrav 二手儀器交易平台 - 資料庫 Schema
-- 資料庫：cycleflo_zerograv
-- 字元集：utf8mb4 / utf8mb4_unicode_ci
-- 建立日期：2026-03-19
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- 1. 會員資料表
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100)    NOT NULL COMMENT '顯示名稱',
  `email`         VARCHAR(255)    NOT NULL UNIQUE,
  `password`      VARCHAR(255)    NOT NULL COMMENT 'bcrypt hash',
  `phone`         VARCHAR(20)             COMMENT '聯絡電話',
  `line_id`       VARCHAR(100)            COMMENT 'Line ID',
  `company`       VARCHAR(200)            COMMENT '公司/機構名稱',
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME            DEFAULT NULL,
  `last_login_at` DATETIME                DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='會員資料';

-- ------------------------------------------------------------
-- 2. 儀器分類表（支援兩層：父分類 + 子分類）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `parent_id`     INT UNSIGNED            DEFAULT NULL COMMENT 'NULL 表示頂層分類',
  `name`          VARCHAR(100)    NOT NULL COMMENT '分類名稱',
  `slug`          VARCHAR(120)    NOT NULL UNIQUE COMMENT 'URL 友善名稱',
  `icon`          VARCHAR(100)            COMMENT 'Heroicons 圖示名稱或 emoji',
  `sort_order`    SMALLINT        NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_parent`     (`parent_id`),
  INDEX `idx_sort`       (`sort_order`),
  CONSTRAINT `fk_category_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='儀器分類';

-- ------------------------------------------------------------
-- 3. 儀器刊登主表
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `listings` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED    NOT NULL COMMENT '賣家',
  `category_id`   INT UNSIGNED    NOT NULL,
  `title`         VARCHAR(200)    NOT NULL COMMENT '儀器名稱/標題',
  `brand`         VARCHAR(100)            COMMENT '品牌',
  `model`         VARCHAR(100)            COMMENT '型號',
  `year`          YEAR                    COMMENT '出廠年份',
  `condition`     ENUM('like_new','good','fair','for_parts') NOT NULL DEFAULT 'good'
                  COMMENT '成色：like_new=九成新, good=良好, fair=尚可, for_parts=零件用',
  `price`         DECIMAL(12,0)   NOT NULL COMMENT '售價（新台幣）',
  `price_negotiable` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '可議價',
  `description`   TEXT            NOT NULL COMMENT '詳細描述',
  `specs`         JSON                    COMMENT '規格（自由格式 JSON）',
  `location`      VARCHAR(100)            COMMENT '所在地區（縣市）',
  `cover_image`   VARCHAR(255)            COMMENT '封面圖路徑',
  `status`        ENUM('draft','pending','active','inactive','rejected')
                  NOT NULL DEFAULT 'pending',
  `reject_reason` VARCHAR(500)            COMMENT '拒絕原因（僅 rejected 時使用）',
  `reviewed_by`   INT UNSIGNED            COMMENT '審核管理員 id',
  `reviewed_at`   DATETIME                COMMENT '審核時間',
  `views`         INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT '瀏覽次數',
  `is_featured`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '精選刊登（管理員設定）',
  `expires_at`    DATE                    COMMENT '刊登到期日（NULL = 不限期）',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user`       (`user_id`),
  INDEX `idx_category`   (`category_id`),
  INDEX `idx_status`     (`status`),
  INDEX `idx_featured`   (`is_featured`),
  INDEX `idx_created`    (`created_at` DESC),
  FULLTEXT INDEX `ft_search` (`title`, `brand`, `model`, `description`),
  CONSTRAINT `fk_listing_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listing_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='儀器刊登';

-- ------------------------------------------------------------
-- 4. 刊登圖片表
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `listing_images` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `listing_id`    INT UNSIGNED    NOT NULL,
  `filename`      VARCHAR(255)    NOT NULL COMMENT '儲存檔名（含副檔名）',
  `original_name` VARCHAR(255)            COMMENT '原始檔名',
  `sort_order`    TINYINT         NOT NULL DEFAULT 0 COMMENT '0 = 封面',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_listing` (`listing_id`),
  CONSTRAINT `fk_image_listing`
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='刊登圖片';

-- ------------------------------------------------------------
-- 5. 聯絡紀錄（點擊聯絡按鈕的追蹤）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `listing_id`    INT UNSIGNED    NOT NULL,
  `contact_type`  ENUM('phone','line','copy') NOT NULL COMMENT '聯絡方式',
  `visitor_ip`    VARCHAR(45)             COMMENT 'IPv4/IPv6',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_listing`    (`listing_id`),
  INDEX `idx_created`    (`created_at`),
  CONSTRAINT `fk_contact_listing`
    FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='聯絡點擊紀錄';

-- ------------------------------------------------------------
-- 6. 廣告版位
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banners` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(200)    NOT NULL COMMENT '廣告標題（後台顯示用）',
  `image_path`    VARCHAR(255)    NOT NULL COMMENT '廣告圖片路徑',
  `link_url`      VARCHAR(500)            COMMENT '點擊連結',
  `position`      ENUM('top','sidebar') NOT NULL DEFAULT 'top'
                  COMMENT 'top=頂部1200x120, sidebar=側欄300x250',
  `sort_order`    SMALLINT        NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `starts_at`     DATE                    COMMENT '開始日期（NULL=即時）',
  `ends_at`       DATE                    COMMENT '結束日期（NULL=不限期）',
  `click_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_position`   (`position`, `is_active`),
  INDEX `idx_dates`      (`starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='廣告版位';

-- ------------------------------------------------------------
-- 7. 管理員操作日誌
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED    NOT NULL,
  `action`        VARCHAR(100)    NOT NULL COMMENT '操作類型（approve_listing, reject_listing...）',
  `target_type`   VARCHAR(50)             COMMENT '對象類型（listing, user, banner）',
  `target_id`     INT UNSIGNED            COMMENT '對象 ID',
  `note`          VARCHAR(500)            COMMENT '備註',
  `ip`            VARCHAR(45),
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin`      (`admin_id`),
  INDEX `idx_action`     (`action`),
  INDEX `idx_created`    (`created_at` DESC),
  CONSTRAINT `fk_log_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理員操作日誌';

-- ============================================================
-- 初始資料
-- ============================================================

-- 頂層分類
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `icon`, `sort_order`) VALUES
(1,  NULL, '分析儀器',       'analytical',       '🔬', 1),
(2,  NULL, '測試測量',       'test-measurement', '📡', 2),
(3,  NULL, '光學儀器',       'optical',          '🔭', 3),
(4,  NULL, '實驗室設備',     'lab-equipment',    '🧪', 4),
(5,  NULL, '電子量測',       'electronics',      '⚡', 5),
(6,  NULL, '環境監測',       'environmental',    '🌡️', 6),
(7,  NULL, '醫療儀器',       'medical',          '🏥', 7),
(8,  NULL, '工業設備',       'industrial',       '🏭', 8),
(9,  NULL, '其他',           'others',           '📦', 99);

-- 子分類（分析儀器）
INSERT INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
(1, '色譜儀',         'chromatography',   1),
(1, '質譜儀',         'mass-spectrometry',2),
(1, '光譜儀',         'spectrometer',     3),
(1, '電化學分析',     'electrochemical',  4);

-- 子分類（測試測量）
INSERT INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
(2, '示波器',         'oscilloscope',     1),
(2, '訊號產生器',     'signal-generator', 2),
(2, '頻譜分析儀',     'spectrum-analyzer',3),
(2, '網路分析儀',     'network-analyzer', 4),
(2, '萬用表',         'multimeter',       5);

-- 子分類（光學儀器）
INSERT INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
(3, '顯微鏡',         'microscope',       1),
(3, '雷射',           'laser',            2),
(3, '相機/感測器',    'camera-sensor',    3);

-- 子分類（實驗室設備）
INSERT INTO `categories` (`parent_id`, `name`, `slug`, `sort_order`) VALUES
(4, '離心機',         'centrifuge',       1),
(4, '培養箱',         'incubator',        2),
(4, '攪拌加熱',       'stirrer-heater',   3),
(4, '天平/磅秤',      'balance',          4),
(4, '真空設備',       'vacuum',           5);

-- 預設管理員帳號（密碼：Admin@2026，請上線前立即更改）
-- password_hash('Admin@2026', PASSWORD_BCRYPT)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('系統管理員', 'admin@zerograv.com.tw',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin');

SET foreign_key_checks = 1;
