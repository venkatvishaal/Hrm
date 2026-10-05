<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
  $hrName = (string)($user['name'] ?? 'HR Executive');
  $pendingCount = count($pendingLeaves ?? []) ?: 8;
  $approvedCount = count($approvedLeaves ?? []) ?: 24;
  $rejectedCount = count($rejectedLeaves ?? []) ?: 5;
?>

<div class="content-body">
  <!-- ── 1. Greeting & Context Header (Screen 6: HR Dashboard) ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Good Morning, <?= htmlspecialchars($hrName) ?>!</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Manage people, policies and workforce operations.</p>
    </div>
  </div>

  <!-- ── 2. Top 4 Action Cards Row (Screen 6) ── -->
  <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;margin-bottom:20px">
    <!-- Manage Leaves -->
    <a href="?route=leave" class="card" style="text-decoration:none;margin:0;padding:18px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;border-radius:14px;transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="width:44px;height:44px;border-radius:12px;background:#e0f2fe;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#0284c7"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
      </div>
      <span style="font-size:13.5px;font-weight:700;color:#0f172a">Manage Leaves</span>
    </a>

    <!-- Employee Directory -->
    <a href="?route=recruitment" class="card" style="text-decoration:none;margin:0;padding:18px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;border-radius:14px;transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="width:44px;height:44px;border-radius:12px;background:#ede9fe;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#7c3aed"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-7 8c0-3.31 3.13-6 7-6s7 2.69 7 6H5Z"/></svg>
      </div>
      <span style="font-size:13.5px;font-weight:700;color:#0f172a">Employee Directory</span>
    </a>

    <!-- Schedule Training -->
    <a href="?route=training" class="card" style="text-decoration:none;margin:0;padding:18px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;border-radius:14px;transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="width:44px;height:44px;border-radius:12px;background:#dcfce7;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#16a34a"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
      </div>
      <span style="font-size:13.5px;font-weight:700;color:#0f172a">Schedule Training</span>
    </a>

    <!-- Create Notice -->
    <a href="?route=notifications" class="card" style="text-decoration:none;margin:0;padding:18px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;border-radius:14px;transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
      <div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#e11d48"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Zm6-6V11a6 6 0 0 0-5-5.92V4a1 1 0 0 0-2 0v1.08A6 6 0 0 0 6 11v5l-2 2v1h16v-1l-2-2Z"/></svg>
      </div>
      <span style="font-size:13.5px;font-weight:700;color:#0f172a">Create Notice</span>
    </a>
  </div>

  <!-- ── 3. Middle Section: Two Columns (Screen 6) ── -->
  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;align-items:start">
    <!-- Left Column: Leave Requests Queue & Training Schedule -->
    <div style="display:flex;flex-direction:column;gap:18px">
      <!-- Leave Requests with Tabs (Screen 6) -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Leave Requests</h3>
          <a href="?route=leave" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
        </div>

        <!-- Filter Tabs (Screen 6) -->
        <div style="display:flex;gap:8px;margin-bottom:16px">
          <button type="button" class="filter-btn active" style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;border:none;background:#0d474c;color:#ffffff;cursor:pointer">Pending (<?= $pendingCount ?>)</button>
          <button type="button" class="filter-btn" style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer">Approved (<?= $approvedCount ?>)</button>
          <button type="button" class="filter-btn" style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer">Rejected (<?= $rejectedCount ?>)</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:36px;height:36px;border-radius:50%;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">
                RK
              </div>
              <div>
                <strong style="display:block;font-size:13px;color:#0f172a">Ravi Kumar</strong>
                <span style="font-size:11.5px;color:#64748b">Sick Leave • 2 days (Oct 8 - Oct 9)</span>
              </div>
            </div>
            <a href="?route=leave" style="padding:4px 12px;border-radius:6px;background:#0d474c;color:#ffffff;text-decoration:none;font-size:11.5px;font-weight:700">Review</a>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:36px;height:36px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">
                NI
              </div>
              <div>
                <strong style="display:block;font-size:13px;color:#0f172a">Neha Iyer</strong>
                <span style="font-size:11.5px;color:#64748b">Earned Leave • 6 days (Oct 12 - Oct 17)</span>
              </div>
            </div>
            <a href="?route=leave" style="padding:4px 12px;border-radius:6px;background:#0d474c;color:#ffffff;text-decoration:none;font-size:11.5px;font-weight:700">Review</a>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:36px;height:36px;border-radius:50%;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">
                SP
              </div>
              <div>
                <strong style="display:block;font-size:13px;color:#0f172a">Suresh Patel</strong>
                <span style="font-size:11.5px;color:#64748b">Casual Leave • 1 day (Oct 15)</span>
              </div>
            </div>
            <a href="?route=leave" style="padding:4px 12px;border-radius:6px;background:#0d474c;color:#ffffff;text-decoration:none;font-size:11.5px;font-weight:700">Review</a>
          </div>
        </div>
      </div>

      <!-- Training Schedule (Screen 6) -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Training Schedule</h3>
          <a href="?route=training" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:36px;height:36px;border-radius:10px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:18px">
                🫀
              </div>
              <div>
                <strong style="display:block;font-size:13px;color:#0f172a">Basic Life Support (BLS)</strong>
                <span style="font-size:11.5px;color:#64748b">Oct 15, 2026 • All Departments</span>
              </div>
            </div>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0f2fe;color:#0284c7">Upcoming</span>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:36px;height:36px;border-radius:10px;background:#fef2f2;color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:18px">
                🧯
              </div>
              <div>
                <strong style="display:block;font-size:13px;color:#0f172a">Fire Safety Training</strong>
                <span style="font-size:11.5px;color:#64748b">Oct 28, 2026 • Support Staff</span>
              </div>
            </div>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0f2fe;color:#0284c7">Upcoming</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Recruitment Updates & Banner (Screen 6) -->
    <div style="display:flex;flex-direction:column;gap:18px">
      <!-- Recruitment Updates Card -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Recruitment Updates</h3>
          <a href="?route=recruitment.create" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-radius:10px">
            <span style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155">
              <span>📋</span> Open Positions
            </span>
            <strong style="font-size:16px;color:#0f172a"><?= (int)($recruitmentStats['open_positions'] ?? 3) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-radius:10px">
            <span style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155">
              <span>👥</span> Applications
            </span>
            <strong style="font-size:16px;color:#0f172a"><?= (int)($recruitmentStats['applications'] ?? 48) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-radius:10px">
            <span style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155">
              <span>⭐</span> Shortlisted
            </span>
            <strong style="font-size:16px;color:#0f172a"><?= (int)($recruitmentStats['shortlisted'] ?? 8) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-radius:10px">
            <span style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155">
              <span>📅</span> Interviews
            </span>
            <strong style="font-size:16px;color:#0f172a"><?= (int)($recruitmentStats['interviews'] ?? 4) ?></strong>
          </div>
        </div>
      </div>

      <!-- Empowered People Stronger Healthcare Banner (Screen 6) -->
      <div style="border-radius:16px;background:linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);padding:22px;border:1px solid #7dd3fc;display:flex;align-items:center;justify-content:space-between">
        <div>
          <h4 style="font-size:17px;font-weight:800;color:#0369a1;margin:0 0 4px">Empowered People<br>Stronger Healthcare</h4>
          <p style="font-size:12px;color:#0284c7;margin:0 0 12px">Hospital workforce operations excellence.</p>
          <a href="?route=recruitment" style="font-size:12.5px;font-weight:700;color:#0369a1;text-decoration:none">Staff Directory →</a>
        </div>
        <div style="width:36px;height:36px;border-radius:50%;background:#0369a1;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700">
          →
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
