<?php
echo "<h1>Panorama Files Check</h1>";

// Function to check directory and list files
function checkDirectory($dir) {
    echo "<h2>Checking directory: $dir</h2>";
    
    if (!file_exists($dir)) {
        echo "<p style='color:red'>Directory does not exist!</p>";
        return;
    }
    
    if (!is_readable($dir)) {
        echo "<p style='color:red'>Directory is not readable!</p>";
        return;
    }
    
    echo "<p style='color:green'>Directory exists and is readable.</p>";
    
    // List files in the directory
    $files = scandir($dir);
    if (count($files) <= 2) { // Only . and .. entries
        echo "<p>Directory is empty (no files).</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>Filename</th><th>Size</th><th>Last Modified</th><th>Readable</th><th>Preview</th></tr>";
        
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $filePath = $dir . '/' . $file;
                $fileSize = filesize($filePath);
                $lastModified = date("Y-m-d H:i:s", filemtime($filePath));
                $isReadable = is_readable($filePath) ? "Yes" : "No";
                
                echo "<tr>";
                echo "<td>$file</td>";
                echo "<td>$fileSize bytes</td>";
                echo "<td>$lastModified</td>";
                echo "<td>$isReadable</td>";
                
                // For image files, show a small preview
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);
                    echo "<td><img src='$relativePath' width='100' height='auto'></td>";
                } else {
                    echo "<td>Not an image</td>";
                }
                
                echo "</tr>";
            }
        }
        
        echo "</table>";
    }
}

// Check the main panoramas directory
checkDirectory(__DIR__ . '/img/panoramas');

// Check the fallback directory
checkDirectory(__DIR__ . '/uploads/panoramas');

// Check if the system temp directory is being used
$tempDir = sys_get_temp_dir() . '/movie_uploads';
if (file_exists($tempDir)) {
    checkDirectory($tempDir);
} else {
    echo "<h2>Temp Directory: $tempDir</h2>";
    echo "<p>Directory does not exist (not created yet).</p>";
}

// Check for any panorama images in the database
echo "<h2>Database Panorama Paths</h2>";

// Database connection
$host = "sql12.freesqldatabase.com";
$username = "sql12784044";
$password = "Whcw9IFzSV";
$database = "sql12784044";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "<p style='color:red'>Database connection failed: " . $conn->connect_error . "</p>";
} else {
    // Check theater halls
    $result = $conn->query("SELECT hallID, hallName, hallPanoramaImg FROM theater_halls WHERE hallPanoramaImg IS NOT NULL");
    if ($result) {
        if ($result->num_rows > 0) {
            echo "<h3>Hall Panoramas</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Hall ID</th><th>Hall Name</th><th>Panorama Path</th><th>File Exists</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                $panoramaPath = $row['hallPanoramaImg'];
                $fullPath = __DIR__ . '/' . $panoramaPath;
                $fileExists = file_exists($fullPath) ? "Yes" : "No";
                $fileExistsColor = $fileExists == "Yes" ? "green" : "red";
                
                echo "<tr>";
                echo "<td>" . $row['hallID'] . "</td>";
                echo "<td>" . $row['hallName'] . "</td>";
                echo "<td>" . $panoramaPath . "</td>";
                echo "<td style='color:$fileExistsColor'>" . $fileExists . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No hall panorama images found in database.</p>";
        }
    } else {
        echo "<p style='color:red'>Error querying hall panoramas: " . $conn->error . "</p>";
    }
    
    // Check theaters
    $result = $conn->query("SELECT theaterID, theaterName, theaterPanoramaImg FROM theaters WHERE theaterPanoramaImg IS NOT NULL");
    if ($result) {
        if ($result->num_rows > 0) {
            echo "<h3>Theater Panoramas</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Theater ID</th><th>Theater Name</th><th>Panorama Path</th><th>File Exists</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                $panoramaPath = $row['theaterPanoramaImg'];
                $fullPath = __DIR__ . '/' . $panoramaPath;
                $fileExists = file_exists($fullPath) ? "Yes" : "No";
                $fileExistsColor = $fileExists == "Yes" ? "green" : "red";
                
                echo "<tr>";
                echo "<td>" . $row['theaterID'] . "</td>";
                echo "<td>" . $row['theaterName'] . "</td>";
                echo "<td>" . $panoramaPath . "</td>";
                echo "<td style='color:$fileExistsColor'>" . $fileExists . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No theater panorama images found in database.</p>";
        }
    } else {
        echo "<p style='color:red'>Error querying theater panoramas: " . $conn->error . "</p>";
    }
    
    $conn->close();
}
?>