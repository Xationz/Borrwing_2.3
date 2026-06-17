# คู่มือการเชื่อมต่อ Google Sheet

## ปัญหาที่แก้ไข
เมื่อ กดบันทึกข้อมูลการยืมครุภัณฑ์ ข้อมูลในช่อง `borrower_email` (อีเมล) ไม่ถูกส่งไปยัง Google Sheet

## สาเหตุ
1. **Google Apps Script (`google_apps_script_borrow.gs`)** มีคอลัมน์ `borrower_email` ในอาร์เรย์ `COLUMNS` แล้ว แต่จำนวนฟิลด์ที่ตรวจสอบยังไม่ตรงกัน
2. **partial_user_dashboard.php** ไม่ได้ส่งค่า `borrower_email` ไปใน payload ของ Google Sheet

## การแก้ไขที่ทำ

### 1. แก้ไข `google_apps_script_borrow.gs`
- อาร์เรย์ `COLUMNS` มี `'borrower_email'` อยู่แล้ว (บรรทัดที่ 8)
- ปรับจำนวนฟิลด์ที่ตรวจสอบจาก 14 เป็น **15** (บรรทัดที่ 87):

```javascript
if (rowData.length !== 15) {
  throw new Error('rowData must contain exactly 15 fields (including borrower_email)');
}
```

### 2. แก้ไข `partials/partial_user_dashboard.php`
เพิ่ม `'borrower_email'` เข้าไปใน `$sheet_payloads` array (บรรทัดที่ 288):

```php
$sheet_payloads[] = [
    'borrower_name'       => $borrower_name,
    'borrower_position'   => $borrower_position,
    'borrower_unit'       => $borrower_unit,
    'borrower_phone'      => $borrower_phone,
    'borrower_email'      => $borrower_email,      // ← เพิ่มบรรทัดนี้
    'equipment_type'      => $equipment_type === 'other' && $equipment_type_other !== '' ? $equipment_type_other : ($equipment_type === 'notebook' ? 'Notebook' : $equipment_type),
    'borrow_quantity'     => $qty,
    'purpose'             => $purpose,
    'borrow_date'         => $borrow_date,
    'return_date_planned' => $return_date_planned,
    'borrow_days'         => borrow_days_count($borrow_date, $return_date_planned),
    'it_install'          => $it_install ? 'ต้องการ' : 'ไม่ต้องการ',
    'location'            => $use_location,
    'asset_code'          => $asset_code,
];
```

## ขั้นตอนการติดตั้งสำหรับผู้ใช้

### ขั้นตอนที่ 1: Deploy Google Apps Script
1. เปิด Google Sheet ที่ต้องการใช้
2. ไปที่ **Extensions** > **Apps Script**
3. คัดลอกโค้ดจาก `google_apps_script_borrow.gs` ทั้งหมดไปวาง
4. บันทึกโปรเจกต์
5. คลิก **Deploy** > **New deployment**
6. เลือกประเภทเป็น **Web app**
7. ตั้งค่า:
   - Description: `Borrowings Sync v3`
   - Execute as: **Me**
   - Who has access: **Anyone** (สำคัญมาก!)
8. คลิก **Deploy**
9. คัดลอก **Web app URL** ที่ได้

### ขั้นตอนที่ 2: อัปเดต config.php
เปิดไฟล์ `config.php` และอัปเดต URL:

```php
define('GOOGLE_APPS_SCRIPT_WEB_APP_URL', 'https://script.google.com/macros/s/YOUR_NEW_DEPLOYMENT_ID/exec');
```

แทนที่ `YOUR_NEW_DEPLOYMENT_ID` ด้วย Web app URL ที่ได้จากขั้นตอนที่ 1

### ขั้นตอนที่ 3: เตรียม Google Sheet
1. สร้าง Google Sheet ใหม่หรือใช้ที่มีอยู่
2. ตั้งชื่อ Sheet แรกว่า `Borrowings` (หรือปล่อยให้สคริปต์สร้างอัตโนมัติ)
3. สคริปต์จะสร้าง header row อัตโนมัติเมื่อมีการส่งข้อมูลครั้งแรก

### ขั้นตอนที่ 4: ทดสอบระบบ
1. เข้าสู่ระบบด้วยบัญชี user
2. กรอกแบบฟอร์มยืมครุภัณฑ์ให้ครบถ้วน **รวมถึงช่องอีเมล**
3. กดบันทึก
4. ตรวจสอบ:
   - PDF ถูกสร้างขึ้นและแสดงข้อมูลอีเมลถูกต้อง
   - Google Sheet มีแถวใหม่พร้อมข้อมูลอีเมลในคอลัมน์ `borrower_email`

