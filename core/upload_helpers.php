<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  UPLOAD HELPERS — Image compression + file storage
 *  Compresses images to keep file sizes small.
 * ─────────────────────────────────────────────────────────────
 */

define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);   // 5MB raw upload limit
define('UPLOAD_QUALITY', 60);                   // JPEG quality after compression
define('UPLOAD_MAX_WIDTH', 1200);               // Max pixel width
define('UPLOAD_DIR', __DIR__ . '/../uploads/prerequisites/');
define('UPLOAD_ALLOWED', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);

/**
 * Handle a prerequisite file upload.
 * Compresses images and saves to disk.
 *
 * @param array  $file        $_FILES['field_name'] entry
 * @param int    $request_id  The request this attachment belongs to
 * @return array ['success' => bool, 'path' => string, 'size' => int, 'error' => string]
 */
function handle_prerequisite_upload(array $file, int $request_id): array
{
    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed (error code: ' . $file['error'] . ')'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File too large. Max: 5MB'];
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, WebP, and PDF files are allowed'];
    }

    // Create directory
    $dir = UPLOAD_DIR . $request_id . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('prereq_') . '.' . $ext;
    $destPath = $dir . $filename;

    // If it's an image, compress it
    if (str_starts_with($mime, 'image/')) {
        $compressed = compress_image($file['tmp_name'], $destPath, $mime);
        if (!$compressed) {
            // Fallback: just move the file
            move_uploaded_file($file['tmp_name'], $destPath);
        } else if ($mime === 'image/png' && file_exists(preg_replace('/\.png$/i', '.jpg', $destPath))) {
            $destPath = preg_replace('/\.png$/i', '.jpg', $destPath);
            $filename = preg_replace('/\.png$/i', '.jpg', $filename);
        }
    } else {
        // PDF — just move it
        move_uploaded_file($file['tmp_name'], $destPath);
    }

    $finalSize = filesize($destPath);
    $relativePath = 'uploads/prerequisites/' . $request_id . '/' . $filename;

    return [
        'success' => true,
        'path'    => $relativePath,
        'size'    => $finalSize,
        'error'   => null,
    ];
}

/**
 * Compress an image file — resize + reduce quality.
 */
function compress_image(string $source, string $dest, string $mime): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $createFunction = null;
    switch ($mime) {
        case 'image/jpeg':
            $createFunction = 'imagecreatefromjpeg';
            break;
        case 'image/png':
            $createFunction = 'imagecreatefrompng';
            break;
        case 'image/webp':
            $createFunction = 'imagecreatefromwebp';
            break;
        default:
            return false;
    }

    if (!function_exists($createFunction)) {
        return false;
    }

    $img = @$createFunction($source);
    if (!$img) return false;

    $width  = imagesx($img);
    $height = imagesy($img);

    // Resize if wider than max
    if ($width > UPLOAD_MAX_WIDTH) {
        $ratio     = UPLOAD_MAX_WIDTH / $width;
        $newWidth  = UPLOAD_MAX_WIDTH;
        $newHeight = (int)($height * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img);
        $img = $resized;
    }

    // Save as JPEG for maximum compression (except PNG with transparency)
    if ($mime === 'image/png') {
        // Convert to JPEG anyway for size savings
        $dest = preg_replace('/\.png$/i', '.jpg', $dest);
        imagejpeg($img, $dest, UPLOAD_QUALITY);
    } else {
        imagejpeg($img, $dest, UPLOAD_QUALITY);
    }

    imagedestroy($img);
    return true;
}

/**
 * Ensure the uploads directory has security .htaccess
 */
function ensure_upload_security(): void
{
    $htaccess = UPLOAD_DIR . '.htaccess';
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# Block PHP execution\nphp_flag engine off\n\n# Only allow image/PDF files\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps)$\">\n    Deny from all\n</FilesMatch>\n");
    }
}