<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $attendanceSection = $attendanceSection ?? 'live-status'; ?>
<?php $attendanceMenuRoutes = ['live-status' => 'attendance-live-status', 'monthly-summary' => 'attendance-monthly-summary', 'daily-report' => 'attendance-daily-report', 'upload' => 'attendance-upload', 'manual-absent' => 'attendance-manual-absent']; ?>
<section class="attendance-page attendance-section-<?= htmlspecialchars($attendanceSection) ?>">
<nav class="attendance-section-menu" aria-label="Attendance sections">
  <a class="<?= $attendanceSection === 'live-status' ? 'is-active' : '' ?>" href="?route=<?= $attendanceMenuRoutes['live-status'] ?>">Live Status</a>
  <a class="<?= $attendanceSection === 'monthly-summary' ? 'is-active' : '' ?>" href="?route=<?= $attendanceMenuRoutes['monthly-summary'] ?>">Monthly Summary</a>
  <a class="<?= $attendanceSection === 'daily-report' ? 'is-active' : '' ?>" href="?route=<?= $attendanceMenuRoutes['daily-report'] ?>">Daily Report</a>
  <a class="<?= $attendanceSection === 'upload' ? 'is-active' : '' ?>" href="?route=<?= $attendanceMenuRoutes['upload'] ?>">Upload Attendance</a>
  <a class="<?= $attendanceSection === 'manual-absent' ? 'is-active' : '' ?>" href="?route=<?= $attendanceMenuRoutes['manual-absent'] ?>">Manual Absent</a>
</nav>
<?php if ($attendanceSection === 'live-status'): ?>
<div class="attendance-page-actions">
  <a class="btn-compact attendance-export-link" href="?route=export&type=attendance"><span>Export CSV</span></a>
</div>
<?php endif; ?>

