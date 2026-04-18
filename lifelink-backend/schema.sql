-- ============================================================
-- LifeLink Database Schema
-- Schema only — no data
-- ============================================================

CREATE DATABASE IF NOT EXISTS `lifelink_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `lifelink_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- Table: roles
-- ============================================================
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(25) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `aadhaar_number` varchar(12) NOT NULL,
  `aadhaar_last4` varchar(15) DEFAULT NULL,
  `aadhaar_hash` varchar(255) DEFAULT NULL,
  `user_status` enum('Active','Hold','Inactive','Suspended') NOT NULL DEFAULT 'Active',
  `last_donation_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `points` int(11) DEFAULT 0,
  `is_donor` tinyint(1) NOT NULL DEFAULT 0,
  `fcm_token` varchar(500) DEFAULT NULL,
  `fcm_token_updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `firebase_uid` (`firebase_uid`),
  UNIQUE KEY `unique_aadhaar` (`aadhaar_number`),
  UNIQUE KEY `phone_number` (`phone_number`),
  KEY `role_id` (`role_id`),
  KEY `idx_users_blood_group` (`blood_group`),
  KEY `idx_users_pincode` (`pincode`),
  KEY `idx_users_firebase_uid` (`firebase_uid`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: admin
-- ============================================================
CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `firebase_uid` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`admin_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: donors
-- ============================================================
CREATE TABLE `donors` (
  `donor_id` int(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) NOT NULL,
  `weight` int(3) NOT NULL,
  `donated_recent` tinyint(1) DEFAULT 0,
  `has_anemia` tinyint(1) DEFAULT 0,
  `recent_infection` tinyint(1) DEFAULT 0,
  `is_smoker` tinyint(1) DEFAULT 0,
  `consumes_alcohol` tinyint(1) DEFAULT 0,
  `recent_tattoo` tinyint(1) DEFAULT 0,
  `recent_surgery` tinyint(1) DEFAULT 0,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`donor_id`),
  KEY `firebase_uid` (`firebase_uid`),
  CONSTRAINT `donors_ibfk_1` FOREIGN KEY (`firebase_uid`) REFERENCES `users` (`firebase_uid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: hospital_details
-- ============================================================
CREATE TABLE `hospital_details` (
  `hospital_id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `phone_number` varchar(25) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `hospital_type` enum('Govt','Private','Trust') NOT NULL DEFAULT 'Govt',
  `blood_stock` enum('High','Moderate','Low','Critical') NOT NULL DEFAULT 'Moderate',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`hospital_id`),
  UNIQUE KEY `hospital_name` (`hospital_name`),
  UNIQUE KEY `phone_number` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: blood_requests
-- ============================================================
CREATE TABLE `blood_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) NOT NULL,
  `patient_name` varchar(100) NOT NULL,
  `patient_age` int(3) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `blood_group` varchar(5) NOT NULL,
  `units_required` int(2) NOT NULL,
  `donation_type` varchar(50) NOT NULL,
  `urgency_level` varchar(20) NOT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `hospital_address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `contact_mobile` varchar(15) NOT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `required_date` date NOT NULL,
  `status` enum('Pending','Accepted','Declined','Fulfilled','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `firebase_uid` (`firebase_uid`),
  KEY `idx_br_firebase_uid` (`firebase_uid`),
  KEY `idx_br_status` (`status`),
  KEY `idx_br_blood_group` (`blood_group`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`firebase_uid`) REFERENCES `users` (`firebase_uid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: donor_requests
-- ============================================================
CREATE TABLE `donor_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_uid` varchar(255) NOT NULL,
  `donor_uid` varchar(255) NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `status` enum('Pending','Accepted','Declined','Fulfilled','PendingApproval') DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blood_request_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `fk_donor` (`donor_uid`),
  KEY `idx_blood_request_id` (`blood_request_id`),
  KEY `idx_dr_donor_uid` (`donor_uid`),
  KEY `idx_dr_requester_uid` (`requester_uid`),
  CONSTRAINT `fk_donor` FOREIGN KEY (`donor_uid`) REFERENCES `users` (`firebase_uid`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_blood_req` FOREIGN KEY (`blood_request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: donations
-- ============================================================
CREATE TABLE `donations` (
  `donation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `blood_group` varchar(5) NOT NULL,
  `donation_date` date NOT NULL,
  `volume_ml` int(11) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`donation_id`),
  KEY `user_id` (`user_id`),
  KEY `hospital_id` (`hospital_id`),
  CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `donations_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospital_details` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: campaigns
-- ============================================================
CREATE TABLE `campaigns` (
  `campaign_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(255) NOT NULL,
  `campaign_type` enum('NGO','Hospital','Corporate') NOT NULL,
  `organized_by` varchar(255) NOT NULL,
  `blood_group_needed` varchar(100) DEFAULT 'All Groups',
  `target_units` int(11) DEFAULT 0,
  `venue_info` text NOT NULL,
  `contact_person_name` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `facilities` text DEFAULT NULL,
  `status` enum('Upcoming','Active','Completed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: campaign_registrations
-- ============================================================
CREATE TABLE `campaign_registrations` (
  `registration_id` int(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) NOT NULL,
  `campaign_name` varchar(255) DEFAULT 'General Campaign',
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `blood_group` varchar(5) NOT NULL,
  `age` int(3) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `uq_user_campaign` (`firebase_uid`, `campaign_name`),
  KEY `firebase_uid` (`firebase_uid`),
  CONSTRAINT `campaign_registrations_ibfk_1` FOREIGN KEY (`firebase_uid`) REFERENCES `users` (`firebase_uid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: emergency_alerts
-- ============================================================
CREATE TABLE `emergency_alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `location` varchar(100) NOT NULL,
  `blood_group_needed` varchar(100) NOT NULL,
  `alert_type` enum('System Critical','User Emergency') NOT NULL DEFAULT 'User Emergency',
  `contact_number` varchar(25) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `donors_notified` int(11) NOT NULL DEFAULT 0,
  `alert_timestamp` datetime NOT NULL,
  `is_fulfilled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`alert_id`),
  KEY `user_id` (`admin_id`),
  CONSTRAINT `emergency_alerts_fk_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `firebase_uid` varchar(128) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `priority` enum('normal','high','emergency') NOT NULL DEFAULT 'normal',
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notif_firebase_uid` (`firebase_uid`),
  KEY `idx_notif_status` (`status`),
  KEY `idx_notif_uid_read` (`firebase_uid`, `is_read`),
  KEY `idx_notif_user_type` (`user_id`, `type`, `is_read`),
  KEY `idx_notif_priority` (`priority`, `is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: activity_history
-- ============================================================
CREATE TABLE `activity_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_user_history` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_user_history` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: health_records
-- ============================================================
CREATE TABLE `health_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `hemoglobin_level` float DEFAULT NULL,
  `last_check_date` date DEFAULT NULL,
  `medical_conditions` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`record_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: receiving_requests
-- ============================================================
CREATE TABLE `receiving_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_group` varchar(5) NOT NULL,
  `units_required` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `urgency_level` enum('Low','Medium','High','Critical') NOT NULL,
  `status` enum('Pending','Approved','Rejected','Completed') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `receiving_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: reports
-- ============================================================
CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` enum('DonorReport','HospitalReport','CampaignReport','FinancialReport','SummaryReport','UserReport','RequestReport','EmergencyReport','DonationReport','MonthlyReport') NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `report_data` longtext NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table: feedback
-- ============================================================
CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `comments` varchar(500) NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `submission_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
