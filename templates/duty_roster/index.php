<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$renderDutyTime = static function (array $row): string {
  $start = trim((string)($row['start_time'] ?? ''));
  $end = trim((string)($row['end_time'] ?? ''));
  if ($start === '' && $end === '') {
    return 'Default duty';
  }
  return htmlspecialchars(substr($start, 0, 5) . ' - ' . substr($end, 0, 5));
};
$renderEmployeeMeta = static function (array $employee): string {
  $parts = array_filter([
    trim((string)($employee['department'] ?? '')),
    trim((string)($employee['position'] ?? '')),
  ]);
  return htmlspecialchars($parts ? implode(' | ', $parts) : 'No department assigned');
};
$isOffDuty = static function (array $slots): bool {
  foreach ($slots as $slot) {
    if (strtoupper(trim((string)$slot['shift_name'])) === 'OFF') {
      return true;
    }
  }
  return false;
};
$firstDutySlot = static function (array $slots): ?array {
  foreach ($slots as $slot) {
    if (strtoupper(trim((string)$slot['shift_name'])) !== 'OFF') {
      return $slot;
    }
  }
  return null;
};
$activeShiftOptions = array_keys($shiftDefaults ?? []);
$exportUrl = static function (string $format) use ($weekStart, $activeFilters): string {
  $params = [
    'route' => 'duty-roster.export',
    'format' => $format,
    'week_start' => $weekStart,
  ];
  if (!empty($activeFilters['department']) && $activeFilters['department'] !== '__all__') {
    $params['department'] = (string)$activeFilters['department'];
  }
  if (!empty($activeFilters['employee_q'])) {
    $params['employee_q'] = (string)$activeFilters['employee_q'];
  }
  return '?' . http_build_query($params);
};
?>
<div class="print-only duty-print-header">
  <h1>Hospital HR</h1>
  <h2>Duty Roster</h2>
  <p>Date Range: <?= htmlspecialchars(fmt_date($weekStart)) ?> to <?= htmlspecialchars(fmt_date($weekEnd)) ?></p>
</div>

<section class="duty-status-panel duty-overview no-print">
  <div class="stats duty-status-stats">
    <div class="card duty-summary-card duty-summary-success"><strong>Available Today</strong><span><?= (int)($dutyStatusSummary['available'] ?? 0) ?></span></div>
    <div class="card duty-summary-card duty-summary-info"><strong>Doctors Available</strong><span><?= (int)($dutyStatusSummary['doctors'] ?? 0) ?></span></div>
    <div class="card duty-summary-card duty-summary-danger"><strong>Off Today</strong><span><?= (int)($dutyStatusSummary['off'] ?? 0) ?></span></div>
    <button type="button" class="card duty-summary-card duty-shortage-card <?= !empty($staffShortageAlerts) ? 'has-alerts duty-summary-danger' : 'duty-summary-success' ?>" data-open-duty-modal data-duty-modal-tab="shortage">
      <strong>Staff Shortage Alerts</strong>
      <span><?= count($staffShortageAlerts ?? []) ?></span>
    </button>
  </div>
  <div class="duty-head-actions">
    <button type="button" class="btn-compact duty-modal-open" data-open-duty-modal>History</button>
  </div>
</section>

