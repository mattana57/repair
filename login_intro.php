<?php
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('ไม่รองรับคำขอนี้');
}

$intro_role = strtolower((string) ($_SESSION['role'] ?? ''));

$intro_homes = [
    'admin' => 'dashboard.php',
    'executive' => 'executive_dashboard.php',
    'technician' => 'technician_home.php',
];

$intro_roles = [
    'admin' => 'ผู้ดูแลระบบ',
    'executive' => 'ผู้บริหาร',
    'technician' => 'เจ้าหน้าที่',
];

if (!isset($intro_homes[$intro_role])) {
    http_response_code(403);
    exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}

$intro_home = $intro_homes[$intro_role];
$intro_ticket = $_SESSION['login_intro'] ?? null;

// ใช้ข้อมูลแสดงอินโทรได้ครั้งเดียว
unset($_SESSION['login_intro']);

$intro_now = time();

$intro_valid = is_array($intro_ticket)
    && is_int($intro_ticket['user_id'] ?? null)
    && $intro_ticket['user_id'] === (int) $_SESSION['user_id']
    && is_int($intro_ticket['auth_version'] ?? null)
    && $intro_ticket['auth_version'] === (int) $_SESSION['auth_version']
    && ($intro_ticket['role'] ?? null) === $intro_role
    && is_int($intro_ticket['issued_at'] ?? null)
    && $intro_ticket['issued_at'] <= $intro_now
    && $intro_now - $intro_ticket['issued_at'] <= 120
    && is_string($intro_ticket['destination'] ?? null);

if (!$intro_valid) {
    header('Location: ' . $intro_home, true, 303);
    exit();
}

// รับปลายทางจาก Session ที่ auth.php กำหนดเท่านั้น
$intro_destination = $intro_ticket['destination'];
$intro_target_allowed = $intro_destination === $intro_home;

// รองรับการล็อกอินกลับใบงานเดิมจาก LINE
if (!$intro_target_allowed
    && preg_match(
        '/\Aupdate_repair\.php\?id=([1-9][0-9]*)\z/',
        $intro_destination,
        $intro_matches
    ) === 1) {
    $intro_target_allowed = filter_var(
        $intro_matches[1],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]
    ) !== false;
}

if (!$intro_target_allowed) {
    header('Location: ' . $intro_home, true, 303);
    exit();
}

$intro_name = trim((string) ($_SESSION['full_name'] ?? ''));

if ($intro_name === '') {
    $intro_name = (string) ($_SESSION['username'] ?? '');
}

