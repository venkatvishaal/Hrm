<?php require __DIR__ . '/../layouts/header.php'; ?>

<nav class="employee-service-menu" aria-label="Employee self service">
  <a href="?route=dashboard" class="employee-service-item"><span>D</span><strong>Home</strong></a>
  <a href="?route=employee-leave-permission" class="employee-service-item"><span>L</span><strong>Leave</strong></a>
  <a href="?route=employee-attendance" class="employee-service-item"><span>A</span><strong>Attendance Report</strong></a>
  <a href="?route=employee-attendance-correction" class="employee-service-item"><span>T</span><strong>Time Correction</strong></a>
  <a href="?route=employee-certificates" class="employee-service-item"><span>C</span><strong>Certificates</strong></a>
  <a href="?route=employee-documents" class="employee-service-item"><span>F</span><strong>Documents</strong></a>
</nav>

<section class="certificate-options employee-portal-certificate-requests" id="internal-request">
  <form method="post" action="?route=employee.internal-request.store" class="certificate-option">
    <?= csrf_field() ?>
    <input type="hidden" name="request_type" value="Employee Certificate">
    <input type="hidden" name="to_role" value="HR">
    <input type="hidden" name="message" value="Please issue my employee certificate.">
    <input type="hidden" name="return_route" value="employee-portal">
    <span>EC</span>
    <div>
      <strong>Employee Certificate</strong>
      <small>Request to HR</small>
    </div>
    <button class="btn-compact">Request</button>
  </form>
  <form method="post" action="?route=employee.internal-request.store" class="certificate-option">
    <?= csrf_field() ?>
    <input type="hidden" name="request_type" value="Salary Certificate">
    <input type="hidden" name="to_role" value="HR">
    <input type="hidden" name="message" value="Please issue my salary certificate.">
    <input type="hidden" name="return_route" value="employee-portal">
    <span>SC</span>
    <div>
      <strong>Salary Certificate</strong>
      <small>Request to HR</small>
    </div>
    <button class="btn-compact">Request</button>
  </form>
</section>

<div class="stats leave-cards">
  <div class="card leave-card leave-available"><strong>Available</strong><span class="leave-num"><?= $leaveStats['available'] ?></span></div>
  <div class="card leave-card leave-availed"><strong>Availed</strong><span class="leave-num"><?= $leaveStats['availed'] ?></span></div>
  <div class="card leave-card leave-balance"><strong>Balance</strong><span class="leave-num"><?= $leaveStats['balance'] ?></span></div>
  <div class="card status-pending"><strong>Pending</strong><br><?= $leaveStats['pending'] ?></div>
  <div class="card status-approved"><strong>Approved</strong><br><?= $leaveStats['approved'] ?></div>
  <div class="card status-rejected"><strong>Rejected</strong><br><?= $leaveStats['rejected'] ?></div>
</div>

<form method="get" class="card grid">
  <input type="hidden" name="route" value="employee-portal">
  <label>Month <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"></label>
  <button>Load Month</button>
</form>

<div class="stats">
  <div class="card status-present"><strong>Present</strong></div>
  <div class="card status-absent"><strong>Absent</strong></div>
  <div class="card status-permission"><strong>Permission</strong></div>
  <div class="card status-onduty"><strong>On Duty</strong></div>
</div>

<form method="post" action="?route=employee.leave.store" class="card grid">
  <select name="leave_type" required>
    <option value="">Select Leave Type</option>
    <option>Sick Leave</option><option>Casual Leave</option><option>Annual Leave</option><option>Maternity Leave</option>
    <option>Paternity Leave</option><option>Emergency Leave</option><option>Unpaid Leave</option>
  </select>
  <input type="date" name="start_date" required>
  <input type="date" name="end_date" required>
  <textarea name="reason" placeholder="Reason"></textarea>
  <button>Apply Leave</button>
</form>

<h2>Monthly Calendar</h2>
<?php
$firstDow = (int)date('N', strtotime($monthStart));
$daysInMonth = (int)date('t', strtotime($monthStart));
$day = 1;
?>
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
          elseif ($state === 'permission') { $class = 'cal-permission'; }
          elseif ($state === 'on_duty') { $class = 'cal-onduty'; }
        ?>
        <td class="<?= $class ?>"><strong><?= $day ?></strong><br><small><?= strtoupper(str_replace('_', ' ', $state)) ?></small></td>
        <?php $day++; ?>
      <?php endif; ?>
    <?php endfor; ?>
  </tr>
  <?php if ($day > $daysInMonth) { break; } ?>
  <?php endfor; ?>
</table>

<h2>Applied Leave Status</h2>
<table>
<tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Reason</th></tr>
<?php foreach ($myLeaves as $lv): ?>
<tr>
  <td><?= $lv['id'] ?></td><td><?= htmlspecialchars($lv['leave_type']) ?></td><td><?= htmlspecialchars(fmt_date($lv['start_date'])) ?></td><td><?= htmlspecialchars(fmt_date($lv['end_date'])) ?></td><td><span class="status-badge status-<?= strtolower($lv['status']) ?>"><?= htmlspecialchars($lv['status']) ?></span></td><td><?= htmlspecialchars((string)$lv['reason']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
