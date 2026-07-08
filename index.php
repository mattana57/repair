<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบแจ้งซ่อมคณะการบัญชีและการจัดการ (MBS)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>@import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap'); body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-6">

    <div class="max-w-lg w-full text-center space-y-8">
        <!-- Logo & Header -->
        <div class="space-y-2">
            <div class="w-24 h-24 bg-blue-600 rounded-3xl mx-auto flex items-center justify-center shadow-lg shadow-blue-200">
                <i class="fas fa-tools text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-slate-800">MBS Repair Service</h1>
            <p class="text-slate-500">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
        </div>

        <!-- Buttons -->
        <div class="space-y-4">
            <a href="report_form.html" class="block w-full bg-blue-600 text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-700 transition">
                <i class="fas fa-paper-plane mr-2"></i> แจ้งซ่อมออนไลน์
            </a>
            <a href="login.php" class="block w-full bg-white text-slate-700 font-bold py-4 rounded-2xl border border-slate-200 hover:bg-slate-50 transition">
                <i class="fas fa-lock mr-2"></i> เข้าสู่ระบบ (แอดมิน/ผู้บริหาร)
            </a>
        </div>
    </div>

</body>
</html>