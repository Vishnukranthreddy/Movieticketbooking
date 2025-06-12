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
    // IMPORTANT: Log PHP's current working directory and user
    $debugInfo[] = "PHP Current Working Directory: " . getcwd();
    $debugInfo[] = "PHP Current User: " . get_current_user();
    $debugInfo[] = "PHP Script Owner: " . fileowner(__FILE__);
    $debugInfo[] = "PHP Version: " . PHP_VERSION;
    $debugInfo[] = "PHP OS: " . PHP_OS;
    $debugInfo[] = "Server Software: " . $_SERVER['SERVER_SOFTWARE'];
    
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
    
    // Try a completely different approach - use the system temp directory
    $tempDir = sys_get_temp_dir();
    $debugInfo[] = "System temp directory: " . $tempDir;
    
    // Create a subdirectory in the temp folder for our uploads
    $tempUploadDir = $tempDir . DIRECTORY_SEPARATOR . 'movie_uploads';
    $debugInfo[] = "Temp upload directory: " . $tempUploadDir;
    
    // Create the temp upload directory if it doesn't exist
    if (!file_exists($tempUploadDir)) {
        $debugInfo[] = "Creating temp upload directory...";
        if (!mkdir($tempUploadDir, 0777, true)) {
            $debugInfo[] = "Failed to create temp upload directory: " . error_get_last()['message'];
        } else {
            $debugInfo[] = "Temp upload directory created successfully";
        }
    }
    
    // Check if temp directory is writable
    if (is_writable($tempUploadDir)) {
        $debugInfo[] = "Temp directory is writable, using it for upload";
        $targetDir = $tempUploadDir . DIRECTORY_SEPARATOR;
        $relativePath = "img/panoramas/"; // Still use this for database storage
    } else {
        // Fall back to trying the original directories
        $debugInfo[] = "Temp directory not writable, trying primary directory: " . $primaryDir;
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
                $errorMessage = "No writable directories found. Please check server permissions.";
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
    
    // Try to upload the file using multiple methods
    $debugInfo[] = "Attempting to move uploaded file...";
    
    // Method 1: move_uploaded_file
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $debugInfo[] = "File moved successfully using move_uploaded_file()";
        
        // If we used the temp directory, we need to copy the file to the final destination
        if (strpos($targetDir, sys_get_temp_dir()) === 0) {
            $debugInfo[] = "File is in temp directory, copying to final destination...";
            
            // Try to copy to primary directory first
            $finalPath = $primaryDir . $uniqueFileName;
            $debugInfo[] = "Attempting to copy to primary directory: " . $finalPath;
            
            if (copy($targetFilePath, $finalPath)) {
                $debugInfo[] = "File copied successfully to primary directory";
                // Delete the temp file
                @unlink($targetFilePath);
                return $relativePath . $uniqueFileName;
            } else {
                $copyError = error_get_last();
                $debugInfo[] = "Failed to copy to primary directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
                
                // Try fallback directory
                $finalPath = $fallbackDir . $uniqueFileName;
                $debugInfo[] = "Attempting to copy to fallback directory: " . $finalPath;
                
                if (copy($targetFilePath, $finalPath)) {
                    $debugInfo[] = "File copied successfully to fallback directory";
                    // Delete the temp file
                    @unlink($targetFilePath);
                    return "uploads/panoramas/" . $uniqueFileName;
                } else {
                    $copyError = error_get_last();
                    $debugInfo[] = "Failed to copy to fallback directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
                    
                    // Just use the temp file as is
                    $debugInfo[] = "Using temp file as final destination";
                    return $relativePath . $uniqueFileName;
                }
            }
        } else {
            // We used one of the regular directories, so we're done
            return $relativePath . $uniqueFileName;
        }
    } else {
        $moveError = error_get_last();
        $debugInfo[] = "move_uploaded_file() failed: " . ($moveError ? $moveError['message'] : 'Unknown error');
        
        // Method 2: Try direct copy
        $debugInfo[] = "Attempting alternative copy method...";
        if (copy($file['tmp_name'], $targetFilePath)) {
            $debugInfo[] = "File copied successfully using copy()";
            
            // Same logic as above for temp directory
            if (strpos($targetDir, sys_get_temp_dir()) === 0) {
                $debugInfo[] = "File is in temp directory, copying to final destination...";
                
                // Try to copy to primary directory first
                $finalPath = $primaryDir . $uniqueFileName;
                $debugInfo[] = "Attempting to copy to primary directory: " . $finalPath;
                
                if (copy($targetFilePath, $finalPath)) {
                    $debugInfo[] = "File copied successfully to primary directory";
                    // Delete the temp file
                    @unlink($targetFilePath);
                    return $relativePath . $uniqueFileName;
                } else {
                    $copyError = error_get_last();
                    $debugInfo[] = "Failed to copy to primary directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
                    
                    // Try fallback directory
                    $finalPath = $fallbackDir . $uniqueFileName;
                    $debugInfo[] = "Attempting to copy to fallback directory: " . $finalPath;
                    
                    if (copy($targetFilePath, $finalPath)) {
                        $debugInfo[] = "File copied successfully to fallback directory";
                        // Delete the temp file
                        @unlink($targetFilePath);
                        return "uploads/panoramas/" . $uniqueFileName;
                    } else {
                        $copyError = error_get_last();
                        $debugInfo[] = "Failed to copy to fallback directory: " . ($copyError ? $copyError['message'] : 'Unknown error');
                        
                        // Just use the temp file as is
                        $debugInfo[] = "Using temp file as final destination";
                        return $relativePath . $uniqueFileName;
                    }
                }
            } else {
                // We used one of the regular directories, so we're done
                return $relativePath . $uniqueFileName;
            }
        } else {
            $copyError = error_get_last();
            $debugInfo[] = "copy() failed: " . ($copyError ? $copyError['message'] : 'Unknown error');
            
            // Method 3: Try file_put_contents with file_get_contents
            $debugInfo[] = "Attempting file_put_contents method...";
            $fileContents = @file_get_contents($file['tmp_name']);
            
            if ($fileContents !== false) {
                if (file_put_contents($targetFilePath, $fileContents)) {
                    $debugInfo[] = "File written successfully using file_put_contents()";
                    return $relativePath . $uniqueFileName;
                } else {
                    $putError = error_get_last();
                    $debugInfo[] = "file_put_contents() failed: " . ($putError ? $putError['message'] : 'Unknown error');
                }
            } else {
                $getError = error_get_last();
                $debugInfo[] = "file_get_contents() failed: " . ($getError ? $getError['message'] : 'Unknown error');
            }
            
            $errorMessage = "Failed to upload file after trying multiple methods. Please contact administrator.";
            $uploadOk = 0;
            return null;
        }
    }
}