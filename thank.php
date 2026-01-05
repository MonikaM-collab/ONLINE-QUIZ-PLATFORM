<?php
// You can add PHP logic here to handle quiz results
// For example: $score = $_POST['score'] ?? 0;
// $total_questions = $_POST['total'] ?? 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Quiz Completed!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00f2fe;
            --secondary-color: #4facfe;
            --accent-color-1: #ff6b6b;
            --accent-color-2: #ffd93d;
            --text-light: rgba(255, 255, 255, 0.9);
            --text-dark: rgba(255, 255, 255, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #0f0c29;
            background: -webkit-linear-gradient(to right, #24243e, #302b63, #0f0c29);
            background: linear-gradient(to right, #24243e, #302b63, #0f0c29);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            color: var(--text-light);
        }

        /* Animated background elements */
        .background-wrap {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            animation: move 15s linear infinite;
        }

        .circle.c1 {
            width: 400px;
            height: 400px;
            top: 10%;
            left: -100px;
            opacity: 0.3;
        }
        
        .circle.c2 {
            width: 500px;
            height: 500px;
            bottom: -200px;
            right: -150px;
            opacity: 0.4;
            animation-duration: 20s;
        }


        @keyframes move {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-40px) rotate(180deg); }
            100% { transform: translateY(0) rotate(360deg); }
        }

        /* Main container */
        .container {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .thank-you-card {
            background: rgba(30, 30, 50, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 650px;
            width: 100%;
            opacity: 0;
            transform: scale(0.9) translateY(30px);
            animation: card-appear 1s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            transition: transform 0.4s ease;
        }

        @keyframes card-appear {
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .icon-container {
            margin-bottom: 30px;
            position: relative;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2.5s ease-in-out infinite;
        }

        .success-icon svg {
            width: 100%;
            height: 100%;
        }

        .check-mark {
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
            animation: draw-check 1.5s ease-out 0.5s forwards;
        }
        
        .check-bg {
            fill: var(--primary-color);
            fill-opacity: 0.2;
            stroke: var(--primary-color);
            stroke-width: 2;
        }

        @keyframes draw-check {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        .main-title {
            font-size: 3.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color), #fff);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: text-shine 5s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes text-shine {
            to {
                background-position: 200% center;
            }
        }
        
        .subtitle {
            font-size: 1.4rem;
            color: var(--text-light);
            font-weight: 400;
            margin-bottom: 25px;
            opacity: 0;
            animation: fade-in 1s ease-out 0.5s forwards;
        }

        .message {
            font-size: 1.1rem;
            color: var(--text-dark);
            line-height: 1.7;
            margin-bottom: 35px;
            font-weight: 300;
            opacity: 0;
            animation: fade-in 1s ease-out 1s forwards;
        }
        
        @keyframes fade-in {
            to { opacity: 1; }
        }

        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0;
            animation: fade-in 1s ease-out 1.5s forwards;
        }

        .btn {
            padding: 14px 35px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color-1), var(--accent-color-2));
            color: white;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 8px 25px rgba(0, 242, 254, 0.3);
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.6s ease;
        }
        
        .btn:hover::before {
            transform: translate(-50%, -50%) scale(1);
        }

        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 3;
        }

        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #ff6b6b;
            animation: confetti-fall 4s linear infinite;
            top: -20px;
        }
        
        .confetti-piece.triangle {
             width: 0;
             height: 0;
             background: transparent;
             border-left: 5px solid transparent;
             border-right: 5px solid transparent;
             border-bottom: 10px solid var(--accent-color-1);
        }
        .confetti-piece.rectangle {
            width: 8px;
            height: 12px;
        }


        @keyframes confetti-fall {
            0% {
                transform: translateY(0vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(105vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .thank-you-card {
                padding: 40px 25px;
                margin: 20px;
            }
            
            .main-title {
                font-size: 2.5rem;
            }

            .subtitle {
                font-size: 1.2rem;
            }
            
            .buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                padding: 16px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="background-wrap">
        <div class="circle c1"></div>
        <div class="circle c2"></div>
    </div>
    
    <div class="confetti" id="confetti"></div>

    <div class="container">
        <div class="thank-you-card" id="thank-you-card">
            <div class="icon-container">
                <div class="success-icon">
                    <svg viewBox="0 0 100 100">
                        <circle class="check-bg" cx="50" cy="50" r="46"/>
                        <polyline class="check-mark" points="28,50 45,67 72,40" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <h1 class="main-title">Thank You!</h1>
            <p class="subtitle">Your Quiz is Complete!</p>

            <p class="message">
                Congratulations on completing the quiz! Your dedication and effort are truly commendable. 
                We hope you had a great experience.
            </p>

            <div class="buttons">
                <a href="quiz.php" class="btn btn-primary">Take Another Quiz</a>
                <a href="log.php" class="btn btn-secondary">Exit</a>
            </div>
        </div>
    </div>

    <script>
        // Create confetti animation
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            if (!confettiContainer) return;
            
            const colors = ['#ff6b6b', '#ffd93d', '#6bcf7f', '#4d9de0', '#9b59b6', '#00f2fe'];
            const shapes = ['square', 'triangle', 'rectangle'];
            
            const createPiece = () => {
                const confettiPiece = document.createElement('div');
                const shape = shapes[Math.floor(Math.random() * shapes.length)];

                confettiPiece.className = `confetti-piece ${shape}`;
                confettiPiece.style.left = Math.random() * 100 + 'vw';
                
                const color = colors[Math.floor(Math.random() * colors.length)];
                if (shape === 'triangle') {
                    confettiPiece.style.borderBottomColor = color;
                } else {
                    confettiPiece.style.backgroundColor = color;
                }

                confettiPiece.style.animationDuration = (Math.random() * 3 + 3) + 's';
                confettiPiece.style.animationDelay = Math.random() * 2 + 's';
                confettiPiece.style.transform = `rotate(${Math.random() * 360}deg)`;

                confettiContainer.appendChild(confettiPiece);

                setTimeout(() => {
                    confettiPiece.remove();
                }, 6000);
            };

            // Initial burst
            for (let i = 0; i < 50; i++) {
                setTimeout(createPiece, i * 50);
            }
            
            // Continue dropping
            setInterval(createPiece, 400);
        }

        // Add 3D tilt effect on card hover
        function addTiltEffect() {
            const card = document.getElementById('thank-you-card');
            if (!card) return;

            card.addEventListener('mousemove', function(e) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 20; // Reduced intensity
                const rotateY = (centerX - x) / 20; // Reduced intensity
                
                card.style.transform = `perspective(1500px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = 'perspective(1500px) rotateX(0deg) rotateY(0deg)';
            });
        }


        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(createConfetti, 500);
            addTiltEffect();
        });
    </script>
</body>
</html>