-- Migration V3: Borrow Serials + New Borrower Fields
USE `equipment_borrowing`;

-- 1. Add status column to equipment_serials (track per-serial availability)
ALTER TABLE `equipment_serials`
  ADD COLUMN IF NOT EXISTS `status` ENUM('available','borrowed') DEFAULT 'available';

-- 2. Create borrow_serials table (links borrowing to specific serial items)
CREATE TABLE IF NOT EXISTS `borrow_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `borrowing_id` int(11) NOT NULL COMMENT 'อ้างอิง borrowings.id',
  `serial_id` int(11) NOT NULL COMMENT 'อ้างอิง equipment_serials.id',
  `serial_code` varchar(100) NOT NULL COMMENT 'เก็บค่า serial_code ณ เวลายืม',
  PRIMARY KEY (`id`),
  KEY `borrowing_id` (`borrowing_id`),
  KEY `serial_id` (`serial_id`),
  CONSTRAINT `fk_bs_borrowing` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bs_serial`    FOREIGN KEY (`serial_id`)    REFERENCES `equipment_serials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='รหัสครุภัณฑ์ที่เลือกในแต่ละรายการยืม';

-- 3. Add new borrower fields to borrowings
ALTER TABLE `borrowings`
  ADD COLUMN IF NOT EXISTS `borrower_student_id` varchar(50) DEFAULT NULL COMMENT 'รหัสนักศึกษา/พนักงาน',
  ADD COLUMN IF NOT EXISTS `use_location` varchar(255) DEFAULT NULL COMMENT 'สถานที่ใช้งาน',
  ADD COLUMN IF NOT EXISTS `purpose` text DEFAULT NULL COMMENT 'เหตุผลการยืม';

-- 4. Expand status ENUM to include 'pending'
ALTER TABLE `borrowings`
  MODIFY COLUMN `status` ENUM('borrowing','waiting_return_approval','returned','borrowed','pending') DEFAULT 'pending';

SELECT 'Migration V3 Complete' AS status;
