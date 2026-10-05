<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
  $managerName = (string)($managerEmployee['first_name'] ?? ($user['name'] ?? 'Manager'));
  $deptName = (string)($department ?? 'Medical');
  $teamCount = (int)($teamCount ?? 28);
  $onLeaveCount = (int)($onLeaveCount ?? 4);
  $pendingCount = (int)($pendingApprovalsCount ?? 6);
  $trainingDueCount = 8;
  $presentCount = max(0, $teamCount - $onLeaveCount);
?>

<div class="content-body">
  <!-- ── 1. Greeting & Context Header (Screen 5: Manager Dashboard) ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Good Morning, <?= htmlspecialchars($managerName) ?>!</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Here's what's happening with your team.</p>
    </div>
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:600;color:#0f766e;display:flex;align-items:center;gap:6px">
      <span>🏥 <?= htmlspecialchars($deptName) ?> Department</span>
    </div>
  </div>

  <!-- ── 2. Top 4 Team Metrics (Screen 5) ── -->
  <div class="stats-overview-grid">
    <!-- Team Members -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-staff">
          <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4Zm8 0c-.33 0-.68.02-1.05.05C16.19 13.89 18 15.02 18 17v2h6v-2c0-2.66-5.33-4-8-4Z"/></svg>
        </div>
        <span class="stat-metric-label">Team Members</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value"><?= $teamCount ?></span>
        <span class="stat-metric-badge" style="background:#f1f5f9;color:#64748b">Active</span>
      </div>
    </div>

    <!-- On Leave Today -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon" style="background:#fef2f2;color:#ef4444">
          <svg viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
        </div>
        <span class="stat-metric-label">On Leave Today</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value" style="color:#ef4444"><?= $onLeaveCount ?></span>
        <span class="stat-metric-badge" style="background:#fee2e2;color:#b91c1c">Today</span>
      </div>
    </div>

    <!-- Pending Approvals -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon" style="background:#fef3c7;color:#d97706">
          <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm1-13h-2v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
        </div>
        <span class="stat-metric-label">Pending Approvals</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value" style="color:#d97706"><?= $pendingCount ?></span>
        <span class="stat-metric-badge" style="background:#fef3c7;color:#b45309">Action req.</span>
      </div>
    </div>

    <!-- Training Due -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-doctors">
          <svg viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
        </div>
        <span class="stat-metric-label">Training Due</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value"><?= $trainingDueCount ?></span>
        <span class="stat-metric-badge" style="background:#e0f2fe;color:#0284c7">This Month</span>
      </div>
    </div>
  </div>

  <!-- ── 3. Middle Section: Two Columns (Screen 5) ── -->
  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;align-items:start">
    <!-- Left Column: Team Leave Requests & Upcoming Team Training -->
    <div style="display:flex;flex-direction:column;gap:18px">
      <!-- Team Leave Requests Card -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Team Leave Requests</h3>
            <span style="font-size:12px;color:#64748b">Direct approvals for your department</span>
          </div>
          <a href="?route=leave" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
          <?php if (!empty($teamLeaveRequests)): ?>
            <?php foreach ($teamLeaveRequests as $req): ?>
              <?php
                $isPending = strcasecmp((string)$req['status'], 'Pending') === 0;
                $empName = trim((string)($req['first_name'] ?? '') . ' ' . (string)($req['last_name'] ?? ''));
                $badgeBg = $isPending ? '#fef3c7' : '#dcfce7';
                $badgeColor = $isPending ? '#b45309' : '#15803d';
              ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:12px">
                <div style="display:flex;align-items:center;gap:12px">
                  <div style="width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#334155">
                    <?= htmlspecialchars(strtoupper(substr($empName ?: 'Staff', 0, 1))) ?>
                  </div>
                  <div>
                    <strong style="display:block;font-size:13.5px;color:#0f172a"><?= htmlspecialchars($empName ?: 'Staff Member') ?></strong>
                    <span style="font-size:11.5px;color:#64748b"><?= htmlspecialchars((string)($req['leave_type'] ?? 'Leave')) ?> • <?= htmlspecialchars(fmt_date($req['start_date'] ?? date('Y-m-d'))) ?></span>
                  </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                  <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:<?= $badgeBg ?>;color:<?= $badgeColor ?>">
                    <?= htmlspecialchars((string)($req['status'] ?? 'Pending')) ?>
                  </span>
                  <?php if ($isPending): ?>
                    <a href="?route=leave" style="font-size:12px;font-weight:700;color:#0f766e;text-decoration:none">Review</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="padding:14px;background:#f8fafc;border-radius:12px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <strong style="font-size:13px;color:#0f172a">Priya Sharma</strong>
                <div style="font-size:11.5px;color:#64748b">Sick Leave • 2 days</div>
              </div>
              <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#b45309">Pending</span>
            </div>
            <div style="padding:14px;background:#f8fafc;border-radius:12px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <strong style="font-size:13px;color:#0f172a">Ravi Kumar</strong>
                <div style="font-size:11.5px;color:#64748b">Casual Leave • 1 day</div>
              </div>
              <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#b45309">Pending</span>
            </div>
            <div style="padding:14px;background:#f8fafc;border-radius:12px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <strong style="font-size:13px;color:#0f172a">Anita Verma</strong>
                <div style="font-size:11.5px;color:#64748b">Earned Leave • 3 days</div>
              </div>
              <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:#dcfce7;color:#15803d">Approved</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Upcoming Training (Team) -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Upcoming Training (Team)</h3>
            <span style="font-size:12px;color:#64748b">Scheduled departmental learning sessions</span>
          </div>
          <a href="?route=training" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:40px;height:40px;border-radius:10px;background:#0d474c;display:flex;align-items:center;justify-content:center;color:#ffffff;font-size:18px">
                🫀
              </div>
              <div>
                <strong style="display:block;font-size:13.5px;color:#0f172a">Advanced Cardiac Life Support</strong>
                <span style="font-size:11.5px;color:#64748b">Nov 5, 2026 • 25 Participants</span>
              </div>
            </div>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0f2fe;color:#0284c7">Upcoming</span>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px">
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:40px;height:40px;border-radius:10px;background:#0f766e;display:flex;align-items:center;justify-content:center;color:#ffffff;font-size:18px">
                🧼
              </div>
              <div>
                <strong style="display:block;font-size:13.5px;color:#0f172a">Infection Control Practices</strong>
                <span style="font-size:11.5px;color:#64748b">Nov 20, 2026 • 18 Participants</span>
              </div>
            </div>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0f2fe;color:#0284c7">Upcoming</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Team Attendance Gauge & Support Guide -->
    <div style="display:flex;flex-direction:column;gap:18px">
      <!-- Team Attendance Today Gauge Card -->
      <div class="card" style="margin:0;padding:22px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Team Attendance</h3>
          <span style="font-size:12px;color:#64748b">Today</span>
        </div>

        <div style="display:flex;flex-direction:column;align-items:center;padding:12px 0 16px">
          <!-- Circular Donut Attendance Display (Screen 5) -->
          <div style="width:140px;height:140px;border-radius:50%;border:8px solid #0f766e;border-top-color:#ef4444;display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:16px">
            <strong style="font-size:24px;font-weight:800;color:#0f172a"><?= $presentCount ?>/<?= $teamCount ?></strong>
            <span style="font-size:11px;color:#64748b;font-weight:600">Present</span>
          </div>

          <div style="width:100%;display:flex;justify-content:space-around;padding:10px 0;border-top:1px solid #f1f5f9;font-size:12px">
            <span style="display:flex;align-items:center;gap:6px">
              <b style="width:8px;height:8px;border-radius:50%;background:#10b981"></b>
              Present: <strong><?= $presentCount ?></strong>
            </span>
            <span style="display:flex;align-items:center;gap:6px">
              <b style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></b>
              On Leave: <strong><?= $onLeaveCount ?></strong>
            </span>
            <span style="display:flex;align-items:center;gap:6px">
              <b style="width:8px;height:8px;border-radius:50%;background:#ef4444"></b>
              Absent: <strong>0</strong>
            </span>
          </div>
        </div>
      </div>

      <!-- Support Guide Promo Card (Screen 5) -->
      <div style="border-radius:16px;background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);padding:22px;border:1px solid #bbf7d0;display:flex;align-items:center;justify-content:space-between">
        <div>
          <h4 style="font-size:16px;font-weight:800;color:#14532d;margin:0 0 4px">Support Guide</h4>
          <p style="font-size:12px;color:#166534;margin:0 0 12px">Guide your team towards healthcare excellence.</p>
          <a href="?route=training" style="font-size:12.5px;font-weight:700;color:#14532d;text-decoration:none">View Leadership Resources →</a>
        </div>
        <div style="width:36px;height:36px;border-radius:50%;background:#14532d;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700">
          →
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
