<?php
session_start();
ob_start();

// ===================== CONFIGURATION =====================
$uploadDir = '../uploads/';
$maxFileSize = 1024 * 1024 * 1024; // 1GB for videos
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'zip',
                 'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg'];
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
    <title>File Upload Portal</title>
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

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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

        .uploader-info {
            background-color: #e8f6f3;
            border-left: 4px solid #2ecc71;
            padding: 15px;
            margin-bottom: 20px;
        }

        .admin-access {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>📤 File Upload Portal</h1>
            <div>
                <span class="status-badge status-uploader">
                    Upload Mode
                </span>
                <a href="admin.php" class="btn btn-admin">Admin Login</a>
            </div>
        </div>

        <?php if (!empty($uploadMessage)) echo $uploadMessage; ?>

        <div class="uploader-info">
            <p><strong>Welcome to the File Upload Portal!</strong> You can upload files, but you cannot see, download, or delete files uploaded by others. Only administrators have access to the file list.</p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <h2>Upload Video/File (Max 1GB)</h2>

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

        </div>

        <footer>
            <p>File Upload System | Uploads are stored in the <strong>uploads/</strong> directory</p>
            <p><strong>Upload Mode:</strong> Upload only | <strong>Admin Mode:</strong> Full access</p>
            <p><a href="admin.php" class="btn btn-admin" style="margin-top: 10px;">Access Admin Panel</a></p>
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

                    // Check if response is JSON
                    const contentType = xhr.getResponseHeader('Content-Type');
                    
                    if (contentType && contentType.includes('application/json')) {
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
                    } else {
                        // Server returned HTML/plain text instead of JSON
                        showStatus('Server error: ' + xhr.status + ' - ' + xhr.statusText, 'error');
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
