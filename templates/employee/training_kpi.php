<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$assignedCount = (int)($trainingStats['assigned'] ?? 12);
$inProgressCount = (int)($trainingStats['in_progress'] ?? 5);
$completedCount = (int)($trainingStats['completed'] ?? 7);

$mockSessions = [
  ['title' => 'Basic Life Support (BLS)', 'date' => 'Oct 15, 2026 • 2h', 'status' => 'Upcoming', 'icon' => '🩺', 'color' => '#e0f2fe'],
  ['title' => 'Infection Control Practices', 'date' => 'Oct 20, 2026 • 1.5h', 'status' => 'Ongoing', 'icon' => '🧼', 'color' => '#dbeafe'],
  ['title' => 'Advanced Cardiac Life Support', 'date' => 'Nov 5, 2026 • 3h', 'status' => 'Upcoming', 'icon' => '❤️', 'color' => '#fef3c7'],
  ['title' => 'Patient Safety & Quality Care', 'date' => 'Nov 12, 2026 • 2h', 'status' => 'Upcoming', 'icon' => '🛡️', 'color' => '#dcfce7'],
];
?>

<div class="content-body">
  <!-- ── Page Header (Screen 6) ── -->
  <div class="dashboard-header-greeting">
    <h1>Training & KPI</h1>
    <p>View assigned training sessions and performance metrics.</p>
  </div>

  <!-- ── Tabs Navigation (Screen 6) ── -->
  <nav class="tab-nav" id="trainingTabs">
    <button type="button" class="active" data-tab-target="trainingTabSection">Training</button>
    <button type="button" data-tab-target="kpiTabSection">KPI</button>
  </nav>

  <!-- ── Training Section ── -->
  <div id="trainingTabSection">
    <!-- Stat Pills Row (Screen 6) -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap">
      <div style="background:#ffffff;border:1px solid #f1f5f9;border-radius:12px;padding:12px 20px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 3px rgba(15,23,42,0.03)">
        <div style="width:32px;height:32px;border-radius:8px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0284c7;font-weight:700">📋</div>
        <div>
          <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Assigned</span>
          <strong style="font-size:20px;font-weight:800;color:#0f172a"><?= $assignedCount ?></strong>
        </div>
      </div>

      <div style="background:#ffffff;border:1px solid #f1f5f9;border-radius:12px;padding:12px 20px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 3px rgba(15,23,42,0.03)">
        <div style="width:32px;height:32px;border-radius:8px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;color:#0369a1;font-weight:700">⏳</div>
        <div>
          <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">In Progress</span>
          <strong style="font-size:20px;font-weight:800;color:#0369a1"><?= $inProgressCount ?></strong>
        </div>
      </div>

      <div style="background:#ffffff;border:1px solid #f1f5f9;border-radius:12px;padding:12px 20px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 3px rgba(15,23,42,0.03)">
        <div style="width:32px;height:32px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#15803d;font-weight:700">✓</div>
        <div>
          <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Completed</span>
          <strong style="font-size:20px;font-weight:800;color:#15803d"><?= $completedCount ?></strong>
        </div>
      </div>
    </div>

    <!-- My Training Sessions Card -->
    <div class="card" style="margin:0 0 20px">
      <div class="chart-card-header">
        <h3 class="chart-card-title">My Training Sessions</h3>
        <select style="width:auto !important;font-size:12px !important;padding:6px 12px !important;border-radius:8px !important">
          <option>All Status</option>
          <option>Upcoming</option>
          <option>Ongoing</option>
          <option>Completed</option>
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px">
        <?php
          $sessionsToDisplay = !empty($myTraining) ? $myTraining : $mockSessions;
          foreach ($sessionsToDisplay as $s):
            $sTitle = (string)($s['title'] ?? '');
            $sDate = isset($s['date']) ? $s['date'] : (fmt_date($s['session_date'] ?? date('Y-m-d')) . ' • 2h');
            $sStatus = (string)($s['status'] ?? 'Upcoming');
            $statusClass = match(strtolower($sStatus)) {
              'completed' => 'active',
              'in progress', 'ongoing' => 'on_leave',
              default => 'on_leave',
            };
        ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f8fafc;border-radius:12px;transition:background .15s">
            <div style="display:flex;align-items:center;gap:14px">
              <div style="width:42px;height:42px;border-radius:10px;background:#e6f4f1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:20px">
                <?= $s['icon'] ?? '🩺' ?>
              </div>
              <div>
                <strong style="font-size:14px;color:#0f172a;display:block"><?= htmlspecialchars($sTitle) ?></strong>
                <span style="font-size:12px;color:#64748b"><?= htmlspecialchars($sDate) ?></span>
              </div>
            </div>
            <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars($sStatus) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:14px;text-align:right">
        <a href="?route=employee-training-kpi" style="font-size:12.5px;font-weight:700;color:#0f766e;text-decoration:none">View All Training →</a>
      </div>
    </div>

    <!-- Recent KPI Reviews Card (Screen 6) -->
    <div class="card" style="margin:0">
      <h3 class="chart-card-title" style="margin-bottom:16px">Recent KPI Reviews</h3>
      <?php if (($kpiStats['review_count'] ?? 0) === 0 && empty($kpiHistory)): ?>
        <div style="padding:40px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center">
          <div style="width:60px;height:60px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="#94a3b8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
          </div>
          <strong style="font-size:14px;color:#0f172a">No KPI reviews yet.</strong>
          <p style="font-size:12px;color:#64748b;margin:4px 0 0">Quarterly appraisals and performance ratings will be published here.</p>
        </div>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:14px;margin-bottom:16px">
          <div style="background:#f8fafc;padding:14px;border-radius:10px">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">My Average</span>
            <strong style="display:block;font-size:22px;color:#0f172a;margin-top:2px"><?= htmlspecialchars((string)($kpiStats['average'] ?? '8.5')) ?>/10</strong>
          </div>
          <div style="background:#f8fafc;padding:14px;border-radius:10px">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Department Avg</span>
            <strong style="display:block;font-size:22px;color:#0f172a;margin-top:2px"><?= htmlspecialchars((string)($kpiStats['department_average'] ?? '8.1')) ?>/10</strong>
          </div>
          <div style="background:#f8fafc;padding:14px;border-radius:10px">
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Hospital Avg</span>
            <strong style="display:block;font-size:22px;color:#0f172a;margin-top:2px"><?= htmlspecialchars((string)($kpiStats['org_average'] ?? '7.9')) ?>/10</strong>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── KPI Tab Section (Interactive Details) ── -->
  <div id="kpiTabSection" style="display:none">
    <div class="card" style="margin:0">
      <h3 class="chart-card-title" style="margin-bottom:16px">Performance & Key Indicators</h3>
      <p style="font-size:13px;color:#64748b;margin-bottom:16px">Annual target score is 8.5/10 across Clinical Competence, Patient Care, and Attendance.</p>
      <?php if (!empty($kpiHistory)): ?>
        <table>
          <thead><tr><th>Review Date</th><th>Score</th><th>Evaluator Notes</th></tr></thead>
          <tbody>
            <?php foreach ($kpiHistory as $row): ?>
              <tr>
                <td><?= fmt_date($row['review_date']) ?></td>
                <td><strong style="color:#0f766e"><?= htmlspecialchars((string)$row['kpi_score']) ?>/10</strong></td>
                <td><?= htmlspecialchars((string)$row['review_notes']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="font-size:13px;color:#94a3b8">No historical KPI records found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function () {
    var tabBtns = document.querySelectorAll('#trainingTabs button');
    var trainingSec = document.getElementById('trainingTabSection');
    var kpiSec = document.getElementById('kpiTabSection');

    tabBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabBtns.forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var target = btn.getAttribute('data-tab-target');
        if (target === 'trainingTabSection') {
          trainingSec.style.display = 'block';
          kpiSec.style.display = 'none';
        } else {
          trainingSec.style.display = 'none';
          kpiSec.style.display = 'block';
        }
      });
    });
  })();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
