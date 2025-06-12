<?php
/**
 * Check Panorama Directories
 * This script checks the status of panorama directories and displays information
 */

// Define directories to check
$directories = [
    'img/panoramas',
    'uploads/panoramas',
    'logs'
];

// Function to check directory
function checkDirectory($dir) {
    echo "<h3>Checking directory: $dir</h3>";
    
    $fullPath = __DIR__ . '/' . $dir;
    
    // Check if directory exists
    if (!file_exists($fullPath)) {
        echo "Directory does not exist.<br>";
        return false;
    } else {
        echo "Directory exists.<br>";
    }
    
    // Check if directory is writable
    if (!is_writable($fullPath)) {
        echo "Directory is not writable.<br>";
    } else {
        echo "Directory is writable.<br>";
    }
    
    // List files in directory
    echo "<h4>Files in directory:</h4>";
    $files = scandir($fullPath);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $fullPath . '/' . $file;
            echo "<li>" . $file;
            echo " (Size: " . filesize($filePath) . " bytes";
            echo ", Permissions: " . substr(sprintf('%o', fileperms($filePath)), -4);
            echo ", Writable: " . (is_writable($filePath) ? 'Yes' : 'No') . ")";
            echo "</li>";
        }
    }
    echo "</ul>";
    
    return true;
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

// Check each directory
echo "<h2>Directory Checks</h2>";
foreach ($directories as $dir) {
    checkDirectory($dir);
    echo "<hr>";
}

// Check for upload_debug.log file
$logFile = __DIR__ . '/logs/upload_debug.log';
echo "<h3>Checking for upload_debug.log file</h3>";
if (file_exists($logFile)) {
    echo "Log file exists.<br>";
    echo "File size: " . filesize($logFile) . " bytes<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($logFile)), -4) . "<br>";
    echo "Writable: " . (is_writable($logFile) ? 'Yes' : 'No') . "<br>";
    
    // Display last few lines of log file
    echo "<h4>Last 10 lines of log file:</h4>";
    $logContent = file($logFile);
    $lastLines = array_slice($logContent, -10);
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "Log file does not exist.<br>";
}
?>