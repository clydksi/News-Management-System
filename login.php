<?php
session_start();
require 'db.php';
require 'csrf.php';

$error = '';

// --- Rate limiting ---
$maxAttempts = 5;
$lockoutSeconds = 300; // 5 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt'] = time();
}

$isLockedOut = false;
if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $elapsed = time() - $_SESSION['login_first_attempt'];
    if ($elapsed < $lockoutSeconds) {
        $remaining = $lockoutSeconds - $elapsed;
        $error = "Too many failed attempts. Please wait " . ceil($remaining / 60) . " minute(s).";
        $isLockedOut = true;
    } else {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    // CSRF check
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($username === '' || $password === '') {
            $error = "Please enter both username and password!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Reset rate limit on success
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_first_attempt'] = time();

                $update = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $update->execute([$user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/admin_dashboard.php");
                } else {
                    header("Location: user/user_dashboard.php");
                }
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $error = "Invalid credentials!";
            }
        }
    }
}

$savedUsername         = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
$savedUsernameForResend = $savedUsernameForResend ?? $savedUsername;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="robots" content="noindex, nofollow"/>
    <title>Sign In — NewsRoom</title>

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
                        'fade-in':   'fadeIn .4s ease both',
                        'shake':     'shake .5s ease',
                        'pulse-dot': 'pulseDot 1.4s ease-in-out infinite',
                        'spin-slow': 'spin 1s linear infinite',
                        'slide-down':'slideDown .3s cubic-bezier(.16,1,.3,1) both',
                    },
                    keyframes: {
                        fadeUp:    { from:{ opacity:0, transform:'translateY(20px)' }, to:{ opacity:1, transform:'none' } },
                        fadeIn:    { from:{ opacity:0 }, to:{ opacity:1 } },
                        shake:     { '0%,100%':{ transform:'translateX(0)' }, '20%,60%':{ transform:'translateX(-6px)' }, '40%,80%':{ transform:'translateX(6px)' } },
                        pulseDot:  { '0%,100%':{ opacity:1, transform:'scale(1)' }, '50%':{ opacity:.4, transform:'scale(.8)' } },
                        slideDown: { from:{ opacity:0, transform:'translateY(-8px)' }, to:{ opacity:1, transform:'none' } },
                    }
                }
            }
        }
    </script>

    <style>
        * { -webkit-font-smoothing: antialiased; }

        .bg-mesh {
            background-color: #0d0618;
            background-image:
                radial-gradient(ellipse 80% 60% at 20% 10%,  rgba(124,58,237,.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%,  rgba(79,70,229,.18)  0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%,  rgba(167,139,250,.08) 0%, transparent 70%);
        }

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
            top: 45%; left: 55%;
            animation: orbFloat 24s ease-in-out infinite 6s;
        }
        @keyframes orbFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33%     { transform: translate(25px,-20px) scale(1.04); }
            66%     { transform: translate(-20px,25px) scale(.97); }
        }

        .star { position:absolute; border-radius:50%; pointer-events:none; animation:twinkle ease-in-out infinite; }
        @keyframes twinkle {
            0%,100% { opacity:.7; transform:scale(1); }
            50%     { opacity:.15; transform:scale(.6); }
        }

        .grid-overlay {
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 60px 60px;
        }

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
        .field.is-valid {
            border-color: #34D399;
            box-shadow: 0 0 0 3px rgba(52,211,153,.14);
        }

        .field-icon { transition: color .25s; }

        .btn-sign-in {
            background: linear-gradient(135deg, #7C3AED 0%, #5B21B6 100%);
            box-shadow: 0 4px 24px rgba(124,58,237,.45), inset 0 1px 0 rgba(255,255,255,.12);
            transition: transform .2s, box-shadow .2s, filter .2s;
            position: relative; overflow: hidden;
        }
        .btn-sign-in::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.12), transparent);
            opacity: 0; transition: opacity .2s;
        }
        .btn-sign-in:hover:not(:disabled)::before { opacity: 1; }
        .btn-sign-in:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(124,58,237,.55), inset 0 1px 0 rgba(255,255,255,.12);
        }
        .btn-sign-in:active:not(:disabled) { transform: translateY(0); }
        .btn-sign-in:disabled { opacity: .55; cursor: not-allowed; }

        .ripple-el {
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,.28);
            transform: scale(0);
            animation: rippleAnim .65s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleAnim { to { transform:scale(4.5); opacity:0; } }

        .caps-badge {
            position: absolute; right: 48px; top: 50%; transform: translateY(-50%);
            background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.35);
            color: #FCD34D; font-size: 9px; font-weight: 800;
            padding: 2px 7px; border-radius: 5px; letter-spacing: .1em;
            pointer-events: none; opacity: 0; transition: opacity .2s;
        }
        .caps-badge.show { opacity: 1; }

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

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(124,58,237,.35); border-radius: 9px; }

        .success-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(8,4,20,.96);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity .5s ease;
        }
        .success-overlay.show { opacity: 1; pointer-events: all; }

        .modal-wrap {
            position: fixed; inset: 0; z-index: 100;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            background: rgba(0,0,0,.55); backdrop-filter: blur(6px);
            opacity: 0; pointer-events: none; transition: opacity .3s;
        }
        .modal-wrap.open { opacity: 1; pointer-events: all; }
        .modal-box {
            transform: scale(.94) translateY(8px);
            transition: transform .3s cubic-bezier(.16,1,.3,1);
        }
        .modal-wrap.open .modal-box { transform: scale(1) translateY(0); }

        .or-line::before, .or-line::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(255,255,255,.08);
        }

        .toast-item {
            animation: slideDown .3s cubic-bezier(.16,1,.3,1) both;
        }
    </style>
