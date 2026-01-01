<?php
session_start();
ob_start();

// ===================== CONFIGURATION =====================
$uploadDir = '../uploads/';
$adminUsername = 'sunbeam';  // Change this
$adminPassword = 'Sunbeam@123';  // Change this to a strong password
// =========================================================

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
    header('Location: admin.php');
    exit;
}

// Check if user is logged in as admin
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle file download
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

// Handle file deletion
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
    <title>Admin File Manager</title>
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
            border-bottom: 2px solid #3498db;
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
            max-width: 400px;
            margin: 0 auto;
        }

        .login-form h2 {
            margin-top: 0;
            text-align: center;
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
        input[type="password"] {
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

        .btn-logout {
            background-color: #95a5a6;
        }

        .btn-logout:hover {
            background-color: #7f8c8d;
        }

        .btn-upload {
            background-color: #2ecc71;
        }

        .btn-upload:hover {
            background-color: #27ae60;
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

        .admin-info {
            background-color: #e3f2fd;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
        }

        .admin-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .stats-box {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            flex: 1;
            min-width: 200px;
        }

        .stat-card h3 {
            margin-top: 0;
            color: #3498db;
        }

        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        footer {
            margin-top: 30px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
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

            .stats-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>📁 Admin File Manager</h1>
            <div>
                <?php if ($isAdmin): ?>
                    <span class="status-badge status-admin">
                        Admin: <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-upload">Back to Upload</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($uploadMessage)) echo $uploadMessage; ?>

        <?php if (!$isAdmin): ?>
            <!-- Login Form -->
            <div class="login-form">
                <h2>Admin Login Required</h2>
                <p>You need admin credentials to access the file manager.</p>
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
                    <button type="submit" name="login" class="btn btn-admin" style="width: 100%;">Login as Admin</button>
                </form>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="index.php" class="btn btn-upload" style="display: block;">Go to Upload Page</a>
                </p>
            </div>
        <?php else: ?>
            <!-- Admin Dashboard -->
            <div class="admin-info">
                <p><strong>Welcome, Admin!</strong> You have full access to view, download, and delete all uploaded files.</p>
            </div>

            <!-- Statistics -->
            <div class="stats-box">
                <div class="stat-card">
                    <h3>Total Files</h3>
                    <div class="number"><?php echo count($uploadedFiles); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Size</h3>
                    <div class="number">
                        <?php
                        $totalSize = 0;
                        foreach ($uploadedFiles as $file) {
                            $totalSize += $file['size'];
                        }
                        echo formatBytes($totalSize);
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Last Upload</h3>
                    <div class="number">
                        <?php
                        if (!empty($uploadedFiles)) {
                            echo date('Y-m-d H:i', $uploadedFiles[0]['modified']);
                        } else {
                            echo 'No files';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- File List -->
            <div class="files-list">
                <h2>All Uploaded Files (<?php echo count($uploadedFiles); ?>)</h2>

                <?php if (empty($uploadedFiles)): ?>
                    <div class="empty-files">
                        No files uploaded yet. Users can upload files from the <a href="index.php">upload page</a>.
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
                <a href="index.php" class="btn btn-upload">Go to Upload Page</a>
                <button onclick="window.location.reload()" class="btn btn-admin">Refresh File List</button>
            </div>
        <?php endif; ?>

        <footer>
            <p>Admin File Manager | Files are stored in the <strong>uploads/</strong> directory</p>
            <p><a href="index.php" class="btn btn-upload" style="margin-top: 10px;">Go to Upload Portal</a></p>
        </footer>
    </div>
</body>
</html>
