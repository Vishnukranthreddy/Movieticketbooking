<?php
// Test script to check directory permissions

echo "<h1>Directory Permission Test</h1>";

// Function to test directory permissions
function testDirectory($path) {
    echo "<h2>Testing: $path</h2>";
    
    // Check if directory exists
    if (!file_exists($path)) {
        echo "<p>Directory does not exist. Attempting to create...</p>";
        if (mkdir($path, 0777, true)) {
            echo "<p style='color:green'>Directory created successfully!</p>";
        } else {
            echo "<p style='color:red'>Failed to create directory: " . error_get_last()['message'] . "</p>";
            return;
        }
    } else {
        echo "<p>Directory exists.</p>";
    }
    
    // Check if directory is writable
    if (is_writable($path)) {
        echo "<p style='color:green'>Directory is writable!</p>";
    } else {
        echo "<p style='color:red'>Directory is NOT writable!</p>";
        
        // Try to make it writable
        echo "<p>Attempting to set permissions...</p>";
        if (chmod($path, 0777)) {
            echo "<p style='color:green'>Permissions set successfully!</p>";
            if (is_writable($path)) {
                echo "<p style='color:green'>Directory is now writable!</p>";
            } else {
                echo "<p style='color:red'>Directory is still NOT writable despite chmod!</p>";
            }
        } else {
            echo "<p style='color:red'>Failed to set permissions: " . error_get_last()['message'] . "</p>";
        }
    }
    
    // Try to create a test file
    $testFile = $path . '/test_' . time() . '.txt';
    echo "<p>Attempting to create test file: $testFile</p>";
    
    if (file_put_contents($testFile, 'Test content')) {
        echo "<p style='color:green'>Test file created successfully!</p>";
        
        // Try to delete the test file
        if (unlink($testFile)) {
            echo "<p style='color:green'>Test file deleted successfully!</p>";
        } else {
            echo "<p style='color:red'>Failed to delete test file: " . error_get_last()['message'] . "</p>";
        }
    } else {
        echo "<p style='color:red'>Failed to create test file: " . error_get_last()['message'] . "</p>";
    }
    
    // Show directory permissions
    echo "<h3>Directory Information:</h3>";
    echo "<p>Owner ID: " . fileowner($path) . "</p>";
    echo "<p>Group ID: " . filegroup($path) . "</p>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "</p>";
}

// System information
echo "<h2>System Information</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>PHP User: " . get_current_user() . "</p>";
echo "<p>Current Working Directory: " . getcwd() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Owner ID: " . fileowner(__FILE__) . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";

// Test directories
$directories = [
    __DIR__ . '/img/panoramas/',
    __DIR__ . '/uploads/panoramas/',
    sys_get_temp_dir() . '/movie_uploads/'
];

foreach ($directories as $dir) {
    testDirectory($dir);
    echo "<hr>";
}

// Test upload functionality
echo "<h2>Upload Test Form</h2>";
echo "<form action='' method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='test_file'>";
echo "<input type='submit' name='submit' value='Test Upload'>";
echo "</form>";

if (isset($_POST['submit'])) {
    if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] == UPLOAD_ERR_OK) {
        echo "<h3>Upload Information:</h3>";
        echo "<p>Temp file: " . $_FILES['test_file']['tmp_name'] . "</p>";
        echo "<p>File name: " . $_FILES['test_file']['name'] . "</p>";
        echo "<p>File size: " . $_FILES['test_file']['size'] . " bytes</p>";
        
        // Try to upload to each directory
        foreach ($directories as $dir) {
            $targetFile = $dir . basename($_FILES['test_file']['name']);
            echo "<h4>Trying to upload to: $targetFile</h4>";
            
            if (move_uploaded_file($_FILES['test_file']['tmp_name'], $targetFile)) {
                echo "<p style='color:green'>File uploaded successfully!</p>";
                
                // Try to delete the file
                if (unlink($targetFile)) {
                    echo "<p style='color:green'>File deleted successfully!</p>";
                } else {
                    echo "<p style='color:red'>Failed to delete file: " . error_get_last()['message'] . "</p>";
                }
                
                break; // Stop after first successful upload
            } else {
                echo "<p style='color:red'>Failed to upload file: " . error_get_last()['message'] . "</p>";
                
                // Try copy instead
                echo "<p>Trying copy() instead...</p>";
                if (copy($_FILES['test_file']['tmp_name'], $targetFile)) {
                    echo "<p style='color:green'>File copied successfully!</p>";
                    
                    // Try to delete the file
                    if (unlink($targetFile)) {
                        echo "<p style='color:green'>File deleted successfully!</p>";
                    } else {
                        echo "<p style='color:red'>Failed to delete file: " . error_get_last()['message'] . "</p>";
                    }
                    
                    break; // Stop after first successful upload
                } else {
                    echo "<p style='color:red'>Failed to copy file: " . error_get_last()['message'] . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color:red'>Upload error: " . $_FILES['test_file']['error'] . "</p>";
    }
}
?>