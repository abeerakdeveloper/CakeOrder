<?php
/**
 * Helper functions to convert binary data to HEX for database storage
 * Compatible with PHP 5.3.2
 * 
 * Features:
 * - Only accepts JPG/JPEG/PNG
 * - Auto-resizes to max 1024px
 * - Always saves as JPG (smaller, faster)
 * - Generates separate thumbnail (200px)
 */

// Maximum dimensions
define('IMG_MAX_SIZE', 1024);     // Main image max width/height
define('IMG_THUMB_SIZE', 200);    // Thumbnail max width/height
define('IMG_QUALITY_MAIN', 75);   // JPEG quality for main (75 = good balance)
define('IMG_QUALITY_THUMB', 70);  // JPEG quality for thumbnail

/**
 * Convert base64 data URL to HEX string after:
 * 1. Validating it's an image
 * 2. Resizing to max IMG_MAX_SIZE
 * 3. Converting to JPG
 * 4. Storing as hex
 */
function dataUrlToHex($dataUrl) {
    if (empty($dataUrl)) return null;
    
    // Extract base64 portion
    if (strpos($dataUrl, 'base64,') !== false) {
        $parts = explode('base64,', $dataUrl);
        $base64Data = $parts[1];
        
        // Extract mime type
        preg_match('/data:(image\/[^;]+);/', $dataUrl, $matches);
        $mime = isset($matches[1]) ? strtolower($matches[1]) : '';
    } else {
        $base64Data = $dataUrl;
        $mime = '';
    }
    
    // ============================================
    // VALIDATE: Only accept JPG/JPEG/PNG (or audio webm passes through)
    // ============================================
    $isImage = (strpos($mime, 'image/') === 0);
    
    if ($isImage) {
        $allowedMimes = array('image/jpeg', 'image/jpg', 'image/png');
        if (!in_array($mime, $allowedMimes)) {
            error_log('Rejected image type: ' . $mime);
            return null;
        }
    }
    
    $binary = base64_decode($base64Data);
    if ($binary === false) return null;
    
    // Non-image data (audio) - just convert to hex
    if (!$isImage) {
        return bin2hex($binary);
    }
    
    // ============================================
    // PROCESS IMAGE: Resize + Convert to JPG
    // ============================================
    $jpgBinary = processImageToJpg($binary, IMG_MAX_SIZE, IMG_QUALITY_MAIN);
    
    if ($jpgBinary === false) {
        // GD failed, fallback to original (already validated as JPG/PNG)
        return bin2hex($binary);
    }
    
    return bin2hex($jpgBinary);
}

/**
 * Generate thumbnail from base64 image (resize to max 200px, save as JPG)
 */
function generateThumbnailHex($dataUrl, $maxSize = null) {
    if ($maxSize === null) $maxSize = IMG_THUMB_SIZE;
    
    if (empty($dataUrl)) return null;
    
    if (strpos($dataUrl, 'base64,') !== false) {
        $parts = explode('base64,', $dataUrl);
        $base64Data = $parts[1];
        preg_match('/data:(image\/[^;]+);/', $dataUrl, $matches);
        $mime = isset($matches[1]) ? strtolower($matches[1]) : '';
    } else {
        $base64Data = $dataUrl;
        $mime = '';
    }
    
    // Validate image type
    if (!empty($mime)) {
        $allowedMimes = array('image/jpeg', 'image/jpg', 'image/png');
        if (!in_array($mime, $allowedMimes)) {
            return null;
        }
    }
    
    $binary = base64_decode($base64Data);
    if ($binary === false) return null;
    
    $jpgBinary = processImageToJpg($binary, $maxSize, IMG_QUALITY_THUMB);
    
    if ($jpgBinary === false) {
        return bin2hex($binary);
    }
    
    return bin2hex($jpgBinary);
}

/**
 * Core image processor:
 * - Reads any image (JPG/PNG)
 * - Resizes maintaining aspect ratio
 * - Adds white background (for PNG transparency)
 * - Returns JPG binary
 */
function processImageToJpg($binary, $maxSize, $quality) {
    // Check if GD is available
    if (!function_exists('imagecreatefromstring')) {
        error_log('GD library not available');
        return false;
    }
    
    // Create source image
    $sourceImg = @imagecreatefromstring($binary);
    if (!$sourceImg) {
        error_log('Failed to create image from string');
        return false;
    }
    
    $srcW = imagesx($sourceImg);
    $srcH = imagesy($sourceImg);
    
    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($sourceImg);
        return false;
    }
    
    // Calculate new dimensions
    if ($srcW > $srcH) {
        if ($srcW > $maxSize) {
            $newW = $maxSize;
            $newH = intval($srcH * ($maxSize / $srcW));
        } else {
            $newW = $srcW;
            $newH = $srcH;
        }
    } else {
        if ($srcH > $maxSize) {
            $newH = $maxSize;
            $newW = intval($srcW * ($maxSize / $srcH));
        } else {
            $newW = $srcW;
            $newH = $srcH;
        }
    }
    
    // Create new image with white background (important for PNG transparency)
    $newImg = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($newImg, 255, 255, 255);
    imagefilledrectangle($newImg, 0, 0, $newW, $newH, $white);
    
    // Resample
    imagecopyresampled($newImg, $sourceImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
    
    // Output as JPEG to buffer
    ob_start();
    $ok = imagejpeg($newImg, null, $quality);
    $jpgBinary = ob_get_clean();
    
    // Cleanup
    imagedestroy($sourceImg);
    imagedestroy($newImg);
    
    if (!$ok || empty($jpgBinary)) {
        return false;
    }
    
    return $jpgBinary;
}

/**
 * Convert HEX back to binary
 */
function hexToBinary($hex) {
    if (empty($hex)) return null;
    return pack('H*', $hex);
}

/**
 * Convert HEX to base64 data URL for displaying image in <img> tag
 */
function hexToDataUrl($hex, $mime = 'image/jpeg') {
    if (empty($hex)) return '';
    $binary = pack('H*', $hex);
    return 'data:' . $mime . ';base64,' . base64_encode($binary);
}

/**
 * Validate uploaded file is an allowed image
 * For use with $_FILES upload (if you ever add direct upload)
 */
function isValidImageUpload($fileArray) {
    if (!isset($fileArray['tmp_name']) || !is_uploaded_file($fileArray['tmp_name'])) {
        return false;
    }
    
    $allowedMimes = array('image/jpeg', 'image/jpg', 'image/png');
    $allowedExts = array('jpg', 'jpeg', 'png');
    
    // Check MIME type using fileinfo (if available)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);
        if (!in_array(strtolower($mime), $allowedMimes)) return false;
    }
    
    // Check extension
    $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return false;
    
    // Try to actually load it as an image
    $info = @getimagesize($fileArray['tmp_name']);
    if (!$info || !isset($info[2])) return false;
    
    $allowedTypes = array(IMAGETYPE_JPEG, IMAGETYPE_PNG);
    if (!in_array($info[2], $allowedTypes)) return false;
    
    return true;
}
?>