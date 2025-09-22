
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>





<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>

  <title>ATIERA — Privacy Policy</title>
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
<!-- Right: Privacy Policy -->
<section class="col-span-1 w-full flex items-center justify-center px-6">
  <main class="w-full max-w-3xl card p-8 rounded-lg shadow-lg bg-white text-black dark:bg-gray-900 dark:text-white">
    <h1 class="text-3xl font-extrabold mb-4 text-center">Privacy Policy</h1>
    <p class="text-sm text-center mb-8">Effective Date: September 18, 2025</p>

    <h2 class="text-xl font-semibold mt-6">1. Data We Collect</h2>
    <ul class="list-disc ml-6 space-y-1">
      <li>Employee details (name, department, position, contact info)</li>
      <li>Attendance records (clock-in/out times, hours worked)</li>
      <li>Shift and schedule assignments</li>
      <li>Timesheets (regular hours, overtime, undertime, breaks)</li>
      <li>Leave requests and approvals</li>
      <li>Claims and reimbursement submissions</li>
    </ul>

    <h2 class="text-xl font-semibold mt-6">2. Purpose of Collection</h2>
    <p>Data is collected to ensure accurate attendance monitoring, shift scheduling, timesheet reporting, leave management, and claims processing. Data may also be used for system analytics and HR decision-making.</p>

    <h2 class="text-xl font-semibold mt-6">3. Data Security</h2>
    <p>We apply technical and organizational measures aligned with ISO 27001 to protect data. This includes encryption, access controls, user authentication, and system monitoring. Data is never sold or shared outside the organization without legal basis.</p>

    <h2 class="text-xl font-semibold mt-6">4. Data Rights</h2>
    <p>Employees have the right to access, correct, or request deletion of their personal data. Requests should be made through the system administrator or HR department.</p>

    <h2 class="text-xl font-semibold mt-6">5. Consent</h2>
    <p>By logging in and using this system, you consent to the collection, storage, and processing of your data as described in this Privacy Policy.</p>

    <h2 class="text-xl font-semibold mt-6">6. Contact</h2>
    <p>For concerns regarding your data, please contact the system administrator or HR representative.</p>

    <p class="mt-8 text-sm text-center">
      © 2025 ATIERA BSIT 4101 CLUSTER 1
    </p>
  </main>
</section>




  

</body>
</html>