<div class="duty-modal no-print" data-duty-modal hidden>
  <div class="duty-modal-backdrop" data-close-duty-modal></div>
  <section class="duty-modal-card" role="dialog" aria-modal="true" aria-labelledby="dutyModalTitle">
    <div class="duty-modal-head">
      <div>
        <h2 id="dutyModalTitle">Duty Status & History</h2>
        <p><?= htmlspecialchars(fmt_date($today ?? date('Y-m-d'))) ?> current status with past, current, and upcoming duty history</p>
      </div>
      <button type="button" class="icon-btn duty-modal-close" data-close-duty-modal title="Close">X</button>
    </div>

    <div class="duty-modal-tabs" role="tablist">
      <button type="button" class="active" data-duty-tab="available">Today</button>
      <button type="button" data-duty-tab="past">Past</button>
      <button type="button" data-duty-tab="current">Current</button>
      <button type="button" data-duty-tab="upcoming">Upcoming</button>
      <button type="button" data-duty-tab="shortage">Shortage</button>
    </div>

    <div class="duty-modal-body">
      <div class="duty-tab-panel active" data-duty-panel="available">
        <div class="duty-modal-columns">
          <section>
            <h3>Doctors Available</h3>
            <div class="duty-person-list">
              <?php if (!empty($doctorsToday)): ?>
                <?php foreach ($doctorsToday as $item): ?>
                  <article class="card duty-person-card">
                    <strong><?= htmlspecialchars($item['employee']['first_name'].' '.$item['employee']['last_name']) ?></strong>
                    <small><?= $renderEmployeeMeta($item['employee']) ?></small>
                    <?php if (!empty($item['rows'])): ?>
                      <?php foreach ($item['rows'] as $row): ?>
                        <span><?= htmlspecialchars($row['shift_name']) ?> - <?= $renderDutyTime($row) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span>Default duty - 09:00 - 17:00</span>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="card duty-empty">No doctor duty found for today.</div>
              <?php endif; ?>
            </div>
          </section>
          <section>
            <h3>Employees Available</h3>
            <div class="duty-person-list">
              <?php if (!empty($availableToday)): ?>
                <?php foreach ($availableToday as $item): ?>
                  <article class="card duty-person-card">
                    <strong><?= htmlspecialchars($item['employee']['first_name'].' '.$item['employee']['last_name']) ?></strong>
                    <small><?= $renderEmployeeMeta($item['employee']) ?></small>
                    <?php if (!empty($item['rows'])): ?>
                      <?php foreach ($item['rows'] as $row): ?>
                        <span><?= htmlspecialchars($row['shift_name']) ?> - <?= $renderDutyTime($row) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span>Default duty - 09:00 - 17:00</span>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="card duty-empty">No employees are available today.</div>
              <?php endif; ?>
            </div>
          </section>
          <section>
            <h3>Off Duty Today</h3>
            <div class="duty-person-list">
              <?php if (!empty($offToday)): ?>
                <?php foreach ($offToday as $item): ?>
                  <article class="card duty-person-card duty-off-card">
                    <strong><?= htmlspecialchars($item['employee']['first_name'].' '.$item['employee']['last_name']) ?></strong>
                    <small><?= $renderEmployeeMeta($item['employee']) ?></small>
                    <span>OFF</span>
                  </article>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="card duty-empty">No one is marked OFF today.</div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>

      <?php foreach (['past' => 'Past Duties', 'current' => 'Current Duties', 'upcoming' => 'Upcoming Duties'] as $panelKey => $panelTitle): ?>
        <div class="duty-tab-panel" data-duty-panel="<?= htmlspecialchars($panelKey) ?>">
          <h3><?= htmlspecialchars($panelTitle) ?></h3>
          <div class="table-scroll">
            <table class="duty-history-table">
              <tr><th>Date</th><th>Employee</th><th>Department</th><th>Position</th><th>Shift</th><th>Time</th><th>Ward</th><th>Notes</th></tr>
              <?php if (!empty($dutyHistory[$panelKey])): ?>
                <?php foreach ($dutyHistory[$panelKey] as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars(fmt_date($row['duty_date'])) ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars((string)$row['department']) ?></td>
                    <td><?= htmlspecialchars((string)$row['position']) ?></td>
                    <td><?= htmlspecialchars($row['shift_name']) ?></td>
                    <td><?= $renderDutyTime($row) ?></td>
                    <td><?= htmlspecialchars((string)$row['ward']) ?></td>
                    <td><?= htmlspecialchars((string)$row['notes']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="8">No <?= htmlspecialchars(strtolower($panelTitle)) ?> found.</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="duty-tab-panel" data-duty-panel="shortage">
        <div class="training-head">
          <div>
            <h3>Staff Shortage Alerts</h3>
            <p class="muted">Set minimum nurse and technician coverage by department and shift. Alerts are calculated against this week roster.</p>
          </div>
        </div>
        <form method="post" action="?route=staffing-requirement.store" class="grid duty-shortage-form">
          <?= csrf_field() ?>
          <label>Department<input name="department" value="<?= htmlspecialchars(($activeFilters['department'] ?? '') !== '__all__' ? (string)($activeFilters['department'] ?? '') : '') ?>" required></label>
          <label>Shift
            <select name="shift_name" required>
              <?php foreach ($activeShiftOptions as $shiftOption): ?>
                <option><?= htmlspecialchars((string)$shiftOption) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Required Nurses<input type="number" min="0" name="required_nurses" value="0"></label>
          <label>Required Technicians<input type="number" min="0" name="required_technicians" value="0"></label>
          <button class="btn-compact">Save Requirement</button>
        </form>
        <div class="table-scroll duty-shortage-table">
          <table>
            <tr><th>Date</th><th>Department</th><th>Shift</th><th>Nurse Shortage</th><th>Technician Shortage</th></tr>
            <?php if (empty($staffShortageAlerts)): ?>
              <tr><td colspan="5">No shortage alerts for this week.</td></tr>
            <?php else: ?>
              <?php foreach ($staffShortageAlerts as $alert): ?>
                <tr>
                  <td><?= htmlspecialchars(fmt_date($alert['date'])) ?></td>
                  <td><?= htmlspecialchars((string)$alert['department']) ?></td>
                  <td><?= htmlspecialchars((string)$alert['shift_name']) ?></td>
                  <td><span class="status-badge <?= (int)$alert['nurse_shortage'] > 0 ? 'status-rejected' : 'status-approved' ?>"><?= (int)$alert['nurse_shortage'] ?></span></td>
                  <td><span class="status-badge <?= (int)$alert['technician_shortage'] > 0 ? 'status-rejected' : 'status-approved' ?>"><?= (int)$alert['technician_shortage'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<section class="card duty-shift-tracker no-print">
  <div class="training-head">
    <div>
      <h2>Easy Shift Tracker</h2>
      <p class="muted">Weekly coverage from the current roster grid.</p>
    </div>
    <strong class="duty-tracker-percent"><?= (int)($shiftTracker['coverage_percent'] ?? 0) ?>%</strong>
  </div>
  <div class="duty-tracker-grid">
    <div class="duty-tracker-card">
      <span>Filled Cells</span>
      <strong><?= (int)($shiftTracker['filled'] ?? 0) ?></strong>
      <small><?= (int)($shiftTracker['missing'] ?? 0) ?> missing of <?= (int)($shiftTracker['capacity'] ?? 0) ?></small>
    </div>
    <?php foreach (($shiftTracker['by_shift'] ?? []) as $shiftName => $shiftCount): ?>
      <div class="duty-tracker-card">
        <span><?= htmlspecialchars((string)$shiftName) ?></span>
        <strong><?= (int)$shiftCount ?></strong>
        <?php if (isset($shiftDefaults[$shiftName])): ?>
          <small><?= htmlspecialchars($shiftDefaults[$shiftName][0] . ' - ' . $shiftDefaults[$shiftName][1]) ?></small>
        <?php else: ?>
          <small>Weekly off</small>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="table-scroll duty-tracker-days">
    <table>
      <tr>
        <th>Date</th><th>On Duty</th>
        <?php foreach ($activeShiftOptions as $shiftOption): ?>
          <th><?= htmlspecialchars((string)$shiftOption) ?></th>
        <?php endforeach; ?>
        <th>OFF</th><th>Missing</th>
      </tr>
      <?php foreach (($shiftTracker['by_day'] ?? []) as $dayRow): ?>
        <tr>
          <td><?= htmlspecialchars(fmt_date((string)$dayRow['date'])) ?></td>
          <td><?= (int)$dayRow['total'] ?></td>
          <?php foreach ($activeShiftOptions as $shiftOption): ?>
            <td><?= (int)($dayRow['shifts'][$shiftOption] ?? 0) ?></td>
          <?php endforeach; ?>
          <td><?= (int)$dayRow['off'] ?></td>
          <td><span class="status-badge <?= (int)$dayRow['unassigned'] > 0 ? 'status-pending' : 'status-approved' ?>"><?= (int)$dayRow['unassigned'] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<section class="duty-week-panel">
  <form method="get" class="duty-filter-bar no-print">
    <input type="hidden" name="route" value="duty-roster">
    <input type="hidden" name="show_month" value="0">
    <label>Week
      <input type="date" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
    </label>
    <?php if (!empty($departments)): ?>
      <label>Department
        <select name="department">
          <option value="">Select department</option>
          <option value="__all__" <?= (($activeFilters['department'] ?? '') === '__all__') ? 'selected' : '' ?>>All departments</option>
          <?php foreach ($departments as $departmentRow): ?>
            <?php $departmentName = (string)$departmentRow['department']; ?>
            <option value="<?= htmlspecialchars($departmentName) ?>" <?= (($activeFilters['department'] ?? '') === $departmentName) ? 'selected' : '' ?>><?= htmlspecialchars($departmentName) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php else: ?>
      <label>Department
        <input value="<?= htmlspecialchars($scopeLabel ?? '') ?>" disabled>
      </label>
    <?php endif; ?>
    <label>Employee
      <input type="search" name="employee_q" value="<?= htmlspecialchars((string)($activeFilters['employee_q'] ?? '')) ?>" placeholder="Name, code, position">
    </label>
    <div class="duty-filter-actions">
      <button type="submit" class="btn-compact">Search</button>
      <a class="btn-compact btn-muted" href="?route=duty-roster">Clear</a>
      <button type="button" class="btn-compact" onclick="window.print()">Print</button>
      <a class="btn-compact" href="<?= htmlspecialchars($exportUrl('xlsx')) ?>">XLSX</a>
      <a class="btn-compact" href="<?= htmlspecialchars($exportUrl('pdf')) ?>">PDF</a>
    </div>
  </form>
  <div class="duty-week-head no-print">
    <div>
      <h2 class="print-title">Weekly Roster</h2>
      <div class="duty-period-badges">
        <span><?= htmlspecialchars($weeklyPeriod['month'] ?? date('F Y', strtotime($weekStart))) ?></span>
        <span><?= htmlspecialchars($weeklyPeriod['week_label'] ?? 'Week') ?>: <?= htmlspecialchars($weeklyPeriod['range'] ?? (fmt_date($weekStart) . ' to ' . fmt_date($weekEnd))) ?></span>
        <span>Scope: <?= htmlspecialchars($scopeLabel ?? 'All departments') ?></span>
      </div>
      <p>Select a shift once for the employee week, or assign by department. Choose OFF only on week-off dates. <?= (int)($weeklySummary['off_days'] ?? 0) ?> off days · <?= (int)($weeklySummary['explicit_records'] ?? 0) ?> saved records</p>
    </div>
  </div>

  <form method="post" action="?route=duty-roster.department-assign" class="duty-department-assign no-print">
    <?= csrf_field() ?>
    <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
    <input type="hidden" name="show_month" value="<?= !empty($showMonth) ? '1' : '0' ?>">
    <label>Department
      <select name="department" required>
        <option value="">Select department</option>
        <?php if (!empty($departments)): ?>
          <?php foreach ($departments as $departmentRow): ?>
            <?php $departmentName = (string)$departmentRow['department']; ?>
            <option value="<?= htmlspecialchars($departmentName) ?>" <?= (($activeFilters['department'] ?? '') === $departmentName) ? 'selected' : '' ?>><?= htmlspecialchars($departmentName) ?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <option value="<?= htmlspecialchars($scopeLabel ?? '') ?>" selected><?= htmlspecialchars($scopeLabel ?? '') ?></option>
        <?php endif; ?>
      </select>
    </label>
    <label>Weekly Shift
      <select name="shift_name" required>
        <option value="">Select shift</option>
        <?php foreach ($activeShiftOptions as $shiftOption): ?>
          <option><?= htmlspecialchars((string)$shiftOption) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn-compact duty-assign-btn">Assign</button>
  </form>

  <table class="roster-grid roster-grid-compact">
    <tr>
      <th>Employee</th>
      <?php foreach ($days as $d): ?>
        <th>
          <span class="roster-day"><?= htmlspecialchars(date('D', strtotime($d))) ?></span>
          <small><?= htmlspecialchars(date('d/m', strtotime($d))) ?></small>
        </th>
      <?php endforeach; ?>
    </tr>
    <?php if (empty($grid)): ?>
      <tr><td colspan="<?= count($days) + 1 ?>"><?= !empty($departmentRequired) ? 'Select a department to view roster, or choose All departments.' : 'No employees found for this department/search.' ?></td></tr>
    <?php endif; ?>
    <?php foreach ($grid as $row): ?>
      <tr id="employee-<?= (int)$row['employee']['id'] ?>" class="roster-employee-row">
        <td>
          <strong><?= htmlspecialchars($row['employee']['first_name'].' '.$row['employee']['last_name']) ?></strong>
          <span><?= htmlspecialchars((string)($row['employee']['employee_code'] ?? '')) ?></span>
          <small><?= $renderEmployeeMeta($row['employee']) ?></small>
        </td>
        <?php foreach ($days as $d): ?>
          <?php
            $slotsForDay = $row['days'][$d] ?? [];
            $isOffOverride = $isOffDuty($slotsForDay);
            $dutySlot = $firstDutySlot($slotsForDay);
            $offSlot = null;
            foreach ($slotsForDay as $slot) {
              if (strtoupper(trim((string)$slot['shift_name'])) === 'OFF') {
                $offSlot = $slot;
                break;
              }
            }
            $currentSlot = $isOffOverride ? $offSlot : $dutySlot;
            $currentShift = $isOffOverride ? 'OFF' : ($dutySlot ? (string)$dutySlot['shift_name'] : '');
            $currentStart = $currentSlot ? substr((string)$currentSlot['start_time'], 0, 5) : '';
            $currentEnd = $currentSlot ? substr((string)$currentSlot['end_time'], 0, 5) : '';
            $shiftClass = 'is-empty';
            if ($isOffOverride) {
              $shiftClass = 'is-off';
            } elseif ($currentShift !== '') {
              $shiftClass = 'is-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $currentShift));
            }
            $shiftOptions = array_merge($activeShiftOptions, ['OFF']);
          ?>
          <td class="dropzone" data-employee-id="<?= (int)$row['employee']['id'] ?>" data-duty-date="<?= htmlspecialchars($d) ?>">
            <div class="print-only roster-print-status">
              <strong><?= $isOffOverride ? 'OFF' : 'DUTY' ?></strong>
            </div>
            <form method="post" action="?route=duty-roster.assign" class="roster-cell-form no-print <?= $shiftClass ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="employee_id" value="<?= (int)$row['employee']['id'] ?>">
              <input type="hidden" name="return_employee_id" value="<?= (int)$row['employee']['id'] ?>">
              <input type="hidden" name="return_department" value="<?= htmlspecialchars((string)($activeFilters['department'] ?? '')) ?>">
              <input type="hidden" name="duty_date" value="<?= htmlspecialchars($d) ?>">
              <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
              <input type="hidden" name="show_month" value="<?= !empty($showMonth) ? '1' : '0' ?>">
              <input type="hidden" name="start_time" value="<?= htmlspecialchars($currentStart) ?>">
              <input type="hidden" name="end_time" value="<?= htmlspecialchars($currentEnd) ?>">
              <input type="hidden" name="ward" value="<?= htmlspecialchars((string)($currentSlot['ward'] ?? '')) ?>">
              <input type="hidden" name="scroll_top" value="">
              <select name="shift_name" required data-current-shift="<?= htmlspecialchars($currentShift) ?>" title="Regular shifts apply to the whole week. OFF applies only to this date." aria-label="Shift for <?= htmlspecialchars($row['employee']['first_name'].' '.$row['employee']['last_name']) ?> on <?= htmlspecialchars(date('d/m', strtotime($d))) ?>">
                <option value="">Select</option>
                <?php foreach ($shiftOptions as $shiftOption): ?>
                  <option value="<?= htmlspecialchars($shiftOption) ?>" <?= $currentShift === $shiftOption ? 'selected' : '' ?>><?= htmlspecialchars($shiftOption) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($currentShift !== '' && $currentShift !== 'OFF'): ?>
                <small><?= htmlspecialchars($currentStart . ' - ' . $currentEnd) ?></small>
              <?php endif; ?>
            </form>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="duty-tools no-print">
  <details>
    <summary>Add Shift</summary>
    <form method="post" action="?route=duty-roster.store" class="duty-tool-form">
      <?= csrf_field() ?>
      <select name="employee_id" required>
        <option value="">Select Employee</option>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name'].' | '.$emp['department'].' | '.$emp['position']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="duty_date" value="<?= htmlspecialchars($weekStart) ?>" required>
      <select name="shift_name" required>
        <option value="">Shift</option>
        <?php foreach ($activeShiftOptions as $shiftOption): ?>
          <option><?= htmlspecialchars((string)$shiftOption) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="time" name="start_time" required>
      <input type="time" name="end_time" required>
      <input name="ward" placeholder="Ward/Unit">
      <input name="notes" placeholder="Notes">
      <button>Add</button>
    </form>
  </details>

  <details>
    <summary>Bulk Tools</summary>
    <form method="post" action="?route=duty-roster.auto" class="duty-tool-form">
      <?= csrf_field() ?>
      <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
      <input type="hidden" name="show_month" value="<?= !empty($showMonth) ? '1' : '0' ?>">
      <?php if (!empty($departments)): ?>
        <select name="department">
          <option value="__all__">All departments</option>
          <?php foreach ($departments as $departmentRow): ?>
            <?php $departmentName = (string)$departmentRow['department']; ?>
            <option value="<?= htmlspecialchars($departmentName) ?>" <?= (($activeFilters['department'] ?? '') === $departmentName) ? 'selected' : '' ?>><?= htmlspecialchars($departmentName) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input name="department" value="<?= htmlspecialchars($scopeLabel ?? '') ?>" readonly>
      <?php endif; ?>
      <select name="generation_mode">
        <option value="fill_missing">Fill missing only</option>
        <option value="replace_auto">Replace auto-generated rows</option>
      </select>
      <small>Creates balanced active shifts and one weekly OFF per employee.</small>
      <button type="submit">Automate Week</button>
    </form>
    <form method="post" action="?route=duty-roster.bulk-apply" class="duty-tool-form duty-tool-form-large">
      <?= csrf_field() ?>
      <select name="employee_ids[]" multiple size="6" required>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name'].' | '.$emp['department'].' | '.$emp['position']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="duty_date" value="<?= htmlspecialchars($weekStart) ?>" required>
      <select name="shift_name" required>
        <option value="">Shift</option>
        <?php foreach ($activeShiftOptions as $shiftOption): ?>
          <option><?= htmlspecialchars((string)$shiftOption) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="time" name="start_time" required>
      <input type="time" name="end_time" required>
      <input name="ward" placeholder="Ward/Unit">
      <input name="notes" placeholder="Notes">
      <button>Bulk Apply</button>
    </form>
  </details>

  <details>
    <summary>Upload File</summary>
    <form method="post" action="?route=duty-roster.bulk-upload" enctype="multipart/form-data" class="duty-tool-form">
      <?= csrf_field() ?>
      <input type="file" name="roster_sheet" accept=".csv,.xlsx" required>
      <small>Headers: employee_id,duty_date,shift_name,start_time,end_time,ward,notes</small>
      <button>Upload</button>
    </form>
  </details>
</section>



<?php require __DIR__ . '/../layouts/footer.php'; ?>
<script>
(() => {
  const modal = document.querySelector('[data-duty-modal]');
  if (!modal) return;
  const openBtns = document.querySelectorAll('[data-open-duty-modal]');
  const closeBtns = modal.querySelectorAll('[data-close-duty-modal]');
  const tabs = modal.querySelectorAll('[data-duty-tab]');
  const panels = modal.querySelectorAll('[data-duty-panel]');

  const openModal = (tabName = 'available') => {
    activateTab(tabName);
    modal.hidden = false;
    document.body.classList.add('modal-open');
  };
  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
  };
  const activateTab = (name) => {
    tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.dutyTab === name));
    panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.dutyPanel === name));
  };

  openBtns.forEach((btn) => {
    btn.addEventListener('click', () => openModal(btn.dataset.dutyModalTab || 'available'));
  });
  closeBtns.forEach((btn) => btn.addEventListener('click', closeModal));
  tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.dutyTab)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });
})();

