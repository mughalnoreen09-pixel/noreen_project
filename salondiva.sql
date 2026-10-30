-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2026 at 09:36 PM
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
-- Database: `salondiva`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `stylist_user_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `service_name_snapshot` varchar(120) NOT NULL,
  `service_price_snapshot` decimal(10,2) NOT NULL,
  `status` enum('confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'confirmed',
  `notes` varchar(1000) DEFAULT NULL,
  `manage_token_hash` char(64) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `booking_code`, `customer_id`, `stylist_user_id`, `service_id`, `starts_at`, `ends_at`, `service_name_snapshot`, `service_price_snapshot`, `status`, `notes`, `manage_token_hash`, `created_by`, `cancelled_at`, `cancelled_by`, `created_at`, `updated_at`) VALUES
(1, 'SD-02122ED6', 2, 6, 2, '2026-10-27 12:00:00', '2026-10-27 12:30:00', 'ddsaasad@', 2000.00, 'confirmed', NULL, '3e30c7c748f49507c04d8b8621bc8b8e87a0bf75909bfe7847fbb46c15a8e835', NULL, NULL, NULL, '2026-10-26 18:51:45', '2026-10-26 18:51:45'),
(2, 'SD-CA909EFA', 2, 6, 1, '2026-10-28 15:30:00', '2026-10-28 16:00:00', 'haricut', 1200.00, 'confirmed', NULL, '2445f0dc2f141b08c4589c1e78816eba755791a7d334bd48f73830634e7a8464', NULL, NULL, NULL, '2026-10-26 19:12:41', '2026-10-26 19:12:41'),
(3, 'SD-C1C4D253', 3, 6, 2, '2026-10-28 16:00:00', '2026-10-28 16:30:00', 'ddsaasad@', 2000.00, 'confirmed', NULL, '215db56bcfdc8530e2effdbd29c27cd35c12d9508fd0eedee3688b279c61e522', NULL, NULL, NULL, '2026-10-26 20:12:11', '2026-10-26 20:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_notifications`
--

CREATE TABLE `appointment_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `recipient` varchar(254) NOT NULL,
  `event_type` enum('confirmation','rescheduled','cancelled') NOT NULL,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
  `result_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_notifications`
--

INSERT INTO `appointment_notifications` (`id`, `appointment_id`, `channel`, `recipient`, `event_type`, `status`, `result_message`, `created_at`) VALUES
(1, 1, 'sms', '4556', 'confirmation', 'skipped', 'Skipped: configure Twilio environment variables and enable PHP cURL to send SMS.', '2026-10-26 18:51:45'),
(2, 2, 'sms', '4556', 'confirmation', 'skipped', 'Skipped: configure Twilio environment variables and enable PHP cURL to send SMS.', '2026-10-26 19:12:41'),
(3, 3, 'sms', 's', 'confirmation', 'skipped', 'Skipped: configure Twilio environment variables and enable PHP cURL to send SMS.', '2026-10-26 20:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `notes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'sdd', NULL, 'ddd', NULL, 1, 1, '2026-10-26 16:48:37', '2026-10-26 16:48:37'),
(2, 'eee', NULL, '4556', NULL, 1, NULL, '2026-10-26 18:51:45', '2026-10-26 18:51:45'),
(3, '<?php declare(strict_types=1); date_default_timezone_set(\'Asia/Karachi\'); require_once __DIR__ . \'/../app/bootstrap.php\'', NULL, 's', NULL, 1, NULL, '2026-10-26 20:12:11', '2026-10-26 20:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(120) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'unit',
  `quantity_on_hand` decimal(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `sku`, `name`, `category`, `unit`, `quantity_on_hand`, `reorder_level`, `unit_cost`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'eee', 'eee', 'unit', 2338.994, 0.000, 2220.00, 1, 1, '2026-10-26 18:35:49', '2026-10-26 20:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('restock','use','damage','adjustment') NOT NULL,
  `quantity_delta` decimal(12,3) NOT NULL,
  `quantity_before` decimal(12,3) NOT NULL,
  `quantity_after` decimal(12,3) NOT NULL,
  `unit_cost_snapshot` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `item_id`, `movement_type`, `quantity_delta`, `quantity_before`, `quantity_after`, `unit_cost_snapshot`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'restock', 2340.000, 0.000, 2340.000, 2220.00, NULL, 1, '2026-10-26 18:36:04'),
(2, 1, 'use', -1.006, 2340.000, 2338.994, 2220.00, 'Sold on invoice SD-2026-000001', 10, '2026-10-26 20:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(32) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','partially_paid','paid','void') NOT NULL DEFAULT 'open',
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `appointment_id`, `invoice_date`, `due_date`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SD-2026-000001', 2, NULL, '2026-10-27', '2026-11-03', 0.00, 0.00, 0.00, 0.00, 'open', NULL, 10, '2026-10-26 20:10:57', '2026-10-26 20:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `item_type` enum('service','product') NOT NULL,
  `description` varchar(200) NOT NULL,
  `appointment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `inventory_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `description`, `appointment_id`, `inventory_item_id`, `quantity`, `unit_price`, `line_total`, `created_at`) VALUES
