-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 02, 2025 at 05:59 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 7.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `equipment_borrowing`
--
CREATE DATABASE IF NOT EXISTS `equipment_borrowing` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `equipment_borrowing`;

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned') DEFAULT 'borrowed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `user_id`, `equipment_id`, `quantity`, `borrow_date`, `return_date`, `status`, `created_at`) VALUES
(1, 2, 1, 2, '2025-05-01', '2025-05-01', 'returned', '2025-05-01 08:08:13'),
(2, 2, 6, 2, '2025-05-01', NULL, 'borrowed', '2025-05-01 08:08:13'),
(3, 2, 1, 5, '2025-05-01', NULL, 'borrowed', '2025-05-01 08:09:38'),
(4, 2, 5, 5, '2025-05-01', NULL, 'borrowed', '2025-05-01 08:17:35'),
(5, 2, 3, 2, '2025-05-02', NULL, 'borrowed', '2025-05-02 03:45:20'),
(6, 2, 2, 2, '2025-05-02', NULL, 'borrowed', '2025-05-02 03:45:20');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Electronics', '2025-05-01 06:19:53'),
(2, 'Tools', '2025-05-01 06:19:53');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `description`, `category_id`, `quantity`, `image`, `created_at`) VALUES
(1, 'Electronics 1', 'Electronics 1', 1, 17, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:30:50'),
(2, 'Tools1', 'Tools1', 2, 28, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:31:14'),
(3, 'Electronics 2', 'Electronics 2', 1, 8, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:30:50'),
(4, 'Tools2', 'Tools2', 2, 10, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:31:14'),
(5, 'Electronics 3', 'Electronics 3', 1, 2, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:30:50'),
(6, 'Tools3', 'Tools3', 2, 17, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:31:14'),
(10, 'Electronics 4', 'Electronics 4', 1, 2, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:30:50'),
(11, 'Tools4', 'Tools4', 2, 17, 'oinam-TJHXkCp2bBo-unsplash.jpg', '2025-05-01 06:31:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$J3Z5z7y2z3Y8Z9z1Z2Z3Z.j3Z5z7y2z3Y8Z9z1Z2Z3Z.j3Z5z7y2z', 'admin', '2025-05-01 06:19:53'),
(2, 'user', '$2y$10$vLyMfZ.Wg6ce8wS0L.TQ3u053p2LY.WDtW5ijzDJbfX.Pggh3gq/S', 'user', '2025-05-01 06:32:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`);

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
