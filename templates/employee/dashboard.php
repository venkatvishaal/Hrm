<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
  // ── Clinical Operational Identity & Greeting ────────────────────────────
  $firstName = trim((string)($employee['first_name'] ?? ''));
  $lastName = trim((string)($employee['last_name'] ?? ''));
  $fullName = trim($firstName . ' ' . $lastName);
  if ($fullName === '') {
      $fullName = (string)($user['name'] ?? 'Staff Member');
  }
  $displayFirstName = $firstName !== '' ? ucwords(strtolower($firstName)) : htmlspecialchars($fullName);

  $hour = (int)date('H');
  $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

  $position = (string)($employee['position'] ?? 'Clinical Staff');
  $department = (string)($employee['department'] ?? 'General Ward');
  $location = (string)($employee['location'] ?? 'KH');
  $empCode = (string)($employee['employee_code'] ?? '');

  // ── Real Duty Roster Resolution ─────────────────────────────────────────
  $todayDate = date('Y-m-d');
  $todayDuty = null;
  if (!empty($dashboardRosterItems) && is_array($dashboardRosterItems)) {
      foreach ($dashboardRosterItems as $roster) {
          if (($roster['duty_date'] ?? '') === $todayDate) {
              $todayDuty = $roster;
              break;
          }
      }
  }

  // Check if staff has an approved leave request for today
  $todayOnLeave = false;
  $todayLeaveType = '';
  if (!empty($myLeaves) && is_array($myLeaves)) {
      foreach ($myLeaves as $l) {
          if (strcasecmp((string)($l['status'] ?? ''), 'approved') === 0 &&
              ($l['start_date'] ?? '') <= $todayDate &&
              ($l['end_date'] ?? '') >= $todayDate) {
              $todayOnLeave = true;
              $todayLeaveType = (string)($l['leave_type'] ?? 'Leave');
              break;
          }
      }
  }

  // ── Real Notifications & Notices Query ──────────────────────────────────
  $employeeNotices = [];
  try {
      $dbConn = $GLOBALS['db'] ?? null;
      if ($dbConn && !empty($employee['id'])) {
          $employeeNotices = $dbConn->fetchAll(
              'SELECT title, message, created_at, event_type, is_read
               FROM notifications
               WHERE employee_id=:emp_id
               ORDER BY id DESC LIMIT 5',
              ['emp_id' => (int)$employee['id']]
          );
      }
  } catch (Throwable $e) {
      $employeeNotices = [];
  }

  // ── Real Dynamic Action Queue Items ─────────────────────────────────────
  $actionQueue = [];

  // 1. Pending leave requests awaiting approval
  $pendingLeaves = (int)($leaveStats['pending'] ?? 0);
  if ($pendingLeaves > 0) {
      $actionQueue[] = [
          'title' => $pendingLeaves . ' Leave Request' . ($pendingLeaves > 1 ? 's' : '') . ' Awaiting Approval',
          'meta' => 'Submitted / Under supervisory review',
          'badge' => 'Pending',
          'badge_type' => 'action',
          'link' => '?route=employee-leave-permission',
          'btn' => 'Track',
      ];
  }

  // 2. Training sessions assigned and incomplete
  $assignedTrainingCount = 0;
  $nextTrainingSession = null;
  if (!empty($myTraining) && is_array($myTraining)) {
      foreach ($myTraining as $t) {
          $tStatus = (string)($t['status'] ?? '');
          if ($tStatus === 'Assigned' || $tStatus === 'In Progress') {
              $assignedTrainingCount++;
              if ($nextTrainingSession === null) {
                  $nextTrainingSession = $t;
              }
          }
      }
      if ($nextTrainingSession === null && !empty($myTraining[0])) {
          $nextTrainingSession = $myTraining[0];
      }
  }
  if ($assignedTrainingCount > 0) {
      $actionQueue[] = [
          'title' => $assignedTrainingCount . ' Mandatory Training Session' . ($assignedTrainingCount > 1 ? 's' : ''),
          'meta' => $nextTrainingSession ? htmlspecialchars((string)($nextTrainingSession['title'] ?? 'Training Session')) : 'Clinical compliance requirement',
          'badge' => 'Required',
          'badge_type' => 'urgent',
          'link' => '?route=employee-training-kpi',
          'btn' => 'Open',
      ];
  }

  // 3. Onboarding items incomplete
  if (!empty($onboarding) && ($onboarding['status'] ?? '') !== 'Completed') {
      $actionQueue[] = [
          'title' => 'Staff Onboarding & Credentials Checklist',
          'meta' => 'Complete profile details, license documents, and hospital assets signoff',
          'badge' => 'Action Needed',
          'badge_type' => 'action',
          'link' => '?route=employee-onboarding',
          'btn' => 'Complete',
      ];
  }

  // 4. Unapproved absence days warning
  $absentDays = (int)($leaveStats['absent'] ?? 0);
  if ($absentDays > 0) {
      $actionQueue[] = [
          'title' => $absentDays . ' Uncovered Absence Record' . ($absentDays > 1 ? 's' : ''),
          'meta' => 'Submit leave application or attendance correction before payroll cutoff',
          'badge' => 'Correction Needed',
          'badge_type' => 'urgent',
          'link' => '?route=employee-attendance-correction',
          'btn' => 'Resolve',
      ];
  }

  // ── Metrics ─────────────────────────────────────────────────────────────
  $totalAvailableLeaves = (int)($leaveStats['available'] ?? 24);
  $availedLeaves = (int)($leaveStats['availed'] ?? 0);
  $remainingBalance = (int)($leaveStats['balance'] ?? max(0, $totalAvailableLeaves - $availedLeaves));
  $todayAttendanceStatus = (string)($dashboardTodayStatus ?? 'Not Marked');

  // ── Robust Profile Photo Path Resolution ─────────────────────────────────
  $empPhotoSrc = '';
  if (!empty($employee['photo_path'])) {
      $cleanPhoto = ltrim(str_replace('\\', '/', (string)$employee['photo_path']), '/');
      if (str_starts_with($cleanPhoto, 'public/')) {
          $cleanPhoto = substr($cleanPhoto, 7);
      }
      $isPublicRoot = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/');
      $empPhotoSrc = $isPublicRoot ? $cleanPhoto : 'public/' . $cleanPhoto;
  }
