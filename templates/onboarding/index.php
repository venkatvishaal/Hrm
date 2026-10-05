<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$statusOptions = ['Pending', 'In Progress', 'Completed'];
$dueOptions = [
  '' => 'All due dates',
  'overdue' => 'Overdue',
  'week' => 'Due in 7 days',
  'no-target' => 'No target date',
];
$activeFilters = array_filter([$status ?: null, $q ? 'Search: ' . $q : null, $due ? ($dueOptions[$due] ?? null) : null]);
$selected = $selectedItem ?? null;
$progressFor = static function (array $item): int {
  return ((int)$item['orientation_done'] + (int)$item['documents_collected'] + (int)$item['assets_issued'] + (int)$item['training_assigned']) * 25;
};
$dueLabel = static function (array $item): array {
  $dueDays = $item['due_days'] === null ? null : (int)$item['due_days'];
  if ($item['status'] === 'Completed') {
    return ['Completed', 'onboarding-neutral'];
  }
  if ($dueDays !== null && $dueDays < 0) {
    return [abs($dueDays) . ' day(s) overdue', 'status-rejected'];
  }
  if ($dueDays === 0) {
    return ['Due today', 'status-pending'];
  }
  if ($dueDays !== null) {
    return ['Due in ' . $dueDays . ' day(s)', $dueDays <= 7 ? 'status-pending' : 'onboarding-neutral'];
  }
  return ['No target date', 'onboarding-neutral'];
};
?>

