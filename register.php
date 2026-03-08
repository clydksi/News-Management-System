<?php
require 'db.php';
session_start();

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

$allowed_domains = ['mbcradio.net', 'dzrh.com.ph'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $username      = trim($_POST['username'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';
        $department_id = $_POST['department_id'] ?? '';

        $email_domain = substr(strrchr($email, '@'), 1);

        if (empty($username) || empty($email) || empty($password) || empty($department_id)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($email_domain, $allowed_domains)) {
            $error = 'Registration is restricted to @mbcradio.net email addresses.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email is already taken.';
            } else {
                $token      = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $hashed     = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare('
                    INSERT INTO users
                      (username, email, password, department_id, role,
                       email_verify_token, email_token_expires_at, is_active)
                    VALUES (?, ?, ?, ?, \'user\', ?, ?, 0)
                ');
                $stmt->execute([$username, $email, $hashed, $department_id, $token, $expires_at]);

                // Send verification email
                $verify_url = 'https://newsnetwork.mbcradio.net/crud/verify_email.php?token=' . $token;
                $subject    = 'Verify your NewsRoom account';
                $message    = "Hi {$username},\r\n\r\n"
                            . "Click the link below to verify your email address.\r\n"
                            . "This link expires in 24 hours.\r\n\r\n"
                            . $verify_url . "\r\n\r\n"
                            . "If you did not register, you can safely ignore this email.\r\n\r\n"
                            . "— MBC NewsRoom Team";
                $headers    = "From: noreply@mbcradio.net\r\n"
                            . "Reply-To: noreply@mbcradio.net\r\n"
                            . "X-Mailer: PHP/" . phpversion();
                mail($email, $subject, $message, $headers);

                $success = 'Account created! Check your @mbcradio.net inbox to verify your email before logging in.';
            }
        }
    }
}

$departments = $pdo->query('SELECT * FROM departments ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="robots" content="noindex, nofollow"/>
    <title>Create Account — NewsRoom</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary:     '#7C3AED',
                        'primary-d': '#6D28D9',
                        'primary-l': '#EDE9FE',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    animation: {
                        'fade-up':   'fadeUp .5s cubic-bezier(.16,1,.3,1) both',
                        'shake':     'shake .5s ease',
                        'pulse-dot': 'pulseDot 1.4s ease-in-out infinite',
                        'spin-slow': 'spin 1s linear infinite',
                        'slide-down':'slideDown .3s cubic-bezier(.16,1,.3,1) both',
                        'pop-in':    'popIn .5s cubic-bezier(.22,1,.36,1) both',
                    },
                    keyframes: {
                        fadeUp:    { from:{ opacity:0, transform:'translateY(24px)' }, to:{ opacity:1, transform:'none' } },
                        shake:     { '0%,100%':{ transform:'translateX(0)' }, '20%,60%':{ transform:'translateX(-6px)' }, '40%,80%':{ transform:'translateX(6px)' } },
                        pulseDot:  { '0%,100%':{ opacity:1, transform:'scale(1)' }, '50%':{ opacity:.4, transform:'scale(.8)' } },
                        slideDown: { from:{ opacity:0, transform:'translateY(-8px)' }, to:{ opacity:1, transform:'none' } },
                        popIn:     { from:{ transform:'scale(0) rotate(-20deg)', opacity:0 }, to:{ transform:'scale(1) rotate(0)', opacity:1 } },
                    }
                }
            }
        }
    </script>

    <style>
        * { -webkit-font-smoothing: antialiased; }

        /* ── Dark mesh background ── */
        .bg-mesh {
            background-color: #0d0618;
            background-image:
                radial-gradient(ellipse 80% 60% at 20% 10%,  rgba(124,58,237,.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%,  rgba(79,70,229,.18)  0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%,  rgba(167,139,250,.08) 0%, transparent 70%);
        }

        /* ── Floating orbs ── */
        .orb {
            position: absolute; border-radius: 50%;
            filter: blur(90px); pointer-events: none;
            will-change: transform;
        }
        .orb-1 {
            width:600px; height:600px;
            background: radial-gradient(circle, rgba(124,58,237,.2), transparent 70%);
            top: -150px; left: -150px;
            animation: orbFloat 20s ease-in-out infinite;
        }
        .orb-2 {
            width:500px; height:500px;
            background: radial-gradient(circle, rgba(79,70,229,.15), transparent 70%);
            bottom: -120px; right: -120px;
            animation: orbFloat 16s ease-in-out infinite reverse;
        }
        .orb-3 {
            width:350px; height:350px;
            background: radial-gradient(circle, rgba(192,132,252,.1), transparent 70%);
            top: 40%; left: 60%;
            animation: orbFloat 24s ease-in-out infinite 6s;
        }
        @keyframes orbFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33%     { transform: translate(25px,-20px) scale(1.04); }
            66%     { transform: translate(-20px,25px) scale(.97); }
        }

        /* ── Stars ── */
        .star { position:absolute; border-radius:50%; pointer-events:none; animation:twinkle ease-in-out infinite; }
        @keyframes twinkle {
            0%,100% { opacity:.7; transform:scale(1); }
            50%     { opacity:.15; transform:scale(.6); }
        }

        /* ── Grid overlay ── */
        .grid-overlay {
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ── Glass ── */
        .glass {
            background: rgba(255,255,255,.055);
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border: 1px solid rgba(255,255,255,.1);
        }
        .glass-subtle {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.07);
        }

        /* ── Shimmer stripe on card top ── */
        .card-shimmer {
            position: absolute; top: 0; left: 0; right: 0; height: 3px; z-index: 1;
            background: linear-gradient(90deg, #4C1D95, #7C3AED, #A78BFA, #818CF8, #4C1D95);
            background-size: 300% 100%;
            animation: shimmer 4s linear infinite;
            border-radius: 20px 20px 0 0;
        }
        @keyframes shimmer { 0%{background-position:0 0} 100%{background-position:300% 0} }

        /* ── Input field ── */
        .field {
            background: rgba(255,255,255,.07);
            border: 1.5px solid rgba(255,255,255,.1);
            color: white;
            transition: border-color .25s, box-shadow .25s, background .25s;
        }
        .field::placeholder { color: rgba(255,255,255,.3); }
        .field:focus {
            outline: none;
            border-color: #7C3AED;
            box-shadow: 0 0 0 3.5px rgba(124,58,237,.22);
            background: rgba(255,255,255,.1);
        }
        .field.is-error {
            border-color: #F87171;
            box-shadow: 0 0 0 3px rgba(248,113,113,.18);
        }
        .field.is-ok {
            border-color: #34D399;
            box-shadow: 0 0 0 3px rgba(52,211,153,.14);
        }
        /* Override Tailwind forms plugin for select */
        select.field { cursor: pointer; }
        select.field option { background: #1a1030; color: white; }

        /* ── Strength bar ── */
        .strength-track {
            height: 3px; border-radius: 99px;
            background: rgba(255,255,255,.1);
            overflow: hidden; margin-top: 8px;
        }
        .strength-fill {
            height: 100%; width: 0; border-radius: 99px;
            transition: width .4s cubic-bezier(.22,1,.36,1), background .35s;
        }

        /* ── Req dots ── */
        .req-dot {
            width: 5px; height: 5px; border-radius: 50%;
            background: rgba(255,255,255,.2);
            transition: background .25s;
        }
        .req-dot.met { background: #34D399; }

        /* ── Match pill ── */
        .match-pill {
            position: absolute; right: 42px; top: 50%; transform: translateY(-50%);
            font-size: 9px; font-weight: 700; font-family: 'Courier New', monospace;
            padding: 2px 7px; border-radius: 99px;
            display: none; transition: all .2s; white-space: nowrap;
            pointer-events: none;
        }
        .match-pill.ok { background: rgba(52,211,153,.15); border: 1px solid rgba(52,211,153,.3); color: #6EE7B7; display: block; }
        .match-pill.no { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.25); color: #FCA5A5; display: block; }

        /* ── Eye button ── */
        .eye-btn {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,.3); padding: 4px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            transition: color .15s, background .15s;
        }
        .eye-btn:hover { color: rgba(255,255,255,.7); background: rgba(255,255,255,.08); }

        /* ── Submit button ── */
        .btn-submit {
            background: linear-gradient(135deg, #7C3AED 0%, #5B21B6 100%);
            box-shadow: 0 4px 24px rgba(124,58,237,.45), inset 0 1px 0 rgba(255,255,255,.12);
            transition: transform .2s, box-shadow .2s;
            position: relative; overflow: hidden;
        }
        .btn-submit::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.12), transparent);
            opacity: 0; transition: opacity .2s;
        }
        .btn-submit:hover:not(:disabled)::before { opacity: 1; }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(124,58,237,.55), inset 0 1px 0 rgba(255,255,255,.12);
        }
        .btn-submit:active:not(:disabled) { transform: translateY(0); }
        .btn-submit:disabled { opacity: .55; cursor: not-allowed; }

        /* ── Ripple ── */
        .ripple-el {
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,.28);
            transform: scale(0);
            animation: rippleAnim .65s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim { to { transform:scale(4.5); opacity:0; } }

        /* ── Progress steps ── */
        .step-dot {
            width: 22px; height: 22px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700; flex-shrink: 0;
        }
        .step-dot.active  { background: #7C3AED; color: white; box-shadow: 0 0 0 3px rgba(124,58,237,.25); }
        .step-dot.inactive{ background: rgba(255,255,255,.08); color: rgba(255,255,255,.3); border: 1px solid rgba(255,255,255,.1); }

        /* ── Divider ── */
        .form-divider {
            display: flex; align-items: center; gap: 10px;
            margin: 4px 0 20px; font-size: 10px;
            color: rgba(255,255,255,.25);
            font-family: 'Courier New', monospace; letter-spacing: .08em;
        }
        .form-divider::before, .form-divider::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(255,255,255,.08);
        }

        /* ── Deco cards (left panel feature grid) ── */
        .deco-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            transition: transform .3s, box-shadow .3s;
        }
        .deco-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(0,0,0,.35);
        }

        /* ── Success overlay ── */
        .success-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(8,4,20,.96);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity .5s ease;
        }
        .success-overlay.show { opacity: 1; pointer-events: all; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(124,58,237,.35); border-radius: 9px; }
    </style>