</head>

<body class="font-sans min-h-screen text-white bg-mesh overflow-x-hidden">

<!-- ══ BACKGROUND LAYERS ══════════════════════════════════════════════════ -->
<div class="fixed inset-0 grid-overlay pointer-events-none z-0"></div>
<div class="orb orb-1 fixed z-0"></div>
<div class="orb orb-2 fixed z-0"></div>
<div class="orb orb-3 fixed z-0"></div>
<div id="starField" class="fixed inset-0 pointer-events-none z-0 overflow-hidden"></div>

<!-- ══ SUCCESS OVERLAY ════════════════════════════════════════════════════ -->
<div class="success-overlay" id="successOverlay">
    <div class="text-center animate-fade-up">
        <div class="relative w-24 h-24 mx-auto mb-8">
            <div class="absolute inset-0 rounded-full bg-emerald-500/10 border border-emerald-500/20 animate-ping"></div>
            <div class="relative w-24 h-24 rounded-full bg-emerald-500/15 border border-emerald-400/40 flex items-center justify-center">
                <span class="material-symbols-outlined text-5xl text-emerald-400" style="font-variation-settings:'FILL' 1">check_circle</span>
            </div>
        </div>
        <h2 class="text-3xl font-extrabold mb-2 tracking-tight">Welcome back!</h2>
        <p class="text-white/50 text-sm mb-8">Authentication successful — redirecting you now</p>
        <div class="flex gap-1.5 justify-center">
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot"></span>
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot" style="animation-delay:.18s"></span>
            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse-dot" style="animation-delay:.36s"></span>
        </div>
    </div>
</div>

