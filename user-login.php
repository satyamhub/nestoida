<?php
session_start();
include "db.php";

$error = "";
$unverifiedEmail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, email_verified_at, COALESCE(session_version, 1) AS session_version FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user["password"])) {
            if (!empty($user["email_verified_at"])) {
                $_SESSION["user_id"] = (int)$user["id"];
                $_SESSION["user_name"] = $user["full_name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_role"] = $user["role"];
                $_SESSION["user_session_version"] = (int)$user["session_version"];

                if ($user["role"] === "owner") {
                    header("Location: owner-dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $unverifiedEmail = $user["email"];
                $error = "Please verify your email before login.";
            }
        }
    }

    if ($error === "") {
        $error = "Invalid email or password.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Nestoida</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem("nestoida_theme") === "dark") {
                    document.documentElement.classList.add("dark");
                }
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Space Grotesk"', 'sans-serif'],
                        body: ['"Manrope"', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
    <style>
        :root{
            --night:#0b1020;
            --night2:#11172b;
            --glow:rgba(118,189,255,.35);
            --window-on:#ffe29a;
            --window-off:#1b2440;
            --house:#ff7ac7;
            --house-dark:#e663b7;
        }
        body {
            background: radial-gradient(1200px 600px at 20% 10%, #18223d 0%, #0b1020 45%, #070b16 100%);
        }
        .page{
            min-height:100vh;
            display:grid;
            grid-template-columns: minmax(0,1.1fr) minmax(0,.9fr);
        }
        .scene-panel{
            position:relative;
            overflow:hidden;
            background:
              radial-gradient(500px 200px at 20% 20%, rgba(99,102,241,.18), transparent 60%),
              radial-gradient(420px 200px at 80% 30%, rgba(14,165,233,.2), transparent 65%),
              linear-gradient(180deg, #0a0f1f 0%, #0b1020 100%);
            transition: background 1s ease;
        }
        .scene-panel.day{
            background:
              radial-gradient(600px 240px at 30% 20%, rgba(255,210,120,.55), transparent 60%),
              radial-gradient(520px 240px at 80% 30%, rgba(56,189,248,.35), transparent 65%),
              linear-gradient(180deg, #87d7ff 0%, #d7f2ff 55%, #f7fdff 100%);
        }
        .scene-panel::after{
            content:"";
            position:absolute;
            inset:0;
            background: radial-gradient(300px 120px at 50% 80%, var(--glow), transparent 70%);
            opacity:.5;
            pointer-events:none;
        }
        .sky-toggle{
            position:absolute;
            top:20px;
            left:20px;
            width:72px;
            height:72px;
            border-radius:999px;
            display:flex;
            align-items:center;
            justify-content:center;
            transition: opacity .7s ease, transform 1.1s ease;
            z-index:1;
        }
        .sun{
            background: radial-gradient(circle at 30% 30%, #fff3b0 0%, #ffd24d 55%, #f59e0b 100%);
            box-shadow: 0 0 30px rgba(255,210,77,.6);
        }
        .moon{
            background: radial-gradient(circle at 30% 30%, #e2e8f0 0%, #94a3b8 60%, #64748b 100%);
            box-shadow: 0 0 24px rgba(148,163,184,.4);
        }
        .scene-panel.day .sun{
            transform: translate(0, 0) scale(1);
            opacity:1;
        }
        .scene-panel.night .sun{
            transform: translate(180px, 140px) scale(.7);
            opacity:0;
        }
        .scene-panel.day .moon{
            transform: translate(-120px, 40px) scale(.6);
            opacity:0;
        }
        .scene-panel.night .moon{
            transform: translate(0, 0) scale(1);
            opacity:1;
        }
        .house-wrap{
            position:absolute;
            inset:0;
            display:flex;
            align-items:stretch;
            justify-content:center;
            padding:0;
            z-index:2;
        }
        .house{
            width:100%;
            height:100%;
            filter: drop-shadow(0 20px 60px rgba(0,0,0,.55));
        }
        .house .window{
            fill: var(--window-off);
            transition: fill .35s ease, filter .35s ease;
        }
        .house .lamp{
            stroke:#2d3748;
            stroke-width:2;
            transition: transform .6s ease;
            transform: translateY(-18px);
            opacity:0;
            transform-origin: 50% 0%;
        }
        .house .lamp-bulb{
            fill:#fef3c7;
            transition: opacity .4s ease;
            opacity:.3;
        }
        .house-lit .lamp{
            transform: translateY(10px);
            opacity:1;
            animation: swing 3.6s ease-out 1;
        }
        @keyframes swing {
            0% { transform: translateY(10px) rotate(8deg); }
            25% { transform: translateY(10px) rotate(-6deg); }
            50% { transform: translateY(10px) rotate(4deg); }
            75% { transform: translateY(10px) rotate(-2deg); }
            100% { transform: translateY(10px) rotate(0deg); }
        }
        .house-lit .lamp-bulb{
            opacity:1;
            filter: drop-shadow(0 0 8px rgba(255,214,102,.6));
        }
        .house-lit .window{
            fill: var(--window-on);
            filter: drop-shadow(0 0 12px rgba(255,214,102,.6));
        }
        .house .shutter{
            opacity:0;
            transition: transform .35s ease, opacity .2s ease;
            transform-box: fill-box;
        }
        .house .shutter.left{
            transform-origin: left center;
            transform: scaleX(0);
        }
        .house .shutter.right{
            transform-origin: right center;
            transform: scaleX(0);
        }
        .house.window-closed .shutter{
            opacity:1;
        }
        .house.window-closed .shutter.left{
            transform: scaleX(1);
        }
        .house.window-closed .shutter.right{
            transform: scaleX(1);
        }
        .house .man{
            fill:#2c2c2c;
            transition: transform .35s ease;
            transform-origin: center;
        }
        .house-lit .man{
            transform: translateY(-2px);
        }
        .logo-mark .pulse{
            transform-origin: 60px 60px;
            animation: pulse 2.6s ease-in-out infinite;
        }
        .logo-mark .ring{
            transform-origin: 60px 60px;
            animation: ring 2.8s ease-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.92; }
        }
        @keyframes ring {
            0% { transform: scale(0.85); opacity: 0.45; }
            70% { transform: scale(1.18); opacity: 0; }
            100% { transform: scale(1.18); opacity: 0; }
        }
        .form-panel{
            display:flex;
            align-items:center;
            justify-content:center;
            padding:2.5rem 2rem;
        }
        .card{
            width:min(420px, 92vw);
            background: rgba(15,20,40,.88);
            border:1px solid #223055;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .card.light{
            background: rgba(255,255,255,.9);
            border-color:#e2e8f0;
            color:#0f172a;
        }
        .card.light .subtle{
            color:#475569;
        }
        .card.light .text-on-dark{
            color:#0f172a;
        }
        .card.light .field{
            background:#ffffff;
            color:#0f172a;
            border-color:#cbd5f5;
        }
        .card.light .chip{
            border-color:#cbd5f5;
            color:#0f172a;
        }
        @media (max-width: 900px){
            .page{ grid-template-columns: 1fr; }
            .scene-panel{ min-height:44vh; }
        }
    </style>
</head>
<body class="airbnb-ui font-body text-slate-100">
    <div class="page">
        <section id="scenePanel" class="scene-panel day">
            <div id="sun" class="sky-toggle sun" aria-hidden="true"></div>
            <div id="moon" class="sky-toggle moon" aria-hidden="true"></div>
            <div class="house-wrap">
                <svg id="house" class="house" viewBox="0 0 600 520" aria-hidden="true">
                    <defs>
                        <linearGradient id="housePink" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--house)"/>
                            <stop offset="100%" stop-color="var(--house-dark)"/>
                        </linearGradient>
                        <linearGradient id="logoBanner" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#0f172a"/>
                            <stop offset="100%" stop-color="#1f2a44"/>
                        </linearGradient>
                        <linearGradient id="woodShutter" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#8b5a3c"/>
                            <stop offset="50%" stop-color="#b07a54"/>
                            <stop offset="100%" stop-color="#6f3f29"/>
                        </linearGradient>
                    </defs>
                    <rect x="120" y="160" width="360" height="280" rx="24" fill="url(#housePink)"/>
                    <polygon points="300,40 80,180 520,180" fill="#ef5fb8"/>
                    <g transform="translate(160 88)">
                        <rect x="0" y="0" width="280" height="56" rx="14" fill="url(#logoBanner)" stroke="#2b385f" stroke-width="2"/>
                        <g class="logo-mark" transform="translate(12 8) scale(0.33)">
                            <defs>
                                <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ff385c"/>
                                    <stop offset="100%" stop-color="#e31c5f"/>
                                </linearGradient>
                                <linearGradient id="g2" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#22d3ee"/>
                                    <stop offset="100%" stop-color="#0284c7"/>
                                </linearGradient>
                            </defs>
                            <g class="ring">
                                <circle cx="60" cy="60" r="34" fill="none" stroke="url(#g2)" stroke-width="4"/>
                            </g>
                            <g class="pulse">
                                <path d="M60 15c-18 0-32 13.5-32 31 0 19.8 16.6 36.4 28.5 53.1a4.1 4.1 0 0 0 7 0C75.4 82.4 92 65.8 92 46c0-17.5-14-31-32-31z" fill="url(#g1)"/>
                                <path d="M42 50.5 60 35l18 15.5v16.8a2.7 2.7 0 0 1-2.7 2.7h-8.8V56.8h-13V70h-8.8a2.7 2.7 0 0 1-2.7-2.7V50.5z" fill="#fff"/>
                                <path d="M37.5 49.5 60 30.8l22.5 18.7" fill="none" stroke="#fff" stroke-linecap="round" stroke-width="4.5"/>
                            </g>
                        </g>
                        <text x="170" y="36" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="20" letter-spacing="2" fill="#e2e8f0">NESTOIDA</text>
                    </g>
                    <rect x="160" y="200" width="80" height="80" rx="10" class="window"/>
                    <rect x="160" y="200" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="200" y="200" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <rect x="260" y="200" width="80" height="80" rx="10" class="window"/>
                    <rect x="260" y="200" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="300" y="200" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <rect x="360" y="200" width="80" height="80" rx="10" class="window"/>
                    <rect x="360" y="200" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="400" y="200" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <rect x="160" y="300" width="80" height="80" rx="10" class="window"/>
                    <rect x="160" y="300" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="200" y="300" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <rect x="260" y="300" width="80" height="80" rx="10" class="window"/>
                    <rect x="260" y="300" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="300" y="300" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <rect x="360" y="300" width="80" height="80" rx="10" class="window"/>
                    <rect x="360" y="300" width="40" height="80" rx="10" class="shutter left" fill="url(#woodShutter)"/>
                    <rect x="400" y="300" width="40" height="80" rx="10" class="shutter right" fill="url(#woodShutter)"/>
                    <g class="lamp" transform="translate(0 0)">
                        <line x1="200" y1="190" x2="200" y2="214"/>
                        <circle cx="200" cy="222" r="6" class="lamp-bulb"/>
                        <line x1="300" y1="190" x2="300" y2="214"/>
                        <circle cx="300" cy="222" r="6" class="lamp-bulb"/>
                        <line x1="400" y1="190" x2="400" y2="214"/>
                        <circle cx="400" cy="222" r="6" class="lamp-bulb"/>
                        <line x1="200" y1="290" x2="200" y2="314"/>
                        <circle cx="200" cy="322" r="6" class="lamp-bulb"/>
                        <line x1="300" y1="290" x2="300" y2="314"/>
                        <circle cx="300" cy="322" r="6" class="lamp-bulb"/>
                        <line x1="400" y1="290" x2="400" y2="314"/>
                        <circle cx="400" cy="322" r="6" class="lamp-bulb"/>
                    </g>
                </svg>
            </div>
            <div class="trees" aria-hidden="true">
                <div class="tree"></div>
                <div class="tree" style="transform: scale(1.2);"></div>
                <div class="tree" style="transform: scale(0.9);"></div>
                <div class="tree" style="transform: scale(1.1);"></div>
            </div>
        </section>

        <section class="form-panel">
            <div id="loginCard" class="card rounded-3xl p-8">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h1 class="font-display text-3xl">User Login</h1>
                    <button id="theme-toggle" type="button" class="px-3 py-2 text-sm rounded-full border border-slate-700 text-slate-200">
                        <span id="theme-toggle-label" class="sr-only">Dark</span>
                        <span class="inline-flex items-center gap-2">
                            <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-amber-400">
                                <circle cx="12" cy="12" r="4" fill="currentColor"/>
                                <path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" stroke="currentColor" stroke-linecap="round" stroke-width="2" fill="none"/>
                            </svg>
                            <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-sky-200 hidden">
                                <path d="M20.5 14.2A7.5 7.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7Z" fill="currentColor"/>
                            </svg>
                        </span>
                    </button>
                </div>
                <p class="text-sm subtle mb-4">Login as property owner or viewer.</p>

            <?php if ($error !== "") { ?>
                <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
                <?php if ($unverifiedEmail !== "") { ?>
                    <a href="resend-verification.php?email=<?php echo urlencode($unverifiedEmail); ?>" class="inline-block -mt-2 mb-3 text-sm text-cyan-700 dark:text-cyan-300 hover:underline">Resend verification email</a>
                <?php } ?>
            <?php } ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input id="userField" type="email" name="email" required class="field w-full border border-slate-700 rounded-xl px-4 py-3 bg-slate-900/60 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input id="passField" type="password" name="password" required class="field w-full border border-slate-700 rounded-xl px-4 py-3 bg-slate-900/60 text-slate-100 focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <button type="submit" class="w-full bg-cyan-600 text-white py-3 rounded-xl font-semibold hover:bg-cyan-500 transition">Login</button>
            </form>
            <div class="mt-3">
                <a href="forgot-password.php" class="text-sm text-cyan-700 dark:text-cyan-300 hover:underline">Forgot password?</a>
            </div>

            <div class="mt-4 flex gap-2 text-sm">
                <a href="user-register.php" class="chip px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Create Account</a>
                <a href="index.php" data-no-loader="true" class="chip px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Home</a>
                <a href="login.php" class="chip px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Admin Login</a>
            </div>
            </div>
        </section>
    </div>
    <script>
        const house = document.getElementById('house');
        const loginCard = document.getElementById('loginCard');
        const iconSun = document.getElementById('theme-icon-sun');
        const iconMoon = document.getElementById('theme-icon-moon');
        const sun = document.getElementById('sun');
        const moon = document.getElementById('moon');
        const scenePanel = document.getElementById('scenePanel');
        const userField = document.getElementById('userField');
        const passField = document.getElementById('passField');
        function setHouseLit(isLit){
            if (!house) return;
            house.classList.toggle('house-lit', isLit);
        }
        function setWindowsClosed(isClosed){
            if (!house) return;
            house.classList.toggle('window-closed', isClosed);
        }
        userField.addEventListener('focus', ()=> setWindowsClosed(false));
        userField.addEventListener('input', ()=> setWindowsClosed(false));
        userField.addEventListener('blur', ()=> setWindowsClosed(false));
        passField.addEventListener('focus', ()=>{
            setHouseLit(false);
            setWindowsClosed(true);
        });
        passField.addEventListener('input', ()=>{
            setHouseLit(false);
            setWindowsClosed(true);
        });
        passField.addEventListener('blur', ()=> setWindowsClosed(false));
        function syncSky(){
            const isDark = document.documentElement.classList.contains('dark');
            if (scenePanel) {
                scenePanel.classList.toggle('night', isDark);
                scenePanel.classList.toggle('day', !isDark);
            }
            if (loginCard) {
                loginCard.classList.toggle('light', !isDark);
            }
            if (iconSun && iconMoon) {
                iconSun.classList.toggle('hidden', isDark);
                iconMoon.classList.toggle('hidden', !isDark);
            }
            setHouseLit(isDark);
        }
        setHouseLit(true);
        (function () {
            const btn = document.getElementById("theme-toggle");
            const label = document.getElementById("theme-toggle-label");
            function syncThemeLabel() {
                if (!label) return;
                label.textContent = document.documentElement.classList.contains("dark") ? "Light" : "Dark";
            }
            syncThemeLabel();
            if (btn) {
                btn.addEventListener("click", function () {
                    const root = document.documentElement;
                    const isDark = root.classList.toggle("dark");
                    try {
                        localStorage.setItem("nestoida_theme", isDark ? "dark" : "light");
                    } catch (e) {}
                    syncThemeLabel();
                    syncSky();
                });
            }
        })();
        syncSky();
    </script>
</body>
</html>
