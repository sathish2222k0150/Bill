CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `brand_name` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_description` text DEFAULT NULL,
  `product_location` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `dealer_price` decimal(10,2) DEFAULT 0.00,
  `retail_price` decimal(10,2) DEFAULT 0.00,
  `cgst` decimal(5,2) DEFAULT 0.00,
  `sgst` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_contact` varchar(20) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `invoice_type` enum('Invoice','Tax Invoice','Estimate','Tax Estimate','Supplementary','Tax Supplementary') NOT NULL DEFAULT 'Invoice',
  `with_tax` enum('Yes','No') NOT NULL DEFAULT 'Yes',
  `invoice_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `cgst_percent` decimal(5,2) DEFAULT 0.00,
  `sgst_percent` decimal(5,2) DEFAULT 0.00,
  `cgst` decimal(10,2) DEFAULT 0.00,
  `sgst` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `labour_invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_contact` varchar(20) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `vehicle_model` varchar(100) DEFAULT NULL,
  `total_hours` decimal(10,2) DEFAULT NULL,
  `rate_per_hour` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `gst_percent` decimal(5,2) DEFAULT NULL,
  `gst_amount` decimal(10,2) DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `labour_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `vendors` (
  `vendor_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms` enum('COD','15 Days','30 Days') DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `bill_sequences` (
  `year` int(4) NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchases` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `purpose` enum('Office','Vehicle','Maintenance') DEFAULT NULL,
  `vehicle_reg_no` varchar(20) DEFAULT NULL,
  `vehicle_model` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `total_cgst` decimal(10,2) NOT NULL,
  `total_sgst` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_no` (`bill_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(100) NOT NULL,
  `mrp` decimal(10,2) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `qty` int(11) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL,
  `sgst_rate` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  CONSTRAINT `fk_purchase_items_purchases` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `inventory` (
  `part_no` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `current_qty` int(11) DEFAULT 0,
  `min_qty` int(11) DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;