<?php
/** Borrow wizard modal — 4-step pattern */
if (!isset($BORROWER_UNITS)) require_once dirname(__DIR__) . '/org_units.php';
$today = date('Y-m-d');
?>
<div class="modal fade" id="borrowWizardModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>แบบฟอร์มขอยืมครุภัณฑ์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>

            <div class="wizard-steps" id="wizardStepsBar">
                <div class="wizard-step active" id="wstep1">
                    <span class="wizard-step__num">1</span>
                    <span>เลือกครุภัณฑ์และวันที่</span>
                </div>
                <div class="wizard-step__line"></div>
                <div class="wizard-step" id="wstep2">
                    <span class="wizard-step__num">2</span>
                    <span>ข้อมูลผู้ยืม</span>
                </div>
                <div class="wizard-step__line"></div>
                <div class="wizard-step" id="wstep3">
                    <span class="wizard-step__num">3</span>
                    <span>ตรวจสอบข้อมูล</span>
                </div>
                <div class="wizard-step__line"></div>
                <div class="wizard-step" id="wstep4">
                    <span class="wizard-step__num">4</span>
                    <span>ส่งคำขอสำเร็จ</span>
                </div>
            </div>

            <form method="POST" id="borrowWizardForm" novalidate>
                <input type="hidden" name="borrow_equipment" value="1">
                <input type="hidden" name="equipment_id" id="wiz_equipment_id">
                <input type="hidden" name="it_install" id="wiz_it_install" value="">
                <div id="wiz_serial_inputs"></div>
                <div id="wiz_multi_equip_inputs"></div>

                <div class="modal-body">
                    <!-- Step 1 -->
                    <div class="wizard-panel active" id="wizPage1">
                        <div class="wizard-banner" id="wiz_equip_banner">
                            <i class="bi bi-laptop"></i>
                            <div>
                                <div class="wizard-banner__title" id="wiz_equip_name_display">-</div>
                                <div class="wizard-banner__sub" id="wiz_equip_qty_span">คงเหลือ: <span id="wiz_equip_max_display">-</span> ชิ้น</div>
                                <div id="wiz_multi_equip_list" class="d-none mt-2"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section__title"><i class="bi bi-upc-scan"></i> รหัสครุภัณฑ์ <span class="required">*</span></div>
                            <p class="form-helper">เลือกได้หลายรายการ — แสดงเฉพาะที่พร้อมให้ยืม</p>
                            <div class="serial-list" id="wiz_serial_list">
                                <span class="text-muted" id="wiz_serial_placeholder">กรุณากดปุ่มยืมจากการ์ดครุภัณฑ์ก่อน</span>
                            </div>
                            <p class="form-helper mt-2">เลือกแล้ว: <strong id="wiz_selected_count">0</strong> รายการ</p>
                        </div>

                        <div class="form-section">
                            <div class="form-section__title"><i class="bi bi-calendar-range"></i> วันที่ยืม และ วันที่กำหนดคืน</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="wiz_borrow_date">วันที่ยืม <span class="required">*</span></label>
                                    <input type="date" name="borrow_date" id="wiz_borrow_date" class="form-control" min="<?= $today ?>" required>
                                    <p class="form-helper">เลือกวันเริ่มต้นการยืม</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_return_date">วันที่กำหนดคืน <span class="required">*</span></label>
                                    <input type="date" name="return_date_planned" id="wiz_return_date" class="form-control" required>
                                    <p class="form-helper">วันคืนต้องไม่น้อยกว่าวันที่ยืม</p>
                                </div>
                            </div>
                            <div id="wiz_day_badge" class="d-none mt-2">
                                <span class="day-badge"><i class="bi bi-calendar-check"></i> <span id="wiz_day_num">0</span> วัน</span>
                            </div>
                            <div class="alert-conflict hidden" id="wiz_conflict_alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="wiz_conflict_msg"></span>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section__title"><i class="bi bi-calendar3"></i> ปฏิทินการยืม</div>
                            <p class="form-helper">คลิกหรือลากบนปฏิทินเพื่อเลือกช่วงวันยืม–คืน</p>
                            <div id="wiz_calendar" class="calendar-container"></div>
                            <div class="cal-legend" id="wiz_calendar_legend"></div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="wizard-panel" id="wizPage2">
                        <div class="form-section">
                            <div class="form-section__title"><i class="bi bi-person-badge"></i> ข้อมูลผู้ยืม</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="wiz_borrower_name">ชื่อ-นามสกุลผู้ยืม <span class="required">*</span></label>
                                    <input type="text" name="borrower_name" id="wiz_borrower_name" class="form-control" placeholder="กรอกชื่อ-นามสกุล" required>
                                    <p class="form-error">กรุณากรอกชื่อ-นามสกุลผู้ยืม</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_equipment_type_select">ประเภทการยืม <span class="required">*</span></label>
                                    <select name="equipment_type" id="wiz_equipment_type_select" class="form-select" required>
                                        <option value="">เลือกประเภท</option>
                                        <option value="notebook">Notebook</option>
                                        <option value="other">อื่นๆ</option>
                                    </select>
                                    <p class="form-error">กรุณาเลือกประเภทการยืม</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_borrower_position">ตำแหน่งผู้ยืม <span class="required">*</span></label>
                                    <select name="borrower_position" id="wiz_borrower_position" class="form-select" required>
                                        <option value="">เลือกตำแหน่ง</option>
                                        <option value="แพทย์">แพทย์</option>
                                        <option value="บุคลากรสายวิชาชีพ">บุคลากรสายวิชาชีพ</option>
                                        <option value="บุคลากรสายสนับสนุน">บุคลากรสายสนับสนุน</option>
                                        <option value="นิสิต">นิสิต</option>
                                        <option value="บุคคลภายนอก">บุคคลภายนอก</option>
                                        <option value="อื่นๆ">อื่นๆ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_purpose">เหตุผลการใช้งาน <span class="required">*</span></label>
                                    <select name="purpose" id="wiz_purpose" class="form-select" required>
                                        <option value="">เลือกเหตุผล</option>
                                        <option value="การเรียนการสอน">การเรียนการสอน</option>
                                        <option value="จัดประชุม">จัดประชุม</option>
                                        <option value="จัดอบรม">จัดอบรม</option>
                                        <option value="อื่นๆ">อื่นๆ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_borrower_unit">หน่วยงานที่สังกัด <span class="required">*</span></label>
                                    <select name="borrower_unit" id="wiz_borrower_unit" class="form-select" required>
                                        <option value=""></option>
                                        <?php foreach ($BORROWER_UNITS as $u): ?>
                                        <option value="<?= htmlspecialchars($u['label']) ?>"><?= htmlspecialchars($u['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="form-helper">พิมพ์เพื่อค้นหาหน่วยงาน</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_it_install_select">ติดตั้งโดย IT <span class="required">*</span></label>
                                    <select id="wiz_it_install_select" class="form-select" required>
                                        <option value="">เลือก</option>
                                        <option value="1">ต้องการ</option>
                                        <option value="0">ไม่ต้องการ</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_phone">เบอร์ภายใน</label>
                                    <input type="text" name="borrower_phone" id="wiz_phone" class="form-control" maxlength="6" pattern="[0-9]{4,6}" inputmode="numeric" placeholder="1234">
                                    <p class="form-helper">4-6 หลัก (ไม่บังคับ)</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_email">อีเมล</label>
                                    <input type="email" name="borrower_email" id="wiz_email" class="form-control" placeholder="example@domain.com">
                                    <p class="form-helper">จะถูกบันทึกในเอกสารคำขอ</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_location">สถานที่ใช้งาน <span class="required">*</span></label>
                                    <input type="text" name="use_location" id="wiz_location" class="form-control" placeholder="เช่น ห้อง 301 อาคาร A" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz_serial_code_display">รหัสครุภัณฑ์</label>
                                    <input type="text" id="wiz_serial_code_display" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="wizard-panel" id="wizPage3">
                        <div class="form-section">
                            <div class="form-section__title"><i class="bi bi-check2-square"></i> สรุปคำขอยืม</div>
                            <div class="summary-grid" id="wiz_summary"></div>
                        </div>
                        <div class="alert-info-box">
                            <i class="bi bi-info-circle me-2"></i>
                            เมื่อกด <strong>ส่งคำขอ</strong> รายการจะถูกบันทึกเป็นสถานะ <strong>กำลังยืม</strong>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="wizard-panel" id="wizPage4">
                        <div class="wizard-success">
                            <div class="wizard-success__icon"><i class="bi bi-check-lg"></i></div>
                            <h3 class="wizard-success__title">ส่งคำขอสำเร็จ!</h3>
                            <p class="wizard-success__desc">ระบบบันทึกรายการยืมครุภัณฑ์เรียบร้อยแล้ว คุณสามารถตรวจสอบสถานะได้ที่ประวัติการยืม</p>
                            <button type="button" class="btn btn--primary spa-link" data-page="borrow_history" data-bs-dismiss="modal">ดูประวัติการยืม</button>
                        </div>
                    </div>
                </div>

                <div class="wizard-footer" id="wizardFooter">
                    <button type="button" class="btn btn--ghost d-none" id="wiz_btn_prev"><i class="bi bi-arrow-left"></i> ย้อนกลับ</button>
                    <button type="button" class="btn btn--secondary" data-bs-dismiss="modal" id="wiz_btn_draft">บันทึกร่าง</button>
                    <div class="wizard-footer__actions">
                        <button type="button" class="btn btn--primary" id="wiz_btn_next">ถัดไป <i class="bi bi-arrow-right"></i></button>
                        <button type="submit" class="btn btn--success d-none" id="wiz_btn_submit">
                            <span id="wiz_submit_icon"><i class="bi bi-send"></i></span>
                            <span id="wiz_submit_text">ส่งคำขอ</span>
                            <span id="wiz_submit_spinner" class="spinner-border spinner-border-sm ms-1 d-none"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="equipCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upc-scan me-2"></i><span id="equipCodeModalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipCodeModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
