#!/usr/bin/env python3
"""
pdf_generator.py
สร้าง PDF ยืมครุภัณฑ์โดยวางข้อมูลทับบน Template PDF
Usage: python3 pdf_generator.py <input_json> <output_pdf>
"""

import sys
import json
import io
import os

try:
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    from reportlab.pdfgen import canvas
    from pypdf import PdfReader, PdfWriter
except ImportError as e:
    print(f"Missing library: {e}", file=sys.stderr)
    print("Run: pip3 install reportlab pypdf", file=sys.stderr)
    sys.exit(1)

# ── Font: หา TTF ที่รองรับภาษาไทย (รองรับทั้ง macOS / Linux / Windows) ──────
FONT_NAME = 'ThaiFont'

FONT_CANDIDATES = [
    # macOS — fonts ที่ติดมากับระบบ
    '/Library/Fonts/Arial Unicode.ttf',
    '/Library/Fonts/Tahoma.ttf',
    '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
    '/System/Library/Fonts/Supplemental/Tahoma.ttf',
    '/Library/Fonts/Microsoft/Tahoma.ttf',
    # Linux
    '/usr/share/fonts/truetype/freefont/FreeSerif.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.otf',
    # Windows
    'C:/Windows/Fonts/tahoma.ttf',
    'C:/Windows/Fonts/arial.ttf',
]

# ลอง bundle font ที่วางไว้ข้างๆ script ก่อนเลย (สำหรับ deploy พกพา)
_script_dir = os.path.dirname(os.path.abspath(__file__))
FONT_CANDIDATES = [
    os.path.join(_script_dir, 'fonts', 'THSarabunNew.ttf'),
    os.path.join(_script_dir, 'fonts', 'NotoSansThai-Regular.ttf'),
    os.path.join(_script_dir, 'fonts', 'FreeSerif.ttf'),
    os.path.join(_script_dir, 'THSarabunNew.ttf'),
] + FONT_CANDIDATES

def find_and_register_font():
    for path in FONT_CANDIDATES:
        if os.path.isfile(path):
            try:
                pdfmetrics.registerFont(TTFont(FONT_NAME, path))
                print(f"[font] Using: {path}", file=sys.stderr)
                return True
            except Exception as e:
                print(f"[font] Skip {path}: {e}", file=sys.stderr)
                continue
    return False

if not find_and_register_font():
    print("ERROR: ไม่พบ font ที่รองรับภาษาไทย", file=sys.stderr)
    print("วิธีแก้: วาง THSarabunNew.ttf ไว้ในโฟลเดอร์ project01/", file=sys.stderr)
    print("ดาวน์โหลด: https://www.f0nt.com/release/th-sarabun-new/", file=sys.stderr)
    sys.exit(1)

# ── Thai helpers ──────────────────────────────────────────────────────────────
THAI_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
               'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']

def parse_thai_date(date_str: str):
    try:
        parts = date_str.split('-')
        y, m, d = int(parts[0]), int(parts[1]), int(parts[2])
        if y < 2500:
            y += 543
        return str(d), THAI_MONTHS[m], str(y)
    except Exception:
        return '', '', ''

PURPOSE_MAP = {
    'teaching': 'การเรียนการสอน',
    'meeting':  'จัดประชุม',
    'training': 'จัดอบรม',
    'project':  'จัดโครงการ',
}

PDF_W, PDF_H = 596.04, 843.0

# ═══════════════════════════════════════════════════════════════════════════════
# 🗺️  FIELD POSITION CONFIG — ปรับตำแหน่ง x,y ของแต่ละช่องที่นี่ที่เดียว
#
#  พิกัดระบบ PDF: (0,0) = มุมล่างซ้าย, x เพิ่มไปทางขวา, y เพิ่มขึ้นข้างบน
#  หน่วย: points (1 นิ้ว = 72 pt)  ขนาดหน้า A4 = 596 x 843 pt
#
#  วิธีปรับ:
#   - เพิ่ม x  → ขยับไปทางขวา      - ลด x → ขยับไปทางซ้าย
#   - เพิ่ม y  → ขยับขึ้นข้างบน    - ลด y → ขยับลงข้างล่าง
# ═══════════════════════════════════════════════════════════════════════════════

