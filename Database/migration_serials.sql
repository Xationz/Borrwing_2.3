-- Migration: เพิ่มตาราง equipment_serials สำหรับเก็บรหัสครุภัณฑ์รายเครื่อง
-- รันไฟล์นี้ใน phpMyAdmin หรือ MySQL CLI ก่อนใช้งานฟีเจอร์ใหม่

USE `equipment_borrowing`;

CREATE TABLE IF NOT EXISTS `equipment_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL COMMENT 'อ้างอิงไปยังตาราง equipment',
  `serial_code` varchar(100) NOT NULL COMMENT 'รหัสครุภัณฑ์ประจำเครื่อง (unique)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_code` (`serial_code`),
  KEY `equipment_id` (`equipment_id`),
  CONSTRAINT `equipment_serials_ibfk_1`
    FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='รหัสครุภัณฑ์ประจำเครื่องแต่ละชิ้น';
