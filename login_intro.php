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
        * { box-sizing: border-box; }

        html, body { margin: 0; min-height: 100%; }

        body {
            font-family: "Kanit", "Sarabun", Tahoma, sans-serif;
            color: #183b68;
            background: #f3faff;
        }

        .intro {
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            overflow: hidden;
            padding: 24px 18px;
            background:
                radial-gradient(ellipse at 20% 20%, #d9f4ff, transparent 55%),
                radial-gradient(ellipse at 85% 80%, #e0eaff, transparent 55%),
                #f8fcff;
        }

        .welcome {
            width: min(100%, 520px);
            text-align: center;
        }

        .scene {
            position: relative;
            width: min(100%, 340px);
            height: 250px;
            margin: 0 auto;
        }

        .halo {
            position: absolute;
            inset: 10px 55px 20px;
            border-radius: 50%;
            background: #e0f3ff;
            box-shadow:
                0 0 0 18px #edf8ff,
                0 0 0 19px #d5ebfa;
            animation: halo-in .8s ease-out both;
        }

        .ground {
            position: absolute;
            width: 170px;
            height: 18px;
            left: 50%;
            bottom: 26px;
            border-radius: 50%;
            background: #b3d9f1;
            filter: blur(7px);
            transform: translateX(-50%);
            animation: shadow-in .8s ease-out both;
        }

        .toolkit {
            position: absolute;
            width: 190px;
            height: 190px;
            left: 50%;
            top: 28px;
            margin-left: -95px;
            animation: toolkit-in .8s cubic-bezier(.18,.85,.3,1.2) both;
        }

        .toolkit svg {
            display: block;
            width: 100%;
            height: 100%;
            overflow: visible;
            filter: drop-shadow(0 15px 12px #247cc52b);
        }

        .floating-tool {
            transform-box: fill-box;
            transform-origin: center;
            animation: tool-in 1.5s ease-in-out both;
        }

        .tile {
            position: absolute;
            display: grid;
            place-items: center;
            width: 55px;
            height: 55px;
            border: 2px solid white;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 9px 0 #dcebf5, 0 15px 22px #477ba51c;
            animation: tile-in .7s cubic-bezier(.2,.8,.25,1.3) both;
        }

        .tile svg { width: 28px; height: 28px; }

        .tile-check {
            left: 18px;
            top: 56px;
            color: #16a987;
            background: #eefff9;
            transform: rotate(-13deg);
            animation-delay: .12s;
        }

        .tile-chart {
            right: 16px;
            top: 34px;
            color: #4384e7;
            background: #f2f7ff;
            transform: rotate(12deg);
            animation-delay: .23s;
        }

        .tile-bolt {
            right: 24px;
            bottom: 32px;
            width: 43px;
            height: 43px;
            border-radius: 14px;
            color: #e9a22a;
            background: #fff9e9;
            transform: rotate(-9deg);
            animation-delay: .32s;
        }

        .spark {
            position: absolute;
            color: #45b8ee;
            font-size: 25px;
            font-weight: 700;
            animation: sparkle 1.4s ease-in-out both;
        }

        .spark-one { left: 62px; top: 14px; }
        .spark-two { right: 77px; bottom: 13px; animation-delay: .15s; }

        .dot {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #77cfee;
            animation: sparkle 1.4s ease-in-out both;
        }

        .dot-one { right: 76px; top: 5px; }
        .dot-two { left: 37px; bottom: 46px; background: #f0c66a; }

        .brand {
            margin: 2px 0 12px;
            font-size: clamp(31px, 8vw, 40px);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -1.6px;
            animation: text-in .5s .15s both;
        }

        .brand span { color: #188bd1; }

        .welcome-label {
            margin: 0;
            color: #69839d;
            font-size: 14px;
            animation: text-in .5s .22s both;
        }

        .name {
            margin: 7px 0 12px;
            font-size: clamp(22px, 5.5vw, 29px);
            line-height: 1.5;
            overflow-wrap: anywhere;
            animation: text-in .5s .28s both;
        }

        .role {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 17px;
            border: 1px solid #d5eaf7;
            border-radius: 999px;
            background: #ffffffd9;
            color: #3474a3;
            font-size: 12px;
            animation: text-in .5s .32s both;
        }

        .role::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #23b99c;
            box-shadow: 0 0 0 3px #e2f8f1;
        }

        .journey {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin: 25px 0 12px;
        }

        .journey span {
            width: 25px;
            height: 5px;
            border-radius: 8px;
            background: #cce4f4;
            animation: step 1.5s ease-out both;
        }

        .journey span:nth-child(2) { animation-delay: .15s; }
        .journey span:nth-child(3) { animation-delay: .3s; }

        .status {
            margin: 0;
            color: #7890a6;
            font-size: 12px;
        }

        .skip {
            display: inline-block;
            padding: 10px 16px;
            margin-top: 12px;
            border-radius: 12px;
            color: #477d9f;
            font-size: 12px;
            text-decoration: none;
        }

        .skip:hover { background: #e2f2fd; }

        .skip:focus-visible {
            outline: 3px solid #4ca9e4;
            outline-offset: 3px;
        }

        @keyframes toolkit-in {
            from { opacity: 0; transform: translateY(35px) scale(.65) rotate(-12deg); }
            to { opacity: 1; transform: translateY(0) scale(1) rotate(0); }
        }

        @keyframes tool-in {
            0% { transform: translateY(18px) rotate(-16deg); }
            45% { transform: translateY(-9px) rotate(7deg); }
            75% { transform: translateY(-3px) rotate(-3deg); }
            100% { transform: translateY(-6px) rotate(0); }
        }

        @keyframes tile-in {
            from { opacity: 0; scale: .35; translate: 0 22px; }
            to { opacity: 1; scale: 1; translate: 0 0; }
        }

        @keyframes halo-in {
            from { opacity: 0; transform: scale(.6); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes shadow-in {
            from { opacity: 0; width: 80px; }
            to { opacity: 1; width: 170px; }
        }

        @keyframes sparkle {
            0% { opacity: 0; transform: scale(.2) rotate(-30deg); }
            55% { opacity: 1; transform: scale(1.2) rotate(15deg); }
            100% { opacity: .8; transform: scale(1) rotate(0); }
        }

        @keyframes text-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes step {
            0%, 15% { background: #cce4f4; }
            65%, 100% { background: #2799de; }
        }

        @media (max-width: 360px) {
            .scene { transform: scale(.88); margin-top: -15px; margin-bottom: -15px; }
        }

        @media (max-height: 550px) and (orientation: landscape) {
            .welcome {
                width: min(100%, 740px);
                display: grid;
                grid-template-columns: 280px 1fr;
                align-items: center;
                gap: 16px;
            }

            .scene { width: 280px; margin: 0; }
            .brand { font-size: 32px; }
            .name { font-size: 23px; }
            .journey { margin-top: 16px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; }
        }
    </style>
</head>

<body>
    <main class="intro">
        <section class="welcome" aria-labelledby="intro-name">

            <div class="scene" aria-hidden="true">
                <div class="halo"></div>
                <div class="ground"></div>

                <div class="toolkit">
                    <svg viewBox="0 0 200 200" fill="none">
                        <defs>
                            <linearGradient id="case-front" x1="35" y1="100"
                                            x2="150" y2="190"
                                            gradientUnits="userSpaceOnUse">
                                <stop stop-color="#42BFF3"/>
                                <stop offset="1" stop-color="#1673D6"/>
                            </linearGradient>

                            <linearGradient id="tool-metal" x1="80" y1="25"
                                            x2="126" y2="117"
                                            gradientUnits="userSpaceOnUse">
                                <stop stop-color="#FFFFFF"/>
                                <stop offset="1" stop-color="#AFD8F2"/>
                            </linearGradient>
                        </defs>

                        <!-- ด้ามจับด้านหลัง -->
                        <path d="M71 111V91a12 12 0 0 1 12-12h34a12 12 0 0 1 12 12v20"
                              stroke="#2467B6" stroke-width="13"
                              stroke-linecap="round"/>

                        <!-- ประแจที่เคลื่อนไหว -->
                        <g class="floating-tool">
                            <path d="M111 24a25 25 0 0 0-25 33L57 97a10 10 0 0 0 16 12l30-41a25 25 0 0 0 27-36l-15 18-13-10 14-16z"
                                  fill="#80B9DF"
                                  transform="translate(3 4)"/>

                            <path d="M111 24a25 25 0 0 0-25 33L57 97a10 10 0 0 0 16 12l30-41a25 25 0 0 0 27-36l-15 18-13-10 14-16z"
                                  fill="url(#tool-metal)"
                                  stroke="#86B8D7" stroke-width="2"
                                  stroke-linejoin="round"/>

                            <circle cx="66" cy="101" r="3" fill="#5596C2"/>
                        </g>

                        <!-- ตัวกล่องและด้านข้าง -->
                        <path d="M38 113l124-8 11 15v48a13 13 0 0 1-13 13H50a12 12 0 0 1-12-12z"
                              fill="#155DB5"/>

                        <rect x="27" y="108" width="135" height="68"
                              rx="14" fill="url(#case-front)"/>

                        <path d="M28 127h133" stroke="#116EBB"
                              stroke-width="3"/>

                        <path d="M42 115h102" stroke="#8DDEFF"
                              stroke-width="3" stroke-linecap="round"/>

                        <!-- ตัวล็อก -->
                        <rect x="82" y="120" width="24" height="23"
                              rx="6" fill="#135DA9"/>

                        <rect x="82" y="117" width="24" height="21"
                              rx="6" fill="#FFE6A0"
                              stroke="#FFF5D7" stroke-width="2"/>

                        <path d="M94 123v7" stroke="#D39A35"
                              stroke-width="3" stroke-linecap="round"/>

                        <path d="M44 156h27" stroke="#86D6FF"
                              stroke-width="4" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="tile tile-check">
                    <svg viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="16" r="12"
                                fill="#D1F5E8"/>
                        <path d="m10 16 4 4 8-9"
                              stroke="currentColor" stroke-width="3"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div class="tile tile-chart">
                    <svg viewBox="0 0 32 32" fill="none">
                        <rect x="5" y="17" width="5" height="10"
                              rx="2" fill="#9DCCFF"/>
                        <rect x="13" y="11" width="5" height="16"
                              rx="2" fill="#65AAEF"/>
                        <rect x="21" y="5" width="5" height="22"
                              rx="2" fill="currentColor"/>
                    </svg>
                </div>

                <div class="tile tile-bolt">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="m18 3-11 15h8l-1 11 11-16h-8z"
                              fill="#FFC65C" stroke="currentColor"
                              stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                </div>

                <span class="spark spark-one">✦</span>
                <span class="spark spark-two">✧</span>
                <span class="dot dot-one"></span>
                <span class="dot dot-two"></span>
            </div>

            <div class="welcome-copy">
                <p class="brand">MBS<span>Repair</span></p>

                <p class="welcome-label">ยินดีต้อนรับกลับมา</p>

                <h1 class="name" id="intro-name">
                    <?= $intro_name_html ?>
                </h1>

                <span class="role"><?= $intro_role_html ?></span>

                <div class="journey" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <p class="status" role="status">
                    กำลังเปิดพื้นที่ทำงานของคุณ
                </p>

                <a class="skip" href="<?= $intro_destination_html ?>">
                    ข้ามอินโทร →
                </a>
            </div>
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