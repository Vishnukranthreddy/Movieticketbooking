<?php
// Database connection
$host = "sql12.freesqldatabase.com";
$username = "sql12784044";
$password = "Whcw9IFzSV";
$database = "sql12784044";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read SQL file
$sql = file_get_contents('../database/admin_updates.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "<h2>Database setup completed successfully!</h2>";
    echo "<p>The admin panel has been set up. You can now <a href='index.php'>login to the admin panel</a> using:</p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><strong>Important:</strong> Please change the default password after logging in for the first time.</p>";
} else {
    echo "Error executing SQL: " . $conn->error;
}

$conn->close();
?>