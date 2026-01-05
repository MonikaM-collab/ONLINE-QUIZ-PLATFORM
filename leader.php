<?php
// --- NEW: Start session and fetch user data for the header ---
session_start();

// Database credentials for User Profile Header (using PDO like in logre.php)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quiz_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // This will not stop the page, but the header might show guest data.
    error_log("Header DB connection failed: " . $e->getMessage());
    $pdo = null; // Ensure pdo is null if connection fails
}

// Initialize user data for the header
$user_data = null;

if ($pdo && isset($_SESSION['user_id'])) {
    $loggedInUserId = $_SESSION['user_id'];
    try {
        $stmt_user = $pdo->prepare("SELECT id, username, email, avatar, score, rank FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            // Assuming 'quizzes_completed' might exist in your users table
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0;
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            // Provide a default avatar if none is set
            $user_data['avatar'] = $user_data['avatar'] ?? 'https://via.placeholder.com/50/FFDAB9/004d4d?text=' . substr($user_data['username'], 0, 1);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data for header: " . $e->getMessage());
        $user_data = null;
    }
}

// Set default Guest user data if not logged in or if data fetch failed
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
// --- END NEW SECTION ---


// --- ORIGINAL PHP Backend Logic for Leaderboard (using mysqli) ---
header('Content-Type: text/html');

// Database credentials (original mysqli connection)
$servername_mysqli = "localhost";
$username_mysqli = "root";
$password_mysqli = "";
$dbname_mysqli = "quiz_db";

$leaderboardData = [];
$errorMessage = null;

// Create mysqli connection
$conn = new mysqli($servername_mysqli, $username_mysqli, $password_mysqli, $dbname_mysqli);

if ($conn->connect_error) {
    error_log("Leaderboard DB connection failed: " . $conn->connect_error);
    $errorMessage = 'Could not connect to the database for leaderboard data.';
} else {
    // SQL query to fetch leaderboard data
    $sql = "SELECT username, score FROM results ORDER BY score DESC LIMIT 100";
    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            $rank = 1;
            while($row = $result->fetch_assoc()) {
                $row['rank'] = $rank++;
                $leaderboardData[] = $row;
            }
        }
    } else {
        error_log("SQL query failed: " . $conn->error);
        $errorMessage = 'Failed to retrieve leaderboard data.';
    }
    $conn->close();
}

