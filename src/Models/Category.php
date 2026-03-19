<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getPDO();
    }

    /** 取得所有父分類（含子分類） */
    public function allWithChildren(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name"
        );
        $all = $stmt->fetchAll();

        $parents  = [];
        $children = [];

        foreach ($all as $cat) {
            if ($cat['parent_id'] === null) {
                $cat['children'] = [];
                $parents[$cat['id']] = $cat;
            } else {
                $children[(int)$cat['parent_id']][] = $cat;
            }
        }

        foreach ($children as $parentId => $kids) {
            if (isset($parents[$parentId])) {
                $parents[$parentId]['children'] = $kids;
            }
        }

        return array_values($parents);
    }

    /** 取得所有分類（平坦列表，用於下拉選單） */
    public function flatList(): array
    {
        $stmt = $this->db->query(
            "SELECT c.*, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.is_active = 1
             ORDER BY COALESCE(c.parent_id, c.id), c.sort_order"
        );
        return $stmt->fetchAll();
    }

    /** 依 slug 取得分類 */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }
}
