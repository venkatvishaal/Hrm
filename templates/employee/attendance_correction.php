<?php require __DIR__ . '/../layouts/header.php'; ?>

<section class="card correction-page">
  <div class="training-head">
    <div>
      <h2>Correction Request</h2>
      <p class="muted">Submit missing punch, wrong punch, or status correction details for HR review.</p>
    </div>
    <a class="btn-link" href="?route=employee-attendance">Attendance Report</a>
  </div>
  <form method="post" action="?route=employee.attendance-correction.store" class="grid correction-form">
    <label>Date
      <input type="date" name="correction_date" value="<?= date('Y-m-d') ?>" required>
    </label>
    <label>Time
      <input type="time" name="correction_time" required>
    </label>
    <label>Correction Type
      <select name="correction_type" required>
        <option>Check In</option>
        <option>Check Out</option>
        <option>Status Correction</option>
        <option>Other</option>
      </select>
    </label>
    <label class="wide-field">Justification
      <textarea name="justification" placeholder="Enter justification" required></textarea>
    </label>
    <button>Submit Request</button>
  </form>
</section>

<section class="card">
  <h2>My Correction Requests</h2>
  <div class="table-scroll">
    <table>
      <tr><th>Date</th><th>Time</th><th>Type</th><th>Status</th><th>Justification</th><th>HR Remarks</th></tr>
      <?php if (empty($requests)): ?>
        <tr><td colspan="6">No correction requests submitted.</td></tr>
      <?php else: ?>
        <?php foreach ($requests as $request): ?>
          <tr>
            <td><?= htmlspecialchars(fmt_date($request['correction_date'])) ?></td>
            <td><?= htmlspecialchars(substr((string)$request['correction_time'], 0, 5)) ?></td>
            <td><?= htmlspecialchars((string)$request['correction_type']) ?></td>
            <td><span class="status-badge status-<?= htmlspecialchars(strtolower((string)$request['status'])) ?>"><?= htmlspecialchars((string)$request['status']) ?></span></td>
            <td><?= htmlspecialchars((string)$request['justification']) ?></td>
            <td><?= htmlspecialchars((string)($request['hr_remarks'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
