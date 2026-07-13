<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งซ่อม - คณะการบัญชีและการจัดการ MSU</title>
    <!-- ใช้ Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ใช้ Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Kanit', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="p-4">
    <div class="container max-w-lg mx-auto">
        <div class="card p-4">
            <h2 class="text-2xl font-bold text-center text-blue-800 mb-4">แจ้งซ่อมอุปกรณ์</h2>
            <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
                <!-- ข้อมูลผู้แจ้ง (ดึงจาก LIFF) -->
                <div class="mb-3">
                    <label class="form-label">ชื่อ-นามสกุล (ผู้แจ้ง)</label>
                    <input type="text" name="reporter_name" class="form-control" required placeholder="ระบุชื่อจริง">
                </div>

                <!-- เลือกประเภทอุปกรณ์ -->
                <div class="mb-3">
                    <label class="form-label">ประเภทอุปกรณ์</label>
                    <select name="equipment_type" class="form-select" id="equipSelect" onchange="checkOther()">
                        <option value="แอร์">แอร์</option>
                        <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                        <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                        <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                        <option value="ไมค์">ไมค์</option>
                        <option value="other">อื่นๆ (ระบุ...)</option>
                    </select>
                    <input type="text" name="other_equip" id="otherInput" class="form-control mt-2 hidden" placeholder="ระบุชื่ออุปกรณ์">
                </div>

                <!-- เลือกตึกและระบุห้อง -->
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">ตึก</label>
                        <select name="building" class="form-select">
                            <option value="SBB">SBB</option>
                            <option value="ACC.BIZ">ACC.BIZ</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">เลขห้อง</label>
                        <input type="text" name="room_no" class="form-control" placeholder="เช่น 303" required>
                    </div>
                </div>

                <!-- เบอร์ติดต่อ -->
                <div class="mb-3">
                    <label class="form-label">เบอร์ติดต่อกลับ</label>
                    <input type="tel" name="phone_number" class="form-control" required>
                </div>

                <!-- อาการเสีย -->
                <div class="mb-3">
                    <label class="form-label">อาการเสีย</label>
                    <textarea name="problem_desc" class="form-control" rows="3" required></textarea>
                </div>

                <!-- แนบภาพ -->
                <div class="mb-3">
                    <label class="form-label">แนบภาพประกอบ (ถ้ามี)</label>
                    <input type="file" name="image_before" class="form-control" accept="image/*">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-3">ส่งรายการแจ้งซ่อม</button>
            </form>
        </div>
    </div>

    <script>
        function checkOther() {
            const select = document.getElementById('equipSelect');
            const input = document.getElementById('otherInput');
            input.classList.toggle('hidden', select.value !== 'other');
        }
    </script>
</body>
</html>