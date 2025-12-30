<?php
session_start();
ob_start();

// ===================== CONFIGURATION =====================
$uploadDir = '../uploads/';
$maxFileSize = 1024 * 1024 * 1024; // 1GB for videos
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip',
                 'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg'];
$adminUsername = 'admin';  // Change this
$adminPassword = 'admin123';  // Change this to a strong password
// =========================================================

// Increase PHP limits for large file uploads
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
ini_set('memory_limit', '256M');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("<div class='error'>Failed to create upload directory. Please create a folder named 'uploads' manually with write permissions.</div>");
    }
}

// Check if uploads directory is writable
if (!is_writable($uploadDir)) {
    die("<div class='error'>Upload directory is not writable. Please set write permissions (chmod 755) on the 'uploads' folder.</div>");
}

// Handle upload progress tracking
if (isset($_GET['progress'])) {
    session_start();
    $key = ini_get("session.upload_progress.prefix") . "file_upload";

    if (!empty($_SESSION[$key])) {
        $progress = $_SESSION[$key];
        $bytes_processed = $progress["bytes_processed"];
        $bytes_total = $progress["content_length"];
        $percentage = ($bytes_total > 0) ? round(($bytes_processed / $bytes_total) * 100) : 0;

        header('Content-Type: application/json');
        echo json_encode([
            'percentage' => $percentage,
            'bytes_processed' => formatBytes($bytes_processed),
            'bytes_total' => formatBytes($bytes_total),
            'status' => 'uploading'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'percentage' => 0,
            'bytes_processed' => '0 B',
            'bytes_total' => '0 B',
            'status' => 'waiting'
        ]);
    }
    exit;
}

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if user is logged in as admin
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Check PHP upload settings
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');

// Handle AJAX file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json');

    $file = $_FILES['file'];
    $response = ['success' => false, 'message' => '', 'filename' => ''];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file size
        if ($fileSize > $maxFileSize) {
            $response['message'] = 'File is too large. Maximum size: ' . formatBytes($maxFileSize);
        }
        // Check for empty file
        elseif ($fileSize == 0) {
            $response['message'] = 'File is empty. Please select a valid file.';
        }
        // Validate file type
        elseif (!in_array($fileExt, $allowedTypes)) {
            $response['message'] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        }
        // Check if file already exists
        elseif (file_exists($uploadDir . $fileName)) {
            // Add timestamp to filename to avoid conflicts
            $fileInfo = pathinfo($fileName);
            $fileName = $fileInfo['filename'] . '_' . time() . '.' . $fileInfo['extension'];
            $destination = $uploadDir . $fileName;
        } else {
            $destination = $uploadDir . $fileName;
        }

        if (empty($response['message'])) {
            // For large files, use chunked upload approach
            if ($fileSize > 100 * 1024 * 1024) { // For files > 100MB
                // Simple move for now - in production consider chunked upload
                if (move_uploaded_file($fileTmp, $destination)) {
                    chmod($destination, 0644);
                    $response['success'] = true;
                    $response['message'] = 'File uploaded successfully: ' . htmlspecialchars($fileName) . ' (' . formatBytes($fileSize) . ')';
                    $response['filename'] = $fileName;
                    $response['size'] = formatBytes($fileSize);
                } else {
                    $uploadError = error_get_last();
                    $response['message'] = 'Failed to upload file. Error: ' . (isset($uploadError['message']) ? $uploadError['message'] : 'Unknown error');
                }
            } else {
                // For smaller files
                if (move_uploaded_file($fileTmp, $destination)) {
                    chmod($destination, 0644);
                    $response['success'] = true;
                    $response['message'] = 'File uploaded successfully: ' . htmlspecialchars($fileName) . ' (' . formatBytes($fileSize) . ')';
                    $response['filename'] = $fileName;
                    $response['size'] = formatBytes($fileSize);
                } else {
                    $uploadError = error_get_last();
                    $response['message'] = 'Failed to upload file. Error: ' . (isset($uploadError['message']) ? $uploadError['message'] : 'Unknown error');
                }
            }
        }
    } else {
        // Handle specific upload errors
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];

        $errorCode = $file['error'];
        $errorMsg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Unknown upload error.';
        $response['message'] = 'Error uploading file: ' . $errorMsg . ' (Code: ' . $errorCode . ')';
    }

    echo json_encode($response);
    exit;
}