?>

<div class="content-body clinical-ops-center">

  <!-- ── Clinical Hero Identity & Station Banner ──────────────────────────── -->
  <header class="clinical-hero-banner">
    <div class="clinical-hero-identity">
      <div class="clinical-avatar" style="width:54px;height:54px;font-size:18px;font-weight:800;border:2.5px solid #ffffff;box-shadow:0 3px 10px rgba(15,23,42,0.15)">
        <?php if ($empPhotoSrc !== ''): ?>
          <img src="<?= htmlspecialchars($empPhotoSrc) ?>" alt="<?= htmlspecialchars($fullName) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
        <?php else: ?>
          <span><?= htmlspecialchars(strtoupper(substr($firstName ?: 'S', 0, 1) . substr($lastName ?: 'M', 0, 1))) ?></span>
        <?php endif; ?>
        <span class="clinical-avatar-badge <?= $todayOnLeave ? 'leave' : (($todayAttendanceStatus === 'Present' || $todayAttendanceStatus === 'On Duty') ? 'online' : 'offline') ?>" style="width:13px;height:13px;border-width:2.5px" title="<?= $todayOnLeave ? 'On Leave' : (($todayAttendanceStatus === 'Present' || $todayAttendanceStatus === 'On Duty') ? 'On Duty' : 'Off Duty / Standby') ?>"></span>
      </div>
      <div class="clinical-hero-text">
        <h1><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($displayFirstName) ?></h1>
        <div class="clinical-hero-meta">
          <span class="clinical-meta-pill facility" title="Hospital Location"><?= htmlspecialchars($location) ?></span>
          <span class="clinical-meta-pill code" title="Employee Code"><?= htmlspecialchars($empCode ?: 'EMP') ?></span>
          <span><?= htmlspecialchars($position) ?></span>
          <span>•</span>
          <span><?= htmlspecialchars($department) ?></span>
        </div>
      </div>
    </div>

    <!-- Today's Station / Shift Quick Status with Tabler-inspired Status Ribbon -->
    <div class="clinical-shift-callout" style="position:relative">
      <?php if ($todayOnLeave): ?>
        <span class="clinical-ribbon clinical-ribbon-escalated">On Leave</span>
      <?php elseif ($todayDuty): ?>
        <span class="clinical-ribbon clinical-ribbon-verified">On Duty</span>
      <?php else: ?>
        <span class="clinical-ribbon clinical-ribbon-info">Standby</span>
      <?php endif; ?>
      <div class="clinical-shift-info" style="padding-top:4px">
        <?php if ($todayOnLeave): ?>
          <strong>On Approved <?= htmlspecialchars($todayLeaveType) ?></strong>
          <span>Exempt from active roster duty today</span>
        <?php elseif ($todayDuty): ?>
          <strong><?= htmlspecialchars((string)($todayDuty['shift_name'] ?? 'General Shift')) ?></strong>
          <span><?= htmlspecialchars((string)($todayDuty['start_time'] ?? '08:00')) ?> – <?= htmlspecialchars((string)($todayDuty['end_time'] ?? '16:00')) ?> &bull; <?= htmlspecialchars((string)($todayDuty['notes'] ?: $department . ' Ward')) ?></span>
        <?php else: ?>
          <strong>Standard Operational Schedule</strong>
          <span><?= htmlspecialchars($department) ?> &bull; Contact HOD for roster post</span>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($todayOnLeave): ?>
          <span class="clinical-status-tag leave">On Leave</span>
        <?php elseif ($todayDuty): ?>
          <span class="clinical-status-tag active">Assigned</span>
        <?php else: ?>
          <span class="clinical-status-tag off">Standby</span>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- ── Operational High-Density Command Grid ────────────────────────────── -->
  <div class="clinical-grid">

    <!-- ── Left Column: Operations & Active Queues ──────────────────────── -->
    <main class="clinical-main-col">

      <!-- Operational Status & Attendance Strip -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
              <h2 class="clinical-panel-title">Today's Presence & Time Record</h2>
              <div class="clinical-panel-sub"><?= date('l, F j, Y') ?> &bull; Biometric Time Attendance</div>
            </div>
          </div>
          <a class="clinical-panel-link" href="?route=employee-attendance">
            <span>View Timesheet</span>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
          </a>
        </div>

        <div class="clinical-stat-row">
          <a class="clinical-kpi-card accent-teal" href="?route=employee-attendance" style="text-decoration:none">
            <span class="clinical-kpi-label">Shift Punch Status</span>
            <div class="clinical-kpi-value" style="font-size:18px;color:<?= ($todayAttendanceStatus === 'Present' || $todayAttendanceStatus === 'On Duty') ? '#0f766e' : '#b45309' ?>">
              <?= htmlspecialchars($todayAttendanceStatus) ?>
            </div>
            <span class="clinical-kpi-sub">Today's calculated status</span>
          </a>

          <a class="clinical-kpi-card accent-green" href="?route=employee-attendance" style="text-decoration:none">
            <span class="clinical-kpi-label">Days Present</span>
            <div class="clinical-kpi-value" style="color:#0f766e">
              <?= count(array_filter($attendancePreview['calendar'] ?? [], static fn($d) => in_array(strtolower((string)($d['status'] ?? '')), ['present', 'half_day'], true))) ?>
            </div>
            <span class="clinical-kpi-sub">Current month punches</span>
          </a>

          <a class="clinical-kpi-card accent-blue" href="?route=employee-leave-permission" style="text-decoration:none">
            <span class="clinical-kpi-label">Leave Balance</span>
            <div class="clinical-kpi-value" style="color:#0d474c">
              <?= $remainingBalance ?> <span style="font-size:13px;font-weight:500;color:#64748b">/ <?= $totalAvailableLeaves ?>d</span>
            </div>
            <span class="clinical-kpi-sub"><?= $availedLeaves ?> days availed</span>
          </a>

          <a class="clinical-kpi-card <?= $absentDays > 0 ? 'accent-red' : 'accent-amber' ?>" href="?route=employee-attendance-correction" style="text-decoration:none">
            <span class="clinical-kpi-label">Missing Punches</span>
            <div class="clinical-kpi-value" style="color:<?= $absentDays > 0 ? '#dc2626' : '#64748b' ?>">
              <?= $absentDays ?>
            </div>
            <span class="clinical-kpi-sub">Requires correction</span>
          </a>
        </div>
      </section>

      <!-- Compliance & Action Items Queue -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            <div>
              <h2 class="clinical-panel-title">Action Queue & Compliance Items</h2>
              <div class="clinical-panel-sub">Hospital compliance requirements needing your action</div>
            </div>
          </div>
          <span class="clinical-queue-badge" style="background:#f0fdfa;color:#0f766e;border:1px solid #ccfbf1">
            <?= count($actionQueue) ?> Active
          </span>
        </div>

        <?php if (!empty($actionQueue)): ?>
          <div class="clinical-queue-list">
            <?php foreach ($actionQueue as $item): ?>
              <a class="clinical-queue-item" href="<?= htmlspecialchars($item['link']) ?>">
                <div class="clinical-queue-left">
                  <span class="clinical-queue-dot <?= htmlspecialchars($item['badge_type']) ?>"></span>
                  <div>
                    <div class="clinical-queue-title"><?= htmlspecialchars($item['title']) ?></div>
                    <div class="clinical-queue-meta"><?= htmlspecialchars($item['meta']) ?></div>
                  </div>
                </div>
                <span class="clinical-quick-action-btn" style="width:auto;padding:6px 14px;font-size:12px">
                  <?= htmlspecialchars($item['btn']) ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="clinical-empty-box">
            All compliance items, document credentials, and attendance records are fully up to date.
          </div>
        <?php endif; ?>
      </section>

      <!-- Weekly Duty Roster Timeline -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
            <div>
              <h2 class="clinical-panel-title">Weekly Duty Schedule</h2>
              <div class="clinical-panel-sub"><?= htmlspecialchars((string)($dashboardRosterRange ?? 'Current Weekly Hospital Roster')) ?></div>
            </div>
          </div>
          <a class="clinical-panel-link" href="?route=employee-duty-roster">
            <span>Full Schedule</span>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
          </a>
        </div>

        <?php if (!empty($dashboardRosterItems)): ?>
          <div class="clinical-roster-strip">
            <?php foreach ($dashboardRosterItems as $r): ?>
              <?php
                $rDate = (string)($r['duty_date'] ?? '');
                $isToday = $rDate === $todayDate;
                $dayName = date('D', strtotime($rDate));
                $dayNum = date('j', strtotime($rDate));
                $shiftName = (string)($r['shift_name'] ?? 'OFF');
                $isOff = strcasecmp($shiftName, 'off') === 0;
              ?>
              <div class="clinical-roster-day <?= $isToday ? 'is-today' : '' ?>">
                <div class="clinical-day-name"><?= htmlspecialchars($dayName) ?></div>
                <div class="clinical-day-date"><?= htmlspecialchars($dayNum) ?></div>
                <span class="clinical-day-shift <?= $isOff ? 'off' : 'active' ?>" title="<?= htmlspecialchars($shiftName . ' (' . ($r['start_time'] ?? '') . '-' . ($r['end_time'] ?? '') . ')') ?>">
                  <?= htmlspecialchars($shiftName) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="clinical-empty-box">
            Weekly roster for this period has not been published yet. Check with your department HOD.
          </div>
        <?php endif; ?>
      </section>

      <!-- Institutional Notices & Announcements -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <div>
              <h2 class="clinical-panel-title">Institutional Notices & Policy Updates</h2>
              <div class="clinical-panel-sub">Official hospital communications and directives</div>
            </div>
          </div>
          <a class="clinical-panel-link" href="?route=notifications">View All</a>
        </div>

        <?php if (!empty($employeeNotices)): ?>
          <div class="clinical-queue-list">
            <?php foreach ($employeeNotices as $notice): ?>
              <a class="clinical-notice-row" href="?route=notifications">
                <span class="clinical-notice-bullet <?= empty($notice['is_read']) ? 'is-unread' : '' ?>"></span>
                <div style="flex:1;min-width:0">
                  <div class="clinical-notice-title"><?= htmlspecialchars((string)($notice['title'] ?? 'Notice')) ?></div>
                  <div class="clinical-notice-sub">
                    <?= htmlspecialchars(fmt_datetime($notice['created_at'])) ?>
                    <?php if (!empty($notice['event_type'])): ?> &bull; <?= htmlspecialchars((string)$notice['event_type']) ?><?php endif; ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="clinical-empty-box">
            No institutional notices published today.
          </div>
        <?php endif; ?>
      </section>

    </main>

    <!-- ── Right Column: Clinical Quick Operations ──────────────────────── -->
    <aside class="clinical-side-col">

      <!-- Fast Action Center -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <div>
              <h2 class="clinical-panel-title">Quick Actions</h2>
              <div class="clinical-panel-sub">Frequently used workflows</div>
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
          <a class="clinical-quick-action-btn primary" href="?route=employee-leave-permission">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Apply Leave / Permission
          </a>
          <a class="clinical-quick-action-btn" href="?route=employee-attendance-correction">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
            Submit Time Correction
          </a>
          <a class="clinical-quick-action-btn" href="?route=employee-certificates">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
            Request Official Certificate
          </a>
          <a class="clinical-quick-action-btn" href="?route=employee-documents">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload Compliance Document
          </a>
        </div>
      </section>

      <!-- Mandatory Training & Clinical Competency -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
            <div>
              <h2 class="clinical-panel-title">Training & Development</h2>
              <div class="clinical-panel-sub">Mandatory clinical competencies</div>
            </div>
          </div>
          <a class="clinical-panel-link" href="?route=employee-training-kpi">Details</a>
        </div>

        <?php if (!empty($nextTrainingSession)): ?>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px">
            <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px">
              <?= htmlspecialchars((string)($nextTrainingSession['title'] ?? 'Mandatory Safety Training')) ?>
            </div>
            <div style="font-size:12.5px;color:#475569;display:flex;flex-direction:column;gap:4px">
              <div><strong>Date:</strong> <?= htmlspecialchars(fmt_date($nextTrainingSession['session_date'] ?? date('Y-m-d'))) ?></div>
              <?php if (!empty($nextTrainingSession['location'])): ?>
                <div><strong>Venue:</strong> <?= htmlspecialchars((string)$nextTrainingSession['location']) ?></div>
              <?php endif; ?>
              <?php if (!empty($nextTrainingSession['trainer_name'])): ?>
                <div><strong>Trainer:</strong> <?= htmlspecialchars((string)$nextTrainingSession['trainer_name']) ?></div>
              <?php endif; ?>
            </div>
            <div style="margin-top:10px;display:flex;align-items:center;justify-content:space-between">
              <span class="clinical-status-tag active" style="font-size:11px">
                <?= htmlspecialchars((string)($nextTrainingSession['status'] ?? 'Assigned')) ?>
              </span>
              <a href="?route=employee-training-kpi" style="font-size:12px;color:#0f766e;font-weight:600;text-decoration:none">Update Status</a>
            </div>
          </div>
        <?php else: ?>
          <div class="clinical-empty-box">
            No pending training sessions assigned.
          </div>
        <?php endif; ?>
      </section>

      <!-- Staff Dossier & Health Records -->
      <section class="clinical-panel">
        <div class="clinical-panel-header">
          <div class="clinical-panel-title-wrap">
            <svg class="clinical-icon lucide-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27"/></svg>
            <div>
              <h2 class="clinical-panel-title">Dossier & Health Records</h2>
              <div class="clinical-panel-sub">Verified staff records</div>
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;font-size:13px">
          <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f1f5f9">
            <span style="color:#64748b">Verified Documents</span>
            <strong><?= count($documents ?? []) ?> files</strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f1f5f9">
            <span style="color:#64748b">Annual Health Check</span>
            <strong><?= !empty($healthReports) ? 'Completed' : 'Pending Review' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:8px;border-bottom:1px solid #f1f5f9">
            <span style="color:#64748b">Joining Date</span>
            <strong><?= !empty($employee['join_date']) ? htmlspecialchars(fmt_date($employee['join_date'])) : 'On Record' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="color:#64748b">Staff Dossier</span>
            <a href="?route=employee-profile" style="color:#0f766e;font-weight:600;text-decoration:none">View Profile</a>
          </div>
        </div>
      </section>

    </aside>

  </div>

</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