</head>

<body class="font-sans min-h-screen text-white bg-mesh overflow-x-hidden">

<!-- ══ BACKGROUND ═════════════════════════════════════════════════════════ -->
<div class="fixed inset-0 grid-overlay pointer-events-none z-0"></div>
<div class="orb orb-1 fixed z-0"></div>
<div class="orb orb-2 fixed z-0"></div>
<div class="orb orb-3 fixed z-0"></div>
<div id="starField" class="fixed inset-0 pointer-events-none z-0 overflow-hidden"></div>

<!-- ══ MAIN ═══════════════════════════════════════════════════════════════ -->
<div class="relative z-10 flex min-h-screen">

    <!-- ─── LEFT PANEL (desktop) ─────────────────────────────────────── -->
    <div class="hidden lg:flex lg:w-[48%] xl:w-[45%] flex-col justify-between p-12 xl:p-16 relative overflow-hidden">

        <!-- Logo -->
        <a href="login.php" class="flex items-center gap-3 w-fit">
            <div class="w-10 h-10 rounded-xl glass flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-400 text-xl" style="font-variation-settings:'FILL' 1">newspaper</span>
            </div>
            <span class="text-base font-bold tracking-tight">NewsRoom</span>
        </a>

        <!-- Center content -->
        <div class="flex-1 flex flex-col justify-center py-16">
            <div class="inline-flex items-center gap-2 glass-subtle rounded-full px-4 py-1.5 w-fit mb-8">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse-dot"></span>
                <span class="text-xs text-white/50 font-medium">Open for new members</span>
            </div>

            <h2 class="text-4xl xl:text-[3.2rem] font-extrabold leading-[1.12] tracking-tight mb-5">
                Your news,<br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 via-purple-400 to-indigo-400">
                    your language.
                </span>
            </h2>

            <p class="text-white/45 text-[15px] leading-relaxed max-w-[360px] mb-10">
                Manage, publish, and translate articles across eight Filipino dialects from one powerful dashboard.
            </p>

            <!-- Feature grid -->
            <div class="grid grid-cols-2 gap-3 max-w-[380px]">
                <?php
                $features = [
                    ['translate',    'Translation Engine', 'AI-powered dialect support'],
                    ['groups',       'Team Collaboration', 'Role-based access control'],
                    ['bar_chart',    'Live Analytics',     'Real-time dashboard metrics'],
                    ['notifications','Smart Alerts',       'Instant news notifications'],
                ];
                foreach ($features as [$ico, $title, $desc]):
                ?>
                <div class="deco-card p-4">
                    <span class="material-symbols-outlined text-purple-400 text-2xl mb-2 block" style="font-variation-settings:'FILL' 1"><?= $ico ?></span>
                    <p class="text-sm font-semibold text-white/80 leading-snug"><?= $title ?></p>
                    <p class="text-xs text-white/35 mt-0.5"><?= $desc ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Testimonial -->
        <div class="glass rounded-2xl p-6 max-w-[420px]">
            <div class="flex gap-0.5 mb-4">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <span class="material-symbols-outlined text-amber-400 text-base" style="font-variation-settings:'FILL' 1">star</span>
                <?php endfor; ?>
            </div>
            <p class="text-sm text-white/65 leading-relaxed mb-5">
                "The dialect translation feature transformed how we reach our regional audience. Truly indispensable for any news operation."
            </p>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex items-center justify-center text-sm font-bold flex-shrink-0">J</div>
                <div>
                    <p class="text-sm font-semibold text-white/80">Juan dela Cruz</p>
                    <p class="text-xs text-white/35">Senior Editor, Manila Bureau</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT PANEL (form) ───────────────────────────────────────── -->
    <div class="flex-1 flex flex-col items-center justify-center p-5 sm:p-10 lg:p-14 relative">

        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-2 mb-10 self-start">
            <div class="w-9 h-9 rounded-xl glass flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-400 text-lg" style="font-variation-settings:'FILL' 1">newspaper</span>
            </div>
            <span class="text-sm font-bold tracking-tight">NewsRoom</span>
        </div>

        <div class="w-full max-w-[440px] animate-fade-up">

            <!-- Heading -->
            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-purple-400 mb-3">New member</p>
                <h1 class="text-[2rem] font-extrabold tracking-tight leading-none mb-2">Create your account</h1>
                <p class="text-white/40 text-sm">Join the newsroom and start contributing today.</p>
            </div>

            <!-- ── Progress strip ── -->
            <div class="flex items-center gap-3 mb-7">
                <span class="step-dot active text-xs">1</span>
                <span class="text-[10px] font-bold uppercase tracking-[.1em] text-white/50">Account Info</span>
                <div class="flex-1 h-px bg-white/10"></div>
                <span class="text-[10px] font-bold uppercase tracking-[.1em] text-white/20">Verify Email</span>
                <span class="step-dot inactive text-xs">2</span>
            </div>

            <!-- ── Error banner ── -->
            <?php if (!empty($error)): ?>
            <div class="mb-5 glass rounded-xl px-4 py-3.5 flex items-start gap-3 animate-shake"
                 style="border-color:rgba(239,68,68,.3); background:rgba(239,68,68,.08)">
                <span class="material-symbols-outlined text-red-400 text-xl flex-shrink-0 mt-0.5">error</span>
                <p class="text-sm text-red-300 font-medium leading-snug">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- ── Form card ── -->
            <div class="glass rounded-2xl p-7 sm:p-8 relative overflow-hidden">
                <div class="card-shimmer"></div>

                <form method="post" id="registerForm" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>

                    <!-- Username -->
                    <div class="mb-4">
                        <label for="username" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-[19px] pointer-events-none">alternate_email</span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Choose a username"
                                autocomplete="username"
                                required
                                class="field w-full rounded-xl pl-11 pr-4 py-3.5 text-sm font-medium"
                            />
                        </div>
                        <p class="text-xs text-white/25 mt-1.5 ml-0.5">Min. 3 characters</p>
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Work Email
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-[19px] pointer-events-none">mail</span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="you@mbcradio.net"
                                autocomplete="email"
                                required
                                class="field w-full rounded-xl pl-11 pr-4 py-3.5 text-sm font-medium"
                            />
                        </div>
                        <p class="text-xs text-white/25 mt-1.5 ml-0.5">Must be an @mbcradio.net address</p>
                    </div>

                    <!-- Department -->
                    <div class="mb-5">
                        <label for="department_id" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Department
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-[19px] pointer-events-none">domain</span>
                            <select
                                id="department_id"
                                name="department_id"
                                required
                                class="field w-full rounded-xl pl-11 pr-10 py-3.5 text-sm font-medium appearance-none"
                            >
                                <option value="">— Select your department —</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"<?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-white/30 text-[18px] pointer-events-none">expand_more</span>
                        </div>
                    </div>

                    <div class="form-divider">password</div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-[19px] pointer-events-none">lock</span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Min. 8 characters"
                                autocomplete="new-password"
                                required
                                class="field w-full rounded-xl pl-11 pr-11 py-3.5 text-sm font-medium"
                            />
                            <button type="button" class="eye-btn" onclick="togglePwd('password', this)" tabindex="-1">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="strength-track">
                            <div class="strength-fill" id="sFill"></div>
                        </div>
                        <div class="flex items-center justify-between mt-1.5">
                            <span class="text-[10px] font-medium font-mono" id="sLabel" style="transition:color .25s"></span>
                            <div class="flex gap-1">
                                <span class="req-dot" id="r1" title="8+ chars"></span>
                                <span class="req-dot" id="r2" title="Uppercase"></span>
                                <span class="req-dot" id="r3" title="Number"></span>
                                <span class="req-dot" id="r4" title="Symbol"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30 text-[19px] pointer-events-none">lock_outline</span>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Repeat your password"
                                autocomplete="new-password"
                                required
                                class="field w-full rounded-xl pl-11 pr-28 py-3.5 text-sm font-medium"
                            />
                            <span class="match-pill" id="matchPill"></span>
                            <button type="button" class="eye-btn" onclick="togglePwd('confirm_password', this)" tabindex="-1">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="submitBtn"
                            class="btn-submit w-full h-[52px] rounded-xl text-sm font-bold tracking-wide
                                   text-white flex items-center justify-center gap-2 overflow-hidden">
                        <span id="btnContent" class="flex items-center gap-2 pointer-events-none">
                            <span class="material-symbols-outlined text-xl" style="font-variation-settings:'FILL' 1">person_add</span>
                            <span id="btnText">Create Account</span>
                        </span>
                        <span id="btnSpinner" class="hidden pointer-events-none">
                            <svg class="animate-spin-slow w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="3.5"/>
                                <path class="opacity-80" fill="white" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                        </span>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <p class="text-center mt-7 text-sm text-white/35">
                Already a member?
                <a href="login.php" class="font-semibold text-purple-400 hover:text-purple-300 transition-colors">
                    Sign in here
                </a>
            </p>

            <!-- Security badges -->
            <div class="flex items-center justify-center gap-5 mt-6 flex-wrap">
                <?php foreach ([
                    ['security',      'SSL Secured'],
                    ['verified_user', 'CSRF Protected'],
                    ['mail_lock',     'Email Verified'],
                ] as [$ico, $lbl]): ?>
                <div class="flex items-center gap-1.5 text-white/20">
                    <span class="material-symbols-outlined text-[15px]"><?= $ico ?></span>
                    <span class="text-[11px] font-medium"><?= $lbl ?></span>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /max-w -->
    </div><!-- /right panel -->
