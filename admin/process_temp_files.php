<?php
/**
 * Script to process temporary files and move them to their final destinations
 * This should be run periodically to ensure files are properly stored
 */

session_start();

// Include upload helper functions
require_once('upload_helper.php');

// Initialize debug info array
$debugInfo = [];
$debugInfo[] = "Starting temp file processing at " . date('Y-m-d H:i:s');

// Set up log file
$logFile = "../logs/temp_files_processing.log";
if (!file_exists("../logs/")) {
    mkdir("../logs/", 0777, true);
}

// Check if there are any temp files to process
if (!isset($_SESSION['temp_files']) || empty($_SESSION['temp_files'])) {
    $debugInfo[] = "No temporary files found in session.";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Temp Files Processing:\n" . implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
    echo "No temporary files to process.";
    exit;
}

$debugInfo[] = "Found " . count($_SESSION['temp_files']) . " temporary files to process.";

// Set up primary and fallback directories
$primaryDir = realpath("..") . "/img/panoramas/";
$fallbackDir = realpath("..") . "/uploads/panoramas/";

// Ensure directories exist and are writable
ensureDirectoryIsWritable($primaryDir, $debugInfo);
ensureDirectoryIsWritable($fallbackDir, $debugInfo);

// Database connection
$host = "sql12.freesqldatabase.com";
$username = "sql12784044";
$password = "Whcw9IFzSV";
$database = "sql12784044";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    $debugInfo[] = "Database connection failed: " . $conn->connect_error;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Temp Files Processing:\n" . implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
    echo "Database connection failed.";
    exit;
}

// Process each temp file
$processedFiles = [];
foreach ($_SESSION['temp_files'] as $index => $tempFilePath) {
    $debugInfo[] = "Processing temp file: " . $tempFilePath;
    
    // Check if the temp file exists
    if (!file_exists($tempFilePath)) {
        $debugInfo[] = "Temp file does not exist: " . $tempFilePath;
        $processedFiles[] = $index;
        continue;
    }
    
    // Generate a unique filename
    $uniqueFileName = uniqid() . "_" . basename($tempFilePath);
    $debugInfo[] = "Generated unique filename: " . $uniqueFileName;
    
    // Try to copy to primary directory first
    $finalPath = $primaryDir . $uniqueFileName;
    $debugInfo[] = "Attempting to copy to primary directory: " . $finalPath;
    
    $success = false;
    $finalRelativePath = "";
    
    if (copy($tempFilePath, $finalPath)) {
        $debugInfo[] = "File copied successfully to primary directory";
        $finalRelativePath = "img/panoramas/" . $uniqueFileName;
        $success = true;
    } else {
        $copyError = error_get_last();
        $debugInfo[] = "Failed to copy to primary directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
        
        // Try fallback directory
        $finalPath = $fallbackDir . $uniqueFileName;
        $debugInfo[] = "Attempting to copy to fallback directory: " . $finalPath;
        
        if (copy($tempFilePath, $finalPath)) {
            $debugInfo[] = "File copied successfully to fallback directory";
            $finalRelativePath = "uploads/panoramas/" . $uniqueFileName;
            $success = true;
        } else {
            $copyError = error_get_last();
            $debugInfo[] = "Failed to copy to fallback directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
        }
    }
    
    // If successfully copied, update database and delete temp file
    if ($success) {
        // Get the placeholder path that was stored in the database
        $placeholderPath = "uploads/panoramas/" . basename($tempFilePath);
        $debugInfo[] = "Placeholder path in database: " . $placeholderPath;
        
        // Update database to use the new path
        $stmt = $conn->prepare("UPDATE theater_halls SET hallPanoramaImg = ? WHERE hallPanoramaImg = ?");
        if ($stmt === false) {
            $debugInfo[] = "Failed to prepare database update: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $finalRelativePath, $placeholderPath);
            if ($stmt->execute()) {
                $debugInfo[] = "Database updated successfully. Rows affected: " . $stmt->affected_rows;
                
                // Delete the temp file
                if (unlink($tempFilePath)) {
                    $debugInfo[] = "Temp file deleted successfully";
                } else {
                    $debugInfo[] = "Failed to delete temp file: " . error_get_last()['message'];
                }
                
                $processedFiles[] = $index;
            } else {
                $debugInfo[] = "Failed to update database: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Remove processed files from session
foreach ($processedFiles as $index) {
    unset($_SESSION['temp_files'][$index]);
}

// Reindex the array
$_SESSION['temp_files'] = array_values($_SESSION['temp_files']);

$debugInfo[] = "Processed " . count($processedFiles) . " files. " . count($_SESSION['temp_files']) . " files remaining.";

// Log results
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Temp Files Processing:\n" . implode("\n", $debugInfo) . "\n\n", FILE_APPEND);

// Output results
echo "<h1>Temporary Files Processing</h1>";
echo "<pre>" . implode("\n", $debugInfo) . "</pre>";
echo "<p><a href='theater_halls.php'>Return to Theater Halls</a></p>";
?>