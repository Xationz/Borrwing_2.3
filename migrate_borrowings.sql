-- Migration: Add detailed borrowing fields to borrowings table
USE `equipment_borrowing`;

ALTER TABLE `borrowings`
  ADD COLUMN IF NOT EXISTS `borrower_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อ-นามสกุล ผู้ยืม',
  ADD COLUMN IF NOT EXISTS `borrower_position` varchar(100) DEFAULT NULL COMMENT 'ตำแหน่งผู้ยืม',
  ADD COLUMN IF NOT EXISTS `borrower_position_other` varchar(255) DEFAULT NULL COMMENT 'ตำแหน่งอื่น ๆ',
  ADD COLUMN IF NOT EXISTS `borrower_unit` varchar(255) DEFAULT NULL COMMENT 'หน่วยสังกัด',
  ADD COLUMN IF NOT EXISTS `borrower_phone` varchar(50) DEFAULT NULL COMMENT 'เบอร์ภายใน',
  ADD COLUMN IF NOT EXISTS `equipment_type` varchar(100) DEFAULT NULL COMMENT 'ประเภทครุภัณฑ์ที่ยืม',
  ADD COLUMN IF NOT EXISTS `equipment_type_other` varchar(255) DEFAULT NULL COMMENT 'ประเภทครุภัณฑ์อื่น ๆ',
  ADD COLUMN IF NOT EXISTS `purpose` varchar(100) DEFAULT NULL COMMENT 'เหตุผลในการยืม',
  ADD COLUMN IF NOT EXISTS `return_date_planned` date DEFAULT NULL COMMENT 'วันที่กำหนดคืน',
  ADD COLUMN IF NOT EXISTS `it_install` tinyint(1) DEFAULT 0 COMMENT 'ต้องการให้ IT ติดตั้ง (1=ต้องการ, 0=ไม่ต้องการ)';
