<?php
// File: admin_quiz.php
// This script displays quizzes fetched from the database, primarily for admin-added questions.

// Load environment variables (e.g., via vlucas/phpdotenv or server env)
// IMPORTANT: Replace with your actual database credentials or set them as environment variables
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'quiz_db';

// Start the session to access username
session_start();

// Database connection (adjust credentials as needed)
$servername = $dbHost;
$dbUsername = $dbUser;
$dbPassword = $dbPass;
$dbName = $dbName;

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbName", $dbUsername, $dbPassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Something went wrong with the database connection. Please try again later.");
}

// Initialize user data for the header
$user_data = null;

if (isset($_SESSION['user_id'])) {
    $loggedInUserId = $_SESSION['user_id'];

    // --- Fetch User Profile Data ---
    try {
        $stmt_user = $pdo->prepare("SELECT id, username, email, avatar, score, rank, quizzes_completed FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0;
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            // Placeholder avatar for demonstration if 'avatar' column doesn't exist or is null
            $user_data['avatar'] = $user_data['avatar'] ?? 'https://via.placeholder.com/50/FFDAB9/004d4d?text=' . substr($user_data['username'], 0, 1);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        $user_data = null;
    }
}

// Sample user data if no session (Guest user)
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

$questions = [];
$error = null;
$message = null; // Variable for info messages
$selectedTopic = trim($_POST['quizTopic'] ?? ''); // Use trim to remove whitespace
$selectedCategory = trim($_POST['quizCategory'] ?? ''); // Use trim
$numQuestionsRequested = intval($_POST['numQuestions'] ?? 0);

// Flag to determine if a filter request was made
$filterRequested = $_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'save_results');

if ($filterRequested) {
    // Only proceed with fetching if at least one filter criterion is provided
    if (!empty($selectedTopic) || (!empty($selectedCategory) && $selectedCategory !== 'all') || $numQuestionsRequested > 0) {

        // Build the WHERE clause dynamically
        $whereClauses = [];
        $params = [];

        if (!empty($selectedTopic)) {
            $whereClauses[] = "quiz_topic = ?";
            $params[] = $selectedTopic;
        }
        if (!empty($selectedCategory) && $selectedCategory !== 'all') {
            $whereClauses[] = "category = ?";
            $params[] = $selectedCategory;
        }

        $sql = "SELECT id, question, options, answer, category, quiz_topic, created_by, created_at, generated_by_ai FROM quiz_questions";

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // Always order randomly for varied quizzes
        $sql .= " ORDER BY RAND()";

        // Add LIMIT only if a valid number of questions is requested
        if ($numQuestionsRequested > 0) {
            $sql .= " LIMIT " . $numQuestionsRequested;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $databaseQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($databaseQuestions as &$q) {
                // Decode JSON options
                $q['options'] = json_decode($q['options'], true);
                if (!is_array($q['options'])) {
                    $q['options'] = []; // Ensure it's an array even if decoding fails
                }
                $q['source'] = 'database';
            }
            $questions = $databaseQuestions;

            if (empty($questions)) {
                $message = "No questions found matching your filter criteria. Please try different selections.";
            } else {
                $message = "Displaying " . count($questions) . " questions.";
            }

        } catch (PDOException $e) {
            error_log("Error fetching questions from database: " . $e->getMessage());
            $error = "Could not load existing questions: " . $e->getMessage();
        }
    } else {
        $message = "Please enter filter criteria (Topic, Category, or Number of Questions) to display quizzes.";
    }
} else {
    // Initial page load or a GET request without any filter submission
    $message = "Use the filters above to find quizzes.";
}

