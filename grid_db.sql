-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2026 at 09:39 PM
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
-- Database: `grid_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id` char(36) NOT NULL,
  `project_id` char(36) DEFAULT NULL,
  `nama_aset` varchar(100) NOT NULL,
  `kategori` enum('Sprite','Audio','Script','Other') NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `ukuran_kb` int(11) DEFAULT NULL,
  `format` varchar(20) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `versi` varchar(20) DEFAULT '1.0',
  `uploader_id` char(36) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `project_id`, `nama_aset`, `kategori`, `file_url`, `thumbnail_url`, `ukuran_kb`, `format`, `tags`, `versi`, `uploader_id`, `status`, `created_at`) VALUES
('1b6aab54-0ea4-43d9-9195-601b827c52ab', 'e80e9911-9b67-4a95-bb4f-6828b3a658f2', 'apa aja', 'Sprite', '/uploads/sprite/1781525144_RobloxScreenShot20250306_055952482png', NULL, 2031, 'png', '', '1.0', NULL, 'Approved', '2026-06-15 12:05:44'),
('b36cd18a-0fa7-4dd9-b2de-4b2c83381c83', '633f7dce-d5f1-46fc-8cdd-50289cd86471', 'apa aja3', 'Sprite', '/uploads/sprite/1781530544_RobloxScreenShot20250306_055952482png', NULL, 2031, 'png', '', '1.0', 'd530beb7-5d28-4988-bfc2-e54b582fade6', 'Approved', '2026-06-15 13:35:44'),
('e7233bc8-0a19-4204-b746-00e73bc7c98b', 'e80e9911-9b67-4a95-bb4f-6828b3a658f2', 'apa aja2', 'Sprite', '/uploads/sprite/1781525385_RobloxScreenShot20250306_055952482png', NULL, 2031, 'png', '', '1.0', NULL, 'Approved', '2026-06-15 12:09:45');

-- --------------------------------------------------------

--
-- Table structure for table `bug_reports`
--

DROP TABLE IF EXISTS `bug_reports`;
CREATE TABLE `bug_reports` (
  `id` char(36) NOT NULL,
  `project_id` char(36) DEFAULT NULL,
  `task_id` char(36) DEFAULT NULL COMMENT 'Opsional: terkait task di Kanban',
  `reporter_id` char(36) DEFAULT NULL COMMENT 'User yang melaporkan',
  `judul` varchar(200) NOT NULL,
  `deskripsi` text NOT NULL,
  `langkah` text DEFAULT NULL COMMENT 'Langkah reproduksi',
  `prioritas` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bug_reports`
--

INSERT INTO `bug_reports` (`id`, `project_id`, `task_id`, `reporter_id`, `judul`, `deskripsi`, `langkah`, `prioritas`, `status`, `created_at`, `updated_at`) VALUES
('1571d3f1-ff6a-478a-ae7c-29e5003a86dc', '633f7dce-d5f1-46fc-8cdd-50289cd86471', NULL, 'd530beb7-5d28-4988-bfc2-e54b582fade6', 'menakutkan', 'ada serangga', '1. duar', 'Medium', 'Open', '2026-06-15 18:55:50', '2026-06-15 18:55:50');

-- --------------------------------------------------------

--
-- Table structure for table `devlogs`
--

DROP TABLE IF EXISTS `devlogs`;
CREATE TABLE `devlogs` (
  `id` char(36) NOT NULL,
  `project_id` char(36) DEFAULT NULL,
  `penulis_id` char(36) DEFAULT NULL,
  `judul` varchar(200) NOT NULL,
  `konten` text NOT NULL,
  `kategori` enum('Update','Bugfix','Feature','Announcement') NOT NULL,
  `tags` text DEFAULT NULL,
  `status` enum('Draft','Published') DEFAULT 'Draft',
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` char(36) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kode_unik` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `owner_id` char(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `nama`, `kode_unik`, `deskripsi`, `owner_id`, `created_at`) VALUES
('245f27e1-0f73-4ce2-9d4e-1e0b1b1ddc76', 'Kota Saya Solo', 'KOTASAYASOLO', NULL, '211ca861-545a-4fd1-8a2c-b06c07e52507', '2026-06-15 13:18:41');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` char(36) NOT NULL,
  `nama_game` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `genre` varchar(50) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `engine` varchar(50) DEFAULT NULL,
  `status` enum('Planning','Development','Testing','Released','On Hold') DEFAULT 'Planning',
  `cover_url` varchar(255) DEFAULT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `target_rilis` date DEFAULT NULL,
  `lead_id` char(36) DEFAULT NULL,
  `organization_id` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `nama_game`, `deskripsi`, `genre`, `platform`, `engine`, `status`, `cover_url`, `tanggal_mulai`, `target_rilis`, `lead_id`, `organization_id`) VALUES
