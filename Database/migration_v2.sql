-- Migration V2: Return Approval System
USE `equipment_borrowing`;

-- 1. ขยาย ENUM status ของ borrowings
ALTER TABLE `borrowings` 
  MODIFY COLUMN `status` ENUM('borrowing','waiting_return_approval','returned','borrowed') DEFAULT 'borrowing';

-- 2. เพิ่มคอลัมน์สำหรับ approval workflow
ALTER TABLE `borrowings`
  ADD COLUMN IF NOT EXISTS `returned_request_at` datetime DEFAULT NULL COMMENT 'เวลาที่ผู้ใช้กดคืน',
  ADD COLUMN IF NOT EXISTS `approved_return_at` datetime DEFAULT NULL COMMENT 'เวลาที่ Admin ยืนยันการคืน',
  ADD COLUMN IF NOT EXISTS `approved_by_admin` int(11) DEFAULT NULL COMMENT 'Admin ที่อนุมัติการคืน',
  ADD COLUMN IF NOT EXISTS `actual_return_date` date DEFAULT NULL COMMENT 'วันที่คืนจริง (บันทึกโดย Admin)';

-- 3. อัพเดต status เก่า: 'borrowed' -> 'borrowing'
UPDATE `borrowings` SET `status` = 'borrowing' WHERE `status` = 'borrowed';

-- 4. Foreign key สำหรับ approved_by_admin (optional - เพิ่มถ้า users table มี)
-- ALTER TABLE `borrowings` ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by_admin`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- ตรวจสอบผลลัพธ์
SELECT 'Migration V2 Complete' as status;