$intro_name_html = htmlspecialchars(
    $intro_name,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$intro_role_html = htmlspecialchars(
    $intro_roles[$intro_role],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$intro_destination_html = htmlspecialchars(
    $intro_destination,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$intro_destination_json = json_encode(
    $intro_destination,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

// ปล่อย Session ก่อนแสดงภาพเคลื่อนไหว
session_write_close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh"
          content="3;url=<?= $intro_destination_html ?>">

    <title>MBS Repair</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            background: #071426;
            font-family: Arial, Helvetica, sans-serif;
        }

        .intro {
            position: relative;
            isolation: isolate;
            display: grid;
            place-items: center;
            min-height: 100vh;
            min-height: 100svh;
            overflow: hidden;
            padding: 30px 20px;
            background:
                radial-gradient(
                    ellipse at 50% 43%,
                    #153f65 0%,
                    #0b233d 34%,
                    #071426 72%
                );
        }

        /* พื้นหลังเส้นตารางจาง */
        .intro::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background-image:
                linear-gradient(#82d9ff08 1px, transparent 1px),
                linear-gradient(90deg, #82d9ff08 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(
                ellipse at center,
                black,
                transparent 72%
            );
        }

        /* แสงแนวนอนด้านหลังโลโก้ */
        .horizon {
            position: absolute;
            top: 44%;
            left: 50%;
            width: min(1100px, 130vw);
            height: 1px;
            pointer-events: none;
            background: linear-gradient(
                90deg,
                transparent,
                #36bfff33,
                #9ceaffb3,
                #36bfff33,
                transparent
            );
            box-shadow: 0 0 26px 4px #35baff24;
            transform: translateX(-50%);
            animation: horizon-in 1100ms 150ms ease-out both;
        }

        .composition {
            position: relative;
            width: min(100%, 640px);
            text-align: center;
            animation: composition-out 280ms 2220ms ease-in both;
        }

        .emblem {
            position: relative;
            display: grid;
            place-items: center;
            width: clamp(240px, 35vw, 340px);
            aspect-ratio: 1;
            margin: 0 auto 22px;
        }

        .aura {
            position: absolute;
            inset: 10%;
            border-radius: 50%;
            background: radial-gradient(
                circle,
                #2fb8ff38,
                #248dfb0d 55%,
                transparent 72%
            );
            filter: blur(12px);
            animation: aura-in 1600ms ease-out both;
        }

        .orbit {
            position: absolute;
            inset: 5%;
            border: 1px solid #74d8ff24;
            border-radius: 50%;
            animation: orbit-in 1000ms ease-out both;
        }

        .orbit::before {
            content: "";
            position: absolute;
            inset: -1px;
            border: 2px solid transparent;
            border-top-color: #70ddff;
            border-right-color: #42acff30;
            border-radius: inherit;
            transform: rotate(-45deg);
            animation: orbit-turn 2300ms
                cubic-bezier(.2,.65,.25,1) both;
        }

        .orbit::after {
            content: "";
            position: absolute;
            width: 7px;
            height: 7px;
            top: 14%;
            right: 14%;
            border-radius: 50%;
            background: #c4f3ff;
            box-shadow:
                0 0 12px #69d8ff,
                0 0 24px #3cbbff;
            animation: point-in 800ms 350ms both;
        }

        .orbit-inner {
            position: absolute;
            inset: 13%;
            border: 1px dashed #83d9ff24;
            border-radius: 50%;
            animation: inner-turn 2500ms ease-out both;
        }

        .logo {
            position: relative;
            display: grid;
            place-items: center;
            width: 46%;
            aspect-ratio: 1;
            overflow: hidden;
            border: 1px solid #b3ebff80;
            border-radius: 29%;
            background: linear-gradient(
                145deg,
                #49d6ff 0%,
                #159de6 38%,
                #2462cd 100%
            );
            box-shadow:
                inset 0 2px 2px #ffffff70,
                inset 0 -6px 14px #13409d55,
                0 18px 50px #00000040,
                0 0 45px #25adff24;
            animation: logo-in 900ms
                cubic-bezier(.16,.8,.24,1) both;
        }

        .logo::after {
            content: "";
            position: absolute;
            inset: -70%;
            background: linear-gradient(
                110deg,
                transparent 42%,
                #ffffff70 50%,
                transparent 58%
            );
            transform: translateX(-100%);
            animation: shine 850ms 850ms ease-in-out both;
        }

        .logo svg {
            width: 50%;
            height: 50%;
            overflow: visible;
            filter: drop-shadow(0 3px 2px #15548b30);
        }

        .logo path {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: draw 850ms 250ms ease-out forwards;
        }

        .wordmark {
            display: inline-block;
            margin: 0;
            color: #f4fbff;
            font-size: clamp(38px, 7vw, 66px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -2px;
            animation: wordmark-in 850ms 500ms
                cubic-bezier(.16,.8,.24,1) both;
        }

        .wordmark span {
            color: #67d6ff;
            font-weight: 400;
        }

        .accent {
            width: 64px;
            height: 2px;
            margin: 25px auto 0;
            border-radius: 5px;
            background: linear-gradient(
                90deg,
                transparent,
                #70deff,
                transparent
            );
            box-shadow: 0 0 16px #44caff66;
            animation: accent-in 700ms 850ms both;
        }

        @keyframes horizon-in {
            from {
                opacity: 0;
                transform: translateX(-50%) scaleX(.15);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) scaleX(1);
            }
        }

        @keyframes aura-in {
            from { opacity: 0; transform: scale(.5); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes orbit-in {
            from { opacity: 0; transform: scale(.7); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes orbit-turn {
            from { transform: rotate(-160deg); }
            to { transform: rotate(90deg); }
        }

        @keyframes inner-turn {
            from { opacity: 0; transform: rotate(60deg) scale(.85); }
            to { opacity: 1; transform: rotate(-25deg) scale(1); }
        }

        @keyframes point-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes logo-in {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.8) rotate(-8deg);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1) rotate(0);
            }
        }

        @keyframes draw {
            to { stroke-dashoffset: 0; }
        }

        @keyframes shine {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }

        @keyframes wordmark-in {
            from {
                opacity: 0;
                transform: translateY(16px);
                filter: blur(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
                filter: blur(0);
            }
        }

        @keyframes accent-in {
            from { opacity: 0; transform: scaleX(.1); }
            to { opacity: 1; transform: scaleX(1); }
        }

        @keyframes composition-out {
            to {
                opacity: 0;
                transform: scale(1.035);
                filter: blur(3px);
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .intro { padding: 16px; }
            .emblem { width: 200px; margin-bottom: 8px; }
            .wordmark { font-size: 36px; }
            .accent { margin-top: 14px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation: none !important;
            }

            .logo path { stroke-dashoffset: 0; }
            .logo::after { display: none; }
        }
    </style>
</head>

<body>
    <main class="intro" aria-label="MBS Repair">
        <div class="horizon" aria-hidden="true"></div>

        <div class="composition">
            <div class="emblem" aria-hidden="true">
                <div class="aura"></div>
                <div class="orbit"></div>
                <div class="orbit-inner"></div>

                <div class="logo">
                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="#ffffff"
                         stroke-width="1.6"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path
                            pathLength="100"
                            d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6
                               1.6a1 1 0 0 0 1.4 0l3.2-3.2a6
                               6 0 0 1-7.9 7.9l-7.3 7.3a2.1
                               2.1 0 0 1-3-3L10 11a6 6 0 0
                               1 7.9-7.9z"/>
                    </svg>
                </div>
            </div>

            <p class="wordmark" aria-hidden="true">
                MBS<span>Repair</span>
            </p>

            <div class="accent" aria-hidden="true"></div>
        </div>
    </main>

    <script>
        (() => {
            const destination = <?= $intro_destination_json ?>;

            const reducedMotion = window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches;

            const go = () => window.location.replace(destination);

            window.setTimeout(go, reducedMotion ? 100 : 2500);

            window.addEventListener('pageshow', event => {
                if (event.persisted) go();
            });
        })();
    </script>
</body>
</html>