// Encode the fetched data for JavaScript
$jsLeaderboardData = json_encode($leaderboardData);
$jsErrorMessage = json_encode($errorMessage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Leaderboard - Interactive Quiz Platform</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* General Body and Layout */
        body {
            background-color: #f4f7fa;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #667eea 0%,rgb(177, 156, 197) 100%); /* Added consistent background */
        }

        /* --- NEW: Header Styles from logre.php --- */
        .header {
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between; /* Changed to space-between */
            align-items: center;
            z-index: 1000;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
            cursor: pointer;
            text-decoration: none;
        }

        .nav {
            display: flex;
            gap: 2rem;
            flex-grow: 1; /* Allows nav to take available space */
            justify-content: center; /* Centers the nav items */
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
        }
        .nav-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        /* Profile Section - Removed */
        /* .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            object-fit: cover;
        }
        .profile-avatar:hover {
            transform: scale(1.1);
            border-color: rgba(255, 255, 255, 0.8);
        }
        .profile-info {
            color: #fff;
            text-align: right;
        }
        .profile-username { font-weight: bold; font-size: 1.1rem; }
        .profile-score { font-size: 0.9rem; opacity: 0.8; } */
        /* --- END NEW HEADER STYLES --- */


        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 40px 5%; /* Adjusted padding */
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }
        .page-title {
            font-family: 'Dancing Script', cursive;
            font-size: 3.8em;
            color: #ffffff; /* Changed for better contrast */
            margin-bottom: 0px;
            font-weight: 700;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.2);
        }
        .page-subtitle {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1em;
            color: rgba(255, 255, 255, 0.85); /* Changed for better contrast */
            margin-top: 5px;
            margin-bottom: 50px;
            font-weight: 400;
        }

        /* Leaderboard Specific Styles (Unchanged) */
        .leaderboard-table-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-top: 40px;
            animation: fadeInScale 0.8s ease-out;
        }
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
            table-layout: fixed;
        }
        .leaderboard-table thead {
            background-color: #5a7ea8;
            color: #ffffff;
        }
        .leaderboard-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 1.05em;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .leaderboard-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .leaderboard-table tbody tr:last-child {
            border-bottom: none;
        }
        .leaderboard-table tbody tr:hover {
            background-color: #eef3f8;
            transform: translateY(-2px);
        }
        .leaderboard-table td {
            padding: 15px;
            text-align: left;
            font-size: 0.95em;
            color: #555;
        }
        .leaderboard-table td:first-child {
            font-weight: 700;
            color: #4a699c;
            width: 10%;
        }
        .leaderboard-table td:last-child {
            font-weight: 700;
            color: #28a745;
            width: 15%;
        }

        /* Top 3 Player & Medal Styles (Unchanged) */
        .first-place { background: linear-gradient(to right, #ffe082, #ffd54f); }
        .second-place { background: linear-gradient(to right, #e0e0e0, #bdbdbd); }
        .third-place { background: linear-gradient(to right, #d8bcab, #c89c7d); }
        .gold-medal { color: #d4af37; }
        .silver-medal { color: #a8a8a8; }
        .bronze-medal { color: #cd7f32; }

        /* Loading/Error/No Data Messages (Unchanged) */
        .loading-message, .error-message, .no-data-message {
            padding: 30px 20px;
            font-size: 1.1em;
            color: #777;
            text-align: center;
        }
        .error-message { color: #dc3545; }

        /* Animations (Unchanged) */
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">QUIZIT</div>

        <nav class="nav">
            <a href="log.php" class="nav-item">Home</a>
            <a href="leader.php" class="nav-item active">Leaderboard</a>
        </nav>

        </header>

    <div class="main-content">
        <h2 class="page-title">Leaderboard</h2>
        <p class="page-subtitle">Who's at the top? See the highest scores!</p>

        <div class="leaderboard-table-container">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-trophy"></i> Rank</th>
                        <th><i class="fas fa-user"></i> Username</th>
                        <th><i class="fas fa-star"></i> Score</th>
                    </tr>
                </thead>
                <tbody id="leaderboardTableBody">
                    </tbody>
            </table>
            <div class="loading-message" id="leaderboardLoading" style="display:none;">
                <i class="fas fa-spinner fa-spin"></i> Loading leaderboard...
            </div>
            <div class="error-message" id="leaderboardError" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i> Failed to load leaderboard data.
            </div>
            <div class="no-data-message" id="leaderboardNoData" style="display:none;">
                <i class="fas fa-info-circle"></i> No leaderboard data available yet.
            </div>
        </div>
    </div>

    <script>
        // --- ORIGINAL JavaScript for Leaderboard ---
        const leaderboardTableBody = document.getElementById('leaderboardTableBody');
        const leaderboardLoading = document.getElementById('leaderboardLoading');
        const leaderboardError = document.getElementById('leaderboardError');
        const leaderboardNoData = document.getElementById('leaderboardNoData');

        const initialLeaderboardData = <?php echo $jsLeaderboardData; ?>;
        const initialErrorMessage = <?php echo $jsErrorMessage; ?>;

        function displayLeaderboard() {
            leaderboardLoading.style.display = 'none';

            if (initialErrorMessage) {
                leaderboardError.textContent = `Error: ${initialErrorMessage}`;
                leaderboardError.style.display = 'block';
                return;
            }

            if (initialLeaderboardData.length === 0) {
                leaderboardNoData.style.display = 'block';
                return;
            }

            initialLeaderboardData.forEach((player, index) => {
                const row = document.createElement('tr');
                let rankCellContent;

                if (index === 0) {
                    row.classList.add('first-place');
                    rankCellContent = `<i class="fas fa-medal gold-medal"></i> 1`;
                } else if (index === 1) {
                    row.classList.add('second-place');
                    rankCellContent = `<i class="fas fa-medal silver-medal"></i> 2`;
                } else if (index === 2) {
                    row.classList.add('third-place');
                    rankCellContent = `<i class="fas fa-medal bronze-medal"></i> 3`;
                } else {
                    rankCellContent = player.rank;
                }

                row.innerHTML = `
                    <td>${rankCellContent}</td>
                    <td>${player.username}</td>
                    <td>${player.score}</td>
                `;
                leaderboardTableBody.appendChild(row);
            });
        }

        document.addEventListener('DOMContentLoaded', displayLeaderboard); // Moved to DOMContentLoaded
    </script>
</body>
</html>