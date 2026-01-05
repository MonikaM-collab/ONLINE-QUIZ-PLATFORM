<?php
session_start();

// Database connection (adjust credentials as needed)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quiz_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log the error instead of dying in production
    error_log("Database connection failed: " . $e->getMessage());
    // Display a user-friendly message
    die("Something went wrong with the database connection. Please try again later.");
}

// Initialize user data and quiz results
$user_data = null;
$user_quiz_results = [];

if (isset($_SESSION['user_id'])) {
    $loggedInUserId = $_SESSION['user_id'];

    // --- Fetch User Profile Data ---
    try {
        $stmt_user = $pdo->prepare("SELECT id, username, email, avatar, score, rank FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // Ensure all expected keys are set, providing defaults if they were not fetched from DB
        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0;
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            $user_data['avatar'] = $user_data['avatar'] ?? 'https://via.placeholder.com/50';
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        $user_data = null; // Reset user data on error
    }

    // --- Fetch User Quiz Results ---
    try {
        $stmt_results = $pdo->prepare("
            SELECT
                r.quiz_topic,
                r.total_questions,
                r.score,
                r.timestamp -- Assuming you have a timestamp column for ordering
            FROM
                results r
            WHERE
                r.user_id = :user_id
            ORDER BY
                r.timestamp DESC
        ");
        $stmt_results->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
        $stmt_results->execute();
        $user_quiz_results = $stmt_results->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching quiz results: " . $e->getMessage());
        $user_quiz_results = []; // Ensure empty on error
    }

}

// Sample user data if no session (for demo/not logged in)
// This ensures that even if no user is logged in, there's default data to display
// without "Undefined array key" warnings.
if (!$user_data) {
    $user_data = [
        'id' => null, // No ID for a guest/demo user
        'username' => 'Guest',
        'email' => 'guest@quizit.com',
        'avatar' => 'https://via.placeholder.com/50',
        'score' => 0,
        'rank' => 'N/A',
        'quizzes_completed' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZIT - Ultimate Quiz Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #fff; /* Default text color for body */
        }

        /* Animated Background */
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
            animation: float 6s ease-in-out infinite;
        }

        .floating-shapes:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-shapes:nth-child(2) { top: 20%; right: 10%; animation-delay: 1s; }
        .floating-shapes:nth-child(3) { bottom: 10%; left: 20%; animation-delay: 2s; }
        .floating-shapes:nth-child(4) { bottom: 20%; right: 20%; animation-delay: 3s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between; /* Keeps logo to the left and pushes nav/profile (if re-added) to ends */
            align-items: center;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Logo */
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

        /* Navigation */
        .nav {
            display: flex;
            gap: 2rem;
            flex-grow: 1; /* Allows the nav to take up available space */
            justify-content: center; /* This will center the nav items within the available space */
            white-space: nowrap; /* Added to prevent Leaderboard wrapping */
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

        .nav-item:hover::before {
            left: 0;
        }

        .nav-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.8);
        }

        /* Main Content */
        .main-content {
            margin-top: 100px;
            padding: 2rem;
            text-align: center;
        }

        .hero-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 4rem 2rem;
            margin: 2rem auto;
            max-width: 1200px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            color: #fff; /* Ensure text is white */
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3.5rem;
            color: #fff;
            margin-bottom: 1rem;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { text-shadow: 0 0 30px rgba(255, 255, 255, 0.3); }
            to { text-shadow: 0 0 40px rgba(255, 255, 255, 0.6); }
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

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
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .cta-button:hover::before {
            left: 100%;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }

        /* Quiz Results Section */
        .quiz-results-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem auto;
            max-width: 1200px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            text-align: left; /* Align text within this section */
        }

        .quiz-results-section h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #fff;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }

        .quiz-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .quiz-results-table th,
        .quiz-results-table td {
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            text-align: left;
        }

        .quiz-results-table th {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        .quiz-results-table tbody tr:nth-child(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .quiz-results-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .no-results-message {
            text-align: center;
            font-size: 1.1rem;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }


        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav {
                gap: 1rem;
                flex-wrap: wrap; /* Allows nav items to wrap */
                justify-content: center; /* Centers wrapped nav items */
            }

            .nav-item {
                margin: 5px 0; /* Adjust spacing for wrapped items */
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .logo {
                font-size: 2rem;
            }

            .main-content {
                margin-top: 150px; /* Adjusted to account for potentially taller header */
            }

            .quiz-results-table,
            .quiz-results-table thead,
            .quiz-results-table tbody,
            .quiz-results-table th,
            .quiz-results-table td,
            .quiz-results-table tr {
                display: block; /* Make table elements stack on small screens */
            }

            .quiz-results-table thead tr {
                position: absolute;
                top: -9999px; /* Hide table headers */
                left: -9999px;
            }

            .quiz-results-table tr {
                margin-bottom: 10px;
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 10px;
                background-color: rgba(255, 255, 255, 0.08);
            }

            .quiz-results-table td {
                border: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                position: relative;
                padding-left: 50%; /* Make space for pseudo-element labels */
                text-align: right;
            }

            .quiz-results-table td::before {
                content: attr(data-label); /* Use data-label for mobile headers */
                position: absolute;
                left: 10px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                color: rgba(255, 255, 255, 0.7);
            }
        }

        /* Cursor particle styles */
        .cursor-particle {
            animation: particle-fade 1s ease-out forwards;
        }

        @keyframes particle-fade {
            0% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(0.5); }
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
            <a href="#" class="nav-item active" onclick="showHome(event)">Home</a>
            <a href="leader.php" class="nav-item">Leaderboard</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Welcome to QUIZIT</h1>
                <p class="hero-subtitle">
                    Challenge your mind with our extraordinary quiz platform.
                    Compete with friends, climb the leaderboard, and become the ultimate quiz champion!
                </p>
                <?php if ($user_data['id'] === null): // Show Login button only if not logged in ?>
                    <a href="logre.php" class="cta-button">Login / Register</a>
                <?php else: ?>
                    <a href="start_quiz.php" class="cta-button">Start Quiz</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user_data['id'] !== null): // Only show quiz results if a user is logged in ?>
            <div class="quiz-results-section">
                <h2>Your Quiz History</h2>
                <?php if (!empty($user_quiz_results)): ?>
                    <table class="quiz-results-table">
                        <thead>
                            <tr>
                                <th>Quiz Topic</th>
                                <th>Total Questions</th>
                                <th>Your Score</th>
                                <th>Date Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_quiz_results as $result): ?>
                                <tr>
                                    <td data-label="Quiz Topic"><?php echo htmlspecialchars($result['quiz_topic']); ?></td>
                                    <td data-label="Total Questions"><?php echo htmlspecialchars($result['total_questions']); ?></td>
                                    <td data-label="Your Score"><?php echo htmlspecialchars($result['score']); ?></td>
                                    <td data-label="Date Taken"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($result['timestamp']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-results-message">You haven't completed any quizzes yet. Start a quiz to see your history here!</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>
        // Profile dropdown functionality (no longer needed, but kept for reference if you re-add profile)
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) { // Check if element exists before toggling
                dropdown.classList.toggle('active');
            }
        }

        // Close dropdown when clicking outside (no longer needed)
        document.addEventListener('click', function(event) {
            const profileSection = document.querySelector('.profile-section'); // This element is removed
            const dropdown = document.getElementById('profileDropdown'); // This element is removed

            if (profileSection && dropdown && !profileSection.contains(event.target) && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        });

        // Navigation functionality
        function showHome(event) {
            if (event) event.preventDefault(); // Prevent default link behavior if it's a '#' link

            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });

            event.target.classList.add('active');
            console.log('Home page activated');
        }

        // Add scroll effect to header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            if (window.scrollY > 50) {
                header.style.background = 'rgba(255, 255, 255, 0.2)';
                header.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.3)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.1)';
                header.style.boxShadow = 'none';
            }
        });

        // Add particle effect on mouse move
        document.addEventListener('mousemove', function(e) {
            const cursor = document.createElement('div');
            cursor.className = 'cursor-particle';
            cursor.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 50%;
                pointer-events: none;
                z-index: 9999;
                left: ${e.clientX}px;
                top: ${e.clientY}px;
            `;

            document.body.appendChild(cursor);

            setTimeout(() => {
                cursor.remove();
            }, 1000);
        });

        // Add typing effect to hero title
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            element.style.opacity = '1';

            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // Initialize typing effect after page load
        window.addEventListener('load', function() {
            setTimeout(() => {
                const heroTitle = document.querySelector('.hero-title');
                if (heroTitle) {
                    typeWriter(heroTitle, 'Welcome to QUIZIT', 150);
                }
            }, 500);
        });
    </script>
</body>
</html>