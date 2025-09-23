<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
session_start();
require __DIR__ . '/config/GoogleAuthenticator-master/PHPGangsta/GoogleAuthenticator.php';
include __DIR__ . '/dbconnection/mainDb.php';

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || strpos($line, '#') === 0) continue;

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $value = trim($value);
            // Remove surrounding quotes if present
            $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);
            putenv(trim($name) . "=" . $value);
            $_ENV[trim($name)] = $value; // Optional: for $_ENV access
        }
    }
}
loadEnv(__DIR__ . '/.env');

  $apiKey = getenv('FIREBASE_API_KEY');

$ga = new PHPGangsta_GoogleAuthenticator();
$errors = [];
$recaptcha_secret = '6LcWessrAAAAAEldogR_ObH_We1gKnL3YHrnSG60';

/**
 * 🔹 Helper: finalize login
 */
function finalizeLogin($user, $conn, $acceptTerms = false, $remember = false) {
    $_SESSION['user_id']      = $user['user_id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['roles']        = $user['role_name'] ?? 'Employee';
    $_SESSION['employee_id']  = $user['employee_id'];
    $_SESSION['user_name']    = $user['first_name'] . ' ' . $user['last_name'];

    // update last login & consent
    if ($acceptTerms) {
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), consent_accepted_at = NOW() WHERE user_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    }
    $stmt->bind_param("s", $user['user_id']);
    $stmt->execute();

    // handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), "/", "", true, true);
        $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $token, $user['user_id']);
        $stmt->execute();
    }

    header("Location: timesheet/dashboard.php");
    exit;
}

/**
 * 🔹 Handle 2FA Verification
 */
if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
    $code   = trim($_POST['2fa_code'] ?? '');
    $secret = $_SESSION['2fa_secret'] ?? null;
    $userId = $_SESSION['temp_user_id'] ?? null;

    if ($secret && $userId && !empty($code) && $ga->verifyCode($secret, $code, 2)) {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, r.name AS role_name,
                   e.employee_id, e.first_name, e.last_name
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            LEFT JOIN hr3_system.employees e ON e.user_id = u.user_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            unset($_SESSION['temp_user_id'], $_SESSION['2fa_secret']);
            $acceptTerms = isset($_SESSION['accept_terms']) && $_SESSION['accept_terms'] === true;
            $remember    = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

            // clear temp flags
            unset($_SESSION['accept_terms'], $_SESSION['remember_me']);

            finalizeLogin($user, $conn, $acceptTerms, $remember);
        } else {
            $errors[] = "User not found. Please login again.";
        }
    } else {
        $errors[] = "Invalid 2FA code. Please try again.";
    }
}

/**
 * 🔹 Auto-login via Remember Me cookie
 */
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.email, r.name AS role_name,
               e.employee_id, e.first_name, e.last_name
        FROM users u
        LEFT JOIN user_roles ur ON u.user_id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.role_id
        LEFT JOIN hr3_system.employees e ON e.user_id = u.user_id
        WHERE u.remember_token = ? LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        finalizeLogin($user, $conn, false, false);
    } else {
        setcookie('remember_me', '', time() - 3600, "/", "", true, true);
    }
}

