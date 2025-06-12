<?php
/**
 * Fix Panorama Directories Permissions
 * This script checks and fixes permissions for panorama directories
 */

// Define directories to check
$directories = [
    'img/panoramas',
    'uploads/panoramas',
    'logs'
];

// Function to check and fix directory
function checkAndFixDirectory($dir) {
    echo "<h3>Checking directory: $dir</h3>";
    
    $fullPath = __DIR__ . '/' . $dir;
    
    // Check if directory exists
    if (!file_exists($fullPath)) {
        echo "Directory does not exist, creating...<br>";
        if (mkdir($fullPath, 0777, true)) {
            echo "Directory created successfully.<br>";
        } else {
            echo "Failed to create directory: " . error_get_last()['message'] . "<br>";
            return false;
        }
    } else {
        echo "Directory exists.<br>";
    }
    
    // Check if directory is writable
    if (!is_writable($fullPath)) {
        echo "Directory is not writable, attempting to set permissions...<br>";
        
        // Try PHP's chmod
        if (@chmod($fullPath, 0777)) {
            echo "Permissions set successfully using PHP chmod.<br>";
        } else {
            echo "Failed to set permissions using PHP chmod: " . error_get_last()['message'] . "<br>";
            
            // Try system commands
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows commands
                echo "Trying Windows commands...<br>";
                
                // Remove read-only attribute
                $cmd = 'attrib -R "' . $fullPath . '" /S /D';
                echo "Running: $cmd<br>";
                exec($cmd, $output, $returnVar);
                echo "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "<br>";
                
                // Grant full permissions to Everyone
                $cmd = 'icacls "' . $fullPath . '" /grant Everyone:(OI)(CI)F';
                echo "Running: $cmd<br>";
                exec($cmd, $output, $returnVar);
                echo "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "<br>";
            } else {
                // Linux/Unix commands
                echo "Trying Unix commands...<br>";
                $cmd = 'chmod -R 777 "' . $fullPath . '"';
                echo "Running: $cmd<br>";
                exec($cmd, $output, $returnVar);
                echo "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "<br>";
            }
        }
    } else {
        echo "Directory is writable.<br>";
    }
    
    // Final check
    if (is_writable($fullPath)) {
        echo "Directory is now writable.<br>";
        
        // Create a test file to verify write permissions
        $testFile = $fullPath . '/test_' . time() . '.txt';
        if (file_put_contents($testFile, 'Test file to verify write permissions')) {
            echo "Test file created successfully: " . basename($testFile) . "<br>";
            // Clean up test file
            unlink($testFile);
            echo "Test file removed.<br>";
            return true;
        } else {
            echo "Failed to create test file: " . error_get_last()['message'] . "<br>";
            return false;
        }
    } else {
        echo "Directory is still not writable after all attempts.<br>";
        return false;
    }
}

// Display system information
echo "<h2>System Information</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "OS: " . PHP_OS . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Current User: " . get_current_user() . "<br>";
echo "Script Owner: " . fileowner(__FILE__) . "<br>";
echo "Current Working Directory: " . getcwd() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Path: " . __FILE__ . "<br>";

// Check and fix each directory
echo "<h2>Directory Checks</h2>";
$allSuccess = true;
foreach ($directories as $dir) {
    $success = checkAndFixDirectory($dir);
    if (!$success) {
        $allSuccess = false;
    }
    echo "<hr>";
}

// Summary
echo "<h2>Summary</h2>";
if ($allSuccess) {
    echo "<div style='color: green; font-weight: bold;'>All directories are now properly configured and writable.</div>";
} else {
    echo "<div style='color: red; font-weight: bold;'>Some directories could not be properly configured. Please check the details above.</div>";
}

// Create upload_debug.log file if it doesn't exist
$logFile = __DIR__ . '/logs/upload_debug.log';
if (!file_exists($logFile)) {
    echo "<h3>Creating upload_debug.log file</h3>";
    if (file_put_contents($logFile, date('Y-m-d H:i:s') . " - Log file created\n")) {
        echo "Log file created successfully.<br>";
    } else {
        echo "Failed to create log file: " . error_get_last()['message'] . "<br>";
    }
}
?>