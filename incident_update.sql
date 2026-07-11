-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 11, 2026 at 06:09 PM
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
-- Database: `incident_update`
--

-- --------------------------------------------------------

--
-- Table structure for table `atm_contact`
--

CREATE TABLE `atm_contact` (
  `id` int(11) NOT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `custodian1_name` varchar(100) DEFAULT NULL,
  `custodian1_mobile` varchar(20) DEFAULT NULL,
  `custodian2_name` varchar(100) DEFAULT NULL,
  `custodian2_mobile` varchar(20) DEFAULT NULL,
  `ip_phone_no` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `manager_mobile` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_crm_ariful_june_2026`
--

CREATE TABLE `atm_crm_ariful_june_2026` (
  `COL 1` varchar(10) DEFAULT NULL,
  `COL 2` varchar(33) DEFAULT NULL,
  `COL 3` varchar(14) DEFAULT NULL,
  `COL 4` varchar(17) DEFAULT NULL,
  `COL 5` varchar(16) DEFAULT NULL,
  `COL 6` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_device_movement`
--

CREATE TABLE `atm_device_movement` (
  `id` int(11) NOT NULL,
  `movement_no` varchar(50) DEFAULT NULL,
  `atm_master_id` int(11) DEFAULT NULL,
  `old_atm_id` varchar(50) DEFAULT NULL,
  `new_atm_id` varchar(50) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT NULL,
  `movement_type` varchar(50) DEFAULT NULL,
  `old_atm_name` varchar(150) DEFAULT NULL,
  `old_branch_name` varchar(150) DEFAULT NULL,
  `old_zone_name` varchar(100) DEFAULT NULL,
  `old_group_no` varchar(50) DEFAULT NULL,
  `new_atm_name` varchar(150) DEFAULT NULL,
  `new_branch_name` varchar(150) DEFAULT NULL,
  `new_zone_name` varchar(100) DEFAULT NULL,
  `new_group_no` varchar(50) DEFAULT NULL,
  `old_atm_vendor_id` int(11) DEFAULT NULL,
  `new_atm_vendor_id` int(11) DEFAULT NULL,
  `old_ups_vendor_id` int(11) DEFAULT NULL,
  `new_ups_vendor_id` int(11) DEFAULT NULL,
  `movement_date` date DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_ip_details`
--

CREATE TABLE `atm_ip_details` (
  `id` int(11) NOT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `atm_name` varchar(150) DEFAULT NULL,
  `monitoring_ip` varchar(50) DEFAULT NULL,
  `internal_ip` varchar(50) DEFAULT NULL,
  `subnet_mask` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_master`
--

CREATE TABLE `atm_master` (
  `id` int(11) NOT NULL,
  `atm_id` varchar(50) NOT NULL,
  `atm_name` varchar(150) NOT NULL,
  `ups_vendor` varchar(100) DEFAULT NULL,
  `atm_model` varchar(100) DEFAULT NULL,
  `atm_vendor` varchar(100) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `monitoring_ip` varchar(50) DEFAULT NULL,
  `internal_ip` varchar(50) DEFAULT NULL,
  `subnet_mask` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `group_no` int(11) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `machine_type` enum('ATM','CRM') DEFAULT 'ATM',
  `atm_vendor_id` int(11) DEFAULT NULL,
  `ups_vendor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_network_info`
--

CREATE TABLE `atm_network_info` (
  `id` int(11) NOT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `atm_name` varchar(100) DEFAULT NULL,
  `monitoring_ip` varchar(50) DEFAULT NULL,
  `internal_ip` varchar(50) DEFAULT NULL,
  `subnet_mask` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_sg`
--

CREATE TABLE `atm_sg` (
  `id` int(11) NOT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `booth_address` text DEFAULT NULL,
  `atm_name` varchar(100) DEFAULT NULL,
  `sg1_name` varchar(100) DEFAULT NULL,
  `sg1_mobile` varchar(20) DEFAULT NULL,
  `sg2_name` varchar(100) DEFAULT NULL,
  `sg2_mobile` varchar(20) DEFAULT NULL,
  `sg3_name` varchar(100) DEFAULT NULL,
  `sg3_mobile` varchar(20) DEFAULT NULL,
  `supervisor_details` text DEFAULT NULL,
  `company_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atm_update`
--

CREATE TABLE `atm_update` (
  `incident_id` int(11) NOT NULL,
  `atm_id` varchar(50) NOT NULL,
  `atm_name` varchar(100) NOT NULL,
  `ups_vendor` varchar(100) DEFAULT NULL,
  `atm_vendor` varchar(100) DEFAULT NULL,
  `down_time` varchar(50) DEFAULT NULL,
  `problem` varchar(100) NOT NULL,
  `responsible_vendor_name` varchar(100) NOT NULL,
  `vendor_ticket_no` varchar(100) DEFAULT NULL,
  `vendor_eng_name` varchar(100) DEFAULT NULL,
  `vendor_eng_contact` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `incident_status` enum('Open','Closed') DEFAULT 'Open',
  `group_no` int(11) NOT NULL,
  `last_modified_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_empty_positions`
--

CREATE TABLE `cash_empty_positions` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `branch_empty` int(11) DEFAULT 0,
  `third_party_empty` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_branch_dispatch`
--

CREATE TABLE `cctv_branch_dispatch` (
  `id` int(11) NOT NULL,
  `dispatch_no` varchar(50) NOT NULL,
  `cctv_location_id` int(11) NOT NULL,
  `dispatch_date` date NOT NULL,
  `dispatch_type` enum('EMERGENCY_BACKUP','OLD_REPAIRED_PARTS','ADVANCE_STOCK') NOT NULL,
  `letter_no` varchar(100) DEFAULT NULL,
  `acknowledgement_status` enum('Pending','Received') DEFAULT 'Pending',
  `acknowledgement_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_branch_dispatch_items`
--

CREATE TABLE `cctv_branch_dispatch_items` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_device_history`
--

CREATE TABLE `cctv_device_history` (
  `id` int(11) NOT NULL,
  `atm_id` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `device_type` enum('DVR','CAMERA','HDD','SMPS','ROUTER') NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `action_type` enum('Installed','Replaced','Removed','Faulty','Serviced') NOT NULL,
  `old_serial_no` varchar(100) DEFAULT NULL,
  `new_serial_no` varchar(100) DEFAULT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `install_date` date DEFAULT NULL,
  `remove_date` date DEFAULT NULL,
  `warranty_start` date DEFAULT NULL,
  `warranty_end` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_installed_devices`
--

CREATE TABLE `cctv_installed_devices` (
  `id` int(11) NOT NULL,
  `cctv_location_id` int(11) NOT NULL,
  `source_type` enum('NEW_SET','SPARE_REPLACEMENT','ADVANCE_STOCK','OLD_REPAIRED_STOCK') NOT NULL,
  `source_reference_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(150) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `warranty_end_date` date DEFAULT NULL,
  `status` enum('Active','Removed','Faulty','In_Warranty_Claim','Replaced') DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_item_master`
--

CREATE TABLE `cctv_item_master` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `approved_rate` decimal(12,2) DEFAULT 0.00,
  `item_category` enum('SET_ITEM','SPARE_PART','ACCESSORY') NOT NULL,
  `item_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `warranty_applicable` tinyint(1) DEFAULT 0,
  `warranty_years` int(11) DEFAULT 0,
  `requires_brand_model_serial` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_list`
--

CREATE TABLE `cctv_list` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `br_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `atm_name` text DEFAULT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `network` varchar(100) DEFAULT NULL,
  `backup` varchar(100) DEFAULT NULL,
  `dvr_inst_date` date DEFAULT NULL,
  `dvr_vendor` varchar(150) DEFAULT NULL,
  `dvr_brand` varchar(100) DEFAULT NULL,
  `dvr_model` varchar(100) DEFAULT NULL,
  `dvr_serial` varchar(100) DEFAULT NULL,
  `dvr_password` varchar(100) DEFAULT NULL,
  `camera` varchar(100) DEFAULT NULL,
  `camera_inst_date` date DEFAULT NULL,
  `camera_vendor` varchar(150) DEFAULT NULL,
  `hdd_size_tb` varchar(50) DEFAULT NULL,
  `hdd_inst_date` date DEFAULT NULL,
  `hdd_serial` varchar(100) DEFAULT NULL,
  `hdd_vendor` varchar(150) DEFAULT NULL,
  `m_ip` varchar(50) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `subnet` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_list_remarks_history`
--

CREATE TABLE `cctv_list_remarks_history` (
  `id` int(11) NOT NULL,
  `cctv_list_id` int(11) NOT NULL,
  `old_remarks` text DEFAULT NULL,
  `new_remarks` text DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_locations`
--

CREATE TABLE `cctv_locations` (
  `id` int(11) NOT NULL,
  `atm_master_id` int(11) DEFAULT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `branch_name` varchar(150) NOT NULL,
  `booth_name` varchar(150) NOT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `group_no` int(11) DEFAULT NULL,
  `service_type` enum('ATM','CRM','UPS','NEW_BOOTH') DEFAULT 'ATM',
  `machine_type` enum('ATM','CRM','OTHER') DEFAULT 'ATM',
  `is_new_booth` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_monitor_devices`
--

CREATE TABLE `cctv_monitor_devices` (
  `id` int(11) NOT NULL,
  `cctv_list_id` int(11) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `br_code` varchar(50) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `atm_name` text DEFAULT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `dvr_vendor` varchar(150) DEFAULT NULL,
  `dvr_brand` varchar(100) DEFAULT NULL,
  `dvr_model` varchar(100) DEFAULT NULL,
  `dvr_serial` varchar(100) DEFAULT NULL,
  `m_ip` varchar(50) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `dvr_ip` varchar(50) DEFAULT NULL,
  `subnet` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `http_port` int(11) DEFAULT 80,
  `rtsp_port` int(11) DEFAULT 554,
  `total_channel` int(11) DEFAULT 4,
  `monitor_username` varchar(100) DEFAULT NULL,
  `monitor_password` varchar(255) DEFAULT NULL,
  `monitoring_enabled` tinyint(1) DEFAULT 1,
  `last_online_status` varchar(50) DEFAULT 'Not Checked',
  `last_http_status` varchar(50) DEFAULT 'Not Checked',
  `last_rtsp_status` varchar(50) DEFAULT 'Not Checked',
  `last_camera_status` varchar(100) DEFAULT 'Not Checked',
  `last_hdd_status` varchar(100) DEFAULT 'Not Checked',
  `last_backup_status` varchar(100) DEFAULT 'Not Checked',
  `last_checked_at` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_monitor_results`
--

CREATE TABLE `cctv_monitor_results` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `atm_id` varchar(50) NOT NULL,
  `channel_no` int(11) NOT NULL,
  `dvr_online` enum('Online','Offline') DEFAULT 'Offline',
  `http_status` enum('Open','Closed') DEFAULT 'Closed',
  `rtsp_status` enum('Open','Closed') DEFAULT 'Closed',
  `camera_status` enum('Normal','Black Screen','No Signal','Snapshot Fail','Not Checked') DEFAULT 'Not Checked',
  `brightness` decimal(8,2) DEFAULT NULL,
  `contrast_value` decimal(8,2) DEFAULT NULL,
  `snapshot_path` varchar(255) DEFAULT NULL,
  `hdd_status` varchar(100) DEFAULT 'Not Checked',
  `backup_status` varchar(100) DEFAULT 'Not Checked',
  `last_checked_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_qc_records`
--

CREATE TABLE `cctv_qc_records` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `qc_date` date NOT NULL,
  `qc_status` enum('Pending','Passed','Failed','Partially_Passed') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_set_requisition`
--

CREATE TABLE `cctv_set_requisition` (
  `id` int(11) NOT NULL,
  `requisition_no` varchar(50) NOT NULL,
  `cctv_location_id` int(11) NOT NULL,
  `source_type` enum('BRANCH','ATMMD','OTHER') DEFAULT 'BRANCH',
  `requisition_date` date NOT NULL,
  `received_date` date DEFAULT NULL,
  `cause` text DEFAULT NULL,
  `forwarded_to_cpd_date` date DEFAULT NULL,
  `tender_received_date` date DEFAULT NULL,
  `technical_evaluation_date` date DEFAULT NULL,
  `qc_date` date DEFAULT NULL,
  `installation_permission_date` date DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `status` enum('Waiting_for_Approval','Sent_to_CPD','Waiting_for_Technical_Evaluation','Waiting_for_Work_Order','Waiting_for_Delivery','Waiting_for_QC','Technical_Evaluation_Passed','Technical_Evaluation_Failed','QC_Passed','QC_Failed','Waiting_for_Installation','Installed','Closed','Cancelled') DEFAULT 'Waiting_for_Approval',
  `work_order_no` varchar(100) DEFAULT NULL,
  `work_order_date` date DEFAULT NULL,
  `selected_vendor_id` int(11) DEFAULT NULL,
  `branch_contact` varchar(100) DEFAULT NULL,
  `monitoring_ip` varchar(50) DEFAULT NULL,
  `internal_ip` varchar(50) DEFAULT NULL,
  `subnet_mask` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_set_requisition_items`
--

CREATE TABLE `cctv_set_requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_set_requisition_remarks`
--

CREATE TABLE `cctv_set_requisition_remarks` (
  `id` int(11) NOT NULL,
  `set_requisition_id` int(11) NOT NULL,
  `remark` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_spare_replacement_details`
--

CREATE TABLE `cctv_spare_replacement_details` (
  `id` int(11) NOT NULL,
  `spare_requisition_id` int(11) NOT NULL,
  `old_installed_device_id` int(11) DEFAULT NULL,
  `new_item_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(150) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `warranty_end_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_spare_requisition`
--

CREATE TABLE `cctv_spare_requisition` (
  `id` int(11) NOT NULL,
  `requisition_no` varchar(50) NOT NULL,
  `cctv_location_id` int(11) NOT NULL,
  `branch_contact` text DEFAULT NULL,
  `ip_details` text DEFAULT NULL,
  `application_date` date NOT NULL,
  `received_date` date DEFAULT NULL,
  `problem_details` text DEFAULT NULL,
  `source_type` enum('BRANCH','ATMMD') DEFAULT 'BRANCH',
  `action_type` enum('VENDOR_REPLACEMENT','ADVANCE_STOCK_INSTALL','OLD_REPAIRED_DISPATCH') NOT NULL,
  `assigned_vendor_id` int(11) DEFAULT NULL,
  `service_charge` decimal(12,2) DEFAULT 0.00,
  `service_charge_type` enum('AREA_WISE','FIXED','NONE') DEFAULT 'NONE',
  `installation_date` date DEFAULT NULL,
  `bill_submission_date` date DEFAULT NULL,
  `fad_forward_date` date DEFAULT NULL,
  `payment_status` enum('Pending','Forwarded_to_FAD','Paid','Rejected') DEFAULT 'Pending',
  `status` varchar(100) DEFAULT 'Draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_spare_requisition_items`
--

CREATE TABLE `cctv_spare_requisition_items` (
  `id` int(11) NOT NULL,
  `spare_requisition_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `stock_id` int(11) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `source_from` enum('VENDOR','ADVANCE_STOCK','OLD_REPAIRED_STOCK') NOT NULL,
  `item_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_spare_requisition_remarks`
--

CREATE TABLE `cctv_spare_requisition_remarks` (
  `id` int(11) NOT NULL,
  `spare_requisition_id` int(11) NOT NULL,
  `remark` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_status_log`
--

CREATE TABLE `cctv_status_log` (
  `id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_stock`
--

CREATE TABLE `cctv_stock` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `stock_type` enum('ADVANCE_PURCHASE','OLD_REPAIRED','NEW_UNUSED') NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(150) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `source_reference` varchar(100) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `status` enum('In_Stock','Issued','Installed','Returned','Disposed') DEFAULT 'In_Stock',
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_stock_transactions`
--

CREATE TABLE `cctv_stock_transactions` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `transaction_type` enum('IN','OUT','TRANSFER_TO_BRANCH','INSTALL','RETURN_FROM_BRANCH','ACKNOWLEDGED','DISPOSE') NOT NULL,
  `transaction_date` date NOT NULL,
  `ref_table` varchar(50) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `booth_name` varchar(150) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `letter_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_tender_bids`
--

CREATE TABLE `cctv_tender_bids` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `bid_date` date DEFAULT NULL,
  `total_bid_amount` decimal(12,2) DEFAULT NULL,
  `technical_status` enum('Pending','Qualified','Disqualified') DEFAULT 'Pending',
  `financial_status` enum('Pending','Lowest','Not_Lowest') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_tender_bid_items`
--

CREATE TABLE `cctv_tender_bid_items` (
  `id` int(11) NOT NULL,
  `tender_bid_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `total_price` decimal(12,2) DEFAULT NULL,
  `warranty_years` decimal(4,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_vendors`
--

CREATE TABLE `cctv_vendors` (
  `id` int(11) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `vendor_type` enum('ENLISTED','PROCUREMENT','SERVICE','BOTH') DEFAULT 'BOTH',
  `contact_person` varchar(100) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_vendor_bills`
--

CREATE TABLE `cctv_vendor_bills` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(100) NOT NULL,
  `bill_date` date NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `spare_requisition_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) DEFAULT 0.00,
  `parts_amount` decimal(12,2) DEFAULT 0.00,
  `fad_forward_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_status` enum('Pending','Forwarded_to_FAD','Paid','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_modified_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_work_orders`
--

CREATE TABLE `cctv_work_orders` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `work_order_no` varchar(100) DEFAULT NULL,
  `work_order_date` date DEFAULT NULL,
  `delivery_deadline` date DEFAULT NULL,
  `work_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_details`
--

CREATE TABLE `group_details` (
  `id` int(11) NOT NULL,
  `group_no` int(11) NOT NULL,
  `zones` text DEFAULT NULL,
  `group_leader_name` varchar(150) DEFAULT NULL,
  `group_members` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_master`
--

CREATE TABLE `group_master` (
  `id` int(11) NOT NULL,
  `group_no` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ignored_penalties`
--

CREATE TABLE `ignored_penalties` (
  `id` int(11) NOT NULL,
  `original_penalty_id` int(11) DEFAULT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `atm_name` varchar(150) DEFAULT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `vendor_type` varchar(20) DEFAULT NULL,
  `incident_name` varchar(150) DEFAULT NULL,
  `original_down_time` varchar(100) DEFAULT NULL,
  `original_down_time_minutes` int(11) DEFAULT NULL,
  `final_down_time` varchar(100) DEFAULT NULL,
  `final_down_time_minutes` int(11) DEFAULT NULL,
  `penalty_percent` decimal(5,2) DEFAULT NULL,
  `penalty_amount` decimal(12,2) DEFAULT NULL,
  `vendor_ticket_no` varchar(100) DEFAULT NULL,
  `vendor_ticket_date_time` datetime DEFAULT NULL,
  `ignore_reason` text DEFAULT NULL,
  `ignore_instruction_by` varchar(150) DEFAULT NULL,
  `ignore_remarks` text DEFAULT NULL,
  `ignored_by` int(11) DEFAULT NULL,
  `ignored_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_penalties`
--

CREATE TABLE `incident_penalties` (
  `penalty_id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `atm_id` varchar(50) NOT NULL,
  `atm_name` varchar(150) DEFAULT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `vendor_type` varchar(20) NOT NULL,
  `incident_name` varchar(150) DEFAULT NULL,
  `original_down_time` varchar(100) DEFAULT NULL,
  `original_down_time_minutes` int(11) DEFAULT 0,
  `final_down_time` varchar(100) DEFAULT NULL,
  `final_down_time_minutes` int(11) DEFAULT 0,
  `penalty_percent` decimal(5,2) DEFAULT 0.00,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `vendor_ticket_no` varchar(100) DEFAULT NULL,
  `vendor_ticket_date_time` datetime DEFAULT NULL,
  `penalty_status` varchar(20) DEFAULT 'ACTIVE',
  `is_down_time_edited` tinyint(1) DEFAULT 0,
  `is_amount_overridden` tinyint(1) DEFAULT 0,
  `edit_reason` text DEFAULT NULL,
  `permission_by` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_remarks`
--

CREATE TABLE `incident_remarks` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `remark` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penalty_reports`
--

CREATE TABLE `penalty_reports` (
  `id` int(11) NOT NULL,
  `penalty_id` varchar(50) DEFAULT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `vendor_ticket_no` varchar(100) DEFAULT NULL,
  `atm_id` varchar(50) DEFAULT NULL,
  `incident_name` varchar(255) DEFAULT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `service_type` varchar(50) DEFAULT NULL,
  `machine_type` varchar(50) DEFAULT NULL,
  `penalty_from` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `down_time_minutes` int(11) DEFAULT 0,
  `deduction_rate` decimal(5,2) DEFAULT 0.00,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `imposed_by` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_master`
--

CREATE TABLE `problem_master` (
  `id` int(11) NOT NULL,
  `problem_name` varchar(150) NOT NULL,
  `responsible_vendor_type` varchar(20) NOT NULL DEFAULT 'ATM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `role_type` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role_id` int(11) DEFAULT NULL,
  `assigned_zone` varchar(150) DEFAULT NULL,
  `user_type` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_excluded_permissions`
--

CREATE TABLE `user_excluded_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_type_excluded_permissions`
--

CREATE TABLE `user_type_excluded_permissions` (
  `id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_amc_rates`
--

CREATE TABLE `vendor_amc_rates` (
  `id` int(11) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `vendor_type` varchar(20) NOT NULL,
  `amc_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  `machine_type` enum('ATM','CRM') NOT NULL DEFAULT 'ATM',
  `service_type` enum('ATM','CRM','UPS') DEFAULT 'ATM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_contacts`
--

CREATE TABLE `vendor_contacts` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `contact_type` enum('mobile','email','address') NOT NULL,
  `contact_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_master`
--

CREATE TABLE `vendor_master` (
  `id` int(11) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `vendor_type` varchar(100) DEFAULT NULL,
  `vendor_email` varchar(150) DEFAULT NULL,
  `vendor_mobile` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_penalty_rules`
--

CREATE TABLE `vendor_penalty_rules` (
  `id` int(11) NOT NULL,
  `vendor_name` varchar(150) DEFAULT NULL,
  `service_type` enum('ATM','CRM','UPS') NOT NULL DEFAULT 'ATM',
  `vendor_type` varchar(20) NOT NULL,
  `from_minute` int(11) NOT NULL,
  `to_minute` int(11) NOT NULL,
  `penalty_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  `machine_type` enum('ATM','CRM') NOT NULL DEFAULT 'ATM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zonal_head_contacts`
--

CREATE TABLE `zonal_head_contacts` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(150) NOT NULL,
  `zonal_head_name` varchar(150) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `ip_phone` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_branch_map`
--

CREATE TABLE `zone_branch_map` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(150) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zone_master`
--

CREATE TABLE `zone_master` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `active_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `atm_contact`
--
ALTER TABLE `atm_contact`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atm_device_movement`
--
ALTER TABLE `atm_device_movement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atm_ip_details`
--
ALTER TABLE `atm_ip_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atm_master`
--
ALTER TABLE `atm_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `atm_id` (`atm_id`),
  ADD UNIQUE KEY `atm_id_2` (`atm_id`),
  ADD UNIQUE KEY `uq_atm_master_atm_id` (`atm_id`),
  ADD KEY `idx_atm_master_id` (`atm_id`),
  ADD KEY `idx_zone` (`zone_name`),
  ADD KEY `idx_atm_master_atm_id` (`atm_id`),
  ADD KEY `idx_atm_master_zone_name` (`zone_name`);

--
-- Indexes for table `atm_network_info`
--
ALTER TABLE `atm_network_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atm_sg`
--
ALTER TABLE `atm_sg`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atm_update`
--
ALTER TABLE `atm_update`
  ADD PRIMARY KEY (`incident_id`),
  ADD KEY `idx_atm_id` (`atm_id`),
  ADD KEY `idx_status` (`incident_status`),
  ADD KEY `idx_group` (`group_no`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_atm_update_status_group_created` (`incident_status`,`group_no`,`created_at`),
  ADD KEY `idx_atm_update_atm_id` (`atm_id`),
  ADD KEY `idx_atm_update_problem` (`problem`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_empty_positions`
--
ALTER TABLE `cash_empty_positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_zone` (`entry_date`,`zone_name`);

--
-- Indexes for table `cctv_branch_dispatch`
--
ALTER TABLE `cctv_branch_dispatch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dispatch_no` (`dispatch_no`),
  ADD KEY `fk_branch_dispatch_location` (`cctv_location_id`);

--
-- Indexes for table `cctv_branch_dispatch_items`
--
ALTER TABLE `cctv_branch_dispatch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_branch_dispatch_items_dispatch` (`dispatch_id`),
  ADD KEY `fk_branch_dispatch_items_stock` (`stock_id`),
  ADD KEY `fk_branch_dispatch_items_item` (`item_id`);

--
-- Indexes for table `cctv_device_history`
--
ALTER TABLE `cctv_device_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cctv_installed_devices`
--
ALTER TABLE `cctv_installed_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cctv_installed_location` (`cctv_location_id`),
  ADD KEY `fk_cctv_installed_item` (`item_id`),
  ADD KEY `fk_cctv_installed_vendor` (`vendor_id`),
  ADD KEY `idx_serial_no` (`serial_no`),
  ADD KEY `idx_installed_status` (`status`),
  ADD KEY `idx_warranty_end` (`warranty_end_date`);

--
-- Indexes for table `cctv_item_master`
--
ALTER TABLE `cctv_item_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cctv_item_name` (`item_name`);

--
-- Indexes for table `cctv_list`
--
ALTER TABLE `cctv_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cctv_list_remarks_history`
--
ALTER TABLE `cctv_list_remarks_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cctv_list_id` (`cctv_list_id`);

--
-- Indexes for table `cctv_locations`
--
ALTER TABLE `cctv_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_atm_id` (`atm_id`),
  ADD KEY `idx_branch_name` (`branch_name`),
  ADD KEY `idx_zone_name` (`zone_name`),
  ADD KEY `idx_group_no` (`group_no`);

--
-- Indexes for table `cctv_monitor_devices`
--
ALTER TABLE `cctv_monitor_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cctv_list_id` (`cctv_list_id`),
  ADD KEY `idx_atm_id` (`atm_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_zone_name` (`zone_name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `cctv_monitor_results`
--
ALTER TABLE `cctv_monitor_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cctv_qc_records`
--
ALTER TABLE `cctv_qc_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cctv_qc_req` (`requisition_id`);

--
-- Indexes for table `cctv_set_requisition`
--
ALTER TABLE `cctv_set_requisition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_no` (`requisition_no`),
  ADD KEY `fk_cctv_set_req_location` (`cctv_location_id`),
  ADD KEY `fk_cctv_set_req_vendor` (`selected_vendor_id`),
  ADD KEY `idx_req_status` (`status`),
  ADD KEY `idx_req_date` (`requisition_date`);

--
-- Indexes for table `cctv_set_requisition_items`
--
ALTER TABLE `cctv_set_requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cctv_set_req_items_req` (`requisition_id`),
  ADD KEY `fk_cctv_set_req_items_item` (`item_id`);

--
-- Indexes for table `cctv_set_requisition_remarks`
--
ALTER TABLE `cctv_set_requisition_remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `set_requisition_id` (`set_requisition_id`);

--
-- Indexes for table `cctv_spare_replacement_details`
--
ALTER TABLE `cctv_spare_replacement_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_spare_replace_req` (`spare_requisition_id`),
  ADD KEY `fk_spare_replace_old_device` (`old_installed_device_id`),
  ADD KEY `fk_spare_replace_new_item` (`new_item_id`),
  ADD KEY `fk_spare_replace_vendor` (`vendor_id`);

--
-- Indexes for table `cctv_spare_requisition`
--
ALTER TABLE `cctv_spare_requisition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_no` (`requisition_no`),
  ADD KEY `fk_spare_req_location` (`cctv_location_id`),
  ADD KEY `fk_spare_req_vendor` (`assigned_vendor_id`),
  ADD KEY `idx_spare_req_status` (`status`),
  ADD KEY `idx_spare_req_date` (`application_date`);

--
-- Indexes for table `cctv_spare_requisition_items`
--
ALTER TABLE `cctv_spare_requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_spare_req_items_req` (`spare_requisition_id`),
  ADD KEY `fk_spare_req_items_item` (`item_id`);

--
-- Indexes for table `cctv_spare_requisition_remarks`
--
ALTER TABLE `cctv_spare_requisition_remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spare_requisition_id` (`spare_requisition_id`);

--
-- Indexes for table `cctv_status_log`
--
ALTER TABLE `cctv_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_record` (`module_name`,`record_id`);

--
-- Indexes for table `cctv_stock`
--
ALTER TABLE `cctv_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_item` (`item_id`),
  ADD KEY `idx_stock_serial` (`serial_no`),
  ADD KEY `idx_stock_status` (`status`);

--
-- Indexes for table `cctv_stock_transactions`
--
ALTER TABLE `cctv_stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_txn_stock` (`stock_id`);

--
-- Indexes for table `cctv_tender_bids`
--
ALTER TABLE `cctv_tender_bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cctv_tender_req` (`requisition_id`),
  ADD KEY `fk_cctv_tender_vendor` (`vendor_id`);

--
-- Indexes for table `cctv_tender_bid_items`
--
ALTER TABLE `cctv_tender_bid_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cctv_tender_bid_items_bid` (`tender_bid_id`),
  ADD KEY `fk_cctv_tender_bid_items_item` (`item_id`);

--
-- Indexes for table `cctv_vendors`
--
ALTER TABLE `cctv_vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_name` (`vendor_name`);

--
-- Indexes for table `cctv_vendor_bills`
--
ALTER TABLE `cctv_vendor_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vendor_bill_vendor` (`vendor_id`),
  ADD KEY `fk_vendor_bill_spare_req` (`spare_requisition_id`);

--
-- Indexes for table `cctv_work_orders`
--
ALTER TABLE `cctv_work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_workorder_requisition` (`requisition_id`);

--
-- Indexes for table `group_details`
--
ALTER TABLE `group_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_no` (`group_no`);

--
-- Indexes for table `group_master`
--
ALTER TABLE `group_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_no` (`group_no`);

--
-- Indexes for table `ignored_penalties`
--
ALTER TABLE `ignored_penalties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incident_penalties`
--
ALTER TABLE `incident_penalties`
  ADD PRIMARY KEY (`penalty_id`),
  ADD UNIQUE KEY `uniq_incident_vendor` (`incident_id`,`vendor_name`,`vendor_type`);

--
-- Indexes for table `incident_remarks`
--
ALTER TABLE `incident_remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_incident_remarks_incident_created` (`incident_id`,`created_at`,`id`);

--
-- Indexes for table `penalty_reports`
--
ALTER TABLE `penalty_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `problem_master`
--
ALTER TABLE `problem_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `problem_name` (`problem_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_role_permission` (`role_id`,`permission_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_id` (`id`);

--
-- Indexes for table `user_excluded_permissions`
--
ALTER TABLE `user_excluded_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`permission_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`permission_id`);

--
-- Indexes for table `user_type_excluded_permissions`
--
ALTER TABLE `user_type_excluded_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_type` (`user_type`,`permission_id`);

--
-- Indexes for table `vendor_amc_rates`
--
ALTER TABLE `vendor_amc_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendor_contacts`
--
ALTER TABLE `vendor_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_master`
--
ALTER TABLE `vendor_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_name` (`vendor_name`);

--
-- Indexes for table `vendor_penalty_rules`
--
ALTER TABLE `vendor_penalty_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zonal_head_contacts`
--
ALTER TABLE `zonal_head_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zone_name` (`zone_name`);

--
-- Indexes for table `zone_branch_map`
--
ALTER TABLE `zone_branch_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_zone_branch` (`zone_name`,`branch_code`,`branch_name`);

--
-- Indexes for table `zone_master`
--
ALTER TABLE `zone_master`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `atm_contact`
--
ALTER TABLE `atm_contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_device_movement`
--
ALTER TABLE `atm_device_movement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_ip_details`
--
ALTER TABLE `atm_ip_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_master`
--
ALTER TABLE `atm_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_network_info`
--
ALTER TABLE `atm_network_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_sg`
--
ALTER TABLE `atm_sg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atm_update`
--
ALTER TABLE `atm_update`
  MODIFY `incident_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_empty_positions`
--
ALTER TABLE `cash_empty_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_branch_dispatch`
--
ALTER TABLE `cctv_branch_dispatch`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_branch_dispatch_items`
--
ALTER TABLE `cctv_branch_dispatch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_device_history`
--
ALTER TABLE `cctv_device_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_installed_devices`
--
ALTER TABLE `cctv_installed_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_item_master`
--
ALTER TABLE `cctv_item_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_list`
--
ALTER TABLE `cctv_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_list_remarks_history`
--
ALTER TABLE `cctv_list_remarks_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_locations`
--
ALTER TABLE `cctv_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_monitor_devices`
--
ALTER TABLE `cctv_monitor_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_monitor_results`
--
ALTER TABLE `cctv_monitor_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_qc_records`
--
ALTER TABLE `cctv_qc_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_set_requisition`
--
ALTER TABLE `cctv_set_requisition`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_set_requisition_items`
--
ALTER TABLE `cctv_set_requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_set_requisition_remarks`
--
ALTER TABLE `cctv_set_requisition_remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_spare_replacement_details`
--
ALTER TABLE `cctv_spare_replacement_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_spare_requisition`
--
ALTER TABLE `cctv_spare_requisition`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_spare_requisition_items`
--
ALTER TABLE `cctv_spare_requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_spare_requisition_remarks`
--
ALTER TABLE `cctv_spare_requisition_remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_status_log`
--
ALTER TABLE `cctv_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_stock`
--
ALTER TABLE `cctv_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_stock_transactions`
--
ALTER TABLE `cctv_stock_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_tender_bids`
--
ALTER TABLE `cctv_tender_bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_tender_bid_items`
--
ALTER TABLE `cctv_tender_bid_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_vendors`
--
ALTER TABLE `cctv_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_vendor_bills`
--
ALTER TABLE `cctv_vendor_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_work_orders`
--
ALTER TABLE `cctv_work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_details`
--
ALTER TABLE `group_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_master`
--
ALTER TABLE `group_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ignored_penalties`
--
ALTER TABLE `ignored_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_penalties`
--
ALTER TABLE `incident_penalties`
  MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_remarks`
--
ALTER TABLE `incident_remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penalty_reports`
--
ALTER TABLE `penalty_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_master`
--
ALTER TABLE `problem_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_excluded_permissions`
--
ALTER TABLE `user_excluded_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_type_excluded_permissions`
--
ALTER TABLE `user_type_excluded_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_amc_rates`
--
ALTER TABLE `vendor_amc_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_contacts`
--
ALTER TABLE `vendor_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_master`
--
ALTER TABLE `vendor_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_penalty_rules`
--
ALTER TABLE `vendor_penalty_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zonal_head_contacts`
--
ALTER TABLE `zonal_head_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zone_branch_map`
--
ALTER TABLE `zone_branch_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zone_master`
--
ALTER TABLE `zone_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cctv_branch_dispatch`
--
ALTER TABLE `cctv_branch_dispatch`
  ADD CONSTRAINT `fk_branch_dispatch_location` FOREIGN KEY (`cctv_location_id`) REFERENCES `cctv_locations` (`id`);

--
-- Constraints for table `cctv_branch_dispatch_items`
--
ALTER TABLE `cctv_branch_dispatch_items`
  ADD CONSTRAINT `fk_branch_dispatch_items_dispatch` FOREIGN KEY (`dispatch_id`) REFERENCES `cctv_branch_dispatch` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_branch_dispatch_items_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`),
  ADD CONSTRAINT `fk_branch_dispatch_items_stock` FOREIGN KEY (`stock_id`) REFERENCES `cctv_stock` (`id`);

--
-- Constraints for table `cctv_installed_devices`
--
ALTER TABLE `cctv_installed_devices`
  ADD CONSTRAINT `fk_cctv_installed_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`),
  ADD CONSTRAINT `fk_cctv_installed_location` FOREIGN KEY (`cctv_location_id`) REFERENCES `cctv_locations` (`id`),
  ADD CONSTRAINT `fk_cctv_installed_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_list_remarks_history`
--
ALTER TABLE `cctv_list_remarks_history`
  ADD CONSTRAINT `fk_cctv_remarks_history_cctv_list` FOREIGN KEY (`cctv_list_id`) REFERENCES `cctv_list` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_qc_records`
--
ALTER TABLE `cctv_qc_records`
  ADD CONSTRAINT `fk_cctv_qc_req` FOREIGN KEY (`requisition_id`) REFERENCES `cctv_set_requisition` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_set_requisition`
--
ALTER TABLE `cctv_set_requisition`
  ADD CONSTRAINT `fk_cctv_set_req_location` FOREIGN KEY (`cctv_location_id`) REFERENCES `cctv_locations` (`id`),
  ADD CONSTRAINT `fk_cctv_set_req_vendor` FOREIGN KEY (`selected_vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_set_requisition_items`
--
ALTER TABLE `cctv_set_requisition_items`
  ADD CONSTRAINT `fk_cctv_set_req_items_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`),
  ADD CONSTRAINT `fk_cctv_set_req_items_req` FOREIGN KEY (`requisition_id`) REFERENCES `cctv_set_requisition` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_set_requisition_remarks`
--
ALTER TABLE `cctv_set_requisition_remarks`
  ADD CONSTRAINT `cctv_set_requisition_remarks_ibfk_1` FOREIGN KEY (`set_requisition_id`) REFERENCES `cctv_set_requisition` (`id`);

--
-- Constraints for table `cctv_spare_replacement_details`
--
ALTER TABLE `cctv_spare_replacement_details`
  ADD CONSTRAINT `fk_spare_replace_new_item` FOREIGN KEY (`new_item_id`) REFERENCES `cctv_item_master` (`id`),
  ADD CONSTRAINT `fk_spare_replace_old_device` FOREIGN KEY (`old_installed_device_id`) REFERENCES `cctv_installed_devices` (`id`),
  ADD CONSTRAINT `fk_spare_replace_req` FOREIGN KEY (`spare_requisition_id`) REFERENCES `cctv_spare_requisition` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spare_replace_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_spare_requisition`
--
ALTER TABLE `cctv_spare_requisition`
  ADD CONSTRAINT `fk_spare_req_location` FOREIGN KEY (`cctv_location_id`) REFERENCES `cctv_locations` (`id`),
  ADD CONSTRAINT `fk_spare_req_vendor` FOREIGN KEY (`assigned_vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_spare_requisition_items`
--
ALTER TABLE `cctv_spare_requisition_items`
  ADD CONSTRAINT `fk_spare_req_items_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`),
  ADD CONSTRAINT `fk_spare_req_items_req` FOREIGN KEY (`spare_requisition_id`) REFERENCES `cctv_spare_requisition` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_spare_requisition_remarks`
--
ALTER TABLE `cctv_spare_requisition_remarks`
  ADD CONSTRAINT `cctv_spare_requisition_remarks_ibfk_1` FOREIGN KEY (`spare_requisition_id`) REFERENCES `cctv_spare_requisition` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_stock`
--
ALTER TABLE `cctv_stock`
  ADD CONSTRAINT `fk_stock_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`);

--
-- Constraints for table `cctv_stock_transactions`
--
ALTER TABLE `cctv_stock_transactions`
  ADD CONSTRAINT `fk_stock_txn_stock` FOREIGN KEY (`stock_id`) REFERENCES `cctv_stock` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cctv_tender_bids`
--
ALTER TABLE `cctv_tender_bids`
  ADD CONSTRAINT `fk_cctv_tender_req` FOREIGN KEY (`requisition_id`) REFERENCES `cctv_set_requisition` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cctv_tender_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_tender_bid_items`
--
ALTER TABLE `cctv_tender_bid_items`
  ADD CONSTRAINT `fk_cctv_tender_bid_items_bid` FOREIGN KEY (`tender_bid_id`) REFERENCES `cctv_tender_bids` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cctv_tender_bid_items_item` FOREIGN KEY (`item_id`) REFERENCES `cctv_item_master` (`id`);

--
-- Constraints for table `cctv_vendor_bills`
--
ALTER TABLE `cctv_vendor_bills`
  ADD CONSTRAINT `fk_vendor_bill_spare_req` FOREIGN KEY (`spare_requisition_id`) REFERENCES `cctv_spare_requisition` (`id`),
  ADD CONSTRAINT `fk_vendor_bill_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `cctv_vendors` (`id`);

--
-- Constraints for table `cctv_work_orders`
--
ALTER TABLE `cctv_work_orders`
  ADD CONSTRAINT `fk_workorder_requisition` FOREIGN KEY (`requisition_id`) REFERENCES `cctv_set_requisition` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_remarks`
--
ALTER TABLE `incident_remarks`
  ADD CONSTRAINT `fk_remarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vendor_contacts`
--
ALTER TABLE `vendor_contacts`
  ADD CONSTRAINT `vendor_contacts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendor_master` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