## โครงสร้างคอลัมน์ใน Google Sheet
หลังจากแก้ไขแล้ว Google Sheet จะมีคอลัมน์ดังนี้ (รวม 15 คอลัมน์):

| ลำดับ | ชื่อคอลัมน์ | คำอธิบาย | ตรงกับฟิลด์ในแบบฟอร์ม |
|-------|------------|----------|----------------------|
| A | borrower_name | ชื่อ-นามสกุลผู้ยืม | ชื่อ- นามสกุล ผู้ยืม |
| B | borrower_position | ตำแหน่ง (ภาษาไทย) | ตำแหน่ง ผู้ยืม |
| C | borrower_unit | หน่วยสังกัด | หน่วยงานที่สังกัด |
| D | borrower_phone | เบอร์โทรศัพท์ | เบอร์ภายใน |
| E | **borrower_email** | **อีเมล** | **อีเมล** |
| F | equipment_type | ประเภทครุภัณฑ์ | มีความประสงค์จะขอยืม |
| G | borrow_quantity | จำนวนที่ยืม | จำนวนที่ยืม |
| H | purpose | เหตุผลในการยืม | เหตุผลในการยืม/การนำไปใช้งาน |
| I | borrow_date | วันที่ยืม | วันที่ยืม |
| J | return_date_planned | วันที่คาดว่าจะคืน | วันที่คืน |
| K | borrow_days | จำนวนวันที่ยืม | ยืมทั้งหมดจำนวนกี่วัน |
| L | it_install | ต้องการติดตั้ง IT หรือไม่ | ต้องการให้เจ้าหน้าที่ IT ดำเนินการติดตั้งครุภัณฑ์ที่จะยืมให้หรือไม่? |
| M | location | สถานที่ | สถานที่ |
| N | asset_code | รหัสครุภัณฑ์ | รหัสครุภัณฑ์ |

## การแก้ไขปัญหาที่พบบ่อย

### ปัญหา: CORS Error
**อาการ:** ได้ error เกี่ยวกับ CORS ใน console
**วิธีแก้:** ตรวจสอบว่า Deploy Web app ตั้งค่า "Who has access" เป็น **Anyone**

### ปัญหา: ข้อมูลไม่เข้า Google Sheet
**อาการ:** บันทึกใน DB ได้ แต่ Google Sheet ไม่มีข้อมูลใหม่
**วิธีแก้:**
1. ตรวจสอบว่า `GOOGLE_APPS_SCRIPT_WEB_APP_URL` ใน `config.php` ถูกต้อง
2. ตรวจสอบ error log ของ PHP: ระบบจะ log error ไว้หากการส่งข้อมูลไป Google Sheet ล้มเหลว
3. ทดสอบ Web App URL ด้วยเบราว์เซอร์ ควรได้ JSON response

### ปัญหา: คอลัมน์ไม่ตรงกัน
**อาการ:** ข้อมูลไปอยู่ในคอลัมน์ผิด
**วิธีแก้:** 
1. ลบ header row ใน Google Sheet ออก
2. ส่งข้อมูลใหม่เพื่อให้สคริปต์สร้าง header ที่ถูกต้อง

### ปัญหา: ได้ error "rowData must contain exactly 15 fields"
**อาการ:** ข้อมูลไม่เข้า Google Sheet และได้ error นี้
**วิธีแก้:** ตรวจสอบว่า `partial_user_dashboard.php` มีการส่งค่า `borrower_email` ใน `$sheet_payloads` แล้ว

## หมายเหตุสำคัญ
- การส่งข้อมูลไป Google Sheet เป็นการทำงานเสริม (optional) หากล้มเหลวจะไม่กระทบการบันทึกข้อมูลในฐานข้อมูลหลัก
- ระบบจะแสดงข้อความเตือนหากการส่งข้อมูลไป Google Sheet ล้มเหลว แต่ข้อมูลยังถูกบันทึกในฐานข้อมูล
- ต้อง redeploy Google Apps Script ทุกครั้งที่มีการแก้ไขโค้ดใน `google_apps_script_borrow.gs`
- หลังจาก redeploy แล้ว ต้องรอประมาณ 1-2 นาทีให้ Google update deployment

