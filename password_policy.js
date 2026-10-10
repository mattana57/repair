// ตรวจหน้าเว็บตามค่าที่ PHP ส่งมา; PHP ต้องตรวจซ้ำก่อนบันทึกเสมอ
(function (root) {
    'use strict';
    function rules(password, policy) {
        return {
            length: Array.from(password).length >= policy.min_length,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/.test(password),
            bytes: new TextEncoder().encode(password).length <= policy.max_bytes,
            printable: !/[\x00-\x1F\x7F]/.test(password),
            common: password !== '' && !policy.common_passwords.includes(password.toLowerCase())
        };
    }
    function error(password, policy) {
        if (typeof password !== 'string' || password === '') {
            return 'กรุณากรอกรหัสผ่านเป็นข้อความที่ถูกต้อง';
        }
        const result = rules(password, policy);
        const messages = {
            length: 'รหัสผ่านต้องมีอย่างน้อย ' + policy.min_length + ' ตัวอักษร',
            uppercase: 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่ A–Z อย่างน้อย 1 ตัว',
            lowercase: 'รหัสผ่านต้องมีตัวพิมพ์เล็ก a–z อย่างน้อย 1 ตัว',
            number: 'รหัสผ่านต้องมีตัวเลข 0–9 อย่างน้อย 1 ตัว',
            special: 'รหัสผ่านต้องมีอักขระพิเศษ เช่น _ . ! @ # $ % & * อย่างน้อย 1 ตัว',
            bytes: 'รหัสผ่านต้องไม่เกิน ' + policy.max_bytes + ' ไบต์',
            printable: 'รหัสผ่านต้องไม่มีอักขระควบคุม เช่น ขึ้นบรรทัดใหม่',
            common: 'กรุณาใช้รหัสผ่านอื่นที่ไม่ใช่รหัสตัวอย่างที่เดาง่าย'
        };
        for (const key of Object.keys(messages)) {
            if (!result[key]) return messages[key];
        }
        return null;
    }
    root.PasswordPolicy = Object.freeze({ rules: rules, error: error });
})(window);