// Handle quiz results submission and user score update (This part remains unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_results') {
    header('Content-Type: application/json');

    $username = $_SESSION['username'] ?? 'Guest';
    $userId = $_SESSION['user_id'] ?? null;
    $quizTopic = trim($_POST['quizTopic'] ?? '');
    $numQuestions = intval($_POST['numQuestions'] ?? 0);
    $score = intval($_POST['score'] ?? 0);
    $totalQuestions = intval($_POST['totalQuestions'] ?? 0);

    if (empty($quizTopic) || $numQuestions < 1 || $totalQuestions < 1 || $score < 0 || $score > $totalQuestions) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided for saving results.']);
        exit;
    }

    // Using PDO for results table consistency with other DB operations
    try {
        $pdo->beginTransaction();

        // 1. Insert into results table
        $stmt = $pdo->prepare("INSERT INTO results (username, quiz_topic, num_questions, score, total_questions, submission_time) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$username, $quizTopic, $numQuestions, $score, $totalQuestions]);

        // 2. Update user's cumulative score and quizzes completed in 'users' table
        if ($userId) {
            $updateUserStmt = $pdo->prepare("UPDATE users SET score = score + ?, quizzes_completed = quizzes_completed + 1 WHERE id = ?");
            $updateUserStmt->execute([$score, $userId]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Quiz results saved successfully! User score updated.']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Failed to save quiz results or update user score: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to save quiz results: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZIT - Admin Quizzes</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/quiz.css">
</head>
<body>
    <div class="bg-animation">
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
        <div class="floating-shapes"></div>
    </div>

    <header class="header">
    <div class="logo" onclick="location.href='logre.php'">QUIZIT</div>

    <nav class="nav">
        <a href="log.php" class="nav-item">Home</a>
        <a href="leader.php" class="nav-item">Leaderboard</a>
        <a href="quiz.php" class="nav-item">AI Quizzes</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" class="nav-item">Admin Panel</a>
        <?php endif; ?>
    </nav>
    <a href="profile.php" class="profile-icon">
        <img src="profile.png" alt="Profile" />
    </a>

    <?php if ($user_data['id'] !== null): ?>
        <?php else: ?>
        <div style="width: 50px;"></div>
    <?php endif; ?>
</header>

    <main class="main-content-area">
        <div class="container">
            <h1>Admin Generated Quizzes <span class="clipboard-icon">📋</span></h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="info-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" id="quizFilterForm">
                <div class="form-group">
                    <label for="quizTopic">Filter by Topic:</label>
                    <input type="text" id="quizTopic" name="quizTopic" placeholder="e.g., Science" value="<?= htmlspecialchars($selectedTopic); ?>">
                </div>
                <div class="form-group">
                    <label for="quizCategory">Filter by Category:</label>
                    <select id="quizCategory" name="quizCategory">
                        <option value="all" <?= ($selectedCategory == 'all' || empty($selectedCategory)) ? 'selected' : ''; ?>>All Categories</option>
                        <?php
                        // Fetch distinct categories from the database for the dropdown
                        try {
                            $stmt_categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions ORDER BY category");
                            $categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($categories as $cat) {
                                echo '<option value="' . htmlspecialchars($cat) . '"' . (($selectedCategory == $cat) ? ' selected' : '') . '>' . htmlspecialchars(ucfirst($cat)) . '</option>';
                            }
                        } catch (PDOException $e) {
                            error_log("Error fetching categories for dropdown: " . $e->getMessage());
                            echo '<option value="">Error loading categories</option>'; // Fallback in case of error
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="numQuestions">Number of Questions to Show:</label>
                    <input type="number" id="numQuestions" name="numQuestions" min="1" max="50" value="<?= htmlspecialchars($numQuestionsRequested > 0 ? $numQuestionsRequested : ''); ?>">
                </div>
                <button type="submit">Filter Quizzes</button>
            </form>

            <?php
            // Only display quizzes if the $questions array is not empty
            if (!empty($questions)):
            ?>
                <h2>Available Quizzes
                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="username-display">Name: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <?php endif; ?>
                </h2>
                <form id="quizForm" data-quiz-topic="<?= htmlspecialchars($selectedTopic); ?>" data-num-questions="<?= count($questions); ?>">
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="question-block" id="question-<?php echo $i; ?>" data-answer="<?php echo htmlspecialchars($q['answer']); ?>">
                            <div class="question-timer" id="timer-q<?php echo $i; ?>"></div>
                            <strong>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($q['question']); ?></strong>
                            <ul>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <li onclick="selectOption(this)">
                                        <input type="radio" id="q<?php echo $i; ?>_<?php echo md5($opt); ?>" name="question<?php echo $i; ?>" value="<?php echo htmlspecialchars($opt); ?>" disabled>
                                        <label for="q<?php echo $i; ?>_<?php echo md5($opt); ?>"><?php echo htmlspecialchars($opt); ?></label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="feedback"></div>
                            <p class="source-info">Source: Admin-added question</p>
                            <p class="category-info">Category: <?php echo htmlspecialchars(ucfirst($q['category'] ?? 'N/A')); ?></p>
                            <p class="topic-info">Topic: <?php echo htmlspecialchars($q['quiz_topic'] ?? 'N/A'); ?></p>
                            <?php // Display created_by and created_at from your table ?>
                            <p class="created-info">Created By: <?php echo htmlspecialchars($q['created_by'] ?? 'N/A'); ?> on <?php echo htmlspecialchars($q['created_at'] ?? 'N/A'); ?></p>
                            <?php // Display generated_by_ai if it exists and is relevant (e.g., if it's 1 for AI-generated) ?>
                            <?php if (isset($q['generated_by_ai']) && $q['generated_by_ai'] == 1): ?>
                                <p class="ai-info">Generated by AI: Yes</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" id="checkAnswersBtn" onclick="checkAnswers()">Check My Score</button>
                    <button type="button" id="submitQuizBtn" onclick="submitQuizResults()" style="display: none;">Submit Quiz Results</button>
                    <div id="validationMessage" class="validation-message" style="display: none;"></div>
                    <div id="scoreDisplay" class="score-display" style="display: none;"></div>
                    <div id="statusMessage" class="status-message" style="display: none;"></div>
                </form>
            <?php endif; // End of if (!empty($questions)) ?>
        </div>
    </main>

    <script src="assets/js/quiz.js"></script>
</body>
</html>