FIELD_POS = {
    # --- วันที่ออกเอกสาร (มุมบนขวา) ---
    'doc_date':        (477, 642),

    # --- ข้อมูลผู้ยืม บรรทัด 1: ชื่อ / นามสกุล / ตำแหน่ง ---
    'fname':           (190, 616),
    'lname':           (282, 616),
    'position':        (400, 616),

    # --- ข้อมูลผู้ยืม บรรทัด 2: หน่วยสังกัด / เบอร์โทร / อีเมล ---
    'unit':            (100, 597),
    'phone':           (330, 597),
    'email':           (425, 597),

    # --- ประเภทครุภัณฑ์: ตำแหน่ง checkbox ☑ แต่ละประเภท (x คงที่, y ต่างกัน) ---
    'eq_checkbox_x':   222.5,       # x ของเครื่องหมาย ☑
    'eq_qty_x':        330,         # x ของจำนวน
    'eq_other_text_x': 260,         # x ของข้อความ อื่นๆ
    'eq_y': {
        'notebook': 579,            # Notebook   ← ปรับ y ของแต่ละแถวที่นี่
        'pc':       563,            # PC
        'aio':      548,            # AIO
        'printer':  533,            # Printer
        'other':    524,            # อื่นๆ
    },

    # --- รหัสครุภัณฑ์ (serial) ---
    # กรณีเครื่องเดียว: วางข้างจำนวน
    'serial_single_x': 365,         # x ของ serial เครื่องเดียว
    # กรณีหลายเครื่อง: วางซ้อนกันลงมา (บรรทัดละ 12 pt)
    'serial_multi_x':  434,         # x ของ serial หลายเครื่อง
    'serial_line_gap': 12,          # ระยะห่างระหว่างบรรทัด serial

    # --- วัตถุประสงค์ ---
    'purpose':         (60, 505),

    # --- วันที่ยืม: วัน / เดือน / ปี ---
    'borrow_d':        (132, 488),
    'borrow_m':        (189, 488),
    'borrow_y':        (275, 488),

    # --- วันที่คืน: วัน / เดือน / ปี ---
    'return_d':        (389, 488),
    'return_m':        (443, 488),
    'return_y':        (520, 488),

    # --- ลายเซ็น / ชื่อผู้ขอใช้บริการ ---
    'sign_name':       (380, 433),
    'sign_unit':       (360, 415),
}

# ═══════════════════════════════════════════════════════════════════════════════

