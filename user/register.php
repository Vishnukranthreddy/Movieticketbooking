<?php
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

// Database connection
$host = "dpg-d1gk4s7gi27c73brav8g-a.oregon-postgres.render.com";
$username = "showtime_select_user";
$password = "kbJAnSvfJHodYK7oDCaqaR7OvwlnJQi1";
$database = "showtime_select";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $new_username = $_POST['username']; // Assuming this is also the email for simplicity
    $new_password = $_POST['password'];
    $phone = $_POST['phone'];

    // Check if username/email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $new_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "Username/Email already exists. Please choose a different one.";
    } else {
        // Insert new user
        // Note: Storing plain text password for consistency with provided movie_db.sql.
        // In a real application, ALWAYS hash passwords using password_hash().
        $insert_stmt = $conn->prepare("INSERT INTO users (username, name, password, phone) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssss", $new_username, $name, $new_password, $phone); // new_password is plain text here

        if ($insert_stmt->execute()) {
            $success = "Registration successful! You can now log in.";
            // Optionally, log the user in directly after registration
            // $_SESSION['user_id'] = $conn->insert_id;
            // $_SESSION['user_username'] = $new_username;
            // $_SESSION['user_name'] = $name;
            // $_SESSION['user_phone'] = $phone;
            // header("Location: profile.php");
            // exit();
            header("Location: login.php?registered=true"); // Redirect to login page
            exit();
        } else {
            $error = "Error during registration: " . $conn->error;
        }
        $insert_stmt->close();
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Showtime Select</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS for 21stdev classic look -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #1a1a2e; /* Darker background */
            color: #e0e0e0; /* Light text */
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Full viewport height */
            padding: 20px;
        }
        .register-container {
            max-width: 500px;
            width: 100%;
            background-color: #0f3460; /* Dark blue for card */
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            padding: 40px;
            text-align: center;
        }
        .register-logo {
            margin-bottom: 30px;
        }
        .register-logo h1 {
            color: #e94560; /* Accent color for logo */
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        .register-logo p {
            color: #e0e0e0;
            font-size: 1.1rem;
        }
        .form-input {
            background-color: #16213e;
            border: 1px solid #0f3460;
            color: #e0e0e0;
            border-radius: 8px;
            padding: 12px 18px;
            width: 100%;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .form-input:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.5);
        }
        .btn-primary {
            background-color: #e94560;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            font-weight: bold;
            width: 100%;
            font-size: 1.1rem;
        }
        .btn-primary:hover {
            background-color: #b82e4a;
        }
        .error-message {
            color: #ef4444; /* Tailwind red-500 */
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .success-message {
            color: #34d399; /* Tailwind green-400 */
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .link-text {
            color: #8892b0; /* Light gray-blue for links */
            transition: color 0.3s ease;
        }
        .link-text:hover {
            color: #e94560;
        }
    </style>
</head>
<body class="antialiased">
    <div class="register-container">
        <div class="register-logo">
            <h1>Showtime Select</h1>
            <p>User Registration</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-4">
                <input type="text" name="name" placeholder="Your Full Name" class="form-input" required>
            </div>
            <div class="mb-4">
                <input type="email" name="username" placeholder="Email (Username)" class="form-input" required>
            </div>
            <div class="mb-4">
                <input type="password" name="password" placeholder="Password" class="form-input" required>
            </div>
            <div class="mb-6">
                <input type="tel" name="phone" placeholder="Phone Number" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Register</button>
        </form>

        <div class="mt-6 text-center text-gray-400">
            Already have an account? <a href="login.php" class="link-text font-semibold">Login Here</a>
        </div>
        <div class="mt-4 text-center">
            <a href="index.php" class="link-text inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
