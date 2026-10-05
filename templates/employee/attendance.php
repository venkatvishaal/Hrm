<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$hoursLabel = static function (float $hours): string {
    $minutes = max(0, (int)round($hours * 60));
    return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
};
?>
<?php
$periodLabel = $viewMode === 'weekly'
    ? fmt_date($weekStart) . ' to ' . fmt_date($weekEnd)
    : date('F Y', strtotime($monthStart));
$statementLabel = $viewMode === 'weekly' ? 'Weekly' : 'Monthly';
$statusLabel = static function (string $state): string {
    $labels = ['on_leave' => 'ON LEAVE', 'on_duty' => 'ON DUTY', 'permission' => 'PERMISSION', 'off_duty' => 'OFF'];
    if (isset($labels[$state])) {
        return $labels[$state];
    }
    if ($state === 'none') {
        return '';
    }
    if (str_starts_with($state, 'duty:')) {
        return strtoupper(substr($state, 5));
    }
    if ($state === 'off_duty') {
        return 'OFF';
    }
    return strtoupper(str_replace('_', ' + ', $state));
};
$shiftClass = static function (string $state): string {
    if (!str_starts_with($state, 'duty:')) {
        return '';
    }

    $shift = strtolower(preg_replace('/[^a-z0-9]+/i', '-', substr($state, 5)));
    return $shift !== '' ? ' cal-shift-' . trim($shift, '-') : '';
};
?>