</div><!-- /main -->

<!-- ══ SUCCESS OVERLAY ════════════════════════════════════════════════════ -->
<?php if (!empty($success)): ?>
<div class="success-overlay show" id="successOverlay">
    <div class="text-center animate-fade-up px-6 max-w-sm mx-auto">
        <!-- Animated icon ring -->
        <div class="relative w-28 h-28 mx-auto mb-8">
            <div class="absolute inset-0 rounded-full bg-emerald-500/10 border border-emerald-500/20 animate-ping"></div>
            <div class="absolute inset-2 rounded-full bg-emerald-500/10 border border-emerald-500/15 animate-ping" style="animation-delay:.2s"></div>
            <div class="relative w-28 h-28 rounded-full bg-emerald-500/15 border border-emerald-400/40 flex items-center justify-center">
                <span class="material-symbols-outlined text-5xl text-emerald-400" style="font-variation-settings:'FILL' 1">mark_email_read</span>
            </div>
        </div>

        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-400 mb-3">Step 1 complete</p>
        <h2 class="text-3xl font-extrabold mb-3 tracking-tight">Account created!</h2>
        <p class="text-white/50 text-sm leading-relaxed mb-8">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <!-- Info card -->
        <div class="glass rounded-2xl px-5 py-4 mb-8 text-left flex items-start gap-3">
            <span class="material-symbols-outlined text-amber-400 text-xl flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">schedule</span>
            <p class="text-xs text-white/50 leading-relaxed">
                The verification link expires in <strong class="text-white/70">24 hours</strong>.
                Check your spam folder if you don't see it in your inbox.
            </p>
        </div>

        <!-- Step 2 indicator -->
        <div class="flex items-center justify-center gap-3 mb-8">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[12px] text-white" style="font-variation-settings:'FILL' 1">check</span>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-[.1em] text-emerald-400">Account Info</span>
            </div>
            <div class="flex-1 h-px bg-white/15 max-w-[40px]"></div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-purple-500/30 border border-purple-400/50 flex items-center justify-center flex-shrink-0">
                    <span class="text-[10px] font-bold text-purple-300">2</span>
                </div>
                <span class="text-[11px] font-bold uppercase tracking-[.1em] text-purple-400">Verify Email</span>
            </div>
        </div>

        <!-- Pulse dots -->
        <div class="flex gap-1.5 justify-center mb-8">
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot"></span>
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot" style="animation-delay:.18s"></span>
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot" style="animation-delay:.36s"></span>
        </div>

        <a href="login.php"
           class="btn-submit inline-flex items-center justify-center gap-2 px-8 h-12 rounded-xl text-sm font-bold text-white">
            <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">login</span>
            Go to Login
        </a>
    </div>
