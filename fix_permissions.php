<?php
/**
 * Script to fix directory permissions for file uploads
 * This script should be run with appropriate privileges (e.g., sudo on Linux)
 */

// Define directories that need permissions fixed
$directories = [
    __DIR__ . '/img/panoramas',
    __DIR__ . '/uploads/panoramas',
    __DIR__ . '/logs'
];

echo "<h1>Fixing Directory Permissions</h1>";

// Function to fix directory permissions
function fixDirectoryPermissions($dir) {
    echo "<h2>Processing: $dir</h2>";
    
    // Create directory if it doesn't exist
    if (!file_exists($dir)) {
        echo "<p>Directory does not exist. Creating...</p>";
        if (mkdir($dir, 0777, true)) {
            echo "<p style='color:green'>Directory created successfully!</p>";
        } else {
            echo "<p style='color:red'>Failed to create directory: " . error_get_last()['message'] . "</p>";
            return false;
        }
    } else {
        echo "<p>Directory exists.</p>";
    }
    
    // Set permissions
    echo "<p>Setting permissions to 0777...</p>";
    
    // Try PHP's chmod
    if (@chmod($dir, 0777)) {
        echo "<p style='color:green'>Permissions set successfully using PHP chmod!</p>";
    } else {
        echo "<p style='color:red'>Failed to set permissions using PHP chmod: " . error_get_last()['message'] . "</p>";
        
        // Try system commands
        echo "<p>Attempting to use system commands...</p>";
        
        // On Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Remove read-only attribute
            $cmd = 'attrib -R "' . $dir . '" /S /D';
            echo "<p>Running Windows command: $cmd</p>";
            @exec($cmd, $output, $returnVar);
            echo "<p>Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "</p>";
            
            // Grant full permissions to Everyone
            $cmd = 'icacls "' . $dir . '" /grant Everyone:(OI)(CI)F';
            echo "<p>Running Windows command: $cmd</p>";
            @exec($cmd, $output, $returnVar);
            echo "<p>Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "</p>";
        } else {
            // On Linux/Unix
            $cmd = 'chmod -R 777 "' . $dir . '"';
            echo "<p>Running Unix command: $cmd</p>";
            @exec($cmd, $output, $returnVar);
            echo "<p>Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "</p>";
            
            // Try to change owner to web server user (www-data on many systems)
            $cmd = 'chown -R www-data:www-data "' . $dir . '"';
            echo "<p>Running Unix command: $cmd</p>";
            @exec($cmd, $output, $returnVar);
            echo "<p>Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)") . "</p>";
        }
    }
    
    // Verify permissions
    if (is_writable($dir)) {
        echo "<p style='color:green'>Directory is now writable!</p>";
        
        // Try to create a test file
        $testFile = $dir . '/test_' . time() . '.txt';
        echo "<p>Testing write permissions with test file...</p>";
        
        if (file_put_contents($testFile, 'Test content')) {
            echo "<p style='color:green'>Test file created successfully!</p>";
            
            // Clean up test file
            if (unlink($testFile)) {
                echo "<p style='color:green'>Test file deleted successfully!</p>";
            } else {
                echo "<p style='color:red'>Failed to delete test file: " . error_get_last()['message'] . "</p>";
            }
            
            return true;
        } else {
            echo "<p style='color:red'>Failed to create test file: " . error_get_last()['message'] . "</p>";
            return false;
        }
    } else {
        echo "<p style='color:red'>Directory is still not writable after all attempts!</p>";
        return false;
    }
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
echo "<p>Operating System: " . PHP_OS . "</p>";

// Process each directory
foreach ($directories as $dir) {
    fixDirectoryPermissions($dir);
    echo "<hr>";
}

echo "<h2>Recommendations for Linux/Unix Systems</h2>";
echo "<p>If you're still experiencing permission issues, try running these commands on your server:</p>";
echo "<pre>
sudo mkdir -p /var/www/html/uploads/panoramas
sudo mkdir -p /var/www/html/img/panoramas
sudo mkdir -p /var/www/html/logs

sudo chmod -R 777 /var/www/html/uploads
sudo chmod -R 777 /var/www/html/img
sudo chmod -R 777 /var/www/html/logs

sudo chown -R www-data:www-data /var/www/html/uploads
sudo chown -R www-data:www-data /var/www/html/img
sudo chown -R www-data:www-data /var/www/html/logs
</pre>";

echo "<h2>Recommendations for Windows Systems</h2>";
echo "<p>If you're still experiencing permission issues, try running these commands on your server:</p>";
echo "<pre>
icacls \"c:\\xampp\\htdocs\\Movieticketbooking\\uploads\\panoramas\" /grant Everyone:(OI)(CI)F
icacls \"c:\\xampp\\htdocs\\Movieticketbooking\\img\\panoramas\" /grant Everyone:(OI)(CI)F
icacls \"c:\\xampp\\htdocs\\Movieticketbooking\\logs\" /grant Everyone:(OI)(CI)F
</pre>";
?>