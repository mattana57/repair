# MBS Repair UI redesign

เปลี่ยนส่วนแสดงผลเป็นธีม Purple / Indigo / Blue โดยใช้ Tailwind v3 CDN เดิม
และ component stylesheet กลาง ไม่เพิ่มระบบ build หรือ dependency ของฝั่ง PHP

## ไฟล์และหน้าที่ครอบคลุม

- `dashboard.php`: Overview, Transactions, Team, Technician, Assets, Contacts, Reports, profile dropdown และ modal เดิม
- `executive_dashboard.php`: หน้าภาพรวมผู้บริหาร รายการแจ้งซ่อม ทำเนียบช่าง และ modal เดิม
- `index.php`, `login.php`: หน้าแรก การค้นหาสถานะ และเข้าสู่ระบบ
- `form_repair.php`, `view_repair.php`, `update_repair.php`: แจ้งซ่อม รายละเอียด และอัปเดตใบงาน
- `executive_report.php`, `executive_summary_report.php`, `print_report.php`: ส่วนควบคุมรายงานและการดูเอกสารบนจอ โดยแยกธีมออกจาก print styles
- `dashboard.html`, `executive_dashboard.html`, `index.html`, `report_form.html`: หน้า HTML เดิม ใช้ธีมร่วมกันโดยคงข้อมูลตัวอย่างและสคริปต์เดิม

`export_excel.php` เป็นไฟล์ส่งออก Excel จึงคงรูปแบบข้อมูลส่งออกเดิมไว้
ไฟล์รับข้อมูล Authentication, Session, API และ LINE ไม่ถูกแก้ไข

## Design system

- `assets/repair-ui.css`: entry point กลาง
- `assets/mbs-design-system.css`: สี Typography Sidebar Topbar KPI ตาราง ฟอร์ม ปุ่ม Badge Modal และ responsive rules
- Prefix `mbs-` เป็น presentation hook เพิ่มเติม ไม่มีการเปลี่ยนชื่อ ID หรือ class ที่เป็น hook เดิม
- Sidebar พื้นขาว 264px, active สีม่วงอ่อน และ Topbar สีขาว
- KPI เปลี่ยนเป็น 1 / 2 / 4 คอลัมน์ มีหัวข้อภาพรวมและคำอธิบายเพิ่มใน Dashboard ทั้งสองบทบาท
- ตารางเปลี่ยน header สีเหลืองเป็น slate และให้เลื่อนภายในตาราง แทนการบีบตัวอักษร/ซ่อนปุ่มบน Desktop
- ฟอร์มแจ้งซ่อมกว้างขึ้นและใช้ responsive grid
- Login เปลี่ยนเป็น split layout พื้นสีอ่อน
- ส่วนหัวแผนกเป็นพื้นขาว มีเส้น accent และ badge; ตัวการ์ดช่างคงขอบ/แสง Sky Blue พร้อมชื่อและปุ่มสีฟ้า
- ใช้ Prompt / Noto Sans Thai; เอกสาร A4 คง Sarabun
- รูปแบบพิมพ์เดิมอยู่นอก stylesheet ที่ใช้ `media="screen"`

## ตรวจสอบที่ทำแล้ว

รัน PHP lint กับหน้า PHP ทั้ง 10 ไฟล์ที่แก้ไขผ่าน

รัน:

```powershell
C:\xampp\php\php.exe tools/verify-ui.php
```

ตรวจทั้ง 14 ไฟล์เทียบกับ Git HEAD ก่อนการแก้ไข:

- PHP token ทุกส่วนเหมือนเดิม รวม SQL, Session, Authentication, API และ PHP ที่สร้าง HTML
- Script เดิมทั้งหมดเหมือนเดิม
- ID, name, value, type, href, src, action, method, enctype, for, data attributes และ event handlers เหมือนเดิม
- ข้อความเดิมเหมือนเดิม ยกเว้นการเพิ่มหัวข้อ/คำอธิบายภาพรวมที่ระบุไว้ด้านบน

ตรวจซ้ำกับสำเนาไฟล์ก่อนแก้บนเครื่องแบบ byte-exact ผ่านแล้วด้วย
เครื่องมือตรวจไม่โหลดหรือ execute โค้ดแอป และไม่เชื่อมต่อฐานข้อมูล

คำสั่งนี้ตรวจ uncommitted changes กับ HEAD; หลัง commit แล้วควรส่ง path ของ baseline เดิมเป็น argument หากต้องการเทียบกับก่อน redesign

## ขั้นตอนที่ยังตรวจรับไม่ได้

เครื่องมือ Browser ไม่พบ Chrome/Edge ที่เชื่อมต่อได้ และการเปิด `chrome` / `iab` ตอบกลับว่า browser unavailable
การตรวจ `http://localhost/repair/login.php` และ CSS ทั้งสองไฟล์ได้ HTTP 404
จาก Apache config พบว่า XAMPP ใช้พอร์ต 8080 แต่การเชื่อมต่อ `http://localhost:8080/repair/login.php` ไม่สำเร็จ
ผู้ใช้ระบุ URL จริงเป็น `http://103.99.11.147/repair/dashboard.php`; การอ่านหน้า Login และ CSS ที่โฮสต์นี้ไม่สำเร็จเช่นกัน และคำขอตรวจซ้ำนอก sandbox ไม่ได้รับอนุมัติ
ยังไม่ได้ยืนยันว่า URL จริงเสิร์ฟไฟล์จาก workspace นี้ และไม่ได้ deploy ไฟล์ไปยังเครื่องอื่น

ยังไม่ได้ยืนยันหน้าจอจริงที่ Desktop / Tablet / Mobile, interaction จริง และ CRUD แบบ end-to-end
การตรวจโค้ดด้านบนยืนยันการคงสัญญาการทำงานเดิม แต่ไม่ใช่หลักฐานว่าผ่าน live browser test

เมื่อเชื่อมต่อเว็บได้ ต้องตรวจรายการต่อไปนี้ก่อนรับงานว่าครบทั้งหมด:

1. Overview ทั้งสองบทบาท: KPI, กราฟ, ตัวเลือกเดือน/ปี, sidebar drawer และ profile dropdown
2. Transactions: Search, status filter, ปุ่มดูรายละเอียด/แก้ไข และ scroll ตาราง
3. Team/Technician: ฟิลเตอร์แผนก, search, modal, delete confirmation และสีฟ้าของการ์ดช่าง
4. Assets/Contacts: ตาราง ค้นหา modal และปุ่มเดิม
5. Login/Create/View/Edit: focus, validation, แนบภาพ และการแสดงผลมือถือ
6. Reports: ตัวกรอง, preview และ print layout; การดาวน์โหลด Excel เดิม
7. ตรวจซ้ำที่ 375px, 768px, 1440px และสถานะ empty / modal / dropdown
8. ทดสอบการบันทึกและ CRUD ในฐานข้อมูลทดสอบที่แยกจากข้อมูลจริงและการส่ง LINE

เอกสารอ้างอิง responsive utilities ที่ใช้: https://v3.tailwindcss.com/docs/responsive-design
