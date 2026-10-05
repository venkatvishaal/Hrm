<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Krishna Health - Hospital HRM</title>
  <meta name="description" content="Hospital Workforce Management System secure login.">
  <?php $assetBase = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/') ? 'assets' : 'public/assets'; ?>
  <?php $themeV = @filemtime(__DIR__ . '/../../public/assets/hospital-theme.css') ?: time(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/hospital-theme.css?v=<?= $themeV ?>">
  <style>
    *,*::before,*::after{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#f6faf8;color:#132321}
    a{color:inherit;text-decoration:none}
    button,input{font:inherit}
    .page{min-height:100vh;overflow:hidden;background:linear-gradient(180deg,#fbfffd 0%,#f2faf7 46%,#eaf4f0 100%)}
    .topbar{position:sticky;top:0;z-index:30;background:rgba(255,255,255,.94);backdrop-filter:blur(18px);border-bottom:1px solid #dcebe5}
    .nav{width:min(1180px,calc(100% - 36px));margin:auto;display:flex;align-items:center;justify-content:space-between;gap:22px;padding:16px 0}
    .brand{display:flex;align-items:center;gap:12px;min-width:max-content}
    .brand-icon{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(145deg,#0f766e,#134e4a);color:#fff;box-shadow:0 14px 28px rgba(15,118,110,.18)}
    .brand strong{display:block;font-size:25px;line-height:1;font-weight:900;color:#123330}
    .brand span span{display:block;margin-top:5px;font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#60716d}
    .nav-links{display:flex;align-items:center;gap:30px;font-weight:800;color:#263a37}
    .nav-links a{font-size:14px}
    .nav-links a:first-child{color:#0f766e;border-bottom:3px solid #0f766e;padding-bottom:8px}
    .nav-action,.btn-primary,.btn-secondary,.login-submit{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;border-radius:10px;font-weight:900;border:0;cursor:pointer}
    .nav-action,.btn-primary,.login-submit{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;box-shadow:0 16px 30px rgba(15,118,110,.2)}
    .nav-action{padding:0 24px}
    .btn-primary,.btn-secondary{padding:0 22px}
    .btn-secondary{background:#fff;color:#36514d;border:1px solid #c9ddd6}
    .hero-wrap{position:relative}
    .hero-wrap::after{content:"";position:absolute;right:-9vw;bottom:0;width:58vw;height:128px;background:linear-gradient(135deg,#b9e7dc,#14b8a6 58%,#0f766e);border-radius:100% 0 0 0;transform:skewY(-6deg);opacity:.78}
    .hero{position:relative;z-index:2;width:min(1180px,calc(100% - 36px));margin:auto;display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:34px;align-items:center;padding:58px 0 112px}
    .hero-copy{min-width:0}
    .kicker{margin:0 0 18px;font-size:13px;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:#0f766e}
    .hero h1{margin:0;font-size:clamp(44px,4.8vw,70px);line-height:1.02;font-weight:900;color:#132321}
    .hero h1 span{display:block;color:#0f766e}
    .hero h1 em{font-style:normal;color:#8a6f28}
    .hero-text{max-width:620px;margin:24px 0 28px;color:#4c625d;font-size:18px;line-height:1.72;font-weight:650}
    .hero-actions{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:28px}
    .hero-dashboard{position:relative;width:min(610px,100%);min-height:250px;margin-top:30px;border-radius:18px;background:#fff;border:1px solid #dcebe5;box-shadow:0 24px 58px rgba(19,35,33,.1);overflow:hidden}
    .hero-dashboard img{width:100%;height:250px;object-fit:cover;display:block;opacity:.9}
    .hero-dashboard::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,255,255,.04),rgba(7,24,63,.06))}
    .metric-pills{position:absolute;left:18px;right:18px;bottom:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;z-index:2}
    .metric-pills span{border-radius:10px;background:rgba(255,255,255,.92);border:1px solid #d7ebe5;padding:12px;font-size:12px;font-weight:900;color:#21443f;box-shadow:0 12px 24px rgba(19,35,33,.07)}
    .login-card{background:#fff;border:1px solid #dcebe5;border-radius:18px;box-shadow:0 24px 62px rgba(19,35,33,.14);padding:30px}
    .login-card-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:22px}
    .login-card h2{font-size:31px;line-height:1;margin:0 0 8px;color:#132321}
    .login-card p{margin:0;color:#5a6c68;font-weight:700}
    .secure-badge{display:grid;place-items:center;width:46px;height:46px;border-radius:14px;background:#e9f8f6;color:#08756b}
    .form-group{margin-bottom:16px}
    .form-group label{display:block;margin-bottom:8px;font-size:13px;font-weight:900;color:#1f3632}
    .input-wrap{position:relative}
    .input-wrap input{width:100%;border:1px solid #c8d9d4;border-radius:10px;background:#fbfefd;color:#132321;outline:0;padding:14px 46px 14px 15px;transition:.18s;font-size:15px}
    .input-wrap input:focus{border-color:#0d9488;box-shadow:0 0 0 4px rgba(13,148,136,.13);background:#fff}
    .btn-eye-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;border-radius:10px;background:#0f5f66;color:#fff;display:grid;place-items:center;cursor:pointer}
    .options-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:5px 0 20px;color:#415370;font-size:13px;font-weight:800}
    .remember-check{display:flex;align-items:center;gap:8px}
    .remember-check input{accent-color:#0f766e}
    .forgot-link{color:#0f766e}
    .login-submit{width:100%;min-height:50px;background:#0b5960;box-shadow:0 12px 24px rgba(11,89,96,.2)}
    .register-wrap{margin-top:16px;text-align:center;color:#5a6c68;font-weight:750}
    .register-wrap a{color:#0f766e;font-weight:900}
    .flash-error,.flash-ok{padding:11px 13px;border-radius:10px;font-size:13px;line-height:1.5;margin-bottom:16px}
    .flash-error{background:#fff1f2;color:#9f1239;border-left:4px solid #e11d48}
    .flash-ok{background:#ecfdf5;color:#065f46;border-left:4px solid #10b981}
    .ops-panel{position:relative;z-index:5;width:min(1080px,calc(100% - 36px));margin:-54px auto 72px;background:#fff;border:1px solid #dcebe5;border-radius:18px;box-shadow:0 24px 58px rgba(19,35,33,.1);display:grid;grid-template-columns:repeat(5,1fr);overflow:hidden}
    .ops-item{padding:24px 18px;text-align:center;border-right:1px solid #e1eee9}
    .ops-item:last-child{border-right:0}
    .ops-icon{width:56px;height:56px;margin:0 auto 13px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#14b8a6,#0f766e);color:#fff;font-weight:900}
    .ops-item strong{display:block;color:#1f3632;font-size:13px;text-transform:uppercase}
    .ops-item p{margin:7px 0 0;color:#5a6c68;font-size:12px;line-height:1.55;font-weight:700}
    .section{width:min(1080px,calc(100% - 36px));margin:0 auto 74px}
    .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;margin-bottom:26px}
    .section h2{margin:0;color:#132321;font-size:35px;line-height:1.16}
    .section-copy{max-width:640px;color:#5a6c68;font-weight:650;line-height:1.7}
    .workflows{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
    .workflow-card{background:#fff;border:1px solid #dcebe5;border-radius:16px;box-shadow:0 18px 42px rgba(19,35,33,.07);overflow:hidden}
    .workflow-image{height:180px;position:relative;background:#123330}
    .workflow-image img{width:100%;height:100%;object-fit:cover;display:block;opacity:.86}
    .workflow-tag{position:absolute;left:16px;top:16px;border-radius:9px;background:linear-gradient(135deg,#0f766e,#8a6f28);color:#fff;padding:8px 10px;font-size:11px;font-weight:900}
    .workflow-card div:last-child{padding:22px}
    .workflow-card h3{margin:0 0 10px;color:#1f3632;font-size:21px}
    .workflow-card p{margin:0;color:#5a6c68;line-height:1.62;font-weight:650}
    .support-grid{display:grid;grid-template-columns:.95fr 1.05fr;gap:46px;align-items:center}
    .support-copy p{color:#5a6c68;line-height:1.75;font-weight:650}
    .support-list{display:grid;gap:12px;margin:22px 0 26px}
    .support-list span{display:flex;align-items:center;gap:10px;font-weight:850;color:#21443f}
    .support-list span::before{content:"";width:11px;height:11px;border-radius:50%;background:#0f766e;box-shadow:0 0 0 5px #dff3ed;flex:0 0 auto}
    .support-photo{min-height:370px;border-radius:28px;background:url("<?= htmlspecialchars($assetBase) ?>/team_banner_new.jpg") center/cover;border:1px solid #dcebe5;box-shadow:0 24px 58px rgba(19,35,33,.1);position:relative;overflow:hidden}
    .support-photo::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,255,255,.16),rgba(7,24,63,.04))}
    .support-photo::after{content:"Workforce data should help HR act, not hunt.";position:absolute;left:24px;right:24px;bottom:24px;border-radius:14px;background:rgba(255,255,255,.92);padding:16px;color:#21443f;font-weight:900;box-shadow:0 16px 34px rgba(19,35,33,.11)}
    .insights{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:#dcebe5;border:1px solid #dcebe5;border-radius:18px;overflow:hidden;box-shadow:0 20px 48px rgba(19,35,33,.07)}
    .insight{background:#fff;padding:26px;display:flex;gap:15px;align-items:center}
    .insight span{width:54px;height:54px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff;font-weight:900;flex:0 0 auto}
    .insight strong{display:block;font-size:29px;color:#132321}
    .insight small{color:#5a6c68;font-weight:800}
    .cta{background:#e3f2ed}
    .cta-inner{width:min(1080px,calc(100% - 36px));margin:auto;display:grid;grid-template-columns:1fr auto;gap:28px;align-items:center;padding:42px 0}
    .cta h2{margin:0 0 8px;color:#132321;font-size:29px}
    .cta p{margin:0;color:#5a6c68;font-weight:700}
    .footer{background:#fff;border-top:1px solid #dcebe5}
    .footer-inner{width:min(1080px,calc(100% - 36px));margin:auto;display:grid;grid-template-columns:1.4fr repeat(3,1fr);gap:34px;padding:34px 0}
    .footer h4{margin:0 0 14px;color:#1f3632;font-size:13px;text-transform:uppercase}
    .footer p,.footer a{display:block;margin:0 0 9px;color:#5a6c68;font-size:13px;font-weight:650}
    .copyright{background:linear-gradient(135deg,#0f766e,#123330);color:#fff;font-size:12px;font-weight:800}
    .copyright-inner{width:min(1080px,calc(100% - 36px));margin:auto;display:flex;justify-content:space-between;gap:14px;padding:14px 0}
    @media (max-width:1020px){.nav-links{display:none}.hero{grid-template-columns:1fr;padding-top:34px}.hero-dashboard{max-width:none}.login-card{max-width:520px}.ops-panel,.workflows,.support-grid,.insights,.footer-inner,.cta-inner{grid-template-columns:1fr}.ops-item{border-right:0;border-bottom:1px solid #e8eef7}.ops-item:last-child{border-bottom:0}.insight{justify-content:flex-start}.section-head{align-items:flex-start;flex-direction:column}.cta-inner{align-items:flex-start}}
    @media (max-width:640px){.nav{width:min(100% - 24px,1180px)}.brand strong{font-size:20px}.brand-icon{width:46px;height:46px}.nav-action{padding:0 15px}.hero{width:min(100% - 24px,1180px);padding-bottom:86px}.hero h1{font-size:42px}.hero-text{font-size:15px}.hero-actions{display:grid}.btn-primary,.btn-secondary{width:100%}.metric-pills{grid-template-columns:1fr}.hero-dashboard img{height:310px}.ops-panel,.section,.cta-inner,.footer-inner{width:min(100% - 24px,1080px)}.login-card{padding:22px}.options-row{align-items:flex-start;flex-direction:column}.support-photo{min-height:290px}.copyright-inner{flex-direction:column;text-align:center}}
  </style>
</head>
<body>
<?php
  $hospitalName = $hospitalName ?? 'Krishna Health';
  $prefilledCode = $_SESSION['registered_emp_code'] ?? '';
  unset($_SESSION['registered_emp_code']);
?>
<div class="page">
  <header class="topbar">
    <div class="nav">
      <a class="brand" href="#home" aria-label="Krishna Health home">
        <span class="brand-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M6 8h12"/><path d="M8 13h8"/><path d="M10 18h4"/><path d="M4 21h16"/></svg></span>
        <span><strong><?= htmlspecialchars($hospitalName) ?></strong><span>Hospital Workforce System</span></span>
      </a>
      <nav class="nav-links" aria-label="Primary navigation">
        <a href="#home">Home</a><a href="#workflows">Workflows</a><a href="#support">Staff Support</a><a href="#insights">Insights</a><a href="#contact">Contact</a>
      </nav>
      <a class="nav-action" href="#login">Staff Login</a>
    </div>
  </header>

  <main id="home">
    <section class="hero-wrap">
      <div class="hero">
        <div class="hero-copy">
          <p class="kicker">Hospital HR operations portal</p>
          <h1>Manage people, shifts, <em>leave</em><span>and compliance.</span></h1>
          <p class="hero-text">A secure entry point for HR teams, managers, and employees to handle attendance, duty rosters, onboarding, documents, payroll records, approvals, and reports.</p>
          <div class="hero-actions"><a class="btn-primary" href="#workflows">View Modules</a><a class="btn-secondary" href="#support">Employee Services</a></div>
          <div class="hero-dashboard" aria-label="Hospital HR dashboard preview">
            <img src="<?= htmlspecialchars($assetBase) ?>/landing-hr-hero.png" alt="Digital hospital HR dashboard">
            <div class="metric-pills"><span>Roster planning</span><span>Leave approvals</span><span>Document tracking</span></div>
          </div>
        </div>

        <aside class="login-card" id="login" aria-label="Login form">
          <div class="login-card-head">
            <div><h2>Welcome Back</h2><p>Sign in to continue to HRM.</p></div>
            <span class="secure-badge"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/></svg></span>
          </div>
          <?php if ($ok = flash('ok')): ?><div class="flash-ok"><strong>Registration Successful!</strong><br><?= htmlspecialchars($ok) ?></div><?php endif; ?>
          <?php if ($err = flash('error')): ?><div class="flash-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
          <form method="post" action="?route=login.submit" autocomplete="on">
            <?= csrf_field() ?>
            <div class="form-group"><label for="employee_code">Employee ID / Email</label><div class="input-wrap"><input type="text" id="employee_code" name="employee_code" placeholder="e.g. KH002 or name@hospital.org" value="<?= htmlspecialchars($prefilledCode) ?>" required <?= $prefilledCode !== '' ? '' : 'autofocus' ?>></div></div>
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrap">
                <input type="password" id="password" name="password" placeholder="Enter your password" required <?= $prefilledCode !== '' ? 'autofocus' : '' ?>>
                <button type="button" class="btn-eye-toggle" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility"><svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
              </div>
            </div>
            <div class="options-row"><label class="remember-check"><input type="checkbox" name="remember" checked><span>Keep me signed in</span></label><a href="#" class="forgot-link" onclick="alert('Please contact your Hospital HR / Administrator to reset your credentials.');return false;">Forgot password?</a></div>
            <button type="submit" class="login-submit">Sign in to HRM -></button>
          </form>
          <div class="register-wrap">New staff member? <a href="?route=employee-register">Register here</a></div>
        </aside>
      </div>
    </section>

    <section class="ops-panel" aria-label="HRM capabilities">
      <div class="ops-item"><span class="ops-icon">01</span><strong>Attendance</strong><p>Daily status, corrections, late marks, and monthly summaries.</p></div>
      <div class="ops-item"><span class="ops-icon">02</span><strong>Duty Roster</strong><p>Weekly assignments, shortages, departments, and shift settings.</p></div>
      <div class="ops-item"><span class="ops-icon">03</span><strong>Leave</strong><p>Leave, permission, approval hierarchy, and balance visibility.</p></div>
      <div class="ops-item"><span class="ops-icon">04</span><strong>Employees</strong><p>Profiles, onboarding, credentials, certificates, and documents.</p></div>
      <div class="ops-item"><span class="ops-icon">05</span><strong>Reports</strong><p>Recruitment, training, performance, audit, and export views.</p></div>
    </section>

    <section class="section" id="workflows">
      <div class="section-head">
        <div><p class="kicker">Core modules</p><h2>Built around the screens this HRM already has.</h2></div>
        <p class="section-copy">The landing page now points to actual product areas: onboarding, staff development, attendance operations, leave decisions, records, and administrative reports.</p>
      </div>
      <div class="workflows">
        <article class="workflow-card"><div class="workflow-image"><img src="<?= htmlspecialchars($assetBase) ?>/hero_hospital_new.jpg" alt="Hospital building"><span class="workflow-tag">Recruitment</span></div><div><h3>Move candidates to employees</h3><p>Support manpower requisitions, employee registration, onboarding tasks, and new staff document collection.</p></div></article>
        <article class="workflow-card"><div class="workflow-image"><img src="<?= htmlspecialchars($assetBase) ?>/training-seminar.jpg" alt="Training seminar"><span class="workflow-tag">Training</span></div><div><h3>Track learning and KPI records</h3><p>Manage sessions, participation, employee training updates, performance scores, and leaderboard views.</p></div></article>
        <article class="workflow-card"><div class="workflow-image"><img src="<?= htmlspecialchars($assetBase) ?>/real_krishna_hospital.jpg" alt="Hospital exterior"><span class="workflow-tag">Operations</span></div><div><h3>Monitor shifts and attendance</h3><p>Review duty coverage, live attendance status, absentees, corrections, monthly reports, and exports.</p></div></article>
      </div>
    </section>

    <section class="section support-grid" id="support">
      <div class="support-copy">
        <p class="kicker">Role based access</p>
        <h2>One login, different workspaces.</h2>
        <p>After sign-in, the system routes users into the right experience: employee self-service, manager approvals, HR operations, or admin configuration.</p>
        <div class="support-list">
          <span>Employees can access attendance, leave, duty roster, payslip, certificates, and documents</span>
          <span>HR can manage recruitment, onboarding, attendance imports, credentials, and health checkups</span>
          <span>Managers can review team leave, performance, notifications, and operational requests</span>
          <span>Admins can configure departments, designations, users, shifts, settings, and RBAC menus</span>
        </div>
        <a class="btn-primary" href="#login">Open Staff Login</a>
      </div>
      <div class="support-photo" role="img" aria-label="Hospital team"></div>
    </section>

    <section class="section insights" id="insights">
      <div class="insight"><span>EMP</span><div><strong>500+</strong><small>Employee-ready records</small></div></div>
      <div class="insight"><span>ATT</span><div><strong>30</strong><small>Day attendance review</small></div></div>
      <div class="insight"><span>DUT</span><div><strong>7</strong><small>Day roster planning</small></div></div>
      <div class="insight"><span>REP</span><div><strong>PDF</strong><small>Excel export support</small></div></div>
    </section>

    <section class="cta"><div class="cta-inner"><div><h2>Start with the task that brought you here.</h2><p>Sign in to continue attendance review, leave approval, employee profile updates, roster planning, or report exports.</p></div><a class="btn-primary" href="#login">Sign In Now</a></div></section>
  </main>

  <footer class="footer" id="contact">
    <div class="footer-inner">
      <div><a class="brand" href="#home"><span class="brand-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M6 8h12"/><path d="M8 13h8"/><path d="M10 18h4"/></svg></span><span><strong><?= htmlspecialchars($hospitalName) ?></strong><span>Hospital Workforce System</span></span></a><p>Centralized workforce operations for hospital HR, managers, and employees.</p></div>
      <div><h4>System</h4><a href="#workflows">Workflows</a><a href="#support">Staff Support</a><a href="#insights">Insights</a></div>
      <div><h4>Access</h4><a href="#login">Staff Login</a><a href="?route=employee-register">Employee Registration</a></div>
      <div><h4>Contact</h4><p>hr@krishnahealth.org</p><p>Thanjavur, Tamil Nadu, India</p><p>+91 12345 67890</p></div>
    </div>
    <div class="copyright"><div class="copyright-inner"><span>Copyright 2026 <?= htmlspecialchars($hospitalName) ?>. All Rights Reserved.</span><span>People - Process - Care</span></div></div>
  </footer>
</div>
<script>
  function togglePasswordVisibility(){
    var pwd=document.getElementById('password');
    var icon=document.getElementById('eyeIcon');
    if(!pwd||!icon)return;
    if(pwd.type==='password'){
      pwd.type='text';
      icon.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    }else{
      pwd.type='password';
      icon.innerHTML='<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
  }
</script>
</body>
</html>
