<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

class Listing
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getPDO();
    }

    // ── 取得單筆（含圖片、分類、賣家）────────────────────────

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                    u.name AS seller_name, u.phone AS seller_phone,
                    u.line_id AS seller_line, u.company AS seller_company
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.user_id
             WHERE l.id = ?"
        );
        $stmt->execute([$id]);
        $listing = $stmt->fetch();
        if (!$listing) return null;

        $listing['images'] = $this->getImages($id);
        return $listing;
    }

    // ── 列表查詢（含篩選/搜尋/分頁）─────────────────────────

    public function search(array $params = []): array
    {
        $where  = ["l.status = 'active'"];
        $bind   = [];

        if (!empty($params['q'])) {
            $where[] = "MATCH(l.title, l.brand, l.model, l.description) AGAINST(? IN BOOLEAN MODE)";
            $bind[]  = '+' . implode(' +', array_filter(explode(' ', trim($params['q']))));
        }

        if (!empty($params['category'])) {
            // 支援父分類（顯示所有子分類結果）
            $where[] = "(c.slug = ? OR pc.slug = ?)";
            $bind[]  = $params['category'];
            $bind[]  = $params['category'];
        }

        if (!empty($params['condition'])) {
            $where[] = "l.condition = ?";
            $bind[]  = $params['condition'];
        }

        if (!empty($params['min_price'])) {
            $where[] = "l.price >= ?";
            $bind[]  = (int)$params['min_price'];
        }

        if (!empty($params['max_price'])) {
            $where[] = "l.price <= ?";
            $bind[]  = (int)$params['max_price'];
        }

        if (!empty($params['location'])) {
            $where[] = "l.location = ?";
            $bind[]  = $params['location'];
        }

        $whereStr = 'WHERE ' . implode(' AND ', $where);

        // 計算總數
        $countSql = "SELECT COUNT(*) FROM listings l
                     JOIN categories c ON c.id = l.category_id
                     LEFT JOIN categories pc ON pc.id = c.parent_id
                     {$whereStr}";
        $total = (int)$this->db->prepare($countSql)->execute($bind) ?
                 $this->db->prepare($countSql)->execute($bind) : 0;

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($bind);
        $total = (int)$countStmt->fetchColumn();

        // 排序
        $orderMap = [
            'newest'    => 'l.is_featured DESC, l.created_at DESC',
            'price_asc' => 'l.price ASC',
            'price_desc'=> 'l.price DESC',
            'views'     => 'l.views DESC',
        ];
        $order = $orderMap[$params['sort'] ?? 'newest'] ?? $orderMap['newest'];

        // 分頁
        $perPage = (int)($params['per_page'] ?? 20);
        $page    = max(1, (int)($params['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $sql = "SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                       u.name AS seller_name
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                LEFT JOIN categories pc ON pc.id = c.parent_id
                JOIN users u ON u.id = l.user_id
                {$whereStr}
                ORDER BY {$order}
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total, 'per_page' => $perPage, 'page' => $page];
    }

    // ── 我的刊登 ─────────────────────────────────────────────

    public function byUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->prepare(
            "SELECT COUNT(*) FROM listings WHERE user_id = ?"
        )->execute([$userId]) ? 0 : 0;

        $cStmt = $this->db->prepare("SELECT COUNT(*) FROM listings WHERE user_id = ?");
        $cStmt->execute([$userId]);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT l.*, c.name AS category_name
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$userId]);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    // ── 建立刊登 ─────────────────────────────────────────────

    public function create(array $data, int $userId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO listings
             (user_id, category_id, title, brand, model, year, `condition`,
              price, price_negotiable, description, specs, location, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $userId,
            (int)$data['category_id'],
            trim($data['title']),
            trim($data['brand'] ?? ''),
            trim($data['model'] ?? ''),
            !empty($data['year']) ? (int)$data['year'] : null,
            $data['condition'],
            (int)$data['price'],
            !empty($data['price_negotiable']) ? 1 : 0,
            trim($data['description']),
            !empty($data['specs']) ? $data['specs'] : null,
            trim($data['location'] ?? ''),
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── 更新刊登 ─────────────────────────────────────────────

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE listings SET
             category_id = ?, title = ?, brand = ?, model = ?, year = ?,
             `condition` = ?, price = ?, price_negotiable = ?, description = ?,
             location = ?, status = 'pending', updated_at = NOW()
             WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([
            (int)$data['category_id'],
            trim($data['title']),
            trim($data['brand'] ?? ''),
            trim($data['model'] ?? ''),
            !empty($data['year']) ? (int)$data['year'] : null,
            $data['condition'],
            (int)$data['price'],
            !empty($data['price_negotiable']) ? 1 : 0,
            trim($data['description']),
            trim($data['location'] ?? ''),
            $id,
            $userId,
        ]);
    }

    // ── 下架 ─────────────────────────────────────────────────

    public function deactivate(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE listings SET status = 'inactive' WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    // ── 設定封面圖 ───────────────────────────────────────────

    public function setCover(int $id, string $filename): void
    {
        $this->db->prepare("UPDATE listings SET cover_image = ? WHERE id = ?")
                 ->execute([$filename, $id]);
    }

    // ── 新增圖片記錄 ─────────────────────────────────────────

    public function addImage(int $listingId, string $filename, string $originalName, int $sortOrder): void
    {
        $this->db->prepare(
            "INSERT INTO listing_images (listing_id, filename, original_name, sort_order)
             VALUES (?, ?, ?, ?)"
        )->execute([$listingId, $filename, $originalName, $sortOrder]);
    }

    // ── 取得圖片 ─────────────────────────────────────────────

    public function getImages(int $listingId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order"
        );
        $stmt->execute([$listingId]);
        return $stmt->fetchAll();
    }

    // ── 刪除圖片記錄 ─────────────────────────────────────────

    public function deleteImage(int $imageId, int $listingId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT filename FROM listing_images WHERE id = ? AND listing_id = ?"
        );
        $stmt->execute([$imageId, $listingId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $this->db->prepare("DELETE FROM listing_images WHERE id = ?")->execute([$imageId]);
        return $row['filename'];
    }

    // ── 增加瀏覽數 ───────────────────────────────────────────

    public function incrementViews(int $id): void
    {
        $this->db->prepare("UPDATE listings SET views = views + 1 WHERE id = ?")
                 ->execute([$id]);
    }

    // ── 後台：待審核列表 ─────────────────────────────────────

    public function pendingList(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $cStmt = $this->db->prepare("SELECT COUNT(*) FROM listings WHERE status = 'pending'");
        $cStmt->execute();
        $total = (int)$cStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT l.*, c.name AS category_name, u.name AS seller_name, u.email AS seller_email
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.user_id
             WHERE l.status = 'pending'
             ORDER BY l.created_at ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    // ── 後台：審核動作 ───────────────────────────────────────

    public function review(int $id, int $adminId, string $action, string $reason = ''): bool
    {
        $status = ($action === 'approve') ? 'active' : 'rejected';
        $stmt = $this->db->prepare(
            "UPDATE listings SET status = ?, reject_reason = ?,
             reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$status, $reason, $adminId, $id]);
    }

    // ── 後台：所有刊登（含篩選狀態）────────────────────────

    public function adminList(string $status = '', int $page = 1, int $perPage = 30): array
    {
        $where = $status ? "WHERE l.status = ?" : '';
        $bind  = $status ? [$status] : [];
        $offset = ($page - 1) * $perPage;

        $cStmt = $this->db->prepare("SELECT COUNT(*) FROM listings l {$where}");
        $cStmt->execute($bind);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT l.*, c.name AS category_name, u.name AS seller_name
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u ON u.id = l.user_id
             {$where}
             ORDER BY l.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($bind);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }
}