// Handle regular POST upload (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_regular'])) {
    $file = $_FILES['file_regular'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file size
        if ($fileSize > $maxFileSize) {
            $uploadMessage = '<div class="error">File is too large. Maximum size: ' . formatBytes($maxFileSize) . '</div>';
        }
        // Validate file type
        elseif (!in_array($fileExt, $allowedTypes)) {
            $uploadMessage = '<div class="error">File type not allowed. Allowed types: ' . implode(', ', $allowedTypes) . '</div>';
        }
        // Upload file
        else {
            $destination = $uploadDir . $fileName;

            if (file_exists($destination)) {
                $fileInfo = pathinfo($fileName);
                $fileName = $fileInfo['filename'] . '_' . time() . '.' . $fileInfo['extension'];
                $destination = $uploadDir . $fileName;
            }

            if (move_uploaded_file($fileTmp, $destination)) {
                chmod($destination, 0644);
                $uploadMessage = '<div class="success">File uploaded successfully: ' . htmlspecialchars($fileName) . ' (' . formatBytes($fileSize) . ')</div>';
            } else {
                $uploadMessage = '<div class="error">Failed to upload file. Please try again.</div>';
            }
        }
    }
}

// Handle file download (admin only)
if (isset($_GET['download'])) {
    if (!$isAdmin) {
        header('HTTP/1.0 403 Forbidden');
        die('<div class="error">Access denied. Admin login required to download files.</div>');
    }

    $fileName = basename($_GET['download']);
    $filePath = $uploadDir . $fileName;

    if (file_exists($filePath) && is_file($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $uploadMessage = '<div class="error">File not found: ' . htmlspecialchars($fileName) . '</div>';
    }
}

// Handle file deletion (admin only)
if (isset($_GET['delete'])) {
    if (!$isAdmin) {
        header('HTTP/1.0 403 Forbidden');
        die('<div class="error">Access denied. Admin login required to delete files.</div>');
    }

    $fileName = basename($_GET['delete']);
    $filePath = $uploadDir . $fileName;

    if (file_exists($filePath) && is_file($filePath)) {
        if (unlink($filePath)) {
            $uploadMessage = '<div class="success">File deleted successfully: ' . htmlspecialchars($fileName) . '</div>';
        } else {
            $uploadMessage = '<div class="error">Failed to delete file. Check file permissions.</div>';
        }
    } else {
        $uploadMessage = '<div class="error">File not found: ' . htmlspecialchars($fileName) . '</div>';
    }
}

// Get list of uploaded files (admin sees all, regular users see none)
function getUploadedFiles($dir, $isAdmin) {
    if (!$isAdmin) {
        return []; // Regular users can't see uploaded files
    }
    
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_file($dir . $item)) {
                $filePath = $dir . $item;
                $files[] = [
                    'name' => $item,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                    'type' => pathinfo($item, PATHINFO_EXTENSION)
                ];
            }
        }
    }
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    return $files;
}

$uploadedFiles = getUploadedFiles($uploadDir, $isAdmin);

// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get file type category for icons
function getFileTypeClass($ext) {
    $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];
    $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg'];
    $audioTypes = ['mp3', 'wav', 'ogg', 'm4a'];
    $docTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    $archiveTypes = ['zip', 'rar', '7z', 'tar', 'gz'];

    if (in_array($ext, $imageTypes)) return 'file-image';
    if (in_array($ext, $videoTypes)) return 'file-video';
    if (in_array($ext, $audioTypes)) return 'file-audio';
    if (in_array($ext, $docTypes)) return 'file-doc';
    if (in_array($ext, $archiveTypes)) return 'file-zip';
    return 'file-generic';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Video Upload with Progress</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            padding-bottom: 10px;
        }

        .admin-page h1 {
            border-bottom: 2px solid #3498db;
        }

        .upload-page h1 {
            border-bottom: 2px solid #2ecc71;
        }

        h2 {
            color: #3498db;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        h3 {
            color: #7f8c8d;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .login-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #ddd;
        }

        .login-form h2 {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-upload {
            background-color: #2ecc71;
        }

        .btn-upload:hover {
            background-color: #27ae60;
        }

        .btn-admin {
            background-color: #3498db;
        }

        .btn-admin:hover {
            background-color: #2980b9;
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #2ecc71;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-warning {
            background-color: #f39c12;
        }

        .btn-warning:hover {
            background-color: #d68910;
        }

        .btn-logout {
            background-color: #95a5a6;
        }

        .btn-logout:hover {
            background-color: #7f8c8d;
        }

        .message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .files-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .files-table th, .files-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .files-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .files-table tr:hover {
            background-color: #f8f9fa;
        }

        .file-actions {
            display: flex;
            gap: 10px;
        }

        .empty-files {
            text-align: center;
            padding: 30px;
            color: #777;
            font-style: italic;
        }

        .file-type-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 8px;
            vertical-align: middle;
            background-size: contain;
            background-repeat: no-repeat;
        }

        .file-video { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%239b59b6"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>'); }
        .file-audio { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e74c3c"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>'); }
        .file-image { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233498db"><path d="M8.5 13.5l2.5 3 3.5-4.5 4.5 6H5M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>'); }
        .file-doc { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232ecc71"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'); }
        .file-pdf { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e74c3c"><path d="M8.267 14.68c-.184 0-.308.018-.372.036v1.178c.076.018.171.023.302.023.479 0 .774-.242.774-.651 0-.366-.254-.586-.704-.586zm3.487.012c-.183 0-.308.018-.372.036v2.61c.062.018.188.023.317.023.717 0 1.155-.423 1.155-1.16 0-.638-.423-1.509-1.1-1.509zM14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1.473 12.527c-.183 0-.308.018-.371.036v2.906c.062.018.188.023.317.023.866 0 1.355-.439 1.355-1.269 0-.774-.436-1.696-1.301-1.696zM13 9V3.5L18.5 9H13z"/></svg>'); }
        .file-zip { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f39c12"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 11h-3v3.75c0 1.24-1.01 2.25-2.25 2.25S8.5 17.99 8.5 16.75s1.01-2.25 2.25-2.25c.46 0 .89.14 1.25.38V11h4v2zm-3-4V3.5L18.5 9H13z"/></svg>'); }
        .file-generic { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2395a5a6"><path d="M6 2c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>'); }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-admin {
            background-color: #3498db;
            color: white;
        }

        .status-uploader {
            background-color: #2ecc71;
            color: white;
        }

        .upload-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px dashed #ccc;
        }

        /* Upload Progress Styles */
        .upload-progress {
            margin-top: 20px;
            display: none;
        }

        .progress-container {
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
            height: 20px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #2ecc71);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .upload-status {
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }

        .upload-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .upload-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .upload-zone {
            border: 2px dashed #2ecc71;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f8fafc;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .upload-zone:hover {
            background-color: #e3f2fd;
            border-color: #27ae60;
        }

        .upload-zone.dragover {
            background-color: #d4edda;
            border-color: #2ecc71;
        }

        .upload-icon {
            font-size: 48px;
            color: #2ecc71;
            margin-bottom: 15px;
        }

        .file-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            display: none;
        }

        .file-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-name {
            font-weight: 500;
        }

        .file-size {
            color: #666;
            font-size: 14px;
        }

        .php-info {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
        }

        .php-info ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .php-info li {
            margin-bottom: 5px;
        }

        footer {
            margin-top: 30px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .file-info {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .troubleshooting {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }

        .troubleshooting h3 {
            color: #856404;
            margin-top: 0;
        }

        .admin-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .uploader-info {
            background-color: #e8f6f3;
            border-left: 4px solid #2ecc71;
            padding: 15px;
            margin-bottom: 20px;
        }

        .admin-info {
            background-color: #e3f2fd;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
        }

        .page-switcher {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .file-actions {
                flex-direction: column;
                gap: 5px;
            }

            .files-table {
                display: block;
                overflow-x: auto;
            }

            .admin-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container <?php echo $isAdmin ? 'admin-page' : 'upload-page'; ?>">
        <div class="header-bar">
            <h1>
                <?php if ($isAdmin): ?>
                    📁 Admin File Manager
                <?php else: ?>
                    📤 File Upload Portal
                <?php endif; ?>
            </h1>
            <div>
                <?php if ($isAdmin): ?>
                    <span class="status-badge status-admin">
                        Admin Mode
                    </span>
                    <a href="?logout=1" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <span class="status-badge status-uploader">
                        Upload Mode
                    </span>
                    <?php if (isset($_SESSION['admin_username'])): ?>
                        <a href="?logout=1" class="btn btn-logout">Logout</a>
                    <?php else: ?>
                        <a href="#admin-login" class="btn btn-admin">Admin Login</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($uploadMessage)) echo $uploadMessage; ?>

        <!-- Page Switcher -->
        <div class="page-switcher">
            <?php if ($isAdmin): ?>
                <p>You are in <strong>Admin Mode</strong>. You can see all uploaded files and manage them.</p>
                <a href="?logout=1" class="btn btn-upload">Switch to Upload Mode</a>
            <?php else: ?>
                <p>You are in <strong>Upload Mode</strong>. You can upload files but cannot see or download other users' files.</p>
                <p><a href="#admin-login" class="btn btn-admin">Switch to Admin Mode</a></p>
            <?php endif; ?>
        </div>

        <!-- Upload Section (Visible to Everyone) -->
        <div class="upload-section">
            <h2>Upload Video/File (Max 1GB)</h2>
            
            <?php if (!$isAdmin): ?>
                <div class="uploader-info">
                    <p><strong>Uploader Notice:</strong> You can upload files, but you cannot see, download, or delete files uploaded by others. Only administrators have access to the file list.</p>
                </div>
            <?php endif; ?>

            <div class="upload-zone" id="uploadZone">
                <div class="upload-icon">📁</div>
                <h3>Drag & Drop files here</h3>
                <p>or click to browse</p>
                <p class="file-info">Max file size: <?php echo formatBytes($maxFileSize); ?>. Allowed types: <?php echo implode(', ', $allowedTypes); ?></p>
                <input type="file" id="fileInput" style="display: none;" accept="<?php echo '.' . implode(',.', $allowedTypes); ?>">
            </div>

            <div class="file-list" id="fileList"></div>

            <div class="upload-progress" id="uploadProgress">
                <div class="progress-info">
                    <span id="progressStatus">Preparing upload...</span>
                    <span id="progressPercentage">0%</span>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="progress-info">
                    <span id="progressProcessed">0 B</span>
                    <span id="progressTotal">0 B</span>
                </div>
            </div>

            <div class="upload-status" id="uploadStatus"></div>

            <button id="uploadButton" class="btn btn-upload" style="display: none;">Start Upload</button>

            <!-- Fallback form for non-JS browsers -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h3>Alternative Upload (No JavaScript)</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file_regular">Select file to upload:</label>
                        <input type="file" name="file_regular" id="file_regular" required>
                    </div>
                    <button type="submit" class="btn btn-upload">Upload File</button>
                </form>
            </div>
        </div>

        <!-- File List (Only visible to Admin) -->
        <?php if ($isAdmin): ?>
            <div class="files-list">
                <h2>All Uploaded Files (<?php echo count($uploadedFiles); ?>)</h2>

                <?php if (empty($uploadedFiles)): ?>
                    <div class="empty-files">
                        No files uploaded yet. Upload files using the form above.
                    </div>
                <?php else: ?>
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Last Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploadedFiles as $file): ?>
                                <?php
                                $fileTypeClass = getFileTypeClass($file['type']);
                                $isVideo = in_array($file['type'], ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg']);
                                ?>
                                <tr>
                                    <td>
                                        <span class="file-type-icon <?php echo $fileTypeClass; ?>"></span>
                                        <?php echo htmlspecialchars($file['name']); ?>
                                        <?php if ($isVideo): ?>
                                            <span style="color: #9b59b6; font-weight: bold;">[VIDEO]</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><?php echo strtoupper($file['type']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                                    <td>
                                        <div class="file-actions">
                                            <a href="?download=<?php echo urlencode($file['name']); ?>" class="btn btn-admin">Download</a>
                                            <a href="?delete=<?php echo urlencode($file['name']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($file['name']); ?>?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Admin Actions -->
            <div class="admin-actions">
                <a href="?logout=1" class="btn btn-logout">Logout from Admin</a>
                <a href="#php-info" class="btn btn-warning">View PHP Info</a>
                <a href="#upload" class="btn btn-upload">Go to Upload</a>
            </div>
        <?php endif; ?>

        <!-- Admin Login Form (Visible to Everyone, but only when not logged in as admin) -->
        <?php if (!$isAdmin): ?>
            <div class="login-form" id="admin-login">
                <h2>Admin Login</h2>
                <p>Only administrators can view and manage all uploaded files.</p>
                <?php if (isset($loginError)): ?>
                    <div class="error"><?php echo $loginError; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-admin">Login as Admin</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- PHP Configuration Information (Visible to Everyone) -->
        <div class="php-info" id="php-info">
            <h3>System Information</h3>
            <ul>
                <li>Upload directory: <?php echo realpath($uploadDir); ?> (<?php echo is_writable($uploadDir) ? 'Writable' : 'Not Writable'; ?>)</li>
                <li>PHP upload_max_filesize: <?php echo $uploadMaxFilesize; ?></li>
                <li>PHP post_max_size: <?php echo $postMaxSize; ?></li>
                <li>Application max file size: <?php echo formatBytes($maxFileSize); ?></li>
                <li>Current mode: <?php echo $isAdmin ? 'Admin (can view all files)' : 'Uploader (can only upload)'; ?></li>
                <li>Total files uploaded: <?php 
                    if (is_dir($uploadDir)) {
                        $files = scandir($uploadDir);
                        $fileCount = count($files) - 2; // Subtract . and ..
                        echo max(0, $fileCount);
                    } else {
                        echo '0';
                    }
                ?></li>
            </ul>
        </div>

        <footer>
            <p>PHP File Upload System | Uploads are stored in the <strong>uploads/</strong> directory</p>
            <p><strong>Default Admin Credentials:</strong> admin / admin123 (Change these in the PHP code!)</p>
            <p><strong>Upload Mode:</strong> Upload only | <strong>Admin Mode:</strong> Full access</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            const fileList = document.getElementById('fileList');
            const uploadButton = document.getElementById('uploadButton');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadStatus = document.getElementById('uploadStatus');
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            const progressStatus = document.getElementById('progressStatus');
            const progressProcessed = document.getElementById('progressProcessed');
            const progressTotal = document.getElementById('progressTotal');

            let selectedFiles = [];
            let isUploading = false;
            let progressInterval;

            // Enable upload progress tracking
            enableUploadProgress();

            // Click on upload zone to trigger file input
            uploadZone.addEventListener('click', function() {
                fileInput.click();
            });

            // Handle file input change
            fileInput.addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });

            // Drag and drop functionality
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });

            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });

            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });

            // Handle selected files
            function handleFiles(files) {
                if (files.length === 0) return;

                selectedFiles = Array.from(files);
                fileList.innerHTML = '';

                selectedFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <div>
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="removeFile(${index})">Remove</button>
                    `;
                    fileList.appendChild(fileItem);
                });

                fileList.style.display = 'block';
                uploadButton.style.display = 'block';
            }

            // Remove file from list
            window.removeFile = function(index) {
                selectedFiles.splice(index, 1);
                handleFiles(selectedFiles);

                if (selectedFiles.length === 0) {
                    fileList.style.display = 'none';
                    uploadButton.style.display = 'none';
                }
            };

            // Upload button click
            uploadButton.addEventListener('click', function() {
                if (isUploading) return;

                if (selectedFiles.length === 0) {
                    showStatus('No files selected', 'error');
                    return;
                }

                // Upload each file sequentially
                uploadFilesSequentially(0);
            });

            // Upload files one by one
            function uploadFilesSequentially(index) {
                if (index >= selectedFiles.length) {
                    isUploading = false;
                    uploadButton.disabled = false;
                    uploadButton.textContent = 'Start Upload';
                    showStatus('All files uploaded successfully!', 'success');

                    // If in admin mode, refresh page to show new files
                    <?php if ($isAdmin): ?>
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    <?php endif; ?>
                    return;
                }

                const file = selectedFiles[index];
                uploadFile(file, index, selectedFiles.length);
            }

            // Upload single file
            function uploadFile(file, currentIndex, totalFiles) {
                isUploading = true;
                uploadButton.disabled = true;
                uploadButton.textContent = 'Uploading...';

                // Reset progress
                progressBar.style.width = '0%';
                progressPercentage.textContent = '0%';
                progressProcessed.textContent = '0 B';
                progressTotal.textContent = formatFileSize(file.size);
                progressStatus.textContent = `Uploading ${currentIndex + 1} of ${totalFiles}: ${file.name}`;

                uploadProgress.style.display = 'block';
                uploadStatus.style.display = 'none';

                const formData = new FormData();
                formData.append('file', file);

                // Start progress tracking
                startProgressTracking();

                // Send AJAX request
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        updateProgressBar(percentComplete, e.loaded, e.total);
                    }
                });

                xhr.addEventListener('load', function() {
                    stopProgressTracking();

                    try {
                        const response = JSON.parse(xhr.responseText);

                        if (response.success) {
                            showStatus(response.message, 'success');

                            // Upload next file after a short delay
                            setTimeout(() => {
                                uploadFilesSequentially(currentIndex + 1);
                            }, 1000);
                        } else {
                            showStatus(response.message, 'error');
                            isUploading = false;
                            uploadButton.disabled = false;
                            uploadButton.textContent = 'Start Upload';
                        }
                    } catch (e) {
                        showStatus('Error parsing server response', 'error');
                        isUploading = false;
                        uploadButton.disabled = false;
                        uploadButton.textContent = 'Start Upload';
                    }
                });

                xhr.addEventListener('error', function() {
                    stopProgressTracking();
                    showStatus('Network error occurred during upload', 'error');
                    isUploading = false;
                    uploadButton.disabled = false;
                    uploadButton.textContent = 'Start Upload';
                });

                xhr.open('POST', window.location.href);
                xhr.send(formData);
            }

            // Enable upload progress tracking
            function enableUploadProgress() {
                // Set session cookie for upload progress
                document.cookie = "PHPSESSID=<?php echo session_id(); ?>; path=/";
            }

            // Start tracking upload progress
            function startProgressTracking() {
                progressInterval = setInterval(function() {
                    fetch('?progress=1&t=' + Date.now())
                        .then(response => response.json())
                        .then(data => {
                            if (data.percentage > 0) {
                                updateProgressBar(data.percentage, data.bytes_processed, data.bytes_total);
                            }
                        })
                        .catch(error => console.error('Progress tracking error:', error));
                }, 500);
            }

            // Stop progress tracking
            function stopProgressTracking() {
                if (progressInterval) {
                    clearInterval(progressInterval);
                }
            }

            // Update progress bar
            function updateProgressBar(percentage, processed, total) {
                const percentValue = Math.min(Math.max(percentage, 0), 100);
                progressBar.style.width = percentValue + '%';
                progressPercentage.textContent = Math.round(percentValue) + '%';

                if (typeof processed === 'string') {
                    progressProcessed.textContent = processed;
                }

                if (typeof total === 'string') {
                    progressTotal.textContent = total;
                }
            }

            // Show status message
            function showStatus(message, type) {
                uploadStatus.textContent = message;
                uploadStatus.className = 'upload-status ' + type;
                uploadStatus.style.display = 'block';
            }

            // Format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        });
    </script>
</body>
</html>