/**
 * 🔹 Handle Login Submission (Step 1)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'verify_2fa')) {
    $username = trim($_POST['username'] ?? '');
    $consentRequired = true;

    // check consent requirement
    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT consent_accepted_at FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $userConsent = $stmt->get_result()->fetch_assoc();
        if ($userConsent && !empty($userConsent['consent_accepted_at'])) {
            $consentRequired = false;
        }
    }
    if ($consentRequired && !isset($_POST['terms'])) {
        $errors[] = "You must agree to the Privacy Policy and Terms & Conditions before continuing.";
    }

    // check recaptcha
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        $errors[] = "Please complete the reCAPTCHA before logging in.";
    } else {
        $recaptcha_response = $_POST['g-recaptcha-response'];
        $recaptcha_verify = file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response"
        );
        $recaptcha_result = json_decode($recaptcha_verify, true);

        if (empty($recaptcha_result['success'])) {
            $errors[] = "reCAPTCHA verification failed. Please try again.";
        } else {
            $password = $_POST['password'] ?? '';
            if ($username && $password && empty($errors)) {
                $stmt = $conn->prepare("
                    SELECT u.user_id, u.username, u.email, u.password_hash, u.is_active, u.is_verified,
                           r.name AS role_name,
                           e.employee_id, e.first_name, e.last_name
                    FROM users u
                    LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.role_id
                    LEFT JOIN hr3_system.employees e ON e.user_id = u.user_id
                    WHERE (u.username = ? OR u.email = ?) AND r.name IS NOT NULL
                    LIMIT 1
                ");
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (!$user['is_active']) {
                        $errors[] = "Account is inactive.";
                    } else {
                        $passwordVerified = password_verify($password, $user['password_hash']);
                        $firebaseLogin = null;

                        // fallback to Firebase
                        if (!$passwordVerified) {
                   $apiKey = getenv('FIREBASE_API_KEY');
if (!$apiKey) {
    die("Firebase API key not loaded from .env");
}

                            $payload = json_encode([
                                "email" => $user['email'],
                                "password" => $password,
                                "returnSecureToken" => true
                            ]);
                            $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=$apiKey");
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_POST => true,
                                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                                CURLOPT_POSTFIELDS => $payload
                            ]);
                            $firebaseLogin = json_decode(curl_exec($ch), true);
                            curl_close($ch);

                            if (isset($firebaseLogin['idToken'])) {
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE email=?");
                                $stmt->bind_param("ss", $newHash, $user['email']);
                                $stmt->execute();
                                $passwordVerified = true;
                            } elseif (isset($firebaseLogin['error'])) {
                                $errors[] = "Firebase login failed: " . $firebaseLogin['error']['message'];
                            }
                        }

                        // verify email
                        $idToken = $firebaseLogin['idToken'] ?? null;
                        $emailVerified = true;
                        if ($idToken) {
                        $apiKey = getenv('FIREBASE_API_KEY');
if (!$apiKey) {
    die("Firebase API key not loaded from .env");
}

                            $payload = json_encode(["idToken" => $idToken]);
                            $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=$apiKey");
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                                CURLOPT_POSTFIELDS => $payload
                            ]);
                            $response = json_decode(curl_exec($ch), true);
                            curl_close($ch);

                            $emailVerified = $response['users'][0]['emailVerified'] ?? false;
                            if (!$emailVerified) {
                                $payload = json_encode([
                                    "requestType" => "VERIFY_EMAIL",
                                    "idToken" => $idToken
                                ]);
                                $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=$apiKey");
                                curl_setopt_array($ch, [
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_POST => true,
                                    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                                    CURLOPT_POSTFIELDS => $payload
                                ]);
                                curl_exec($ch);
                                curl_close($ch);

                                $errors[] = "Please verify your email before logging in. A verification link has been sent.";
                            }
                        }

                        // 🔹 Check 2FA
                        if ($passwordVerified && $emailVerified && empty($errors)) {
                            $stmt = $conn->prepare("SELECT two_factor_enabled, two_factor_secret FROM users WHERE user_id = ?");
                            $stmt->bind_param("s", $user['user_id']);
                            $stmt->execute();
                            $twoFA = $stmt->get_result()->fetch_assoc();

                            $acceptTerms = isset($_POST['terms']);
                            $remember    = isset($_POST['remember']);

                            if ($twoFA['two_factor_enabled']) {
                                $_SESSION['temp_user_id'] = $user['user_id'];
                                $_SESSION['2fa_secret']   = $twoFA['two_factor_secret'];
                                $_SESSION['accept_terms'] = $acceptTerms;
                                $_SESSION['remember_me']  = $remember;

                                echo "<script>window.onload=function(){document.getElementById('twoFAModal').classList.remove('hidden');};</script>";
                            } else {
                                finalizeLogin($user, $conn, $acceptTerms, $remember);
                            }
                        } elseif (!$passwordVerified) {
                            $errors[] = "Incorrect password.";
                        }
                    }
                } else {
                    $errors[] = "User not found.";
                }
            } else {
                $errors[] = "Email and password are required.";
            }
        }
    }
}
?>



<!-- 

*** © 2025 ATIERA BSIT 4101 CLUSTER 1  ***

    ________________.________________________    _____   
  /  _  \__    ___/|   \_   _____/\______   \  /  _  \  
 /  /_\  \|    |   |   ||    __)_  |       _/ /  /_\  \ 
/    |    \    |   |   ||        \ |    |   \/    |    \
\____|__  /____|   |___/_______  / |____|_  /\____|__  /
        \/                     \/         \/         \/ 
                                                        
                                                        
                                                        
                                                        
                                                        
                                                        
   ___ ___ ______________________________.____          
  /   |   \\_____  \__    ___/\_   _____/|    |         
 /    ~    \/   |   \|    |    |    __)_ |    |         
 \    Y    /    |    \    |    |        \|    |___      
  \___|_  /\_______  /____|   /_______  /|_______ \     
        \/         \/                 \/         \/     
                                                        
                                                        
                                                        
                                                        
                                                        
                                                        
  ____                                                  
 /  _ \                                                 
 >  _ </\                                               
/  <_\ \/                                               
\_____\ \                                               
       \/                                               
                                                        
                                                        
                                                        
                                                        
                                                        
                                                        
 _____________________ _________                        
 \______   \_   _____//   _____/                        
  |       _/|    __)_ \_____  \                         
  |    |   \|        \/        \                        
  |____|_  /_______  /_______  /                        
         \/        \/        \/                         
________________   ____ ___                             
\__    ___/  _  \ |    |   \                            
  |    | /  /_\  \|    |   /                            
  |    |/    |    \    |  /                             
  |____|\____|__  /______/                              
                \/                                      
__________    _____    __________________               
\______   \  /  _  \   \      \__    ___/               
 |       _/ /  /_\  \  /   |   \|    |                  
 |    |   \/    |    \/    |    \    |                  
 |____|_  /\____|__  /\____|__  /____|                  
        \/         \/         \/                        
                                                        
                                                        
                                                        
                                                        
                                                        
                                                        
  ___ _____________________                             
 /   |   \______   \_____  \                            
/    ~    \       _/ _(__  <                            
\    Y    /    |   \/       \                           
 \___|_  /|____|_  /______  /                           
       \/        \/       \/                            
                                                            




Welcome to ATIERA HOTEL & RESTAURANT HR3! -->


<!DOCTYPE html>

<html lang="en" class="scroll-smooth">
<head>
  
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>ATIERA — Secure Login</title>
<link rel="icon" href="picture/logo2.png">
<script src="https://cdn.tailwindcss.com"></script>

<style>
  :root{
    --blue-600:#1b2f73; --blue-700:#15265e; --blue-800:#0f1c49; --blue-a:#2342a6;
    --gold:#d4af37; --ink:#0f172a; --muted:#64748b;
    --ring:0 0 0 3px rgba(35,66,166,.28);
    --card-bg: rgba(255,255,255,.95); --card-border: rgba(255, 255, 255, 0.9);
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

  .card { color: inherit; }

</style>
</head>

<body class="grid md:grid-cols-2 gap-0 place-items-center p-6 md:p-10">

  <!-- Watermark -->
  <div class="bg-watermark" aria-hidden="true">
    <img src="picture/logo.png" alt="ATIERA watermark" id="wm">
  </div>

  <!-- Left panel -->
  <section class="hidden md:flex w-full h-full items-center justify-center">
    <div class="max-w-lg text-white px-6 reveal">
      <img src="picture/logo.png" alt="ATIERA" class="w-56 mb-6 drop-shadow-xl select-none" draggable="false">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
        ATIERA <span style="color:var(--gold)">HOTEL & RESTAURANT</span> HR3 <br>
      
      </h1>
      <p class="mt-4 text-white/90 text-lg">Secure • Fast • Intuitive</p>
    </div>
  </section>

  <!-- Right: Login -->
  <main class="w-full max-w-md md:ml-auto">
    <div id="card" class="card p-6 sm:p-8 reveal">
      <div class="flex items-center justify-between mb-4">
        <div class="md:hidden flex items-center gap-3">
          <img src="picture/logo.png" alt="ATIERA" class="h-10 w-auto">
          <div>
            <div class="text-sm font-semibold leading-4">ATIERA Finance Suite</div>
            <div class="text-[10px] text-[color:var(--muted)]">Blue • White • <span class="font-medium" style="color:var(--gold)">Gold</span></div>
          </div>
        </div>
        <button id="modeBtn" class="px-3 py-2 rounded-lg border border-slate-200 text-sm hover:bg-white/60 dark:hover:bg-slate-800" aria-pressed="false" title="Toggle dark mode">🌓</button>
      </div>

 
  <h3 class="text-xl font-bold  text-slate-500 dark:text-slate-400 mb-4">Login </h3>


      <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Use your administrator credentials to continue.</p>


  
      <!-- Inline attempt + info banners -->
      <div id="alert" class="alert alert-error hidden mb-2" role="alert"></div>
      <div id="info"  class="alert alert-info  hidden mb-4" role="status"></div>
<!-- Include the reCAPTCHA v2 script in the head or before </body> -->


<script src="https://www.google.com/recaptcha/api.js" async defer></script>


<form id="loginForm" class="space-y-4" novalidate action="index.php" method="POST">

  <?php if (!empty($errors)): ?>
  <div class="mb-4 p-3 rounded bg-red-100 border border-red-400 text-red-700">
    <?php foreach ($errors as $error): ?>
      <p><?php echo htmlspecialchars($error); ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Username -->
  <div class="field">
    <input id="username" name="username" type="text" autocomplete="username"
      class="input peer" placeholder=" " required aria-describedby="userHelp">
    <label for="username" class="float-label">Email</label>
    <span class="icon-right" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5.33 0-8 2.67-8 5v1h16v-1c0-2.33-2.67-5-8-5Z" fill="currentColor"/>
      </svg>
    </span>
  </div>
  <p id="userHelp" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
    e.g., <span class="font-mono">admin</span> or <span class="font-mono">admin@example.com</span>
  </p>

  <!-- Password -->
  <div>
    <div class="flex items-center justify-between mb-1">
      <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300"></label>
      <span id="capsNote" class="hidden text-xs px-2 py-0.5 rounded bg-amber-50 border border-amber-300 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200">Caps Lock is ON</span>
    </div>
    <div class="field">
      <input id="password" name="password" type="password" autocomplete="current-password"
        class="input pr-12 peer" placeholder=" " required>
      <label for="password" class="float-label">Password</label>
      <div class="icon-right flex items-center gap-1">
        <button type="button" id="togglePw" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center" aria-label="Show password" aria-pressed="false" title="Show/Hide password">
          <svg id="eyeOn" width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 11a4 4 0 1 1 4-4 4 4 0 0 1-4 4Z" fill="currentColor"/>
          </svg>
          <svg id="eyeOff" class="hidden" width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M3 4.27 4.28 3 21 19.72 19.73 21l-2.2-2.2A11.73 11.73 0 0 1 12 19c-5 0-9.27-3.11-11-7a12.71 12.71 0 0 1 4.1-4.73L3 4.27ZM12 7a5 5 0 0 1 5 5 5 5 0 0 1-.46 2.11L14.6 12.17A2.5 2.5 0 0 0 11.83 9.4L9.9 7.46A4.84 4.84 0 0 1 12 7Z" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- reCAPTCHA v2 Checkbox -->
  <div class="g-recaptcha" data-sitekey="6LcWessrAAAAANsdwWOfPKg2Td6CSu2j9dDbbjth"></div>
<!-- Terms & Conditions -->
<div class="mt-4">
  <input type="checkbox" id="terms" name="terms" required checked>
  <label for="terms" class="text-sm text-slate-600 dark:text-slate-400">
    By Logging in you agree to the 
    <a href="/public_html/user/privacyPolicy.php" target="_blank" class="text-blue-600 underline">Privacy Policy</a> 
    and 
    <a href="/public_html/user/terms.php" target="_blank" class="text-blue-600 underline">Terms & Conditions</a>.
  </label>
</div>

<!-- Remember Me -->
<div class="mt-2">
  <input type="checkbox" id="remember" name="remember">
  <label for="remember" class="text-sm text-slate-600 dark:text-slate-400">
    Remember me
  </label>
</div>

  <!-- Submit -->
  <button id="submitBtn" type="submit" class="btn" aria-live="polite">
    <span id="btnText">Sign In</span>
  </button>

  <a href="/public_html/user/forgotPassword.php" class="btn block text-center" aria-live="polite">
    <span>Forgot Password?</span>
  </a>

  <p class="text-xs text-center text-slate-500 dark:text-slate-400">
    © 2025 ATIERA BSIT 4101 CLUSTER 1
  </p>
</form>





    </div>
  </main>
<!-- 2FA Modal -->
<div id="twoFAModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
  <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-lg w-96">
    <h2 class="text-lg font-semibold mb-4">Two-Factor Authentication</h2>
    <form method="POST" action="">
      <input type="hidden" name="action" value="verify_2fa">
      <div class="field">
        <input type="text" name="2fa_code" class="input" placeholder="Enter 6-digit code" required>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('twoFAModal').classList.add('hidden')" class="btn bg-gray-200">Cancel</button>
        <button type="submit" class="btn">Verify</button>
      </div>
    </form>
  </div>
</div>

  <!-- ===== Center Popup (success/goodbye only; slow animations) ===== -->
  <div id="popupBackdrop" class="hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-[2px] will-change-[opacity]"></div>
  <div id="popupRoot" class="hidden fixed inset-0 z-[61] grid place-items-center pointer-events-none">
    <div id="popupCard" class="pointer-events-auto w-[92%] max-w-sm rounded-2xl p-6 card shadow-2xl opacity-0 scale-95"
         role="alertdialog" aria-modal="true" aria-labelledby="popupTitle" aria-describedby="popupMsg">
      <div id="popupIconWrap" class="mx-auto mb-3 w-14 h-14 rounded-full flex items-center justify-center text-white relative overflow-visible"
           style="background:linear-gradient(180deg,var(--blue-600),var(--blue-800)); box-shadow:0 10px 18px rgba(2,6,23,.18)">
        <svg id="popupIcon" width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M9.5 16.2 5.8 12.5l-1.3 1.3 5 5 10-10-1.3-1.3-8.7 8.7Z" fill="currentColor"/>
        </svg>
        <span id="iconRipple" class="absolute inset-0 rounded-full border border-white/60 opacity-0"></span>
      </div>
      <h4 id="popupTitle" class="text-xl font-extrabold text-center" style="color:var(--gold)">Hello, Admin 👋</h4>
      <p id="popupMsg" class="mt-1 text-sm text-center text-slate-600 dark:text-slate-400 typing"></p>
      <div class="mt-4 flex justify-center">
        <button id="popupOkBtn" class="btn !w-auto px-4 py-2 text-sm">OK</button>
      </div>
    </div>
  </div>

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
