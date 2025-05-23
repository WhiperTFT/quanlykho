-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th5 21, 2025 lúc 10:17 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `db_quanlykho`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'HẠT NHỰA', NULL, '2025-04-17 02:30:02', '2025-04-17 02:30:02'),
(2, 'NHỰA ABS', 1, '2025-04-17 02:30:59', '2025-04-17 02:30:59'),
(3, 'THERMOFORM', NULL, '2025-04-17 02:33:06', '2025-04-17 02:33:06'),
(4, 'VỈ NHỰA', 3, '2025-04-17 02:33:29', '2025-04-17 02:33:29'),
(5, 'TÚI', NULL, '2025-04-17 02:34:46', '2025-04-17 02:34:46'),
(6, 'NHỰA PP', 1, '2025-04-17 02:35:01', '2025-04-17 02:35:01'),
(7, 'NHỰA LDPE', 1, '2025-04-17 02:38:00', '2025-04-17 02:38:00'),
(8, 'PE', 5, '2025-04-17 02:41:22', '2025-04-17 02:41:22'),
(9, 'PE (P)', 5, '2025-04-17 02:41:32', '2025-04-17 02:41:32'),
(10, 'HỘP', NULL, '2025-04-17 07:42:31', '2025-04-17 07:42:31'),
(11, 'HỘP DRYEL', 10, '2025-04-17 07:42:40', '2025-04-17 07:42:40'),
(13, 'NHỰA PMMA', 1, '2025-04-22 09:22:35', '2025-04-22 09:22:35'),
(14, 'HDPE', 1, '2025-05-13 01:39:31', '2025-05-13 01:39:31'),
(15, 'GPPS', 1, '2025-05-13 01:40:27', '2025-05-13 01:40:27'),
(16, 'HIPS', 1, '2025-05-13 01:40:33', '2025-05-13 01:40:33'),
(17, 'PA66', 1, '2025-05-13 01:40:39', '2025-05-13 01:40:39'),
(18, 'PA6', 1, '2025-05-13 01:40:43', '2025-05-13 01:40:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `company_info`
--

CREATE TABLE `company_info` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `name_vi` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `address_vi` text DEFAULT NULL,
  `address_en` text DEFAULT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn đến file ảnh chữ ký',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `company_info`
--

INSERT INTO `company_info` (`id`, `name_vi`, `name_en`, `address_vi`, `address_en`, `tax_id`, `phone`, `email`, `website`, `logo_path`, `signature_path`, `updated_at`) VALUES
(1, 'CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG', 'URANUS TRADING SERVICES COMPANY LIMITED', '542/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương', '542/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province', '3701760653', '02743501502', 'saothienvuong80@gmail.com', 'http://saothienvuong.com', 'uploads/company/logo_1747359696.png', 'uploads/company/signature_1746602276.png', '2025-05-16 01:41:36');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `delivery_shipments`
--

CREATE TABLE `delivery_shipments` (
  `id` int(11) NOT NULL,
  `sales_order_id` int(10) UNSIGNED NOT NULL COMMENT 'ID của Đơn đặt hàng (PO)',
  `driver_id` int(11) DEFAULT NULL COMMENT 'ID của Tài xế',
  `shipment_date` date NOT NULL COMMENT 'Ngày giao hàng',
  `shipped_by_text` varchar(255) DEFAULT NULL COMMENT 'Tên người giao hàng (nếu không chọn từ bảng drivers)',
  `vehicle_details` varchar(255) DEFAULT NULL COMMENT 'Thông tin xe giao (vd: biển số nếu không lấy từ driver)',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú cho chuyến giao',
  `status` enum('pending_shipment','shipping','partially_completed','fully_completed','cancelled') DEFAULT 'pending_shipment' COMMENT 'Trạng thái chuyến giao: pending_shipment (Chờ giao), shipping (Đang giao), partially_completed (Hoàn thành 1 phần - có thể có hàng trả lại), fully_completed (Hoàn thành đủ), cancelled (Hủy chuyến)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Người tạo chuyến giao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `delivery_shipment_details`
--

CREATE TABLE `delivery_shipment_details` (
  `id` int(11) NOT NULL,
  `delivery_shipment_id` int(11) NOT NULL COMMENT 'ID của Chuyến giao hàng',
  `sales_order_detail_id` int(11) NOT NULL COMMENT 'ID của Chi tiết đơn hàng gốc trong sales_order_details',
  `product_id` int(11) DEFAULT NULL COMMENT 'ID sản phẩm (snapshot)',
  `product_name_snapshot` varchar(255) DEFAULT NULL COMMENT 'Tên sản phẩm (snapshot)',
  `unit_snapshot` varchar(100) DEFAULT NULL COMMENT 'Đơn vị tính (snapshot)',
  `quantity_ordered_snapshot` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Số lượng đặt ban đầu của sản phẩm này trong PO detail (snapshot)',
  `quantity_shipped` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Số lượng thực giao trong chuyến này',
  `quantity_returned_damage` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Số lượng trả lại do hư hỏng',
  `quantity_returned_other` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Số lượng trả lại vì lý do khác (giao dư, KH không nhận)',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú cho dòng chi tiết giao hàng này',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL COMMENT 'Tên tài xế',
  `cccd` varchar(20) NOT NULL COMMENT 'Số Căn cước công dân',
  `ngay_cap` date DEFAULT NULL COMMENT 'Ngày cấp CCCD',
  `noi_cap` varchar(255) DEFAULT NULL COMMENT 'Nơi cấp CCCD',
  `sdt` varchar(15) DEFAULT NULL,
  `bien_so_xe` text DEFAULT NULL COMMENT 'Danh sách biển số xe, cách nhau bởi dấu phẩy',
  `ghi_chu` text DEFAULT NULL COMMENT 'Ghi chú thêm',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `drivers`
--

INSERT INTO `drivers` (`id`, `ten`, `cccd`, `ngay_cap`, `noi_cap`, `sdt`, `bien_so_xe`, `ghi_chu`, `created_at`, `updated_at`) VALUES
(1, 'LÊ VĂN HUY', '089090015023', '2022-11-04', 'Cục trưởng cục cảnh sát quản lý hành chính về trật tự xã hội', '0933216410', '61C – 322.84', 'LÊ VĂN HUY - 089090015023- 61C32284 - LẤY NGÀY', '2025-05-14 02:54:36', '2025-05-14 03:02:30'),
(2, 'NGUYỄN VĂN BÌNH', '089082023019', '2022-10-10', 'Cục trưởng cục cảnh sát quản lý hành chính về trật tự xã hội', '0869659469', '67L2 – 07920', 'NGUYỄN VĂN BÌNH- 089082023019 - 67L2 – 07920 - LẤY NGÀY', '2025-05-14 03:00:46', '2025-05-14 03:02:21'),
(3, 'Trần Thanh Phúc', '074081001024', '2022-04-25', 'Cục trưởng cục cảnh sát quản lý hành chính về trật tự xã hội', '0365373709', '61-C1 84930', 'TRẦN THANH PHÚC - 074081001024 - 61C1 - 84930 - LẤY NGÀY', '2025-05-14 03:02:06', '2025-05-14 03:02:42');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `order_number` varchar(100) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `cc_emails` text DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL COMMENT 'Tiêu đề email đã gửi',
  `body` text DEFAULT NULL COMMENT 'Nội dung email đã gửi',
  `status` enum('pending','sending','sent','failed') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `attachment_paths` varchar(512) DEFAULT NULL COMMENT 'Đường dẫn đến file đính kèm đã gửi',
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'ID của Sản phẩm',
  `transaction_type` enum('purchase_received','sale_shipped','adjustment_in','adjustment_out','customer_return','supplier_return','initial_stock','production_in','production_out','transfer_in','transfer_out') NOT NULL COMMENT 'Loại giao dịch kho',
  `quantity` decimal(10,2) NOT NULL COMMENT 'Số lượng thay đổi (dương là nhập, âm là xuất)',
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Ngày giờ giao dịch',
  `reference_type` varchar(100) DEFAULT NULL COMMENT 'Loại chứng từ gốc (e.g., sales_orders, delivery_shipments)',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID của chứng từ gốc',
  `reference_detail_id` int(11) DEFAULT NULL COMMENT 'ID của chi tiết chứng từ gốc (e.g., sales_order_detail_id, delivery_shipment_detail_id)',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú giao dịch kho',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID Người tạo giao dịch'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `ip_address`, `timestamp`) VALUES
(1, 1, 'Updated company information.', '::1', '2025-04-15 08:02:11'),
(2, 1, 'Updated company information.', '::1', '2025-04-15 08:02:37'),
(3, 1, 'Added new partner: CÔNG TY TNHH KIM HOÀNG LONG', '::1', '2025-04-15 08:36:26'),
(4, 1, 'Added new partner: CÔNG TY TNHH S&J HOSIERY (VIỆT NAM)', '::1', '2025-04-15 08:39:06'),
(5, 1, 'Added new category: HẠT NHỰA', '::1', '2025-04-15 08:41:56'),
(6, 1, 'Added new category: NHỰA ABS', '::1', '2025-04-15 08:45:22'),
(7, 1, 'Added new unit: KG', '::1', '2025-04-15 08:46:05'),
(8, 1, 'Added new unit: CÁI', '::1', '2025-04-15 08:46:09'),
(9, 1, 'Added new unit: TÚI', '::1', '2025-04-15 08:46:14'),
(10, 1, 'Added new unit: GÓI', '::1', '2025-04-15 08:46:18'),
(11, 1, 'Added new unit: CUỘN', '::1', '2025-04-15 08:46:27'),
(12, 1, 'Added new product: ABS PA-757', '::1', '2025-04-15 08:46:45'),
(13, 1, 'Added new category: NHỰA LDPE', '::1', '2025-04-15 08:47:12'),
(14, 1, 'Added new category: NHỰA PP', '::1', '2025-04-15 08:47:21'),
(15, 1, 'Added new category: TÚI', '::1', '2025-04-15 08:47:40'),
(16, 1, 'Added new category: BỘT MÀU', '::1', '2025-04-15 08:47:46'),
(17, 1, 'Added new product: LDPE 2427H', '::1', '2025-04-15 08:48:23'),
(18, 1, 'Deleted product ID: 2 (Name: LDPE 2427H)', '::1', '2025-04-15 08:48:30'),
(19, 1, 'Added new product: LDPE 2427H', '::1', '2025-04-15 08:55:54'),
(20, 1, 'Added new product: PP 1100N', '::1', '2025-04-15 08:56:08'),
(21, 1, 'Added new product: ABS PA-758', '::1', '2025-04-15 08:56:19'),
(22, 1, 'Added new category: TÚI PE (P)', '::1', '2025-04-15 09:00:43'),
(23, 1, 'Added new category: TÚI PE', '::1', '2025-04-15 09:00:53'),
(24, 1, 'Added new category: TÚI HDPE', '::1', '2025-04-15 09:01:01'),
(25, 1, 'Added new category: THERMOFORM', '::1', '2025-04-15 09:01:16'),
(26, 1, 'Added new category: VỈ NHỰA', '::1', '2025-04-15 09:01:34'),
(27, 1, 'Updated product ID: 5', '::1', '2025-04-15 09:09:39'),
(28, 1, 'Updated product ID: 1', '::1', '2025-04-15 09:19:23'),
(29, 1, 'Updated product ID: 1', '::1', '2025-04-15 09:22:24'),
(30, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:39:40'),
(31, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:39:45'),
(32, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:39:49'),
(33, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:40:16'),
(34, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:41:07'),
(35, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-04-16 01:42:32'),
(36, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-16 01:42:57'),
(37, 1, 'Updated product ID: 1', '::1', '2025-04-16 03:14:42'),
(38, 1, 'CRITICAL Catalog Processing Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.image_path\' in \'field list\'', '::1', '2025-04-16 03:40:53'),
(39, 1, 'Added new category: LDPE 2427H', '::1', '2025-04-16 03:42:00'),
(40, 1, 'Deleted category ID: 12 (Name: LDPE 2427H)', '::1', '2025-04-16 03:42:06'),
(41, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-16 07:04:05'),
(42, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-17 02:23:13'),
(43, 1, 'Updated company information.', '::1', '2025-04-17 04:00:32'),
(44, 1, 'Created Sales Order: PO-20250417-001', '::1', '2025-04-17 08:50:48'),
(45, 1, 'Deleted Sales Order: PO-20250417-001', '::1', '2025-04-17 08:51:12'),
(46, 1, 'Created Sales Order: PO-20250417-001', '::1', '2025-04-17 08:53:21'),
(47, 1, 'Added new partner: CÔNG TY TNHH DI ĐẠI HƯNG', '::1', '2025-04-17 09:24:37'),
(48, 1, 'Created Sales Order: PO-20250417-002', '::1', '2025-04-17 09:25:25'),
(49, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-18 01:13:24'),
(50, 1, 'Updated company information.', '::1', '2025-04-18 02:19:52'),
(51, 1, 'Created Sales Order: PO-20250418-001', '::1', '2025-04-18 02:21:16'),
(52, 1, 'Deleted Sales Order: PO-20250418-001', '::1', '2025-04-18 02:50:37'),
(53, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-18 03:42:46'),
(54, 1, 'Updated Sales Order: PO-20250417-002', '::1', '2025-04-18 04:16:05'),
(55, 1, 'Updated Sales Order: PO-20250417-002', '::1', '2025-04-18 07:08:10'),
(56, 1, 'Updated company information.', '::1', '2025-04-18 08:32:33'),
(57, 1, 'Updated company information.', '::1', '2025-04-18 08:32:37'),
(58, 1, 'Updated Sales Order: PO-20250417-002', '::1', '2025-04-18 09:03:39'),
(59, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-21 01:37:23'),
(60, 1, 'Updated Sales Order: PO-20250417-002', '::1', '2025-04-21 01:38:48'),
(61, 1, 'Created Sales Order: PO-20250421-001', '::1', '2025-04-21 03:29:49'),
(62, 1, 'Updated Sales Order: PO-20250417-001', '::1', '2025-04-21 04:04:32'),
(63, 1, 'Updated Sales Order: PO-20250421-001', '::1', '2025-04-21 04:04:38'),
(64, 1, 'Updated Sales Order: PO-20250421-001', '::1', '2025-04-21 04:31:02'),
(65, 1, 'Updated Sales Order: PO-20250421-001', '::1', '2025-04-21 08:47:46'),
(66, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-22 01:36:04'),
(67, 1, 'Added new partner: CÔNG TY TNHH VIỆT NAM DONG YUN PLATE MAKING MIỀN NAM', '::1', '2025-04-22 09:27:49'),
(68, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-23 01:43:19'),
(69, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-23 06:43:54'),
(70, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-24 01:50:43'),
(71, 1, 'Added new partner: CÔNG TY TNHH TM VÀ SX THUẬN HƯNG PHÁT', '::1', '2025-04-24 02:18:35'),
(72, 1, 'Updated partner ID: 2', '::1', '2025-04-24 02:27:53'),
(73, 1, 'Updated partner ID: 3', '::1', '2025-04-24 02:39:42'),
(74, 1, 'Updated partner ID: 3', '::1', '2025-04-24 02:39:51'),
(75, 1, 'Updated partner ID: 3', '::1', '2025-04-24 02:39:57'),
(76, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-25 01:32:31'),
(77, 1, 'Added new partner: test', '::1', '2025-04-25 01:54:00'),
(78, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-27 07:02:20'),
(79, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-04-27 13:25:29'),
(80, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.14', '2025-04-27 13:27:07'),
(81, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.14', '2025-04-27 13:27:11'),
(82, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.14', '2025-04-27 13:27:23'),
(83, 1, 'Người dùng đăng nhập thành công.', '192.168.1.14', '2025-04-27 13:27:35'),
(84, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-01 04:34:17'),
(85, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-02 08:35:34'),
(86, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-03 08:37:16'),
(87, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-04 05:31:30'),
(88, NULL, 'WARNING: Language file not found: C:\\xampp\\htdocs\\quanlykho\\includes/../lang/en.json. Falling back to default.', '::1', '2025-05-04 01:31:44'),
(89, NULL, 'WARNING: Language file not found: C:\\xampp\\htdocs\\quanlykho\\includes/../lang/en.json. Falling back to default.', '::1', '2025-05-04 01:31:44'),
(90, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-06 01:35:41'),
(91, 1, 'Người dùng đã đăng xuất.', '::1', '2025-05-06 07:03:19'),
(92, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-06 07:03:23'),
(93, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-07 02:00:18'),
(94, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.7', '2025-05-07 06:51:11'),
(95, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.7', '2025-05-07 06:51:15'),
(96, NULL, 'Đăng nhập thất bại với tên: admin', '192.168.1.7', '2025-05-07 06:51:20'),
(97, 1, 'Người dùng đăng nhập thành công.', '192.168.1.7', '2025-05-07 06:51:32'),
(98, 1, 'Người dùng đã đăng xuất.', '192.168.1.7', '2025-05-07 06:52:21'),
(99, 1, 'Updated company information.', '::1', '2025-05-07 07:16:15'),
(100, 1, 'Updated partner ID: 6', '::1', '2025-05-12 07:16:34'),
(101, 1, 'Added new partner: test', '::1', '2025-05-12 07:50:50'),
(102, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 07:50:56'),
(103, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 07:51:03'),
(104, 1, 'Deleted partner ID: 7 (Name: test)', '::1', '2025-05-12 07:51:08'),
(105, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 07:51:12'),
(106, 1, 'Updated partner ID: 6', '::1', '2025-05-12 07:51:20'),
(107, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 07:51:22'),
(108, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 07:51:26'),
(109, 1, 'Người dùng đã đăng xuất.', '::1', '2025-05-12 08:01:39'),
(110, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-12 08:16:47'),
(111, 1, 'Người dùng đã đăng xuất.', '::1', '2025-05-12 08:16:48'),
(112, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-12 08:20:21'),
(113, 1, 'CRITICAL Partner Processing Error: SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`db_quanlykho`.`sales_orders`, CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`)', '::1', '2025-05-12 08:20:27'),
(114, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-13 01:31:21'),
(115, 1, 'Added new partner: CÔNG TY TRÁCH NHIỆM HỮU HẠN SẢN XUẤT THƯƠNG MẠI NHỰA CẨM THÀNH', '::1', '2025-05-13 01:59:29'),
(116, 1, 'CRITICAL Partner Processing PDOException: SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'email\' cannot be null', '::1', '2025-05-13 02:19:14'),
(117, 1, 'CRITICAL Partner Processing PDOException: SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'email\' cannot be null', '::1', '2025-05-13 02:19:18'),
(118, 1, 'CRITICAL Partner Processing PDOException: SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'email\' cannot be null', '::1', '2025-05-13 02:19:34'),
(119, 1, 'Added new partner: test', '::1', '2025-05-13 02:19:37'),
(120, 1, 'Deleted partner ID: 9 (Name: test)', '::1', '2025-05-13 02:19:48'),
(121, 1, 'Deleted partner ID: 6 (Name: test)', '::1', '2025-05-13 02:19:52'),
(122, 1, 'Added new partner: test', '::1', '2025-05-13 02:29:41'),
(123, 1, 'Added new partner: test 2', '::1', '2025-05-13 02:32:17'),
(124, NULL, 'Đăng nhập thất bại với tên: admin', '::1', '2025-05-13 09:09:59'),
(125, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-13 09:10:03'),
(126, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-14 01:24:08'),
(127, 1, 'Người dùng đăng nhập thành công.', '::1', '2025-05-15 01:03:45');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `partners`
--

CREATE TABLE `partners` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('customer','supplier') NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `cc_emails` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `partners`
--

INSERT INTO `partners` (`id`, `name`, `type`, `tax_id`, `address`, `phone`, `email`, `cc_emails`, `contact_person`, `created_at`, `updated_at`) VALUES
(1, 'CÔNG TY TNHH KIM HOÀNG LONG', 'supplier', '0309509850', 'Số 26 Đường Số 6, Khu phố 11, Phường Trường Thọ, Thành phố Thủ Đức, Thành phố Hồ Chí Minh, Việt Nam', '0348880949', 'kimhoanglongco@khlplastic.vn', NULL, 'Ms. Hồng', '2025-04-15 08:36:26', '2025-04-15 08:36:26'),
(2, 'CÔNG TY TNHH S&J HOSIERY (VIỆT NAM)', 'customer', '3700755643', 'Lô M-3B-CN, Khu công nghiệp Mỹ Phước 2, Phường Chánh Phú Hòa, Thành Phố Bến Cát, Tỉnh Bình Dương, Việt Nam', NULL, 'maryjane@snj.com.vn', 'loan@snj.com.vn', NULL, '2025-04-15 08:39:06', '2025-04-24 02:27:53'),
(3, 'CÔNG TY TNHH DI ĐẠI HƯNG', 'supplier', '0303171660', 'Số 462 và 466 Hồng Bàng, Phường 16, Quận 11, TP. HCM', '028 3960 5688- 3960 5800', 'info@tashing.com.vn', 'sale9@tashing.com.vn', 'Mr Lộc', '2025-04-17 09:24:37', '2025-05-16 08:55:50'),
(4, 'CÔNG TY TNHH VIỆT NAM DONG YUN PLATE MAKING MIỀN NAM', 'customer', '1100785988', 'Lô số 5, đường số 7, KCN Tân Đức, Xã Đức Hòa Hạ, Huyện Đức Hoà, Tỉnh Long An', '0272.3.769.617', '', NULL, NULL, '2025-04-22 09:27:49', '2025-04-22 09:27:49'),
(5, 'CÔNG TY TNHH TM VÀ SX THUẬN HƯNG PHÁT', 'supplier', '0304218544', '532/3/23 Kinh Dương Vương, KP.1, P.An Lạc, Q.Bình Tân, TP.HCM\nChi Nhánh 1: 638, Trần Đại Nghĩa, Ấp 1, X.Tân Kiên, Huyện Bình Chánh, Tp.HCM.', '(028)37524200 - 3752 6652', 'thuanhungphat168@gmail.com', 'thuanhungphat@yahoo.com', '0918795525 Ms Linh;  Mộng - 0906376269', '2025-04-24 02:18:35', '2025-04-24 02:18:35'),
(8, 'CÔNG TY TRÁCH NHIỆM HỮU HẠN SẢN XUẤT THƯƠNG MẠI NHỰA CẨM THÀNH', 'supplier', '0314121090', '871 Hồng Bàng, Phường 9, Quận 6,TP Hồ Chí Minh', '0932180589', 'info@camthanh.com.vn', 'hoadon@camthanh.com.vn', 'Anh Dũng', '2025-05-13 01:59:29', '2025-05-13 01:59:29'),
(10, 'test', 'customer', NULL, NULL, NULL, 'duong.garenaag@gmail.com', NULL, NULL, '2025-05-13 02:29:41', '2025-05-13 02:29:41'),
(11, 'test 2', 'supplier', NULL, NULL, NULL, 'duong.garenaag@gmail.com', NULL, NULL, '2025-05-13 02:32:17', '2025-05-13 02:32:17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `category_id`, `unit_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'ABS 765A', '', '2025-04-17 02:32:38', '2025-05-13 01:32:04'),
(2, 2, 1, 'ABS PA-757', '', '2025-04-17 02:32:52', '2025-04-17 02:32:52'),
(3, 4, 2, 'VỈ TRÒN F83', '', '2025-04-17 02:33:43', '2025-04-17 02:33:43'),
(4, 7, 1, 'LDPE 2427K', '', '2025-04-17 02:38:17', '2025-04-17 02:38:17'),
(5, 6, 1, 'PP H129', 'Tên cũ là PP 1100N', '2025-04-17 02:41:06', '2025-04-17 02:41:06'),
(6, 2, 1, 'ABS PA-758', NULL, '2025-04-17 07:55:13', '2025-04-17 07:55:13'),
(7, 13, 1, 'PMMA CM205', '', '2025-04-22 09:24:06', '2025-04-22 09:25:41'),
(8, 14, 1, 'HDPE KT10000UE', '', '2025-05-13 01:39:52', '2025-05-14 02:39:05'),
(9, 7, 1, 'LDPE 2427H', '', '2025-05-13 01:41:01', '2025-05-13 01:41:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_files`
--

CREATE TABLE `product_files` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_type` enum('image','pdf') NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `product_files`
--

INSERT INTO `product_files` (`id`, `product_id`, `file_path`, `original_filename`, `file_type`, `uploaded_at`) VALUES
(2, 1, 'uploads/products/documents/13-Sao_Thi__n_V____ng_-_TB_tr___c_Dryel__1__1744857158_84659811.pdf', '13-Sao Thiên Vương - TB trục Dryel (1).pdf', 'pdf', '2025-04-17 02:32:38'),
(4, 7, 'uploads/products/documents/ISO_TDS_CM-205_EN_1745313931_cd857ecc.pdf', 'ISO_TDS_CM-205_EN.pdf', 'pdf', '2025-04-22 09:25:31');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quote_email_logs`
--

CREATE TABLE `quote_email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `order_number` varchar(100) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `cc_emails` text DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` enum('pending','sending','sent','failed') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `attachment_paths` varchar(512) DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `quote_email_logs`
--

INSERT INTO `quote_email_logs` (`id`, `order_id`, `created_at`, `order_number`, `to_email`, `cc_emails`, `subject`, `body`, `status`, `message`, `attachment_paths`, `sent_at`) VALUES
(6, 4, '2025-05-16 03:47:59', '', 'duong.garenaag@gmail.com', '', 'BG STV - BG-STV-16052025-001', 'Kính gửi Quý công ty,\r\n\r\nCông ty STV xin gửi đến Quý công ty BG số: BG-STV-16052025-001.\r\nVui lòng xem chi tiết trong file PDF đính kèm.\r\n\r\nThanks and best regard!', 'pending', NULL, '[\"pdf\\/BG-STV-16052025-001.pdf\"]', NULL),
(7, 4, '2025-05-19 04:24:53', '', 'duong.garenaag@gmail.com', '', 'BG STV - BG-STV-16052025-001', 'Kính gửi Quý công ty,\r\n\r\nCông ty STV xin gửi đến Quý công ty BG số: BG-STV-16052025-001.\r\nVui lòng xem chi tiết trong file PDF đính kèm.\r\n\r\nThanks and best regard!', 'pending', NULL, '[]', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `company_info_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`company_info_snapshot`)),
  `supplier_info_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supplier_info_snapshot`)),
  `quote_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sub_total` decimal(18,2) DEFAULT 0.00,
  `vat_total` decimal(18,2) DEFAULT 0.00,
  `grand_total` decimal(18,2) DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'VND' COMMENT 'Loại tiền tệ (VND, USD)',
  `status` enum('draft','ordered','partially_received','fully_received','cancelled') DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vat_rate` decimal(5,2) DEFAULT 10.00 COMMENT 'VAT Rate for the whole order'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `order_number`, `order_date`, `supplier_id`, `company_info_snapshot`, `supplier_info_snapshot`, `quote_id`, `notes`, `sub_total`, `vat_total`, `grand_total`, `currency`, `status`, `created_by`, `created_at`, `updated_at`, `vat_rate`) VALUES
(12, 'PO-STV-13052025-001', '2025-05-13', 11, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/logos\\/logo_1744965153.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-07 14:17:56\"}', '{\"id\":11,\"name\":\"test 2\",\"type\":\"supplier\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:32:17\",\"updated_at\":\"2025-05-13 09:32:17\"}', NULL, NULL, 2000.00, 200.00, 2200.00, 'VND', 'draft', 1, '2025-05-13 02:32:34', '2025-05-13 02:32:34', 10.00),
(14, 'PO-STV-15052025-001', '2025-05-15', 11, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/logos\\/logo_1744965153.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-07 14:17:56\"}', '{\"id\":11,\"name\":\"test 2\",\"type\":\"supplier\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:32:17\",\"updated_at\":\"2025-05-13 09:32:17\"}', 2, NULL, 280000.00, 28000.00, 308000.00, 'VND', 'draft', 1, '2025-05-15 07:57:06', '2025-05-15 09:05:15', 10.00),
(16, 'PO-STV-16052025-001', '2025-05-16', 11, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/company\\/logo_1747359696.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-16 08:41:36\"}', '{\"id\":11,\"name\":\"test 2\",\"type\":\"supplier\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:32:17\",\"updated_at\":\"2025-05-13 09:32:17\"}', 3, NULL, 1100000.00, 110000.00, 1210000.00, 'VND', 'draft', 1, '2025-05-16 02:20:20', '2025-05-16 07:41:14', 10.00),
(17, 'PO-STV-16052025-002', '2025-05-16', 3, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/company\\/logo_1747359696.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-16 08:41:36\"}', '{\"id\":3,\"name\":\"CÔNG TY TNHH DI ĐẠI HƯNG\",\"type\":\"supplier\",\"tax_id\":\"0303171660\",\"address\":\"Số 462 và 466 Hồng Bàng, Phường 16, Quận 11, TP. HCM\",\"phone\":\"028 3960 5688- 3960 5800\",\"email\":\"info@tashing.com.vn\",\"cc_emails\":\"sale9@tashing.com.vn\",\"contact_person\":\"Mr Lộc\",\"created_at\":\"2025-04-17 16:24:37\",\"updated_at\":\"2025-05-16 15:55:50\"}', 5, NULL, 11000000.00, 1100000.00, 12100000.00, 'VND', 'draft', 1, '2025-05-16 08:54:29', '2025-05-21 08:13:31', 10.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sales_order_details`
--

CREATE TABLE `sales_order_details` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'ID đơn hàng (FK tới sales_orders)',
  `product_id` int(11) DEFAULT NULL COMMENT 'ID sản phẩm (FK tới products, có thể NULL nếu là sản phẩm ghi chú thêm)',
  `product_name_snapshot` varchar(255) NOT NULL COMMENT 'Tên sản phẩm tại thời điểm tạo đơn',
  `category_snapshot` varchar(255) DEFAULT NULL COMMENT 'Tên danh mục tại thời điểm tạo đơn',
  `unit_snapshot` varchar(100) DEFAULT NULL COMMENT 'Tên đơn vị tính tại thời điểm tạo đơn',
  `quantity` decimal(10,2) NOT NULL COMMENT 'Số lượng đặt',
  `unit_price` decimal(15,2) NOT NULL COMMENT 'Đơn giá'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chi tiết Đơn Đặt Hàng Mua';

--
-- Đang đổ dữ liệu cho bảng `sales_order_details`
--

INSERT INTO `sales_order_details` (`id`, `order_id`, `product_id`, `product_name_snapshot`, `category_snapshot`, `unit_snapshot`, `quantity`, `unit_price`) VALUES
(20, 12, 6, 'ABS PA-758', 'NHỰA ABS', 'KG', 10.00, 200.00),
(23, 14, 8, 'HDPE KT10000UE', 'HDPE', 'KG', 100.00, 200.00),
(24, 14, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 300.00, 400.00),
(65, 16, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 3000.00),
(66, 16, 6, 'ABS PA-758', 'NHỰA ABS', 'KG', 200.00, 4000.00),
(67, 17, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 30000.00),
(68, 17, 2, 'ABS PA-757', 'NHỰA ABS', 'KG', 200.00, 40000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sales_quotes`
--

CREATE TABLE `sales_quotes` (
  `id` int(10) UNSIGNED NOT NULL,
  `quote_number` varchar(50) NOT NULL,
  `quote_date` date NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `company_info_snapshot` longtext DEFAULT NULL,
  `customer_info_snapshot` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `sub_total` decimal(18,2) DEFAULT 0.00,
  `vat_total` decimal(18,2) DEFAULT 0.00,
  `grand_total` decimal(18,2) DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'VND',
  `status` enum('draft','sent','accepted','rejected','expired','invoiced') DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vat_rate` decimal(5,2) DEFAULT 10.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sales_quotes`
--

INSERT INTO `sales_quotes` (`id`, `quote_number`, `quote_date`, `customer_id`, `company_info_snapshot`, `customer_info_snapshot`, `notes`, `sub_total`, `vat_total`, `grand_total`, `currency`, `status`, `created_by`, `created_at`, `updated_at`, `vat_rate`) VALUES
(1, 'BG-STV-12052025-001', '2025-05-12', 4, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/logos\\/logo_1744965153.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-07 14:17:56\"}', '{\"id\":4,\"name\":\"CÔNG TY TNHH VIỆT NAM DONG YUN PLATE MAKING MIỀN NAM\",\"type\":\"customer\",\"tax_id\":\"1100785988\",\"address\":\"Lô số 5, đường số 7, KCN Tân Đức, Xã Đức Hòa Hạ, Huyện Đức Hoà, Tỉnh Long An\",\"phone\":\"0272.3.769.617\",\"email\":\"\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-04-22 16:27:49\",\"updated_at\":\"2025-04-22 16:27:49\"}', NULL, 2000000.00, 200000.00, 2200000.00, 'VND', 'rejected', 1, '2025-05-12 03:43:41', '2025-05-15 06:54:03', 10.00),
(2, 'BG-STV-13052025-001', '2025-05-13', 10, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/logos\\/logo_1744965153.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-07 14:17:56\"}', '{\"id\":10,\"name\":\"test\",\"type\":\"customer\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:29:41\",\"updated_at\":\"2025-05-13 09:29:41\"}', NULL, 200000.00, 20000.00, 220000.00, 'VND', 'accepted', 1, '2025-05-13 02:30:15', '2025-05-15 06:53:32', 10.00),
(3, 'BG-STV-15052025-001', '2025-05-15', 10, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/logos\\/logo_1744965153.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-07 14:17:56\"}', '{\"id\":10,\"name\":\"test\",\"type\":\"customer\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:29:41\",\"updated_at\":\"2025-05-13 09:29:41\"}', NULL, 11000000.00, 1100000.00, 12100000.00, 'VND', 'accepted', 1, '2025-05-15 07:01:43', '2025-05-15 09:11:10', 10.00),
(4, 'BG-STV-16052025-001', '2025-05-16', 10, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/company\\/logo_1747359696.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-16 08:41:36\"}', '{\"id\":10,\"name\":\"test\",\"type\":\"customer\",\"tax_id\":null,\"address\":null,\"phone\":null,\"email\":\"duong.garenaag@gmail.com\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-05-13 09:29:41\",\"updated_at\":\"2025-05-13 09:29:41\"}', NULL, 140000.00, 14000.00, 154000.00, 'VND', 'draft', 1, '2025-05-16 01:40:34', '2025-05-21 07:58:29', 10.00),
(5, 'BG-STV-16052025-002', '2025-05-16', 4, '{\"id\":1,\"name_vi\":\"CÔNG TY TNHH THƯƠNG MẠI DỊCH VỤ SAO THIÊN VƯƠNG\",\"name_en\":\"URANUS TRADING SERVICES COMPANY LIMITED\",\"address_vi\":\"542\\/3 khu phố Thạnh Bình, Phường An Thạnh, Thành Phố Thuận An, tỉnh Bình Dương\",\"address_en\":\"542\\/3 Thanh Binh Quarter, An Thanh Ward, Thuan An City, Binh Duong Province\",\"tax_id\":\"3701760653\",\"phone\":\"02743501502\",\"email\":\"saothienvuong80@gmail.com\",\"website\":\"http:\\/\\/saothienvuong.com\",\"logo_path\":\"uploads\\/company\\/logo_1747359696.png\",\"signature_path\":\"uploads\\/company\\/signature_1746602276.png\",\"updated_at\":\"2025-05-16 08:41:36\"}', '{\"id\":4,\"name\":\"CÔNG TY TNHH VIỆT NAM DONG YUN PLATE MAKING MIỀN NAM\",\"type\":\"customer\",\"tax_id\":\"1100785988\",\"address\":\"Lô số 5, đường số 7, KCN Tân Đức, Xã Đức Hòa Hạ, Huyện Đức Hoà, Tỉnh Long An\",\"phone\":\"0272.3.769.617\",\"email\":\"\",\"cc_emails\":null,\"contact_person\":null,\"created_at\":\"2025-04-22 16:27:49\",\"updated_at\":\"2025-04-22 16:27:49\"}', '<p>test</p>', 11000000.00, 1100000.00, 12100000.00, 'VND', 'accepted', 1, '2025-05-16 08:53:22', '2025-05-16 08:53:47', 10.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sales_quote_details`
--

CREATE TABLE `sales_quote_details` (
  `id` int(11) NOT NULL,
  `quote_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name_snapshot` varchar(255) NOT NULL,
  `category_snapshot` varchar(255) DEFAULT NULL,
  `unit_snapshot` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sales_quote_details`
--

INSERT INTO `sales_quote_details` (`id`, `quote_id`, `product_id`, `product_name_snapshot`, `category_snapshot`, `unit_snapshot`, `quantity`, `unit_price`) VALUES
(1, 1, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 20000.00),
(2, 2, 8, 'HDPE KT10000UE', 'HDPE', 'KG', 100.00, 2000.00),
(3, 3, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 30000.00),
(4, 3, 7, 'PMMA CM205', 'NHỰA PMMA', 'KG', 200.00, 40000.00),
(5, 4, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 200.00),
(6, 4, 7, 'PMMA CM205', 'NHỰA PMMA', 'KG', 300.00, 400.00),
(7, 5, 1, 'ABS 765A', 'NHỰA ABS', 'KG', 100.00, 30000.00),
(8, 5, 2, 'ABS PA-757', 'NHỰA ABS', 'KG', 200.00, 40000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `units`
--

INSERT INTO `units` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'KG', NULL, '2025-04-17 02:31:28', '2025-04-17 02:31:28'),
(2, 'CÁI', NULL, '2025-04-17 02:31:34', '2025-04-17 02:31:34'),
(3, 'Trục', NULL, '2025-04-17 02:31:45', '2025-04-17 02:31:45'),
(4, 'GÓI', NULL, '2025-04-17 02:31:53', '2025-04-17 02:31:53'),
(5, 'HỘP', NULL, '2025-04-24 08:17:16', '2025-04-24 08:17:16'),
(6, 'M2', NULL, '2025-04-24 08:17:20', '2025-04-24 08:17:20'),
(7, 'CUỘN', NULL, '2025-04-24 08:17:33', '2025-04-24 08:17:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','manager') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `signature_image_path` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn ảnh chữ ký',
  `signature_pos_x` varchar(10) DEFAULT '0px' COMMENT 'Vị trí X của chữ ký',
  `signature_pos_y` varchar(10) DEFAULT '0px' COMMENT 'Vị trí Y của chữ ký',
  `signature_size_w` varchar(10) DEFAULT '150px' COMMENT 'Chiều rộng chữ ký',
  `signature_size_h` varchar(10) DEFAULT 'auto' COMMENT 'Chiều cao chữ ký',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `full_name`, `role`, `is_active`, `signature_image_path`, `signature_pos_x`, `signature_pos_y`, `signature_size_w`, `signature_size_h`, `created_at`, `updated_at`, `permissions`) VALUES
(1, 'admin', '$2y$10$fSsF4MVsBm1vqXvNeD09auDc69vNixdH2n9eeAf/DLx300xfqMUBC', NULL, 'Administrator', 'admin', 1, 'uploads/signatures/user_1_sig_1744949267.png', '10px', '10px', '150px', 'auto', '2025-04-15 07:55:13', '2025-04-18 04:07:48', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `view_product_stock`
-- (See below for the actual view)
--
CREATE TABLE `view_product_stock` (
`product_id` int(11)
,`product_name` varchar(255)
,`product_description` text
,`category_name` varchar(255)
,`unit_name` varchar(100)
,`current_stock` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `view_product_stock`
--
DROP TABLE IF EXISTS `view_product_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_product_stock`  AS SELECT `p`.`id` AS `product_id`, `p`.`name` AS `product_name`, `p`.`description` AS `product_description`, `cat`.`name` AS `category_name`, `u`.`name` AS `unit_name`, coalesce(sum(`it`.`quantity`),0) AS `current_stock` FROM (((`products` `p` left join `categories` `cat` on(`p`.`category_id` = `cat`.`id`)) left join `units` `u` on(`p`.`unit_id` = `u`.`id`)) left join `inventory_transactions` `it` on(`p`.`id` = `it`.`product_id`)) GROUP BY `p`.`id`, `p`.`name`, `p`.`description`, `cat`.`name`, `u`.`name` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Chỉ mục cho bảng `company_info`
--
ALTER TABLE `company_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tax_id` (`tax_id`);

--
-- Chỉ mục cho bảng `delivery_shipments`
--
ALTER TABLE `delivery_shipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ds_sales_order` (`sales_order_id`),
  ADD KEY `fk_ds_driver` (`driver_id`),
  ADD KEY `fk_ds_user_created` (`created_by`);

--
-- Chỉ mục cho bảng `delivery_shipment_details`
--
ALTER TABLE `delivery_shipment_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dsd_delivery_shipment` (`delivery_shipment_id`),
  ADD KEY `fk_dsd_sales_order_detail` (`sales_order_detail_id`),
  ADD KEY `fk_dsd_product` (`product_id`);

--
-- Chỉ mục cho bảng `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cccd` (`cccd`),
  ADD KEY `idx_ten` (`ten`),
  ADD KEY `idx_cccd` (`cccd`),
  ADD KEY `idx_sdt` (`sdt`);

--
-- Chỉ mục cho bảng `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_product_date` (`product_id`,`transaction_date`),
  ADD KEY `fk_it_user_created` (`created_by`);

--
-- Chỉ mục cho bảng `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tax_id` (`tax_id`),
  ADD KEY `idx_partner_name` (`name`),
  ADD KEY `idx_partner_type` (`type`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Chỉ mục cho bảng `product_files`
--
ALTER TABLE `product_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `quote_email_logs`
--
ALTER TABLE `quote_email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_sales_orders_supplier` (`supplier_id`),
  ADD KEY `fk_sales_orders_user` (`created_by`),
  ADD KEY `idx_currency` (`currency`),
  ADD KEY `fk_sales_orders_quote` (`quote_id`);

--
-- Chỉ mục cho bảng `sales_order_details`
--
ALTER TABLE `sales_order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `sales_quotes`
--
ALTER TABLE `sales_quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNI_quote_number` (`quote_number`),
  ADD KEY `IDX_customer_id` (`customer_id`),
  ADD KEY `IDX_currency` (`currency`),
  ADD KEY `IDX_created_by` (`created_by`);

--
-- Chỉ mục cho bảng `sales_quote_details`
--
ALTER TABLE `sales_quote_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_quote_id` (`quote_id`),
  ADD KEY `IDX_product_id` (`product_id`);

--
-- Chỉ mục cho bảng `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT cho bảng `delivery_shipments`
--
ALTER TABLE `delivery_shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `delivery_shipment_details`
--
ALTER TABLE `delivery_shipment_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT cho bảng `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT cho bảng `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `product_files`
--
ALTER TABLE `product_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `quote_email_logs`
--
ALTER TABLE `quote_email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT cho bảng `sales_order_details`
--
ALTER TABLE `sales_order_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT cho bảng `sales_quotes`
--
ALTER TABLE `sales_quotes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `sales_quote_details`
--
ALTER TABLE `sales_quote_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `delivery_shipments`
--
ALTER TABLE `delivery_shipments`
  ADD CONSTRAINT `fk_ds_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ds_sales_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ds_user_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `delivery_shipment_details`
--
ALTER TABLE `delivery_shipment_details`
  ADD CONSTRAINT `fk_dsd_delivery_shipment` FOREIGN KEY (`delivery_shipment_id`) REFERENCES `delivery_shipments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dsd_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dsd_sales_order_detail` FOREIGN KEY (`sales_order_detail_id`) REFERENCES `sales_order_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_it_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_it_user_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Các ràng buộc cho bảng `product_files`
--
ALTER TABLE `product_files`
  ADD CONSTRAINT `product_files_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `fk_sales_orders_quote` FOREIGN KEY (`quote_id`) REFERENCES `sales_quotes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `partners` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_orders_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
