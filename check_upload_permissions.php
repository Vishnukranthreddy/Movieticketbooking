<?php
/**
 * Script to check upload directory permissions and diagnose issues
 */

echo "<h1>Upload Directory Permission Check</h1>";

// Function to check directory permissions
function checkDirectory($path, $label) {
    echo "<h2>$label: $path</h2>";
    
    // Check if directory exists
    if (!file_exists($path)) {
        echo "<p style='color:orange'>Directory does not exist.</p>";
        echo "<p>Attempting to create directory...</p>";
        
        if (mkdir($path, 0777, true)) {
            echo "<p style='color:green'>Directory created successfully!</p>";
        } else {
            $error = error_get_last();
            echo "<p style='color:red'>Failed to create directory: " . ($error ? $error['message'] : 'Unknown error') . "</p>";
            echo "<p>This is likely a permission issue. The web server user does not have permission to create directories in this location.</p>";
            return false;
        }
    } else {
        echo "<p style='color:green'>Directory exists.</p>";
    }
    
    // Check if directory is writable
    if (is_writable($path)) {
        echo "<p style='color:green'>Directory is writable!</p>";
    } else {
        echo "<p style='color:red'>Directory is NOT writable!</p>";
        echo "<p>This is a permission issue. The web server user does not have write permission to this directory.</p>";
        
        // Try to make it writable
        echo "<p>Attempting to set permissions to 0777...</p>";
        if (@chmod($path, 0777)) {
            echo "<p style='color:green'>Permissions set successfully!</p>";
            
            if (is_writable($path)) {
                echo "<p style='color:green'>Directory is now writable!</p>";
            } else {
                echo "<p style='color:red'>Directory is still NOT writable despite chmod!</p>";
                echo "<p>This suggests a deeper permission issue, possibly related to the file system or server configuration.</p>";
            }
        } else {
            $error = error_get_last();
            echo "<p style='color:red'>Failed to set permissions: " . ($error ? $error['message'] : 'Unknown error') . "</p>";
            echo "<p>This indicates that PHP does not have permission to change the directory permissions.</p>";
        }
        
        return false;
    }
    
    // Try to create a test file
    $testFile = $path . '/test_' . time() . '.txt';
    echo "<p>Testing write permissions by creating a test file: " . basename($testFile) . "</p>";
    
    if (file_put_contents($testFile, 'Test content')) {
        echo "<p style='color:green'>Test file created successfully!</p>";
        
        // Try to delete the test file
        if (unlink($testFile)) {
            echo "<p style='color:green'>Test file deleted successfully!</p>";
        } else {
            $error = error_get_last();
            echo "<p style='color:red'>Failed to delete test file: " . ($error ? $error['message'] : 'Unknown error') . "</p>";
            echo "<p>This suggests that while the directory is writable, there may be issues with file deletion.</p>";
        }
        
        return true;
    } else {
        $error = error_get_last();
        echo "<p style='color:red'>Failed to create test file: " . ($error ? $error['message'] : 'Unknown error') . "</p>";
        echo "<p>This indicates a problem with file creation despite the directory appearing to be writable.</p>";
        return false;
    }
}

// System information
echo "<h2>System Information</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>PHP User:</strong> " . get_current_user() . "</p>";
echo "<p><strong>Current Working Directory:</strong> " . getcwd() . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Owner:</strong> " . fileowner(__FILE__) . "</p>";
echo "<p><strong>Operating System:</strong> " . PHP_OS . "</p>";

// Check upload directories
$directories = [
    [__DIR__ . '/img/panoramas', 'Primary Upload Directory'],
    [__DIR__ . '/uploads/panoramas', 'Fallback Upload Directory'],
    [sys_get_temp_dir() . '/movie_uploads', 'Temporary Upload Directory'],
    [__DIR__ . '/logs', 'Log Directory']
];

$allOk = true;
foreach ($directories as $dirInfo) {
    $result = checkDirectory($dirInfo[0], $dirInfo[1]);
    $allOk = $allOk && $result;
    echo "<hr>";
}

