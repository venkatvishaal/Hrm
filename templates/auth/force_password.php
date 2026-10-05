<?php require __DIR__ . '/../layouts/header.php'; ?>
<div style="min-height:calc(100vh - 120px);display:flex;align-items:center;justify-content:center;padding:24px">
  <div style="width:100%;max-width:400px">
    <!-- Header -->
    <div style="text-align:center;margin-bottom:24px">
      <div style="width:52px;height:52px;border-radius:13px;background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="#fff"><path d="M17 9h2v13H5V9h2V7a5 5 0 0 1 10 0v2Zm-8 0h6V7a3 3 0 0 0-6 0v2Zm2 5v3h2v-3h-2Z"/></svg>
      </div>
      <h1 style="font-size:22px;font-weight:800;color:#0f2744;margin:0 0 6px;letter-spacing:-.3px">Password Reset Required</h1>
      <p style="font-size:13px;color:#64748b;margin:0;line-height:1.5">
        Your account is using a default or temporary password.<br>
        Please set a new secure password (minimum 8 characters) to continue.
      </p>
    </div>

    <!-- Alert -->
    <div style="background:#fff8f0;border:1px solid #fdd090;border-left:4px solid #d97706;border-radius:9px;padding:10px 14px;margin-bottom:20px;font-size:12.5px;color:#78350f;display:flex;align-items:flex-start;gap:9px">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="#d97706" style="margin-top:1px;flex-shrink:0"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
      <span>For patient data security, your password must be at least <strong>8 characters</strong> long.</span>
    </div>

    <!-- Error flash -->
    <?php if ($err = flash('error')): ?>
      <div style="background:#fff1f2;border:1px solid #fca5a5;border-left:4px solid #c0392b;color:#7f1d1d;padding:10px 14px;border-radius:9px;font-size:12.5px;margin-bottom:16px">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <!-- Form card -->
    <div style="background:#fff;border:1px solid #e8eef6;border-radius:13px;padding:24px;box-shadow:0 2px 16px rgba(10,74,124,.08)">
      <form method="post" action="?route=force-password.update" id="force-password-form">
        <?= csrf_field() ?>
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">New Password
            <span style="font-weight:400;color:#94a3b8">(min. 8 characters)</span>
          </label>
          <input type="password" name="new_password" id="new_password" minlength="8" placeholder="Choose a strong password" required
            style="width:100%;padding:10px 12px;border:1.5px solid #e8eef6;border-radius:9px;font-size:14px;font-family:inherit;background:#f8fafd;color:#0f2744;outline:none;transition:border-color .15s,box-shadow .15s"
            onfocus="this.style.borderColor='#0a4a7c';this.style.boxShadow='0 0 0 3px rgba(10,74,124,.1)';this.style.background='#fff'"
            onblur="this.style.borderColor='#e8eef6';this.style.boxShadow='';this.style.background='#f8fafd'">
        </div>
        <div style="margin-bottom:20px">
          <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Confirm New Password</label>
          <input type="password" name="confirm_password" id="confirm_password" minlength="8" placeholder="Repeat the new password" required
            style="width:100%;padding:10px 12px;border:1.5px solid #e8eef6;border-radius:9px;font-size:14px;font-family:inherit;background:#f8fafd;color:#0f2744;outline:none;transition:border-color .15s,box-shadow .15s"
            onfocus="this.style.borderColor='#0a4a7c';this.style.boxShadow='0 0 0 3px rgba(10,74,124,.1)';this.style.background='#fff'"
            onblur="this.style.borderColor='#e8eef6';this.style.boxShadow='';this.style.background='#f8fafd'">
        </div>

        <!-- Password strength hint -->
        <div style="background:#f0f6ff;border:1px solid #dbeafe;border-radius:8px;padding:10px 12px;margin-bottom:18px">
          <div style="font-size:11px;font-weight:600;color:#1e40af;margin-bottom:6px">Password requirements:</div>
          <ul style="margin:0;padding-left:16px;font-size:11px;color:#3b82f6;line-height:1.8">
            <li>Minimum 8 characters</li>
            <li>Avoid using your name or employee code</li>
            <li>Avoid commonly used passwords like "password123"</li>
          </ul>
        </div>

        <button type="submit"
          style="width:100%;padding:12px;background:linear-gradient(135deg,#0a4a7c,#1565a6);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 4px 14px rgba(10,74,124,.3);display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s,transform .1s"
          onmouseover="this.style.opacity='.9';this.style.transform='translateY(-1px)'"
          onmouseout="this.style.opacity='1';this.style.transform=''">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="rgba(255,255,255,.85)"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
          Set New Password &amp; Continue
        </button>
      </form>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:12px;color:#94a3b8">
      <a href="?route=logout" style="color:#64748b;text-decoration:none;font-weight:500">Sign out and log in with a different account</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