def make_overlay(data: dict) -> io.BytesIO:
    packet = io.BytesIO()
    c = canvas.Canvas(packet, pagesize=(PDF_W, PDF_H))
    P = FIELD_POS   # shorthand

    eq_type    = data.get('equipment_type', '').lower()
    full_name  = data.get('borrower_name', '').strip()
    name_parts = full_name.split(' ', 1)
    fname      = name_parts[0]
    lname      = name_parts[1] if len(name_parts) > 1 else ''

    # วันที่ออกเอกสาร (บนขวา)
    c.setFont(FONT_NAME, 9)
    c.drawString(*P['doc_date'], data.get('borrow_date_text', ''))

    # Row 1: ชื่อ / นามสกุล / ตำแหน่ง
    c.setFont(FONT_NAME, 10)
    c.drawString(*P['fname'],    fname)
    c.drawString(*P['lname'],    lname)
    c.drawString(*P['position'], data.get('position_text', '')[:25])

    # Row 2: หน่วยสังกัด / เบอร์โทร / อีเมล
    unit  = data.get('borrower_unit', '')
    phone = data.get('borrower_phone', '')
    email = data.get('borrower_email', '')
    c.setFont(FONT_NAME, 9)
    c.drawString(*P['unit'],  unit[:45])
    c.drawString(*P['phone'], phone[:15])
    c.setFont(FONT_NAME, 8)
    c.drawString(*P['email'], email[:25])

    # Checkbox ประเภทครุภัณฑ์ + จำนวน
    eq_y_map = P['eq_y']
    c.setFont(FONT_NAME, 10)
    if eq_type in eq_y_map:
        eq_y = eq_y_map[eq_type]
        c.drawString(P['eq_checkbox_x'], eq_y, '☑')
        c.drawString(P['eq_qty_x'],      eq_y, str(data.get('borrow_quantity', '1')))
        if eq_type == 'other':
            c.drawString(P['eq_other_text_x'], eq_y, data.get('equipment_type_other', '')[:40])

    # รหัสครุภัณฑ์ (serial)
    serial_text  = data.get('equipment_serial_text', '')
    serial_lines = data.get('equipment_serial_lines') or []
    if not serial_lines and serial_text:
        serial_lines = [line.strip() for line in str(serial_text).splitlines() if line.strip()]

    if serial_lines:
        c.setFont(FONT_NAME, 8)
        start_y = eq_y_map.get(eq_type, 579)
        if len(serial_lines) == 1 and ':' not in serial_lines[0]:
            c.drawString(P['serial_single_x'], start_y, ('เลขครุภัณฑ์ ' + serial_lines[0])[:38])
        else:
            for i, line in enumerate(serial_lines[:4]):
                c.drawString(P['serial_multi_x'], start_y - (i * P['serial_line_gap']), line[:42])

    # วัตถุประสงค์
    purpose_key  = data.get('purpose', '')
    purpose_text = PURPOSE_MAP.get(purpose_key, purpose_key)
    c.setFont(FONT_NAME, 9)
    c.drawString(*P['purpose'], purpose_text[:50])

    # วันที่ยืม + วันที่คืน
    bd, bm, by = parse_thai_date(data.get('borrow_date', ''))
    rd, rm, ry = parse_thai_date(data.get('return_date_planned', ''))
    c.setFont(FONT_NAME, 9)
    c.drawString(*P['borrow_d'], bd)
    c.drawString(*P['borrow_m'], bm)
    c.drawString(*P['borrow_y'], by)
    c.drawString(*P['return_d'], rd)
    c.drawString(*P['return_m'], rm)
    c.drawString(*P['return_y'], ry)

    # ลายเซ็น
    c.setFont(FONT_NAME, 9)
    c.drawString(*P['sign_name'], full_name)
    c.setFont(FONT_NAME, 8)
    c.drawString(*P['sign_unit'], unit[:40])

    c.save()
    packet.seek(0)
    return packet


def generate_pdf(data: dict, template_path: str, output_path: str) -> None:
    overlay_bytes = make_overlay(data)
    template_pdf  = PdfReader(template_path)
    overlay_pdf   = PdfReader(overlay_bytes)
    writer        = PdfWriter()
    page          = template_pdf.pages[0]
    page.merge_page(overlay_pdf.pages[0])
    writer.add_page(page)
    with open(output_path, 'wb') as f:
        writer.write(f)


if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("Usage: python3 pdf_generator.py <input.json> <output.pdf>", file=sys.stderr)
        sys.exit(1)

    json_path   = sys.argv[1]
    output_path = sys.argv[2]

    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(f"Cannot read JSON: {e}", file=sys.stderr)
        sys.exit(1)

    template_path = data.get('template_path', '')
    if not template_path:
        print("Missing 'template_path' in JSON", file=sys.stderr)
        sys.exit(1)

    try:
        generate_pdf(data, template_path, output_path)
        print(f"PDF created: {output_path}")
    except Exception as e:
        print(f"PDF generation error: {e}", file=sys.stderr)
        sys.exit(1)
