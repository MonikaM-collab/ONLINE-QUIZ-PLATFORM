<?php
session_start();

// Database connection (adjust credentials as needed) - USING PDO FOR CONSISTENCY WITH LOG.PHP
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quiz_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Something went wrong with the database connection. Please try again later.");
}

// Initialize user data
$user_data = null;

if (isset($_SESSION['user_id'])) {
    $loggedInUserId = $_SESSION['user_id'];

    // --- Fetch User Profile Data ---
    try {
        $stmt_user = $pdo->prepare("SELECT id, username, email, avatar, score, rank FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0; // Assuming this column might exist
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            $user_data['avatar'] = $user_data['avatar'] ?? 'https://via.placeholder.com/50/FFDAB9/004d4d?text=' . substr($user_data['username'], 0, 1);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        $user_data = null;
    }
}

// Sample user data if no session
if (!$user_data) {
    $user_data = [
        'id' => null,
        'username' => 'Guest',
        'email' => 'guest@quizit.com',
        'avatar' => 'https://via.placeholder.com/50/D9534F/FFFFFF?text=G',
        'score' => 0,
        'rank' => 'N/A',
        'quizzes_completed' => 0
    ];
}

// --- Function to safely redirect ---
function redirect_to_page($page, $messageType = '', $message = '', $formToDisplay = '') {
    $params = [];
    if (!empty($messageType)) {
        $params[$messageType] = urlencode($message);
    }
    if (!empty($formToDisplay)) {
        $params['form'] = urlencode($formToDisplay);
    }
    $redirectUrl = $page;
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    header("Location: " . $redirectUrl);
    exit();
}

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Signup
    if (isset($_POST['signup'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $role = isset($_POST['role']) ? $_POST['role'] : 'user';

        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            redirect_to_page('logre.php', 'error', 'All fields are required.', 'signup');
        }
        if (strlen($password) < 6) {
            redirect_to_page('logre.php', 'error', 'Password must be at least 6 characters long.', 'signup');
        }
        if ($password !== $confirmPassword) {
            redirect_to_page('logre.php', 'error', 'Passwords do not match.', 'signup');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect_to_page('logre.php', 'error', 'Please enter a valid email address.', 'signup');
        }
        if (!in_array($role, ['user', 'admin'])) {
            redirect_to_page('logre.php', 'error', 'Please select a valid role.', 'signup');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            redirect_to_page('logre.php', 'error', 'Username or email already exists. Please choose another.', 'signup');
        }

        $plainPassword = $password; // Storing plain password as per your original code

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $plainPassword, $role])) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['role'] = $role;
            
            if ($role === 'admin') {
                redirect_to_page('admin.php');
            } else {
                redirect_to_page('logre.php?welcome=1');
            }
        } else {
            redirect_to_page('logre.php', 'error', 'Error during signup. Please try again.', 'signup');
        }
    }
    // Handle Login
    else if (isset($_POST['login'])) {
        $usernameEmail = trim($_POST['username']);
        $password = $_POST['password'];
        $role = isset($_POST['role']) ? $_POST['role'] : ''; // Default to empty string if not set

        // Initialize error flags for more specific messages
        $isUsernameEmailEmpty = empty($usernameEmail);
        $isPasswordEmpty = empty($password);
        $isRoleEmptyOrInvalid = !in_array($role, ['user', 'admin']);

        // Immediately check for empty fields and invalid role selection
        if ($isUsernameEmailEmpty || $isPasswordEmpty || $isRoleEmptyOrInvalid) {
            $errorMessages = [];
            if ($isUsernameEmailEmpty) {
                $errorMessages[] = 'Username or Email is required.';
            }
            if ($isPasswordEmpty) {
                $errorMessages[] = 'Password is required.';
            }
            if ($isRoleEmptyOrInvalid) {
                $errorMessages[] = 'Role is required or invalid.';
            }
            redirect_to_page('logre.php', 'error', implode(' ', $errorMessages), 'login');
        }

        // Proceed with database check if basic validation passes
        $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$usernameEmail, $usernameEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $loginErrors = [];

        if (!$user) {
            // User not found by username or email
            // Determine if it was an email format or just a non-existent username
            if (filter_var($usernameEmail, FILTER_VALIDATE_EMAIL)) {
                $loginErrors[] = 'Invalid email.';
            } else {
                $loginErrors[] = 'Invalid username.';
            }
        } else {
            // User found, now check password and role
            if ($password !== $user['password']) { // Comparing plain passwords as per your original code
                $loginErrors[] = 'Invalid password.';
            }
            if ($role !== $user['role']) {
                $loginErrors[] = 'Invalid role.';
            }
        }

        if (!empty($loginErrors)) {
            // If there are specific login errors, combine and redirect
            redirect_to_page('logre.php', 'error', implode(' ', $loginErrors), 'login');
        } else {
            // All credentials match
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                redirect_to_page('admin.php');
            } else {
                redirect_to_page('logre.php?welcome=1');
            }
        }
    }
    // Handle Start Quiz button
    else if (isset($_POST['start_quiz'])) {
        if (isset($_SESSION['username'])) {
            header("Location: quiz.php");
            exit();
        } else {
            redirect_to_page('logre.php', 'error', 'Please log in first.', 'login');
        }
    }
}
// Handle Logout
else if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $_SESSION = array();
    session_destroy();
    redirect_to_page('logre.php');
}

