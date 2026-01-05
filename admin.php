<?php
session_start();

// --- Database Configuration ---
$servername = "localhost"; // Your database server
$username = "root";             // Your database username
$password = "";                 // Your database password
$dbname = "quiz_db";           // Your database name

// --- Database Connection ---
$conn = null;
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create quiz_questions table if it doesn't exist
    // IMPORTANT: Make sure you've already run the ALTER TABLE statement
    // if this table already existed without the 'category' column.
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        options JSON NOT NULL,
        answer VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'average', -- Added category column
        quiz_topic VARCHAR(255) DEFAULT '',    -- Added quiz_topic column
        created_by VARCHAR(255) DEFAULT 'admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        generated_by_ai BOOLEAN DEFAULT FALSE
    );";
    $conn->exec($createTableSql);

    // ALTER TABLE to add quiz_topic if it doesn't exist (useful for existing databases)
    // You only need to run this once if your table already exists without the column
    $alterTableSql = "
    DO $$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'quiz_questions' AND column_name = 'quiz_topic') THEN
            ALTER TABLE quiz_questions ADD COLUMN quiz_topic VARCHAR(255) DEFAULT '';
        END IF;
    END
    $$;
    ";
    // NOTE: The above DO $$ block is for PostgreSQL. For MySQL, it would be:
    // ALTER TABLE quiz_questions ADD COLUMN quiz_topic VARCHAR(255) DEFAULT '' AFTER category;
    // Let's use the MySQL-compatible one since you're using PDO with mysql:host
    try {
        $checkColumnSql = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'quiz_questions' AND COLUMN_NAME = 'quiz_topic'");
        $checkColumnSql->execute([$dbname]);
        if (!$checkColumnSql->fetch()) {
            $conn->exec("ALTER TABLE quiz_questions ADD COLUMN quiz_topic VARCHAR(255) DEFAULT '' AFTER category");
        }
    } catch (PDOException $e) {
        // Log the error but don't stop execution, as it might just be the column already exists
        error_log("Error adding quiz_topic column: " . $e->getMessage());
    }


} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// --- Admin Access Control ---
// Redirect if not logged in or not an admin.
// Note: In a real application, you would have a robust role check.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // To prevent redirect loops if the user is already on logre.php
    if (basename($_SERVER['PHP_SELF']) !== 'logre.php') {
        header("Location: logre.php?error=" . urlencode("Access Denied. You must be logged in as an admin."));
        exit();
    }
}


// --- Fetch User Profile Data (from logre.php) ---
$user_data = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt_user = $conn->prepare("SELECT id, username, email, avatar, score, rank FROM users WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0;
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            // Use a default avatar if none is set
            $user_data['avatar'] = $user_data['avatar'] ?? 'https://via.placeholder.com/50/FFDAB9/004d4d?text=' . substr($user_data['username'], 0, 1);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data for admin panel: " . $e->getMessage());
        $user_data = null; // Set to null on error
    }
}

// Provide default data for guest or if fetch fails
if (!$user_data) {
    $user_data = [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'Admin',
        'email' => 'N/A',
        'avatar' => 'https://via.placeholder.com/50/D9534F/FFFFFF?text=A',
        'score' => 0,
        'rank' => 'N/A',
        'quizzes_completed' => 0
    ];
}


$message = '';
$messageType = ''; // 'success', 'error', 'info'

// --- Function to display messages ---
function showMessage($msg, $type) {
    global $message, $messageType;
    $message = $msg;
    $messageType = $type;
}