('633f7dce-d5f1-46fc-8cdd-50289cd86471', 'celeste', 'des', 'aaa', 'PC', 'Unity', 'Planning', NULL, '2026-06-15', '2026-06-25', 'd530beb7-5d28-4988-bfc2-e54b582fade6', '245f27e1-0f73-4ce2-9d4e-1e0b1b1ddc76'),
('e80e9911-9b67-4a95-bb4f-6828b3a658f2', 'fawf', 'wadawfa', 'afaf', 'afaff', 'afafa', 'Planning', NULL, '2026-06-14', '2026-06-26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

DROP TABLE IF EXISTS `project_members`;
CREATE TABLE `project_members` (
  `id` char(36) NOT NULL,
  `project_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_members`
--

INSERT INTO `project_members` (`id`, `project_id`, `user_id`, `joined_at`) VALUES
('bf4a2a2b-0e63-4e21-9b50-957525ab09f3', '633f7dce-d5f1-46fc-8cdd-50289cd86471', 'd530beb7-5d28-4988-bfc2-e54b582fade6', '2026-06-15 13:34:47');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` char(36) NOT NULL,
  `project_id` char(36) DEFAULT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status_kolom` enum('To Do','In Progress','Review','Done') DEFAULT 'To Do',
  `prioritas` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `assignee_id` char(36) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `estimasi_jam` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Member','Guest') DEFAULT 'Guest',
  `avatar_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `organization_id` char(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `nama_lengkap`, `email`, `password_hash`, `role`, `avatar_url`, `is_active`, `organization_id`, `created_at`) VALUES
('211ca861-545a-4fd1-8a2c-b06c07e52507', 'jokowi', 'pangeran nipunegoro mangku janda limo', 'jkw@gmail.com', '$2y$10$ansdDrtA0.wLjd4KIk5bIel6ub2gkRZMGFL.RM5EX5ZtQrio/U0F2', 'Admin', NULL, 1, '245f27e1-0f73-4ce2-9d4e-1e0b1b1ddc76', '2026-06-15 13:18:41'),
('d530beb7-5d28-4988-bfc2-e54b582fade6', 'budi', 'budiono', 'budi@gmail.com', '$2y$10$gVXDZy5PpfSefxeCblhi6Ot.QIFei0St83o4rPFSvJ3gqiG.KG1eu', 'Member', NULL, 1, '245f27e1-0f73-4ce2-9d4e-1e0b1b1ddc76', '2026-06-15 13:33:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `uploader_id` (`uploader_id`);

--
-- Indexes for table `bug_reports`
--
ALTER TABLE `bug_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `devlogs`
--
ALTER TABLE `devlogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `penulis_id` (`penulis_id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_unik` (`kode_unik`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `projects_ibfk_org` (`organization_id`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_member` (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assignee_id` (`assignee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `users_ibfk_org` (`organization_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bug_reports`
--
ALTER TABLE `bug_reports`
  ADD CONSTRAINT `bug_reports_ibfk_proj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bug_reports_ibfk_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bug_reports_ibfk_user` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `devlogs`
--
ALTER TABLE `devlogs`
  ADD CONSTRAINT `devlogs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `devlogs_ibfk_2` FOREIGN KEY (`penulis_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_ibfk_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
