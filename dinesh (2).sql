-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2026 at 05:07 PM
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
-- Database: `dinesh`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$DnsRRCeZpTV/XoHa2N8wVOQiibGRvaf9cTPAxHCOVllsliCIXmhs6');

-- --------------------------------------------------------

--
-- Table structure for table `booklet`
--

CREATE TABLE `booklet` (
  `id` int(11) NOT NULL,
  `u_id` int(11) DEFAULT NULL,
  `b_name` varchar(100) NOT NULL,
  `b_range` varchar(11) NOT NULL,
  `b_srnstart` varchar(11) NOT NULL,
  `b_srnend` varchar(11) NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booklet`
--

INSERT INTO `booklet` (`id`, `u_id`, `b_name`, `b_range`, `b_srnstart`, `b_srnend`, `create_date`) VALUES
(1, 7, 'Booklet1', '10', '1', '10', '2025-04-23 07:13:59'),
(10, 6, 'Booklet3', '10', '21', '30', '2025-05-14 05:38:54'),
(11, 5, 'Booklet4', '10', '31', '40', '2025-05-14 06:28:29'),
(12, 6, 'Booklet2', '10', '11', '20', '2025-05-20 04:19:42'),
(13, 5, 'Booklet5', '10', '41', '50', '2026-02-17 16:19:33'),
(14, 6, 'Booklet6', '10', '51', '60', '2026-02-18 17:28:33'),
(15, 8, 'Booklet7', '10', '61', '70', '2026-02-20 00:44:20'),
(16, 5, 'Booklet8', '10', '71', '80', '2026-02-20 15:22:31');

-- --------------------------------------------------------

--
-- Table structure for table `referrers`
--

CREATE TABLE `referrers` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(40) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `runner`
--

CREATE TABLE `runner` (
  `r_id` int(11) NOT NULL,
  `u_id` int(11) DEFAULT NULL,
  `b_id` int(11) DEFAULT NULL,
  `r_srn` int(11) NOT NULL,
  `r_name` varchar(150) NOT NULL,
  `r_contact` varchar(100) NOT NULL,
  `r_dob` date NOT NULL,
  `r_gender` varchar(10) NOT NULL,
  `r_bdgp` char(4) NOT NULL,
  `r_email` varchar(150) NOT NULL,
  `r_catgry` varchar(50) NOT NULL,
  `r_tshirt_sz` varchar(10) NOT NULL,
  `r_emrg_con` varchar(15) NOT NULL,
  `r_med_dt` varchar(150) NOT NULL,
  `r_fee` varchar(150) DEFAULT NULL,
  `r_payment_status` varchar(50) NOT NULL,
  `reg_dt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reg_type` varchar(20) DEFAULT 'booklet',
  `referral_code` varchar(40) DEFAULT NULL,
  `referrer_id` int(11) DEFAULT NULL,
  `referred_by_user` int(11) DEFAULT NULL,
  `ip_addr` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `runner`
--

INSERT INTO `runner` (`r_id`, `u_id`, `b_id`, `r_srn`, `r_name`, `r_contact`, `r_dob`, `r_gender`, `r_bdgp`, `r_email`, `r_catgry`, `r_tshirt_sz`, `r_emrg_con`, `r_med_dt`, `r_fee`, `r_payment_status`, `reg_dt`, `reg_type`, `referral_code`, `referrer_id`, `referred_by_user`, `ip_addr`, `user_agent`) VALUES
(3, 5, 13, 41, 'Bharat Gayari', '9876787654', '1990-02-10', 'Male', 'O+', 'ritikgayari@gmail.com', '10 KM', 'M', '8767564532', 'None', '500', 'online', '2026-02-23 16:03:07', 'booklet', NULL, NULL, NULL, NULL, NULL),
(5, 6, 14, 52, 'Bharat Gayari', '9876787654', '1990-02-19', 'Male', 'O+', 'bharatgayari11@gmail.com', '21.09 KM', 'S', '5464564666', 'NO', '00', '', '2026-02-19 16:16:14', 'booklet', NULL, NULL, NULL, NULL, NULL),
(6, 6, 14, 53, 'Bharat Gayari', '9876567890', '1990-02-19', 'Male', 'O+', 'bgayari11@gmail.com', '10 KM', 'M', '5464564666', 'NO', '00', '', '2026-02-19 01:12:45', 'booklet', NULL, NULL, NULL, NULL, NULL),
(7, 6, 14, 54, 'Bharat Gayari', '9876567890', '2026-02-19', 'Male', 'O+', 'ritikgayari@gmail.com', '10 KM', 'L', '8767564532', 'NO', '00', '', '2026-02-19 16:16:50', 'booklet', NULL, NULL, NULL, NULL, NULL),
(14, 6, 14, 55, 'Bharat Gayari', '9876567890', '2026-02-19', 'Male', 'B-', 'ritikgayari@gnail.com', '10 KM', 'L', '8767564532', 'NO', '00', '', '2026-02-19 15:41:34', 'booklet', NULL, NULL, NULL, NULL, NULL),
(18, 6, 14, 56, 'Anurag dadhich ', '9876574636', '1992-02-19', 'Male', 'B+', 'anuragdd@gmail.com', '21.09 KM', 'L', '8789098767', 'NO', '00', '', '2026-02-19 16:16:05', 'booklet', NULL, NULL, NULL, NULL, NULL),
(19, 6, 10, 21, 'Shailendra jain', '7654567898', '1990-01-19', 'Male', 'A+', 'shailooj1990@gmail.com', '21.09 KM', 'XL', '8909098734', 'None', '00', '', '2026-02-19 16:07:48', 'booklet', NULL, NULL, NULL, NULL, NULL),
(20, 5, 13, 42, 'Gourav sen', '8765432123', '1993-02-19', 'Male', 'B+', 'gorusen@gmail.com', '21.09 KM', 'L', '7896857433', 'NONE', '00', '', '2026-02-19 16:48:36', 'booklet', NULL, NULL, NULL, NULL, NULL),
(21, 6, 10, 22, 'Aashis ', '9876567678', '1993-02-20', 'Male', 'A+', 'aashishm2026@gmail.com', '21.09 KM', 'L', '8989871234', 'None', '00', '', '2026-02-20 00:47:33', 'booklet', NULL, NULL, NULL, NULL, NULL),
(22, 5, 13, 43, 'Puspendra singh ranawat', '7656763212', '2001-02-20', 'Male', 'B+', 'puspatherule@gmail.com', '5 KM', 'M', '8909675434', 'None', '00', '', '2026-02-21 07:08:41', 'booklet', NULL, NULL, NULL, NULL, NULL),
(23, 5, 11, 31, 'Shreya ', '9809876543', '2002-02-20', 'Female', 'A+', 'shryuseth@gmail.com', '5 KM', 'M', '8878789098', 'None', '00', '', '2026-02-20 16:41:15', 'booklet', NULL, NULL, NULL, NULL, NULL),
(24, 5, 11, 32, 'Ranu', '8765432123', '2008-05-21', 'Female', 'B+', 'ranukumari@gmail.com', '5 KM', 'S', '8989898997', 'None', '00', '', '2026-02-21 07:10:31', 'booklet', NULL, NULL, NULL, NULL, NULL),
(27, NULL, NULL, 0, 'Mina kumari', '8987654435', '2003-01-08', 'Female', 'AB-', 'minukumari20@gmail.com', '10K Run', 'M', '8976789090', 'None', NULL, '', '2026-02-21 14:16:38', 'public', '9D2B1CFD', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(28, 5, NULL, 0, 'Aashi sharma', '6785948321', '1989-12-30', 'Male', 'B+', 'aashisharma@zhoho.com', '10 KM', 'L', '6574664666', 'None', '500', 'offline', '2026-02-23 16:02:38', 'public', '3BBF4ECA', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(29, 5, 11, 33, 'Myank choudhary', '8767890986', '1996-02-23', 'Male', 'AB+', 'mayankchodhary@gmail.com', '10 KM', 'L', '9876568900', 'None', '500', 'offline', '2026-02-23 16:02:20', 'booklet', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `runner_public`
--

CREATE TABLE `runner_public` (
  `id` int(11) NOT NULL,
  `r_name` varchar(120) NOT NULL,
  `r_email` varchar(120) NOT NULL,
  `r_contact` varchar(20) NOT NULL,
  `r_gender` varchar(20) NOT NULL,
  `r_dob` date DEFAULT NULL,
  `r_bdgp` varchar(10) DEFAULT NULL,
  `r_catgry` varchar(50) DEFAULT NULL,
  `r_tshirt_sz` varchar(10) DEFAULT NULL,
  `r_emrg_con` varchar(20) DEFAULT NULL,
  `r_med_dt` varchar(255) DEFAULT NULL,
  `referral_code` varchar(40) DEFAULT NULL,
  `referrer_id` int(11) DEFAULT NULL,
  `ip_addr` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `type`
--

CREATE TABLE `type` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `type`
--

INSERT INTO `type` (`id`, `type`) VALUES
(1, 'Super Admin'),
(2, 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `u_type` int(11) NOT NULL,
  `fname` varchar(255) DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `referral_code` varchar(40) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(300) NOT NULL,
  `contactno` varchar(15) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `posting_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `u_type`, `fname`, `lname`, `referral_code`, `email`, `password`, `contactno`, `status`, `posting_date`) VALUES