// --- CRUD Operations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $question = trim($_POST['question'] ?? '');
        $options = [
            trim($_POST['option1'] ?? ''),
            trim($_POST['option2'] ?? ''),
            trim($_POST['option3'] ?? ''),
            trim($_POST['option4'] ?? '')
        ];
        $answer = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? 'average'); // Get the category from the form
        $quiz_topic = trim($_POST['quiz_topic'] ?? ''); // Get the quiz_topic from the form

        if (empty($question) || empty($options[0]) || empty($options[1]) || empty($options[2]) || empty($options[3]) || empty($answer)) {
            showMessage("All fields are required!", "error");
        } else if (!in_array($answer, $options)) {
            showMessage("Correct answer must be one of the provided options!", "error");
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $conn->prepare("INSERT INTO quiz_questions (question, options, answer, category, quiz_topic, created_by, generated_by_ai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$question, json_encode($options), $answer, $category, $quiz_topic, $user_data['username'], 0]);
                    showMessage("Question added successfully!", "success");
                } elseif ($action === 'edit') {
                    $id = intval($_POST['id'] ?? 0);
                    if ($id > 0) {
                        $stmt = $conn->prepare("UPDATE quiz_questions SET question = ?, options = ?, answer = ?, category = ?, quiz_topic = ? WHERE id = ?");
                        $stmt->execute([$question, json_encode($options), $answer, $category, $quiz_topic, $id]);
                        showMessage("Question updated successfully!", "success");
                    } else {
                        showMessage("Invalid question ID for update.", "error");
                    }
                }
            } catch (PDOException $e) {
                showMessage("Database error: " . $e->getMessage(), "error");
            }
        }
    }
}

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
            $stmt->execute([$id]);
            // Set message in session to persist after redirect
            $_SESSION['flash_message'] = "Question deleted successfully!";
            $_SESSION['flash_message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_message_type'] = "error";
        }
    } else {
        $_SESSION['flash_message'] = "Invalid question ID for deletion.";
        $_SESSION['flash_message_type'] = "error";
    }
    header("Location: admin.php");
    exit();
}

// Check for flash messages from session (e.g., after delete redirect)
if (isset($_SESSION['flash_message'])) {
    showMessage($_SESSION['flash_message'], $_SESSION['flash_message_type']);
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}


// --- Fetch Questions (Read) ---
$questions = [];
try {
    // SELECT * will now include 'category' and 'quiz_topic'
    $stmt = $conn->query("SELECT * FROM quiz_questions ORDER BY created_at DESC");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($questions as &$q) {
        $q['options'] = json_decode($q['options'], true);
    }
} catch (PDOException $e) {
    showMessage("Error fetching questions: " . $e->getMessage(), "error");
}

