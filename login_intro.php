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
            background: #f8fbff;
            font-family: Arial, Helvetica, sans-serif;
        }

        .brand-intro {
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: 24px;
            background:
                radial-gradient(
                    ellipse at 50% 45%,
                    #ffffff 0%,
                    #f8fbff 55%,
                    #eef5fc 100%
                );
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 19px;
            animation: brand-exit 220ms 1280ms ease-in both;
        }

        .brand-symbol {
            position: relative;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            width: 78px;
            height: 78px;
            overflow: hidden;
            border: 1px solid #ffffff80;
            border-radius: 23px;
            background: linear-gradient(
                145deg,
                #29b8ee 0%,
                #168cda 45%,
                #2563c6 100%
            );
            box-shadow:
                inset 0 1px 1px #ffffff66,
                inset 0 -2px 4px #154a9c33,
                0 12px 28px #187ac526;
            animation: symbol-enter 650ms cubic-bezier(.2,.75,.25,1) both;
        }

        .brand-symbol::after {
            content: "";
            position: absolute;
            inset: -50%;
            pointer-events: none;
            background: linear-gradient(
                110deg,
                transparent 38%,
                #ffffff00 42%,
                #ffffff55 50%,
                #ffffff00 58%,
                transparent 62%
            );
            transform: translateX(-100%);
            animation: symbol-light 650ms 480ms ease-in-out both;
        }

        .brand-symbol svg {
            width: 39px;
            height: 39px;
            overflow: visible;
        }

        .brand-symbol path {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: draw-symbol 650ms 130ms ease-out forwards;
        }

        .brand-wordmark {
            margin: 0;
            color: #193351;
            font-size: clamp(29px, 7vw, 43px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -1.8px;
            white-space: nowrap;
            animation: wordmark-enter 650ms 180ms
                cubic-bezier(.2,.75,.25,1) both;
        }

        .brand-wordmark span {
            color: #168acb;
            font-weight: 400;
            letter-spacing: -1.5px;
        }

        @keyframes symbol-enter {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.92);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes draw-symbol {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes wordmark-enter {
            from {
                opacity: 0;
                transform: translateX(-12px);
                filter: blur(5px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
                filter: blur(0);
            }
        }

        @keyframes symbol-light {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(100%);
            }
        }

        @keyframes brand-exit {
            to {
                opacity: 0;
                transform: translateY(-3px);
            }
        }

        @media (max-width: 380px) {
            .brand-lockup {
                gap: 13px;
            }

            .brand-symbol {
                width: 62px;
                height: 62px;
                border-radius: 19px;
            }

            .brand-symbol svg {
                width: 32px;
                height: 32px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .brand-lockup,
            .brand-symbol,
            .brand-symbol::after,
            .brand-symbol path,
            .brand-wordmark {
                animation: none;
            }

            .brand-symbol path {
                stroke-dashoffset: 0;
            }

            .brand-symbol::after {
                display: none;
            }
        }
    </style>
</head>

<body>
    <main class="brand-intro" aria-label="MBS Repair">
        <div class="brand-lockup">

            <div class="brand-symbol" aria-hidden="true">
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

            <p class="brand-wordmark" aria-hidden="true">
                MBS<span>Repair</span>
            </p>

        </div>
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