<?php
/**
 * טיפול בהעלאת תמונות: אימות MIME + סיומת, הגבלת גודל, שם קובץ אקראי, הקטנה ל-1600px רוחב מקסימלי.
 */

const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;
const UPLOAD_ALLOWED_EXT = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

/** @return array{ok: bool, filename?: string, error?: string} */
function handle_image_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'לא נבחר קובץ.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'שגיאה בהעלאת הקובץ.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'קובץ לא תקין.'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'הקובץ גדול מ-8MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset(UPLOAD_ALLOWED_EXT[$ext])) {
        return ['ok' => false, 'error' => 'סוג קובץ לא נתמך. יש להעלות jpg, png או webp.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== UPLOAD_ALLOWED_EXT[$ext]) {
        return ['ok' => false, 'error' => 'תוכן הקובץ אינו תואם את סוג הקובץ.'];
    }

    $filename = bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $destPath = UPLOADS_DIR . '/' . $filename;

    if (!downscale_and_save_image($file['tmp_name'], $destPath, $mime)) {
        return ['ok' => false, 'error' => 'שגיאה בעיבוד התמונה.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function downscale_and_save_image(string $srcPath, string $destPath, string $mime): bool
{
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($srcPath),
        'image/png' => @imagecreatefrompng($srcPath),
        'image/webp' => @imagecreatefromwebp($srcPath),
        default => false,
    };
    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > 1600) {
        $newW = 1600;
        $newH = (int) round($h * (1600 / $w));
        $resized = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($img, $destPath, 82),
        'image/png' => imagepng($img, $destPath, 6),
        'image/webp' => imagewebp($img, $destPath, 82),
        default => false,
    };
    imagedestroy($img);
    return $ok;
}

/** מוחק קובץ שהועלה בעבר (מתעלם מ-URLs חיצוניים של נתוני הדמו) */
function delete_uploaded_image(?string $filename): void
{
    if (!$filename || preg_match('#^https?://#i', $filename)) {
        return;
    }
    $path = UPLOADS_DIR . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * מעתיק קובץ תמונה קיים לשם קובץ אקראי חדש — נחוץ בשכפול נכס, כדי שלשני
 * הרשומות יהיו קבצים פיזיים נפרדים (מחיקת אחת לא תשבור את השנייה).
 * URLs חיצוניים (נתוני דמו) מוחזרים כמות שהם, בלי העתקה.
 */
function duplicate_uploaded_image(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }
    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }
    $srcPath = UPLOADS_DIR . '/' . basename($filename);
    if (!is_file($srcPath)) {
        return $filename;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $newFilename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!copy($srcPath, UPLOADS_DIR . '/' . $newFilename)) {
        return $filename;
    }
    return $newFilename;
}