$showWelcomePage = false;
if (isset($_SESSION['username']) && isset($_GET['welcome']) && $_SESSION['role'] !== 'admin') {
    $showWelcomePage = true;
}

$errorMessage = '';
$successMessage = '';
$displayForm = 'login';

if (!$showWelcomePage) {
    if (isset($_GET['error'])) {
        // Corrected line: urldecode() to convert '+' back to spaces
        $errorMessage = htmlspecialchars(urldecode($_GET['error'])); 
        if (isset($_GET['form'])) {
            $displayForm = htmlspecialchars($_GET['form']);
        }
    } elseif (isset($_GET['success'])) {
        if ($_GET['success'] === 'signup') {
            $successMessage = "Account created successfully! Please log in.";
            $displayForm = 'login';
        }
    }
}

$pageTitle = ($displayForm === 'signup') ? "Join QUIZIT!" : "Welcome Back!";
$lockIcon = ($displayForm === 'signup') ? "🚀" : "🔐";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZIT - <?php echo $showWelcomePage ? 'Welcome' : 'Login/Signup'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* General Styling & Background */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            height: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%,rgb(177, 156, 197) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden; /* Prevent body from scrolling */
        }

        /* Animated Background from log.php */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
        }

        .floating-shapes {
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 12s ease-in-out infinite;
        }

        .floating-shapes:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; width: 80px; height: 80px; }
        .floating-shapes:nth-child(2) { top: 20%; right: 10%; animation-delay: 2s; width: 120px; height: 120px; }
        .floating-shapes:nth-child(3) { bottom: 10%; left: 20%; animation-delay: 4s; width: 60px; height: 60px; }
        .floating-shapes:nth-child(4) { bottom: 20%; right: 20%; animation-delay: 6s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }

        /* Header (Consistent with log.php) */
        .header {
            position: relative; /* Changed from fixed */
            flex-shrink: 0; /* Prevent header from shrinking */
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .logo:hover {
            transform: scale(1.05);
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.8);
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            border-radius: 2px;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .logo:hover::after {
            transform: scaleX(1);
        }

        .nav { 
            display: flex; 
            gap: 2rem; 
            /* Added for centering the nav items */
            flex-grow: 1; 
            justify-content: center; 
        }
        .nav-item {
            padding: 0.8rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            transition: left 0.5s ease;
        }
        .nav-item:hover::before { left: 0; }
        .nav-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
        }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Profile Section (Removed) */
        /* .profile-section { display: flex; align-items: center; gap: 1rem; position: relative; } */
        /* .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
            background-color: rgba(255,255,255,0.2);
        } */
        /* .profile-avatar:hover {
            transform: scale(1.1);
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        } */
        /* .profile-info { color: #fff; cursor: pointer; transition: all 0.3s ease; text-align: right; } */
        /* .profile-info:hover { transform: translateY(-2px); } */
        /* .profile-username { font-weight: bold; font-size: 1.1rem; } */
        /* .profile-score { font-size: 0.9rem; opacity: 0.8; } */
        /* .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1rem;
            min-width: 250px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            margin-top: 1rem;
            color: #333;
        } */
        /* .profile-dropdown.active { transform: translateY(0); opacity: 1; visibility: visible; } */
        /* .profile-dropdown h3 { color: #333; margin-bottom: 0.5rem; text-align: center; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; } */
        /* .profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; } */
        /* .stat-item { background: rgba(102, 126, 234, 0.1); padding: 0.8rem; border-radius: 10px; text-align: center; color: #333; } */
        /* .stat-value { font-size: 1.5rem; font-weight: bold; color: #667eea; } */
        /* .stat-label { font-size: 0.8rem; opacity: 0.7; } */
        .cta-button {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: #fff;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            border: none;
        }
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }

        /* Main Content Area - NEW for scrolling */
        .main-content-area {
            flex-grow: 1; /* Take remaining vertical space */
            overflow-y: auto; /* Enable vertical scrolling ONLY for this container */
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Align content to the top */
        }
        
        /* Custom Scrollbar */
        .main-content-area::-webkit-scrollbar {
            width: 8px;
        }
        .main-content-area::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .main-content-area::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        .main-content-area::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Extraordinary Design for the Container */
        .container {
            background: rgba(10, 5, 25, 0.25); /* Darker, more contrasted background */
            backdrop-filter: blur(15px); /* Increased blur for better glass effect */
            border: 1px solid rgba(255, 255, 255, 0.15); /* Subtler border */
            border-radius: 25px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            padding: 40px 50px;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at top left, rgba(255,107,107,0.5), transparent 40%),
                        radial-gradient(circle at bottom right, rgba(78,205,196,0.5), transparent 40%);
            z-index: -1;
            border-radius: 25px;
            opacity: 0;
            transition: opacity 0.5s ease;
            animation: pulse-glow 8s infinite alternate;
        }
        
        @keyframes pulse-glow {
            from { opacity: 0; }
            to { opacity: 0.3; }
        }

        .container:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.4);
        }

        /* Welcome Container - The Star of the Show */
        .welcome-container {
            max-width: 700px;
            padding: 60px;
            text-align: center;
            animation: fadeInFromBottom 1s ease-out forwards;
            /* NEW: Professional dark gradient for high contrast */
            background: linear-gradient(145deg, rgba(30, 25, 60, 0.4), rgba(15, 10, 35, 0.3));
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes fadeInFromBottom {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .welcome-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #f8ffae, #43c6ac);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 40px rgba(248, 255, 174, 0.3);
        }
        
        .welcome-subtitle {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 2.5rem;
            font-weight: 300;
        }
        
        .welcome-actions {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .welcome-actions .cta-button {
            font-size: 1.2rem;
            padding: 1rem 2.5rem;
        }

        .welcome-actions .logout-button {
            background: transparent;
            border: 2px solid rgba(255, 107, 107, 0.8);
            color: rgba(255, 107, 107, 0.9);
        }
        .welcome-actions .logout-button:hover {
            background: rgba(255, 107, 107, 0.8);
            color: #fff;
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }


        /* Form Styling */
        h1 {
            margin-bottom: 30px;
            color: #fff;
            font-size: 2.5em;
            font-weight: 700;
            text-shadow: 2px 3px 7px rgba(0, 0, 0, 0.3);
        }
        h1 .lock-icon { display: inline-block; margin-left: 10px; font-size: 0.9em; filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.2)); }

        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #e0e0e0; font-size: 1em; }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 1em;
            color: #333; /* Changed to a dark color for better contrast on white background */
            background-color: #fff; /* Changed to white */
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        input[type="password"] {
            padding-right: 45px; /* Make space for the icon */
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666; /* Adjusted color for visibility on white background */
            z-index: 2;
        }
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: #4ecdc4;
            box-shadow: 0 0 0 4px rgba(78, 205, 196, 0.3);
            outline: none;
            background-color: #fff; /* Ensure it stays white on focus */
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23333' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); /* Changed arrow color for white background */
            background-position: right 15px center;
            background-repeat: no-repeat;
            background-size: 16px;
        }

        select option { background-color: #667eea; color: #fff; }

        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }
        button[type="submit"]:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4);
        }

        .toggle-form { margin-top: 25px; font-size: 0.95em; color: #e0e0e0; }
        .toggle-form a { color: #4ecdc4; text-decoration: none; font-weight: 600; }
        .toggle-form a:hover { text-decoration: underline; }

        .error-message, .success-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            display: none;
            animation: fadeIn 0.4s ease-out;
        }
        .error-message { color: #ffdddd; background-color: rgba(255, 99, 71, 0.2); border: 1px solid #ff6b6b; }
        .success-message { color: #ddffdd; background-color: rgba(92, 184, 92, 0.2); border: 1px solid #5cb85c; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; padding: 1rem; }
            .nav { gap: 1rem; flex-wrap: wrap; justify-content: center; }
            .logo { font-size: 2rem; }
            .container { padding: 30px; margin: 15px; }
            .welcome-container { padding: 40px 30px; }
            h1, .welcome-title { font-size: 2.2em; }
            .welcome-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
    </div>

    <header class="header">
        <div class="logo">QUIZIT</div>

        <nav class="nav">
            <a href="log.php" class="nav-item">Home</a>
            <a href="leader.php" class="nav-item">Leaderboard</a>
        </nav>
        </header>

    <main class="main-content-area">
        <?php if ($showWelcomePage): ?>
            <div class="container welcome-container">
                <h1 class="welcome-title">Welcome to the Quiz, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p class="welcome-subtitle">Your next challenge awaits. Are you ready to prove your knowledge?</p>
                
                <div class="welcome-actions">
                    <form method="POST" action="logre.php" style="display: inline;">
                        <button type="submit" name="start_quiz" class="cta-button">Start New Quiz</button>
                    </form>
                    <a href="logre.php?action=logout" class="cta-button logout-button">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <div class="container">
                <h1><?php echo $pageTitle; ?> <span class="lock-icon"><?php echo $lockIcon; ?></span></h1>

                <div id="loginForm" style="display: <?php echo ($displayForm === 'login' || !empty($successMessage)) ? 'block' : 'none'; ?>;">
                    <?php if (!empty($errorMessage) && $displayForm === 'login') : ?>
                        <div class="error-message" style="display: block;"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($successMessage) && $displayForm === 'login') : ?>
                        <div class="success-message" style="display: block;"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <form id="actualLoginForm" action="logre.php" method="POST">
                        <input type="hidden" name="login" value="1">
                        <div class="form-group">
                            <label for="loginUsernameEmail">Username or Email</label>
                            <input type="text" id="loginUsernameEmail" name="username" placeholder="Enter username or email">
                        </div>
                        <div class="form-group">
                            <label for="loginPassword">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="loginPassword" name="password" placeholder="********">
                                <span class="toggle-password"></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="loginRole">Role</label>
                            <select id="loginRole" name="role">
                                <option value="" disabled selected>Select your role</option>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit">Login</button>
                    </form>
                    <div class="toggle-form">
                        Don't have an account? <a href="#" id="showSignup">Sign Up</a>
                    </div>
                </div>

                <div id="signupForm" style="display: <?php echo ($displayForm === 'signup') ? 'block' : 'none'; ?>;">
                    <?php if (!empty($errorMessage) && $displayForm === 'signup') : ?>
                        <div class="error-message" style="display: block;"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    <form id="actualSignupForm" action="logre.php" method="POST">
                        <input type="hidden" name="signup" value="1">
                        <div class="form-group">
                            <label for="signupUsername">Username</label>
                            <input type="text" id="signupUsername" name="username" placeholder="Choose a username">
                        </div>
                        <div class="form-group">
                            <label for="signupEmail">Email</label>
                            <input type="email" id="signupEmail" name="email" placeholder="your@example.com">
                        </div>
                        <div class="form-group">
                            <label for="signupPassword">Password (min. 6 characters)</label>
                             <div class="password-wrapper">
                                <input type="password" id="signupPassword" name="password" placeholder="********">
                                <span class="toggle-password"></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirmPassword" name="confirm_password" placeholder="********">
                                <span class="toggle-password"></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="signupRole">Role</label>
                            <select id="signupRole" name="role">
                                <option value="" disabled selected>Select your role</option>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit">Create Account</button>
                    </form>
                    <div class="toggle-form">
                        Already have an account? <a href="#" id="showLogin">Login</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Profile dropdown functionality (Removed)
        // function toggleProfileDropdown() {
        //     document.getElementById('profileDropdown').classList.toggle('active');
        // }

        // Close dropdown when clicking outside (Modified to remove profile dropdown specific listener)
        document.addEventListener('click', function(event) {
            // No longer need to check for profileSection or dropdown here
        });

        // Form toggling logic
        document.addEventListener('DOMContentLoaded', () => {
            const loginFormDiv = document.getElementById('loginForm');
            const signupFormDiv = document.getElementById('signupForm');
            const showSignupLink = document.getElementById('showSignup');
            const showLoginLink = document.getElementById('showLogin');
            const mainHeading = document.querySelector('.container h1');
            const lockIcon = document.querySelector('.container h1 .lock-icon');

            function updateHeading(isSignup) {
                if (mainHeading && lockIcon) {
                    const text = isSignup ? "Join QUIZIT!" : "Welcome Back!";
                    const icon = isSignup ? "🚀" : "🔐";
                    mainHeading.innerHTML = `${text} <span class="lock-icon">${icon}</span>`;
                }
            }

            if (showSignupLink) {
                showSignupLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (loginFormDiv) loginFormDiv.style.display = 'none';
                    if (signupFormDiv) signupFormDiv.style.display = 'block';
                    document.querySelectorAll('.error-message, .success-message').forEach(el => el.style.display = 'none');
                    updateHeading(true);
                    history.replaceState(null, '', window.location.pathname); // Clear URL params
                });
            }

            if (showLoginLink) {
                showLoginLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (signupFormDiv) signupFormDiv.style.display = 'none';
                    if (loginFormDiv) loginFormDiv.style.display = 'block';
                    document.querySelectorAll('.error-message, .success-message').forEach(el => el.style.display = 'none');
                    updateHeading(false);
                    history.replaceState(null, '', window.location.pathname); // Clear URL params
                });
            }

            // --- NEW: Password Visibility Toggle ---
            const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
            const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" x2="22" y1="2" y2="22"></line></svg>`;

            document.querySelectorAll('.toggle-password').forEach(toggle => {
                toggle.innerHTML = eyeIcon; // Set initial icon
                toggle.addEventListener('click', () => {
                    const passwordInput = toggle.previousElementSibling;
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        toggle.innerHTML = eyeOffIcon;
                    } else {
                        passwordInput.type = 'password';
                        toggle.innerHTML = eyeIcon;
                    }
                });
            });
        });
    </script>
</body>
</html>