// Overall status
echo "<h2>Overall Status</h2>";
if ($allOk) {
    echo "<p style='color:green; font-size: 1.2em;'>All directories are properly configured and writable!</p>";
    echo "<p>File uploads should work correctly.</p>";
} else {
    echo "<p style='color:red; font-size: 1.2em;'>Some directories have permission issues!</p>";
    echo "<p>File uploads may fail or use fallback mechanisms.</p>";
    
    // Provide recommendations based on OS
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        echo "<h3>Recommendations for Windows</h3>";
        echo "<p>Run these commands as an administrator:</p>";
        echo "<pre>
icacls \"" . __DIR__ . "\\img\\panoramas\" /grant Everyone:(OI)(CI)F
icacls \"" . __DIR__ . "\\uploads\\panoramas\" /grant Everyone:(OI)(CI)F
icacls \"" . __DIR__ . "\\logs\" /grant Everyone:(OI)(CI)F
</pre>";
    } else {
        echo "<h3>Recommendations for Linux/Unix</h3>";
        echo "<p>Run these commands as root or with sudo:</p>";
        echo "<pre>
chmod -R 777 " . __DIR__ . "/img/panoramas
chmod -R 777 " . __DIR__ . "/uploads/panoramas
chmod -R 777 " . __DIR__ . "/logs

# Or better, set ownership to the web server user (e.g., www-data, apache, nginx)
chown -R www-data:www-data " . __DIR__ . "/img/panoramas
chown -R www-data:www-data " . __DIR__ . "/uploads/panoramas
chown -R www-data:www-data " . __DIR__ . "/logs
</pre>";
    }
}

// Add a test upload form
echo "<h2>Test File Upload</h2>";
echo "<form action='' method='post' enctype='multipart/form-data'>";
echo "<p><input type='file' name='test_file'></p>";
echo "<p><input type='submit' name='submit' value='Test Upload'></p>";
echo "</form>";

// Process test upload
if (isset($_POST['submit']) && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Results</h3>";
    
    if ($_FILES['test_file']['error'] == UPLOAD_ERR_OK) {
        echo "<p><strong>File Name:</strong> " . $_FILES['test_file']['name'] . "</p>";
        echo "<p><strong>File Size:</strong> " . $_FILES['test_file']['size'] . " bytes</p>";
        echo "<p><strong>Temporary File:</strong> " . $_FILES['test_file']['tmp_name'] . "</p>";
        
        // Try to upload to each directory
        foreach ($directories as $dirInfo) {
            $dir = $dirInfo[0];
            $label = $dirInfo[1];
            
            echo "<h4>Testing upload to $label</h4>";
            
            $targetFile = $dir . '/' . basename($_FILES['test_file']['name']);
            
            if (move_uploaded_file($_FILES['test_file']['tmp_name'], $targetFile)) {
                echo "<p style='color:green'>File uploaded successfully to $label!</p>";
                
                // Delete the file
                if (unlink($targetFile)) {
                    echo "<p style='color:green'>Test file deleted successfully.</p>";
                } else {
                    echo "<p style='color:red'>Failed to delete test file: " . error_get_last()['message'] . "</p>";
                }
                
                // Re-upload for next test
                copy($_FILES['test_file']['tmp_name'], $_FILES['test_file']['tmp_name'] . '.bak');
                $_FILES['test_file']['tmp_name'] = $_FILES['test_file']['tmp_name'] . '.bak';
            } else {
                echo "<p style='color:red'>Failed to upload file to $label: " . error_get_last()['message'] . "</p>";
            }
        }
    } else {
        echo "<p style='color:red'>Upload error: " . $_FILES['test_file']['error'] . "</p>";
        
        // Provide error explanation
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        ];
        
        if (isset($errorMessages[$_FILES['test_file']['error']])) {
            echo "<p>" . $errorMessages[$_FILES['test_file']['error']] . "</p>";
        }
    }
}

// PHP configuration information
echo "<h2>PHP Upload Configuration</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . " seconds</p>";
echo "<p><strong>Temporary upload directory:</strong> " . ini_get('upload_tmp_dir') . "</p>";
?>