<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$totalSessions = count($sessions);
$totalAssigned = 0;
$totalCompleted = 0;
foreach ($sessions as $session) {
  $totalAssigned += (int)($session['assigned_count'] ?? 0);
  $totalCompleted += (int)($session['completed_count'] ?? 0);
}
$totalPending = max(0, $totalAssigned - $totalCompleted);
?>

<div class="content-body">
  <!-- ── 1. Page Header ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Training & Development</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Schedule hospital training sessions, track staff certifications, and manage completion records.</p>
    </div>
    <div>
      <a href="?route=export&type=training" style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none">
        📥 Export CSV
      </a>
    </div>
  </div>

  <!-- ── 2. Top Metric Cards ── -->
  <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;margin-bottom:24px">
    <div class="card" style="padding:18px 20px;margin:0;display:flex;align-items:center;gap:16px">
      <div style="width:44px;height:44px;border-radius:12px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:20px">
        📚
      </div>
      <div>
        <span style="display:block;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase">Total Sessions</span>
        <strong style="font-size:22px;font-weight:800;color:#0f172a"><?= (int)$totalSessions ?></strong>
      </div>
    </div>

    <div class="card" style="padding:18px 20px;margin:0;display:flex;align-items:center;gap:16px">
      <div style="width:44px;height:44px;border-radius:12px;background:#e6f4f1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:20px">
        👥
      </div>
      <div>
        <span style="display:block;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase">Assigned Staff</span>
        <strong style="font-size:22px;font-weight:800;color:#0f766e"><?= (int)$totalAssigned ?></strong>
      </div>
    </div>

    <div class="card" style="padding:18px 20px;margin:0;display:flex;align-items:center;gap:16px">
      <div style="width:44px;height:44px;border-radius:12px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:20px">
        ✓
      </div>
      <div>
        <span style="display:block;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase">Completed</span>
        <strong style="font-size:22px;font-weight:800;color:#15803d"><?= (int)$totalCompleted ?></strong>
      </div>
    </div>

    <div class="card" style="padding:18px 20px;margin:0;display:flex;align-items:center;gap:16px">
      <div style="width:44px;height:44px;border-radius:12px;background:#fef3c7;color:#b45309;display:flex;align-items:center;justify-content:center;font-size:20px">
        ⏳
      </div>
      <div>
        <span style="display:block;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase">Pending Due</span>
        <strong style="font-size:22px;font-weight:800;color:#d97706"><?= (int)$totalPending ?></strong>
      </div>
    </div>
  </div>

  <!-- ── 3. Schedule New Training Form ── -->
  <div class="card" style="padding:24px 28px;margin:0 0 24px;border-radius:14px">
    <div style="margin-bottom:18px">
      <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 4px">Schedule New Training Session</h3>
      <p style="font-size:12.5px;color:#64748b;margin:0">Create mandatory or specialized clinical/non-clinical training for staff.</p>
    </div>

    <form method="post" action="?route=training.store" class="create-employee-form">
      <?= csrf_field() ?>
      <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Session Title *</label>
          <input name="title" placeholder="e.g. Basic Life Support (BLS)" required>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Session Date *</label>
          <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Trainer / Department Head</label>
          <input name="trainer_name" placeholder="e.g. Dr. Ramesh Kumar / External Trainer">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Location / Room</label>
          <input name="location" placeholder="e.g. Conference Hall B / 2nd Floor Seminar Room">
        </div>

        <div style="grid-column:span 2">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Assign Employees * (Hold Ctrl to select multiple)</label>
          <select name="employee_ids[]" multiple size="5" required style="width:100%">
            <option value="all">Assign to All Employees</option>
            <?php foreach ($employees as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>">
                <?= htmlspecialchars(trim(($employee['employee_code'] ? $employee['employee_code'] . ' - ' : '') . $employee['first_name'] . ' ' . $employee['last_name'] . ' | ' . $employee['department'] . ' | ' . $employee['position'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column:span 2">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Session Description / Objectives</label>
          <textarea name="description" rows="2" placeholder="Brief outline of session curriculum, prerequisites, or objectives..."></textarea>
        </div>
      </div>

      <div style="margin-top:16px;text-align:right">
        <button type="submit" class="btn-primary" style="background:#0d474c;padding:10px 24px;font-size:13px;font-weight:700">
          Create Training Session
        </button>
      </div>
    </form>
  </div>

  <!-- ── 4. Sessions List ── -->
  <h3 style="font-size:17px;font-weight:800;color:#0f172a;margin:0 0 16px">Scheduled Sessions</h3>

  <?php if (empty($sessions)): ?>
    <div class="card" style="padding:40px;text-align:center;color:#64748b">
      No training sessions have been scheduled yet.
    </div>
  <?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:18px">
    <?php foreach ($sessions as $session): ?>
      <?php
        $asCount = (int)($session['assigned_count'] ?? 0);
        $cpCount = (int)($session['completed_count'] ?? 0);
        $pct = $asCount > 0 ? round(($cpCount / $asCount) * 100) : 0;
      ?>
      <div class="card" style="padding:22px 26px;margin:0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:12px">
          <div>
            <h4 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 4px"><?= htmlspecialchars($session['title']) ?></h4>
            <div style="font-size:12.5px;color:#64748b;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
              <span>📅 <?= fmt_date($session['session_date']) ?></span>
              <?php if (!empty($session['trainer_name'])): ?>
                <span>👤 Trainer: <?= htmlspecialchars($session['trainer_name']) ?></span>
              <?php endif; ?>
              <?php if (!empty($session['location'])): ?>
                <span>📍 <?= htmlspecialchars($session['location']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:12px;font-weight:700;color:#0f766e;background:#e6f4f1;padding:4px 12px;border-radius:20px">
              <?= $cpCount ?> / <?= $asCount ?> Completed (<?= $pct ?>%)
            </span>
            <?php if (($_SESSION['user']['role'] ?? '') === 'Admin' || ($_SESSION['user']['role'] ?? '') === 'SuperAdmin'): ?>
              <form method="post" action="?route=training.delete" onsubmit="return confirm('Delete this training session and assignments?')" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">
                <button type="submit" style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;padding:5px 12px;border-radius:8px;font-size:11.5px;font-weight:700;cursor:pointer">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($session['description'])): ?>
          <p style="font-size:12.5px;color:#475569;margin:0 0 16px;line-height:1.5">
            <?= nl2br(htmlspecialchars($session['description'])) ?>
          </p>
        <?php endif; ?>

        <!-- Assigned Employees Update Table -->
        <?php if (!empty($bySession[(int)$session['id']])): ?>
          <div style="border-top:1px solid #f1f5f9;padding-top:14px">
            <span style="display:block;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px">Participant Progress</span>
            <div style="display:flex;flex-direction:column;gap:8px">
              <?php foreach ($bySession[(int)$session['id']] as $assignment): ?>
                <form method="post" action="?route=training.update" style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:#f8fafc;border-radius:8px;flex-wrap:wrap">
                  <?= csrf_field() ?>
                  <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                  <div style="flex:1;min-width:180px">
                    <strong style="font-size:13px;color:#0f172a"><?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?></strong>
                  </div>
                  <div style="width:130px">
                    <select name="status" style="font-size:12px;padding:4px 8px">
                      <?php foreach (['Assigned', 'In Progress', 'Completed'] as $st): ?>
                        <option <?= $assignment['status'] === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="width:90px">
                    <input type="number" name="score" min="0" max="100" step="0.1" value="<?= htmlspecialchars((string)$assignment['score']) ?>" placeholder="Score" style="font-size:12px;padding:4px 8px">
                  </div>
                  <div style="flex:1;min-width:160px">
                    <input name="notes" value="<?= htmlspecialchars((string)$assignment['notes']) ?>" placeholder="Evaluation notes" style="font-size:12px;padding:4px 8px">
                  </div>
                  <button type="submit" class="btn-primary" style="background:#0d474c;font-size:11.5px;padding:5px 12px">Save</button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
