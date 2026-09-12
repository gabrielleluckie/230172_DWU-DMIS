-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 12, 2026 at 11:07 AM
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
-- Database: `partnershipregistry`
--

-- --------------------------------------------------------

--
-- Table structure for table `agreement`
--

CREATE TABLE IF NOT EXISTS `agreement` (
  `Agree_ID` int(11) NOT NULL,
  `Partner_ID` int(11) NOT NULL,
  `Campus_ID` int(11) NOT NULL,
  `Submitted_By` int(11) NOT NULL,
  `Reviewed_By` int(11) DEFAULT NULL,
  `Partnership_Type` varchar(100) NOT NULL,
  `Agreement_Type` varchar(100) NOT NULL,
  `Scope_Description` text DEFAULT NULL,
  `Status` enum('Draft','Submitted','Under Review','Revision Required','Approved','Rejected','Active','Expiring Soon','Expired') NOT NULL DEFAULT 'Active',
  `Director_Comments` text DEFAULT NULL,
  `Signed_Date` date DEFAULT NULL,
  `Expiry_Date` date DEFAULT NULL,
  `Expiry_Alert_Sent_At` datetime DEFAULT NULL,
  `Document_Path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `access_token` varchar(64) DEFAULT NULL,
  `Agreement_Title` varchar(255) DEFAULT NULL,
  `Physical_Address` text DEFAULT NULL,
  `Mailing_Address` text DEFAULT NULL,
  `Partner_Email` varchar(150) DEFAULT NULL,
  `Director_Email` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agreement`
--

INSERT INTO `agreement` (`Agree_ID`, `Partner_ID`, `Campus_ID`, `Submitted_By`, `Reviewed_By`, `Partnership_Type`, `Agreement_Type`, `Scope_Description`, `Status`, `Director_Comments`, `Signed_Date`, `Expiry_Date`, `Expiry_Alert_Sent_At`, `Document_Path`, `created_at`, `updated_at`, `access_token`, `Agreement_Title`, `Physical_Address`, `Mailing_Address`, `Partner_Email`, `Director_Email`) VALUES
(2, 2, 1, 1, 4, 'Research', 'MOU', 'Objectives/Rationale:\nTest objectives for proposal workflow.\n\nDWU Commitments:\nDWU will provide faculty support.\n\nPartner Contributions:\nPartner will fund equipment.', 'Approved', NULL, NULL, NULL, NULL, NULL, '2026-08-09 15:54:19', '2026-08-09 15:54:19', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 3, 3, 4, 'Academic/Twinning', 'Memorandum of Understanding (MOU)', 'Objectives/Rationale:\nprovide business training workshops\n\nDWU Commitments:\nstaff and student time\n\nPartner Contributions:\nexpertise and business training', 'Rejected', 'require more information about DWU commitments, what exactly is dwu commiting in terms of staff and student time?', NULL, NULL, NULL, NULL, '2026-08-09 16:50:51', '2026-09-08 21:44:44', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, 2, 4, 4, 'Twinning', 'MOU', 'DFAT funding twinning program focused on improving newly graduates in STEM courses', 'Active', NULL, '2026-08-10', '2028-08-10', NULL, NULL, '2026-08-09 17:14:21', '2026-08-09 17:14:21', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 5, 1, 4, 4, 'Community Engagement', 'Service Agreement', 'it is soon to expire, this agreement', 'Active', NULL, '2026-07-31', '2026-08-21', NULL, 'uploads/agreements/1787885524_Assessment_Centre_Guide_-_Gabrielle_Luckie.pdf', '2026-08-28 02:52:04', '2026-08-28 02:52:04', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 6, 1, 4, 4, 'Research Collaboration', 'MOA', 'renewal soon', 'Active', NULL, '2025-04-03', '2028-04-04', NULL, 'uploads/agreements/1788904800_IS405_ICT_HELPDESK_IMMERSION_Wks_9-10-11_1.pdf', '2026-09-08 22:00:00', '2026-09-08 22:04:32', 'e38178b024dacdf4bde8ff16f4c6e1e56b28d47479c57e306f655edd72d997c4', NULL, NULL, NULL, NULL, NULL),
(7, 7, 1, 4, 4, 'Twinning', 'MOA', NULL, 'Active', NULL, '2024-09-11', '2026-09-07', NULL, NULL, '2026-09-08 22:35:15', '2026-09-08 22:35:15', '805bd5e105b0acd9c0b90a32ff6b327d31c77945c1db81c9609408630034f365', NULL, NULL, NULL, NULL, NULL),
(8, 8, 1, 4, 4, 'Industry / Workforce Training', 'Contract', 'Student partnership', 'Expiring Soon', NULL, '2025-09-09', '2026-09-25', '2026-09-09 13:07:22', NULL, '2026-09-09 03:01:34', '2026-09-09 03:07:22', '0f53330b40e7914691c306cd6e5d517a951e971a31300a834906cac051d353b2', 'Student Internship for Digital Banking', 'BSP Centre, Section 34, Lot 7, Musgrave Street, Town, Port Moresby, NCD', NULL, NULL, '230172student@dwu.ac.pg'),
(9, 8, 1, 4, 4, 'Industry / Workforce Training', 'Contract', 'student recuirtment', 'Expiring Soon', NULL, '2025-10-09', '2026-10-09', '2026-09-09 13:12:41', NULL, '2026-09-09 03:12:23', '2026-09-09 03:12:41', 'f0d855eff27da90edc8789b31752521b85884fdd2dd48ac90bd9634c0b613cca', 'IS and MCS Student Recruitment', 'BSP Centre, Section 34, Lot 7, Musgrave Street, Town, Port Moresby, NCD', NULL, 'gabrielleluckie20@gmail.com', '230172student@dwu.ac.pg'),
(10, 8, 1, 4, 4, 'Industry / Workforce Training', 'Contract', 'student recruitment', 'Active', NULL, '2025-10-09', '2026-10-09', NULL, NULL, '2026-09-09 03:12:41', '2026-09-09 03:12:41', '42d608218fc4c841c5613bda046d7276d15a409ee7077b80a8c63546506af358', 'IS and MCS Student Recruitment', 'BSP Centre, Section 34, Lot 7, Musgrave Street, Town, Port Moresby, NCD', NULL, 'gabrielleluckie20@gmail.com', '230172student@dwu.ac.pg');

-- --------------------------------------------------------

--
-- Table structure for table `agreement_draft`
--

CREATE TABLE IF NOT EXISTS `agreement_draft` (
  `Draft_ID` int(10) UNSIGNED NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Campus_ID` int(11) DEFAULT NULL,
  `Title` varchar(255) NOT NULL DEFAULT 'Untitled draft',
  `Partner_Name` varchar(255) DEFAULT NULL,
  `Agreement_Type` varchar(255) DEFAULT NULL,
  `Campus_Name` varchar(255) DEFAULT NULL,
  `Form_Data` longtext NOT NULL,
  `Created_At` datetime NOT NULL DEFAULT current_timestamp(),
  `Updated_At` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agreement_draft`
--

INSERT INTO `agreement_draft` (`Draft_ID`, `User_ID`, `Campus_ID`, `Title`, `Partner_Name`, `Agreement_Type`, `Campus_Name`, `Form_Data`, `Created_At`, `Updated_At`) VALUES
(1, 4, NULL, 'Draft test MOA', NULL, NULL, NULL, '{\"partner_mode\":\"existing\",\"partner_id\":0,\"partner_name\":\"\",\"partner_country\":\"\",\"partner_website\":\"\",\"campus_id\":0,\"contact_name\":\"\",\"contact_designation\":\"\",\"contact_email\":\"\",\"contact_phone\":\"\",\"contact_fax\":\"\",\"agreement_title\":\"Draft test MOA\",\"physical_address\":\"\",\"mailing_address\":\"\",\"partner_email\":\"\",\"director_email\":\"230172student@dwu.ac.pg\",\"partnership_type\":\"\",\"agreement_type\":\"\",\"signed_date\":\"\",\"expiry_date\":\"\",\"scope_description\":\"\",\"document_path\":null}', '2026-09-09 12:59:42', '2026-09-09 12:59:42');

-- --------------------------------------------------------

--
-- Table structure for table `agreement_history`
--

CREATE TABLE IF NOT EXISTS `agreement_history` (
  `AgreeHis_ID` int(11) NOT NULL,
  `Agree_ID` int(11) NOT NULL,
  `Logged_By` int(11) NOT NULL,
  `Event_Type` varchar(100) NOT NULL,
  `Event_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Comments` text DEFAULT NULL,
  `Document_Version_Path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agreement_history`
--

INSERT INTO `agreement_history` (`AgreeHis_ID`, `Agree_ID`, `Logged_By`, `Event_Type`, `Event_Date`, `Comments`, `Document_Version_Path`) VALUES
(2, 2, 1, 'Proposal Submitted', '2026-08-09 07:54:19', 'Partnership proposal submitted by Test Campus Admin for director review.', NULL),
(3, 2, 4, 'Proposal Approved', '2026-08-09 07:54:19', 'Proposal approved for offline negotiation. Approved by Test Director.', NULL),
(4, 3, 3, 'Proposal Submitted', '2026-08-09 08:50:51', 'Partnership proposal submitted by William Luke for director review.', NULL),
(5, 4, 4, 'Agreement Created', '2026-08-09 09:14:21', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL),
(6, 5, 4, 'Agreement Created', '2026-08-27 18:52:04', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL),
(7, 3, 4, 'Proposal Rejected', '2026-09-08 13:44:44', 'Proposal rejected: require more information about DWU commitments, what exactly is dwu commiting in terms of staff and student time? (Reviewed by Mary Robinson)', NULL),
(8, 6, 4, 'Agreement Created', '2026-09-08 14:00:00', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL),
(9, 7, 4, 'Agreement Created', '2026-09-08 14:35:15', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard.', NULL),
(10, 8, 4, 'Agreement Created', '2026-09-08 19:01:34', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL),
(11, 9, 4, 'Agreement Created', '2026-09-08 19:12:23', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL),
(12, 10, 4, 'Agreement Created', '2026-09-08 19:12:41', 'Active partnership registered by Mary Robinson via the Partnership Director dashboard. Scope/funding notes recorded.', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `campus`
--

CREATE TABLE IF NOT EXISTS `campus` (
  `Campus_ID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Province` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campus`
--

INSERT INTO `campus` (`Campus_ID`, `Name`, `Province`, `created_at`) VALUES
(1, 'Madang Campus', 'Madang', '2026-07-29 10:45:56'),
(2, 'Port Moresby Campus', 'National Capital District', '2026-07-29 10:45:56'),
(3, 'Wewak Campus', 'East Sepik', '2026-07-29 10:45:56'),
(4, 'Rabaul Campus', 'East New Britain', '2026-07-29 10:45:56');

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE IF NOT EXISTS `contact` (
  `Contact_ID` int(11) NOT NULL,
  `Partner_ID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Gender` enum('Male','Female','Other') DEFAULT NULL,
  `Designation` varchar(100) DEFAULT NULL,
  `Email` varchar(150) DEFAULT NULL,
  `Phone_Number` varchar(30) DEFAULT NULL,
  `Fax` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact`
--

INSERT INTO `contact` (`Contact_ID`, `Partner_ID`, `Name`, `Gender`, `Designation`, `Email`, `Phone_Number`, `Fax`) VALUES
(1, 4, 'michael marum', NULL, 'manager', 'mmarum@gmail.com', '73923495', '294505'),
(2, 5, 'lucy maino', NULL, 'principal', 'gabrielleluckie@gmail.com', '72384734', '34'),
(3, 6, 'peter rabbit', NULL, 'head of department of reasearch', 'gabrielleluckie20@gmail.com', '72834593', '334'),
(4, 7, 'luke mark', NULL, 'dean of business studies', 'gabrielleluckie20@gmail.com', '73456789', '234'),
(5, 8, 'Jolyn Mayers', NULL, 'Manager people and Culture', 'gabrielleluckie20@gmail.com', '71234345', '234'),
(6, 8, 'Andre Terry', NULL, 'Manager technology division', 'gabrielleluckie20@gmail.com', '72345934', '234'),
(7, 8, 'Andre Terry', NULL, 'Manager technology division', 'gabrielleluckie20@gmail.com', '72345934', '234');

-- --------------------------------------------------------

--
-- Table structure for table `partner`
--

CREATE TABLE IF NOT EXISTS `partner` (
  `Partner_ID` int(11) NOT NULL,
  `Campus_ID` int(11) NOT NULL,
  `Name` varchar(150) NOT NULL,
  `Country` varchar(100) NOT NULL,
  `Address` text DEFAULT NULL,
  `Mailing_Address` text DEFAULT NULL,
  `Website` varchar(255) DEFAULT NULL,
  `Is_Deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `partner`
--

INSERT INTO `partner` (`Partner_ID`, `Campus_ID`, `Name`, `Country`, `Address`, `Mailing_Address`, `Website`, `Is_Deleted`, `created_at`) VALUES
(1, 1, 'Test Partner Org', 'Australia', 'Australia, Sydney', NULL, NULL, 0, '2026-08-09 15:53:43'),
(2, 1, 'Test Partner Org 1786290859', 'Australia', 'Australia, Sydney', NULL, NULL, 0, '2026-08-09 15:54:19'),
(3, 3, 'mathew mark', 'south korea', 'south korea', NULL, NULL, 0, '2026-08-09 16:50:51'),
(4, 2, 'ExxonMobil', 'papua new guinea', 'P.O.BOX 455, gerehu station', NULL, NULL, 0, '2026-08-09 17:14:21'),
(5, 1, 'madang technical college', 'papua new guinea', 'P.o.box 435\r\nmadang', NULL, NULL, 0, '2026-08-28 02:52:04'),
(6, 1, 'University Of Papua New Guinea', 'papua new guinea', 'Waigani Campus, University Avenue, Waigani, NCD, Papua New Guinea', NULL, NULL, 0, '2026-09-08 22:00:00'),
(7, 1, 'university of Goroka', 'papua new guinea', 'Goroka Town, University Avenue, Papua New Guinea', NULL, NULL, 0, '2026-09-08 22:35:15'),
(8, 1, 'Bank of South Pacific', 'Papua New guinea', 'BSP Centre, Section 34, Lot 7, Musgrave Street, Town, Port Moresby, NCD', NULL, NULL, 0, '2026-09-09 03:01:34');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_draft`
--

CREATE TABLE IF NOT EXISTS `proposal_draft` (
  `Draft_ID` int(10) UNSIGNED NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Campus_ID` int(11) DEFAULT NULL,
  `Title` varchar(255) NOT NULL DEFAULT 'Untitled draft',
  `Partner_Name` varchar(255) DEFAULT NULL,
  `Agreement_Type` varchar(255) DEFAULT NULL,
  `Campus_Name` varchar(255) DEFAULT NULL,
  `Form_Data` longtext NOT NULL,
  `Created_At` datetime NOT NULL DEFAULT current_timestamp(),
  `Updated_At` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proposal_draft`
--

INSERT INTO `proposal_draft` (`Draft_ID`, `User_ID`, `Campus_ID`, `Title`, `Partner_Name`, `Agreement_Type`, `Campus_Name`, `Form_Data`, `Created_At`, `Updated_At`) VALUES
(1, 1, 1, 'University of Queensland', 'University of Queensland', NULL, 'Madang (Main)', '{\"staff_name\":\"Alois Sanki\",\"department\":\"Office of Partnerships & Development\",\"submission_date\":\"2026-09-04\",\"email\":\"admin.madang@dwu.ac.pg\",\"phone\":\"\",\"campus\":\"Madang (Main)\",\"partner_name\":\"University of Queensland\",\"partner_type\":\"\",\"partner_type_other\":\"\",\"partner_location\":\"\",\"partner_website\":\"\",\"contact_person_name\":\"\",\"contact_person_title\":\"\",\"contact_person_email\":\"\",\"contact_person_phone\":\"\",\"agreement_type\":\"\",\"partnership_nature_other\":\"\",\"partnership_description\":\"\",\"dwu_commitments\":\"\",\"dwu_estimated_cost\":\"\",\"dwu_responsible_unit\":\"\",\"partner_contributions\":\"\",\"partner_funding_amount\":\"\",\"partner_funding_currency\":\"\",\"partner_payment_timing\":\"\",\"partner_contribution_conditions\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"total_duration\":\"\",\"key_milestones\":\"\",\"dwu_benefits\":\"\",\"strategic_alignment\":\"\",\"perceived_risks\":\"\",\"previous_engagement_details\":\"\",\"signee_name\":\"Alois Sanki\",\"signee_campus\":\"Madang Campus\",\"signee_date\":\"2026-09-04\",\"campus_admin_comments\":\"\",\"director_decision_status\":\"pending\"}', '2026-09-04 14:07:38', '2026-09-04 14:07:38'),
(2, 1, 1, 'Pacific Adventist University', 'Pacific Adventist University', NULL, 'Madang (Main)', '{\"staff_name\":\"Alois Sanki\",\"department\":\"Office of Partnerships & Development\",\"submission_date\":\"2026-09-04\",\"email\":\"admin.madang@dwu.ac.pg\",\"phone\":\"\",\"campus\":\"Madang (Main)\",\"partner_name\":\"Pacific Adventist University\",\"partner_type\":\"\",\"partner_type_other\":\"\",\"partner_location\":\"\",\"partner_website\":\"\",\"contact_person_name\":\"\",\"contact_person_title\":\"\",\"contact_person_email\":\"\",\"contact_person_phone\":\"\",\"agreement_type\":\"\",\"partnership_nature_other\":\"\",\"partnership_description\":\"\",\"dwu_commitments\":\"\",\"dwu_estimated_cost\":\"\",\"dwu_responsible_unit\":\"\",\"partner_contributions\":\"\",\"partner_funding_amount\":\"\",\"partner_funding_currency\":\"\",\"partner_payment_timing\":\"\",\"partner_contribution_conditions\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"total_duration\":\"\",\"key_milestones\":\"\",\"dwu_benefits\":\"\",\"strategic_alignment\":\"\",\"perceived_risks\":\"\",\"previous_engagement_details\":\"\",\"signee_name\":\"Alois Sanki\",\"signee_campus\":\"Madang Campus\",\"signee_date\":\"2026-09-04\",\"campus_admin_comments\":\"\",\"director_decision_status\":\"pending\"}', '2026-09-04 14:08:03', '2026-09-04 14:08:03'),
(3, 2, 2, 'UPNG Test Draft', 'UPNG Test Draft', NULL, 'Port Moresby', '{\"staff_name\":\"Theresa Pomaleu\",\"department\":\"Office of Partnerships & Development\",\"submission_date\":\"2026-09-04\",\"email\":\"admin.pom@dwu.ac.pg\",\"phone\":\"\",\"campus\":\"Port Moresby\",\"partner_name\":\"UPNG Test Draft\",\"partner_type\":\"\",\"partner_type_other\":\"\",\"partner_location\":\"\",\"partner_website\":\"\",\"contact_person_name\":\"\",\"contact_person_title\":\"\",\"contact_person_email\":\"\",\"contact_person_phone\":\"\",\"agreement_type\":\"\",\"partnership_nature_other\":\"\",\"partnership_description\":\"\",\"dwu_commitments\":\"\",\"dwu_estimated_cost\":\"\",\"dwu_responsible_unit\":\"\",\"partner_contributions\":\"\",\"partner_funding_amount\":\"\",\"partner_funding_currency\":\"\",\"partner_payment_timing\":\"\",\"partner_contribution_conditions\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"total_duration\":\"\",\"key_milestones\":\"\",\"dwu_benefits\":\"\",\"strategic_alignment\":\"\",\"perceived_risks\":\"\",\"previous_engagement_details\":\"\",\"signee_name\":\"Theresa Pomaleu\",\"signee_campus\":\"Port Moresby Campus\",\"signee_date\":\"2026-09-04\",\"campus_admin_comments\":\"\",\"director_decision_status\":\"pending\",\"submitter_campus\":\"Port Moresby\"}', '2026-09-04 14:42:26', '2026-09-04 14:42:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `User_ID` int(11) NOT NULL,
  `Campus_ID` int(11) DEFAULT NULL,
  `First_Name` varchar(50) NOT NULL,
  `Last_Name` varchar(50) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `Phone_Number` varchar(30) DEFAULT NULL,
  `Role` enum('campus_admin','partnership_director','executive_officer','president') NOT NULL,
  `Is_Active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `Campus_ID`, `First_Name`, `Last_Name`, `Email`, `password`, `Phone_Number`, `Role`, `Is_Active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Alois', 'Sanki', 'admin.madang@dwu.ac.pg', 'sanki1', '+675 422 2999', 'campus_admin', 1, '2026-07-29 10:45:56', '2026-09-06 14:09:16'),
(2, 2, 'Theresa', 'Pomaleu', 'admin.pom@dwu.ac.pg', 'pomaleu2', '+675 325 3333', 'campus_admin', 1, '2026-07-29 10:45:56', '2026-09-06 14:09:16'),
(3, 3, 'William', 'Luke', 'admin.wewak@dwu.ac.pg', 'luke3', '+675 456 2111', 'campus_admin', 1, '2026-07-29 10:45:56', '2026-09-06 14:09:16'),
(4, NULL, 'Mary', 'Robinson', '230172student@dwu.ac.pg', 'robinson4', '+675 422 2001', 'partnership_director', 1, '2026-07-29 10:45:56', '2026-09-08 22:06:47'),
(5, NULL, 'Karen', 'Reeves', 'exec.officer@dwu.ac.pg', 'reeves5', '+675 422 2002', 'executive_officer', 1, '2026-07-29 10:45:56', '2026-09-06 14:09:16'),
(6, NULL, 'Philip', 'Gregory', 'president@dwu.ac.pg', 'gregory6', '+675 422 2000', 'president', 1, '2026-07-29 10:45:56', '2026-09-06 14:09:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agreement`
--
ALTER TABLE `agreement`
  ADD PRIMARY KEY (`Agree_ID`),
  ADD UNIQUE KEY `uq_agreement_access_token` (`access_token`),
  ADD KEY `Partner_ID` (`Partner_ID`),
  ADD KEY `Campus_ID` (`Campus_ID`),
  ADD KEY `Submitted_By` (`Submitted_By`),
  ADD KEY `Reviewed_By` (`Reviewed_By`);

--
-- Indexes for table `agreement_draft`
--
ALTER TABLE `agreement_draft`
  ADD PRIMARY KEY (`Draft_ID`),
  ADD KEY `idx_agreement_draft_user` (`User_ID`);

--
-- Indexes for table `agreement_history`
--
ALTER TABLE `agreement_history`
  ADD PRIMARY KEY (`AgreeHis_ID`),
  ADD KEY `Agree_ID` (`Agree_ID`),
  ADD KEY `Logged_By` (`Logged_By`);

--
-- Indexes for table `campus`
--
ALTER TABLE `campus`
  ADD PRIMARY KEY (`Campus_ID`);

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`Contact_ID`),
  ADD KEY `Partner_ID` (`Partner_ID`);

--
-- Indexes for table `partner`
--
ALTER TABLE `partner`
  ADD PRIMARY KEY (`Partner_ID`),
  ADD KEY `Campus_ID` (`Campus_ID`);

--
-- Indexes for table `proposal_draft`
--
ALTER TABLE `proposal_draft`
  ADD PRIMARY KEY (`Draft_ID`),
  ADD KEY `idx_proposal_draft_user` (`User_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `Campus_ID` (`Campus_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agreement`
--
ALTER TABLE `agreement`
  MODIFY `Agree_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `agreement_draft`
--
ALTER TABLE `agreement_draft`
  MODIFY `Draft_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `agreement_history`
--
ALTER TABLE `agreement_history`
  MODIFY `AgreeHis_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `campus`
--
ALTER TABLE `campus`
  MODIFY `Campus_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact`
--
ALTER TABLE `contact`
  MODIFY `Contact_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `partner`
--
ALTER TABLE `partner`
  MODIFY `Partner_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `proposal_draft`
--
ALTER TABLE `proposal_draft`
  MODIFY `Draft_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agreement`
--
ALTER TABLE `agreement`
  ADD CONSTRAINT `agreement_ibfk_1` FOREIGN KEY (`Partner_ID`) REFERENCES `partner` (`Partner_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `agreement_ibfk_2` FOREIGN KEY (`Campus_ID`) REFERENCES `campus` (`Campus_ID`),
  ADD CONSTRAINT `agreement_ibfk_3` FOREIGN KEY (`Submitted_By`) REFERENCES `users` (`User_ID`),
  ADD CONSTRAINT `agreement_ibfk_4` FOREIGN KEY (`Reviewed_By`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `agreement_history`
--
ALTER TABLE `agreement_history`
  ADD CONSTRAINT `agreement_history_ibfk_1` FOREIGN KEY (`Agree_ID`) REFERENCES `agreement` (`Agree_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `agreement_history_ibfk_2` FOREIGN KEY (`Logged_By`) REFERENCES `users` (`User_ID`);

--
-- Constraints for table `contact`
--
ALTER TABLE `contact`
  ADD CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`Partner_ID`) REFERENCES `partner` (`Partner_ID`) ON DELETE CASCADE;

--
-- Constraints for table `partner`
--
ALTER TABLE `partner`
  ADD CONSTRAINT `partner_ibfk_1` FOREIGN KEY (`Campus_ID`) REFERENCES `campus` (`Campus_ID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`Campus_ID`) REFERENCES `campus` (`Campus_ID`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
