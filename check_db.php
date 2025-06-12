<?php
// Database connection
$host = "sql12.freesqldatabase.com";
$username = "sql12784044";
$password = "Whcw9IFzSV";
$database = "sql12784044"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Check</h1>";

// Check theater_halls table
echo "<h2>Theater Halls</h2>";
$result = $conn->query("SELECT hallID, hallName, hallPanoramaImg FROM theater_halls LIMIT 10");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Hall ID</th><th>Hall Name</th><th>Panorama Image</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['hallID'] . "</td>";
        echo "<td>" . $row['hallName'] . "</td>";
        echo "<td>" . ($row['hallPanoramaImg'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check theaters table
echo "<h2>Theaters</h2>";
$result = $conn->query("SELECT theaterID, theaterName, theaterPanoramaImg FROM theaters LIMIT 10");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Theater ID</th><th>Theater Name</th><th>Panorama Image</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['theaterID'] . "</td>";
        echo "<td>" . $row['theaterName'] . "</td>";
        echo "<td>" . ($row['theaterPanoramaImg'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>