(1, 1, 'product', 'eee', NULL, 1, 1.006, 0.00, 0.00, '2026-10-26 20:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `login_key` char(64) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` enum('appointment','invoice','inventory') NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_type` enum('customer','staff','admin') NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `recipient` varchar(254) NOT NULL,
  `dedupe_key` varchar(190) NOT NULL,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
  `result_message` varchar(500) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `entity_type`, `entity_id`, `recipient_type`, `channel`, `event_type`, `recipient`, `dedupe_key`, `status`, `result_message`, `attempted_at`, `sent_at`, `created_at`) VALUES
(1, 'appointment', 1, 'customer', 'sms', 'appointment_reminder_24h', '4556', 'appt24_1_customer_sms', 'skipped', 'Configure Twilio environment credentials and enable PHP cURL to send SMS.', '2026-10-27 01:09:54', NULL, '2026-10-26 20:09:54'),
(2, 'appointment', 1, 'staff', 'email', 'appointment_reminder_24h', 'nkapsheer450@gmail.com', 'appt24_1_staff_email', 'skipped', 'Set SALONDIVA_MAIL_FROM and configure a PHP mail transport.', '2026-10-27 01:09:54', NULL, '2026-10-26 20:09:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','mobile_wallet','other') NOT NULL DEFAULT 'cash',
  `reference_number` varchar(120) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `duration_minutes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'haricut', 'ddd', 1200.00, 30, 1, 1, '2026-10-26 16:41:12', '2026-10-26 17:44:38'),
(2, 'ddsaasad@', NULL, 2000.00, 30, 1, 1, '2026-10-26 17:44:48', '2026-10-26 17:44:48'),
(3, 'Raj', NULL, 30000.00, 60, 1, 1, '2026-10-26 18:39:16', '2026-10-26 18:39:16');

-- --------------------------------------------------------

--
-- Table structure for table `service_commissions`
--

CREATE TABLE `service_commissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stylist_user_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `performed_at` datetime NOT NULL,
  `service_name_snapshot` varchar(120) NOT NULL,
  `service_price` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','paid','void') NOT NULL DEFAULT 'pending',
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_commissions`
--

INSERT INTO `service_commissions` (`id`, `stylist_user_id`, `service_id`, `customer_id`, `performed_at`, `service_name_snapshot`, `service_price`, `commission_rate`, `commission_amount`, `status`, `notes`, `created_by`, `approved_by`, `paid_by`, `approved_at`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 6, 2, 1, '2026-10-26 19:31:00', 'ddsaasad@', 2000.00, 20.00, 400.00, 'void', NULL, 1, NULL, NULL, NULL, NULL, '2026-10-26 18:31:40', '2026-10-26 18:31:44'),
(2, 6, 2, NULL, '2026-10-26 19:54:00', 'ddsaasad@', 2000.00, 20.00, 400.00, 'approved', NULL, 1, 1, NULL, '2026-10-26 23:54:20', NULL, '2026-10-26 18:54:17', '2026-10-26 18:54:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(40) DEFAULT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`user_id`, `phone`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `commission_rate`, `notes`, `created_at`, `updated_at`) VALUES
(2, NULL, NULL, NULL, NULL, 0.00, NULL, '2026-10-26 17:00:06', '2026-10-26 17:00:06'),
(5, NULL, NULL, NULL, NULL, 0.00, NULL, '2026-10-26 17:00:06', '2026-10-26 17:00:06'),
(6, '03323625115', 'DHA Phase 2', 'Nitesh Kapsheer', NULL, 20.00, NULL, '2026-10-26 18:12:27', '2026-10-26 18:12:27');

-- --------------------------------------------------------

--
-- Table structure for table `staff_shifts`
--

CREATE TABLE `staff_shifts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_user_id` bigint(20) UNSIGNED NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(120) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_shifts`
--

