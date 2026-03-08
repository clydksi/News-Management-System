<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
require '../db.php';
require '../csrf.php';

$userId  = $_SESSION['user_id'];
$error   = '';
$success = '';

// Load user with dept name
$stmt = $pdo->prepare("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $userId]);
            $user['email'] = $email;
            $success = 'Profile updated successfully.';
        }
    }
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$roleColors = ['user'=>['#7C3AED','#EDE9FE'],'admin'=>['#059669','#ECFDF5'],'superadmin'=>['#DC2626','#FFF1F2']];
$rc = $roleColors[$user['role']] ?? $roleColors['user'];
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Profile — News CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
:root{--purple:#7C3AED;--purple-md:#6D28D9;--purple-dark:#4C1D95;--purple-light:#EDE9FE;--purple-pale:#F5F3FF;--purple-glow:rgba(124,58,237,.18);--ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;--canvas:#F3F1FA;--surface:#FFFFFF;--surface-2:#EEEAF8;--border:#E2DDEF;--border-md:#C9C2E0;--r:13px;--r-sm:8px;--sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);--sh-md:0 4px 16px rgba(60,20,120,.10)}
[data-theme="dark"]{--ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;--canvas:#0E0C18;--surface:#17142A;--surface-2:#1E1A30;--border:#2A2540;--border-md:#362F50;--purple-light:#1E1440;--purple-pale:#150F2E}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sora',sans-serif;background:var(--canvas);color:var(--ink);min-height:100vh;transition:background .2s,color .2s}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.tb-l{display:flex;align-items:center;gap:12px}
.back-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);color:var(--ink-muted);font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .15s;font-family:'Sora',sans-serif}
.back-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.back-btn .material-icons-round{font-size:16px!important}
.tb-title{font-family:'Playfair Display',serif;font-size:18px;color:var(--ink);font-weight:700}
.tb-r{display:flex;align-items:center;gap:10px}
.theme-btn{width:34px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);color:var(--ink-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s}
.theme-btn:hover{border-color:var(--purple);color:var(--purple)}
.theme-btn .material-icons-round{font-size:18px!important}
.page{max-width:860px;margin:0 auto;padding:32px 20px 64px}
.profile-hero{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:28px;margin-bottom:20px;display:flex;align-items:center;gap:20px}
.av{width:72px;height:72px;border-radius:16px;background:var(--purple);color:white;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:32px;font-weight:700;flex-shrink:0}
.hero-info{flex:1}
.hero-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--ink);margin-bottom:4px}
.hero-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.role-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;font-family:'Fira Code',monospace}
.dept-chip{font-size:11px;color:var(--ink-faint);display:flex;align-items:center;gap:4px;font-family:'Fira Code',monospace}
.dept-chip .material-icons-round{font-size:13px!important}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:640px){.grid-2{grid-template-columns:1fr}.profile-hero{flex-direction:column;text-align:center;align-items:center}}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.card-hd{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px}
.card-icon{width:34px;height:34px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;background:var(--purple-light)}
.card-icon .material-icons-round{font-size:17px!important;color:var(--purple)}
.card-title{font-size:14px;font-weight:600;color:var(--ink)}
.card-sub{font-size:11px;color:var(--ink-faint);margin-top:1px;font-family:'Fira Code',monospace}
.card-body{padding:20px}
.fg{margin-bottom:16px}
.fg:last-child{margin-bottom:0}
.fl{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:6px}
.fl .material-icons-round{font-size:14px!important}
.fi{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);outline:none;transition:border-color .15s,box-shadow .15s}
.fi:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}
.fi:read-only{opacity:.65;cursor:not-allowed}
.fh{display:flex;align-items:center;gap:4px;font-size:11px;color:var(--ink-faint);margin-top:5px}
.fh .material-icons-round{font-size:12px!important}
.card-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s;white-space:nowrap}
.btn .material-icons-round{font-size:15px!important}
.btn-purple{background:var(--purple);color:white}
.btn-purple:hover{background:var(--purple-md);box-shadow:0 4px 12px var(--purple-glow)}
.btn-outline{background:var(--surface);border:1px solid var(--border);color:var(--ink-muted)}
.btn-outline:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.alert{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--r-sm);font-size:13px;font-weight:500;margin-bottom:16px}
.alert .material-icons-round{font-size:17px!important}
.alert-err{background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239}
.alert-suc{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46}
.info-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.info-row:last-child{border-bottom:none;padding-bottom:0}
.info-icon{width:32px;height:32px;border-radius:8px;background:var(--canvas);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.info-icon .material-icons-round{font-size:15px!important;color:var(--ink-faint)}
.info-lbl{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:2px}
.info-val{font-size:13px;font-weight:500;color:var(--ink)}
.pw-alert{display:none;align-items:center;gap:7px;padding:10px 14px;border-radius:var(--r-sm);font-size:13px;margin-top:14px}
.pw-alert .material-icons-round{font-size:16px!important}
.pw-err{background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239}
.pw-suc{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46}
</style>
</head>
<body>
<nav class="topbar">
    <div class="tb-l">
        <a href="user_dashboard.php" class="back-btn"><span class="material-icons-round">arrow_back</span>Dashboard</a>
        <div class="tb-title">My Profile</div>
    </div>
    <div class="tb-r">
        <button class="theme-btn" onclick="toggleDark()"><span class="material-icons-round" id="darkIcon">dark_mode</span></button>
    </div>
</nav>

<div class="page">
    <!-- Profile Hero -->
    <div class="profile-hero">
        <div class="av"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
        <div class="hero-info">
            <div class="hero-name"><?= e($user['username']) ?></div>
            <div class="hero-meta">
                <span class="role-chip" style="background:<?= $rc[1] ?>;color:<?= $rc[0] ?>"><?= ucfirst($user['role']) ?></span>
                <span class="dept-chip"><span class="material-icons-round">business</span><?= e($user['dept_name'] ?? 'No Department') ?></span>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Edit Profile -->
        <div class="card">
            <div class="card-hd">
                <div class="card-icon"><span class="material-icons-round">manage_accounts</span></div>
                <div><div class="card-title">Edit Profile</div><div class="card-sub">Update your account information</div></div>
            </div>
            <form method="POST">
                <div class="card-body">
                    <?php if ($error): ?><div class="alert alert-err"><span class="material-icons-round">error</span><?= e($error) ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-suc"><span class="material-icons-round">check_circle</span><?= e($success) ?></div><?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">person</span>Username</label>
                        <input class="fi" type="text" value="<?= e($user['username']) ?>" readonly/>
                        <div class="fh"><span class="material-icons-round">info</span>Username cannot be changed</div>
                    </div>
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">email</span>Email Address</label>
                        <input class="fi" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="your@email.com" required/>
                    </div>
                </div>
                <div class="card-foot">
                    <button type="submit" class="btn btn-purple"><span class="material-icons-round">save</span>Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Account Info -->
        <div class="card">
            <div class="card-hd">
                <div class="card-icon"><span class="material-icons-round">info</span></div>
                <div><div class="card-title">Account Details</div><div class="card-sub">Read-only account information</div></div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-icon"><span class="material-icons-round">badge</span></div>
                    <div><div class="info-lbl">Role</div><div class="info-val"><?= ucfirst($user['role']) ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><span class="material-icons-round">business</span></div>
                    <div><div class="info-lbl">Department</div><div class="info-val"><?= e($user['dept_name'] ?? '—') ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><span class="material-icons-round">schedule</span></div>
                    <div><div class="info-lbl">Account Status</div><div class="info-val"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></div></div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><span class="material-icons-round">verified</span></div>
                    <div><div class="info-lbl">Email Verified</div><div class="info-val"><?= !empty($user['email_verified_at']) ? date('M d, Y', strtotime($user['email_verified_at'])) : 'Not verified' ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card" style="margin-top:20px">
        <div class="card-hd">
            <div class="card-icon"><span class="material-icons-round">lock</span></div>
            <div><div class="card-title">Change Password</div><div class="card-sub">Update your account credentials</div></div>
        </div>
        <div class="card-body">
            <div style="max-width:420px">
                <div class="fg">
                    <label class="fl"><span class="material-icons-round">lock_outline</span>Current Password</label>
                    <input class="fi" type="password" id="currentPassword" placeholder="Enter current password"/>
                </div>
                <div class="fg">
                    <label class="fl"><span class="material-icons-round">vpn_key</span>New Password</label>
                    <input class="fi" type="password" id="newPassword" placeholder="At least 8 characters"/>
                    <div class="fh"><span class="material-icons-round">info</span>Minimum 8 characters</div>
                </div>
                <div class="fg">
                    <label class="fl"><span class="material-icons-round">check_circle_outline</span>Confirm New Password</label>
                    <input class="fi" type="password" id="confirmPassword" placeholder="Re-enter new password"/>
                </div>
                <div id="pwError"   class="pw-alert pw-err"><span class="material-icons-round">error</span><span id="pwErrorText"></span></div>
                <div id="pwSuccess" class="pw-alert pw-suc"><span class="material-icons-round">check_circle</span><span id="pwSuccessText"></span></div>
            </div>
        </div>
        <div class="card-foot">
            <button onclick="changePassword()" class="btn btn-purple"><span class="material-icons-round">save</span>Change Password</button>
        </div>
    </div>
</div>

<script>
function toggleDark(){
    const h=document.documentElement,dark=h.dataset.theme==='dark';
    h.dataset.theme=dark?'light':'dark';
    localStorage.setItem('theme',dark?'light':'dark');
    document.getElementById('darkIcon').textContent=dark?'dark_mode':'light_mode';
}
(function(){
    const t=localStorage.getItem('theme')||'light';
    document.documentElement.dataset.theme=t;
    if(t==='dark') document.getElementById('darkIcon').textContent='light_mode';
})();

function changePassword(){
    const cur=document.getElementById('currentPassword').value;
    const nw=document.getElementById('newPassword').value;
    const cnf=document.getElementById('confirmPassword').value;
    const err=document.getElementById('pwError'),suc=document.getElementById('pwSuccess');
    err.style.display='none';suc.style.display='none';
    if(!cur||!nw||!cnf){document.getElementById('pwErrorText').textContent='Please fill in all fields.';err.style.display='flex';return;}
    if(nw.length<8){document.getElementById('pwErrorText').textContent='New password must be at least 8 characters.';err.style.display='flex';return;}
    if(nw!==cnf){document.getElementById('pwErrorText').textContent='Passwords do not match.';err.style.display='flex';return;}
    fetch('function/change_password.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current_password:cur,new_password:nw})})
        .then(r=>r.json())
        .then(data=>{
            if(data.success){
                document.getElementById('pwSuccessText').textContent='Password changed successfully!';
                suc.style.display='flex';
                document.getElementById('currentPassword').value='';
                document.getElementById('newPassword').value='';
                document.getElementById('confirmPassword').value='';
            }else{
                document.getElementById('pwErrorText').textContent=data.message||'Failed to change password.';
                err.style.display='flex';
            }
        })
        .catch(()=>{document.getElementById('pwErrorText').textContent='An error occurred.';err.style.display='flex';});
}
</script>
</body>
</html>