document.querySelectorAll('.roster-cell-form select[name="shift_name"]').forEach((select) => {
  select.addEventListener('change', () => {
    if (select.value) {
      if (select.value !== select.dataset.currentShift) {
        select.form.querySelector('input[name="start_time"]').value = '';
        select.form.querySelector('input[name="end_time"]').value = '';
      }
      const content = document.querySelector('.content');
      const scrollInput = select.form.querySelector('input[name="scroll_top"]');
      if (scrollInput) {
        scrollInput.value = String(content ? content.scrollTop : window.scrollY);
      }
      select.form.submit();
    }
  });
});

(() => {
  const params = new URLSearchParams(window.location.search);
  const scrollTop = parseInt(params.get('scroll_top') || '', 10);
  const target = window.location.hash ? document.querySelector(window.location.hash) : null;
  if (!Number.isNaN(scrollTop)) {
    const content = document.querySelector('.content');
    if (content) {
      content.scrollTop = scrollTop;
    } else {
      window.scrollTo(0, scrollTop);
    }
  }
  if (target) {
    target.classList.add('roster-row-focus');
    target.scrollIntoView({block: 'center', inline: 'nearest'});
    window.setTimeout(() => target.classList.remove('roster-row-focus'), 1800);
  }
})();
</script>
<?php if (!empty($canManageDuty)): ?>
<script>
let dragged = null;
document.querySelectorAll('.draggable-shift').forEach((el) => {
  el.addEventListener('dragstart', () => {
    dragged = el;
    el.classList.add('dragging');
  });
  el.addEventListener('dragend', () => {
    el.classList.remove('dragging');
  });
});

document.querySelectorAll('.dropzone').forEach((zone) => {
  zone.addEventListener('dragover', (e) => {
    e.preventDefault();
    zone.classList.add('drag-over');
  });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', async (e) => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    if (!dragged) return;

    const form = new URLSearchParams();
    form.append('id', dragged.dataset.id);
    form.append('employee_id', zone.dataset.employeeId);
    form.append('duty_date', zone.dataset.dutyDate);
    form.append('shift_name', dragged.dataset.shiftName || '');
    form.append('start_time', dragged.dataset.startTime || '');
    form.append('end_time', dragged.dataset.endTime || '');
    form.append('ward', dragged.dataset.ward || '');
    form.append('notes', dragged.dataset.notes || '');
    form.append('_csrf', '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>');

    const res = await fetch('?route=duty-roster.update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: form.toString()
    });
    if (res.ok) {
      window.location.reload();
    } else {
      alert('Drag-drop update failed');
    }
  });
});
</script>
<?php endif; ?>