(5, 1, 'Bharat', 'Gayari', '3BBF4ECA', 'bgayari11@gmail.com', '$2y$10$mujRfdYRttWocyAn21lraObMidO74aikCw6.8jRfUuJvKQyxByLNy', '9602831201', 1, '2025-04-23 06:08:34'),
(6, 2, 'Dinesh ', 'Meghwal', 'BC4CA6F8', 'dinesh.meg22@gmail.com', '$2y$10$JP5294m1hxuHi1tRcivY.uAkPIlPtwGmpKi0t1rGft3T0hdUSTEuO', '8675648903', 1, '2025-04-23 12:12:05'),
(7, 2, 'jigar', 'joshi', '467AE276', 'jigar@gmail.com', '$2y$10$i.8cdgAxf1WLZEr42x5WQ.TCsfF//mW/NjFryikeoXnqBM3P3bzdq', '9876578909', 1, '2025-05-14 04:53:19'),
(8, 2, 'PAWAN KUMAR', 'JEENWAL', '78A4CA6E', 'rjspawanjeenwal@gmail.com', 'Pawan@123', '9269864841', 1, '2025-07-16 10:56:23'),
(9, 1, 'Test', 'User', 'TESTCODE', 'test@test.com', 'hashedpass123', '9876543210', 1, '2026-02-21 16:12:57'),
(10, 2, 'Anurag', 'dadhich', 'F3F90C42', 'anurahdd@gmail.com', '$2y$10$fiQYiC6GISHXdanBxXoDuOprMAZyc8vVUSeR4vQsHDXvCu6hl3HVO', '9809876543', 1, '2026-02-21 16:28:16'),
(11, 2, 'Rohit', 'Kumar', 'D096B226', 'rkumar26@gmail.com', '$2y$10$/OnHZKTInzxapFF.EASiGOLrMRj2ZDVlGRpgFOvFHPfW4qpoXXu4i', '7654345678', 1, '2026-02-21 16:29:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booklet`
--
ALTER TABLE `booklet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booklet_user` (`u_id`);

--
-- Indexes for table `referrers`
--
ALTER TABLE `referrers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `runner`
--
ALTER TABLE `runner`
  ADD PRIMARY KEY (`r_id`),
  ADD KEY `fk_runner_user` (`u_id`),
  ADD KEY `fk_runner_booklet` (`b_id`),
  ADD KEY `fk_referred_user` (`referred_by_user`);

--
-- Indexes for table `runner_public`
--
ALTER TABLE `runner_public`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email` (`r_email`),
  ADD KEY `r_email` (`r_email`),
  ADD KEY `r_contact` (`r_contact`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `fk_referrer` (`referrer_id`);

--
-- Indexes for table `type`
--
ALTER TABLE `type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_referral` (`referral_code`),
  ADD KEY `fk_users_type` (`u_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booklet`
--
ALTER TABLE `booklet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `referrers`
--
ALTER TABLE `referrers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `runner`
--
ALTER TABLE `runner`
  MODIFY `r_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `runner_public`
--
ALTER TABLE `runner_public`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `type`
--
ALTER TABLE `type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booklet`
--
ALTER TABLE `booklet`
  ADD CONSTRAINT `fk_booklet_user` FOREIGN KEY (`u_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `runner`
--
ALTER TABLE `runner`
  ADD CONSTRAINT `fk_referred_user` FOREIGN KEY (`referred_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_runner_booklet` FOREIGN KEY (`b_id`) REFERENCES `booklet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_runner_user` FOREIGN KEY (`u_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `runner_public`
--
ALTER TABLE `runner_public`
  ADD CONSTRAINT `fk_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `referrers` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_type` FOREIGN KEY (`u_type`) REFERENCES `type` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
