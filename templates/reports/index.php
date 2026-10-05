<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/reports_nav.php'; ?>
<?php
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$rangeQuery = '&from=' . urlencode($from) . '&to=' . urlencode($to);
$reportGroups = [
    [
        'title' => 'Workforce',
        'theme' => 'green',
        'items' => [
            ['Employees', 'employees', 'user'],
            ['Department Strength', 'department_strength', 'building'],
            ['Onboarding', 'onboarding', 'clipboard'],
            ['Exit Records', 'exit', 'door'],
        ],
    ],
    [
        'title' => 'Attendance',
        'theme' => 'orange',
        'items' => [
            ['Attendance Register', 'attendance', 'calendar'],
            ['Individual Employee Attendance', 'employee_attendance', 'user'],
            ['Duty Roster', 'duty_roster', 'clock'],
            ['Present Today', 'present_today', 'check'],
            ['Absent Today', 'absent_today', 'alert'],
        ],
    ],
    [
        'title' => 'Leave',
        'theme' => 'teal',
        'items' => [
            ['Leave Requests', 'leave', 'file'],
            ['Pending Leave', 'pending_leave', 'hourglass'],
            ['Leave Status', 'leave_status', 'chart'],
        ],
    ],
    [
        'title' => 'Performance',
        'theme' => 'purple',
        'items' => [
            ['KPI Reviews', 'performance', 'trend'],
            ['Leaderboard', 'performance_leaderboard', 'award'],
            ['Training Sessions', 'training', 'graduation'],
            ['Audit Trail', 'audit', 'shield'],
        ],
    ],
    [
        'title' => 'Payroll',
        'theme' => 'blue',
        'items' => [
            ['Payroll Records', 'payroll', 'wallet', true],
            ['Allowances & Deductions', 'allowances', 'percent', true],
            ['Payroll Status', 'payroll_status', 'receipt', true],
        ],
    ],
];
$icons = [
    'alert' => '<svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 4.3 2.7 17.5A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.5L13.7 4.3a2 2 0 0 0-3.4 0Z"/></svg>',
    'award' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="m8.5 12.5-2 8 5.5-3 5.5 3-2-8"/></svg>',
    'building' => '<svg viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16M9 8h1m4 0h1M9 12h1m4 0h1M9 16h1m4 0h1M3 21h18"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24"><path d="M8 2v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>',
    'chart' => '<svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16v-5m5 5V8m5 8v-3"/></svg>',
    'check' => '<svg viewBox="0 0 24 24"><path d="m20 6-11 11-5-5"/></svg>',
    'clipboard' => '<svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h3v15H5V6h3l1-2Z"/><path d="M9 12h6m-6 4h4"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'door' => '<svg viewBox="0 0 24 24"><path d="M5 21h14M8 21V4h8v17"/><path d="M13 12h.01"/></svg>',
    'file' => '<svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7V3Z"/><path d="M14 3v5h5M9 13h6m-6 4h6"/></svg>',
    'graduation' => '<svg viewBox="0 0 24 24"><path d="m3 8 9-4 9 4-9 4-9-4Z"/><path d="M7 10v5c3 2 7 2 10 0v-5"/></svg>',
    'hourglass' => '<svg viewBox="0 0 24 24"><path d="M6 3h12M6 21h12M8 3c0 5 8 5 8 9s-8 4-8 9M16 3c0 5-8 5-8 9s8 4 8 9"/></svg>',
    'percent' => '<svg viewBox="0 0 24 24"><path d="m19 5-14 14"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
    'receipt' => '<svg viewBox="0 0 24 24"><path d="M6 3h12v18l-2-1-2 1-2-1-2 1-2-1-2 1V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-5"/></svg>',
    'trend' => '<svg viewBox="0 0 24 24"><path d="M4 19h16M6 15l4-4 3 3 5-7"/><path d="M14 7h4v4"/></svg>',
    'user' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>',
    'wallet' => '<svg viewBox="0 0 24 24"><path d="M4 7h16v12H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14"/><path d="M16 13h4"/></svg>',
];
$hrefFor = static function (string $type) use ($rangeQuery): string {
    return '?route=report-detail&report=' . urlencode($type) . $rangeQuery;
};
?>
<section class="reports-page">
  <form method="get" class="reports-toolbar">
    <input type="hidden" name="route" value="reports">
    <label>FROM <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>TO <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button class="reports-apply">Apply</button>
  </form>

  <div class="reports-groups">
    <?php foreach ($reportGroups as $group): ?>
      <div class="report-panel report-<?= htmlspecialchars($group['theme']) ?>">
        <h2><?= htmlspecialchars($group['title']) ?></h2>
        <div class="report-list">
          <?php foreach ($group['items'] as $item): ?>
            <?php [$label, $type, $icon] = $item; $isInactive = !empty($item[3]); ?>
            <?php if ($isInactive): ?>
              <span class="report-link report-link-disabled" title="<?= htmlspecialchars($label) ?> inactive">
                <span class="report-icon" aria-hidden="true"><?= $icons[$icon] ?></span>
                <span><?= htmlspecialchars($label) ?><small>Inactive</small></span>
              </span>
            <?php else: ?>
              <a class="report-link" href="<?= htmlspecialchars($hrefFor($type)) ?>">
                <span class="report-icon" aria-hidden="true"><?= $icons[$icon] ?></span>
                <span><?= htmlspecialchars($label) ?></span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