<!-- ══ MAIN LAYOUT ════════════════════════════════════════════════════════ -->
<div class="relative z-10 flex min-h-screen">

    <!-- ───────────────── LEFT PANEL (desktop) ───────────────── -->
    <div class="hidden lg:flex lg:w-[48%] xl:w-[45%] flex-col justify-between p-12 xl:p-16 relative overflow-hidden">

        <!-- Logo -->
        <a href="#" class="flex items-center gap-3 w-fit">
            <div class="w-10 h-10 rounded-xl glass flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-400 text-xl" style="font-variation-settings:'FILL' 1">newspaper</span>
            </div>
            <span class="text-base font-bold tracking-tight">NewsRoom</span>
        </a>

        <!-- Center content -->
        <div class="flex-1 flex flex-col justify-center py-16">
            <div class="inline-flex items-center gap-2 glass-subtle rounded-full px-4 py-1.5 w-fit mb-8">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse-dot"></span>
                <span class="text-xs text-white/50 font-medium">All systems operational</span>
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
        <?php endif; ?>

        <form method="post" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
                <span class="icon">👤</span>
            </div>
        </div>
    </div>

    <!-- ───────────────── RIGHT PANEL (form) ───────────────── -->
    <div class="flex-1 flex flex-col items-center justify-center p-5 sm:p-10 lg:p-14 relative">

        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-2 mb-10 self-start">
            <div class="w-9 h-9 rounded-xl glass flex items-center justify-center">
                <span class="material-symbols-outlined text-purple-400 text-lg" style="font-variation-settings:'FILL' 1">newspaper</span>
            </div>
            <span class="text-sm font-bold tracking-tight">NewsRoom</span>
        </div>

        <div class="w-full max-w-[420px] animate-fade-up">

            <!-- Heading -->
            <div class="mb-8">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-purple-400 mb-3">Welcome back</p>
                <h1 class="text-[2rem] font-extrabold tracking-tight leading-none mb-2">Sign in to your account</h1>
                <p class="text-white/40 text-sm">Enter your credentials to access the dashboard.</p>
            </div>

            <!-- ── Success Banner ── -->
            <?php if (!empty($success)): ?>
            <div class="mb-6 glass rounded-xl px-4 py-3.5 flex items-start gap-3 animate-slide-down"
                 style="border-color:rgba(52,211,153,.3); background:rgba(16,185,129,.1)">
                <span class="material-symbols-outlined text-emerald-400 text-xl flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">check_circle</span>
                <p class="text-sm text-emerald-300 font-medium leading-snug">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- ── Error Banner ── -->
            <?php if (!empty($error)): ?>
            <div class="mb-6 glass rounded-xl px-4 py-3.5 flex items-start gap-3
                        border-red-500/30 bg-red-500/10 animate-shake" style="border-color:rgba(239,68,68,.3)">
                <span class="material-symbols-outlined text-red-400 text-xl flex-shrink-0 mt-0.5">error</span>
                <p class="text-sm text-red-300 font-medium leading-snug">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- ── Email Not Verified Banner ── -->
            <?php if ($warning === 'unverified'): ?>
            <div class="mb-6 glass rounded-xl px-4 py-4 flex flex-col gap-3 animate-shake"
                 style="border-color:rgba(251,191,36,.3); background:rgba(245,158,11,.08)">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-400 text-xl flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">mark_email_unread</span>
                    <div>
                        <p class="text-sm text-amber-300 font-semibold leading-snug mb-1">Email not verified</p>
                        <p class="text-xs text-amber-300/70 leading-relaxed">
                            Please verify your <strong>@mbcradio.net</strong> email before signing in.
                            Check your inbox for the verification link, or request a new one below.
                        </p>
                    </div>
                </div>
                <!-- Resend form -->
                <form method="POST" class="flex gap-2 mt-1">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
                    <input type="hidden" name="action"     value="resend_verification"/>
                    <input type="hidden" name="username"   value="<?= $savedUsernameForResend ?>"/>
                    <button type="submit"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold
                                   bg-amber-500/20 border border-amber-400/30 text-amber-300
                                   hover:bg-amber-500/30 transition-all">
                        <span class="material-symbols-outlined text-sm">forward_to_inbox</span>
                        Resend verification email
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── Form Card ── -->
            <div class="glass rounded-2xl p-7 sm:p-8">
                <form method="POST" id="loginForm" novalidate autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
                    <input type="hidden" name="action"     value="login"/>

                    <!-- Username -->
                    <div class="mb-5">
                        <label for="username" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40 mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <span id="userIcon" class="field-icon material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-white/30 text-[20px] pointer-events-none">
                                person
                            </span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= $savedUsername ?>"
                                placeholder="Enter your username"
                                autocomplete="username"
                                required
                                class="field w-full rounded-xl pl-12 pr-4 py-3.5 text-sm font-medium <?= (!empty($error) || $warning === 'unverified') ? 'is-error' : '' ?>"
                            />
                        </div>
                        <p class="text-red-400 text-xs mt-1.5 hidden" id="usernameError">
                            <span class="material-symbols-outlined text-xs align-middle">error</span>
                            Username is required.
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-[11px] font-bold uppercase tracking-[.14em] text-white/40">
                                Password
                            </label>
                            <button type="button" onclick="openForgotModal()"
                                    class="text-[11px] font-semibold text-purple-400 hover:text-purple-300 transition-colors">
                                Forgot password?
                            </button>
                        </div>
                        <div class="relative">
                            <span id="lockIcon" class="field-icon material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-white/30 text-[20px] pointer-events-none">
                                lock
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                class="field w-full rounded-xl pl-12 pr-24 py-3.5 text-sm font-medium <?= (!empty($error) || $warning === 'unverified') ? 'is-error' : '' ?>"
                            />
                            <!-- Caps lock -->
                            <span class="caps-badge" id="capsBadge">CAPS</span>
                            <!-- Visibility toggle -->
                            <button type="button" id="togglePassword" tabindex="-1"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9
                                           flex items-center justify-center rounded-lg
                                           text-white/30 hover:text-white/70
                                           hover:bg-white/8 transition-all">
                                <span class="material-symbols-outlined text-[18px]" id="toggleIcon">visibility_off</span>
                            </button>
                        </div>
                        <p class="text-red-400 text-xs mt-1.5 hidden" id="passwordError">
                            <span class="material-symbols-outlined text-xs align-middle">error</span>
                            Password is required.
                        </p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2.5 mb-7">
                        <div class="relative w-4 h-4 flex-shrink-0">
                            <input type="checkbox" id="rememberCheck" name="remember"
                                   class="peer appearance-none w-4 h-4 rounded border border-white/20
                                          bg-white/8 checked:bg-primary checked:border-primary
                                          focus:ring-2 focus:ring-primary/40 cursor-pointer transition-all"/>
                            <span class="material-symbols-outlined absolute inset-0 text-[13px] text-white
                                         flex items-center justify-center opacity-0 peer-checked:opacity-100
                                         pointer-events-none" style="font-variation-settings:'FILL' 1">check</span>
                        </div>
                        <label for="rememberCheck" class="text-xs text-white/45 cursor-pointer select-none hover:text-white/65 transition-colors">
                            Keep me signed in for 30 days
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="loginBtn"
                            class="btn-sign-in w-full h-[52px] rounded-xl text-sm font-bold tracking-wide
                                   text-white flex items-center justify-center gap-2 overflow-hidden">
                        <span id="btnContent" class="flex items-center gap-2 pointer-events-none">
                            <span class="material-symbols-outlined text-xl" style="font-variation-settings:'FILL' 1">login</span>
                            <span id="btnText">Sign In</span>
                        </span>
                        <span id="btnSpinner" class="hidden pointer-events-none">
                            <svg class="animate-spin-slow w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="3.5"/>
                                <path class="opacity-80" fill="white" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                        </span>
                    </button>

                    <!-- Divider -->
                    <div class="flex items-center gap-3 or-line my-6">
                        <span class="text-[11px] text-white/25 font-medium px-1 whitespace-nowrap">or sign in with</span>
                    </div>

                    <!-- Alternate sign-in -->
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" onclick="showSSOToast()"
                                class="flex items-center justify-center gap-2 h-11 rounded-xl
                                       glass-subtle hover:bg-white/8 border-white/8
                                       text-sm font-medium text-white/55 hover:text-white/80 transition-all">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.24 10.285V14.4h6.806c-.275 1.765-2.056 5.174-6.806 5.174-4.095 0-7.439-3.389-7.439-7.574s3.345-7.574 7.439-7.574c2.33 0 3.891.989 4.785 1.849l3.254-3.138C18.189 1.186 15.479 0 12.24 0c-6.635 0-12 5.365-12 12s5.365 12 12 12c6.926 0 11.52-4.869 11.52-11.726 0-.788-.085-1.39-.189-1.989H12.24z"/>
                            </svg>
                            Google
                        </button>
                        <button type="button" onclick="showSSOToast()"
                                class="flex items-center justify-center gap-2 h-11 rounded-xl
                                       glass-subtle hover:bg-white/8 border-white/8
                                       text-sm font-medium text-white/55 hover:text-white/80 transition-all">
                            <span class="material-symbols-outlined text-xl" style="font-variation-settings:'FILL' 1">badge</span>
                            SSO Login
                        </button>
                    </div>
                </form>
            </div>

            <!-- Register -->
            <p class="text-center mt-7 text-sm text-white/35">
                Don't have an account?
                <a href="register.php" class="font-semibold text-purple-400 hover:text-purple-300 transition-colors">
                    Create one here
                </a>
            </p>

            <!-- Security badges -->
            <div class="flex items-center justify-center gap-5 mt-8 flex-wrap">
                <?php foreach ([
                    ['security',      'SSL Secured'],
                    ['verified_user', 'CSRF Protected'],
                    ['shield',        'Encrypted'],
                ] as [$ico, $lbl]): ?>
                <div class="flex items-center gap-1.5 text-white/20">
                    <span class="material-symbols-outlined text-[15px]"><?= $ico ?></span>
                    <span class="text-[11px] font-medium"><?= $lbl ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ FORGOT PASSWORD MODAL ════════════════════════════════════════════ -->