<section class="employee-attendance-workspace">
  <div class="employee-attendance-column">
    <form method="get" class="card grid attendance-view-form">
      <input type="hidden" name="route" value="employee-attendance">
      <label class="attendance-view-mode-field">View Mode
        <select name="view_mode">
          <option value="weekly" <?= $viewMode === 'weekly' ? 'selected' : '' ?>>Weekly</option>
          <option value="monthly" <?= $viewMode === 'monthly' ? 'selected' : '' ?>>Monthly</option>
        </select>
      </label>
      <label>Month <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"></label>
      <label class="attendance-week-field">Week Date <input type="date" name="week_date" value="<?= htmlspecialchars($weekDate) ?>"></label>
      <button>Load</button>
      <a class="btn-link attendance-correction-action" href="?route=employee-attendance-correction">Correction</a>
    </form>

    <section class="card attendance-report-panel">
      <div class="training-head">
        <div>
          <h2>Time Statement</h2>
          <p class="muted"><?= htmlspecialchars($periodLabel) ?></p>
        </div>
      </div>
      <div class="attendance-summary-list">
        <div><span><?= htmlspecialchars($statementLabel) ?> Expected Hours</span><strong><?= htmlspecialchars($hoursLabel((float)$report['expected_hours'])) ?></strong></div>
        <div><span><?= htmlspecialchars($statementLabel) ?> Counted Hours</span><strong><?= htmlspecialchars($hoursLabel((float)$report['counted_hours'])) ?></strong></div>
        <div><span>Excess Hours</span><strong><?= htmlspecialchars($hoursLabel((float)$report['excess_hours'])) ?></strong></div>
        <div><span>Shortage Hours</span><strong><?= htmlspecialchars($hoursLabel((float)$report['shortage_hours'])) ?></strong></div>
        <div><span>Absent Days</span><strong><?= (int)$report['unauthorized_absence_days'] ?> days</strong></div>
        <div><span>Period End</span><strong><?= htmlspecialchars(fmt_date($report['last_compensation_day'])) ?></strong></div>
      </div>
    </section>

  </div>

  <div class="employee-attendance-column">
    <section class="card employee-attendance-calendar-card">
      <div class="employee-attendance-section-head">
        <h2><?= $viewMode === 'monthly' ? 'Attendance Calendar - ' . htmlspecialchars(date('F Y', strtotime($monthStart))) : 'Attendance Calendar' ?></h2>
      </div>
      <div class="attendance-legend attendance-legend-grid" aria-label="Attendance status legend">
        <div class="status-present"><strong>Present</strong></div>
        <div class="status-absent"><strong>Absent</strong></div>
        <div class="status-late"><strong>Late</strong></div>
        <div class="status-on_leave"><strong>On Leave</strong></div>
        <div class="status-permission"><strong>Permission</strong></div>
        <div class="status-onduty"><strong>On Duty</strong></div>
      </div>
      <?php
      $firstDow = (int)date('N', strtotime($monthStart));
      $daysInMonth = (int)date('t', strtotime($monthStart));
      $day = 1;
      ?>
      <div class="attendance-split">
        <section class="attendance-calendar-pane">
          <?php if ($viewMode === 'weekly'): ?>
            <table class="calendar-table calendar-compact attendance-week-calendar">
              <tr><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr>
              <tr>
                <?php for ($offset = 0; $offset < 7; $offset++): ?>
                  <?php
                    $dateKey = date('Y-m-d', strtotime('+' . $offset . ' day', strtotime($weekStart)));
                    $state = $calendar[$dateKey] ?? 'none';
                    $class = 'cal-none';
                    if ($state === 'present') { $class = 'cal-present'; }
                    elseif ($state === 'absent') { $class = 'cal-absent'; }
                    elseif ($state === 'late') { $class = 'cal-late'; }
                    elseif ($state === 'on_leave') { $class = 'cal-onleave'; }
                    elseif ($state === 'permission') { $class = 'cal-permission'; }
                    elseif ($state === 'present_permission') { $class = 'cal-present-permission'; }
                    elseif ($state === 'off_duty') { $class = 'cal-offduty'; }
                    elseif ($state === 'on_duty' || str_starts_with($state, 'duty:')) { $class = 'cal-onduty'; }
                  ?>
                  <td class="<?= htmlspecialchars($class . $shiftClass($state)) ?>"><strong><?= htmlspecialchars(date('d', strtotime($dateKey))) ?></strong></td>
                <?php endfor; ?>
              </tr>
            </table>
          <?php else: ?>
            <table class="calendar-table calendar-compact">
              <tr><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr>
              <?php for ($r = 0; $r < 6; $r++): ?>
              <tr>
                <?php for ($c = 1; $c <= 7; $c++): ?>
                  <?php if (($r === 0 && $c < $firstDow) || $day > $daysInMonth): ?>
                    <td class="cal-empty"></td>
                  <?php else: ?>
                    <?php
                      $dateKey = date('Y-m-d', strtotime(substr($monthStart, 0, 8) . str_pad((string)$day, 2, '0', STR_PAD_LEFT)));
                      $state = $calendar[$dateKey] ?? 'none';
                      $class = 'cal-none';
                      if ($state === 'present') { $class = 'cal-present'; }
                      elseif ($state === 'absent') { $class = 'cal-absent'; }
                      elseif ($state === 'late') { $class = 'cal-late'; }
                      elseif ($state === 'on_leave') { $class = 'cal-onleave'; }
                      elseif ($state === 'permission') { $class = 'cal-permission'; }
                      elseif ($state === 'present_permission') { $class = 'cal-present-permission'; }
                      elseif ($state === 'off_duty') { $class = 'cal-offduty'; }
                      elseif ($state === 'on_duty' || str_starts_with($state, 'duty:')) { $class = 'cal-onduty'; }
                    ?>
                    <td class="<?= htmlspecialchars($class . $shiftClass($state)) ?>"><strong><?= $day ?></strong></td>
                    <?php $day++; ?>
                  <?php endif; ?>
                <?php endfor; ?>
              </tr>
              <?php if ($day > $daysInMonth) { break; } ?>
              <?php endfor; ?>
            </table>
          <?php endif; ?>
        </section>
      </div>
    </section>

    <details class="card employee-attendance-details">
      <summary class="employee-attendance-section-head">
        <h2>Attendance Report</h2>
        <span><?= count($report['rows']) ?> row(s)</span>
      </summary>
      <div class="table-scroll">
        <table>
          <tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Hours</th><th>Source</th></tr>
          <?php if (empty($report['rows'])): ?>
            <tr><td colspan="6">No attendance rows imported for this month.</td></tr>
          <?php else: ?>
            <?php foreach ($report['rows'] as $row): ?>
              <?php
                $worked = '-';
                if (!empty($row['check_in']) && !empty($row['check_out'])) {
                    $inTs = strtotime((string)$row['check_in']);
                    $outTs = strtotime((string)$row['check_out']);
                    if ($inTs !== false && $outTs !== false && $outTs >= $inTs) {
                        $worked = $hoursLabel((float)(($outTs - $inTs) / 3600));
                    }
                }
              ?>
              <tr>
                <td><?= htmlspecialchars(fmt_date($row['attendance_date'])) ?></td>
                <td><span class="status-badge status-<?= htmlspecialchars(str_replace('_', '-', strtolower((string)$row['status']))) ?>"><?= htmlspecialchars((string)$row['live_status']) ?></span></td>
                <td><?= htmlspecialchars((string)($row['check_in'] ?? '-')) ?></td>
                <td><?= htmlspecialchars((string)($row['check_out'] ?? '-')) ?></td>
                <td><?= htmlspecialchars($worked) ?></td>
                <td><?= htmlspecialchars((string)($row['source_file'] ?? '-')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </table>
      </div>
    </details>
  </div>

  <section class="card employee-attendance-remarks">
    <h2>Remarks</h2>
    <div class="table-scroll">
      <table>
        <tr><th>Date</th><th>Remarks</th><th>Duration</th></tr>
        <?php if (empty($report['remarks'])): ?>
          <tr><td colspan="3">No remarks for this month.</td></tr>
        <?php else: ?>
          <?php foreach ($report['remarks'] as $remark): ?>
            <tr>
              <td><?= htmlspecialchars(fmt_date($remark['date'])) ?></td>
              <td><?= htmlspecialchars((string)$remark['remarks']) ?></td>
              <td><?= htmlspecialchars((string)$remark['duration']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>
  </section>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
