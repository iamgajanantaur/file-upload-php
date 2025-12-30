<?php
// Configuration
$uploadDir = 'uploads/';
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip'];

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

// Check PHP upload settings
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');

// Handle file upload
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Debug file upload data (remove in production)
    // echo "<pre>"; print_r($file); echo "</pre>";
    
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

// Handle file download
if (isset($_GET['download'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Manager</title>
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
            max-width: 1000px;
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
        
        .upload-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px dashed #ccc;
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
        
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
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
        
        .file-pdf { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e74c3c"><path d="M8.267 14.68c-.184 0-.308.018-.372.036v1.178c.076.018.171.023.302.023.479 0 .774-.242.774-.651 0-.366-.254-.586-.704-.586zm3.487.012c-.183 0-.308.018-.372.036v2.61c.062.018.188.023.317.023.717 0 1.155-.423 1.155-1.16 0-.638-.423-1.509-1.1-1.509zM14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1.473 12.527c-.183 0-.308.018-.371.036v2.906c.062.018.188.023.317.023.866 0 1.355-.439 1.355-1.269 0-.774-.436-1.696-1.301-1.696zM13 9V3.5L18.5 9H13z"/></svg>'); }
        .file-image { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233498db"><path d="M8.5 13.5l2.5 3 3.5-4.5 4.5 6H5M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>'); }
        .file-text { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%239b59b6"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'); }
        .file-zip { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f39c12"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 11h-3v3.75c0 1.24-1.01 2.25-2.25 2.25S8.5 17.99 8.5 16.75s1.01-2.25 2.25-2.25c.46 0 .89.14 1.25.38V11h4v2zm-3-4V3.5L18.5 9H13z"/></svg>'); }
        .file-generic { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2395a5a6"><path d="M6 2c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>'); }
        
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
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .file-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .files-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PHP File Manager</h1>
        
        <?php if (!empty($uploadMessage)) echo $uploadMessage; ?>
        
        <!-- Troubleshooting Section -->
        <div class="troubleshooting">
            <h3>Troubleshooting Upload Issues</h3>
            <p>If uploads are failing, check the following:</p>
            <ol>
                <li>Make sure the <strong>uploads/</strong> folder exists and has write permissions (chmod 755 or 777)</li>
                <li>Check PHP configuration: 
                    <ul>
                        <li><code>upload_max_filesize</code>: <?php echo $uploadMaxFilesize; ?></li>
                        <li><code>post_max_size</code>: <?php echo $postMaxSize; ?></li>
                        <li><code>max_execution_time</code>: <?php echo $maxExecutionTime; ?> seconds</li>
                    </ul>
                </li>
                <li>Check if the file type is allowed: <?php echo implode(', ', $allowedTypes); ?></li>
                <li>Maximum file size for this application: <?php echo formatBytes($maxFileSize); ?></li>
            </ol>
        </div>
        
        <div class="upload-section">
            <h2>Upload File</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Select file to upload:</label>
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
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedFiles as $file): ?>
                            <?php
                            // Determine file type icon
                            $fileTypeClass = 'file-generic';
                            if (in_array($file['type'], ['jpg', 'jpeg', 'png', 'gif'])) {
                                $fileTypeClass = 'file-image';
                            } elseif (in_array($file['type'], ['pdf'])) {
                                $fileTypeClass = 'file-pdf';
                            } elseif (in_array($file['type'], ['txt', 'doc', 'docx'])) {
                                $fileTypeClass = 'file-text';
                            } elseif (in_array($file['type'], ['zip'])) {
                                $fileTypeClass = 'file-zip';
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="file-type-icon <?php echo $fileTypeClass; ?>"></span>
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </td>
                                <td><?php echo formatBytes($file['size']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                                <td>
                                    <div class="file-actions">
                                        <a href="?download=<?php echo urlencode($file['name']); ?>" class="btn">Download</a>
                                        <a href="?delete=<?php echo urlencode($file['name']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($file['name']); ?>?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- PHP Configuration Information -->
        <div class="php-info">
            <h3>PHP Configuration Information</h3>
            <ul>
                <li>Upload directory: <?php echo realpath($uploadDir); ?> (<?php echo is_writable($uploadDir) ? 'Writable' : 'Not Writable'; ?>)</li>
                <li>PHP upload_max_filesize: <?php echo $uploadMaxFilesize; ?></li>
                <li>PHP post_max_size: <?php echo $postMaxSize; ?></li>
                <li>PHP max_execution_time: <?php echo $maxExecutionTime; ?> seconds</li>
                <li>Allowed memory: <?php echo ini_get('memory_limit'); ?></li>
            </ul>
        </div>
        
        <footer>
            <p>Simple PHP File Manager | Files are stored in the <strong>uploads/</strong> directory</p>
            <p>Check the troubleshooting section above if you encounter upload issues.</p>
        </footer>
    </div>
</body>
</html>