<div class="modal-wrap" id="forgotModal" onclick="if(event.target===this)closeForgotModal()">
    <div class="glass modal-box rounded-2xl p-8 w-full max-w-[400px]">
        <div class="flex items-start justify-between mb-6">
            <div>
                <div class="w-11 h-11 rounded-xl bg-purple-500/15 border border-purple-400/25 flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-purple-400 text-xl" style="font-variation-settings:'FILL' 1">key</span>
                </div>
                <h3 class="text-xl font-bold mb-1">Reset Password</h3>
                <p class="text-sm text-white/45 leading-relaxed">
                    Enter your username and we'll send a reset link to your registered email address.
                </p>
            </div>
            <button onclick="closeForgotModal()"
                    class="text-white/25 hover:text-white/70 transition-colors ml-4 p-1 rounded-lg hover:bg-white/8 flex-shrink-0">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>

        <form method="POST" id="forgotForm" onsubmit="handleForgotSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
            <input type="hidden" name="action"     value="forgot_password"/>

            <div class="relative mb-5">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-white/30 text-[20px] pointer-events-none">person_search</span>
                <input type="text" id="forgotUsernameInput" name="forgot_username"
                       placeholder="Enter your username"
                       class="field w-full rounded-xl pl-12 pr-4 py-3.5 text-sm font-medium"/>
            </div>

            <button type="submit" id="forgotBtn"
                    class="btn-sign-in w-full h-11 rounded-xl text-sm font-bold text-white flex items-center justify-center gap-2">
                <span id="forgotBtnContent" class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">forward_to_inbox</span>
                    Send Reset Link
                </span>
                <span id="forgotBtnSpinner" class="hidden">
                    <svg class="animate-spin-slow w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="3.5"/>
                        <path class="opacity-80" fill="white" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                </span>
            </button>
        </form>

        <p class="text-xs text-white/25 text-center mt-4 leading-relaxed">
            Reset links are sent to your registered @mbcradio.net email and expire after 1 hour.
        </p>
    </div>
