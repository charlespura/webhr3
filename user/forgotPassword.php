
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
session_start();
include __DIR__ . '/../dbconnection/mainDB.php';
$FIREBASE_API_KEY = "AIzaSyCQg9yf_oWKyDAE_WApgRnG3q-BEDL6bSc";

$message      = '';
$messageType  = '';
$showResetForm   = false;
$showRequestForm = false;

// ---- Detect Firebase Action ----
$mode    = $_GET['mode']    ?? null;
$oobCode = $_GET['oobCode'] ?? null;

// ✅ 1. Handle Email Verification
if ($mode === 'verifyEmail' && $oobCode) {
    $payload = json_encode(["oobCode" => $oobCode]);

    $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:update?key=$FIREBASE_API_KEY");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['email'])) {
        $email = $response['email'];

        // Update local database to mark email as verified
        $stmt = $conn->prepare("UPDATE users SET is_verified=1 WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        $message = "✅ Your email ($email) has been verified! You can now log in.";
        $messageType = "success";
    } else {
        $message = "❌ Email verification failed. Please try again.";
        $messageType = "error";
    }
}
// ✅ 2. Show password reset form if link clicked
elseif ($mode === 'resetPassword' && $oobCode) {
    $showResetForm = true;
}
// ✅ 3. Send password reset email request
elseif (isset($_POST['send_reset'])) {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $message = "Please enter your email.";
        $messageType = "error";
    } else {
        // Check if user exists locally
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? AND is_active=1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $message = "No active user found with that email.";
            $messageType = "error";
        } else {
            // Firebase REST API: send password reset email
            $payload = json_encode([
                "requestType" => "PASSWORD_RESET",
                "email"       => $email
            ]);

            $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=$FIREBASE_API_KEY");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($response['error'])) {
                $message = "Firebase error: " . $response['error']['message'];
                $messageType = "error";
            } else {
                $message = "Reset link sent! Check your email and follow the link to reset your password.";
                $messageType = "success";
            }
        }
    }
    $showRequestForm = true;
}
// ✅ 4. Process password reset form
elseif (isset($_POST['reset_password'])) {
    $oobCode         = $_POST['oobCode'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$oobCode || !$newPassword || !$confirmPassword) {
        $message = "All fields are required.";
        $messageType = "error";
        $showResetForm = true;
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";
        $showResetForm = true;
    } elseif (strlen($newPassword) < 8) {
        $message = "Password must be at least 8 characters.";
        $messageType = "error";
        $showResetForm = true;
    } else {
        $payload = json_encode([
            "oobCode"     => $oobCode,
            "newPassword" => $newPassword
        ]);

        $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:resetPassword?key=$FIREBASE_API_KEY");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['error'])) {
            $message = "Firebase error: " . $response['error']['message'];
            $messageType = "error";
            $showResetForm = true;
        } else {
            $email = $response['email'] ?? null;

            if ($email) {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE email=?");
                $stmt->bind_param("ss", $newHash, $email);
                $stmt->execute();
                $stmt->close();

                $message = "✅ Password updated successfully! You can now login.";
                $messageType = "success";
            }
        }
    }
}
// ✅ 5. Default view is request reset form
else {
    $showRequestForm = true;
}
?>


<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>ATIERA — Secure Login</title>
<link rel="icon" href="/public_html/picture/logo2.png">
<script src="https://cdn.tailwindcss.com"></script>

