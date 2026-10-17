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
          content="2;url=<?= $intro_destination_html ?>">
    <title>ยินดีต้อนรับ | MBS Repair</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            font-family: "Kanit", "Sarabun", "Tahoma", sans-serif;
            color: #172554;
            background: #f4f7ff;
        }

        .intro {
            position: relative;
            isolation: isolate;
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 28px 18px;
            overflow: hidden;
            background:
                radial-gradient(
                    ellipse at 12% 15%,
                    #e3dcff 0,
                    transparent 52%
                ),
                radial-gradient(
                    ellipse at 90% 85%,
                    #ccefff 0,
                    transparent 50%
                ),
                #f5f8ff;
        }

        .intro::before,
        .intro::after {
            content: "";
            position: absolute;
            z-index: -1;
            width: min(76vw, 660px);
            aspect-ratio: 1;
            border: 1px solid rgb(110 141 221 / 14%);
            border-radius: 50%;
            pointer-events: none;
        }

        .intro::before {
            top: -30%;
            right: -12%;
        }

        .intro::after {
            bottom: -40%;
            left: -12%;
        }

        .intro-card {
            width: 100%;
            max-width: 480px;
            padding: 42px 34px 28px;
            text-align: center;
            border: 1px solid rgb(255 255 255 / 95%);
            border-radius: 32px;
            background: rgb(255 255 255 / 86%);
            box-shadow: 0 28px 80px rgb(54 72 137 / 11%);
            animation: reveal 650ms ease-out both;
        }

        .intro-mark {
            display: grid;
            place-items: center;
            width: 82px;
            height: 82px;
            margin: 0 auto 24px;
            border: 1px solid rgb(255 255 255 / 50%);
            border-radius: 25px;
            color: white;
            background: linear-gradient(
                140deg, #8455ee, #4f67e8 55%, #26b8e7
            );
            box-shadow: 0 12px 28px rgb(79 103 232 / 24%);
        }

        .intro-mark svg {
            width: 38px;
            height: 38px;
        }

        .intro-brand {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .intro-brand span {
            color: #5664d9;
        }

        .intro-divider {
            width: 38px;
            height: 3px;
            margin: 22px auto;
            border-radius: 4px;
            background: linear-gradient(90deg, #9163ed, #29b9e4);
        }

        .intro-welcome {
            margin: 0;
            font-size: 14px;
            color: #7c87a1;
        }

        .intro-name {
            margin: 9px 0 14px;
            font-size: clamp(21px, 5vw, 27px);
            font-weight: 700;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .intro-role {
            display: inline-block;
            padding: 6px 15px;
            border: 1px solid #dce5fc;
            border-radius: 999px;
            color: #5868b6;
            background: #f0f4ff;
            font-size: 12px;
        }

        .intro-progress {
            height: 4px;
            margin: 30px 0 13px;
            overflow: hidden;
            border-radius: 8px;
            background: #e9edf8;
        }

        .intro-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(
                90deg, #9262ee, #536be8, #28bce6
            );
            transform-origin: left;
            animation: progress 1500ms ease-out both;
        }

        .intro-status {
            margin: 0;
            font-size: 12px;
            color: #8490a8;
        }

        .intro-skip {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            border-radius: 12px;
            color: #5868b6;
            font-size: 12px;
            text-decoration: none;
        }

        .intro-skip:hover {
            background: #eef2ff;
        }

        .intro-skip:focus-visible {
            outline: 3px solid #a5b4fc;
            outline-offset: 3px;
        }

        @keyframes reveal {
            from {
                opacity: 0;
                transform: translateY(16px) scale(.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes progress {
            from {
                transform: scaleX(0);
            }
            to {
                transform: scaleX(1);
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .intro-card {
                max-width: 560px;
                padding: 22px 28px 16px;
            }

            .intro-mark {
                width: 54px;
                height: 54px;
                margin-bottom: 12px;
                border-radius: 18px;
            }

            .intro-mark svg {
                width: 28px;
                height: 28px;
            }

            .intro-brand {
                font-size: 23px;
            }

            .intro-divider {
                margin: 12px auto;
            }

            .intro-name {
                margin: 5px 0 8px;
                font-size: 21px;
            }

            .intro-progress {
                margin-top: 16px;
            }

            .intro-skip {
                margin-top: 8px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .intro-card,
            .intro-progress span {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <main class="intro">
        <section class="intro-card" aria-labelledby="intro-name">
            <div class="intro-mark" aria-hidden="true">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.2-3.2a6 6 0 0 1-7.9 7.9l-7.3 7.3a2.1 2.1 0 0 1-3-3L10 11a6 6 0 0 1 7.9-7.9z"/>
                </svg>
            </div>

            <p class="intro-brand">MBS<span>Repair</span></p>
            <div class="intro-divider" aria-hidden="true"></div>

            <p class="intro-welcome">ยินดีต้อนรับเข้าสู่ระบบ</p>
            <h1 class="intro-name" id="intro-name">
                <?= $intro_name_html ?>
            </h1>
            <span class="intro-role"><?= $intro_role_html ?></span>

            <div class="intro-progress" aria-hidden="true">
                <span></span>
            </div>

            <p class="intro-status" role="status">
                กำลังเปิดพื้นที่ทำงานของคุณ
            </p>

            <a class="intro-skip" href="<?= $intro_destination_html ?>">
                ข้ามอินโทร →
            </a>
        </section>
    </main>

    <script>
        (() => {
            const destination = <?= $intro_destination_json ?>;
            const reducedMotion = window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches;

            const go = () => window.location.replace(destination);

            window.setTimeout(go, reducedMotion ? 100 : 1500);

            window.addEventListener('pageshow', event => {
                if (event.persisted) go();
            });
        })();
    </script>
</body>
</html>