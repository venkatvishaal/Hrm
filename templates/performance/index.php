<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="content-body">
  <!-- ── 1. Page Header ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Staff KPI & Performance</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Record periodic staff performance reviews, monitor KPI benchmarks, and analyze clinical trends.</p>
    </div>
    <div>
      <a href="?route=export&type=performance" style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none">
        📥 Export CSV
      </a>
    </div>
  </div>

  <!-- ── 2. Add New KPI Review Card ── -->
  <div class="card" style="padding:22px 28px;margin:0 0 24px;border-radius:14px">
    <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 16px">Record Employee KPI Review</h3>

    <form method="post" action="?route=performance.store">
      <?= csrf_field() ?>
      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Select Employee *</label>
          <select name="employee_id" required style="width:100%">
            <option value="">Select Employee</option>
            <?php foreach (($employees ?? []) as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>">
                <?= htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?> (<?= htmlspecialchars((string)$emp['employee_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">KPI Score (1.0 to 10.0) *</label>
          <input type="number" min="1" max="10" step="0.1" name="kpi_score" placeholder="e.g. 8.5" required>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Review Date *</label>
          <input type="date" name="review_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div style="grid-column:span 3">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Review Notes / Performance Feedback</label>
          <input name="review_notes" placeholder="Evaluation highlights, clinical adherence, teamwork notes...">
        </div>
      </div>

      <div style="margin-top:16px;text-align:right">
        <button type="submit" class="btn-primary" style="background:#0d474c;padding:10px 24px;font-size:13px;font-weight:700">
          Save KPI Review
        </button>
      </div>
    </form>
  </div>

  <!-- ── 3. Table of Performance Reviews ── -->
  <div class="card" style="padding:0;margin:0 0 24px;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Completed Evaluations</h3>
      <span style="font-size:12px;color:#64748b"><?= count($items) ?> record(s)</span>
    </div>

    <div class="table-scroll">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Date</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Employee</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">KPI Score</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Feedback / Notes</th>
            <?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?>
              <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase;text-align:right">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="5" style="padding:32px 18px;text-align:center;color:#64748b;font-size:13px">
                No performance records recorded yet.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($items as $it): ?>
            <?php
              $score = (float)$it['kpi_score'];
              $pct = min(100, max(0, ($score / 10) * 100));
              $scoreColor = $score >= 8.0 ? '#15803d' : ($score >= 6.0 ? '#0284c7' : '#d97706');
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
              <td style="padding:12px 18px;font-size:12.5px;color:#64748b;white-space:nowrap">
                <?= fmt_date($it['review_date']) ?>
              </td>
              <td style="padding:12px 18px">
                <strong style="font-size:13px;color:#0f172a"><?= htmlspecialchars($it['first_name'] . ' ' . $it['last_name']) ?></strong>
              </td>
              <td style="padding:12px 18px">
                <div style="display:flex;align-items:center;gap:10px">
                  <strong style="font-size:13.5px;color:<?= $scoreColor ?>"><?= number_format($score, 1) ?> / 10</strong>
                  <div style="width:70px;height:6px;border-radius:10px;background:#e2e8f0;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $scoreColor ?>;border-radius:10px"></div>
                  </div>
                </div>
              </td>
              <td style="padding:12px 18px;font-size:12.5px;color:#475569;max-width:300px">
                <?= htmlspecialchars((string)$it['review_notes']) ?>
              </td>
              <?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?>
                <td style="padding:12px 18px;text-align:right">
                  <form method="post" action="?route=performance.delete" onsubmit="return confirm('Delete this performance review?')" style="margin:0;display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <button type="submit" style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;padding:4px 10px;border-radius:6px;font-size:11.5px;cursor:pointer">Delete</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── 4. KPI Performance Trend Chart ── -->
  <?php if (!empty($items)): ?>
    <div class="card" style="padding:22px 26px;margin:0">
      <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 14px">Performance Trend Benchmark</h3>
      <div style="height:220px;position:relative">
        <canvas id="kpiChart"></canvas>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var perf = <?= json_encode($items) ?>;
        var ctx = document.getElementById('kpiChart');
        if (ctx && perf && perf.length > 0) {
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: perf.map(function(r) { return r.review_date; }),
              datasets: [{
                label: 'KPI Score',
                data: perf.map(function(r) { return Number(r.kpi_score); }),
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.08)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: { min: 0, max: 10 }
              }
            }
          });
        }
      });
    </script>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