INSERT INTO `staff_shifts` (`id`, `staff_user_id`, `shift_date`, `start_time`, `end_time`, `location`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 6, '2026-10-26', '09:00:00', '17:00:00', NULL, 'scheduled', NULL, 1, '2026-10-26 18:31:28', '2026-10-26 18:31:28');

-- --------------------------------------------------------

--
-- Table structure for table `staff_tasks`
--

CREATE TABLE `staff_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assigned_to` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_tasks`
--

INSERT INTO `staff_tasks` (`id`, `assigned_to`, `title`, `description`, `due_at`, `priority`, `status`, `created_by`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 6, 'ww', NULL, NULL, 'normal', 'pending', 1, NULL, '2026-10-26 18:12:46', '2026-10-26 18:12:46');

-- --------------------------------------------------------

--
-- Table structure for table `staff_weekly_schedule`
--

CREATE TABLE `staff_weekly_schedule` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_user_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL COMMENT '1=Monday through 7=Sunday',
  `is_day_off` tinyint(1) NOT NULL DEFAULT 0,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_weekly_schedule`
--

INSERT INTO `staff_weekly_schedule` (`id`, `staff_user_id`, `day_of_week`, `is_day_off`, `start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 0, '09:00:00', '17:00:00', '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(2, 2, 2, 0, '09:00:00', '17:00:00', '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(3, 2, 3, 0, '09:00:00', '17:00:00', '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(4, 2, 4, 0, '09:00:00', '17:00:00', '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(5, 2, 5, 0, '09:00:00', '17:00:00', '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(6, 2, 6, 1, NULL, NULL, '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(7, 2, 7, 1, NULL, NULL, '2026-10-26 18:12:57', '2026-10-26 18:12:57'),
(8, 6, 1, 1, NULL, NULL, '2026-10-26 18:30:54', '2026-10-26 18:31:06'),
(9, 6, 2, 0, '09:00:00', '17:00:00', '2026-10-26 18:30:54', '2026-10-26 18:30:54'),
(10, 6, 3, 0, '09:00:00', '17:00:00', '2026-10-26 18:30:54', '2026-10-26 18:30:54'),
(11, 6, 4, 0, '09:00:00', '17:00:00', '2026-10-26 18:30:54', '2026-10-26 18:30:54'),
(12, 6, 5, 0, '09:00:00', '17:00:00', '2026-10-26 18:30:54', '2026-10-26 18:30:54'),
(13, 6, 6, 1, NULL, NULL, '2026-10-26 18:30:54', '2026-10-26 18:30:54'),
(14, 6, 7, 1, NULL, NULL, '2026-10-26 18:30:54', '2026-10-26 18:30:54');

-- --------------------------------------------------------

--
-- Table structure for table `stylist_services`
--

CREATE TABLE `stylist_services` (
  `staff_user_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stylist_services`
--

INSERT INTO `stylist_services` (`staff_user_id`, `service_id`, `is_active`, `assigned_by`, `assigned_at`) VALUES
(6, 1, 1, 1, '2026-10-26 18:54:02'),
(6, 2, 1, 1, '2026-10-26 18:31:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(254) NOT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','receptionist','stylist') NOT NULL DEFAULT 'stylist',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `account_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password_hash`, `role`, `is_active`, `account_status`, `created_at`, `updated_at`) VALUES
(1, 'Salon Admin', 'admin@example.com', 'admin', '$2y$10$WHuK1xTCKWLvX8Wd/HvGhOQdwEcLrNinco9tABiklmw/E5s9emyMG', 'admin', 1, 'approved', '2026-10-26 15:51:47', '2026-10-26 16:17:34'),
(2, 'Ayesha Khan', 'receptionist@example.com', 'ayesha.reception', '$2y$10$ksZ7ZCaDrSDu3ZTzJDpeTOrCearQq2jTX7bj9JBcTAhrQV0E4biz2', 'receptionist', 1, 'approved', '2026-10-26 16:21:15', '2026-10-26 16:21:24'),
(5, 'receptionist@exaple.com', 'receptionist@exaple.com', 'ayesha.recepton', '$2y$10$dqYNpALz13/uWwokTuzLpu9USnUGcmlkx4ihiGkmVsF.2YN813Tfm', 'stylist', 1, 'rejected', '2026-10-26 16:22:57', '2026-10-26 16:23:04'),
(6, 'ddsaasad@', 'nkapsheer450@gmail.com', 'nitesh', '$2y$10$vurLxSxPWQIy8CPWHrBVBenYUiVAAd5vJd1xnvb7mFmZRMFvB8Dom', 'stylist', 1, 'approved', '2026-10-26 17:44:04', '2026-10-26 17:44:10'),
(8, 'shaid kapoor', 'nkapsheer530@gmail.com', 'nitesher', '$2y$10$PluWpRxpeE6Zmtsz54xdhOeDki1g0vPMb83NSopJGI.t.1OowVswi', 'receptionist', 1, 'approved', '2026-10-26 20:01:41', '2026-10-26 20:01:57'),
(9, 'salman khan', 'salmankhan@gmail.com', 'salman', '$2y$10$1L0FEGxbn30yUt1jpVqS0OGDq.1Vx3xH.AkxcTZ7Aq7EKWxuc0ZJK', 'stylist', 1, 'approved', '2026-10-26 20:03:47', '2026-10-26 20:03:56'),
(10, 'shahrukh khan', 'shahrukhkhan@gmail.com', 'shahrukhkhan', '$2y$10$MMCFFEK14IzpqJuxIp6DWe6eZ7h9oABgq8WSv0o3jusxDj9raUilu', 'receptionist', 1, 'approved', '2026-10-26 20:09:12', '2026-10-26 20:09:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appointments_booking_code` (`booking_code`),
  ADD KEY `idx_appointments_stylist_time` (`stylist_user_id`,`starts_at`,`ends_at`,`status`),
  ADD KEY `idx_appointments_customer_time` (`customer_id`,`starts_at`),
  ADD KEY `idx_appointments_start_status` (`starts_at`,`status`),
  ADD KEY `fk_appointments_service` (`service_id`),
  ADD KEY `fk_appointments_creator` (`created_by`),
  ADD KEY `fk_appointments_canceller` (`cancelled_by`);

--
-- Indexes for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointment_notifications_appointment` (`appointment_id`,`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_name` (`name`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_customers_phone` (`phone`),
  ADD KEY `idx_customers_active_name` (`is_active`,`name`),
  ADD KEY `fk_customers_created_by` (`created_by`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_items_sku` (`sku`),
  ADD KEY `idx_inventory_items_active_name` (`is_active`,`name`),
  ADD KEY `idx_inventory_items_stock` (`quantity_on_hand`,`reorder_level`),
  ADD KEY `fk_inventory_items_creator` (`created_by`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_movements_item_date` (`item_id`,`created_at`),
  ADD KEY `idx_inventory_movements_date_type` (`created_at`,`movement_type`),
  ADD KEY `fk_inventory_movements_creator` (`created_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  ADD UNIQUE KEY `uq_invoices_appointment` (`appointment_id`),
  ADD KEY `idx_invoices_customer_date` (`customer_id`,`invoice_date`),
  ADD KEY `idx_invoices_status_due` (`status`,`due_date`),
  ADD KEY `fk_invoices_creator` (`created_by`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_items_invoice` (`invoice_id`),
  ADD KEY `idx_invoice_items_product` (`inventory_item_id`),
  ADD KEY `fk_invoice_items_appointment` (`appointment_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_key_time` (`login_key`,`attempted_at`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notification_dedupe` (`dedupe_key`),
  ADD KEY `idx_notification_recent` (`created_at`,`status`),
  ADD KEY `idx_notification_entity` (`entity_type`,`entity_id`,`event_type`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_invoice_date` (`invoice_id`,`paid_at`),
  ADD KEY `fk_payments_receiver` (`received_by`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_services_active_name` (`is_active`,`name`),
  ADD KEY `fk_services_created_by` (`created_by`);

--
-- Indexes for table `service_commissions`
--
ALTER TABLE `service_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_commission_stylist_status_date` (`stylist_user_id`,`status`,`performed_at`),
  ADD KEY `idx_commission_status_date` (`status`,`performed_at`),
  ADD KEY `fk_commission_service` (`service_id`),
  ADD KEY `fk_commission_customer` (`customer_id`),
  ADD KEY `fk_commission_creator` (`created_by`),
  ADD KEY `fk_commission_approver` (`approved_by`),
  ADD KEY `fk_commission_payer` (`paid_by`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_shifts_date` (`shift_date`,`staff_user_id`),
  ADD KEY `fk_staff_shifts_staff` (`staff_user_id`),
  ADD KEY `fk_staff_shifts_creator` (`created_by`);

--
-- Indexes for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_tasks_assignee_status_due` (`assigned_to`,`status`,`due_at`),
  ADD KEY `fk_staff_tasks_creator` (`created_by`);

--
-- Indexes for table `staff_weekly_schedule`
--
ALTER TABLE `staff_weekly_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_weekly_day` (`staff_user_id`,`day_of_week`);

--
-- Indexes for table `stylist_services`
--
ALTER TABLE `stylist_services`
  ADD PRIMARY KEY (`staff_user_id`,`service_id`),
  ADD KEY `idx_stylist_services_active` (`is_active`,`service_id`),
  ADD KEY `fk_stylist_services_service` (`service_id`),
  ADD KEY `fk_stylist_services_assigner` (`assigned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `service_commissions`
--
ALTER TABLE `service_commissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_weekly_schedule`
--
ALTER TABLE `staff_weekly_schedule`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_appointments_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `fk_appointments_stylist` FOREIGN KEY (`stylist_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `appointment_notifications`
--
ALTER TABLE `appointment_notifications`
  ADD CONSTRAINT `fk_appointment_notifications_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inventory_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inventory_movements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_movements_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoices_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_invoice_items_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_items_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  ADD CONSTRAINT `fk_payments_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_commissions`
--
ALTER TABLE `service_commissions`
  ADD CONSTRAINT `fk_commission_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_commission_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_commission_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_commission_payer` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_commission_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `fk_commission_stylist` FOREIGN KEY (`stylist_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD CONSTRAINT `fk_staff_shifts_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_staff_shifts_staff` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD CONSTRAINT `fk_staff_tasks_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_staff_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_weekly_schedule`
--
ALTER TABLE `staff_weekly_schedule`
  ADD CONSTRAINT `fk_staff_weekly_user` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stylist_services`
--
ALTER TABLE `stylist_services`
  ADD CONSTRAINT `fk_stylist_services_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stylist_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stylist_services_staff` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
