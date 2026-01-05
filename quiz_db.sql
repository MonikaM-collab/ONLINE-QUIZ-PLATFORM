-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 08:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quiz_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  `answer` varchar(255) NOT NULL,
  `created_by` varchar(255) DEFAULT 'admin',
  `created_at` datetime DEFAULT current_timestamp(),
  `generated_by_ai` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `question`, `options`, `answer`, `created_by`, `created_at`, `generated_by_ai`) VALUES
(1, 'Which of the following is the SI unit of mass?', '[\"Gram\",\"Pound\",\"Kilogram\",\"Ounce\"]', 'Kilogram', 'ai_generator', '2025-07-08 11:22:03', 1),
(2, 'What is the process by which plants convert light energy into chemical energy?', '[\"Respiration\",\"Photosynthesis\",\"Transpiration\",\"Digestion\"]', 'Photosynthesis', 'ai_generator', '2025-07-08 11:22:03', 1),
(3, 'Which of these planets is known as the \'blue Planet\'?', '[\"Venus\",\"Mars\",\"Jupiter\",\"Saturn\"]', 'Mars', 'ai_generator', '2025-07-08 11:22:03', 1),
(4, 'What is the chemical symbol for water?', '[\"CO2\",\"NaCl\",\"H2O\",\"O2\"]', 'H2O', 'ai_generator', '2025-07-08 11:22:03', 1),
(5, 'Which branch of science deals with the study of living organisms?', '[\"Physics\",\"Chemistry\",\"Biology\",\"Geology\"]', 'Biology', 'ai_generator', '2025-07-08 11:22:03', 1),
(6, 'what is the color of algae', '[\"green\",\"yellow\",\"black\",\"white\"]', 'green', 'admin', '2025-07-08 12:04:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `quiz_topic` varchar(255) NOT NULL,
  `num_questions` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `submission_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`id`, `username`, `quiz_topic`, `num_questions`, `score`, `total_questions`, `submission_time`) VALUES
(12, 'sri', 'biology', 10, 7, 10, '2025-07-11 12:22:19'),
(14, 'moni', 'html', 10, 6, 10, '2025-07-11 12:35:18'),
(15, 'aarthi', 'social', 10, 4, 10, '2025-07-11 12:40:48'),
(16, 'kesavan', 'maths', 10, 8, 10, '2025-07-11 12:46:49'),
(17, 'mano', 'science', 10, 7, 10, '2025-07-14 11:24:49'),
(20, 'ashvarthini', 'biology', 11, 8, 11, '2025-07-14 12:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `role`) VALUES
(3, 'mano', 'mano18@gmail.com', 'Mano@1805', '2025-07-07 23:31:10', 'user'),
(4, 'moni', 'moni11@gmail.com', 'Moni@1110', '2025-07-08 00:14:17', 'user'),
(5, 'sri', 'sri01@gmail.com', 'Sree@0110', '2025-07-08 09:24:22', 'user'),
(6, 'priya', 'priya@gmail.com', 'Priya@25', '2025-07-08 09:34:01', 'admin'),
(7, 'aarthi', 'Aarthi01@gmail.com', 'Aarthi@01', '2025-07-11 12:37:01', 'user'),
(8, 'kesavan', 'kesav07@gmail.com', 'Kesav@07', '2025-07-11 12:41:52', 'user'),
(9, 'ashvarthini', 'ashva@gmail.com', 'ashva44', '2025-07-14 12:40:16', 'user'),
(10, 'manju', 'manju@gmail.com', 'Manju@21', '2025-07-23 10:49:05', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
