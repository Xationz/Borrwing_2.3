# HANDOVER.md — Borrowing System v2

## สถานะงาน: ✅ เสร็จสมบูรณ์

## สิ่งที่แก้แล้ว

### 1. ✅ ปรับเมนู Admin Sidebar
- ลบเมนู Dashboard ออก
- เพิ่มเมนู "ยืนยันการคืน" 
- ลำดับ: จัดการแอดมิน → หมวดหมู่ → ครุภัณฑ์ → ยืนยันการคืน → ปฏิทินการยืม → รายงานการยืม → ออกจากระบบ

### 2. ✅ ระบบ Return Approval Workflow (3 สถานะ)
- `borrowing` = กำลังยืม (เมื่อยืม)
- `waiting_return_approval` = รอการยืนยันคืน (เมื่อผู้ใช้กดแจ้งคืน)
- `returned` = คืนสำเร็จ (เมื่อ Admin ยืนยัน)

### 3. ✅ หน้า "ยืนยันการคืน" (partials/partial_return_approval.php)
- ใหม่ทั้งหมด
- แสดง filter tabs: ทั้งหมด / รอยืนยัน / กำลังยืม / คืนแล้ว
- ปุ่ม "ยืนยันการคืน" สำหรับรายการที่รอ
- บันทึก actual_return_date, approved_by_admin, approved_return_at
- เพิ่มสต๊อกกลับเมื่อ Admin ยืนยัน

### 4. ✅ แก้ User Dashboard
- ปุ่มคืนเปลี่ยนเป็น "แจ้งคืน" 
- Dialog อธิบาย "รอเจ้าหน้าที่ตรวจสอบ"
- แสดง 3 สถานะ: กำลังยืม / รอเจ้าหน้าที่ตรวจสอบ / คืนสำเร็จ

### 5. ✅ แก้ Dashboard Analytics (partial_borrowing_dashboard.php)
- เพิ่ม Top 10 ผู้ใช้, หน่วยงาน, ครุภัณฑ์
- แสดงจำนวนรายการตามสถานะ
- กราฟ Column + Bar chart

### 6. ✅ Navigation SPA
- spa_shell.php เป็น entry point หลัก
- login.php redirect ไป spa_shell.php
- Sidebar render ครั้งเดียว, เปลี่ยนแค่ content area

### 7. ✅ Database Migration
- ไฟล์: Database/migration_v2.sql
- เพิ่ม columns: returned_request_at, approved_return_at, approved_by_admin, actual_return_date
- ขยาย ENUM status

## ไฟล์ที่แก้ไข
| ไฟล์ | การเปลี่ยนแปลง |
|------|----------------|
| spa_shell.php | ลบ admin_dashboard menu, เพิ่ม return_approval, เปลี่ยน default page |
| sidebar.php | อัพเดตเมนู Admin, ลิงก์ไป spa_shell.php |
| login.php | redirect ไป spa_shell.php |
| partials/partial_user_dashboard.php | Workflow แจ้งคืน, แสดง 3 สถานะ |
| partials/partial_return_approval.php | ใหม่ทั้งหมด |
| partials/partial_borrowing_dashboard.php | เพิ่ม analytics dashboard |
| partials/partial_admin_dashboard.php | เพิ่ม waiting_return_approval stat |
| Database/migration_v2.sql | SQL migration ใหม่ |

## การ Deploy
1. รัน `Database/migration_v2.sql` บน MySQL/MariaDB
2. Upload ไฟล์ที่แก้ไขทั้งหมด
3. ทดสอบ login → spa_shell.php

## Known Issues / ความเสี่ยง
- หาก DB ไม่รองรับ `ADD COLUMN IF NOT EXISTS` (MariaDB < 10.3) ให้รัน migration แยก
- Admin Dashboard เก่า (admin_dashboard.php) ยังมีอยู่แต่ไม่ได้ใช้ใน SPA flow

---

# อัปเดตรอบล่าสุด (v3)

## สิ่งที่แก้แล้ว