</div>

<!-- ══ TOAST STACK ═══════════════════════════════════════════════════════ -->
<div id="toastStack" class="fixed top-5 right-5 z-[300] flex flex-col gap-2 pointer-events-none max-w-[320px]"></div>

<!-- ══ SCRIPTS ════════════════════════════════════════════════════════════ -->
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

// ── Password visibility ───────────────────────────────────────────────────────
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    const show = pw.type === 'password';
    pw.type          = show ? 'text'       : 'password';
    icon.textContent = show ? 'visibility' : 'visibility_off';
    pw.focus();
});

// ── Caps lock ─────────────────────────────────────────────────────────────────
document.getElementById('password')?.addEventListener('keyup', function (e) {
    const badge = document.getElementById('capsBadge');
    if (badge) badge.classList.toggle('show', !!e.getModifierState?.('CapsLock'));
});

// ── Field validation helpers ──────────────────────────────────────────────────
const fields = {
    username: {
        el:   document.getElementById('username'),
        err:  document.getElementById('usernameError'),
        icon: document.getElementById('userIcon'),
    },
    password: {
        el:   document.getElementById('password'),
        err:  document.getElementById('passwordError'),
        icon: document.getElementById('lockIcon'),
    },
};

function setField(name, state) {
    const f = fields[name];
    f.el.classList.remove('is-error', 'is-valid');
    f.err.classList.add('hidden');
    f.icon.style.color = '';

    if (state === 'error') {
        f.el.classList.add('is-error');
        f.err.classList.remove('hidden');
        f.icon.style.color = 'rgba(248,113,113,.7)';
    } else if (state === 'valid') {
        f.el.classList.add('is-valid');
        f.icon.style.color = 'rgba(52,211,153,.7)';
    }
}

Object.entries(fields).forEach(([name, f]) => {
    f.el?.addEventListener('blur', function () {
        setField(name, this.value.trim() ? 'valid' : 'error');
    });
    f.el?.addEventListener('input', function () {
        if (f.el.classList.contains('is-error') && this.value.trim())
            setField(name, 'valid');
    });
});

