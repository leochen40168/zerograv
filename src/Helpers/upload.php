<?php
/**
 * ZeroGrav - 圖片上傳處理
 * 驗證、壓縮、儲存圖片
 */

declare(strict_types=1);

class ImageUploader
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_SIZE     = 5 * 1024 * 1024; // 5 MB
    private const MAX_FILES    = 5;

    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = self::webRoot() . '/uploads/listings';
    }

    /**
     * 自動偵測網站根目錄（相容 public_html / public / www / htdocs）
     */
    public static function webRoot(): string
    {
        $base = dirname(__DIR__, 2);
        foreach (['public_html', 'public', 'www', 'htdocs'] as $dir) {
            if (is_dir($base . '/' . $dir)) {
                return $base . '/' . $dir;
            }
        }
        return $base . '/public_html';
    }

    /**
     * 處理 $_FILES['images'] 多圖上傳
     *
     * @param  array $files  $_FILES['images']
     * @param  int   $listingId
     * @return array{saved: array<string>, errors: array<string>}
     */
    public function handleMultiple(array $files, int $listingId): array
    {
        $saved  = [];
        $errors = [];

        // 重組 $_FILES 陣列（多檔上傳格式）
        $fileList = $this->normalizeFiles($files);

        if (count($fileList) > self::MAX_FILES) {
            $errors[] = '最多只能上傳 ' . self::MAX_FILES . ' 張圖片';
            $fileList = array_slice($fileList, 0, self::MAX_FILES);
        }

        foreach ($fileList as $idx => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = $this->processSingle($file, $listingId, $idx);

            if ($result['success']) {
                $saved[] = $result['filename'];
            } else {
                $errors[] = "圖片 " . ($idx + 1) . "：" . $result['error'];
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    // ── 單張處理 ─────────────────────────────────────────────

    private function processSingle(array $file, int $listingId, int $idx): array
    {
        // 上傳錯誤
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => '上傳失敗（code: ' . $file['error'] . '）'];
        }

        // 大小
        if ($file['size'] > self::MAX_SIZE) {
            return ['success' => false, 'error' => '圖片超過 5MB 限制'];
        }

        // MIME type（讀檔案內容，非信任 $_FILES['type']）
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['success' => false, 'error' => '僅接受 JPG / PNG / WebP 格式'];
        }

        // 副檔名
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return ['success' => false, 'error' => '副檔名不合法'];
        }

        // 建立目錄
        $dir = $this->baseDir . '/' . $listingId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 產生唯一檔名
        $filename = sprintf('%d_%s.%s', $idx, bin2hex(random_bytes(8)), $ext);
        $destPath = $dir . '/' . $filename;

        // 移動並壓縮
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'error' => '儲存失敗'];
        }

        $this->resizeImage($destPath, $mime, 1200, 900);

        return ['success' => true, 'filename' => $filename];
    }

    // ── 壓縮圖片 ─────────────────────────────────────────────

    private function resizeImage(string $path, string $mime, int $maxW, int $maxH): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return; // GD 未安裝，跳過
        }

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => null,
        };

        if ($src === null || $src === false) {
            return;
        }

        [$w, $h] = [imagesx($src), imagesy($src)];

        // 不需縮放
        if ($w <= $maxW && $h <= $maxH) {
            imagedestroy($src);
            return;
        }

        $ratio  = min($maxW / $w, $maxH / $h);
        $newW   = (int)round($w * $ratio);
        $newH   = (int)round($h * $ratio);
        $dst    = imagecreatetruecolor($newW, $newH);

        // PNG / WebP 透明度
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $path, 85),
            'image/png'  => imagepng($dst, $path, 7),
            'image/webp' => imagewebp($dst, $path, 85),
            default      => null,
        };

        imagedestroy($src);
        imagedestroy($dst);
    }

    // ── $_FILES 格式正規化 ────────────────────────────────────

    private function normalizeFiles(array $files): array
    {
        if (!is_array($files['name'])) {
            return [$files];
        }

        $result = [];
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name'     => $name,
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
        return $result;
    }

    /** 刪除刊登的所有圖片目錄 */
    public function deleteListingImages(int $listingId): void
    {
        $dir = $this->baseDir . '/' . $listingId;
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    /** 取得圖片的實體磁碟路徑 */
    public static function imagePath(int $listingId, string $filename): string
    {
        return self::webRoot() . '/uploads/listings/' . $listingId . '/' . $filename;
    }
}