### 1. ✅ จัดการครุภัณฑ์ → เพิ่มครุภัณฑ์ใหม่
- เปลี่ยน label "คำอธิบาย" เป็น "แสดงรหัสครุภัณฑ์" (เฉพาะในฟอร์มเพิ่มครุภัณฑ์ใหม่ — ตาราง/ฟอร์มแก้ไขยังใช้ "คำอธิบาย" เหมือนเดิม)

### 2. ✅ User → ครุภัณฑ์ที่พร้อมให้ยืม
- แต่ละการ์ดแสดง: รูปภาพ, ชื่อครุภัณฑ์, รหัสครุภัณฑ์ (ที่สถานะ available), จำนวนคงเหลือ
- ถ้ามีรหัสมากกว่า 1 รายการ จะมีปุ่ม "+N รหัส" เปิด modal ดูรายการรหัสทั้งหมด (รูปแบบเดียวกับหน้า Admin)
- เอาแถบหมวดหมู่ที่เคยแสดงบนการ์ดออก (data-cat attribute ยังเก็บไว้ใช้กับฟีเจอร์เลือกหลายรายการเหมือนเดิม)

### 3. ✅ ปฏิทินการยืม Admin ↔ User ใช้ข้อมูลชุดเดียวกัน Real-time
- **บั๊กที่พบ:** `partials/partial_calendar.php` เรียก `events: '../fetch_borrowings.php'` ซึ่ง path ผิด (เพราะ JS รันในบริบทของ spa_shell.php ที่อยู่ระดับเดียวกับ fetch_borrowings.php ไม่ใช่ใน partials/) ทำให้ปฏิทิน Admin ไม่เคยโหลดข้อมูลได้จริง — แก้เป็น `'fetch_borrowings.php'`
- ปฏิทินในแบบฟอร์มขอยืม (User) เปลี่ยนจากใช้ snapshot ที่ฝังมาตอนโหลดหน้า (`CAL_EVENTS`) เป็นดึงสดจาก `fetch_borrowings.php` (endpoint เดียวกับ Admin) ทุกครั้งที่เปิด modal/เปลี่ยนเดือน
- ลบ query/JSON `$cal_events` ที่ไม่ใช้แล้วออกจาก `partials/partial_user_dashboard.php`
- เพิ่ม session guard ใน `fetch_borrowings.php` (ต้อง login ก่อน ทั้ง admin และ user) เพราะตอนนี้ฝั่ง user เรียกใช้ endpoint นี้ด้วย

## ไฟล์ที่แก้ไขรอบนี้
| ไฟล์ | การเปลี่ยนแปลง |
|------|----------------|
| partials/partial_equipment.php | เปลี่ยน label "คำอธิบาย" → "แสดงรหัสครุภัณฑ์" ในฟอร์มเพิ่มครุภัณฑ์ใหม่ |
| partials/partial_user_dashboard.php | เพิ่มแสดงรหัสครุภัณฑ์ + modal บนการ์ด, เปลี่ยนปฏิทินไปใช้ fetch_borrowings.php, ลบ $cal_events ที่ไม่ใช้แล้ว |
| partials/partial_calendar.php | แก้ path `'../fetch_borrowings.php'` → `'fetch_borrowings.php'` |
| fetch_borrowings.php | เพิ่ม session guard (ต้อง login) |
| spa_shell.php | เพิ่ม CSS `.badge-code` / `.badge-code-more` สำหรับการ์ดครุภัณฑ์ |

## Known Issues / ความเสี่ยงที่ยังไม่แก้ (อยู่นอกขอบเขตงานรอบนี้)
- `equipment.quantity` ไม่ถูกลดอัตโนมัติตอนยืม (ลดเฉพาะตอน Admin ยืนยันคืนจะ +กลับ) ทำให้ "คงเหลือ" บนการ์ดอาจไม่ตรงกับจำนวนรหัสครุภัณฑ์ที่ status=available จริง ๆ — ของเดิมเป็นแบบนี้อยู่แล้วก่อนรอบนี้ ไม่ได้แก้เพราะอยู่นอก scope ที่ขอมา
- `partials/partial_admin_dashboard.php` มี path bug แบบเดียวกัน (`'../fetch_borrowings.php'`) แต่หน้านี้ไม่ได้อยู่ใน allowed pages ของ spa_shell.php แล้ว (unreachable) จึงไม่ได้แก้
