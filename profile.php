<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quiz_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $user = null;
    $user_id = null;
    $latest_result = null;
} else {
    $user_id = $_SESSION['user_id'];

    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user not found
    if (!$user) {
        session_destroy();
        $user_id = null;
        $latest_result = null;
    } else {
        // Fetch latest quiz result
        $latest_result = null;
        if (isset($user['username'])) {
            $stmt = $pdo->prepare("SELECT * FROM results WHERE username = ? ORDER BY submission_time DESC LIMIT 1");
            $stmt->execute([$user['username']]);
            $latest_result = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: log.php");
    exit();
}

// Prepare user data for display
$user_initial = $user ? strtoupper(substr($user['username'], 0, 1)) : '?';
$join_date = $user ? date('M d, Y', strtotime($user['created_at'] ?? date('Y-m-d'))) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Google Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #202124;
            color: #e8eaed;
            overflow: hidden;
        }

        /* Full page overlay */
        .profile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            padding: 20px;
        }

        /* Profile container positioned on the right */
        .profile-container {
            width: 400px;
            max-height: 90vh;
            background: #202124; /* Black background */
            border-radius: 24px;
            padding: 32px;
            position: relative;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Close button */
        .close-btn {
            position: absolute;
            top: 16px;
            right: 20px;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            font-size: 24px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        /* Profile header */
        .profile-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 500;
            color: white;
            margin: 0 auto 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .profile-name {
            font-size: 24px;
            font-weight: 400;
            color: white;
            margin-bottom: 4px;
        }

        /* Removed profile-email style, as the element itself is removed */
        /* .profile-email {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
        } */

        .profile-join-date {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Form fields */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 400;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Quiz stats */
        .quiz-stats {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 16px 12px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 20px;
            font-weight: 500;
            color: white;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Logout button */
        .logout-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FF6B6B, #FF5252);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 107, 107, 0.3);
        }

        /* Not logged in message */
        .not-logged-in {
            text-align: center;
            color: white;
            padding: 40px 20px;
        }

        .not-logged-in h2 {
            font-size: 24px;
            margin-bottom: 16px;
            font-weight: 400;
        }

        .not-logged-in p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
        }

        .not-logged-in a {
            color: #60A5FA;
            text-decoration: none;
        }

        .not-logged-in a:hover {
            text-decoration: underline;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .profile-overlay {
                padding: 10px;
                justify-content: center;
            }

            .profile-container {
                width: 100%;
                max-width: 400px;
                max-height: 95vh;
            }

            .quiz-stats {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-overlay" onclick="closeProfile(event)">
        <div class="profile-container" onclick="event.stopPropagation()">
            <button class="close-btn" onclick="closeProfile()">&times;</button>
            
            <?php if ($user): ?>
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="profile-name">Hi, <?php echo htmlspecialchars($user['username']); ?>!</div>
                    <div class="profile-join-date">Member since <?php echo $join_date; ?></div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>

                    <?php if ($latest_result): ?>
                    <div class="quiz-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo ucfirst($latest_result['quiz_topic']); ?></div>
                            <div class="stat-label">Last Topic</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $latest_result['score']; ?></div>
                            <div class="stat-label">Score</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $latest_result['total_questions']; ?></div>
                            <div class="stat-label">Out Of</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="quiz-stats">
                        <div class="stat-item">
                            <div class="stat-value">No Quiz</div>
                            <div class="stat-label">Last Topic</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">0</div>
                            <div class="stat-label">Score</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">0</div>
                            <div class="stat-label">Out Of</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="logout" class="logout-btn">
                        Logout
                    </button>
                </form>

            <?php else: ?>
                <div class="not-logged-in">
                    <h2>Account Required</h2>
                    <p>You are not logged in or your account could not be found.</p>
                    <p>Please <a href="logre.php">log in</a> or <a href="logre.php">register</a> to view your profile.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function closeProfile(event) {
            if (event && event.target !== event.currentTarget) return;
            window.history.back();
        }

        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfile();
            }
        });

        // Prevent scrolling on background
        document.body.style.overflow = 'hidden';
    </script>
</body>
</html>