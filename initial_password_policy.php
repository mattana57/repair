<?php
// Flag กำหนดโดยเซิร์ฟเวอร์เฉพาะบัญชีใหม่ที่แอดมินสร้าง ห้ามรับจาก POST
function initialPasswordPending($user) {
    return is_array($user) && in_array($user['initial_password_pending'] ?? null, [1, '1'], true);
}
function initialPasswordModeMatches($user, $context) {
    return is_array($context)
        && (initialPasswordPending($user) ? 1 : 0) === (int)($context['initial_password_pending'] ?? 0);
}
function initialPasswordEmailEligible($user, $email) {
    return is_array($user) && is_string($email) && $email !== ''
        && (initialPasswordPending($user)
            || (is_string($user['verified_email'] ?? null) && $user['verified_email'] === $email && !empty($user['email_verified_at'])));
}
