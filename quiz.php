<?php
// File: quiz.php (or index.php)
// This script uses Google Gemini 2.5 Flash via REST API key to generate quiz questions

// Load environment variables (e.g., via vlucas/phpdotenv or server env)
// IMPORTANT: Replace with your actual database credentials or set them as environment variables
$apiKey = getenv('GEMINI_API_KEY') ?: 'AIzaSyCL2fVYycj-BDwfQKlVPPQo7U61U0THcjI';
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'quiz_db';

// Start the session to access username
session_start();

// Database connection (adjust credentials as needed) - USING PDO FOR CONSISTENCY WITH LOGRE.PHP
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
        $stmt_user = $pdo->prepare("SELECT id, username, email, avatar, score, rank FROM users WHERE id = ?");
        $stmt_user->execute([$loggedInUserId]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_data['score'] = $user_data['score'] ?? 0;
            $user_data['rank'] = $user_data['rank'] ?? 'N/A';
            // Assuming 'quizzes_completed' might exist or default to 0
            $user_data['quizzes_completed'] = $user_data['quizzes_completed'] ?? 0;
            $user_data['email'] = $user_data['email'] ?? 'N/A';
            // Placeholder avatar if not set
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

// Handle quiz generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $topic = trim($_POST['quizTopic'] ?? '');
    $count = intval($_POST['numQuestions'] ?? 0);
    $difficulty = trim($_POST['quizDifficulty'] ?? 'average'); // Capture difficulty, default to average

    if ($topic === '' || $count < 1 || $count > 50) {
        $error = 'Please provide a valid topic and question count (1-50).';
    } else {
        // Build endpoint URL per official docs
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

        // Build prompt with difficulty level
        // MODIFICATION START: Incorporate difficulty into the prompt
        $promptText = "Generate {$count} multiple-choice quiz questions on the topic: {$topic}";
        if (!empty($difficulty) && $difficulty !== 'average') {
            $promptText .= " at a {$difficulty} difficulty level.";
        }
        $promptText .= ". Return a JSON array of objects with keys: question, options (array of four), answer.";
        // MODIFICATION END

        // Build request payload
        $payload = [
            'contents' => [
                [ 'parts' => [ ['text' => $promptText] ] ] // Use the modified prompt text
            ]
        ];

        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = 'cURL error: ' . curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                $error = "API returned HTTP status {$httpCode}: {$response}";
            } else {
                $json = json_decode($response, true);
                // Debug branch: print entire JSON if structure unexpected
                if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    $content = $json['candidates'][0]['content']['parts'][0]['text'];
                    // Strip Markdown code fences if present
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content);
                    $decoded = json_decode($clean, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $questions = $decoded;
                    } else {
                        $error = 'API returned text, but parsing JSON failed. Raw content (fence-stripped): ' . $clean;
                    }
                } else {
                    $error = 'Unexpected API response structure: ' . print_r($json, true);
                }
            }
        }
        curl_close($ch);
    }
}

