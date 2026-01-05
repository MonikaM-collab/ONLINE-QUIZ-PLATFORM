// quiz.js - JavaScript functionality for the quiz application

// Profile dropdown functionality (from logre.php)
function toggleProfileDropdown() {
    document.getElementById('profileDropdown').classList.toggle('active');
}

// Close dropdown when clicking outside (from logre.php)
document.addEventListener('click', function(event) {
    const profileSection = document.querySelector('.profile-section');
    const dropdown = document.getElementById('profileDropdown');
    if (profileSection && !profileSection.contains(event.target) && dropdown.classList.contains('active')) {
        dropdown.classList.remove('active');
    }
});

// Function to handle difficulty buttons
function selectDifficulty(count, difficulty, clickedButton) {
    // Set the difficulty value
    document.getElementById('quizDifficulty').value = difficulty;

    // Remove active class from all difficulty buttons
    document.querySelectorAll('.difficulty-buttons button').forEach(button => {
        button.classList.remove('active-difficulty');
    });
    // Add active class to the clicked button
    clickedButton.classList.add('active-difficulty');
}

// Quiz specific JavaScript
const QUESTION_TIME_LIMIT = 20; // Time in seconds for each question
let currentQuestionIndex = 0;
let activeTimerIntervalId = null;
let questionBlocks = []; // To store all question block elements
let finalCorrectCount = 0; // Global to store the score after checking answers

// Function to allow selecting the option by clicking the li element
function selectOption(liElement) {
    const radioButton = liElement.querySelector('input[type="radio"]');
    if (radioButton && !radioButton.disabled) {
        // Deselect any other option in the same question
        const questionBlock = liElement.closest('.question-block');
        questionBlock.querySelectorAll('li').forEach(item => {
            item.classList.remove('selected');
        });
        
        // Select the clicked option
        radioButton.checked = true;
        liElement.classList.add('selected');
    }
}

// Function to handle the timer for a single question
function runSingleQuestionTimer(block, timerDisplay, questionIndex) {
    let timeLeft = QUESTION_TIME_LIMIT;
    timerDisplay.textContent = timeLeft + 's';
    timerDisplay.classList.remove('time-low'); // Reset class

    // Clear any previously running timer for safety
    if (activeTimerIntervalId) {
        clearInterval(activeTimerIntervalId);
    }

    activeTimerIntervalId = setInterval(() => {
        timeLeft--;
        timerDisplay.textContent = timeLeft + 's';

        if (timeLeft <= 5 && timeLeft > 0) {
            timerDisplay.classList.add('time-low');
        } else if (timeLeft <= 0) {
            clearInterval(activeTimerIntervalId);
            activeTimerIntervalId = null; // Clear the stored ID
            timerDisplay.textContent = 'Time Up!';
            timerDisplay.classList.remove('time-low');

            // Disable current question's radio buttons
            const radios = block.querySelectorAll(`input[name="question${questionIndex}"]`);
            radios.forEach(radio => {
                radio.disabled = true;
            });

            // Automatically move to the next question
            startNextQuestionTimer();
        }
    }, 1000);
}

