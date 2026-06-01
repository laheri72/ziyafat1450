-- Book assignment and request workflow migration
-- Import this in local XAMPP first, then in InfinityFree phpMyAdmin.

START TRANSACTION;

ALTER TABLE `book_transcription`
  MODIFY `status` enum('selected','completed','revoked') DEFAULT 'selected';

ALTER TABLE `book_transcription`
  ADD UNIQUE KEY `unique_book_assignment` (`book_id`);

CREATE TABLE IF NOT EXISTS `book_transcription_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `request_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_book_request` (`user_id`, `book_id`),
  KEY `request_status` (`request_status`),
  KEY `user_id` (`user_id`),
  KEY `book_id` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
