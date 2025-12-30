<?php
session_start();

// ===================== CONFIGURATION =====================
$uploadDir = 'uploads/';
$maxFileSize = 1024 * 1024 * 1024; // 1GB for videos
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip', 
                 'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg'];
$adminUsername = 'admin';  // Change this
$adminPassword = 'admin123';  // Change this to a strong password
// =========================================================

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

// Check if user is logged in
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Check PHP upload settings
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');

// Handle file upload (available to everyone)
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && !isset($_POST['login'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file size
        if ($fileSize > $maxFileSize) {
            $uploadMessage = '<div class="error">File is too large. Maximum size: ' . formatBytes($maxFileSize) . 
                           ' (Your PHP upload_max_filesize is: ' . $uploadMaxFilesize . ')</div>';
        }
        // Check for empty file
        elseif ($fileSize == 0) {
            $uploadMessage = '<div class="error">File is empty. Please select a valid file.</div>';
        }
        // Validate file type
        elseif (!in_array($fileExt, $allowedTypes)) {
            $uploadMessage = '<div class="error">File type not allowed. Allowed types: ' . implode(', ', $allowedTypes) . 
                           '<br>Your file type: ' . htmlspecialchars($fileExt) . '</div>';
        }
        // Check if file already exists
        elseif (file_exists($uploadDir . $fileName)) {
            $uploadMessage = '<div class="error">A file with this name already exists. Please rename your file.</div>';
        }
        // Upload file
        else {
            $destination = $uploadDir . $fileName;
            
            if (move_uploaded_file($fileTmp, $destination)) {
                // Set proper permissions on uploaded file
                chmod($destination, 0644);
                $uploadMessage = '<div class="success">File uploaded successfully: ' . htmlspecialchars($fileName) . 
                               ' (' . formatBytes($fileSize) . ')</div>';
            } else {
                // Get detailed error information
                $uploadError = error_get_last();
                $uploadMessage = '<div class="error">Failed to upload file. Please try again.<br>';
                $uploadMessage .= 'Error details: ' . (isset($uploadError['message']) ? $uploadError['message'] : 'Unknown error') . '</div>';
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
        $uploadMessage = '<div class="error">Error uploading file: ' . $errorMsg . ' (Code: ' . $errorCode . ')</div>';
    }
}

// Handle file download (admin only)
if (isset($_GET['download'])) {
    if (!$isLoggedIn) {
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
    if (!$isLoggedIn) {
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

// Get list of uploaded files
function getUploadedFiles($dir) {
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

$uploadedFiles = getUploadedFiles($uploadDir);

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
    <title>PHP File Manager with Admin Access</title>
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
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
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
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="file"]:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            display: inline-block;
            background-color: #3498db;
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
        
        .btn:hover {
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
        
        .status-logged-in {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-logged-out {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .upload-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px dashed #ccc;
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
    <div class="container">
        <div class="header-bar">
            <h1>Secure PHP File Manager</h1>
            <div>
                <?php if ($isLoggedIn): ?>
                    <span class="status-badge status-logged-in">
                        Logged in as: <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <span class="status-badge status-logged-out">Not logged in</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($uploadMessage)) echo $uploadMessage; ?>
        
        <!-- Login Form (shown when not logged in) -->
        <?php if (!$isLoggedIn): ?>
            <div class="login-form">
                <h2>Admin Login Required</h2>
                <p>Download and delete functions require admin authentication. Anyone can upload files.</p>
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
                    <button type="submit" name="login" class="btn btn-success">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-actions">
                <a href="?logout=1" class="btn btn-logout">Logout</a>
                <a href="#php-info" class="btn btn-warning">View PHP Info</a>
            </div>
        <?php endif; ?>
        
        <!-- Troubleshooting Section -->
        <div class="troubleshooting">
            <h3>Important Configuration</h3>
            <p>For 1GB uploads to work, you may need to update your PHP configuration:</p>
            <ol>
                <li>Edit <code>php.ini</code> file and set:
                    <ul>
                        <li><code>upload_max_filesize = 1024M</code></li>
                        <li><code>post_max_size = 1024M</code></li>
                        <li><code>max_execution_time = 300</code> (5 minutes)</li>
                        <li><code>max_input_time = 300</code></li>
                    </ul>
                </li>
                <li>Or create/modify <code>.htaccess</code> (Apache):
                    <pre>php_value upload_max_filesize 1024M
php_value post_max_size 1024M
php_value max_execution_time 300
php_value max_input_time 300</pre>
                </li>
            </ol>
        </div>
        
        <div class="upload-section">
            <h2>Upload File</h2>
            <p><strong>Note:</strong> Anyone can upload files. Admin login is only required for downloading.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Select file to upload (Max 1GB):</label>
                    <input type="file" name="file" id="file" required>
                    <div class="file-info">Max file size: <?php echo formatBytes($maxFileSize); ?>. Allowed types: <?php echo implode(', ', $allowedTypes); ?></div>
                </div>
                <button type="submit" class="btn btn-success">Upload File</button>
            </form>
        </div>
        
        <div class="files-list">
            <h2>Uploaded Files (<?php echo count($uploadedFiles); ?>)</h2>
            
            <?php if (empty($uploadedFiles)): ?>
                <div class="empty-files">
                    No files uploaded yet. Use the form above to upload your first file.
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
                                        <?php if ($isLoggedIn): ?>
                                            <a href="?download=<?php echo urlencode($file['name']); ?>" class="btn">Download</a>
                                            <a href="?delete=<?php echo urlencode($file['name']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($file['name']); ?>?')">Delete</a>
                                        <?php else: ?>
                                            <button class="btn" disabled title="Login required to download">Download</button>
                                            <button class="btn btn-danger" disabled title="Login required to delete">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- PHP Configuration Information -->
        <div class="php-info" id="php-info">
            <h3>PHP Configuration Information</h3>
            <ul>
                <li>Upload directory: <?php echo realpath($uploadDir); ?> (<?php echo is_writable($uploadDir) ? 'Writable' : 'Not Writable'; ?>)</li>
                <li>PHP upload_max_filesize: <?php echo $uploadMaxFilesize; ?> (Required: 1024M for 1GB videos)</li>
                <li>PHP post_max_size: <?php echo $postMaxSize; ?> (Required: 1024M for 1GB videos)</li>
                <li>PHP max_execution_time: <?php echo $maxExecutionTime; ?> seconds</li>
                <li>Application max file size: <?php echo formatBytes($maxFileSize); ?></li>
                <li>Admin logged in: <?php echo $isLoggedIn ? 'Yes' : 'No'; ?></li>
            </ul>
        </div>
        
        <footer>
            <p>Secure PHP File Manager | Files are stored in the <strong>uploads/</strong> directory</p>
            <p><strong>Default Admin Credentials:</strong> admin / admin123 (Change these in the PHP code!)</p>
            <p>Uploads: Public | Downloads/Deletes: Admin Only</p>
        </footer>
    </div>
</body>
</html>