// ── Ripple effect ─────────────────────────────────────────────────────────────
document.getElementById('loginBtn')?.addEventListener('mousedown', function (e) {
    const rect = this.getBoundingClientRect();
    const sz   = Math.max(rect.width, rect.height) * 1.5;
    const r    = document.createElement('span');
    r.className = 'ripple-el';
    Object.assign(r.style, {
        width:  sz + 'px',
        height: sz + 'px',
        left:   (e.clientX - rect.left  - sz / 2) + 'px',
        top:    (e.clientY - rect.top   - sz / 2) + 'px',
    });
    this.appendChild(r);
    r.addEventListener('animationend', () => r.remove());
});

// ── Form submit ───────────────────────────────────────────────────────────────
let submitted = false;

document.getElementById('loginForm')?.addEventListener('submit', function (e) {
    if (submitted) { e.preventDefault(); return; }

    const uOk = fields.username.el?.value.trim();
    const pOk = fields.password.el?.value;
    let valid = true;

    if (!uOk) { setField('username', 'error'); valid = false; }
    if (!pOk) { setField('password', 'error'); valid = false; }
    if (!valid) { e.preventDefault(); return; }

    submitted = true;

    const btn     = document.getElementById('loginBtn');
    const content = document.getElementById('btnContent');
    const spinner = document.getElementById('btnSpinner');

    btn.disabled = true;
    content.classList.add('hidden');
    spinner.classList.remove('hidden');

    // Show success overlay after brief delay (real redirect fires on server success)
    setTimeout(() => {
        document.getElementById('successOverlay')?.classList.add('show');
    }, 500);
});

// ── Forgot password modal ─────────────────────────────────────────────────────
function openForgotModal() {
    document.getElementById('forgotModal')?.classList.add('open');
    setTimeout(() => document.getElementById('forgotUsernameInput')?.focus(), 150);
}
function closeForgotModal() {
    document.getElementById('forgotModal')?.classList.remove('open');
}

function handleForgotSubmit(e) {
    e.preventDefault();
    const val = document.getElementById('forgotUsernameInput')?.value.trim();
    if (!val) {
        showToast('Please enter your username.', 'error');
        return;
    }

    // Show loading state
    const btn         = document.getElementById('forgotBtn');
    const btnContent  = document.getElementById('forgotBtnContent');
    const btnSpinner  = document.getElementById('forgotBtnSpinner');
    btn.disabled      = true;
    btnContent.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    // Submit the form for real (server handles email sending)
    e.target.submit();
}

// ── SSO notice ────────────────────────────────────────────────────────────────
function showSSOToast() {
    showToast('SSO / OAuth is not configured for this environment.', 'info');
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
    const stack = document.getElementById('toastStack');
    if (!stack) return;

    const styles = {
        success: { bg:'rgba(16,185,129,.12)', border:'rgba(52,211,153,.3)',  text:'#6EE7B7', icon:'check_circle'  },
        error:   { bg:'rgba(239,68,68,.12)',  border:'rgba(248,113,113,.3)', text:'#FCA5A5', icon:'error'         },
        info:    { bg:'rgba(124,58,237,.12)', border:'rgba(167,139,250,.3)', text:'#C4B5FD', icon:'info'          },
    };
    const s = styles[type] || styles.info;

    const t = document.createElement('div');
    t.className = 'toast-item pointer-events-auto flex items-start gap-3 px-4 py-3.5 rounded-xl text-sm font-medium shadow-xl';
    Object.assign(t.style, {
        background:    s.bg,
        border:        `1px solid ${s.border}`,
        color:         s.text,
        backdropFilter:'blur(16px)',
    });
    t.innerHTML = `
        <span class="material-symbols-outlined text-xl flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">${s.icon}</span>
        <span class="leading-snug">${msg}</span>
    `;
    stack.appendChild(t);

    setTimeout(() => {
        t.style.transition = 'opacity .35s, transform .35s';
        t.style.opacity    = '0';
        t.style.transform  = 'translateX(8px)';
        setTimeout(() => t.remove(), 380);
    }, 3800);
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeForgotModal();
});

// ── Auto-focus ────────────────────────────────────────────────────────────────
window.addEventListener('load', () => {
    const u = document.getElementById('username');
    if (u && !u.value) u.focus();
    else document.getElementById('password')?.focus();
});
</script>
</body>
</html>