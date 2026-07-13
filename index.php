<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSU - ระบบแจ้งซ่อมออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Kanit', sans-serif; }
        .header-bg { background: linear-gradient(135deg, #003399, #0055ff); }
        .card-custom { border-radius: 20px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #d1d5db; padding: 10px; }
        .btn-submit { background-color: #003399; border-radius: 10px; font-weight: 500; transition: 0.3s; }
        .btn-submit:hover { background-color: #002266; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="header-bg text-white text-center py-5 mb-4">
    <h1 class="text-3xl font-bold">ระบบแจ้งซ่อมคณะฯ</h1>
    <p class="opacity-80">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
</div>

<div class="container max-w-lg mx-auto pb-5">
    <div class="card card-custom p-4">
        <form action="submit_repair.php" method="POST" enctype="multipart/form-data">
            
            <div class="mb-4">
                <label class="form-label text-gray-700 font-medium">ชื่อ-นามสกุล (ผู้แจ้ง)</label>
                <input type="text" name="reporter_name" class="form-control" required placeholder="เช่น นายสมชาย ใจดี">
            </div>

            <div class="mb-4">
                <label class="form-label text-gray-700 font-medium">ประเภทอุปกรณ์</label>
                <select name="equipment_type" class="form-select" id="equipSelect" onchange="checkOther()">
                    <option value="แอร์">แอร์</option>
                    <option value="คอมพิวเตอร์">คอมพิวเตอร์</option>
                    <option value="จอภาพ/ทีวี">จอภาพ/ทีวี</option>
                    <option value="เครื่องปริ้น">เครื่องปริ้น</option>
                    <option value="ไมค์">ไมค์</option>
                    <option value="other">อื่นๆ (ระบุ...)</option>
                </select>
                <input type="text" name="other_equip" id="otherInput" class="form-control mt-2 hidden transition-all" placeholder="ระบุอุปกรณ์อื่นๆ">
            </div>

            <div class="row">
                <div class="col-6 mb-4">
                    <label class="form-label text-gray-700 font-medium">ตึก</label>
                    <select name="building" class="form-select">
                        <option value="SBB">SBB</option>
                        <option value="ACC.BIZ">ACC.BIZ</option>
                    </select>
                </div>
                <div class="col-6 mb-4">
                    <label class="form-label text-gray-700 font-medium">เลขห้อง</label>
                    <input type="text" name="room_no" class="form-control" placeholder="เช่น 303" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-gray-700 font-medium">เบอร์โทรศัพท์</label>
                <input type="tel" name="phone_number" class="form-control" required placeholder="08x-xxx-xxxx">
            </div>

            <div class="mb-4">
                <label class="form-label text-gray-700 font-medium">รายละเอียดอาการเสีย</label>
                <textarea name="problem_desc" class="form-control" rows="3" required placeholder="อธิบายปัญหาเบื้องต้น..."></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label text-gray-700 font-medium">แนบภาพประกอบ (ถ้ามี)</label>
                <input type="file" name="image_before" class="form-control" accept="image/*">
            </div>

            <button type="submit" class="btn btn-submit text-white w-100 py-3">ส่งรายการแจ้งซ่อม</button>
        </form>
    </div>
</div>

<script>
    function checkOther() {
        const select = document.getElementById('equipSelect');
        const input = document.getElementById('otherInput');
        if(select.value === 'other') {
            input.classList.remove('hidden');
            input.setAttribute('required', 'required');
        } else {
            input.classList.add('hidden');
            input.removeAttribute('required');
        }
    }
</script>

</body>
</html>