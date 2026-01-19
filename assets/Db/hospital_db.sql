-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 19, 2026 at 10:44 PM
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
-- Database: `hospital_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `appointment_datetime` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','CONFIRMED','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `employee_id`, `appointment_datetime`, `reason`, `status`, `created_at`) VALUES
(1, 1, 21, '2026-01-13 15:36:00', NULL, 'CANCELLED', '2026-01-14 12:37:12'),
(2, 2, 2, '2026-01-14 19:00:00', NULL, 'COMPLETED', '2026-01-14 12:39:03'),
(3, 9, 3, '2026-01-19 09:00:00', NULL, 'COMPLETED', '2026-01-18 00:47:30'),
(4, 35, 3, '2026-01-20 08:00:00', NULL, 'CONFIRMED', '2026-01-18 00:48:13'),
(5, 22, 10, '2026-01-19 08:00:00', NULL, 'COMPLETED', '2026-01-18 12:58:35'),
(6, 22, 10, '2026-01-19 09:00:00', NULL, 'CONFIRMED', '2026-01-18 13:00:14'),
(7, 32, 26, '2026-01-20 10:15:00', NULL, 'PENDING', '2026-01-19 19:38:14');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `bill_type` enum('CONSULTATION','PRESCRIPTION','SURGERY','OTHER') NOT NULL DEFAULT 'OTHER',
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
  `payment_method` enum('CASH','EVCPLUS','CARD','BANK') DEFAULT NULL,
  `receipt_no` varchar(40) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `appointment_id`, `patient_id`, `employee_id`, `prescription_id`, `bill_type`, `description`, `amount`, `discount`, `total`, `status`, `payment_method`, `receipt_no`, `paid_at`, `created_at`) VALUES
(4, NULL, 1, 3, 2, 'PRESCRIPTION', 'Prescription #2', 7.80, 0.00, 7.80, 'PAID', 'EVCPLUS', 'RCPT-20260117-F35A43', '2026-01-18 01:50:53', '2026-01-17 21:58:19'),
(5, NULL, 2, 3, 1, 'PRESCRIPTION', 'Prescription #1', 33.00, 0.00, 33.00, 'PAID', 'CASH', 'RCPT-20260117-94B88E', '2026-01-18 01:35:50', '2026-01-17 22:02:12'),
(6, NULL, 1, NULL, NULL, 'SURGERY', 'Surgery: Cesarean Section (C-Section)', 450.00, 0.00, 450.00, 'PAID', 'CASH', 'RCPT-20260117-DA1C26', '2026-01-18 01:43:20', '2026-01-17 22:37:34'),
(7, NULL, 3, 3, 3, 'PRESCRIPTION', 'Prescription #3', 0.75, 0.00, 0.75, 'PAID', 'CASH', 'RCPT-20260117-368355', '2026-01-18 01:51:33', '2026-01-17 22:51:12'),
(8, NULL, 16, 3, NULL, 'SURGERY', 'Bowel Resection (Small/Large) • $1,800.00', 1800.00, 200.00, 1600.00, 'PAID', 'CARD', 'RCPT-20260118-4AB2E2', '2026-01-18 02:29:10', '2026-01-17 23:29:00'),
(10, NULL, 17, 32, NULL, 'SURGERY', 'ORIF (Fracture Fixation) - Large Bone • $2,200.00', 2200.00, 200.00, 2000.00, 'PAID', 'CARD', 'RCPT-20260118-6DE89A', '2026-01-18 15:53:59', '2026-01-18 12:53:48'),
(11, NULL, 22, NULL, NULL, 'CONSULTATION', 'Consultation fee', 10.00, 0.00, 10.00, 'PAID', 'EVCPLUS', 'RCPT-20260118-4050CD', '2026-01-18 16:01:41', '2026-01-18 13:01:35'),
(12, NULL, 56, 99, NULL, 'SURGERY', 'ORIF (Fracture Fixation) - Large Bone • $2,200.00', 2200.00, 200.00, 2000.00, 'PAID', 'CARD', 'RCPT-20260119-7BD9E9', '2026-01-19 12:45:57', '2026-01-19 09:45:45'),
(13, NULL, 2, 3, 4, 'PRESCRIPTION', 'Prescription #4', 2.50, 0.00, 2.50, 'PAID', 'CASH', 'RCPT-20260119-8233F2', '2026-01-19 12:50:08', '2026-01-19 09:49:58'),
(14, NULL, 64, NULL, NULL, 'CONSULTATION', 'Consultation fee', 10.00, 10.00, 0.00, 'PAID', 'EVCPLUS', 'RCPT-20260119-7770EB', '2026-01-20 00:10:22', '2026-01-19 20:58:01'),
(15, NULL, 12, NULL, NULL, 'CONSULTATION', 'Consultation fee', 10.00, 2.00, 8.00, 'PAID', 'EVCPLUS', 'RCPT-20260119-BF5582', '2026-01-20 00:10:10', '2026-01-19 21:06:24'),
(16, NULL, 50, NULL, NULL, 'CONSULTATION', 'Consultation fee', 10.00, 0.00, 10.00, 'PAID', 'EVCPLUS', 'RCPT-20260119-F25773', '2026-01-20 00:30:24', '2026-01-19 21:29:59'),
(17, NULL, 63, NULL, NULL, 'CONSULTATION', 'Consultation fee', 10.00, 0.00, 10.00, 'PAID', 'EVCPLUS', 'RCPT-20260119-238334', '2026-01-20 00:30:18', '2026-01-19 21:30:12');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `emp_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `job_title` enum('DOCTOR','NURSE','ADMINISTRATOR','LAB','PHARMACIST','RECEPTIONIST') NOT NULL,
  `department` varchar(120) DEFAULT NULL,
  `specialization` varchar(120) DEFAULT NULL,
  `hire_date` date DEFAULT current_timestamp(),
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `emp_code`, `full_name`, `gender`, `phone`, `email`, `job_title`, `department`, `specialization`, `hire_date`, `status`, `created_at`) VALUES
(2, NULL, NULL, 'Mohamed Mahad Abdi', NULL, '+252686042127', 'mo.haji.abdi1@gmail.com', 'DOCTOR', NULL, NULL, '2026-01-12', 'INACTIVE', '2026-01-12 17:23:09'),
(3, NULL, NULL, 'Ahmed Ali', NULL, '+252686042127', 'mo.m.haji.abdi25@gmail.com', 'DOCTOR', NULL, NULL, NULL, 'ACTIVE', '2026-01-12 17:24:08'),
(4, NULL, NULL, 'Dr Ahmed Mohamed Ali', 'Male', '0613500001', 'ahmed.ali@hospital.so', 'DOCTOR', 'Internal Medicine', 'General Physician', '2018-02-12', 'ACTIVE', '2026-01-17 23:31:16'),
(5, NULL, NULL, 'Dr Abdirahman Hassan Yusuf', 'Male', '0613500002', 'abdirahman.hassan@hospital.so', 'DOCTOR', 'Cardiology', 'Cardiologist', '2017-06-21', 'ACTIVE', '2026-01-17 23:31:16'),
(6, NULL, NULL, 'Dr Mohamed Abdullahi Noor', 'Male', '0613500003', 'mohamed.noor@hospital.so', 'DOCTOR', 'Pediatrics', 'Pediatrician', '2019-09-10', 'ACTIVE', '2026-01-17 23:31:16'),
(7, NULL, NULL, 'Dr Hassan Ali Warsame', 'Male', '0613500004', 'hassan.warsame@hospital.so', 'DOCTOR', 'Surgery', 'General Surgeon', '2016-03-18', 'ACTIVE', '2026-01-17 23:31:16'),
(8, NULL, NULL, 'Dr Yusuf Ahmed Farah', 'Male', '0613500005', 'yusuf.farah@hospital.so', 'DOCTOR', 'Orthopedics', 'Orthopedic Surgeon', '2020-01-05', 'ACTIVE', '2026-01-17 23:31:16'),
(9, NULL, NULL, 'Dr Abdullahi Omar Ahmed', 'Male', '0613500006', 'abdullahi.omar@hospital.so', 'DOCTOR', 'ENT', 'ENT Specialist', '2018-11-14', 'ACTIVE', '2026-01-17 23:31:16'),
(10, NULL, NULL, 'Dr Ismail Mohamed Abdi', 'Male', '0613500007', 'ismail.abdi@hospital.so', 'DOCTOR', 'Radiology', 'Radiologist', '2017-07-09', 'ACTIVE', '2026-01-17 23:31:16'),
(11, NULL, NULL, 'Dr Said Ahmed Jama', 'Male', '0613500008', 'said.jama@hospital.so', 'DOCTOR', 'Dermatology', 'Dermatologist', '2019-05-30', 'ACTIVE', '2026-01-17 23:31:16'),
(12, NULL, NULL, 'Dr Omar Hassan Sheikh', 'Male', '0613500009', 'omar.sheikh@hospital.so', 'DOCTOR', 'Neurology', 'Neurologist', '2016-10-22', 'ACTIVE', '2026-01-17 23:31:16'),
(13, NULL, NULL, 'Dr Mahad Abdi Mohamed', 'Male', '0613500010', 'mahad.abdi@hospital.so', 'DOCTOR', 'Internal Medicine', 'Endocrinologist', '2021-02-01', 'ACTIVE', '2026-01-17 23:31:16'),
(14, NULL, NULL, 'Dr Amina Hassan Ali', 'Female', '0613500011', 'amina.ali@hospital.so', 'DOCTOR', 'Gynecology', 'Gynecologist', '2018-04-17', 'ACTIVE', '2026-01-17 23:31:16'),
(15, NULL, NULL, 'Dr Fatima Mohamed Noor', 'Female', '0613500012', 'fatima.noor@hospital.so', 'DOCTOR', 'Obstetrics', 'Obstetrician', '2019-08-06', 'ACTIVE', '2026-01-17 23:31:16'),
(16, NULL, NULL, 'Dr Khadra Abdi Ahmed', 'Female', '0613500013', 'khadra.ahmed@hospital.so', 'DOCTOR', 'Pediatrics', 'Pediatrician', '2017-12-29', 'ACTIVE', '2026-01-17 23:31:16'),
(17, NULL, NULL, 'Dr Hodan Yusuf Farah', 'Female', '0613500014', 'hodan.farah@hospital.so', 'DOCTOR', 'Family Medicine', 'Family Physician', '2020-07-17', 'ACTIVE', '2026-01-17 23:31:16'),
(18, NULL, NULL, 'Dr Maryan Ali Warsame', 'Female', '0613500015', 'maryan.warsame@hospital.so', 'DOCTOR', 'Internal Medicine', 'General Physician', '2016-09-23', 'ACTIVE', '2026-01-17 23:31:16'),
(19, NULL, NULL, 'Dr Zahra Mohamed Abdi', 'Female', '0613500016', 'zahra.abdi@hospital.so', 'DOCTOR', 'Dermatology', 'Dermatologist', '2018-12-10', 'ACTIVE', '2026-01-17 23:31:16'),
(20, NULL, NULL, 'Dr Sahra Hassan Jama', 'Female', '0613500017', 'sahra.jama@hospital.so', 'DOCTOR', 'Radiology', 'Radiologist', '2017-03-04', 'ACTIVE', '2026-01-17 23:31:16'),
(21, NULL, NULL, 'Dr Fowsiya Ahmed Omar', 'Female', '0613500018', 'fowsiya.omar@hospital.so', 'DOCTOR', 'Neurology', 'Neurologist', '2019-06-28', 'ACTIVE', '2026-01-17 23:31:16'),
(22, NULL, NULL, 'Dr Nimo Abdullahi Yusuf', 'Female', '0613500019', 'nimo.yusuf@hospital.so', 'DOCTOR', 'Psychiatry', 'Psychiatrist', '2020-11-15', 'ACTIVE', '2026-01-17 23:31:16'),
(23, NULL, NULL, 'Dr Halima Mohamed Ali', 'Female', '0613500020', 'halima.ali@hospital.so', 'DOCTOR', 'Anesthesiology', 'Anesthesiologist', '2016-08-08', 'ACTIVE', '2026-01-17 23:31:16'),
(24, NULL, NULL, 'Dr Ibrahim Hassan Ali', 'Male', '0613500021', 'ibrahim.ali@hospital.so', 'DOCTOR', 'Emergency', 'Emergency Physician', '2017-05-14', 'ACTIVE', '2026-01-17 23:31:16'),
(25, NULL, NULL, 'Dr Mustafa Mohamed Farah', 'Male', '0613500022', 'mustafa.farah@hospital.so', 'DOCTOR', 'ICU', 'Critical Care', '2018-02-03', 'ACTIVE', '2026-01-17 23:31:16'),
(26, NULL, NULL, 'Dr Salman Ahmed Noor', 'Male', '0613500023', 'salman.noor@hospital.so', 'DOCTOR', 'Urology', 'Urologist', '2019-07-19', 'ACTIVE', '2026-01-17 23:31:16'),
(27, NULL, NULL, 'Dr Bilal Abdi Yusuf', 'Male', '0613500024', 'bilal.yusuf@hospital.so', 'DOCTOR', 'Nephrology', 'Nephrologist', '2021-01-26', 'ACTIVE', '2026-01-17 23:31:16'),
(28, NULL, NULL, 'Dr Hamza Ali Farah', 'Male', '0613500025', 'hamza.farah@hospital.so', 'DOCTOR', 'Pulmonology', 'Pulmonologist', '2016-09-11', 'ACTIVE', '2026-01-17 23:31:16'),
(29, NULL, NULL, 'Dr Anas Mohamed Sheikh', 'Male', '0613500026', 'anas.sheikh@hospital.so', 'DOCTOR', 'Gastroenterology', 'Gastroenterologist', '2018-12-01', 'ACTIVE', '2026-01-17 23:31:16'),
(30, NULL, NULL, 'Dr Sharif Ahmed Omar', 'Male', '0613500027', 'sharif.omar@hospital.so', 'DOCTOR', 'Oncology', 'Oncologist', '2017-04-22', 'ACTIVE', '2026-01-17 23:31:16'),
(31, NULL, NULL, 'Dr Abdinasir Abdi Jama', 'Male', '0613500028', 'abdinasir.jama@hospital.so', 'DOCTOR', 'Hematology', 'Hematologist', '2019-06-09', 'ACTIVE', '2026-01-17 23:31:16'),
(32, NULL, NULL, 'Dr Jamal Mohamed Yusuf', 'Male', '0613500029', 'jamal.yusuf@hospital.so', 'DOCTOR', 'Infectious Disease', 'Infectious Disease Specialist', '2020-10-30', 'ACTIVE', '2026-01-17 23:31:16'),
(33, NULL, NULL, 'Dr Yahya Ali Warsame', 'Male', '0613500030', 'yahya.warsame@hospital.so', 'DOCTOR', 'Rheumatology', 'Rheumatologist', '2017-03-18', 'ACTIVE', '2026-01-17 23:31:16'),
(34, NULL, NULL, 'Ayaan Hassan Ali', 'Female', '0613600001', 'ayaan.ali@hospital.so', 'NURSE', 'Emergency', 'ER Nurse', '2019-01-10', 'ACTIVE', '2026-01-17 23:31:16'),
(35, NULL, NULL, 'Hodan Mohamed Noor', 'Female', '0613600002', 'hodan.noor@hospital.so', 'NURSE', 'Pediatrics', 'Child Care Nurse', '2020-03-14', 'ACTIVE', '2026-01-17 23:31:16'),
(36, NULL, NULL, 'Maryan Abdi Ahmed', 'Female', '0613600003', 'maryan.ahmed@hospital.so', 'NURSE', 'ICU', 'Critical Care Nurse', '2018-07-21', 'ACTIVE', '2026-01-17 23:31:16'),
(37, NULL, NULL, 'Sahra Yusuf Farah', 'Female', '0613600004', 'sahra.farah@hospital.so', 'NURSE', 'Maternity', 'Midwife', '2019-09-05', 'ACTIVE', '2026-01-17 23:31:16'),
(38, NULL, NULL, 'Halima Hassan Jama', 'Female', '0613600005', 'halima.jama@hospital.so', 'NURSE', 'Surgery', 'Theatre Nurse', '2017-11-30', 'ACTIVE', '2026-01-17 23:31:16'),
(39, NULL, NULL, 'Fartun Mohamed Ali', 'Female', '0613600006', 'fartun.ali@hospital.so', 'NURSE', 'Internal Medicine', 'Ward Nurse', '2021-02-18', 'ACTIVE', '2026-01-17 23:31:16'),
(40, NULL, NULL, 'Nimo Abdirahman Omar', 'Female', '0613600007', 'nimo.omar@hospital.so', 'NURSE', 'OPD', 'Outpatient Nurse', '2020-06-12', 'ACTIVE', '2026-01-17 23:31:16'),
(41, NULL, NULL, 'Bilan Ahmed Warsame', 'Female', '0613600008', 'bilan.warsame@hospital.so', 'NURSE', 'Emergency', 'Trauma Nurse', '2018-04-26', 'ACTIVE', '2026-01-17 23:31:16'),
(42, NULL, NULL, 'Ubah Mohamed Yusuf', 'Female', '0613600009', 'ubah.yusuf@hospital.so', 'NURSE', 'Pediatrics', 'Child Care Nurse', '2019-10-09', 'ACTIVE', '2026-01-17 23:31:16'),
(43, NULL, NULL, 'Rahma Hassan Ali', 'Female', '0613600010', 'rahma.ali@hospital.so', 'NURSE', 'Maternity', 'Midwife', '2021-01-04', 'ACTIVE', '2026-01-17 23:31:16'),
(44, NULL, NULL, 'Shukri Abdi Farah', 'Female', '0613600011', 'shukri.farah@hospital.so', 'NURSE', 'ICU', 'Critical Care Nurse', '2017-08-19', 'ACTIVE', '2026-01-17 23:31:16'),
(45, NULL, NULL, 'Nasra Mohamed Jama', 'Female', '0613600012', 'nasra.jama@hospital.so', 'NURSE', 'Surgery', 'Recovery Nurse', '2018-12-11', 'ACTIVE', '2026-01-17 23:31:16'),
(46, NULL, NULL, 'Ilham Ahmed Noor', 'Female', '0613600013', 'ilham.noor@hospital.so', 'NURSE', 'Internal Medicine', 'Ward Nurse', '2019-05-23', 'ACTIVE', '2026-01-17 23:31:16'),
(47, NULL, NULL, 'Safiya Yusuf Abdi', 'Female', '0613600014', 'safiya.abdi@hospital.so', 'NURSE', 'Emergency', 'ER Nurse', '2020-07-15', 'ACTIVE', '2026-01-17 23:31:16'),
(48, NULL, NULL, 'Hibo Hassan Ali', 'Female', '0613600015', 'hibo.ali@hospital.so', 'NURSE', 'OPD', 'Outpatient Nurse', '2018-02-02', 'ACTIVE', '2026-01-17 23:31:16'),
(49, NULL, NULL, 'Abdi Mohamed Ali', 'Male', '0613600016', 'abdi.ali@hospital.so', 'NURSE', 'Emergency', 'ER Nurse', '2017-06-09', 'ACTIVE', '2026-01-17 23:31:16'),
(50, NULL, NULL, 'Yusuf Hassan Farah', 'Male', '0613600017', 'yusuf.farah@hospital.so', 'NURSE', 'ICU', 'Critical Care Nurse', '2019-09-18', 'ACTIVE', '2026-01-17 23:31:16'),
(51, NULL, NULL, 'Mahad Abdi Noor', 'Male', '0613600018', 'mahad.noor@hospital.so', 'NURSE', 'Surgery', 'Theatre Nurse', '2018-03-27', 'ACTIVE', '2026-01-17 23:31:16'),
(52, NULL, NULL, 'Ismail Mohamed Jama', 'Male', '0613600019', 'ismail.jama@hospital.so', 'NURSE', 'Internal Medicine', 'Ward Nurse', '2020-11-06', 'ACTIVE', '2026-01-17 23:31:16'),
(53, NULL, NULL, 'Hassan Ahmed Yusuf', 'Male', '0613600020', 'hassan.yusuf@hospital.so', 'NURSE', 'OPD', 'Outpatient Nurse', '2021-04-14', 'ACTIVE', '2026-01-17 23:31:16'),
(54, NULL, NULL, 'Mustafa Ali Warsame', 'Male', '0613600021', 'mustafa.warsame@hospital.so', 'NURSE', 'Emergency', 'Trauma Nurse', '2017-10-08', 'ACTIVE', '2026-01-17 23:31:16'),
(55, NULL, NULL, 'Abdirizak Mohamed Farah', 'Male', '0613600022', 'abdirizak.farah@hospital.so', 'NURSE', 'Pediatrics', 'Child Care Nurse', '2018-05-19', 'ACTIVE', '2026-01-17 23:31:16'),
(56, NULL, NULL, 'Salman Hassan Ali', 'Male', '0613600023', 'salman.ali@hospital.so', 'NURSE', 'ICU', 'Critical Care Nurse', '2019-12-01', 'ACTIVE', '2026-01-17 23:31:16'),
(57, NULL, NULL, 'Bilal Ahmed Noor', 'Male', '0613600024', 'bilal.noor@hospital.so', 'NURSE', 'Surgery', 'Recovery Nurse', '2020-08-22', 'ACTIVE', '2026-01-17 23:31:16'),
(58, NULL, NULL, 'Hamza Abdi Jama', 'Male', '0613600025', 'hamza.jama@hospital.so', 'NURSE', 'Internal Medicine', 'Ward Nurse', '2021-02-09', 'ACTIVE', '2026-01-17 23:31:16'),
(59, NULL, NULL, 'Asha Mohamed Ali', 'Female', '0613600026', 'asha.ali@hospital.so', 'NURSE', 'Maternity', 'Midwife', '2019-06-03', 'ACTIVE', '2026-01-17 23:31:16'),
(60, NULL, NULL, 'Muna Hassan Farah', 'Female', '0613600027', 'muna.farah@hospital.so', 'NURSE', 'OPD', 'Outpatient Nurse', '2018-09-12', 'ACTIVE', '2026-01-17 23:31:16'),
(61, NULL, NULL, 'Layla Abdi Yusuf', 'Female', '0613600028', 'layla.yusuf@hospital.so', 'NURSE', 'Emergency', 'ER Nurse', '2020-01-25', 'ACTIVE', '2026-01-17 23:31:16'),
(62, NULL, NULL, 'Sumaya Ahmed Ali', 'Female', '0613600029', 'sumaya.ali@hospital.so', 'NURSE', 'Pediatrics', 'Child Care Nurse', '2021-03-17', 'ACTIVE', '2026-01-17 23:31:16'),
(63, NULL, NULL, 'Ruqiya Mohamed Noor', 'Female', '0613600030', 'ruqiya.noor@hospital.so', 'NURSE', 'Internal Medicine', 'Ward Nurse', '2017-07-29', 'ACTIVE', '2026-01-17 23:31:16'),
(64, NULL, NULL, 'Ismail Mohamed Jama', 'Male', '0613800001', 'ismail.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Technician', '2018-05-10', 'ACTIVE', '2026-01-17 23:31:16'),
(65, NULL, NULL, 'Khadra Ahmed Noor', 'Female', '0613800002', 'khadra.lab@hospital.so', 'LAB', 'Laboratory', 'Hematology', '2019-07-14', 'ACTIVE', '2026-01-17 23:31:16'),
(66, NULL, NULL, 'Yusuf Hassan Ali', 'Male', '0613800003', 'yusuf.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Analyst', '2020-09-01', 'ACTIVE', '2026-01-17 23:31:16'),
(67, NULL, NULL, 'Hibo Mohamed Farah', 'Female', '0613800004', 'hibo.lab@hospital.so', 'LAB', 'Laboratory', 'Microbiology', '2017-12-20', 'ACTIVE', '2026-01-17 23:31:16'),
(68, NULL, NULL, 'Abdiwali Ahmed Jama', 'Male', '0613800005', 'abdiwali.lab@hospital.so', 'LAB', 'Laboratory', 'Pathology', '2018-08-08', 'ACTIVE', '2026-01-17 23:31:16'),
(69, NULL, NULL, 'Nasra Yusuf Ali', 'Female', '0613800006', 'nasra.lab@hospital.so', 'LAB', 'Laboratory', 'Sample Processing', '2021-01-15', 'ACTIVE', '2026-01-17 23:31:16'),
(70, NULL, NULL, 'Mustafa Mohamed Noor', 'Male', '0613800007', 'mustafa.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Supervisor', '2019-06-03', 'ACTIVE', '2026-01-17 23:31:16'),
(71, NULL, NULL, 'Ilham Hassan Farah', 'Female', '0613800008', 'ilham.lab@hospital.so', 'LAB', 'Laboratory', 'Clinical Chemistry', '2020-11-22', 'ACTIVE', '2026-01-17 23:31:16'),
(72, NULL, NULL, 'Bilal Abdi Yusuf', 'Male', '0613800009', 'bilal.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Technician', '2017-03-18', 'ACTIVE', '2026-01-17 23:31:16'),
(73, NULL, NULL, 'Ruqiya Ahmed Ali', 'Female', '0613800010', 'ruqiya.lab@hospital.so', 'LAB', 'Laboratory', 'Blood Bank', '2022-04-10', 'ACTIVE', '2026-01-17 23:31:16'),
(74, NULL, NULL, 'Abdirahman Mohamed Farah', 'Male', '0613800011', 'abdirahman.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Analyst', '2019-02-14', 'ACTIVE', '2026-01-17 23:31:16'),
(75, NULL, NULL, 'Fowsiya Hassan Ali', 'Female', '0613800012', 'fowsiya.lab@hospital.so', 'LAB', 'Laboratory', 'Microbiology', '2020-06-09', 'ACTIVE', '2026-01-17 23:31:16'),
(76, NULL, NULL, 'Hassan Ahmed Noor', 'Male', '0613800013', 'hassan.lab@hospital.so', 'LAB', 'Laboratory', 'Pathology', '2018-10-21', 'ACTIVE', '2026-01-17 23:31:16'),
(77, NULL, NULL, 'Muna Yusuf Jama', 'Female', '0613800014', 'muna.lab@hospital.so', 'LAB', 'Laboratory', 'Sample Processing', '2021-03-17', 'ACTIVE', '2026-01-17 23:31:16'),
(78, NULL, NULL, 'Ibrahim Abdi Ali', 'Male', '0613800015', 'ibrahim.lab@hospital.so', 'LAB', 'Laboratory', 'Lab Technician', '2017-07-05', 'ACTIVE', '2026-01-17 23:31:16'),
(79, NULL, NULL, 'Salman Hassan Ali', 'Male', '0613800041', 'salman.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Clinical Pharmacy', '2018-02-14', 'ACTIVE', '2026-01-17 23:31:16'),
(80, NULL, NULL, 'Ayaan Mohamed Noor', 'Female', '0613800042', 'ayaan.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Dispensing', '2019-05-22', 'ACTIVE', '2026-01-17 23:31:16'),
(81, NULL, NULL, 'Mustafa Abdi Farah', 'Male', '0613800043', 'mustafa.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Drug Inventory', '2020-08-01', 'ACTIVE', '2026-01-17 23:31:16'),
(82, NULL, NULL, 'Hodan Yusuf Ali', 'Female', '0613800044', 'hodan.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Patient Counseling', '2017-11-18', 'ACTIVE', '2026-01-17 23:31:16'),
(83, NULL, NULL, 'Bilal Ahmed Jama', 'Male', '0613800045', 'bilal.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Clinical Pharmacy', '2018-09-07', 'ACTIVE', '2026-01-17 23:31:16'),
(84, NULL, NULL, 'Rahma Mohamed Noor', 'Female', '0613800046', 'rahma.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Dispensing', '2021-01-11', 'ACTIVE', '2026-01-17 23:31:16'),
(85, NULL, NULL, 'Hamza Hassan Farah', 'Male', '0613800047', 'hamza.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Drug Safety', '2019-06-30', 'ACTIVE', '2026-01-17 23:31:16'),
(86, NULL, NULL, 'Safiya Abdi Ali', 'Female', '0613800048', 'safiya.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Inventory Control', '2020-10-19', 'ACTIVE', '2026-01-17 23:31:16'),
(87, NULL, NULL, 'Yusuf Mohamed Jama', 'Male', '0613800049', 'yusuf.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Clinical Pharmacy', '2018-04-03', 'ACTIVE', '2026-01-17 23:31:16'),
(88, NULL, NULL, 'Ilham Ahmed Noor', 'Female', '0613800050', 'ilham.pharm@hospital.so', 'PHARMACIST', 'Pharmacy', 'Dispensing', '2022-02-06', 'ACTIVE', '2026-01-17 23:31:16'),
(89, NULL, NULL, 'Asha Hassan Ali', 'Female', '0613800071', 'asha.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Patient Registration', '2019-03-12', 'ACTIVE', '2026-01-17 23:31:16'),
(90, NULL, NULL, 'Hodan Mohamed Noor', 'Female', '0613800072', 'hodan.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Appointments', '2020-05-19', 'ACTIVE', '2026-01-17 23:31:16'),
(91, NULL, NULL, 'Abdi Yusuf Farah', 'Male', '0613800073', 'abdi.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Customer Service', '2018-09-01', 'ACTIVE', '2026-01-17 23:31:16'),
(92, NULL, NULL, 'Maryan Ahmed Jama', 'Female', '0613800074', 'maryan.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Patient Records', '2017-12-14', 'ACTIVE', '2026-01-17 23:31:16'),
(93, NULL, NULL, 'Mahad Ali Warsame', 'Male', '0613800075', 'mahad.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Billing Support', '2021-02-22', 'ACTIVE', '2026-01-17 23:31:16'),
(94, NULL, NULL, 'Ruqiya Mohamed Ali', 'Female', '0613800076', 'ruqiya.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Appointments', '2019-07-08', 'ACTIVE', '2026-01-17 23:31:16'),
(95, NULL, NULL, 'Hassan Abdi Noor', 'Male', '0613800077', 'hassan.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Patient Registration', '2020-10-30', 'ACTIVE', '2026-01-17 23:31:16'),
(96, NULL, NULL, 'Nimo Yusuf Jama', 'Female', '0613800078', 'nimo.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Customer Service', '2018-04-17', 'ACTIVE', '2026-01-17 23:31:16'),
(97, NULL, NULL, 'Ahmed Mohamed Farah', 'Male', '0613800079', 'ahmed.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Billing Support', '2021-06-05', 'ACTIVE', '2026-01-17 23:31:16'),
(98, NULL, NULL, 'Fadumo Hassan Ali', 'Female', '0613800080', 'fadumo.reception@hospital.so', 'RECEPTIONIST', 'Front Desk', 'Patient Records', '2017-08-29', 'ACTIVE', '2026-01-17 23:31:16'),
(99, NULL, NULL, 'Abdullahi Bashiir', 'Male', '617008899', NULL, 'DOCTOR', NULL, NULL, NULL, 'ACTIVE', '2026-01-19 09:42:56'),
(101, NULL, NULL, 'Hassan Jamac', 'Male', '0618995511', NULL, 'ADMINISTRATOR', NULL, NULL, NULL, 'ACTIVE', '2026-01-19 18:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(120) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_code`, `full_name`, `gender`, `date_of_birth`, `phone`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`) VALUES
(1, NULL, 'Asho Abdulkadir', 'Female', '1994-05-01', '+252617488500', 'deyniile', NULL, NULL, '2026-01-14 12:25:34'),
(2, NULL, 'Qays Abdi', 'Male', NULL, '+252610544757', 'yaaqshid', NULL, NULL, '2026-01-14 12:32:17'),
(3, NULL, 'Mohamed Hassan', 'Male', NULL, '0616627821', 'deyniile', NULL, NULL, '2026-01-14 12:48:50'),
(4, NULL, 'Ahmed Mohamed Ali', 'Male', '1995-03-12', '0612345001', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(5, NULL, 'Abdirahman Hassan Yusuf', 'Male', '1992-07-21', '0612345002', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(6, NULL, 'Mohamed Abdullahi Noor', 'Male', '1998-11-05', '0612345003', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(7, NULL, 'Hassan Ali Warsame', 'Male', '1990-01-18', '0612345004', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(8, NULL, 'Yusuf Ahmed Farah', 'Male', '2000-09-09', '0612345005', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(9, NULL, 'Abdullahi Omar Ahmed', 'Male', '1996-06-14', '0612345006', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(10, NULL, 'Ismail Mohamed Abdi', 'Male', '1993-12-25', '0612345007', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(11, NULL, 'Said Ahmed Jama', 'Male', '1989-05-30', '0612345008', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(12, NULL, 'Omar Hassan Sheikh', 'Male', '1997-08-19', '0612345009', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(13, NULL, 'Mahad Abdi Mohamed', 'Male', '2001-04-02', '0612345010', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(14, NULL, 'Amina Hassan Ali', 'Female', '1999-02-11', '0612345011', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(15, NULL, 'Fatima Mohamed Noor', 'Female', '1996-10-06', '0612345012', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(16, NULL, 'Khadra Abdi Ahmed', 'Female', '1994-01-29', '0612345013', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(17, NULL, 'Hodan Yusuf Farah', 'Female', '2002-07-17', '0612345014', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(18, NULL, 'Maryan Ali Warsame', 'Female', '1998-09-23', '0612345015', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(19, NULL, 'Zahra Mohamed Abdi', 'Female', '1995-12-10', '0612345016', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(20, NULL, 'Sahra Hassan Jama', 'Female', '1993-03-04', '0612345017', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(21, NULL, 'Fowsiya Ahmed Omar', 'Female', '2000-06-28', '0612345018', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(22, NULL, 'Nimo Abdullahi Yusuf', 'Female', '1997-11-15', '0612345019', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(23, NULL, 'Halima Mohamed Ali', 'Female', '1992-08-08', '0612345020', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(24, NULL, 'Abdirizak Hassan Ali', 'Male', '1991-05-14', '0612345021', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(25, NULL, 'Mustafa Mohamed Farah', 'Male', '1994-02-03', '0612345022', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(26, NULL, 'Salman Ahmed Noor', 'Male', '1999-07-19', '0612345023', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(27, NULL, 'Bilal Abdi Yusuf', 'Male', '2001-01-26', '0612345024', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(28, NULL, 'Ibrahim Hassan Warsame', 'Male', '1996-09-11', '0612345025', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(29, NULL, 'Nuur Mohamed Ali', 'Male', '1990-12-01', '0612345026', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(30, NULL, 'Sharif Ahmed Omar', 'Male', '1998-04-22', '0612345027', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(31, NULL, 'Abdinasir Abdi Jama', 'Male', '1993-06-09', '0612345028', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(32, NULL, 'Anas Mohamed Sheikh', 'Male', '2000-10-30', '0612345029', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(33, NULL, 'Hamza Ali Farah', 'Male', '1997-03-18', '0612345030', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(34, NULL, 'Rahma Hassan Ali', 'Female', '2001-05-07', '0612345031', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(35, NULL, 'Asma Mohamed Yusuf', 'Female', '1996-11-24', '0612345032', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(36, NULL, 'Ilham Abdi Noor', 'Female', '1993-02-15', '0612345033', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(37, NULL, 'Safiya Ahmed Warsame', 'Female', '1999-08-01', '0612345034', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(38, NULL, 'Sumaya Mohamed Ali', 'Female', '2002-04-19', '0612345035', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(39, NULL, 'Hibo Hassan Omar', 'Female', '1995-07-12', '0612345036', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(40, NULL, 'Muna Abdirahman Jama', 'Female', '1998-10-27', '0612345037', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(41, NULL, 'Layla Mohamed Farah', 'Female', '1994-01-09', '0612345038', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(42, NULL, 'Ruqiya Abdi Yusuf', 'Female', '1997-06-21', '0612345039', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(43, NULL, 'Amal Ahmed Ali', 'Female', '2000-12-14', '0612345040', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(44, NULL, 'Suleiman Hassan Ali', 'Male', '1988-09-05', '0612345041', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(45, NULL, 'Khalid Mohamed Noor', 'Male', '1992-04-11', '0612345042', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(46, NULL, 'Abdulqadir Abdi Farah', 'Male', '1996-01-20', '0612345043', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(47, NULL, 'Faarax Ahmed Warsame', 'Male', '1999-07-02', '0612345044', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(48, NULL, 'Jamal Mohamed Yusuf', 'Male', '2001-03-29', '0612345045', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(49, NULL, 'Ridwan Hassan Ali', 'Male', '1994-10-16', '0612345046', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(50, NULL, 'Zakaria Abdirahman Jama', 'Male', '1997-05-08', '0612345047', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(51, NULL, 'Mumin Mohamed Abdi', 'Male', '1990-12-27', '0612345048', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(52, NULL, 'Abdiwali Ahmed Farah', 'Male', '1995-08-13', '0612345049', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(53, NULL, 'Yahya Ali Warsame', 'Male', '2000-02-06', '0612345050', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(54, NULL, 'Nasteexo Hassan Ali', 'Female', '1998-06-03', '0612345051', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(55, NULL, 'Hawa Mohamed Noor', 'Female', '1993-09-17', '0612345052', 'Hargeisa', NULL, NULL, '2026-01-17 23:19:47'),
(56, NULL, 'Bilan Abdi Farah', 'Female', '1996-01-11', '0612345053', 'Bosaso', NULL, NULL, '2026-01-17 23:19:47'),
(57, NULL, 'Ifrah Ahmed Yusuf', 'Female', '2001-04-25', '0612345054', 'Kismayo', NULL, NULL, '2026-01-17 23:19:47'),
(58, NULL, 'Ubah Hassan Warsame', 'Female', '1995-07-30', '0612345055', 'Baidoa', NULL, NULL, '2026-01-17 23:19:47'),
(59, NULL, 'Fartun Mohamed Ali', 'Female', '1999-10-08', '0612345056', 'Beledweyne', NULL, NULL, '2026-01-17 23:19:47'),
(60, NULL, 'Shukri Abdirahman Jama', 'Female', '1994-12-19', '0612345057', 'Garowe', NULL, NULL, '2026-01-17 23:19:47'),
(61, NULL, 'Nasra Ahmed Farah', 'Female', '1997-03-02', '0612345058', 'Galkayo', NULL, NULL, '2026-01-17 23:19:47'),
(62, NULL, 'Ayaan Mohamed Yusuf', 'Female', '2000-05-14', '0612345059', 'Mogadishu', NULL, NULL, '2026-01-17 23:19:47'),
(63, NULL, 'Saida Hassan Ali', 'Female', '1992-08-26', '0612345060', 'Hodan', NULL, NULL, '2026-01-17 23:19:47'),
(64, NULL, 'Nabiila Abdi  Farah', 'Female', '2026-01-19', NULL, NULL, NULL, NULL, '2026-01-19 20:32:05');

-- --------------------------------------------------------

--
-- Table structure for table `pharmacy_items`
--

CREATE TABLE `pharmacy_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(160) NOT NULL,
  `company_name` varchar(160) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pharmacy_items`
--

INSERT INTO `pharmacy_items` (`id`, `item_name`, `company_name`, `unit_price`) VALUES
(1, 'Paracetamol 50mg (Tablet)', 'Hikma', 0.15),
(2, 'Ibuprofen 75mg (Tablet)', 'Cipla', 0.20),
(3, 'Diclofenac 100mg (Tablet)', 'Sandoz', 0.25),
(4, 'Aspirin 250mg (Tablet)', 'Teva', 0.30),
(5, 'Amoxicillin 100mg (Capsule)', 'Pfizer', 0.45),
(6, 'Amoxicillin/Clavulanate 1g (Tablet)', 'GSK', 0.46),
(7, 'Azithromycin 50mg (Tablet)', 'Sanofi', 0.45),
(8, 'Ciprofloxacin 75mg (Tablet)', 'Bayer', 0.15),
(9, 'Metronidazole 100mg (Tablet)', 'Abbott', 0.20),
(10, 'Doxycycline 200mg (Capsule)', 'Novartis', 0.35),
(11, 'Cephalexin 250mg (Capsule)', 'Roche', 0.40),
(12, 'Cefixime 1g (Tablet)', 'AstraZeneca', 0.41),
(13, 'Ceftriaxone 40mg/2ml (Injection)', 'MSD', 2.75),
(14, 'Gentamicin 80mg/2ml (Injection)', 'Sun Pharma', 2.80),
(15, 'Dexamethasone 250mg/vial (Injection)', 'Dr. Reddy\'s', 2.50),
(16, 'Hydrocortisone 500mg/vial (Injection)', 'Ajanta', 2.55),
(17, 'Omeprazole 100mg (Capsule)', 'Lupin', 0.35),
(18, 'Pantoprazole 1g (Tablet)', 'Zydus', 0.36),
(19, 'Famotidine 50mg (Tablet)', 'Aurobindo', 0.35),
(20, 'Loratadine 75mg (Tablet)', 'Alkem', 0.40),
(21, 'Cetirizine 100mg (Tablet)', 'Local Pharma Co.', 0.45),
(22, 'Chlorpheniramine 250mg (Tablet)', 'SomMed', 0.15),
(23, 'Salbutamol 100mcg (Inhaler)', 'EastAfrica Pharma', 3.55),
(24, 'Salbutamol 150ml (Syrup)', 'Mogadishu Medical', 1.30),
(25, 'Prednisolone 50mg (Tablet)', 'Hikma', 0.30),
(26, 'Amlodipine 75mg (Tablet)', 'Cipla', 0.35),
(27, 'Losartan 100mg (Tablet)', 'Sandoz', 0.40),
(28, 'Atenolol 250mg (Tablet)', 'Teva', 0.45),
(29, 'Metformin 500mg (Tablet)', 'Pfizer', 0.15),
(30, 'Glibenclamide 1g (Tablet)', 'GSK', 0.26),
(31, 'Insulin Regular 40mg/2ml (Injection)', 'Sanofi', 2.60),
(32, 'Insulin NPH 80mg/2ml (Injection)', 'Bayer', 2.65),
(33, 'ORS 20.5g (Sachet)', 'Abbott', 0.55),
(34, 'Zinc 250mg (Tablet)', 'Novartis', 0.40),
(35, 'Ferrous Sulfate 500mg (Tablet)', 'Roche', 0.45),
(36, 'Folic Acid 1g (Tablet)', 'AstraZeneca', 0.21),
(37, 'Vitamin C 50mg (Tablet)', 'MSD', 0.20),
(38, 'Vitamin B-Complex 75mg (Tablet)', 'Sun Pharma', 0.25),
(39, 'Calcium Carbonate 100mg (Tablet)', 'Dr. Reddy\'s', 0.30),
(40, 'Magnesium 250mg (Tablet)', 'Ajanta', 0.35),
(41, 'Fluconazole 100mg (Capsule)', 'Lupin', 0.50),
(42, 'Clotrimazole 15g (Cream)', 'Zydus', 1.30),
(43, 'Miconazole 20g (Cream)', 'Aurobindo', 1.00),
(44, 'Acyclovir 75mg (Tablet)', 'Alkem', 0.20),
(45, 'Permethrin 120ml (Lotion)', 'Local Pharma Co.', 1.10),
(46, 'Albendazole 250mg (Tablet)', 'SomMed', 0.30),
(47, 'Mebendazole 500mg (Tablet)', 'EastAfrica Pharma', 0.35),
(48, 'Loperamide 500mg (Capsule)', 'Mogadishu Medical', 0.50),
(49, 'Ondansetron 50mg (Tablet)', 'Hikma', 0.45),
(50, 'Domperidone 75mg (Tablet)', 'Cipla', 0.15),
(51, 'Hydroxyzine 100mg (Tablet)', 'Sandoz', 0.20),
(52, 'Tramadol 500mg (Capsule)', 'Teva', 0.35),
(53, 'Morphine 1g/vial (Injection)', 'Pfizer', 3.65),
(54, 'Lidocaine 2g/vial (Injection)', 'GSK', 4.70),
(55, 'Lidocaine 10g (Gel)', 'Sanofi', 1.25),
(56, 'Povidone-Iodine 200ml (Solution)', 'Bayer', 1.40),
(57, 'Chlorhexidine 500ml (Solution)', 'Abbott', 1.54),
(58, 'Normal Saline 250ml (IV Fluid)', 'Novartis', 1.85),
(59, 'Ringer Lactate 500ml (IV Fluid)', 'Roche', 2.62),
(60, 'Dextrose 1000ml (IV Fluid)', 'AstraZeneca', 3.21),
(61, 'Cough Syrup 60ml (Syrup)', 'MSD', 1.40),
(62, 'Antacid 100mg (Suspension)', 'Sun Pharma', 0.55),
(63, 'Multivitamin 120ml (Syrup)', 'Dr. Reddy\'s', 1.50),
(64, 'Paracetamol 250mg (Tablet)', 'Ajanta', 0.15),
(65, 'Ibuprofen 500mg (Tablet)', 'Lupin', 0.20),
(66, 'Diclofenac 1g (Tablet)', 'Zydus', 0.31),
(67, 'Aspirin 50mg (Tablet)', 'Aurobindo', 0.30),
(68, 'Amoxicillin 500mg (Capsule)', 'Alkem', 0.45),
(69, 'Amoxicillin/Clavulanate 100mg (Tablet)', 'Local Pharma Co.', 0.40),
(70, 'Azithromycin 250mg (Tablet)', 'SomMed', 0.45),
(71, 'Ciprofloxacin 500mg (Tablet)', 'EastAfrica Pharma', 0.15),
(72, 'Metronidazole 1g (Tablet)', 'Mogadishu Medical', 0.26),
(73, 'Doxycycline 100mg (Capsule)', 'Hikma', 0.35),
(74, 'Cephalexin 200mg (Capsule)', 'Cipla', 0.40),
(75, 'Cefixime 100mg (Tablet)', 'Sandoz', 0.35),
(76, 'Ceftriaxone 500mg/vial (Injection)', 'Teva', 2.75),
(77, 'Gentamicin 1g/vial (Injection)', 'Pfizer', 3.80),
(78, 'Dexamethasone 2g/vial (Injection)', 'GSK', 4.50),
(79, 'Hydrocortisone 40mg/2ml (Injection)', 'Sanofi', 2.55),
(80, 'Omeprazole 500mg (Capsule)', 'Bayer', 0.35),
(81, 'Pantoprazole 100mg (Tablet)', 'Abbott', 0.30),
(82, 'Famotidine 250mg (Tablet)', 'Novartis', 0.35),
(83, 'Loratadine 500mg (Tablet)', 'Roche', 0.40),
(84, 'Cetirizine 1g (Tablet)', 'AstraZeneca', 0.51),
(85, 'Chlorpheniramine 50mg (Tablet)', 'MSD', 0.15),
(86, 'Salbutamol 200mcg (Inhaler)', 'Sun Pharma', 3.55),
(87, 'Salbutamol 120ml (Syrup)', 'Dr. Reddy\'s', 1.30),
(88, 'Prednisolone 250mg (Tablet)', 'Ajanta', 0.30),
(89, 'Amlodipine 500mg (Tablet)', 'Lupin', 0.35),
(90, 'Losartan 1g (Tablet)', 'Zydus', 0.46),
(91, 'Atenolol 50mg (Tablet)', 'Aurobindo', 0.45),
(92, 'Metformin 75mg (Tablet)', 'Alkem', 0.15),
(93, 'Glibenclamide 100mg (Tablet)', 'Local Pharma Co.', 0.20),
(94, 'Insulin Regular 500mg/vial (Injection)', 'SomMed', 2.60),
(95, 'Insulin NPH 1g/vial (Injection)', 'EastAfrica Pharma', 3.65),
(96, 'ORS 10g (Sachet)', 'Mogadishu Medical', 0.55),
(97, 'Zinc 50mg (Tablet)', 'Hikma', 0.40),
(98, 'Ferrous Sulfate 75mg (Tablet)', 'Cipla', 0.45),
(99, 'Folic Acid 100mg (Tablet)', 'Sandoz', 0.15),
(100, 'Vitamin C 250mg (Tablet)', 'Teva', 0.20),
(101, 'Vitamin B-Complex 500mg (Tablet)', 'Pfizer', 0.25),
(102, 'Calcium Carbonate 1g (Tablet)', 'GSK', 0.36),
(103, 'Magnesium 50mg (Tablet)', 'Sanofi', 0.35),
(104, 'Fluconazole 500mg (Capsule)', 'Bayer', 0.50),
(105, 'Clotrimazole 10g (Cream)', 'Abbott', 1.30),
(106, 'Miconazole 15g (Cream)', 'Novartis', 1.00),
(107, 'Acyclovir 500mg (Tablet)', 'Roche', 0.20),
(108, 'Permethrin 120ml (Lotion)', 'AstraZeneca', 1.10),
(109, 'Albendazole 50mg (Tablet)', 'MSD', 0.30),
(110, 'Mebendazole 75mg (Tablet)', 'Sun Pharma', 0.35),
(111, 'Loperamide 250mg (Capsule)', 'Dr. Reddy\'s', 0.50),
(112, 'Ondansetron 250mg (Tablet)', 'Ajanta', 0.45),
(113, 'Domperidone 500mg (Tablet)', 'Lupin', 0.15),
(114, 'Hydroxyzine 1g (Tablet)', 'Zydus', 0.26),
(115, 'Tramadol 250mg (Capsule)', 'Aurobindo', 0.35),
(116, 'Morphine 80mg/2ml (Injection)', 'Alkem', 2.65),
(117, 'Lidocaine 250mg/vial (Injection)', 'Local Pharma Co.', 2.70),
(118, 'Lidocaine 10g (Gel)', 'SomMed', 1.25),
(119, 'Povidone-Iodine 200ml (Solution)', 'EastAfrica Pharma', 1.40),
(120, 'Chlorhexidine 500ml (Solution)', 'Mogadishu Medical', 1.54),
(121, 'Normal Saline 250ml (IV Fluid)', 'Hikma', 1.85),
(122, 'Ringer Lactate 500ml (IV Fluid)', 'Cipla', 2.62),
(123, 'Dextrose 1000ml (IV Fluid)', 'Sandoz', 3.21),
(124, 'Cough Syrup 150ml (Syrup)', 'Teva', 1.40),
(125, 'Antacid 100mg (Suspension)', 'Pfizer', 0.55),
(126, 'Multivitamin 100ml (Syrup)', 'GSK', 1.50),
(127, 'Paracetamol 50mg (Tablet)', 'Sanofi', 0.15),
(128, 'Ibuprofen 75mg (Tablet)', 'Bayer', 0.20),
(129, 'Diclofenac 100mg (Tablet)', 'Abbott', 0.25),
(130, 'Aspirin 250mg (Tablet)', 'Novartis', 0.30),
(131, 'Amoxicillin 250mg (Capsule)', 'Roche', 0.45),
(132, 'Amoxicillin/Clavulanate 1g (Tablet)', 'AstraZeneca', 0.46),
(133, 'Azithromycin 50mg (Tablet)', 'MSD', 0.45),
(134, 'Ciprofloxacin 75mg (Tablet)', 'Sun Pharma', 0.15),
(135, 'Metronidazole 100mg (Tablet)', 'Dr. Reddy\'s', 0.20),
(136, 'Doxycycline 500mg (Capsule)', 'Ajanta', 0.35),
(137, 'Cephalexin 100mg (Capsule)', 'Lupin', 0.40),
(138, 'Cefixime 1g (Tablet)', 'Zydus', 0.41),
(139, 'Ceftriaxone 40mg/2ml (Injection)', 'Aurobindo', 2.75),
(140, 'Gentamicin 80mg/2ml (Injection)', 'Alkem', 2.80),
(141, 'Dexamethasone 250mg/vial (Injection)', 'Local Pharma Co.', 2.50),
(142, 'Hydrocortisone 500mg/vial (Injection)', 'SomMed', 2.55),
(143, 'Omeprazole 250mg (Capsule)', 'EastAfrica Pharma', 0.35),
(144, 'Pantoprazole 1g (Tablet)', 'Mogadishu Medical', 0.36),
(145, 'Famotidine 50mg (Tablet)', 'Hikma', 0.35),
(146, 'Loratadine 75mg (Tablet)', 'Cipla', 0.40),
(147, 'Cetirizine 100mg (Tablet)', 'Sandoz', 0.45),
(148, 'Chlorpheniramine 250mg (Tablet)', 'Teva', 0.15),
(149, 'Salbutamol 100mcg (Inhaler)', 'Pfizer', 3.55),
(150, 'Salbutamol 100ml (Syrup)', 'GSK', 1.30),
(151, 'Prednisolone 50mg (Tablet)', 'Sanofi', 0.30),
(152, 'Amlodipine 75mg (Tablet)', 'Bayer', 0.35),
(153, 'Losartan 100mg (Tablet)', 'Abbott', 0.40),
(154, 'Atenolol 250mg (Tablet)', 'Novartis', 0.45),
(155, 'Metformin 500mg (Tablet)', 'Roche', 0.15),
(156, 'Glibenclamide 1g (Tablet)', 'AstraZeneca', 0.26),
(157, 'Insulin Regular 40mg/2ml (Injection)', 'MSD', 2.60),
(158, 'Insulin NPH 80mg/2ml (Injection)', 'Sun Pharma', 2.65),
(159, 'ORS 5g (Sachet)', 'Dr. Reddy\'s', 0.55),
(160, 'Zinc 250mg (Tablet)', 'Ajanta', 0.40),
(161, 'Ferrous Sulfate 500mg (Tablet)', 'Lupin', 0.45),
(162, 'Folic Acid 1g (Tablet)', 'Zydus', 0.21),
(163, 'Vitamin C 50mg (Tablet)', 'Aurobindo', 0.20),
(164, 'Vitamin B-Complex 75mg (Tablet)', 'Alkem', 0.25),
(165, 'Calcium Carbonate 100mg (Tablet)', 'Local Pharma Co.', 0.30),
(166, 'Magnesium 250mg (Tablet)', 'SomMed', 0.35),
(167, 'Fluconazole 250mg (Capsule)', 'EastAfrica Pharma', 0.50),
(168, 'Clotrimazole 30g (Cream)', 'Mogadishu Medical', 1.30),
(169, 'Miconazole 10g (Cream)', 'Hikma', 1.00),
(170, 'Acyclovir 75mg (Tablet)', 'Cipla', 0.20),
(171, 'Permethrin 120ml (Lotion)', 'Sandoz', 1.10),
(172, 'Albendazole 250mg (Tablet)', 'Teva', 0.30),
(173, 'Mebendazole 500mg (Tablet)', 'Pfizer', 0.35),
(174, 'Loperamide 200mg (Capsule)', 'GSK', 0.50),
(175, 'Ondansetron 50mg (Tablet)', 'Sanofi', 0.45),
(176, 'Domperidone 75mg (Tablet)', 'Bayer', 0.15),
(177, 'Hydroxyzine 100mg (Tablet)', 'Abbott', 0.20),
(178, 'Tramadol 200mg (Capsule)', 'Novartis', 0.35),
(179, 'Morphine 1g/vial (Injection)', 'Roche', 3.65),
(180, 'Lidocaine 2g/vial (Injection)', 'AstraZeneca', 4.70),
(181, 'Lidocaine 10g (Gel)', 'MSD', 1.25),
(182, 'Povidone-Iodine 200ml (Solution)', 'Sun Pharma', 1.40),
(183, 'Chlorhexidine 500ml (Solution)', 'Dr. Reddy\'s', 1.54),
(184, 'Normal Saline 250ml (IV Fluid)', 'Ajanta', 1.85),
(185, 'Ringer Lactate 500ml (IV Fluid)', 'Lupin', 2.62),
(186, 'Dextrose 1000ml (IV Fluid)', 'Zydus', 3.21),
(187, 'Cough Syrup 120ml (Syrup)', 'Aurobindo', 1.40),
(188, 'Antacid 100mg (Suspension)', 'Alkem', 0.55),
(189, 'Multivitamin 60ml (Syrup)', 'Local Pharma Co.', 1.50),
(190, 'Paracetamol 250mg (Tablet)', 'SomMed', 0.15),
(191, 'Ibuprofen 500mg (Tablet)', 'EastAfrica Pharma', 0.20),
(192, 'Diclofenac 1g (Tablet)', 'Mogadishu Medical', 0.31),
(193, 'Aspirin 50mg (Tablet)', 'Hikma', 0.30),
(194, 'Amoxicillin 200mg (Capsule)', 'Cipla', 0.45),
(195, 'Amoxicillin/Clavulanate 100mg (Tablet)', 'Sandoz', 0.40),
(196, 'Azithromycin 250mg (Tablet)', 'Teva', 0.45),
(197, 'Ciprofloxacin 500mg (Tablet)', 'Pfizer', 0.15),
(198, 'Metronidazole 1g (Tablet)', 'GSK', 0.26),
(199, 'Doxycycline 250mg (Capsule)', 'Sanofi', 0.35),
(200, 'Cephalexin 500mg (Capsule)', 'Bayer', 0.40),
(201, 'IV Drip Set Adult (1 set)', 'GSK', 1.20),
(202, 'Cannula 20G (1 pc)', 'Sanofi', 0.50),
(203, 'Syringe 10ml (1 pc)', 'Bayer', 0.50),
(204, 'Needle 23G (1 pc)', 'Abbott', 0.80),
(205, 'Gauze Roll 7.5cm x 4m (1 roll)', 'Novartis', 0.90),
(206, 'Bandage 7.5cm (1 roll)', 'Roche', 0.50),
(207, 'Plaster Tape 1 inch (1 roll)', 'AstraZeneca', 0.60),
(208, 'Cotton Wool 500g (1 pack)', 'MSD', 0.70),
(209, 'Gloves L (1 pair)', 'Sun Pharma', 0.80),
(210, 'Face Mask 3-ply (1 box)', 'Dr. Reddy\'s', 3.90),
(211, 'Alcohol Swab 70% (1 pc)', 'Ajanta', 0.50),
(212, 'Hand Sanitizer 500ml (1 bottle)', 'Lupin', 1.60),
(213, 'Thermometer Digital (1 pc)', 'Zydus', 3.20),
(214, 'Blood Pressure Cuff Large (1 pc)', 'Aurobindo', 9.30),
(215, 'Glucometer Strip 50 strips (1 box)', 'Alkem', 6.40),
(216, 'Nebulizer Mask Child (1 pc)', 'Local Pharma Co.', 0.50),
(217, 'Urine Bag 2000ml (1 pc)', 'SomMed', 1.10),
(218, 'Catheter 18Fr (1 pc)', 'EastAfrica Pharma', 0.70),
(219, 'Surgical Blade No.10 (1 pc)', 'Mogadishu Medical', 0.80),
(220, 'Suture 3-0 (1 pc)', 'Hikma', 0.90),
(221, 'IV Drip Set Adult (1 set)', 'Cipla', 1.20),
(222, 'Cannula 20G (1 pc)', 'Sandoz', 0.50),
(223, 'Syringe 10ml (1 pc)', 'Teva', 0.50),
(224, 'Needle 23G (1 pc)', 'Pfizer', 0.80),
(225, 'Gauze Roll 5cm x 4m (1 roll)', 'GSK', 0.90),
(226, 'Bandage 7.5cm (1 roll)', 'Sanofi', 0.50),
(227, 'Plaster Tape 3 inch (1 roll)', 'Bayer', 0.60),
(228, 'Cotton Wool 500g (1 pack)', 'Abbott', 0.70),
(229, 'Gloves M (1 pair)', 'Novartis', 0.80),
(230, 'Face Mask 3-ply (1 box)', 'Roche', 3.90),
(231, 'Alcohol Swab 70% (1 pc)', 'AstraZeneca', 0.50),
(232, 'Hand Sanitizer 250ml (1 bottle)', 'MSD', 1.60),
(233, 'Thermometer Digital (1 pc)', 'Sun Pharma', 3.20),
(234, 'Blood Pressure Cuff Large (1 pc)', 'Dr. Reddy\'s', 9.30),
(235, 'Glucometer Strip 50 strips (1 box)', 'Ajanta', 6.40),
(236, 'Nebulizer Mask Child (1 pc)', 'Lupin', 0.50),
(237, 'Urine Bag 2000ml (1 pc)', 'Zydus', 1.10),
(238, 'Catheter 16Fr (1 pc)', 'Aurobindo', 0.70),
(239, 'Surgical Blade No.15 (1 pc)', 'Alkem', 0.80),
(240, 'Suture 2-0 (1 pc)', 'Local Pharma Co.', 0.90),
(241, 'IV Drip Set Adult (1 set)', 'SomMed', 1.20),
(242, 'Cannula 20G (1 pc)', 'EastAfrica Pharma', 0.50),
(243, 'Syringe 10ml (1 pc)', 'Mogadishu Medical', 0.50),
(244, 'Needle 23G (1 pc)', 'Hikma', 0.80),
(245, 'Gauze Roll 10cm x 4m (1 roll)', 'Cipla', 0.90),
(246, 'Bandage 7.5cm (1 roll)', 'Sandoz', 0.50),
(247, 'Plaster Tape 2 inch (1 roll)', 'Teva', 0.60),
(248, 'Cotton Wool 500g (1 pack)', 'Pfizer', 0.70),
(249, 'Gloves S (1 pair)', 'GSK', 0.80),
(250, 'Face Mask 3-ply (1 box)', 'Sanofi', 3.90),
(251, 'Alcohol Swab 70% (1 pc)', 'Bayer', 0.50),
(252, 'Hand Sanitizer 100ml (1 bottle)', 'Abbott', 1.60),
(253, 'Thermometer Digital (1 pc)', 'Novartis', 3.20),
(254, 'Blood Pressure Cuff Large (1 pc)', 'Roche', 9.30),
(255, 'Glucometer Strip 50 strips (1 box)', 'AstraZeneca', 6.40),
(256, 'Nebulizer Mask Child (1 pc)', 'MSD', 0.50),
(257, 'Urine Bag 2000ml (1 pc)', 'Sun Pharma', 1.10),
(258, 'Catheter 14Fr (1 pc)', 'Dr. Reddy\'s', 0.70),
(259, 'Surgical Blade No.11 (1 pc)', 'Ajanta', 0.80),
(260, 'Suture 4-0 (1 pc)', 'Lupin', 0.90),
(261, 'IV Drip Set Adult (1 set)', 'Zydus', 1.20),
(262, 'Cannula 20G (1 pc)', 'Aurobindo', 0.50),
(263, 'Syringe 10ml (1 pc)', 'Alkem', 0.50),
(264, 'Needle 23G (1 pc)', 'Local Pharma Co.', 0.80),
(265, 'Gauze Roll 7.5cm x 4m (1 roll)', 'SomMed', 0.90),
(266, 'Bandage 7.5cm (1 roll)', 'EastAfrica Pharma', 0.50),
(267, 'Plaster Tape 1 inch (1 roll)', 'Mogadishu Medical', 0.60),
(268, 'Cotton Wool 500g (1 pack)', 'Hikma', 0.70),
(269, 'Gloves L (1 pair)', 'Cipla', 0.80),
(270, 'Face Mask 3-ply (1 box)', 'Sandoz', 3.90),
(271, 'Alcohol Swab 70% (1 pc)', 'Teva', 0.50),
(272, 'Hand Sanitizer 500ml (1 bottle)', 'Pfizer', 1.60),
(273, 'Thermometer Digital (1 pc)', 'GSK', 3.20),
(274, 'Blood Pressure Cuff Large (1 pc)', 'Sanofi', 9.30),
(275, 'Glucometer Strip 50 strips (1 box)', 'Bayer', 6.40),
(276, 'Nebulizer Mask Child (1 pc)', 'Abbott', 0.50),
(277, 'Urine Bag 2000ml (1 pc)', 'Novartis', 1.10),
(278, 'Catheter 18Fr (1 pc)', 'Roche', 0.70),
(279, 'Surgical Blade No.10 (1 pc)', 'AstraZeneca', 0.80),
(280, 'Suture 3-0 (1 pc)', 'MSD', 0.90),
(281, 'IV Drip Set Adult (1 set)', 'Sun Pharma', 1.20),
(282, 'Cannula 20G (1 pc)', 'Dr. Reddy\'s', 0.50),
(283, 'Syringe 10ml (1 pc)', 'Ajanta', 0.50),
(284, 'Needle 23G (1 pc)', 'Lupin', 0.80),
(285, 'Gauze Roll 5cm x 4m (1 roll)', 'Zydus', 0.90),
(286, 'Bandage 7.5cm (1 roll)', 'Aurobindo', 0.50),
(287, 'Plaster Tape 3 inch (1 roll)', 'Alkem', 0.60),
(288, 'Cotton Wool 500g (1 pack)', 'Local Pharma Co.', 0.70),
(289, 'Gloves M (1 pair)', 'SomMed', 0.80),
(290, 'Face Mask 3-ply (1 box)', 'EastAfrica Pharma', 3.90),
(291, 'Alcohol Swab 70% (1 pc)', 'Mogadishu Medical', 0.50),
(292, 'Hand Sanitizer 250ml (1 bottle)', 'Hikma', 1.60),
(293, 'Thermometer Digital (1 pc)', 'Cipla', 3.20),
(294, 'Blood Pressure Cuff Large (1 pc)', 'Sandoz', 9.30),
(295, 'Glucometer Strip 50 strips (1 box)', 'Teva', 6.40),
(296, 'Nebulizer Mask Child (1 pc)', 'Pfizer', 0.50),
(297, 'Urine Bag 2000ml (1 pc)', 'GSK', 1.10),
(298, 'Catheter 16Fr (1 pc)', 'Sanofi', 0.70),
(299, 'Surgical Blade No.15 (1 pc)', 'Bayer', 0.80),
(300, 'Suture 2-0 (1 pc)', 'Abbott', 0.90);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `prescribed_by_employee_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `patient_id`, `prescribed_by_employee_id`, `notes`, `created_at`) VALUES
(1, 2, 3, '3 Times', '2026-01-17 20:11:11'),
(2, 1, 3, NULL, '2026-01-17 20:18:03'),
(3, 3, 3, NULL, '2026-01-17 20:31:03'),
(4, 2, 3, NULL, '2026-01-17 23:02:37');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `pharmacy_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price_at_time` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_items`
--

INSERT INTO `prescription_items` (`id`, `prescription_id`, `pharmacy_item_id`, `quantity`, `unit_price_at_time`) VALUES
(3, 1, 5, 2, NULL),
(4, 1, 60, 10, NULL),
(7, 3, 190, 5, NULL),
(8, 4, 44, 5, NULL),
(9, 4, 190, 10, NULL),
(12, 2, 82, 4, NULL),
(13, 2, 255, 15, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `surgery_items`
--

CREATE TABLE `surgery_items` (
  `id` int(11) NOT NULL,
  `surgery_name` varchar(200) NOT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `surgery_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `surgery_items`
--

INSERT INTO `surgery_items` (`id`, `surgery_name`, `cost`, `surgery_date`) VALUES
(1, 'General Consultation (Procedure Fee)', 10.00, NULL),
(2, 'Emergency Consultation', 25.00, NULL),
(3, 'Minor Procedure Room Fee', 15.00, NULL),
(4, 'Wound Dressing - Small', 8.00, NULL),
(5, 'Wound Dressing - Medium', 12.00, NULL),
(6, 'Wound Dressing - Large', 18.00, NULL),
(7, 'Suturing - Simple (1-3 stitches)', 20.00, NULL),
(8, 'Suturing - Moderate (4-8 stitches)', 35.00, NULL),
(9, 'Suturing - Complex (9+ stitches)', 55.00, NULL),
(10, 'Abscess Incision & Drainage (I&D)', 45.00, NULL),
(11, 'Foreign Body Removal - Superficial', 30.00, NULL),
(12, 'Nail Removal (Partial/Complete)', 40.00, NULL),
(13, 'Burn Dressing - Minor', 20.00, NULL),
(14, 'Burn Dressing - Moderate', 45.00, NULL),
(15, 'Burn Debridement - Minor', 60.00, NULL),
(16, 'IV Cannulation / Line Insertion', 8.00, NULL),
(17, 'IV Drip Setup Fee', 12.00, NULL),
(18, 'IM Injection Procedure Fee', 5.00, NULL),
(19, 'SC Injection Procedure Fee', 5.00, NULL),
(20, 'Nebulization Procedure', 8.00, NULL),
(21, 'Oxygen Therapy - 1 Hour', 10.00, NULL),
(22, 'Urinary Catheter Insertion', 20.00, NULL),
(23, 'Urinary Catheter Removal', 10.00, NULL),
(24, 'NG Tube Insertion', 25.00, NULL),
(25, 'NG Tube Removal', 12.00, NULL),
(26, 'Ear Wax Removal (Syringing)', 18.00, NULL),
(27, 'Epistaxis Control (Nasal Packing)', 35.00, NULL),
(28, 'ECG Procedure Fee', 12.00, NULL),
(29, 'Blood Transfusion Setup Fee', 30.00, NULL),
(30, 'Ambulance Service (Local)', 40.00, NULL),
(31, 'Cesarean Section (C-Section)', 450.00, NULL),
(32, 'Normal Delivery (Procedure Fee)', 150.00, NULL),
(33, 'Assisted Vaginal Delivery (Vacuum/Forceps)', 220.00, NULL),
(34, 'Dilation and Curettage (D&C)', 250.00, NULL),
(35, 'Manual Vacuum Aspiration (MVA)', 200.00, NULL),
(36, 'Ectopic Pregnancy Surgery', 650.00, NULL),
(37, 'Hysterectomy (Abdominal)', 900.00, NULL),
(38, 'Hysterectomy (Vaginal)', 850.00, NULL),
(39, 'Myomectomy', 750.00, NULL),
(40, 'Ovarian Cystectomy', 600.00, NULL),
(41, 'Tubal Ligation', 400.00, NULL),
(42, 'Cervical Biopsy Procedure', 120.00, NULL),
(43, 'Pap Smear Procedure Fee', 25.00, NULL),
(44, 'IUD Insertion Procedure', 50.00, NULL),
(45, 'IUD Removal Procedure', 30.00, NULL),
(46, 'Appendectomy (Open)', 700.00, NULL),
(47, 'Appendectomy (Laparoscopic)', 950.00, NULL),
(48, 'Hernia Repair (Inguinal) - Open', 650.00, NULL),
(49, 'Hernia Repair (Inguinal) - Laparoscopic', 950.00, NULL),
(50, 'Umbilical Hernia Repair', 600.00, NULL),
(51, 'Cholecystectomy (Gallbladder Removal) - Open', 900.00, NULL),
(52, 'Cholecystectomy (Laparoscopic)', 1100.00, NULL),
(53, 'Hemorrhoidectomy', 450.00, NULL),
(54, 'Fistula Surgery (Anal)', 650.00, NULL),
(55, 'Pilonidal Sinus Surgery', 500.00, NULL),
(56, 'Laparotomy (Exploratory)', 1200.00, NULL),
(57, 'Bowel Resection (Small/Large)', 1800.00, NULL),
(58, 'Colostomy (Creation/Closure)', 1500.00, NULL),
(59, 'Endoscopy (Upper GI) Procedure Fee', 120.00, NULL),
(60, 'Colonoscopy Procedure Fee', 160.00, NULL),
(61, 'Cataract Surgery (One Eye)', 350.00, NULL),
(62, 'Pterygium Excision', 180.00, NULL),
(63, 'Glaucoma Procedure (Minor)', 300.00, NULL),
(64, 'Eye Foreign Body Removal', 35.00, NULL),
(65, 'Minor Eye Lid Repair', 200.00, NULL),
(66, 'Tonsillectomy', 450.00, NULL),
(67, 'Adenoidectomy', 400.00, NULL),
(68, 'Nasal Septoplasty', 600.00, NULL),
(69, 'Sinus Surgery (FESS)', 900.00, NULL),
(70, 'Myringotomy / Ear Tube Insertion', 350.00, NULL),
(71, 'Tooth Extraction - Simple', 25.00, NULL),
(72, 'Tooth Extraction - Surgical', 80.00, NULL),
(73, 'Dental Scaling & Polishing', 40.00, NULL),
(74, 'Root Canal Treatment (Single Tooth)', 120.00, NULL),
(75, 'Dental Filling (Composite)', 30.00, NULL),
(76, 'Dental Abscess Drainage', 50.00, NULL),
(77, 'Fracture Reduction (Closed)', 120.00, NULL),
(78, 'POP Cast Application (Small)', 35.00, NULL),
(79, 'POP Cast Application (Large)', 70.00, NULL),
(80, 'Splint Application', 30.00, NULL),
(81, 'Dislocation Reduction (Shoulder)', 150.00, NULL),
(82, 'Dislocation Reduction (Finger)', 60.00, NULL),
(83, 'Arthroscopy (Diagnostic)', 900.00, NULL),
(84, 'ACL Reconstruction', 2200.00, NULL),
(85, 'ORIF (Fracture Fixation) - Small Bone', 1200.00, NULL),
(86, 'ORIF (Fracture Fixation) - Large Bone', 2200.00, NULL),
(87, 'Circumcision (Child)', 120.00, NULL),
(88, 'Circumcision (Adult)', 180.00, NULL),
(89, 'Hydrocelectomy', 700.00, NULL),
(90, 'Varicocelectomy', 850.00, NULL),
(91, 'Prostate Procedure (TURP)', 2200.00, NULL),
(92, 'Cystoscopy Procedure Fee', 180.00, NULL),
(93, 'Kidney Stone Removal (Open)', 1800.00, NULL),
(94, 'Ureteroscopy (Stone)', 1500.00, NULL),
(95, 'Skin Biopsy', 60.00, NULL),
(96, 'Mole/Wart Removal (Minor)', 40.00, NULL),
(97, 'Lipoma Excision - Small', 120.00, NULL),
(98, 'Lipoma Excision - Large', 250.00, NULL),
(99, 'Keloid Excision', 180.00, NULL),
(100, 'Debridement (Moderate)', 250.00, NULL),
(101, 'Debridement (Major)', 600.00, NULL),
(102, 'Anesthesia Fee - Local', 25.00, NULL),
(103, 'Anesthesia Fee - Spinal', 120.00, NULL),
(104, 'Anesthesia Fee - General', 250.00, NULL),
(105, 'Operating Theatre Fee (Standard)', 200.00, NULL),
(106, 'Operating Theatre Fee (Major)', 450.00, NULL),
(107, 'Sterilization / Instrument Fee', 30.00, NULL),
(108, 'Surgical Dressing Pack Fee', 15.00, NULL),
(109, 'Post-Op Observation (2 Hours)', 20.00, NULL),
(110, 'Post-Op Observation (6 Hours)', 45.00, NULL),
(111, 'ICU Procedure Surcharge (Per Day)', 120.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('ADMIN','RECEPTIONIST','STAFF') NOT NULL DEFAULT 'STAFF',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Mohamed Mahad Abdi', 'maher.mahad@gmail.com', '$2y$10$2jfjCLitqIkYa7A9f7vqyucR9X0fKGwTZZ0/ymThAPNkQf.8cuDwO', 'ADMIN', 1, '2026-01-12 16:43:45'),
(2, 'Bashiir Muumin', 'bashiir@gmail.com', '$2y$10$mouGA/Q9/gwj/iOjsvXMRu1EdP2IUENrksHFrd.Ih8LjN3hjSJA9u', 'RECEPTIONIST', 1, '2026-01-14 11:45:21'),
(3, 'Mo Haji Abdi', 'mo.haji.abdi1@gmail.com', '$2y$10$wLtwv1eyky84EdnpLqYaX.r5zS3KWaPPstQoSzzfeN4XNeqcscGzW', 'STAFF', 1, '2026-01-14 13:10:54'),
(4, 'Adan Mahad', 'adanjama@gmail.com', '$2y$10$VB.IkNvpVNMDH8h69K6afOq.QtrEpL02KRS4uqXqdpZ0O.o4jMIua', 'STAFF', 1, '2026-01-18 02:34:14'),
(5, 'Asho Abdulkadir', 'asho@gmail.com', '$2y$10$x5HHQPAyhdt53yu0/TDYjej9axd6NpXobq8.6T9mgDEsTg2d85p1a', 'STAFF', 1, '2026-01-18 02:42:08'),
(6, 'Komaando', 'komaando@gmail.com', '$2y$10$MwlwPkOvDwHpDbo32F9JbuZRp5h5xCEkmX/GOg0wvrhbXvER4qgeW', 'STAFF', 1, '2026-01-19 09:39:13'),
(7, 'Maher yare', 'maher@gmail.com', '$2y$10$fFUlktyq5/09NMTJsXtpqeyFi8x/1Xv.0BG12WhEyvIrom34/ise2', 'STAFF', 1, '2026-01-19 18:20:28'),
(8, 'Mahad Haji', 'mahad@gmail.com', '$2y$10$/GFq3gBHujLRpO9PKGiwBe30lXdfFqmkZPg8fn.3FzFUXJVSj0BJO', 'STAFF', 1, '2026-01-19 18:20:43'),
(9, 'Mahad Haji Abdi', 'mahadd@gmail.com', '$2y$10$iIGNvhIRKg4b1eYPFwyFlOQ.EZBoBP7zvux.iuV03ofYVfFtAw8ku', 'STAFF', 1, '2026-01-19 18:21:36'),
(10, 'Hello', 'hello@gmail.com', '$2y$10$z1fO0vtAWRgcrDeJe/YemuKIBpnT5EfvlPhT4S8osccrbOBzuaFjO', 'ADMIN', 1, '2026-01-19 18:40:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_datetime` (`appointment_datetime`),
  ADD KEY `idx_appt_patient` (`patient_id`),
  ADD KEY `idx_appt_employee` (`employee_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_bill_prescription` (`prescription_id`),
  ADD KEY `idx_bills_status` (`status`),
  ADD KEY `idx_bills_appointment` (`appointment_id`),
  ADD KEY `idx_bills_paid_at` (`paid_at`),
  ADD KEY `idx_bills_receipt_no` (`receipt_no`),
  ADD KEY `idx_bills_patient` (`patient_id`),
  ADD KEY `idx_bills_employee` (`employee_id`),
  ADD KEY `idx_bills_prescription` (`prescription_id`),
  ADD KEY `idx_bills_type` (`bill_type`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_code` (`emp_code`),
  ADD KEY `fk_employees_user` (`user_id`),
  ADD KEY `idx_employees_name` (`full_name`),
  ADD KEY `idx_employees_full_name` (`full_name`),
  ADD KEY `idx_employees_phone` (`phone`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`),
  ADD KEY `idx_patients_name` (`full_name`),
  ADD KEY `idx_patients_phone` (`phone`);

--
-- Indexes for table `pharmacy_items`
--
ALTER TABLE `pharmacy_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_name` (`item_name`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_presc_patient` (`patient_id`),
  ADD KEY `idx_presc_employee` (`prescribed_by_employee_id`),
  ADD KEY `idx_presc_created_at` (`created_at`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pi_prescription` (`prescription_id`),
  ADD KEY `idx_pi_item` (`pharmacy_item_id`);

--
-- Indexes for table `surgery_items`
--
ALTER TABLE `surgery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_surgery_name` (`surgery_name`);

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
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `pharmacy_items`
--
ALTER TABLE `pharmacy_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=301;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `surgery_items`
--
ALTER TABLE `surgery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `fk_bills_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bills_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bills_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bills_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_presc_employee` FOREIGN KEY (`prescribed_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_pi_item` FOREIGN KEY (`pharmacy_item_id`) REFERENCES `pharmacy_items` (`id`),
  ADD CONSTRAINT `fk_pi_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