// Function to start the timer for the next question in sequence
function startNextQuestionTimer() {
    // Hide previous question's timer and remove active class
    if (currentQuestionIndex > 0) {
        const prevBlock = questionBlocks[currentQuestionIndex - 1];
        if (prevBlock) {
            const prevTimerDisplay = prevBlock.querySelector('.question-timer');
            if (prevTimerDisplay) prevTimerDisplay.style.display = 'none';
            prevBlock.classList.remove('active');
        }
    }

    if (currentQuestionIndex < questionBlocks.length) {
        const currentBlock = questionBlocks[currentQuestionIndex];
        currentBlock.style.display = 'block'; // Show the current question
        currentBlock.classList.add('active'); // Mark as active

        // Enable radio buttons for the current question
        const radios = currentBlock.querySelectorAll(`input[name="question${currentQuestionIndex}"]`);
        radios.forEach(radio => {
            radio.disabled = false;
        });

        const timerDisplay = currentBlock.querySelector('.question-timer');
        timerDisplay.style.display = 'block'; // Show timer
        runSingleQuestionTimer(currentBlock, timerDisplay, currentQuestionIndex);

        currentQuestionIndex++; // Increment for the next call
    } else {
        // All questions have been presented
        clearInterval(activeTimerIntervalId); // Ensure last timer is cleared
        activeTimerIntervalId = null;

        // Hide the last question block's timer
        const lastBlock = questionBlocks[questionBlocks.length - 1];
        if (lastBlock) {
            const lastTimerDisplay = lastBlock.querySelector('.question-timer');
            if (lastTimerDisplay) lastTimerDisplay.style.display = 'none';
            lastBlock.classList.remove('active'); // Remove active from the last one
        }
        
        // Show all questions again to allow review before checking answers
        questionBlocks.forEach(block => block.style.display = 'block');

        // Show the "Check My Score" button
        document.getElementById('checkAnswersBtn').style.display = 'block';
    }
}

// Initialize the quiz when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    questionBlocks = document.querySelectorAll('.question-block');
    if (questionBlocks.length > 0) {
        // Hide all questions initially
        questionBlocks.forEach(block => {
            block.style.display = 'none';
            // Also hide timers and clear feedback from potentially previous states
            const timerDisplay = block.querySelector('.question-timer');
            if(timerDisplay) timerDisplay.style.display = 'none';
            const feedbackDiv = block.querySelector('.feedback');
            if(feedbackDiv) {
                feedbackDiv.innerHTML = '';
                feedbackDiv.style.display = 'none';
            }
        });

        // Start the timer for the very first question
        currentQuestionIndex = 0; // Reset index to 0
        document.getElementById('checkAnswersBtn').style.display = 'none'; // Hide button initially
        document.getElementById('submitQuizBtn').style.display = 'none'; // Hide submit button initially
        startNextQuestionTimer();
    } else {
        // If no questions are generated (e.g., first load), automatically select 'average' difficulty
        // and highlight the corresponding button.
        const defaultDifficultyButton = document.getElementById('averageBtn');
        if (defaultDifficultyButton) {
            selectDifficulty(10, 'average', defaultDifficultyButton); // Pass a dummy count (10) as it's no longer used
        }
    }
});

