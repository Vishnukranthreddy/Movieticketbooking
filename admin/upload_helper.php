<?php
/**
 * Helper functions for file uploads
 * This file contains functions to assist with file uploads and directory permissions
 */

/**
 * Ensures a directory exists and is writable
 * 
 * @param string $dir The directory path to check/create
 * @param array $debugInfo Reference to an array for logging debug information
 * @return bool True if directory is writable, false otherwise
 */
function ensureDirectoryIsWritable($dir, &$debugInfo) {
    // Create directory if it doesn't exist
    if (!file_exists($dir)) {
        $debugInfo[] = "Directory does not exist, creating: " . $dir;
        if (!mkdir($dir, 0777, true)) {
            $debugInfo[] = "Failed to create directory: " . error_get_last()['message'];
            return false;
        }
        $debugInfo[] = "Directory created successfully";
    } else {
        $debugInfo[] = "Directory exists: " . $dir;
    }
    
    // Check if directory is writable
    if (!is_writable($dir)) {
        $debugInfo[] = "Directory not writable, attempting to set permissions...";
        
        // Try PHP's chmod
        @chmod($dir, 0777);
        
        // If still not writable, try system commands
        if (!is_writable($dir)) {
            $debugInfo[] = "PHP chmod failed, trying system commands...";
            
            // On Windows
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Remove read-only attribute
                $cmd = 'attrib -R "' . $dir . '" /S /D';
                $debugInfo[] = "Running Windows command: $cmd";
                @exec($cmd, $output, $returnVar);
                $debugInfo[] = "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)");
                
                // Grant full permissions to Everyone
                $cmd = 'icacls "' . $dir . '" /grant Everyone:(OI)(CI)F';
                $debugInfo[] = "Running Windows command: $cmd";
                @exec($cmd, $output, $returnVar);
                $debugInfo[] = "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)");
            } else {
                // On Linux/Unix
                $cmd = 'chmod -R 777 "' . $dir . '"';
                $debugInfo[] = "Running Unix command: $cmd";
                @exec($cmd, $output, $returnVar);
                $debugInfo[] = "Command result: " . ($returnVar === 0 ? "Success" : "Failed (code: $returnVar)");
            }
        }
    }
    
    // Final check
    if (is_writable($dir)) {
        $debugInfo[] = "Directory is writable: " . $dir;
        return true;
    } else {
        $debugInfo[] = "Directory is still not writable after all attempts: " . $dir;
        return false;
    }
}

/**
 * Handles file upload with fallback options
 * 
 * @param array $file The $_FILES array element for the uploaded file
 * @param string $primaryDir The primary directory to upload to
 * @param string $fallbackDir The fallback directory to use if primary fails
 * @param array $debugInfo Reference to an array for logging debug information
 * @param string $errorMessage Reference to error message string
 * @param int $uploadOk Reference to upload status flag
 * @return string|null The relative path to the uploaded file or null on failure
 */
function handleFileUpload($file, $primaryDir, $fallbackDir, &$debugInfo, &$errorMessage, &$uploadOk) {
    // Check if file exists and was uploaded properly
    if (!isset($file) || $file['error'] != UPLOAD_ERR_OK) {
        $debugInfo[] = "File upload error: " . $file['error'];
        $errorMessage = "File upload error code: " . $file['error'];
        $uploadOk = 0;
        return null;
    }
    
    $debugInfo[] = "Upload temp file: " . $file['tmp_name'];
    $debugInfo[] = "Temp file exists: " . (file_exists($file['tmp_name']) ? 'Yes' : 'No');
    $debugInfo[] = "Temp file readable: " . (is_readable($file['tmp_name']) ? 'Yes' : 'No');
    
    // Try primary directory first
    $debugInfo[] = "Trying primary directory: " . $primaryDir;
    $targetDir = $primaryDir;
    $relativePath = "img/panoramas/"; // Path relative to project root
    
    if (!ensureDirectoryIsWritable($primaryDir, $debugInfo)) {
        $debugInfo[] = "Primary directory not writable, trying fallback...";
        
        // Try fallback directory
        if (ensureDirectoryIsWritable($fallbackDir, $debugInfo)) {
            $debugInfo[] = "Using fallback directory for upload";
            $targetDir = $fallbackDir;
            $relativePath = "uploads/panoramas/"; // Path relative to project root
        } else {
            $errorMessage = "Neither primary nor fallback directories are writable. Please check server permissions.";
            $debugInfo[] = "All directory options exhausted";
            
            // Add server environment info for debugging
            $debugInfo[] = "Server OS: " . PHP_OS;
            $debugInfo[] = "Server user: " . get_current_user();
            $debugInfo[] = "PHP version: " . PHP_VERSION;
            $debugInfo[] = "Server software: " . $_SERVER['SERVER_SOFTWARE'];
            
            $uploadOk = 0;
            return null;
        }
    }
    
    // Clean the filename to remove problematic characters
    $fileName = basename($file['name']);
    $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName); // Replace problematic chars with underscore
    $uniqueFileName = uniqid() . "_" . $fileName;
    $targetFilePath = $targetDir . $uniqueFileName;
    $debugInfo[] = "Target file path: " . $targetFilePath;
    
    // Validate file
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $debugInfo[] = "File type: " . $fileType;
    
    $check = @getimagesize($file['tmp_name']);
    if($check === false) { 
        $errorMessage = "File is not a valid image."; 
        $debugInfo[] = "File validation failed: Not a valid image";
        $uploadOk = 0;
        return null;
    }
    
    if($file['size'] > 15000000) { 
        $errorMessage = "Sorry, file is too large (max 15MB)."; 
        $debugInfo[] = "File validation failed: File too large (" . $file['size'] . " bytes)";
        $uploadOk = 0;
        return null;
    }
    
    if($fileType != "jpg" && $fileType != "png" && $fileType != "jpeg" ) { 
        $errorMessage = "Sorry, only JPG, JPEG, and PNG files are allowed."; 
        $debugInfo[] = "File validation failed: Invalid file type";
        $uploadOk = 0;
        return null;
    }
    
    // Try to upload the file
    $debugInfo[] = "Attempting to move uploaded file...";
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $debugInfo[] = "File moved successfully using move_uploaded_file()";
        return $relativePath . $uniqueFileName;
    } else {
        $moveError = error_get_last();
        $debugInfo[] = "move_uploaded_file() failed: " . ($moveError ? $moveError['message'] : 'Unknown error');
        
        // Try alternative method - direct copy
        $debugInfo[] = "Attempting alternative copy method...";
        if (copy($file['tmp_name'], $targetFilePath)) {
            $debugInfo[] = "File copied successfully using copy()";
            return $relativePath . $uniqueFileName;
        } else {
            $copyError = error_get_last();
            $debugInfo[] = "copy() failed: " . ($copyError ? $copyError['message'] : 'Unknown error');
            $errorMessage = "Failed to upload file. Please contact administrator.";
            $uploadOk = 0;
            return null;
        }
    }
}