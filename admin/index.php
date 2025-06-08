<?php
session_start();

// Check if already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "movie_db"; // Ensured to be movie_db
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST["username"];
    $input_password = $_POST["password"];

    // Use prepared statements to prevent SQL injection
    $query = $conn->prepare("SELECT adminID, username, password, fullName, roleID FROM admin_users WHERE username = ? AND status = 'active' LIMIT 1");
    $query->bind_param("s", $input_username);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        // Verify the password (assuming it's hashed in the database as per combined SQL)
        if (password_verify($input_password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['adminID'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['fullName'];
            $_SESSION['admin_role'] = $admin['roleID']; // IMPORTANT: Store the roleID

            // Update last login time
            $updateQuery = $conn->prepare("UPDATE admin_users SET lastLogin = NOW() WHERE adminID = ?");
            $updateQuery->bind_param("i", $admin['adminID']);
            $updateQuery->execute();
            $updateQuery->close();

            // Redirect to dashboard
            $_SESSION['just_logged_in'] = true;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    $query->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Showtime Select</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css">
    <link rel="icon" type="image/png" href="../img/sslogo.jpg">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo h1 {
            color: #e83e8c;
            font-weight: bold;
        }
        .login-logo p {
            color: #6c757d;
        }
        .login-form .form-control {
            height: 45px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .login-form .btn-primary {
            background-color: #e83e8c;
            border-color: #e83e8c;
            height: 45px;
            font-weight: bold;
            border-radius: 4px;
        }
        .login-form .btn-primary:hover {
            background-color: #d33076;
            border-color: #d33076;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #6c757d;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #e83e8c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <h1>Showtime Select</h1>
                <p>Admin Panel</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form class="login-form" method="post" action="">
                <div class="form-group">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </div>
            </form>
            
            <div class="back-link">
                <a href="../user/index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