</div>
<?php endif; ?>
<script>
'use strict';

// ── Star field ────────────────────────────────────────────────────────────────
(function () {
    const c = document.getElementById('starField');
    if (!c) return;
    for (let i = 0; i < 80; i++) {
        const s   = document.createElement('div');
        const sz  = Math.random() * 2 + .5;
        const dur = Math.random() * 4 + 2;
        const del = Math.random() * 6;
        s.className = 'star';
        Object.assign(s.style, {
            width:             sz + 'px',
            height:            sz + 'px',
            left:              Math.random() * 100 + '%',
            top:               Math.random() * 100 + '%',
            background:        `rgba(${180 + Math.random()*60},${160 + Math.random()*60},255,${Math.random() * .6 + .2})`,
            animationDuration: dur + 's',
            animationDelay:    del + 's',
        });
        c.appendChild(s);
    }
})();

// ── Toggle password visibility ────────────────────────────────────────────────
function togglePwd(id, btn) {
    const f   = document.getElementById(id);
    const ico = btn.querySelector('.material-symbols-outlined');
    const show = f.type === 'password';
    f.type          = show ? 'text'       : 'password';
    ico.textContent = show ? 'visibility' : 'visibility_off';
    f.focus();
}

// ── Password strength ─────────────────────────────────────────────────────────
const sFill  = document.getElementById('sFill');
const sLabel = document.getElementById('sLabel');
const r1 = document.getElementById('r1'), r2 = document.getElementById('r2');
const r3 = document.getElementById('r3'), r4 = document.getElementById('r4');

