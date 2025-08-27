CREATE TABLE `bill_sequences` (
  `year` INTEGER PRIMARY KEY,
  `last_number` INTEGER NOT NULL
);

-- --------------------------------------------------------

CREATE TABLE `customers` (
  `id` INTEGER PRIMARY KEY,
  `customer_name` varchar(255) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_contact` varchar(20) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `inventory` (
  `part_no` varchar(50) PRIMARY KEY,
  `description` varchar(100) DEFAULT NULL,
  `current_qty` int(11) DEFAULT 0,
  `min_qty` int(11) DEFAULT 5
);

-- --------------------------------------------------------

CREATE TABLE `invoices` (
  `id` INTEGER PRIMARY KEY,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `invoice_type` TEXT CHECK( invoice_type IN ('Invoice','Tax Invoice','Estimate','Tax Estimate','Supplementary','Tax Supplementary') ) NOT NULL DEFAULT 'Invoice',
  `with_tax` TEXT CHECK( with_tax IN ('Yes','No') ) NOT NULL DEFAULT 'Yes',
  `invoice_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `invoice_items` (
  `id` INTEGER PRIMARY KEY,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `cgst_percent` decimal(5,2) DEFAULT 0.00,
  `sgst_percent` decimal(5,2) DEFAULT 0.00,
  `cgst` decimal(10,2) DEFAULT 0.00,
  `sgst` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `labour_invoices` (
  `id` INTEGER PRIMARY KEY,
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `labour_invoice_items` (
  `id` INTEGER PRIMARY KEY,
  `invoice_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL
);

-- --------------------------------------------------------

CREATE TABLE `products` (
  `id` INTEGER PRIMARY KEY,
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `purchases` (
  `id` INTEGER PRIMARY KEY,
  `bill_no` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `purpose` TEXT CHECK( purpose IN ('Office','Vehicle','Maintenance') ) DEFAULT NULL,
  `vehicle_reg_no` varchar(20) DEFAULT NULL,
  `vehicle_model` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `total_cgst` decimal(10,2) NOT NULL,
  `total_sgst` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL
);

-- --------------------------------------------------------

CREATE TABLE `purchase_items` (
  `id` INTEGER PRIMARY KEY,
  `purchase_id` INTEGER NOT NULL,
  `description` varchar(100) NOT NULL,
  `mrp` decimal(10,2) NOT NULL,
  `discount` decimal(5,2) DEFAULT 0.00,
  `qty` int(11) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL,
  `sgst_rate` decimal(5,2) NOT NULL
);

-- --------------------------------------------------------

CREATE TABLE `receipts` (
  `id` INTEGER PRIMARY KEY,
  `receipt_number` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_mode` TEXT CHECK( payment_mode IN ('Cash','UPI','Card','Transfer') ) NOT NULL,
  `amount_in_words` text DEFAULT NULL,
  `vehicle_no` varchar(50) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` INTEGER PRIMARY KEY,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` TEXT CHECK( role IN ('admin','staff') ) DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Sathish', '$2y$10$GpP2haIdT4RdTu0qqO5iaOQdU4Rysqt4WmQg7I/9X1/dUu7VFiZYq', 'admin', '2025-06-21 12:41:42'),
(2, 'Vishal', '$2y$10$MUi4apAp1arJGXEN4ixNw.IDzvPwy8pQ94/Voy5CFyvcRwXiGLVIC', 'staff', '2025-08-23 18:17:09'),
(4, 'Suresh Kumar', '$2y$10$M41/f8POcAFnscbWat15i.IWtoNqVB15TW9hkCEnSnVVhJAmGaqSu', 'staff', '2025-08-23 18:28:32');

-- --------------------------------------------------------

CREATE TABLE `vendors` (
  `vendor_id` INTEGER PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms` TEXT CHECK( payment_terms IN ('COD','15 Days','30 Days') ) DEFAULT NULL,
  `is_active` INTEGER DEFAULT 1
);