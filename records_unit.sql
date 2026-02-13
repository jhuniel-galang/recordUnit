-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 13, 2026 at 06:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `records_unit`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `document_id`, `description`, `created_at`) VALUES
(1, 1, 'UPLOAD', NULL, 'Uploaded 26A0  - Advice  for Appointment_1.PDF for Alasas ES', '2026-02-05 01:39:33'),
(2, 3, 'UPLOAD', 4, 'Uploaded 26G004 Mr. Canchela - letter of information.pdf for Alasas ES', '2026-02-05 05:28:29'),
(6, 1, 'ARCHIVE', NULL, 'Archived Locator-Slip cheska.docx from Calulut IS', '2026-02-05 08:03:34'),
(7, 1, 'ARCHIVE', NULL, 'Archived 26A0  - Advice  for Appointment_1.PDF from Alasas ES', '2026-02-05 08:13:29'),
(9, 1, 'EDIT', NULL, 'Updated document details (School: Calulut IS)', '2026-02-05 08:21:41'),
(10, 3, 'UPLOAD', 5, 'Uploaded 26G013  SDO Pampanga - transfer of Ms. Sagucio.PDF for Calulut IS', '2026-02-05 08:23:11'),
(11, 1, 'UPLOAD', 6, 'Uploaded 26G014 Mr. Manlapaz of Travel Learning Experience - Letter response.PDF for Baliti IS', '2026-02-05 08:40:05'),
(12, 1, 'EDIT', 6, 'Updated document details (School: Baliti IS)', '2026-02-05 08:40:35'),
(13, 1, 'UPLOAD', 7, 'Uploaded 26G005 Ms. Cruz - letter of information.pdf for Baliti IS', '2026-02-11 01:14:44'),
(14, 1, 'ARCHIVE', NULL, 'Archived Locator-Slip cheska.docx from Calulut IS', '2026-02-11 01:20:03'),
(15, 3, 'UPLOAD', 8, 'Uploaded 26G013  SDO Pampanga - transfer of Ms. Sagucio.PDF for Alasas ES', '2026-02-11 02:33:03'),
(16, 1, 'UPLOAD', 9, 'Uploaded 26G021 Ms. Manarang - Approved request to conduct study & interview.pdf for Baliti IS', '2026-02-11 02:39:01'),
(17, 1, 'UPLOAD', 10, 'Uploaded 26G081.pdf for Calulut IS', '2026-02-11 02:39:16'),
(18, 1, 'UPLOAD', 11, 'Uploaded 26G086 Mr. Pawayal - Letter response.PDF for Baliti IS', '2026-02-11 02:39:26'),
(19, 1, 'UPLOAD', 12, 'Uploaded 26G097 Ms. Camacho - Approved request to conduct study & interview.PDF for Alasas ES', '2026-02-11 02:39:36'),
(20, 1, 'UPLOAD', 13, 'Uploaded 26G025_0001.pdf for Calulut IS', '2026-02-11 02:39:48'),
(21, 3, 'UPLOAD', 14, 'Uploaded 26G006 Mr. Pamintuan - letter of information.pdf for Baliti IS', '2026-02-11 02:45:45'),
(22, 3, 'UPLOAD', 15, 'Uploaded 26G072 Dr. Ballena - Letter response.pdf for Alasas ES', '2026-02-11 02:48:29'),
(23, 1, 'EDIT', 15, 'Updated document details (School: Alasas ES)', '2026-02-11 07:12:40'),
(24, 1, 'EDIT', 1, 'Updated document details (School: Alasas ES)', '2026-02-11 07:21:53'),
(26, 1, 'ARCHIVE', 13, 'Archived 26G025_0001.pdf from Calulut IS', '2026-02-13 05:18:51'),
(27, 1, 'PERMANENT DELETE', NULL, 'Permanently deleted 26A0  - Advice  for Appointment_1.PDF from Alasas ES', '2026-02-13 05:27:12'),
(28, 1, 'UPLOAD', 16, 'Uploaded 26A0  - Advice  for Appointment_1.PDF for Alasas ES', '2026-02-13 05:43:57');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_name` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `file_size` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `school_name`, `file_name`, `file_path`, `file_type`, `file_size`, `remarks`, `uploaded_at`, `deleted_at`, `status`) VALUES
(1, 1, 'Alasas ES', '26A0  - Advice  for Appointment_1.PDF', 'uploads/1770107126_6981b0f679f72.pdf', 'pdf', 523397, 'this is a remarks feb 11', '2026-02-03 08:25:26', NULL, 1),
(4, 3, 'Alasas ES', '26G004 Mr. Canchela - letter of information.pdf', 'uploads/1770269309_69842a7d4ae78.pdf', 'pdf', 413599, 'this is remarks', '2026-02-05 05:28:29', NULL, 1),
(5, 3, 'Calulut IS', '26G013  SDO Pampanga - transfer of Ms. Sagucio.PDF', 'uploads/1770279791_6984536f70ea4.pdf', 'pdf', 675532, 'dsadas', '2026-02-05 08:23:11', NULL, 1),
(6, 1, 'Baliti IS', '26G014 Mr. Manlapaz of Travel Learning Experience - Letter response.PDF', 'uploads/1770280805_69845765dcde6.pdf', 'pdf', 901198, 'remark22', '2026-02-05 08:40:05', NULL, 1),
(7, 1, 'Baliti IS', '26G005 Ms. Cruz - letter of information.pdf', 'uploads/1770772484_698bd80464ae3.pdf', 'pdf', 397604, 'jijkkdf', '2026-02-11 01:14:44', NULL, 1),
(8, 3, 'Alasas ES', '26G013  SDO Pampanga - transfer of Ms. Sagucio.PDF', 'uploads/1770777183_698bea5fc91a3.pdf', 'pdf', 675532, 'fffddf', '2026-02-11 02:33:03', NULL, 1),
(9, 1, 'Baliti IS', '26G021 Ms. Manarang - Approved request to conduct study & interview.pdf', 'uploads/1770777541_698bebc5757f3.pdf', 'pdf', 657631, 'dsadsa', '2026-02-11 02:39:01', NULL, 1),
(10, 1, 'Calulut IS', '26G081.pdf', 'uploads/1770777556_698bebd42c7b8.pdf', 'pdf', 669101, 'dsadsa', '2026-02-11 02:39:16', NULL, 1),
(11, 1, 'Baliti IS', '26G086 Mr. Pawayal - Letter response.PDF', 'uploads/1770777566_698bebde7621d.pdf', 'pdf', 1289148, 'dasdas', '2026-02-11 02:39:26', NULL, 1),
(12, 1, 'Alasas ES', '26G097 Ms. Camacho - Approved request to conduct study & interview.PDF', 'uploads/1770777576_698bebe8e7878.pdf', 'pdf', 462561, 'zcxzxcsa', '2026-02-11 02:39:36', NULL, 1),
(13, 1, 'Calulut IS', '26G025_0001.pdf', 'uploads/1770777588_698bebf40a33d.pdf', 'pdf', 252253, 'zxczxc', '2026-02-11 02:39:48', NULL, 0),
(14, 3, 'Baliti IS', '26G006 Mr. Pamintuan - letter of information.pdf', 'uploads/1770777945_698bed5948121.pdf', 'pdf', 405577, 'this is a remarks feb 11', '2026-02-11 02:45:45', NULL, 1),
(15, 3, 'Alasas ES', '26G072 Dr. Ballena - Letter response.pdf', 'uploads/1770778109_698bedfd71e94.pdf', 'pdf', 1074685, 'sadsad', '2026-02-11 02:48:29', NULL, 1),
(16, 1, 'Alasas ES', '26A0  - Advice  for Appointment_1.PDF', 'uploads/1770961437_698eba1d4cc79.pdf', 'pdf', 449383, 'this is a remarks', '2026-02-13 05:43:57', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','uploader') NOT NULL DEFAULT 'uploader'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `role`) VALUES
(1, 'jhuniel', 'jhuniel@gmail.com', '$2y$10$pZT5tG/ECEdcB8gbAj7Z2ukAP9x1EfVDPm5BNnJbftucTNUNdI/3C', '2026-02-03 08:11:42', 'admin'),
(3, 'uploader1', 'up@gmail.com', '$2y$10$6X8XrCAj9pDZH9JY8DzDXeZ0RrNQ0WJt3.QF5RjBf26yuo6Wa8f56', '2026-02-05 05:24:24', 'uploader');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