// --- Get question for editing ---
$editQuestion = null;
if (isset($_GET['edit_id'])) {
    $id = intval($_GET['edit_id']);
    if ($id > 0) {
        try {
            $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
            $stmt->execute([$id]);
            $editQuestion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($editQuestion) {
                $editQuestion['options'] = json_decode($editQuestion['options'], true);
            } else {
                showMessage("Question not found for editing.", "error");
            }
        } catch (PDOException $e) {
            showMessage("Database error fetching question for edit: " . $e->getMessage(), "error");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZIT - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* --- Styles from logre.php --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { height: 100%; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, rgb(177, 156, 197) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .bg-animation { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; opacity: 0.1; }
        .floating-shapes { position: absolute; width: 100px; height: 100px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: float 12s ease-in-out infinite; }
        .floating-shapes:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; width: 80px; height: 80px; }
        .floating-shapes:nth-child(2) { top: 20%; right: 10%; animation-delay: 2s; width: 120px; height: 120px; }
        .floating-shapes:nth-child(3) { bottom: 10%; left: 20%; animation-delay: 4s; width: 60px; height: 60px; }
        .floating-shapes:nth-child(4) { bottom: 20%; right: 20%; animation-delay: 6s; }
        @keyframes float { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-30px) rotate(180deg); } }
        .header { flex-shrink: 0; background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; z-index: 1000; }
        .logo { font-size: 2.5rem; font-weight: bold; color: #fff; text-shadow: 0 0 20px rgba(255, 255, 255, 0.5); cursor: pointer; }
        .nav { display: flex; gap: 2rem; }
        .nav-item { padding: 0.8rem 1.5rem; background: rgba(255, 255, 255, 0.1); border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 25px; color: #fff; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
        .nav-item:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); border-color: rgba(255, 255, 255, 0.6); }
        .profile-section { display: flex; align-items: center; gap: 1rem; position: relative; }
        .profile-avatar { width: 50px; height: 50px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.5); cursor: pointer; object-fit: cover; }
        .profile-info { color: #fff; cursor: pointer; text-align: right; }
        .profile-username { font-weight: bold; font-size: 1.1rem; }
        .profile-score { font-size: 0.9rem; opacity: 0.8; }
        .profile-dropdown { position: absolute; top: 100%; right: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 1rem; min-width: 250px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); transform: translateY(-10px); opacity: 0; visibility: hidden; transition: all 0.3s ease; margin-top: 1rem; color: #333; }
        .profile-dropdown.active { transform: translateY(0); opacity: 1; visibility: visible; }
        .profile-dropdown h3 { color: #333; margin-bottom: 0.5rem; text-align: center; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; }
        .profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
        .stat-item { background: rgba(102, 126, 234, 0.1); padding: 0.8rem; border-radius: 10px; text-align: center; color: #333; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 0.8rem; opacity: 0.7; }
        .cta-button { display: block; width: 100%; text-align: center; padding: 0.8rem; background: linear-gradient(45deg, #f44336, #d32f2f); color: #fff; text-decoration: none; border-radius: 10px; font-weight: bold; transition: all 0.3s ease; border: none; margin-top: 1rem; }
        .main-content-area { flex-grow: 1; overflow-y: auto; padding: 2rem; }
        .main-content-area::-webkit-scrollbar { width: 8px; }
        .main-content-area::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.1); border-radius: 10px; }
        .main-content-area::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 10px; }

        /* --- Styles for Admin Panel Container & Content --- */
        .container {
            background: rgba(10, 5, 25, 0.35);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            padding: 40px;
            width: 100%;
            max-width: 900px; /* Wider for admin content */
            margin: 0 auto;
        }
        h1 {
            font-size: 3em;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 2px 3px 7px rgba(0, 0, 0, 0.3);
        }
        .message { padding: 15px; margin-bottom: 25px; border-radius: 10px; font-weight: 600; text-align: center; animation: fadeIn 0.5s ease-out; border: 1px solid; }
        .message.success { background-color: rgba(76, 175, 80, 0.2); color: #d4edda; border-color: #c3e6cb; }
        .message.error { background-color: rgba(244, 67, 54, 0.2); color: #f8d7da; border-color: #f5c6cb; }
        .message.info { background-color: rgba(33, 150, 243, 0.2); color: #d1ecf1; border-color: #bee5eb; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .form-section {
            background-color: rgba(0,0,0,0.2);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-section h2 { font-size: 2em; color: #fff; margin-bottom: 25px; font-weight: 700; text-align: center; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #e0e0e0; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group select { /* Added select */
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 1em;
            color: #333;
            background-color: #fff;
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { /* Added select */
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.4);
            outline: none;
        }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: #fff; }
        .btn-primary { background: linear-gradient(45deg, #4ecdc4, #45b7d1); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .btn-danger { background: linear-gradient(45deg, #ff6b6b, #f44336); }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .btn-info { background: linear-gradient(45deg, #667eea, #764ba2); }
        .btn-info:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .btn-warning { background: linear-gradient(45deg, #f59e0b, #d97706); text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }

        .question-list { margin-top: 40px; }
        .question-list h2 { color: #fff; }
        .question-item {
            background-color: rgba(0,0,0,0.15);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .question-item strong { font-size: 1.15em; color: #fff; line-height: 1.4; }
        .question-item ul { list-style-position: inside; padding-left: 0; margin-top: 10px; color: #e0e0e0; }
        .question-item ul li { margin-bottom: 5px; font-size: 0.95em; }
        .question-item .correct-answer { font-weight: 600; color: #4ecdc4; }
        .question-item .category-label { font-weight: 600; color: #78a1f7; font-size: 0.95em; } /* Style for category */
        .question-item .topic-label { font-weight: 600; color: #ffeb3b; font-size: 0.95em; } /* Style for topic */
        .question-actions { display: flex; gap: 10px; margin-top: 15px; justify-content: flex-end; }
        .question-actions .btn { padding: 8px 15px; font-size: 0.9em; }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; padding: 1rem; }
            .nav { gap: 1rem; }
            .container { padding: 25px; margin: 15px 0; }
            h1 { font-size: 2.5em; }
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
        <div class="logo" onclick="location.href='logre.php'">QUIZIT</div>
        <nav class="nav">
            <a href="logre.php" class="nav-item">Home</a>
            <a href="leader.php" class="nav-item">Leaderboard</a>
        </nav>
        <div class="profile-section">
            <img src="<?php echo htmlspecialchars($user_data['avatar']); ?>" alt="Profile" class="profile-avatar" onclick="toggleProfileDropdown()">
            <div class="profile-info" onclick="toggleProfileDropdown()">
                <div class="profile-username"><?php echo htmlspecialchars($user_data['username']); ?></div>
                <div class="profile-score">Role: Admin</div>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <h3><?php echo htmlspecialchars($user_data['username']); ?></h3>
                <div class="profile-stats">
                    <div class="stat-item"><div class="stat-value"><?php echo number_format($user_data['score']); ?></div><div class="stat-label">Total Score</div></div>
                    <div class="stat-item"><div class="stat-value">#<?php echo htmlspecialchars($user_data['rank']); ?></div><div class="stat-label">Rank</div></div>
                </div>
                <a href="logre.php?action=logout" class="cta-button">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content-area">
        <div class="container">
            <h1>Admin Panel</h1>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <h2><?php echo $editQuestion ? 'Edit Question' : 'Add New Question'; ?></h2>
                <form method="POST" action="admin.php">
                    <?php if ($editQuestion): ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editQuestion['id']); ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                    <?php endif; ?>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="question">Question Text:</label>
                        <textarea id="question" name="question" rows="3" required><?php echo htmlspecialchars($editQuestion['question'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label>Options (4):</label>
                        <input type="text" name="option1" placeholder="Option 1" required value="<?php echo htmlspecialchars($editQuestion['options'][0] ?? ''); ?>" style="margin-bottom: 0.5rem;">
                        <input type="text" name="option2" placeholder="Option 2" required value="<?php echo htmlspecialchars($editQuestion['options'][1] ?? ''); ?>" style="margin-bottom: 0.5rem;">
                        <input type="text" name="option3" placeholder="Option 3" required value="<?php echo htmlspecialchars($editQuestion['options'][2] ?? ''); ?>" style="margin-bottom: 0.5rem;">
                        <input type="text" name="option4" placeholder="Option 4" required value="<?php echo htmlspecialchars($editQuestion['options'][3] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="category">Category/Difficulty:</label>
                        <select id="category" name="category" required>
                            <option value="easy" <?php echo (isset($editQuestion['category']) && $editQuestion['category'] == 'easy') ? 'selected' : ''; ?>>Easy</option>
                            <option value="average" <?php echo (isset($editQuestion['category']) && $editQuestion['category'] == 'average') ? 'selected' : ''; ?>>Average</option>
                            <option value="hard" <?php echo (isset($editQuestion['category']) && $editQuestion['category'] == 'hard') ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="quiz_topic">Quiz Topic:</label>
                        <input type="text" id="quiz_topic" name="quiz_topic" placeholder="e.g., History, Science, Math" value="<?php echo htmlspecialchars($editQuestion['quiz_topic'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="answer">Correct Answer (must match one of the options):</label>
                        <input type="text" id="answer" name="answer" required value="<?php echo htmlspecialchars($editQuestion['answer'] ?? ''); ?>">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editQuestion ? 'Update Question' : 'Add Question'; ?>
                        </button>
                        <?php if ($editQuestion): ?>
                            <a href="admin.php" class="btn btn-warning">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="question-list">
                <h2 style="text-align: center; font-size: 2em; margin-bottom: 25px;">Existing Quiz Questions</h2>
                <?php if (empty($questions)): ?>
                    <p style="text-align: center; padding: 2rem; border: 2px dashed rgba(255,255,255,0.2); border-radius: 10px;">No questions available. Add some or generate new ones!</p>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                        <div class="question-item">
                            <strong>Q: <?php echo htmlspecialchars($q['question']); ?></strong>
                            <ul>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <li><?php echo htmlspecialchars($opt); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="correct-answer">Correct Answer: <?php echo htmlspecialchars($q['answer']); ?></p>
                            <p class="category-label">Category: <?php echo htmlspecialchars(ucfirst($q['category'] ?? 'N/A')); ?></p>
                            <p class="topic-label">Topic: <?php echo htmlspecialchars($q['quiz_topic'] ?? 'N/A'); ?></p>
                            <p style="font-size: 0.85em; opacity: 0.7;">
                                Added by: <?php echo htmlspecialchars($q['created_by']); ?>
                                on <?php echo date('Y-m-d H:i', strtotime($q['created_at'])); ?>
                                <?php echo $q['generated_by_ai'] ? '<span style="color: #667eea; font-weight: bold;">(AI)</span>' : ''; ?>
                            </p>
                            <div class="question-actions">
                                <a href="admin.php?edit_id=<?php echo htmlspecialchars($q['id']); ?>#form-section" class="btn btn-primary">Edit</a>
                                <a href="admin.php?delete_id=<?php echo htmlspecialchars($q['id']); ?>"
                                   onclick="return confirm('Are you sure you want to delete this question? This action cannot be undone.');"
                                   class="btn btn-danger">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Profile dropdown functionality from logre.php
        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileSection = document.querySelector('.profile-section');
            const dropdown = document.getElementById('profileDropdown');
            if (profileSection && !profileSection.contains(event.target) && dropdown.classList.contains('active')) {
                dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>