function checkAnswers() {
    // Stop any active timer just in case it's still running when checkAnswers is clicked manually
    if (activeTimerIntervalId) {
        clearInterval(activeTimerIntervalId);
        activeTimerIntervalId = null;
    }

    const validationMessageDiv = document.getElementById('validationMessage');
    const scoreDisplayDiv = document.getElementById('scoreDisplay');
    const checkAnswersBtn = document.getElementById('checkAnswersBtn');
    const submitQuizBtn = document.getElementById('submitQuizBtn');
    const statusMessageDiv = document.getElementById('statusMessage');

    let correctCount = 0;

    validationMessageDiv.style.display = 'none';
    scoreDisplayDiv.style.display = 'none';
    scoreDisplayDiv.textContent = '';
    statusMessageDiv.style.display = 'none'; // Hide previous status messages

    let allProcessed = true; 
    questionBlocks.forEach((block, index) => {
        const selectedOption = block.querySelector(`input[name="question${index}"]:checked`);
        const isRadioDisabled = block.querySelector(`input[name="question${index}"]`).disabled;

        if (!selectedOption && !isRadioDisabled) {
            allProcessed = false;
        }
        
        const feedbackDiv = block.querySelector('.feedback');
        feedbackDiv.innerHTML = ''; 
        feedbackDiv.style.display = 'none';

        block.querySelectorAll('li').forEach(li => { // Changed from label to li
            li.classList.remove('correct-option-label', 'selected'); // Remove selected class too
        });
    });

    if (!allProcessed) {
        validationMessageDiv.textContent = 'Please wait for all questions to be presented or answer the current question.';
        validationMessageDiv.style.display = 'block';
        return;
    }

    questionBlocks.forEach((block, index) => {
        const correctAnswer = block.getAttribute('data-answer');
        const selectedOption = block.querySelector(`input[name="question${index}"]:checked`);
        const feedbackDiv = block.querySelector('.feedback');
        const isRadioDisabled = block.querySelector(`input[name="question${index}"]`).disabled;

        let chosenAnswerText = "Not Answered";
        if (selectedOption) {
            chosenAnswerText = selectedOption.value;
        } else if (isRadioDisabled) {
            chosenAnswerText = "Not Answered (Time Up)";
        }

        if (selectedOption && selectedOption.value === correctAnswer) {
            const correctMessage = document.createElement('span');
            correctMessage.innerHTML = `<span class="feedback-label">Correct!</span> <span class="correct-answer-value">${correctAnswer}</span>`;
            correctMessage.classList.add('correct-answer-text');
            feedbackDiv.appendChild(correctMessage);
            correctCount++;
        } else {
            const wrongChosenMessage = document.createElement('span');
            wrongChosenMessage.innerHTML = `<span class="feedback-label">Wrong Answer:<span class="chosen-answer-value">"${chosenAnswerText}"</span>`;
            wrongChosenMessage.classList.add('wrong-chosen-text');
            feedbackDiv.appendChild(wrongChosenMessage);

            const correctAnswerMessage = document.createElement('span');
            correctAnswerMessage.innerHTML = `<span class="feedback-label">Correct Answer:</span> <span class="correct-answer-value">${correctAnswer}</span>`;
            correctAnswerMessage.classList.add('correct-answer-text');
            feedbackDiv.appendChild(correctAnswerMessage);
        }
        feedbackDiv.style.display = 'block';

        block.querySelectorAll('input[type="radio"]').forEach(option => {
            const listItem = option.closest('li'); // Get the parent li
            if (option.value === correctAnswer) {
                listItem.classList.add('correct-option-label'); // Apply class to li
            }
            // Disable all radio buttons after checking answers
            option.disabled = true;
        });
    });

    finalCorrectCount = correctCount; // Store the score globally
    scoreDisplayDiv.textContent = `Your Score: ${finalCorrectCount} out of ${questionBlocks.length}`;
    scoreDisplayDiv.style.display = 'block';

    // Hide "Check My Score" and show "Submit Quiz Results"
    checkAnswersBtn.style.display = 'none';
    submitQuizBtn.style.display = 'block';
}

async function submitQuizResults() {
    const quizForm = document.getElementById('quizForm');
    const quizTopic = quizForm.getAttribute('data-quiz-topic');
    const numQuestions = quizForm.getAttribute('data-num-questions'); // This is the requested count
    const totalQuestionsAnswered = questionBlocks.length; // This is the actual number generated/displayed
    const score = finalCorrectCount; // Use the globally stored score

    const statusMessageDiv = document.getElementById('statusMessage');
    statusMessageDiv.textContent = 'Saving your results...';
    statusMessageDiv.className = 'status-message info';
    statusMessageDiv.style.display = 'block';
    document.getElementById('submitQuizBtn').disabled = true; // Disable button during submission

    try {
        const response = await fetch('quiz.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'save_results',
                quizTopic: quizTopic,
                numQuestions: numQuestions, // Send the originally requested number
                score: score,
                totalQuestions: totalQuestionsAnswered,
            }),
        });

        const result = await response.json();

        if (result.status === 'success') {
            statusMessageDiv.textContent = result.message;
            statusMessageDiv.className = 'status-message success';
        } else {
            statusMessageDiv.textContent = `Error: ${result.message}`;
            statusMessageDiv.className = 'status-message error';
        }
    } catch (error) {
        statusMessageDiv.textContent = `An unexpected error occurred: ${error.message}`;
        statusMessageDiv.className = 'status-message error';
    } finally {
        document.getElementById('submitQuizBtn').disabled = false; // Re-enable button
    }
    
}