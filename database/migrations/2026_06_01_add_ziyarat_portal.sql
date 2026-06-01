-- Add Ziyarat Portal tables and default Mazar master data.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `mazars_master` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mazar_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mazar_name` (`mazar_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ziyarat_entries` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `mazar_id` int(11) NOT NULL,
  `count_added` int(11) NOT NULL DEFAULT 0,
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `mazar_id` (`mazar_id`),
  KEY `entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `mazars_master` (`mazar_name`, `display_order`) VALUES
('Ahmedabad Mazar', 1),
('Ahmednagar Mazar', 2),
('Amreli Mazar', 3),
('Aurangabad Mazar', 4),
('Banswara Mazar', 5),
('Baroda Mazar', 6),
('Burhanpur Mazar', 7),
('Chechat Mazar', 8),
('Dongaon Mazar', 9),
('Godhra Mazar', 10),
('Halvad Mazar', 11),
('Hasanfeer Mazar', 12),
('Jamnagar Mazar', 13),
('Kalawad Mazar', 14),
('Kamlapur Mazar', 15),
('Kapadwanj Mazar', 16),
('Khambat Mazar', 17),
('Maisaheba Mazar', 18),
('Mandvi Mazar', 19),
('Morbi Mazar', 20),
('Mumbai Mazar', 21),
('Mundra Mazar', 22),
('Pisawada Mazar', 23),
('Pratapgarh Mazar', 24),
('Rampura Mazar', 25),
('Ranpur Mazar', 26),
('Selavi Mazar', 27),
('Shajapur Mazar', 28),
('Sidhpur Mazar', 29),
('Surat Mazar', 30),
('Galiyakot Mazar', 31),
('Udaipur Mazar', 32),
('Ujjain Mazar', 33),
('Umreth Mazar', 34),
('Wakaner Mazar', 35);

COMMIT;