<?php if (in_array($attendanceSection, ['live-status', 'monthly-summary'], true)): ?>
<section class="attendance-operations-grid">
  <?php if ($attendanceSection === 'live-status'): ?>
  <div class="card attendance-operations-card" id="attendance-live-status">
    <div class="attendance-section-heading">
      <h2>Live Status</h2>
      <form method="get" class="attendance-operation-date-form">
        <input type="hidden" name="route" value="attendance-live-status">
        <?php if (!empty($liveFilter)): ?><input type="hidden" name="live_filter" value="<?= htmlspecialchars($liveFilter) ?>"><?php endif; ?>
        <label>Date <input type="date" name="operation_date" value="<?= htmlspecialchars($operationDate) ?>"></label>
        <button class="btn-compact">Show</button>
      </form>
    </div>
    <p class="attendance-selected-date"><?= htmlspecialchars(fmt_date($operationDate)) ?></p>
    <?php if (!empty($liveFilter)): ?><p><a class="btn-compact" href="?route=attendance-live-status&amp;operation_date=<?= rawurlencode($operationDate) ?>">Clear status filter</a></p><?php endif; ?>
    <div class="attendance-status-counts">
      <?php foreach (($liveStatusCounts ?? []) as $label => $count): ?><a href="?route=attendance&amp;operation_date=<?= rawurlencode($operationDate) ?>&amp;live_filter=<?= rawurlencode($label) ?>" class="status-<?= htmlspecialchars(strtolower(str_replace([' ', '/'], ['-', '-'], $label))) ?><?= (($liveFilter ?? '') === $label) ? ' is-active' : '' ?>"><strong><?= (int)$count ?></strong> <?= htmlspecialchars($label) ?></a><?php endforeach; ?>
    </div>
    <div class="table-scroll attendance-operations-scroll"><table><tr><th>Employee</th><th>Department</th><th>Status</th><th>In</th><th>Out</th></tr>
      <?php foreach (array_slice(($liveStatusRows ?? []), 0, 30) as $live): ?><tr><td><?= htmlspecialchars(($live['employee_code'] ?? '') . ' - ' . ($live['first_name'] ?? '') . ' ' . ($live['last_name'] ?? '')) ?></td><td><?= htmlspecialchars((string)($live['department'] ?? '')) ?></td><td><span class="status-badge status-<?= htmlspecialchars(strtolower(str_replace([' ', '/'], ['-', '-'], $live['live_status']))) ?>"><?= htmlspecialchars($live['live_status']) ?></span></td><td><?= htmlspecialchars((string)($live['check_in'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($live['check_out'] ?? '-')) ?></td></tr><?php endforeach; ?>
    </table></div>
  </div>
  <?php endif; ?>
  <?php if ($attendanceSection === 'monthly-summary'): ?>
  <div class="card attendance-operations-card" id="attendance-monthly-summary">
    <div class="attendance-section-heading"><h2>Monthly Summary</h2><span><?= htmlspecialchars(date('F Y')) ?></span></div>
    <div class="attendance-summary-tiles"><span><strong><?= (int)($monthlyOperations['present_count'] ?? 0) ?></strong>Present</span><span><strong><?= (int)($monthlyOperations['late_count'] ?? 0) ?></strong>Late</span><span><strong><?= (int)($monthlyOperations['absent_count'] ?? 0) ?></strong>Absent</span><span><strong><?= (int)($monthlyOperations['on_leave_count'] ?? 0) ?></strong>On Leave</span><span><strong><?= (int)($monthlyOperations['on_duty_count'] ?? 0) ?></strong>On Duty</span><span><strong><?= number_format((float)($monthlyOperations['worked_hours'] ?? 0), 1) ?></strong>Worked hours</span></div>
    <h3>Attendance Corrections</h3><p>Pending leave: <strong><?= $pendingApprovals ?></strong> | Punch corrections: <strong><?= $pendingCorrections ?></strong></p>
    <?php if (!empty($anomalies)): ?><p class="err">Missing punches: <?= count($anomalies) ?></p><?php else: ?><p class="ok">No missing punches detected.</p><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($attendanceSection === 'daily-report'): ?>
<section class="card attendance-calendar-report-card" id="attendance-daily-report">
  <div class="attendance-calendar-report-heading">
    <div><h2>Daily Attendance Report</h2><p>Calculated from attendance, approved leave, and duty roster records.</p></div>
    <form method="get" class="attendance-calendar-controls">
      <input type="hidden" name="route" value="attendance-daily-report">
      <label>Month <input type="month" name="calendar_month" value="<?= htmlspecialchars($attendanceCalendarMonth) ?>"></label>
      <button class="btn-compact">View</button>
      <a class="btn-compact" href="?route=attendance.calendar-report&amp;format=xlsx&amp;month=<?= rawurlencode($attendanceCalendarMonth) ?>">Excel</a>
      <a class="btn-compact" href="?route=attendance.calendar-report&amp;format=pdf&amp;month=<?= rawurlencode($attendanceCalendarMonth) ?>">PDF</a>
    </form>
  </div>
  <div class="table-scroll attendance-calendar-report-scroll"><table>
    <tr><th>Date</th><th>Present</th><th>Late</th><th>Absent</th><th>On Leave</th><th>On Duty</th><th>Not Marked</th></tr>
    <?php foreach (($attendanceCalendarReport['days'] ?? []) as $day): ?><tr><td><?= htmlspecialchars(fmt_date((string)$day['date'])) ?></td><td><?= (int)$day['present'] ?></td><td><?= (int)$day['late'] ?></td><td><?= (int)$day['absent'] ?></td><td><?= (int)$day['on_leave'] ?></td><td><?= (int)$day['on_duty'] ?></td><td><?= (int)$day['not_marked'] ?></td></tr><?php endforeach; ?>
    <tr class="attendance-calendar-total"><th>Total</th><th><?= (int)($attendanceCalendarReport['totals']['present'] ?? 0) ?></th><th><?= (int)($attendanceCalendarReport['totals']['late'] ?? 0) ?></th><th><?= (int)($attendanceCalendarReport['totals']['absent'] ?? 0) ?></th><th><?= (int)($attendanceCalendarReport['totals']['on_leave'] ?? 0) ?></th><th><?= (int)($attendanceCalendarReport['totals']['on_duty'] ?? 0) ?></th><th><?= (int)($attendanceCalendarReport['totals']['not_marked'] ?? 0) ?></th></tr>
  </table></div>
</section>
<?php endif; ?>

<?php if (in_array($attendanceSection, ['upload', 'manual-absent'], true)): ?>
<div class="attendance-workspace-grid">
<?php if ($attendanceSection === 'upload'): ?>
<section class="attendance-workspace-column">
  <form method="post" action="?route=attendance.import" enctype="multipart/form-data" class="card grid attendance-import-card" id="attendance-upload">
    <h2>Upload Attendance</h2>
    <a class="btn-compact" href="?route=attendance.template">Download Excel Template</a>
    <small>Use the Excel template columns: employee_code, attendance_date, status, check_in, check_out. Employee ID is also supported.</small>
    <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>">
    <input type="file" name="sheet" accept=".csv,.xls,.xlsx" required>
    <button>Upload</button>
  </form>
</section>
<?php endif; ?>

<?php if ($attendanceSection === 'manual-absent'): ?>
<section class="attendance-workspace-column">
<div class="card grid compact-matrix-card" id="attendance-manual-absent">
  <h2>Manual Mark Absent</h2>
  <form method="get" class="matrix-toolbar attendance-employee-search">
    <input type="hidden" name="route" value="attendance-manual-absent">
    <input type="hidden" name="absent_date" value="<?= htmlspecialchars((string)$absentDate) ?>">
    <input type="text" name="employee_search" value="<?= htmlspecialchars((string)($employeeSearch ?? '')) ?>" placeholder="Employee code / name / mobile" required>
    <button class="btn-compact matrix-btn">Search</button>
  </form>

  <?php if (trim((string)($employeeSearch ?? '')) === ''): ?>
    <p class="matrix-empty">Search an employee to mark absent. Employees are not listed until you search.</p>
  <?php elseif (empty($employees)): ?>
    <p class="matrix-empty">No employees matched "<?= htmlspecialchars((string)$employeeSearch) ?>".</p>
  <?php else: ?>
  <div class="grid absent-results-form">
  <div class="matrix-toolbar attendance-results-toolbar">
    <span class="matrix-result-count"><?= count($employees) ?> result(s) for "<?= htmlspecialchars((string)$employeeSearch) ?>"</span>
    <button type="button" class="btn-compact matrix-btn" id="addAbsentSelections">Add Selected</button>
  </div>
  <div class="card matrix-table-card">
    <table id="absentMatrix">
      <tr><th>Select</th><th>Employee Code</th><th>Name</th><th>Mobile</th></tr>
      <?php foreach (($employees ?? []) as $i => $emp): ?>
        <?php
          $eid = (int)$emp['id'];
          $ecode = (string)($emp['employee_code'] ?? ('EMP-' . str_pad((string)$eid, 4, '0', STR_PAD_LEFT)));
          $ename = trim((string)($emp['first_name'] . ' ' . $emp['last_name']));
          $phone = (string)($emp['phone'] ?? '');
        ?>
        <tr>
          <td>
            <input type="checkbox" class="absent-result-checkbox" value="<?= $eid ?>" data-code="<?= htmlspecialchars($ecode) ?>" data-name="<?= htmlspecialchars($ename) ?>" data-phone="<?= htmlspecialchars($phone) ?>">
          </td>
          <td><?= htmlspecialchars($ecode) ?></td>
          <td><?= htmlspecialchars($ename) ?></td>
          <td><?= htmlspecialchars($phone) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  </div>
  <?php endif; ?>

  <form method="post" action="?route=attendance.mark-absent" class="grid absent-selected-form" id="absentSelectedForm">
    <div class="matrix-toolbar selected-absent-toolbar">
      <label>Absent Date
        <input type="date" id="absentMarkDate" value="<?= htmlspecialchars((string)$absentDate) ?>" required>
      </label>
    <button class="btn-compact matrix-btn">Mark Absent</button>
    </div>
    <div class="card matrix-table-card absent-selected-card">
      <table id="selectedAbsentMatrix">
        <tr><th>Remove</th><th>Employee Code</th><th>Name</th><th>Mobile</th><th>Date</th></tr>
        <tr class="selected-empty-row"><td colspan="5">No employees selected yet.</td></tr>
      </table>
    </div>
    <div id="selectedAbsentFields"></div>
  </form>
</div>
</section>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if ($attendanceSection === 'manual-absent'): ?>
<script>
  (function () {
    const storeKey = 'hrmAbsentSelections';
    const addButton = document.getElementById('addAbsentSelections');
    const form = document.getElementById('absentSelectedForm');
    const dateInput = document.getElementById('absentMarkDate');
    const table = document.getElementById('selectedAbsentMatrix');
    const fields = document.getElementById('selectedAbsentFields');
    if (!form || !dateInput || !table || !fields) return;

    const load = function () {
      try {
        return JSON.parse(sessionStorage.getItem(storeKey) || '{}') || {};
      } catch (e) {
        return {};
      }
    };
    const save = function (items) {
      sessionStorage.setItem(storeKey, JSON.stringify(items));
    };
    const collectCheckedResults = function () {
      const items = load();
      document.querySelectorAll('.absent-result-checkbox:checked').forEach(function (checkbox) {
        items[checkbox.value] = {
          code: checkbox.getAttribute('data-code') || checkbox.value,
          name: checkbox.getAttribute('data-name') || '',
          phone: checkbox.getAttribute('data-phone') || '',
          date: dateInput.value
        };
        checkbox.checked = false;
      });
      save(items);
    };
    const render = function () {
      const items = load();
      table.querySelectorAll('tr[data-selected-id]').forEach(function (row) { row.remove(); });
      fields.innerHTML = '';
      const emptyRow = table.querySelector('.selected-empty-row');
      const ids = Object.keys(items);
      if (emptyRow) emptyRow.style.display = ids.length ? 'none' : '';

      ids.forEach(function (id) {
        const item = items[id];
        const row = document.createElement('tr');
        row.setAttribute('data-selected-id', id);
        row.innerHTML =
          '<td><button type="button" class="btn-compact remove-absent-selection" data-id="' + id + '">X</button></td>' +
          '<td>' + escapeHtml(item.code || id) + '</td>' +
          '<td>' + escapeHtml(item.name || '') + '</td>' +
          '<td>' + escapeHtml(item.phone || '') + '</td>' +
          '<td>' + escapeHtml(item.date || dateInput.value) + '</td>';
        table.appendChild(row);

        fields.insertAdjacentHTML('beforeend',
          '<input type="hidden" name="selected_rows[]" value="' + id + '">' +
          '<input type="hidden" name="employee_ids[' + id + ']" value="' + id + '">' +
          '<input type="hidden" name="attendance_dates[' + id + ']" value="' + escapeHtml(item.date || dateInput.value) + '">'
        );
      });
    };
    const escapeHtml = function (value) {
      return String(value).replace(/[&<>"']/g, function (ch) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
      });
    };

    if (addButton) {
      addButton.addEventListener('click', function () {
        collectCheckedResults();
        render();
      });
    }

    table.addEventListener('click', function (event) {
      const button = event.target.closest('.remove-absent-selection');
      if (!button) return;
      const items = load();
      delete items[button.getAttribute('data-id') || ''];
      save(items);
      render();
    });

    dateInput.addEventListener('change', function () {
      const items = load();
      Object.keys(items).forEach(function (id) { items[id].date = dateInput.value; });
      save(items);
      render();
    });

    form.addEventListener('submit', function (event) {
      collectCheckedResults();
      render();
      if (!Object.keys(load()).length) {
        event.preventDefault();
        alert('Select at least one employee to mark absent.');
        return;
      }
      sessionStorage.removeItem(storeKey);
    });

    render();
  })();
</script>
<?php endif; ?>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
