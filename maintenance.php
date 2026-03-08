<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>System Maintenance</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Sora',sans-serif;background:linear-gradient(135deg,#0F0C1E 0%,#1A1535 50%,#130F23 100%);color:#EAE6F8;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{text-align:center;padding:60px 48px;max-width:480px;width:100%;animation:fadeUp .5s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.icon-wrap{width:90px;height:90px;border-radius:24px;background:rgba(253,211,77,.12);border:2px solid rgba(253,211,77,.3);display:flex;align-items:center;justify-content:center;margin:0 auto 28px}
.icon-wrap .material-icons-round{font-size:44px!important;color:#FCD34D}
h1{font-size:28px;font-weight:700;color:#EAE6F8;margin-bottom:12px;letter-spacing:-.01em}
p{font-size:14px;color:#9E98B8;line-height:1.7;margin-bottom:24px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:99px;background:rgba(253,211,77,.1);border:1px solid rgba(253,211,77,.3);color:#FCD34D;font-size:11px;font-weight:700;font-family:'Fira Code',monospace;letter-spacing:.08em}
.badge::before{content:'';width:7px;height:7px;border-radius:50%;background:#FCD34D;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
.divider{margin:28px auto;width:60px;height:2px;background:linear-gradient(90deg,transparent,rgba(124,58,237,.4),transparent)}
.contact{font-size:12px;color:#635D7A}
.contact a{color:#A78BFA;text-decoration:none}.contact a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">
        <span class="material-icons-round">construction</span>
    </div>
    <h1>Under Maintenance</h1>
    <p>We're performing scheduled maintenance to improve your experience. The system will be back online shortly.</p>
    <span class="badge">Maintenance in Progress</span>
    <div class="divider"></div>
    <div class="contact">
        Need urgent access? Contact your system administrator.
    </div>
</div>
</body>
</html>
