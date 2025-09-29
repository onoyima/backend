-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 29, 2025 at 06:06 PM
-- Server version: 8.0.43-34
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbjdd49ygtpl1z`
--

-- --------------------------------------------------------

--
-- Table structure for table `vu_sessions`
--

CREATE TABLE `vu_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `session` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_adm_processed` tinyint(1) NOT NULL DEFAULT '0',
  `is_hostel_processed` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vu_sessions`
--

INSERT INTO `vu_sessions` (`id`, `session`, `start_date`, `end_date`, `is_adm_processed`, `is_hostel_processed`, `status`, `created_at`, `updated_at`) VALUES
(1, '2010/2011', '2010-10-08', '2011-08-30', 0, 0, 0, '2023-09-24 07:22:27', '2025-07-14 11:56:01'),
(2, '2009/2010', '2009-09-10', '2010-07-30', 0, 0, 0, '2023-09-24 07:22:33', '2025-07-14 11:56:01'),
(3, '2008/2009', '2008-09-01', '2009-08-28', 0, 0, 0, '2023-09-24 07:22:44', '2025-07-14 11:56:01'),
(4, '2011/2012', '2011-11-17', '2011-11-17', 0, 0, 0, '2023-09-24 07:22:51', '2025-07-14 11:56:01'),
(5, '2012/2013', '2012-10-12', '2013-07-25', 0, 0, 0, '2023-09-24 07:22:57', '2025-07-14 11:56:01'),
(6, '2013/2014', '2013-10-12', '2014-08-29', 0, 0, 0, '2023-09-24 07:23:02', '2025-07-14 11:56:01'),
(7, '2014/2015', '2014-11-03', '2015-07-31', 0, 0, 0, '2023-09-24 07:23:07', '2025-07-14 11:56:01'),
(8, '2015/2016', '2015-09-28', '2016-07-29', 0, 0, 0, '2023-09-24 07:23:13', '2025-07-14 11:56:01'),
(9, 'Disabled', '2015-09-14', '2016-07-22', 0, 0, 2, '2023-09-24 07:23:38', '2025-07-14 11:56:01'),
(10, '2016/2017', '2016-09-20', '2017-07-29', 0, 0, 0, '2023-09-24 07:23:45', '2025-07-14 11:56:01'),
(11, '2017/2018', '2017-09-13', '2018-07-27', 0, 0, 0, '2023-09-24 07:23:51', '2025-07-14 11:56:01'),
(12, '2018/2019', '2018-10-09', '2019-07-31', 0, 0, 0, '2023-09-24 07:23:55', '2025-07-14 11:56:01'),
(13, '2019/2020', '2019-10-04', '2020-07-31', 0, 0, 0, '2023-09-24 07:24:01', '2025-07-14 11:56:01'),
(14, '2020/2021', '2020-10-10', '2020-12-31', 0, 0, 0, '2023-09-24 07:24:06', '2025-07-14 11:56:01'),
(15, '2021/2022', '2021-10-15', '2022-07-29', 0, 0, 0, '2023-09-24 07:24:12', '2025-07-14 11:56:01'),
(16, '2022/2023', '2022-09-01', '2023-07-31', 0, 0, 0, '2023-09-24 07:24:18', '2025-07-14 11:56:01'),
(17, '2023/2024', '2024-06-14', '2024-06-14', 0, 0, 0, '2024-06-14 11:37:47', '2025-07-14 11:56:01'),
(18, '2024/2025', '2024-06-14', '2024-06-14', 0, 0, 1, '2024-06-14 12:57:45', '2025-07-14 11:56:01'),
(19, '2025/2026', '2025-07-09', '2025-07-09', 1, 1, 1, '2025-07-09 06:41:34', '2025-07-14 11:56:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `vu_sessions`
--
ALTER TABLE `vu_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vu_sessions_session_unique` (`session`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `vu_sessions`
--
ALTER TABLE `vu_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