<style>
  :root{
    --blue-600:#1b2f73; --blue-700:#15265e; --blue-800:#0f1c49; --blue-a:#2342a6;
    --gold:#d4af37; --ink:#0f172a; --muted:#64748b;
    --ring:0 0 0 3px rgba(35,66,166,.28);
    --card-bg: rgba(255,255,255,.95); --card-border: rgba(226,232,240,.9);
    --wm-opa-light:.35; --wm-opa-dark:.55;
  }
  @media (prefers-color-scheme: dark){ :root{ --ink:#e5e7eb; --muted:#9ca3af; } }

  /* ===== Background ===== */
  body{
    min-height:100svh; margin:0; color:var(--ink);
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(255,255,255,.18) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.08) 0, transparent 40%),
      linear-gradient(140deg, rgba(15,28,73,1) 50%, rgba(255,255,255,1) 50%);
  }
  html.dark body{
    background:
      radial-gradient(70% 60% at 8% 10%, rgba(212,175,55,.08) 0, transparent 60%),
      radial-gradient(40% 40% at 100% 0%, rgba(212,175,55,.12) 0, transparent 40%),
      linear-gradient(140deg, rgba(7,12,38,1) 50%, rgba(11,21,56,1) 50%);
    color:#e5e7eb;
  }

  /* ===== Watermark ===== */
  .bg-watermark{ position:fixed; inset:0; z-index:-1; display:grid; place-items:center; pointer-events:none; }
  .bg-watermark img{
    width:min(820px,70vw); max-height:68vh; object-fit:contain; opacity:var(--wm-opa-light);
    filter: drop-shadow(0 0 26px rgba(255,255,255,.40)) drop-shadow(0 14px 34px rgba(0,0,0,.25));
    transition:opacity .25s ease, filter .25s ease, transform .6s ease;
  }
  html.dark .bg-watermark img{
    opacity:var(--wm-opa-dark);
    filter: drop-shadow(0 0 34px rgba(255,255,255,.55)) drop-shadow(0 16px 40px rgba(0,0,0,.30));
  }

  .reveal { opacity:0; transform:translateY(8px); animation:reveal .45s .05s both; }
  @keyframes reveal { to { opacity:1; transform:none; } }

  /* ===== Card ===== */
  .card{
    background:var(--card-bg); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border:1px solid var(--card-border); border-radius:18px; box-shadow:0 16px 48px rgba(2,6,23,.18);
  }
  html.dark .card{ background:rgba(17,24,39,.92); border-color:rgba(71,85,105,.55); box-shadow:0 16px 48px rgba(0,0,0,.5); }

  /* ===== Inputs ===== */
  .field{ position:relative; }
  .input{
    width:100%; border:1px solid #e5e7eb; border-radius:12px; background:#fff;
    padding:1rem 2.6rem 1rem .95rem; outline:none; color:#0f172a; transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .input:focus{ border-color:var(--blue-a); box-shadow:var(--ring) }
  html.dark .input{ background:#0b1220; border-color:#243041; color:#e5e7eb; }

  
  .float-label{
    position:absolute; left:.9rem; top:50%; transform:translateY(-50%); padding:0 .25rem; color:#94a3b8;
    pointer-events:none; background:transparent; transition:all .15s ease;
  }
  .input:focus + .float-label,
  .input:not(:placeholder-shown) + .float-label{
    top:0; transform:translateY(-50%) scale(.92); color:var(--blue-a); background:#fff;
  }
  html.dark .input:focus + .float-label,
  html.dark .input:not(:placeholder-shown) + .float-label{ background:#0b1220; }
  .icon-right{ position:absolute; right:.6rem; top:50%; transform:translateY(-50%); color:#64748b; }
  html.dark .icon-right{ color:#94a3b8; }

  /* ===== Buttons ===== */
  .btn{
    width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(180deg, var(--blue-600), var(--blue-800));
    color:#fff; font-weight:800; border-radius:14px; padding:.95rem 1rem; border:1px solid rgba(255,255,255,.06);
    transition:transform .08s ease, filter .15s ease, box-shadow .2s ease; box-shadow:0 8px 18px rgba(2,6,23,.18);
  }
  .btn:hover{ filter:saturate(1.08); box-shadow:0 12px 26px rgba(2,6,23,.26); }
  .btn:active{ transform:translateY(1px) scale(.99); }
  .btn[disabled]{ opacity:.85; cursor:not-allowed; }

  /* ===== Alerts (inline attempts/info) ===== */
  .alert{ border-radius:12px; padding:.65rem .8rem; font-size:.9rem }
  .alert-error{ border:1px solid #fecaca; background:#fef2f2; color:#b91c1c }
  .alert-info{ border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3 }
  html.dark .alert-error{ background:#3f1b1b; border-color:#7f1d1d; color:#fecaca }
  html.dark .alert-info{ background:#1e1b4b; border-color:#3730a3; color:#c7d2fe }

  /* ===== Popup animations (slow) ===== */
  @keyframes popSpring { 0%{transform:scale(.92);opacity:0} 60%{transform:scale(1.04);opacity:1} 85%{transform:scale(.98)} 100%{transform:scale(1)} }
  @keyframes fadeBackdrop { from{opacity:0} to{opacity:1} }
  @keyframes ripple { 0%{transform:scale(.6);opacity:.35} 70%{transform:scale(1.4);opacity:.18} 100%{transform:scale(1.8);opacity:0} }
  @keyframes slideUp { from { transform: translateY(6px) } to { transform: translateY(0) } }
  @keyframes shakeX { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(2px)} }
  @media (prefers-reduced-motion: reduce){
    #popupCard, #popupBackdrop, #popupTitle, #popupMsg { animation: none !important }
  }
  .popup-success #popupIconWrap{ background:linear-gradient(180deg,#16a34a,#15803d) }
  .popup-info    #popupIconWrap{ background:linear-gradient(180deg,#2563eb,#1d4ed8) }
  .popup-error   #popupIconWrap{ background:linear-gradient(180deg,#ef4444,#b91c1c) }
  .popup-goodbye #popupIconWrap{ background:linear-gradient(180deg,var(--blue-600),var(--blue-800)) }

  .typing::after{ content:'|'; margin-left:2px; opacity:.6; animation: blink 1s steps(1) infinite; }
  @keyframes blink { 50%{opacity:0} }
</style>
</head>


<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10">

<!-- Watermark -->
<div class="bg-watermark" aria-hidden="true">
    <img src="/public_html/picture/logo.png" alt="ATIERA watermark" id="wm">
</div>

<!-- Left panel -->
<section class="hidden md:flex w-full h-full items-center justify-center">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="/public_html/picture/logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        ATIERA <span style="color:var(--gold)">HOTEL & RESTAURANT</span> HR3 <br>
      </h1>
      <p class="mt-4 text-white/90 text-lg">Secure • Fast • Intuitive</p>
    </div>
</section>

<!-- Right panel -->
<main class="w-full max-w-md md:ml-auto">
<div id="card" class="card p-6 sm:p-8 reveal">

  <div class="flex items-center justify-between mb-4">
    <div class="md:hidden flex items-center gap-3">
      <img src="/public_html/picture/logo2.png" alt="ATIERA" class="h-10 w-auto">
      <div>
        <div class="text-sm font-semibold leading-4">ATIERA</div>
        <div class="text-[10px] text-[color:var(--muted)]">
            HOTEL & RESTAURANT<span class="font-medium" style="color:var(--gold)">HR3</span>
        </div>
      </div>
    </div>
    <button id="modeBtn" class="px-3 py-2 rounded-lg border border-slate-200 text-sm
            hover:bg-white/60 dark:hover:bg-slate-800" aria-pressed="false"
            title="Toggle dark mode">🌓</button>
  </div>

  <h3 class="text-xl font-bold text-slate-500 dark:text-slate-400 mb-4">Account Actions</h3>

  <?php if ($message): ?>
    <div class="<?= $messageType === 'success'
                  ? 'bg-green-100 text-green-700'
                  : 'bg-red-100 text-red-700' ?> p-3 rounded mb-4 text-center">
      <?= htmlspecialchars($message) ?>

      <?php if ($mode === 'verifyEmail' && $messageType === 'success'): ?>
        <!-- ✅ Show Login button when email verification is successful -->
        <a href="/public_html/index.php" class="btn block text-center mt-5">
            <span>Login</span>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($showResetForm): ?>
      <!-- Password reset form -->
      <form method="POST">
          <input type="hidden" name="oobCode" value="<?= htmlspecialchars($oobCode) ?>">
          <label class="block mb-2">New Password:</label>
          <input type="password" name="new_password" class="w-full border p-2 rounded mb-4" required>
          <label class="block mb-2">Confirm Password:</label>
          <input type="password" name="confirm_password" class="w-full border p-2 rounded mb-4" required>
          <button type="submit" name="reset_password" class="w-full btn">Reset Password</button>
      </form>
      <a href="/public_html/index.php" class="btn block text-center mt-5"><span>Login</span></a>

  <?php elseif ($showRequestForm): ?>
      <!-- Send reset link form -->
      <form method="POST" class="space-y-4">
          <div class="field">
            <input id="email" name="email" type="email" class="input peer" placeholder=" " required>
            <label for="email" class="float-label">Enter your email</label>
          </div>
          <button type="submit" name="send_reset" class="btn w-full">Send Reset Link</button>
      </form>
      <a href="/public_html/index.php" class="btn block text-center mt-5"><span>Login</span></a>
  <?php endif; ?>

  <p class="text-xs text-center text-slate-500 dark:text-slate-400 mt-4">
    © 2025 ATIERA BSIT 4101 CLUSTER 1
  </p>

</div>
</main>


<script>
  const $ = (s, r=document)=>r.querySelector(s);

  // Elements
  const form      = $('#loginForm');
  const userEl    = $('#username');
  const pwEl      = $('#password');
  const toggle    = $('#togglePw');
  const eyeOn     = $('#eyeOn');
  const eyeOff    = $('#eyeOff');
  const alertBox  = $('#alert');
  const infoBox   = $('#info');
  const submitBtn = $('#submitBtn');
  const btnText   = $('#btnText');
  const capsNote  = $('#capsNote');
  const pwBar     = $('#pwBar');
  const pwLabel   = $('#pwLabel');
  const modeBtn   = $('#modeBtn');
  const wmImg     = $('#wm');

  /* ---------- Dark mode toggle ---------- */
  modeBtn.addEventListener('click', ()=>{
    const root = document.documentElement;
    const dark = root.classList.toggle('dark');
    modeBtn.setAttribute('aria-pressed', String(dark));
    wmImg.style.transform = 'scale(1.01)'; setTimeout(()=> wmImg.style.transform = '', 220);
  });

  /* ---------- Alerts helpers ---------- */
  const showError = (msg)=>{ alertBox.textContent = msg; alertBox.classList.remove('hidden'); };
  const hideError = ()=> alertBox.classList.add('hidden');
  const showInfo  = (msg)=>{ infoBox.textContent = msg; infoBox.classList.remove('hidden'); };
  const hideInfo  = ()=> infoBox.classList.add('hidden');

  /* ---------- Caps Lock chip ---------- */
  function caps(e){
    const on = e.getModifierState && e.getModifierState('CapsLock');
    if (capsNote) capsNote.classList.toggle('hidden', !on);
  }
  pwEl.addEventListener('keyup', caps);
  pwEl.addEventListener('keydown', caps);

  /* ---------- Password meter ---------- */
  pwEl.addEventListener('input', () => {
    const v = pwEl.value;
    let score = 0;
    if (v.length >= 6) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const widths = ['12%','38%','64%','88%','100%'];
    const labels = ['weak','fair','okay','good','strong'];
    pwBar.style.width = widths[score];
    pwLabel.textContent = labels[score];
  });

  /* ---------- Show/Hide password ---------- */
  toggle.addEventListener('click', () => {
    const show = pwEl.type === 'password';
    pwEl.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(show));
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    eyeOn.classList.toggle('hidden', show);
    eyeOff.classList.toggle('hidden', !show);
    pwEl.focus();
  });

  /* ---------- Popup (success & goodbye only, slow animation) ---------- */
  (() => {
    const backdrop  = $('#popupBackdrop');
    const root      = $('#popupRoot');
    const card      = $('#popupCard');
    const titleEl   = $('#popupTitle');
    const msgEl     = $('#popupMsg');
    const okBtn     = $('#popupOkBtn');
    const icon      = $('#popupIcon');
    const ripple    = $('#iconRipple');

    let autoTimer = null;
    let closeResolver = null;
    let typingTimer = null;

    const ICONS = {
      success: `<path d="M9.5 16.2 5.8 12.5l-1.3 1.3 5 5 10-10-1.3-1.3-8.7 8.7Z" fill="currentColor"/>`,
      goodbye: `<path d="M12 2a5 5 0 0 0-5 5v3H5a2 2 0 0 0-2 2v7h18v-7a2 2 0 0 0-2-2h-2V7a5 5 0 0 0-5-5Z" fill="currentColor"/>`
    };

    function setIcon(variant){ icon.innerHTML = ICONS[variant] || ICONS.success; }
    function pulseRipple(){ ripple.style.animation = 'none'; void ripple.offsetWidth; ripple.style.animation = 'ripple .6s ease-out'; }

    function typeMessage(text, speed=30){
      clearInterval(typingTimer);
      msgEl.classList.add('typing');
      msgEl.textContent = '';
      let i = 0;
      typingTimer = setInterval(()=>{
        msgEl.textContent += text.charAt(i++);
        if (i >= text.length) { clearInterval(typingTimer); msgEl.classList.remove('typing'); }
      }, speed);
    }

    function animateIn(variant, title, message){
      root.classList.remove('hidden'); backdrop.classList.remove('hidden');
      backdrop.style.animation = 'fadeBackdrop 3s both';
      card.style.animation     = 'popSpring 3s both';
      titleEl.style.animation  = 'slideUp 3s ease-out both';
      msgEl.style.animation    = 'slideUp 3s ease-out both';
      root.classList.remove('popup-success','popup-goodbye');
      root.classList.add(`popup-${variant}`);
      setIcon(variant);
      titleEl.textContent = title || 'Notice';
      pulseRipple();
      typeMessage(message || '');
      okBtn?.focus({ preventScroll:true });
    }

    function animateOut(){
      backdrop.style.animation = 'fadeBackdrop 2s reverse both';
      card.style.animation     = 'popSpring 2s reverse both';
      setTimeout(() => {
        root.classList.add('hidden'); backdrop.classList.add('hidden');
      }, 160);
    }

    window.showPopup = function({ title='Notice', message='', variant='success', autocloseMs=0, onClose=null } = {}){
      clearTimeout(autoTimer);
      animateIn(variant, title, message);
      if (onClose) closeResolver = onClose;
      if (autocloseMs > 0){ autoTimer = setTimeout(() => { window.hidePopup(); }, autocloseMs); }
    };

    window.hidePopup = function(){
      animateOut();
      if (typeof closeResolver === 'function'){ try { closeResolver(); } catch{} }
      closeResolver = null;
    };

    backdrop.addEventListener('click', () => window.hidePopup());
    okBtn.addEventListener('click', () => window.hidePopup());
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') window.hidePopup(); });
  })();

  /* ---------- Logout popup ---------- */
  (function handleLogout(){
    const q = new URLSearchParams(location.search);
    if (q.get('logout') === '1') {
      sessionStorage.removeItem('atiera_logged_in');
      showPopup({
        title: 'Goodbye, ADMIN 👋',
        message: 'Thank you ADMIN — See you next time!',
        variant: 'goodbye',
        autocloseMs: 4200
      });
    }
  })();

  /* ---------- Auth + lockout ---------- */
  const MAX_TRIES = 5, LOCK_MS = 60_000;
  const triesKey = 'atiera_login_tries';
  const lockKey  = 'atiera_login_lock';
  let   lockTimer = null;

  const num = key => Number(localStorage.getItem(key) || '0');
  const setNum = (key,val) => localStorage.setItem(key, String(val));

  function mmss(ms){
    const s = Math.max(0, Math.ceil(ms/1000));
    const m = Math.floor(s/60);
    const r = s % 60;
    return (m? `${m}:${String(r).padStart(2,'0')}` : `${r}s`);
  }

  function startLockCountdown(until){
    clearInterval(lockTimer);
    submitBtn.disabled = true;
    const tick = () => {
      const left = until - Date.now();
      if (left <= 0){
        clearInterval(lockTimer);
        localStorage.removeItem(lockKey);
        setNum(triesKey, 0);
        submitBtn.disabled = false;
        btnText.textContent = 'Sign In';
        hideError(); hideInfo();
        return;
      }
      btnText.textContent = `Locked ${mmss(left)}`;
      showError(`Too many attempts. Try again in ${mmss(left)}.`);
    };
    tick();
    lockTimer = setInterval(tick, 250);
  }

  function checkLock(){
    const until = Number(localStorage.getItem(lockKey) || '0');
    if (until > Date.now()) { startLockCountdown(until); return true; }
    return false;
  }

  function startLoading(){ submitBtn.disabled = true; btnText.textContent = 'Checking…'; }
  function stopLoading(ok=false){
    if (ok){ btnText.textContent = 'Success'; }
    else { btnText.textContent = 'Sign In'; submitBtn.disabled = false; }
  }

  function shakeCard(){
    const card = document.getElementById('card');
    card.style.animation = 'shakeX .35s ease-in-out';
    setTimeout(()=> card.style.animation = '', 360);
  }


  // Resume countdown if locked
  checkLock();
</script>
<script>
// Clear previous chat history on new login
localStorage.removeItem('chatHistory');
</script>


</body>
</html>