<section class="onboarding-process onboarding-list-first">
  <div class="onboarding-command">
    <div>
      <?php if ($selected): ?>
        <p><?= htmlspecialchars((string)$selected['employee_code']) ?> | <?= htmlspecialchars($selected['first_name'] . ' ' . $selected['last_name']) ?></p>
      <?php endif; ?>
    </div>
    <div class="onboarding-command-actions">
      <?php if ($selected): ?><a class="btn-link" href="?route=onboarding">Back to List</a><?php endif; ?>
      <a class="btn-link" href="?route=export&type=onboarding">Export CSV</a>
    </div>
  </div>

  <?php if (!$selected): ?>
    <div class="stats onboarding-summary">
      <a class="card onboarding-stat status-pending" href="?route=onboarding&status=Pending" target="_blank" rel="noopener"><strong><?= (int)($summary['pending'] ?? 0) ?></strong><span>Pending</span></a>
      <a class="card onboarding-stat onboarding-progress" href="?route=onboarding&status=In%20Progress" target="_blank" rel="noopener"><strong><?= (int)($summary['in_progress'] ?? 0) ?></strong><span>In Progress</span></a>
      <a class="card onboarding-stat onboarding-alert" href="?route=onboarding&due=overdue" target="_blank" rel="noopener"><strong><?= (int)($summary['overdue'] ?? 0) ?></strong><span>Overdue</span></a>
      <a class="card onboarding-stat onboarding-soon" href="?route=onboarding&due=week" target="_blank" rel="noopener"><strong><?= (int)($summary['due_soon'] ?? 0) ?></strong><span>Due in 7 days</span></a>
      <a class="card onboarding-stat status-approved" href="?route=onboarding&status=Completed" target="_blank" rel="noopener"><strong><?= (int)($summary['completed'] ?? 0) ?></strong><span>Completed</span></a>
      <a class="card onboarding-stat" href="?route=onboarding" target="_blank" rel="noopener"><strong><?= (int)($summary['average_progress'] ?? 0) ?>%</strong><span>Average</span></a>
    </div>

    <form method="get" class="onboarding-filter">
      <input type="hidden" name="route" value="onboarding">
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, code, department, mentor">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach ($statusOptions as $option): ?>
          <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="due">
        <?php foreach ($dueOptions as $value => $label): ?>
          <option value="<?= htmlspecialchars($value) ?>" <?= $due === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-compact">Apply</button>
      <?php if ($activeFilters): ?><a class="btn-link onboarding-clear" href="?route=onboarding">Clear</a><?php endif; ?>
    </form>

    <?php if (!empty($employees)): ?>
      <form method="post" action="?route=onboarding.store" class="card onboarding-create onboarding-create-compact">
        <label>Employee
          <select name="employee_id" required>
            <option value="">Select employee</option>
            <?php foreach ($employees as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars(trim(($employee['employee_code'] ? $employee['employee_code'] . ' - ' : '') . $employee['first_name'] . ' ' . $employee['last_name'] . ' | ' . $employee['department'] . ' | ' . $employee['position'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Start<input id="onboardingStartDate" type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
        <label>Target (default +3 days)<input id="onboardingTargetDate" type="date" name="target_completion_date" value="<?= date('Y-m-d', strtotime('+3 days')) ?>"></label>
        <button class="btn-compact onboarding-create-btn">Add</button>
      </form>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="card onboarding-empty"><strong>No onboarding records found.</strong><span>Adjust filters or create onboarding from an employee without an existing record.</span></div>
    <?php else: ?>
      <div class="table-scroll onboarding-table-list">
        <table>
          <tr><th>Employee</th><th>Dept</th><th>Position</th><th>Status</th><th>Progress</th><th>Target</th><th>Due</th><th>Mentor</th><th>Action</th></tr>
          <?php foreach ($items as $item): ?>
            <?php [$dueText, $dueClass] = $dueLabel($item); $progress = $progressFor($item); ?>
            <tr>
              <td><strong><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></strong><span><?= htmlspecialchars((string)$item['employee_code']) ?></span></td>
              <td><?= htmlspecialchars((string)$item['department']) ?></td>
              <td><?= htmlspecialchars((string)$item['position']) ?></td>
              <td><span class="status-badge <?= $item['status'] === 'Completed' ? 'status-approved' : ($item['status'] === 'In Progress' ? 'onboarding-progress' : 'status-pending') ?>"><?= htmlspecialchars($item['status']) ?></span></td>
              <td><div class="onboarding-progress-row"><div class="onboarding-progressbar"><span style="width:<?= $progress ?>%"></span></div><strong><?= $progress ?>%</strong></div></td>
              <td><?= htmlspecialchars((string)($item['target_completion_date'] ?: '-')) ?></td>
              <td><span class="status-badge <?= htmlspecialchars($dueClass) ?>"><?= htmlspecialchars($dueText) ?></span></td>
              <td><?= htmlspecialchars((string)($item['mentor_name'] ?: '-')) ?></td>
              <td><a class="btn-link" href="?route=onboarding&id=<?= (int)$item['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
    <?php if (false): ?>
      <section class="card manpower-panel onboarding-manpower-panel" id="manpower-requisitions">
        <div class="training-head">
          <div>
            <h2>Manpower Requisition</h2>
            <p class="muted">Request and review new staff requirements.</p>
          </div>
        </div>
        <form method="post" action="?route=manpower-requisition.store" class="manpower-form">
          <?= csrf_field() ?>
          <label>Department<input name="department" required></label>
          <label>Designation<input name="designation" required></label>
          <div class="manpower-inline">
            <label>Vacancies<input type="number" name="vacancies" min="1" value="1" required></label>
            <label>Priority<select name="priority"><option>Normal</option><option>High</option><option>Urgent</option><option>Low</option></select></label>
          </div>
          <label>Justification<textarea name="justification" required></textarea></label>
          <button>Submit Requisition</button>
        </form>
        <div class="manpower-list">
          <?php if (empty($manpowerRequisitions)): ?><p class="muted">No manpower requisitions submitted.</p><?php endif; ?>
          <?php foreach (($manpowerRequisitions ?? []) as $request): ?>
            <article class="manpower-card">
              <div><strong><?= htmlspecialchars((string)$request['designation']) ?></strong><span><?= htmlspecialchars((string)$request['department']) ?> · <?= (int)$request['vacancies'] ?> vacancy</span></div>
              <span class="status-badge <?= $request['status'] === 'Approved' ? 'status-approved' : ($request['status'] === 'Rejected' ? 'status-rejected' : 'status-pending') ?>"><?= htmlspecialchars((string)$request['status']) ?></span>
              <p><?= htmlspecialchars((string)$request['justification']) ?></p>
              <form method="post" action="?route=manpower-requisition.status" class="manpower-review-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                <select name="status"><?php foreach (['Pending','Approved','Rejected','Closed'] as $statusOption): ?><option value="<?= $statusOption ?>" <?= $request['status'] === $statusOption ? 'selected' : '' ?>><?= $statusOption ?></option><?php endforeach; ?></select>
                <input name="review_remarks" value="<?= htmlspecialchars((string)($request['review_remarks'] ?? '')) ?>" placeholder="Review remarks">
                <button class="btn-compact">Save</button>
              </form>
              <?php if ($request['status'] === 'Approved'): ?><a class="btn-link" href="?route=recruitment.create">Create Employee</a><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <?php
      $checklist = [
        'orientation_done' => ['Orientation', (int)$selected['orientation_done']],
        'documents_collected' => ['Documents', (int)$selected['documents_collected']],
        'assets_issued' => ['Assets', (int)$selected['assets_issued']],
        'training_assigned' => ['Training', (int)$selected['training_assigned']],
      ];
      $progress = $progressFor($selected);
      [$dueText, $dueClass] = $dueLabel($selected);
    ?>
    <form method="post" action="?route=onboarding.update" class="card onboarding-card onboarding-detail-card">
      <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
      <div class="onboarding-card-head">
        <div>
          <strong><?= htmlspecialchars($selected['first_name'] . ' ' . $selected['last_name']) ?></strong>
          <small><?= htmlspecialchars((string)$selected['employee_code']) ?> | <?= htmlspecialchars((string)$selected['department']) ?> | <?= htmlspecialchars((string)$selected['position']) ?></small>
        </div>
        <div class="onboarding-badges">
          <span class="status-badge <?= $selected['status'] === 'Completed' ? 'status-approved' : ($selected['status'] === 'In Progress' ? 'onboarding-progress' : 'status-pending') ?>"><?= htmlspecialchars($selected['status']) ?></span>
          <span class="status-badge <?= htmlspecialchars($dueClass) ?>"><?= htmlspecialchars($dueText) ?></span>
        </div>
      </div>
      <div class="onboarding-progress-row">
        <div class="onboarding-progressbar"><span style="width:<?= $progress ?>%"></span></div>
        <strong><?= $progress ?>%</strong>
      </div>
      <div class="onboarding-steps">
        <?php foreach ($checklist as $field => [$label, $checked]): ?>
          <label class="<?= $checked ? 'step-done' : '' ?>"><input type="checkbox" name="<?= htmlspecialchars($field) ?>" <?= $checked ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="onboarding-fields">
        <label>Start<input type="date" name="start_date" value="<?= htmlspecialchars((string)$selected['start_date']) ?>" required></label>
        <label>Target<input type="date" name="target_completion_date" value="<?= htmlspecialchars((string)$selected['target_completion_date']) ?>"></label>
        <label>Status
          <select name="status">
            <?php foreach ($statusOptions as $option): ?><option <?= $selected['status'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Mentor<input name="mentor_name" value="<?= htmlspecialchars((string)$selected['mentor_name']) ?>" placeholder="Mentor or buddy"></label>
      </div>
      <label>Notes<textarea name="notes" rows="3"><?= htmlspecialchars((string)$selected['notes']) ?></textarea></label>
      <div class="onboarding-actions">
        <button class="btn-compact">Update</button>
        <a class="btn-link onboarding-clear" href="?route=onboarding">Close</a>
        <?php if (($_SESSION['user']['role'] ?? '') === 'Admin' || ($_SESSION['user']['role'] ?? '') === 'SuperAdmin'): ?>
          <button class="btn-compact btn-danger" formaction="?route=onboarding.delete" formmethod="post" onclick="return confirm('Delete onboarding record?')">Delete</button>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</section>

<?php if (!$selected): ?>
<script>
  (function () {
    var start = document.getElementById('onboardingStartDate');
    var target = document.getElementById('onboardingTargetDate');
    if (!start || !target) return;
    start.addEventListener('change', function () {
      if (!start.value || target.dataset.overridden === '1') return;
      var date = new Date(start.value + 'T00:00:00');
      date.setDate(date.getDate() + 3);
      target.value = date.toISOString().slice(0, 10);
    });
    target.addEventListener('change', function () {
      target.dataset.overridden = '1';
    });
  })();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
