<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$totalEmployees  = (int)($counts['employees'] ?? 1248);
$pendingLeaves   = (int)($counts['leaves'] ?? 12);
$activeOnboarding = (int)($counts['onboarding'] ?? 5);
$openTraining    = (int)($counts['training'] ?? 3);
$kpiReviews      = (int)($counts['performance'] ?? 8);
$exitRecords     = (int)($counts['exits'] ?? 2);
$dutyRosters     = (int)($counts['rosters'] ?? 42);
$pendingDocuments = (int)($counts['pending_documents'] ?? 5);
$healthDue       = (int)($counts['health_due'] ?? 0);
?>

<div class="content-body">
  <!-- ── 1. Organization Overview Header (Screen 3: Admin Dashboard) ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Organization Overview</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Complete workforce insights and administration control.</p>
    </div>
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:600;color:#475569">
      📅 <?= date('M 1, Y') ?> – <?= date('M t, Y') ?>
    </div>
  </div>

  <!-- ── 2. Top 4 Metric Cards (Screen 3) ── -->
  <div class="stats-overview-grid">
    <!-- Total Staff -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-staff">
          <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4Zm8 0c-.33 0-.68.02-1.05.05C16.19 13.89 18 15.02 18 17v2h6v-2c0-2.66-5.33-4-8-4Z"/></svg>
        </div>
        <span class="stat-metric-label">Total Staff</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value"><?= number_format($totalEmployees) ?></span>
        <span class="stat-metric-badge positive">↑ 5%</span>
      </div>
    </div>

    <!-- Doctors -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-doctors">
          <svg viewBox="0 0 24 24"><path d="M10.5 13H8v-3h2.5V7.5h3V10H16v3h-2.5v2.5h-3V13ZM12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/></svg>
        </div>
        <span class="stat-metric-label">Doctors</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value">234</span>
        <span class="stat-metric-badge positive">↑ 3%</span>
      </div>
    </div>

    <!-- Nurses -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-nurses">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
        </div>
        <span class="stat-metric-label">Nurses</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value">612</span>
        <span class="stat-metric-badge positive">↑ 4%</span>
      </div>
    </div>

    <!-- Support Staff -->
    <div class="stat-metric-card">
      <div class="stat-metric-top">
        <div class="stat-metric-icon icon-support">
          <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        </div>
        <span class="stat-metric-label">Support Staff</span>
      </div>
      <div class="stat-metric-bottom">
        <span class="stat-metric-value">402</span>
        <span class="stat-metric-badge positive">↑ 2%</span>
      </div>
    </div>
  </div>

  <!-- ── 3. Middle Row: Staff Trend Chart + Department Distribution Donut (Screen 3) ── -->
  <div class="dashboard-charts-grid">
    <!-- Staff Trend Line Chart -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">Staff Trend</h3>
          <span class="chart-card-sub">Growth over the last 6 months</span>
        </div>
        <select style="width:auto !important;font-size:12px !important;padding:5px 10px !important;border-radius:8px !important">
          <option>Last 6 Months</option>
          <option>This Year</option>
          <option>All Time</option>
        </select>
      </div>
      <div style="height:210px;position:relative">
        <canvas id="staffTrendChart"></canvas>
      </div>
    </div>

    <!-- Department Distribution Donut Chart -->
    <div class="chart-card">
      <div class="chart-card-header">
        <h3 class="chart-card-title">Department Distribution</h3>
      </div>
      <div style="display:flex;align-items:center;gap:20px;height:210px">
        <div style="width:145px;height:145px;position:relative;flex-shrink:0">
          <canvas id="adminDeptDonut"></canvas>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;text-align:center">
            <strong style="font-size:16px;font-weight:800;color:#0f172a;line-height:1"><?= number_format($totalEmployees) ?></strong>
            <span style="font-size:9.5px;color:#64748b;font-weight:600">Employees</span>
          </div>
        </div>
        <div style="flex:1;display:flex;flex-direction:column;gap:6px;font-size:12px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="display:flex;align-items:center;gap:6px"><b style="width:8px;height:8px;border-radius:50%;background:#0f766e"></b> Nursing</span>
            <strong style="color:#0f172a">49%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="display:flex;align-items:center;gap:6px"><b style="width:8px;height:8px;border-radius:50%;background:#0284c7"></b> Medical</span>
            <strong style="color:#0f172a">19%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="display:flex;align-items:center;gap:6px"><b style="width:8px;height:8px;border-radius:50%;background:#38bdf8"></b> Administration</span>
            <strong style="color:#0f172a">12%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="display:flex;align-items:center;gap:6px"><b style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></b> Support Services</span>
            <strong style="color:#0f172a">11%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="display:flex;align-items:center;gap:6px"><b style="width:8px;height:8px;border-radius:50%;background:#94a3b8"></b> Others</span>
            <strong style="color:#0f172a">9%</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── 4. Bottom Row: Recent Activities + Pending Approvals (Screen 3) ── -->
  <div class="dashboard-bottom-grid">
    <!-- Recent Activities -->
    <div class="chart-card">
      <div class="chart-card-header">
        <h3 class="chart-card-title">Recent Activities</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div class="activity-item">
          <div class="activity-icon" style="background:#e0f2fe;color:#0284c7">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
          </div>
          <div class="activity-meta" style="flex:1">
            <strong>New employee onboarded</strong>
            <span>Priya Sharma — Staff Nurse</span>
          </div>
          <span style="font-size:11px;color:#94a3b8">10:24 AM</span>
        </div>

        <div class="activity-item">
          <div class="activity-icon" style="background:#dcfce7;color:#16a34a">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
          </div>
          <div class="activity-meta" style="flex:1">
            <strong>Leave request approved</strong>
            <span>Ravi Kumar — 2 days (Sick Leave)</span>
          </div>
          <span style="font-size:11px;color:#94a3b8">09:18 AM</span>
        </div>

        <div class="activity-item">
          <div class="activity-icon" style="background:#fef3c7;color:#d97706">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
          </div>
          <div class="activity-meta" style="flex:1">
            <strong>Training session scheduled</strong>
            <span>Infection Control Practices</span>
          </div>
          <span style="font-size:11px;color:#94a3b8">08:45 AM</span>
        </div>
      </div>
    </div>

    <!-- Pending Approvals (Screen 3) -->
    <div class="chart-card">
      <div class="chart-card-header">
        <h3 class="chart-card-title">Pending Approvals</h3>
        <a href="?route=leave" style="font-size:12.5px;font-weight:600;color:#0f766e;text-decoration:none">View All →</a>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <a href="?route=leave" style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:10px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="width:30px;height:30px;border-radius:8px;background:#fee2e2;color:#ef4444;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">
              <?= $pendingLeaves ?>
            </span>
            <strong style="font-size:13.5px;color:#0f172a">Leave Requests</strong>
          </div>
          <span style="color:#94a3b8;font-size:16px">›</span>
        </a>

        <a href="?route=training" style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:10px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="width:30px;height:30px;border-radius:8px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">
              <?= $openTraining ?>
            </span>
            <strong style="font-size:13.5px;color:#0f172a">Training Enrollments</strong>
          </div>
          <span style="color:#94a3b8;font-size:16px">›</span>
        </a>

        <a href="?route=documents" style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:10px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="width:30px;height:30px;border-radius:8px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">
              <?= $pendingDocuments ?>
            </span>
            <strong style="font-size:13.5px;color:#0f172a">Profile & Document Updates</strong>
          </div>
          <span style="color:#94a3b8;font-size:16px">›</span>
        </a>

        <a href="?route=recruitment" style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:10px;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="width:30px;height:30px;border-radius:8px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px">
              <?= $exitRecords ?>
            </span>
            <strong style="font-size:13.5px;color:#0f172a">Recruitment Requests</strong>
          </div>
          <span style="color:#94a3b8;font-size:16px">›</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // 1. Staff Trend Line Chart (Screen 3)
    var trendCtx = document.getElementById('staffTrendChart');
    if (trendCtx) {
      new Chart(trendCtx, {
        type: 'line',
        data: {
          labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
          datasets: [
            {
              label: 'Doctors',
              data: [190, 205, 215, 220, 228, 234],
              borderColor: '#0284c7',
              backgroundColor: 'rgba(2, 132, 199, 0.08)',
              borderWidth: 2,
              tension: 0.3,
              fill: true,
            },
            {
              label: 'Nurses',
              data: [520, 540, 560, 580, 595, 612],
              borderColor: '#10b981',
              backgroundColor: 'transparent',
              borderWidth: 2,
              tension: 0.3,
            },
            {
              label: 'Support Staff',
              data: [350, 365, 375, 385, 395, 402],
              borderColor: '#f59e0b',
              backgroundColor: 'transparent',
              borderWidth: 2,
              tension: 0.3,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: 'Inter' } } } },
          scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#64748b' } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11, family: 'Inter' }, color: '#64748b' } }
          }
        }
      });
    }

    // 2. Department Distribution Donut Chart
    var donutCtx = document.getElementById('adminDeptDonut');
    if (donutCtx) {
      new Chart(donutCtx, {
        type: 'doughnut',
        data: {
          labels: ['Nursing', 'Medical', 'Administration', 'Support Services', 'Others'],
          datasets: [{
            data: [49, 19, 12, 11, 9],
            backgroundColor: ['#0f766e', '#0284c7', '#38bdf8', '#f59e0b', '#94a3b8'],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: { legend: { display: false } }
        }
      });
    }
  });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