// Handle quiz results submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_results') {
    header('Content-Type: application/json');

    $username = $_SESSION['username'] ?? 'Guest'; // Get username from session
    $quizTopic = trim($_POST['quizTopic'] ?? '');
    $numQuestions = intval($_POST['numQuestions'] ?? 0);
    $score = intval($_POST['score'] ?? 0);
    $totalQuestions = intval($_POST['totalQuestions'] ?? 0);

    // Basic validation
    if (empty($quizTopic) || $numQuestions < 1 || $totalQuestions < 1 || $score < 0 || $score > $totalQuestions) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided for saving results.']);
        exit;
    }

    // Database connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO results (username, quiz_topic, num_questions, score, total_questions, submission_time) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement: ' . $conn->error]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("ssiii", $username, $quizTopic, $numQuestions, $score, $totalQuestions);

    // Execute and check
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Quiz results saved successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save quiz results: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit; // Stop further script execution after handling AJAX request
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZIT - AI Quiz Master</title>
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
        <div class="logo">QUIZIT</div>

        <nav class="nav">
            <a href="log.php" class="nav-item">Home</a>
            <a href="leader.php" class="nav-item">Leaderboard</a>
            <a href="admin_quiz.php" class="nav-item">Admin Quizzes</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" class="nav-item">Admin Panel</a>
            <?php endif; ?>
        </nav>
        
        <?php if ($user_data['id'] !== null): ?>
            <a href="profile.php" class="profile-icon-link">
                <img src="<?= htmlspecialchars($user_data['avatar']) ?>" alt="Profile Avatar">
            </a>
        <?php else: ?>
            <a href="profile.php" class="profile-icon-link">
                <img src="profile.png" alt="Profile">
            </a>
        <?php endif; ?>
    </header>
    <main class="main-content-area">
        <div class="container">
            <h1>AI Quiz Master <span class="brain-icon">🧠</span></h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" id="quizGenerationForm">
                <div class="form-group">
                    <label for="quizTopic">Quiz Topic:</label>
                    <input type="text" id="quizTopic" name="quizTopic" placeholder="e.g., Artificial Intelligence" value="<?php echo htmlspecialchars($_POST['quizTopic'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="numQuestions">Number of Questions (1-50):</label>
                    <input type="number" id="numQuestions" name="numQuestions" min="1" max="50" value="<?php echo htmlspecialchars($_POST['numQuestions'] ?? 5); ?>" required>
                    <input type="hidden" id="quizDifficulty" name="quizDifficulty" value="<?php echo htmlspecialchars($_POST['quizDifficulty'] ?? 'average'); ?>">
                </div>
                <div class="difficulty-buttons">
                    <button type="button" id="easyBtn" onclick="selectDifficulty(5, 'easy', this)">Easy</button>
                    <button type="button" id="averageBtn" onclick="selectDifficulty(10, 'average', this)">Average</button>
                    <button type="button" id="hardBtn" onclick="selectDifficulty(20, 'hard', this)">Hard</button>
                </div>
                <button type="submit" id="generateQuizBtn">Generate Quiz</button>
            </form>

            <?php if (!empty($questions)): ?>
                <h2>Let's Begin!
                    <?php if (isset($_SESSION['username'])): ?>
                        <span class="username-display">Name: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <?php endif; ?>
                </h2>
                <form id="quizForm" data-quiz-topic="<?php echo htmlspecialchars($_POST['quizTopic'] ?? ''); ?>" data-num-questions="<?php echo htmlspecialchars($_POST['numQuestions'] ?? ''); ?>">
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="question-block" id="question-<?php echo $i; ?>" data-answer="<?php echo htmlspecialchars($q['answer']); ?>">
                            <div class="question-timer" id="timer-q<?php echo $i; ?>"></div>
                            <strong>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($q['question']); ?></strong>
                            <ul>
                                <?php foreach ($q['options'] as $opt): ?>
                                    <li onclick="selectOption(this)"> <input type="radio" id="q<?php echo $i; ?>_<?php echo md5($opt); ?>" name="question<?php echo $i; ?>" value="<?php echo htmlspecialchars($opt); ?>" disabled>
                                        <label for="q<?php echo $i; ?>_<?php echo md5($opt); ?>"><?php echo htmlspecialchars($opt); ?></label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="feedback"></div>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" id="checkAnswersBtn" onclick="checkAnswers()">Check My Score</button>
                    <button type="button" id="submitQuizBtn" onclick="submitQuizResults()" style="display: none;">Submit Quiz Results</button>
                    <div id="validationMessage" class="validation-message" style="display: none;"></div>
                    <div id="scoreDisplay" class="score-display" style="display: none;"></div>
                    <div id="statusMessage" class="status-message" style="display: none;"></div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/quiz.js"></script>

    <script>
        // This variable should be updated by your checkAnswers function
        let finalScore = 0;

        // Ensure this function is called by your existing checkAnswers() logic
        // after calculating the score. For example, you'd call it like:
        // finalScore = calculatedScore;
        function setFinalScore(score) {
            finalScore = score;
        }

        async function submitQuizResults() {
            const quizForm = document.getElementById('quizForm');
            const statusMessage = document.getElementById('statusMessage');
            const submitButton = document.getElementById('submitQuizBtn');

            // Disable button to prevent multiple submissions
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';

            // --- Recalculate score right before submission to be certain ---
            let currentScore = 0;
            const questionBlocks = document.querySelectorAll('.question-block');
            questionBlocks.forEach((block, index) => {
                const correctAnswer = block.dataset.answer;
                const selectedOption = block.querySelector('input[type="radio"]:checked');
                if (selectedOption && selectedOption.value === correctAnswer) {
                    currentScore++;
                }
            });
            finalScore = currentScore;
            // -----------------------------------------------------------------

            const formData = new FormData();
            formData.append('action', 'save_results');
            formData.append('quizTopic', quizForm.dataset.quizTopic);
            formData.append('numQuestions', quizForm.dataset.numQuestions);
            formData.append('score', finalScore);
            formData.append('totalQuestions', questionBlocks.length);

            try {
                const response = await fetch('quiz.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // On successful save, update status and then redirect after a delay.
                    submitButton.textContent = 'Success !...';
                    
                    // **MODIFICATION: Wait 1 second (1000 milliseconds) before redirecting**
                    setTimeout(() => {
                        window.location.href = 'thank.php';
                    }, 1000);

                } else {
                    // If saving fails, show an error and re-enable the button
                    statusMessage.textContent = 'Error: ' + result.message;
                    statusMessage.style.display = 'block';
                    statusMessage.style.color = 'red';
                    submitButton.disabled = false;
                    submitButton.textContent = 'Submit Quiz Results';
                }
            } catch (error) {
                // Handle network errors
                statusMessage.textContent = 'A network error occurred. Please try again. ' + error;
                statusMessage.style.display = 'block';
                statusMessage.style.color = 'red';
                submitButton.disabled = false;
                submitButton.textContent = 'Submit Quiz Results';
            }
        }
    </script>
    </body>
</html>