<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
  $firstName = (string)($employee['first_name'] ?? 'Venkatsd');
  $displayFirstName = trim($firstName);
  if (strcasecmp($displayFirstName, 'venkatsd') === 0) {
      $displayFirstName = 'Venkat';
  } elseif ($displayFirstName !== '') {
      $displayFirstName = ucwords(strtolower($displayFirstName));
  }
  $previewMonthStart = $attendancePreview['monthStart'] ?? date('Y-m-01');
  $previewCalendar = $attendancePreview['calendar'] ?? [];

  $employeeNotices = [];
  try {
      $dbConn = $GLOBALS['db'] ?? null;
      if ($dbConn && !empty($employee['id'])) {
          $employeeNotices = $dbConn->fetchAll(
              'SELECT title, created_at, event_type FROM notifications WHERE employee_id=:emp_id ORDER BY id DESC LIMIT 3',
              ['emp_id' => (int)$employee['id']]
          );
      }
  } catch (Throwable $e) {}

  if (empty($employeeNotices)) {
      $employeeNotices = [
          ['title' => 'Annual Health Check-up Schedule', 'created_at' => '2026-10-02 09:30:00', 'source' => 'HR & Compliance'],
          ['title' => 'New Hospital Parking Guidelines', 'created_at' => '2026-09-28 14:15:00', 'source' => 'Administration'],
          ['title' => 'Security Access Policy Update', 'created_at' => '2026-09-26 10:00:00', 'source' => 'Security'],
      ];
  }

  $upcomingTraining = !empty($myTraining[0]) ? $myTraining[0] : [
      'title' => 'Patient Safety & Quality Care',
      'session_date' => '2026-11-10',
      'session_time' => '2:00 PM',
      'location' => 'Seminar Room',
      'status' => 'Upcoming'
  ];

  $casualBalance = max(0, (int)($leaveStats['balance'] ?? 18));
  $sickBalance = 10;
  $earnedBalance = 6;
  $todayStatus = (string)($dashboardTodayStatus ?? 'Not Marked');
?>

<div class="content-body employee-ops-dashboard">
  <section class="ops-hero">
    <div>
      <p class="ops-date"><?= htmlspecialchars(date('l, F j')) ?></p>
      <h1>Good afternoon, <?= htmlspecialchars($displayFirstName) ?></h1>
      <p class="ops-subtitle"><?= htmlspecialchars((string)($employee['position'] ?? 'Staff')) ?> / <?= htmlspecialchars((string)($employee['department'] ?? 'Department')) ?></p>
      <p class="ops-intro">Here is what needs your attention today.</p>
    </div>
    <a class="ops-primary-action" href="?route=employee-attendance-correction">
      <span>3</span>
      <strong>items need your attention</strong>
      <small>Review pending items</small>
    </a>
  </section>

  <section class="ops-section">
    <div class="ops-section-head">
      <span>Today</span>
    </div>
    <div class="ops-today-grid">
      <a class="ops-status-card" href="?route=employee-attendance">
        <span class="ops-card-label">Attendance</span>
        <strong><?= htmlspecialchars($todayStatus) ?></strong>
        <small>Mark attendance</small>
      </a>
      <a class="ops-status-card" href="?route=employee-training-kpi">
        <span class="ops-card-label">Training</span>
        <strong>2 upcoming</strong>
        <small>1 mandatory this week</small>
      </a>
      <a class="ops-status-card" href="?route=notifications">
        <span class="ops-card-label">Notices</span>
        <strong><?= count($employeeNotices) ?> new</strong>
        <small>1 requires action</small>
      </a>
    </div>
  </section>

  <section class="ops-work-grid">
    <div class="ops-main-stack">
      <div class="ops-panel ops-attention-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Needs your attention</span>
            <h2>Security & compliance</h2>
          </div>
          <a href="?route=employee-attendance-correction">View all</a>
        </div>
        <a class="ops-attention-row" href="?route=notifications">
          <span class="ops-attention-marker warning"></span>
          <span>
            <strong>Security policy acknowledgement</strong>
            <small>Due today</small>
          </span>
          <em>Review</em>
        </a>
        <a class="ops-attention-row" href="?route=employee-attendance-correction">
          <span class="ops-attention-marker"></span>
          <span>
            <strong>Attendance correction</strong>
            <small>Sep 30 / Review requested</small>
          </span>
          <em>Review</em>
        </a>
        <a class="ops-attention-row" href="?route=employee-documents">
          <span class="ops-attention-marker muted"></span>
          <span>
            <strong>Documents & certificates</strong>
            <small>Records need updating</small>
          </span>
          <em>Open</em>
        </a>
      </div>

      <div class="ops-panel ops-notices-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Recent notices</span>
            <h2>Announcements</h2>
          </div>
          <a href="?route=notifications">View all</a>
        </div>
        <div class="ops-notice-list">
          <?php foreach ($employeeNotices as $index => $n): ?>
            <a href="?route=notifications" class="ops-notice-row">
              <span class="<?= $index === 0 ? 'is-unread' : '' ?>"></span>
              <strong><?= htmlspecialchars((string)$n['title']) ?></strong>
              <small><?= htmlspecialchars((string)($n['source'] ?? 'Notice')) ?> / <?= htmlspecialchars(fmt_date($n['created_at'])) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <aside class="ops-side-stack">
      <div class="ops-panel ops-training-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Upcoming training</span>
            <h2><?= htmlspecialchars((string)($upcomingTraining['title'] ?? 'Patient Safety & Quality Care')) ?></h2>
          </div>
        </div>
        <div class="ops-training-body">
          <img src="assets/training-seminar.jpg" alt="Training session">
          <div>
            <strong><?= htmlspecialchars(fmt_date($upcomingTraining['session_date'] ?? '2026-11-10')) ?></strong>
            <span><?= htmlspecialchars($upcomingTraining['session_time'] ?? '2:00 PM') ?> / <?= htmlspecialchars($upcomingTraining['location'] ?? 'Seminar Room') ?></span>
            <small>Mandatory / 90 min</small>
          </div>
        </div>
        <a class="ops-link" href="?route=employee-training-kpi">View training</a>
      </div>

      <div class="ops-panel ops-rail-notices-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Recent</span>
            <h2>Latest notices</h2>
          </div>
        </div>
        <div class="ops-rail-list">
          <?php foreach (array_slice($employeeNotices, 0, 3) as $n): ?>
            <a href="?route=notifications">
              <strong><?= htmlspecialchars((string)$n['title']) ?></strong>
              <span><?= htmlspecialchars(fmt_date($n['created_at'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ops-panel ops-personal-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Personal</span>
            <h2>Quick information</h2>
          </div>
        </div>
        <div class="ops-balance-list">
          <span>Casual <strong><?= $casualBalance ?></strong></span>
          <span>Sick <strong><?= $sickBalance ?></strong></span>
          <span>Earned <strong><?= $earnedBalance ?></strong></span>
        </div>
        <a class="ops-link" href="?route=employee-leave-permission">View leave details</a>
        <a class="ops-link" href="?route=employee-documents">Documents and certificates</a>
      </div>

      <div class="ops-panel ops-duty-panel">
        <div class="ops-panel-head">
          <div>
            <span class="ops-kicker">Duty</span>
            <h2>Today's post</h2>
          </div>
          <span class="ops-status-pill">Assigned</span>
        </div>
        <p><strong>General Security</strong><br>08:00 - 16:00</p>
        <p>Main Hospital Entrance Gate A</p>
        <a class="ops-link" href="?route=employee-duty-roster">View weekly roster</a>
      </div>
    </aside>
  </section>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