const COLORS = ['#EF4444','#F59E0B','#10B981','#059669'];
const LABELS = ['Too short','Weak','Fair','Strong'];

function evalPwd(pw) {
    const reqs = {
        len:   pw.length >= 8,
        upper: /[A-Z]/.test(pw),
        num:   /[0-9]/.test(pw),
        sym:   /[^A-Za-z0-9]/.test(pw),
    };
    return { reqs, score: Object.values(reqs).filter(Boolean).length };
}

const pwdInp = document.getElementById('password');
if (pwdInp) {
    pwdInp.addEventListener('input', function () {
        const v = this.value;
        if (!v) {
            sFill.style.width = '0';
            sLabel.textContent = '';
            [r1,r2,r3,r4].forEach(d => d.classList.remove('met'));
            return;
        }
        const { reqs, score } = evalPwd(v);
        const pct = score === 0 ? 8 : score * 25;
        const col = COLORS[Math.max(0, score - 1)];
        sFill.style.width      = pct + '%';
        sFill.style.background = col;
        sLabel.textContent     = LABELS[Math.max(0, score - 1)];
        sLabel.style.color     = col;
        r1.classList.toggle('met', reqs.len);
        r2.classList.toggle('met', reqs.upper);
        r3.classList.toggle('met', reqs.num);
        r4.classList.toggle('met', reqs.sym);
        checkMatch();
    });
}

// ── Password match indicator ──────────────────────────────────────────────────
const confInp  = document.getElementById('confirm_password');
const matchPill = document.getElementById('matchPill');

function checkMatch() {
    if (!confInp || !confInp.value) return;
    const ok = pwdInp.value === confInp.value;
    confInp.classList.toggle('is-ok',    ok);
    confInp.classList.toggle('is-error', !ok);
    confInp.setCustomValidity(ok ? '' : 'Passwords do not match');
    if (matchPill) {
        matchPill.className  = 'match-pill ' + (ok ? 'ok' : 'no');
        matchPill.textContent = ok ? '✓ match' : '✗ no match';
    }
}
if (confInp) confInp.addEventListener('input', checkMatch);

// ── Username live validation ──────────────────────────────────────────────────
const usernameInp = document.getElementById('username');
if (usernameInp) {
    usernameInp.addEventListener('input', function () {
        const ok = this.value.length >= 3;
        this.classList.toggle('is-ok',    ok && this.value.length > 0);
        this.classList.toggle('is-error', !ok && this.value.length > 0);
    });
}

// ── Email domain validation ───────────────────────────────────────────────────
const emailInp = document.getElementById('email');
const ALLOWED  = ['mbcradio.net', 'dzrh.com.ph'];
if (emailInp) {
    emailInp.addEventListener('blur', function () {
        const val    = this.value.trim();
        const domain = val.split('@')[1] || '';
        const ok     = val === '' || ALLOWED.includes(domain);
        this.classList.toggle('is-error', !ok && val.length > 0);
        this.classList.toggle('is-ok',     ok && val.length > 0);
    });
    emailInp.addEventListener('input', function () {
        this.classList.remove('is-error', 'is-ok');
    });
}

// ── Ripple ────────────────────────────────────────────────────────────────────
document.getElementById('submitBtn')?.addEventListener('mousedown', function (e) {
    const rect = this.getBoundingClientRect();
    const sz   = Math.max(rect.width, rect.height) * 1.5;
    const r    = document.createElement('span');
    r.className = 'ripple-el';
    Object.assign(r.style, {
        width:  sz + 'px',
        height: sz + 'px',
        left:   (e.clientX - rect.left - sz / 2) + 'px',
        top:    (e.clientY - rect.top  - sz / 2) + 'px',
    });
    this.appendChild(r);
    r.addEventListener('animationend', () => r.remove());
});

// ── Form submit ───────────────────────────────────────────────────────────────
const form      = document.getElementById('registerForm');
const submitBtn = document.getElementById('submitBtn');
let submitted   = false;

if (form) {
    form.addEventListener('submit', function (e) {
        if (submitted) { e.preventDefault(); return; }

        const pw = document.getElementById('password')?.value || '';
        const cp = document.getElementById('confirm_password')?.value || '';
        if (pw !== cp) {
            e.preventDefault();
            confInp?.setCustomValidity('Passwords do not match');
            confInp?.reportValidity();
            return;
        }

        submitted = true;
        submitBtn.disabled = true;
        document.getElementById('btnContent').classList.add('hidden');
        document.getElementById('btnSpinner').classList.remove('hidden');
    });
}

// ── Auto-focus ────────────────────────────────────────────────────────────────
window.addEventListener('load', () => {
    const u = document.getElementById('username');
    if (u && !u.value) u.focus();
});
</script>
</body>
</html>