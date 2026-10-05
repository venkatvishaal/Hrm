<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hospital HRM - Krishna Healthcare & Research Foundation</title>
  <?php $assetBase = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/') ? 'assets' : 'public/assets'; ?>
  <?php $styleVersion = @filemtime(__DIR__ . '/../../public/assets/styles.css') ?: time(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/styles.css?v=<?= $styleVersion ?>">
  <?php $themeVersion = @filemtime(__DIR__ . '/../../public/assets/hospital-theme.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/hospital-theme.css?v=<?= $themeVersion ?>">
  <script>
    try {
      localStorage.removeItem('hospitalHrTheme');
      document.documentElement.classList.remove('theme-dark');
    } catch (e) {}
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<?php $user = $_SESSION['user'] ?? null; ?>
<?php $bodyClasses = $user ? ['role-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)($user['role'] ?? 'employee')))] : ['guest-page']; ?>
<body class="<?= htmlspecialchars(implode(' ', $bodyClasses)) ?>" data-route="<?= htmlspecialchars((string)($_GET['route'] ?? 'dashboard')) ?>">
<?php $isSuperAdmin = (($user['role'] ?? '') === 'SuperAdmin'); ?>
<?php $currentRoute = $_GET['route'] ?? 'dashboard'; ?>
<?php
$navClass = static function (array $routes) use ($currentRoute): string {
    return in_array($currentRoute, $routes, true) ? ' class="active"' : '';
};
$pageTitles = [
    'dashboard' => ['Dashboard', ''],
    'organisational-hierarchy' => ['Organisational Hierarchy', 'Employee reporting workflow'],
    'recruitment' => ['Employee Directory', 'Staff records and employee administration'],
    'recruitment.create' => ['Create Employee', 'Add a hospital staff profile'],
    'recruitment.edit' => ['Edit Employee', 'Update staff profile details'],
    'attendance' => ['Attendance', ''],
    'attendance-live-status' => ['Live Status', 'Attendance operations'],
    'attendance-monthly-summary' => ['Monthly Summary', 'Attendance summary and approvals'],
    'attendance-daily-report' => ['Daily Attendance Report', 'Daily attendance overview'],
    'attendance-upload' => ['Upload Attendance', 'Import attendance records'],
    'attendance-fetch-machine' => ['Attendance Fetch Module', 'Import biometric machine logs'],
    'attendance-manual-absent' => ['Manual Mark Absent', 'Mark employees absent'],
    'duty-roster' => ['Duty Roster', 'Ward and shift planning'],
    'leave' => ['Leave Requests', 'Leave balances, requests, and approvals'],
    'leave.edit' => ['Edit Leave', 'Update leave request details'],
    'documents' => ['Documents', 'Employee document verification'],
    'health-checkups' => ['Health Checkups', 'Annual health records and due dates'],
    'onboarding' => ['Onboarding', 'New employee joining workflow'],
    'training' => ['Training', 'Staff training sessions and completion'],
    'performance' => ['Performance', 'KPI reviews and staff performance'],
    'performance-leaderboard' => ['Performance Leaderboard', 'Top staff KPI scores'],
    'exit' => ['Exit Records', 'Employee separation records'],
    'notifications' => ['Notifications', 'HR alerts and staff messages'],
    'payroll' => ['Salary Processing', 'Monthly payroll and payment status'],
    'allowances' => ['Allowances & Deductions', 'Payroll adjustments and recurring entries'],
    'departments' => ['Departments', 'Hospital department master'],
    'designations' => ['Designations', 'Role and position master'],
    'users' => ['Users & Roles', 'Admin-only RBAC management'],
    'menu-rbac' => ['Menu RBAC', 'Sidebar visibility by role'],
    'settings' => ['Settings', 'HR module configuration'],
    'settings.shifts' => ['Shift Creation', 'Global shift timings'],
    'settings.leave-permission-hierarchy' => ['Leave & Permission Approval Hierarchy', 'Approval levels, escalation SLA, and notification routing'],
    'reports' => ['Reports', 'Attendance and HR analytics'],
    'report-detail' => ['Report Details', 'Table report and exports'],
    'report-export' => ['Report Export', 'Download report output'],
    'audit' => ['Audit Trail', 'Administrative activity log'],
    'employee-attendance' => ['Attendance Report', 'Monthly time statement and attendance details'],
    'employee-attendance-correction' => ['Time Attendance Correction', 'Submit missing or incorrect attendance time'],
    'employee-profile' => ['Employee Profile', ''],
    'employee-duty-roster' => ['Duty Roster', 'Weekly duty schedule'],
    'employee-onboarding' => ['Onboarding', 'Employee joining checklist'],
    'employee-training-kpi' => ['Training/KPI', 'Training sessions and performance summary'],
    'employee-certificates' => ['Certificates', ''],
    'employee-certificate' => ['Certificate', ''],
    'employee-leave-permission' => ['Leave Management', 'Leave and permission requests'],
    'employee-leave' => ['My Leave', 'Personal leave requests'],
    'employee-permission' => ['Permissions', 'Short permission requests'],
    'employee-documents' => ['My Documents', 'Documents and health reports'],
    'employee-payslip' => ['Payslip', 'Personal salary slip'],
];
[$pageTitle, $pageSubtitle] = $pageTitles[$currentRoute] ?? ['Hospital HR', 'Internal workforce management'];
$sidebarMenus = [];
$sidebarProfilePhotoSrc = '';
$dashboardHeaderEmployee = null;
$popupNotifications = [];
$showLoginNotifications = !empty($_SESSION['show_login_notifications']);
unset($_SESSION['show_login_notifications']);
$hospitalName = 'Hospital HR';
$foundationName = 'KRISHNA HEALTHCARE & RESEARCH FOUNDATION';
if (!$user) {
    try {
        $guestDb = $GLOBALS['db'] ?? null;
        if ($guestDb) {
            $guestHospitalSetting = $guestDb->fetch("SELECT setting_value FROM hr_settings WHERE setting_key='hospital_name' LIMIT 1");
            $guestHospitalName = trim((string)($guestHospitalSetting['setting_value'] ?? ''));
            if ($guestHospitalName !== '') {
                $hospitalName = $guestHospitalName;
            }
        }
    } catch (Throwable $e) {
        // Keep the safe fallback when the public landing page cannot read settings.
    }
}
if ($user) {
    $rawRole = trim((string)($user['role'] ?? 'Employee'));
    $roleNames = ['Admin', 'SuperAdmin', 'HR', 'HOD', 'Manager', 'Employee'];
    $role = 'Employee';
    foreach ($roleNames as $roleName) {
        if (strcasecmp($rawRole, $roleName) === 0) {
            $role = $roleName;
            break;
        }
    }
    try {
        $dbForMenu = $GLOBALS['db'] ?? null;
        if ($dbForMenu) {
            $hospitalSetting = $dbForMenu->fetch("SELECT setting_value FROM hr_settings WHERE setting_key='hospital_name' LIMIT 1");
            $configuredHospitalName = trim((string)($hospitalSetting['setting_value'] ?? ''));
            if ($configuredHospitalName !== '') {
                $hospitalName = $configuredHospitalName;
            }
            $profileEmployee = $dbForMenu->fetch('SELECT id,employee_code,first_name,last_name,department,position,gender,photo_path FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => (int)($user['id'] ?? 0)]);
            if (!empty($profileEmployee['photo_path'])) {
                $photoPath = ltrim(str_replace('\\', '/', (string)$profileEmployee['photo_path']), '/');
                $sidebarProfilePhotoSrc = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/') ? $photoPath : 'public/' . $photoPath;
            }
            if (($user['role'] ?? '') === 'Employee' && $currentRoute === 'dashboard' && $profileEmployee) {
                $dashboardHeaderEmployee = [
                    'name' => trim((string)($profileEmployee['first_name'] ?? '') . ' ' . (string)($profileEmployee['last_name'] ?? '')),
                    'code' => (string)($profileEmployee['employee_code'] ?? ''),
                    'designation' => (string)($profileEmployee['position'] ?? ''),
                    'department' => (string)($profileEmployee['department'] ?? ''),
                ];
                $todayDate = date('Y-m-d');
                $todayAttendance = $dbForMenu->fetch('SELECT status FROM attendance WHERE employee_id=:employee_id AND attendance_date=:attendance_date ORDER BY id DESC LIMIT 1', ['employee_id' => (int)$profileEmployee['id'], 'attendance_date' => $todayDate]);
                if ($todayAttendance) {
                    $dashboardTodayStatus = ucwords(str_replace('_', ' ', strtolower((string)$todayAttendance['status'])));
                } else {
                    $todayLeave = $dbForMenu->fetch("SELECT leave_type FROM leave_requests WHERE employee_id=:employee_id AND status='Approved' AND start_date<=:today AND end_date>=:today ORDER BY id DESC LIMIT 1", ['employee_id' => (int)$profileEmployee['id'], 'today' => $todayDate]);
                    $todayDuty = $dbForMenu->fetch('SELECT shift_name FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:today ORDER BY id DESC LIMIT 1', ['employee_id' => (int)$profileEmployee['id'], 'today' => $todayDate]);
                    $dashboardTodayStatus = $todayLeave ? ((stripos((string)$todayLeave['leave_type'], 'permission') !== false) ? 'Permission' : 'On Leave') : ($todayDuty ? 'On Duty' : 'Not Marked');
                }
            }
            if ($showLoginNotifications) {
                $popupNotifications = $dbForMenu->fetchAll(
                    'SELECT n.id,n.title,n.message,n.event_type,n.created_at
                     FROM notifications n
                     JOIN employees e ON e.id=n.employee_id
                     WHERE e.user_id=:user_id AND n.is_read=0
                     ORDER BY n.created_at DESC
                     LIMIT 3',
                    ['user_id' => (int)($user['id'] ?? 0)]
                );
            }
            if ($isSuperAdmin) {
                $sidebarMenus = $dbForMenu->fetchAll('SELECT menu_key,label,route,section_name,icon_label,sort_order FROM sidebar_menu_permissions WHERE role=:role AND is_visible=1 ORDER BY sort_order,label', ['role' => 'SuperAdmin']);
            } else {
                $sidebarMenus = $dbForMenu->fetchAll('SELECT menu_key,label,route,section_name,icon_label,sort_order FROM sidebar_menu_permissions WHERE role=:role AND is_visible=1 ORDER BY sort_order,label', ['role' => $role]);
            }
        }
    } catch (Throwable $e) {
        $sidebarMenus = [];
    }
}
// Role-aware navigation matching exact Enterprise HRM Information Architecture
if ($user) {
    if ($role === 'Employee') {
        $sidebarMenus = [
            ['menu_key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard', 'section_name' => 'Workspace', 'icon_label' => 'D', 'sort_order' => 10],
            ['menu_key' => 'employee_attendance', 'label' => 'Attendance', 'route' => 'employee-attendance', 'section_name' => 'Workspace', 'icon_label' => 'A', 'sort_order' => 20],
            ['menu_key' => 'employee_leave', 'label' => 'Leave', 'route' => 'employee-leave-permission', 'section_name' => 'Workspace', 'icon_label' => 'L', 'sort_order' => 30],
            ['menu_key' => 'employee_training', 'label' => 'Training', 'route' => 'employee-training-kpi', 'section_name' => 'Workspace', 'icon_label' => 'T', 'sort_order' => 40],
            ['menu_key' => 'notifications', 'label' => 'Notices', 'route' => 'notifications', 'section_name' => 'Communication', 'icon_label' => 'N', 'sort_order' => 50],
            ['menu_key' => 'employee_documents', 'label' => 'Documents', 'route' => 'employee-documents', 'section_name' => 'Communication', 'icon_label' => 'D', 'sort_order' => 60],
            ['menu_key' => 'employee_profile', 'label' => 'My Profile', 'route' => 'employee-profile', 'section_name' => 'Personal', 'icon_label' => 'P', 'sort_order' => 70],
            ['menu_key' => 'settings', 'label' => 'Settings', 'route' => 'employee-profile', 'section_name' => 'System', 'icon_label' => 'S', 'sort_order' => 80],
        ];
    } elseif (in_array($role, ['Manager', 'HOD'], true)) {
        $sidebarMenus = [
            ['menu_key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'section_name' => '', 'icon_label' => 'D', 'sort_order' => 10],
            ['menu_key' => 'employee_directory', 'label' => 'My Team', 'route' => 'recruitment', 'section_name' => '', 'icon_label' => 'T', 'sort_order' => 20],
            ['menu_key' => 'attendance', 'label' => 'Attendance', 'route' => 'attendance', 'section_name' => '', 'icon_label' => 'A', 'sort_order' => 30],
            ['menu_key' => 'leave', 'label' => 'Leave Approvals', 'route' => 'leave', 'section_name' => '', 'icon_label' => 'L', 'sort_order' => 40],
            ['menu_key' => 'training', 'label' => 'Training', 'route' => 'training', 'section_name' => '', 'icon_label' => 'T', 'sort_order' => 50],
            ['menu_key' => 'performance', 'label' => 'Performance', 'route' => 'performance', 'section_name' => '', 'icon_label' => 'P', 'sort_order' => 60],
            ['menu_key' => 'notifications', 'label' => 'Notices', 'route' => 'notifications', 'section_name' => '', 'icon_label' => 'N', 'sort_order' => 70],
        ];
    } elseif ($role === 'HR') {
        $sidebarMenus = [
            ['menu_key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'section_name' => '', 'icon_label' => 'D', 'sort_order' => 10],
            ['menu_key' => 'employee_directory', 'label' => 'Employee Directory', 'route' => 'recruitment', 'section_name' => '', 'icon_label' => 'E', 'sort_order' => 20],
            ['menu_key' => 'recruitment', 'label' => 'Recruitment', 'route' => 'recruitment.create', 'section_name' => '', 'icon_label' => 'R', 'sort_order' => 30],
            ['menu_key' => 'leave', 'label' => 'Leave Management', 'route' => 'leave', 'section_name' => '', 'icon_label' => 'L', 'sort_order' => 40],
            ['menu_key' => 'training', 'label' => 'Training', 'route' => 'training', 'section_name' => '', 'icon_label' => 'T', 'sort_order' => 50],
            ['menu_key' => 'attendance', 'label' => 'Attendance', 'route' => 'attendance', 'section_name' => '', 'icon_label' => 'A', 'sort_order' => 60],
            ['menu_key' => 'documents', 'label' => 'Documents', 'route' => 'documents', 'section_name' => '', 'icon_label' => 'D', 'sort_order' => 70],
            ['menu_key' => 'reports', 'label' => 'Reports', 'route' => 'reports', 'section_name' => '', 'icon_label' => 'R', 'sort_order' => 80],
            ['menu_key' => 'notifications', 'label' => 'Notifications', 'route' => 'notifications', 'section_name' => '', 'icon_label' => 'N', 'sort_order' => 90],
        ];
    } else { // Admin / SuperAdmin
        $sidebarMenus = [
            ['menu_key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'section_name' => '', 'icon_label' => 'D', 'sort_order' => 10],
            ['menu_key' => 'employee_directory', 'label' => 'Employees', 'route' => 'recruitment', 'section_name' => '', 'icon_label' => 'E', 'sort_order' => 20],
            ['menu_key' => 'departments', 'label' => 'Departments', 'route' => 'departments', 'section_name' => '', 'icon_label' => 'D', 'sort_order' => 30],
            ['menu_key' => 'recruitment', 'label' => 'Recruitment', 'route' => 'recruitment.create', 'section_name' => '', 'icon_label' => 'R', 'sort_order' => 40],
            ['menu_key' => 'attendance', 'label' => 'Attendance', 'route' => 'attendance', 'section_name' => '', 'icon_label' => 'A', 'sort_order' => 50],
            ['menu_key' => 'leave', 'label' => 'Leave', 'route' => 'leave', 'section_name' => '', 'icon_label' => 'L', 'sort_order' => 60],
            ['menu_key' => 'payroll', 'label' => 'Payroll', 'route' => 'payroll', 'section_name' => '', 'icon_label' => 'P', 'sort_order' => 70],
            ['menu_key' => 'training', 'label' => 'Training & KPI', 'route' => 'training', 'section_name' => '', 'icon_label' => 'T', 'sort_order' => 80],
            ['menu_key' => 'reports', 'label' => 'Reports', 'route' => 'reports', 'section_name' => '', 'icon_label' => 'R', 'sort_order' => 90],
            ['menu_key' => 'notifications', 'label' => 'Notifications', 'route' => 'notifications', 'section_name' => '', 'icon_label' => 'N', 'sort_order' => 100],
            ['menu_key' => 'settings', 'label' => 'Settings', 'route' => 'settings', 'section_name' => '', 'icon_label' => 'S', 'sort_order' => 110],
        ];
    }
}
$sidebarWorkflowOrder = [
    'dashboard' => ['Main', 10],
    'employee_directory' => ['Workforce', 20],
    'onboarding' => ['Workforce', 30],
    'training' => ['Workforce', 40],
    'attendance' => ['Attendance', 50],
    'duty_roster' => ['Attendance', 60],
    'leave_requests' => ['Leave', 70],
    'payroll' => ['Payroll', 80],
    'allowances' => ['Payroll', 90],
    'settings' => ['Administration', 100],
    'departments' => ['Administration', 110],
    'designations' => ['Administration', 120],
    'users' => ['Administration', 130],
    'menu_rbac' => ['Administration', 140],
    'reports' => ['Reports', 150],
    'performance' => ['Reports', 160],
    'performance_leaderboard' => ['Reports', 170],
    'exit' => ['Reports', 180],
    'audit' => ['Reports', 190],
    'notifications' => ['Self Service', 200],
    'employee_attendance' => ['Self Service', 10],
    'employee_duty_roster' => ['Self Service', 36],
    'employee_previous_duty_roster' => ['Self Service', 37],
    'employee_leave' => ['Self Service', 20],
    'employee_onboarding' => ['Self Service', 30],
    'employee_training' => ['Self Service', 40],
    'employee_profile' => ['Self Service', 50],
];
if ($sidebarMenus) {
    $uniqueSidebarMenus = [];
    foreach ($sidebarMenus as $menu) {
        $key = (string)($menu['menu_key'] ?? '');
        if ($key === '' || isset($uniqueSidebarMenus[$key])) {
            continue;
        }
        // Keep the primary Attendance link, but remove the duplicate shown in Self Service.
        if (($menu['section_name'] ?? '') === 'Self Service'
            && ($key === 'employee_attendance' || ($menu['route'] ?? '') === 'employee-attendance')) {
            continue;
        }
        if ($role !== 'Employee' && isset($sidebarWorkflowOrder[$key])) {
            [$sectionName, $sortOrder] = $sidebarWorkflowOrder[$key];
            $menu['section_name'] = $sectionName;
            $menu['sort_order'] = $sortOrder;
        }
        $uniqueSidebarMenus[$key] = $menu;
    }
    $sidebarMenus = array_values($uniqueSidebarMenus);
    usort($sidebarMenus, static function (array $left, array $right): int {
        return [(int)($left['sort_order'] ?? 0), (string)($left['section_name'] ?? ''), (string)($left['label'] ?? '')]
            <=> [(int)($right['sort_order'] ?? 0), (string)($right['section_name'] ?? ''), (string)($right['label'] ?? '')];
    });
}
$bottomSidebarMenus = [];
if ($sidebarMenus) {
    foreach ($sidebarMenus as $index => $menu) {
        if (($menu['menu_key'] ?? '') === 'organisational_hierarchy' || ($menu['route'] ?? '') === 'organisational-hierarchy') {
            $bottomSidebarMenus[] = $menu;
            unset($sidebarMenus[$index]);
        }
    }
    $sidebarMenus = array_values($sidebarMenus);
}
if (in_array($user['role'] ?? '', ['Admin', 'SuperAdmin'], true)) {
    $settingsChildMenuKeys = ['departments', 'designations', 'users', 'menu_rbac'];
    $sidebarMenus = array_values(array_filter($sidebarMenus, static function (array $menu) use ($settingsChildMenuKeys): bool {
        return !in_array((string)($menu['menu_key'] ?? ''), $settingsChildMenuKeys, true);
    }));
}
if (in_array($user['role'] ?? '', ['Admin', 'SuperAdmin', 'Manager'], true)) {
    $reportsChildMenuKeys = ['performance', 'performance_leaderboard', 'exit', 'audit'];
    $sidebarMenus = array_values(array_filter($sidebarMenus, static function (array $menu) use ($reportsChildMenuKeys): bool {
        return !in_array((string)($menu['menu_key'] ?? ''), $reportsChildMenuKeys, true);
    }));
}
$routeAliases = [
    'recruitment' => ['recruitment', 'recruitment.create', 'recruitment.edit'],
    'leave' => ['leave', 'leave.edit', 'leave.decision'],
    'documents' => ['documents'],
    'health-checkups' => ['health-checkups'],
    'performance' => ['performance'],
    'performance-leaderboard' => ['performance-leaderboard'],
    'reports' => ['reports', 'report-detail', 'report-export'],
    'organisational-hierarchy' => ['organisational-hierarchy'],
    'employee-attendance' => ['employee-attendance'],
    'employee-attendance-correction' => ['employee-attendance-correction'],
    'employee-profile' => ['employee-profile'],
    'employee-onboarding' => ['employee-onboarding'],
    'employee-training-kpi' => ['employee-training-kpi'],
    'employee-certificates' => ['employee-certificates', 'employee-certificate'],
    'dashboard' => ['dashboard'],
    'notifications' => ['notifications'],
    'employee-leave-permission' => ['employee-leave-permission', 'employee-leave', 'employee-permission'],
    'employee-documents' => ['employee-documents'],
];
$menuActive = static function (string $route) use ($currentRoute, $routeAliases): bool {
    if (str_contains($route, '#')) {
        return false;
    }
    $baseRoute = strtok($route, '#');
    return in_array($currentRoute, $routeAliases[$baseRoute] ?? [$baseRoute], true);
};
$menuDisabled = static function (string $route): bool {
    $baseRoute = strtok($route, '#');
    return in_array($baseRoute, ['payroll', 'allowances'], true);
};
$navIconSvg = static function (string $route, string $fallback): string {
    $baseRoute = strtok($route, '#');
    $paths = [
        'dashboard' => 'M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z',
        'recruitment' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4Zm-7 8c0-3.31 3.13-6 7-6s7 2.69 7 6H5Z',
        'attendance' => 'M7 2h2v2h6V2h2v2h3v18H4V4h3V2Zm11 8H6v10h12V10Zm-9 3h2v2H9v-2Zm4 0h2v2h-2v-2Z',
        'employee-attendance' => 'M7 2h2v2h6V2h2v2h3v18H4V4h3V2Zm11 8H6v10h12V10Zm-9 3h2v2H9v-2Zm4 0h2v2h-2v-2Z',
        'employee-attendance-correction' => 'M5 4h10l4 4v12H5V4Zm9 1.5V9h3.5L14 5.5ZM8 13h8v2H8v-2Zm0 4h5v2H8v-2Z',
        'employee-profile' => 'M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4Zm-7 8c.5-3.4 3.4-6 7-6s6.5 2.6 7 6H5Z',
        'employee-onboarding' => 'M12 2a5 5 0 0 1 5 5v2h3v13H4V9h3V7a5 5 0 0 1 5-5Zm-3 7h6V7a3 3 0 0 0-6 0v2Z',
        'employee-training-kpi' => 'M12 3 2 8l10 5 8-4v6h2V8L12 3Zm-6 9v4c2 2 10 2 12 0v-4l-6 3-6-3Z',
        'duty-roster' => 'M4 4h16v4H4V4Zm0 6h4v10H4V10Zm6 0h4v10h-4V10Zm6 0h4v10h-4V10Z',
        'leave' => 'M6 3h12v18H6V3Zm3 4h6v2H9V7Zm0 4h6v2H9v-2Zm0 4h4v2H9v-2Z',
        'employee-leave-permission' => 'M6 3h12v18H6V3Zm3 4h6v2H9V7Zm0 4h6v2H9v-2Zm0 4h4v2H9v-2Z',
        'documents' => 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Z',
        'employee-documents' => 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Z',
        'employee-certificates' => 'M5 3h14v18l-7-3-7 3V3Zm4 5h6v2H9V8Zm0 4h6v2H9v-2Z',
        'training' => 'M12 3 2 8l10 5 8-4v6h2V8L12 3Zm-6 9v4c2 2 10 2 12 0v-4l-6 3-6-3Z',
        'onboarding' => 'M12 2a5 5 0 0 1 5 5v2h3v13H4V9h3V7a5 5 0 0 1 5-5Zm-3 7h6V7a3 3 0 0 0-6 0v2Z',
        'performance' => 'M4 19h16v2H4v-2Zm2-8h3v6H6v-6Zm5-6h3v12h-3V5Zm5 3h3v9h-3V8Z',
        'performance-leaderboard' => 'M7 21V9h4v12H7Zm6 0V3h4v18h-4ZM1 21v-7h4v7H1Zm18 0V6h4v15h-4Z',
        'departments' => 'M3 21V3h8v18H3Zm10 0V7h8v14h-8ZM6 7h2v2H6V7Zm0 4h2v2H6v-2Zm10 0h2v2h-2v-2Zm0 4h2v2h-2v-2Z',
        'designations' => 'M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-4Zm-3 9 2 2 4-4 1.4 1.4L11 15.8l-3.4-3.4L9 11Z',
        'reports' => 'M4 4h16v16H4V4Zm3 11h2v3H7v-3Zm4-6h2v9h-2V9Zm4 3h2v6h-2v-6Z',
        'notifications' => 'M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Zm6-6V11a6 6 0 0 0-5-5.92V4a1 1 0 0 0-2 0v1.08A6 6 0 0 0 6 11v5l-2 2v1h16v-1l-2-2Z',
        'settings' => 'M19.4 13.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5-2-3.5-2.4 1a8 8 0 0 0-2.6-1.5L14 2h-4l-.4 2.5A8 8 0 0 0 7 6L4.6 5 2.6 8.5l2 1.5c-.1.5-.1 1-.1 1.5s0 1 .1 1.5l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 2.6 1.5L10 22h4l.4-2.5A8 8 0 0 0 17 18l2.4 1 2-3.5-2-1.5ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z',
        'users' => 'M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3ZM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.67 0-6 1.34-6 4v2h12v-2c0-2.66-3.33-4-6-4Z',
    ];
    $path = $paths[$baseRoute] ?? '';
    if ($path === '') {
        $path = 'M12 2 4 6v12l8 4 8-4V6l-8-4Zm0 2.2 5.5 2.75L12 9.7 6.5 6.95 12 4.2ZM6 8.6l5 2.5v7.7l-5-2.5V8.6Zm7 10.2v-7.7l5-2.5v7.7l-5 2.5Z';
    }
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . htmlspecialchars($path) . '"></path></svg>';
};
?>
<div class="shell">
  <?php if ($user): ?>
    <aside class="sidebar">
      <div class="brand-block">
        <div class="brand-mark" title="Krishna Health — Hospital HRM">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" style="position:relative;z-index:1">
            <path d="M9 3h6v5h5v6h-5v5H9v-5H4V8h5V3Z" fill="rgba(255,255,255,0.9)"/>
          </svg>
        </div>
        <div class="brand-text">
          <h2>Krishna Health</h2>
          <span>Hospital HRM</span>
        </div>
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Collapse navigation" title="Collapse navigation">
          <span></span><span></span><span></span>
        </button>
      </div>

      <?php if (($user['role'] ?? '') !== 'Employee'): ?>
        <div class="profile-block">
          <div class="profile-photo-wrap">
            <?php if ($sidebarProfilePhotoSrc !== ''): ?>
              <img src="<?= htmlspecialchars($sidebarProfilePhotoSrc) ?>" alt="Profile Photo" class="profile-photo">
            <?php else: ?>
              <span class="profile-photo-fallback"><?= htmlspecialchars(strtoupper(substr((string)$user['name'], 0, 1))) ?></span>
            <?php endif; ?>
          </div>
          <div class="profile-meta">
            <strong><?= htmlspecialchars($user['name']) ?></strong>
            <span><?= htmlspecialchars($user['role']) ?></span>
          </div>
        </div>
      <?php endif; ?>
      <?php if (($role ?? '') === 'Employee'): ?>
        <?php
          $employeeSidebarGroups = [];
          foreach ($sidebarMenus as $menu) {
              $section = trim((string)($menu['section_name'] ?? ''));
              if ($section === '') {
                  $section = 'Workspace';
              }
              $employeeSidebarGroups[$section][] = $menu;
          }
        ?>
        <?php foreach ($employeeSidebarGroups as $sectionName => $sectionMenus): ?>
          <div class="employee-nav-section-title"><?= htmlspecialchars($sectionName) ?></div>
          <?php foreach ($sectionMenus as $menu): ?>
            <?php
              $menuRoute = (string)$menu['route'];
              $menuRouteBase = strtok($menuRoute, '#');
              $menuRouteHash = str_contains($menuRoute, '#') ? substr($menuRoute, strpos($menuRoute, '#')) : '';
              $href = $menuRouteHash !== '' ? '?route=' . urlencode($menuRouteBase) . htmlspecialchars($menuRouteHash) : '?route=' . urlencode($menuRoute);
            ?>
            <?php if ($menuDisabled($menuRoute)): ?>
              <span class="sidebar-disabled-link" data-nav-route="<?= htmlspecialchars($menuRouteBase) ?>" data-nav-hash="<?= htmlspecialchars($menuRouteHash) ?>" title="<?= htmlspecialchars($menu['label']) ?> inactive"><span class="nav-icon"><?= $navIconSvg($menuRoute, $menu['icon_label']) ?></span><span class="nav-label"><?= htmlspecialchars($menu['label']) ?><small>Inactive</small></span></span>
            <?php else: ?>
              <a<?= $menuActive($menuRoute) ? ' class="active"' : '' ?> href="<?= $href ?>" data-nav-route="<?= htmlspecialchars($menuRouteBase) ?>" data-nav-hash="<?= htmlspecialchars($menuRouteHash) ?>" title="<?= htmlspecialchars($menu['label']) ?>"><span class="nav-icon"><?= $navIconSvg($menuRoute, $menu['icon_label']) ?></span><span class="nav-label"><?= htmlspecialchars($menu['label']) ?></span></a>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php $lastSection = null; ?>
        <?php foreach ($sidebarMenus as $menu): ?>
          <?php if (!empty($menu['section_name']) && $lastSection !== $menu['section_name']): ?>
            <div class="menu-section"><?= htmlspecialchars($menu['section_name']) ?></div>
            <?php $lastSection = $menu['section_name']; ?>
          <?php endif; ?>
          <?php
            $menuRoute = (string)$menu['route'];
            $menuRouteBase = strtok($menuRoute, '#');
            $menuRouteHash = str_contains($menuRoute, '#') ? substr($menuRoute, strpos($menuRoute, '#')) : '';
            $href = $menuRouteHash !== '' ? '?route=' . urlencode($menuRouteBase) . htmlspecialchars($menuRouteHash) : '?route=' . urlencode($menuRoute);
          ?>
          <?php if ($menuDisabled($menuRoute)): ?>
            <span class="sidebar-disabled-link" data-nav-route="<?= htmlspecialchars($menuRouteBase) ?>" data-nav-hash="<?= htmlspecialchars($menuRouteHash) ?>" title="<?= htmlspecialchars($menu['label']) ?> inactive"><span class="nav-icon"><?= $navIconSvg($menuRoute, $menu['icon_label']) ?></span><span class="nav-label"><?= htmlspecialchars($menu['label']) ?><small>Inactive</small></span></span>
          <?php else: ?>
            <a<?= $menuActive($menuRoute) ? ' class="active"' : '' ?> href="<?= $href ?>" data-nav-route="<?= htmlspecialchars($menuRouteBase) ?>" data-nav-hash="<?= htmlspecialchars($menuRouteHash) ?>" title="<?= htmlspecialchars($menu['label']) ?>"><span class="nav-icon"><?= $navIconSvg($menuRoute, $menu['icon_label']) ?></span><span class="nav-label"><?= htmlspecialchars($menu['label']) ?></span></a>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php foreach ($bottomSidebarMenus as $menu): ?>
        <?php
          $menuRoute = (string)$menu['route'];
          $menuRouteBase = strtok($menuRoute, '#');
          $menuRouteHash = str_contains($menuRoute, '#') ? substr($menuRoute, strpos($menuRoute, '#')) : '';
          $href = $menuRouteHash !== '' ? '?route=' . urlencode($menuRouteBase) . htmlspecialchars($menuRouteHash) : '?route=' . urlencode($menuRoute);
        ?>
        <a<?= $menuActive($menuRoute) ? ' class="sidebar-bottom-link active"' : ' class="sidebar-bottom-link"' ?> href="<?= $href ?>" data-nav-route="<?= htmlspecialchars($menuRouteBase) ?>" data-nav-hash="<?= htmlspecialchars($menuRouteHash) ?>" title="<?= htmlspecialchars($menu['label']) ?>"><span class="nav-icon"><?= $navIconSvg($menuRoute, $menu['icon_label']) ?></span><span class="nav-label"><?= htmlspecialchars($menu['label']) ?></span></a>
      <?php endforeach; ?>
      <a class="logout-link" href="?route=logout" title="Logout"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 3H4v18h6v-2H6V5h4V3Zm4.6 4.4L13.2 8.8 15.4 11H9v2h6.4l-2.2 2.2 1.4 1.4L19.4 12l-4.8-4.6Z"></path></svg></span><span class="nav-label">Logout</span></a>
      <a class="developer-help-desk" href="mailto:helpdesk@365deck.com?subject=HRM%20Help%20Desk%20Request" title="Developer Help Desk"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v12H7.8L4 19.5V4Zm2 2v8.9l1-.9h11V6H6Zm3 3h6v2H9V9Zm0 3h8v2H9v-2Z"></path></svg></span><span class="nav-label"><strong>Developer Help Desk</strong><small>helpdesk@365deck.com<br>+91-9486669908</small></span></a>
    </aside>
  <?php endif; ?>
  <main class="content">
    <?php if ($user): ?>
      <?php
        $topbarDisplayName = (string)($user['name'] ?? 'Staff Member');
        $topbarDisplayRole = (string)($user['role'] ?? 'Staff');
        $topbarDisplayCode = '';
        if (!empty($profileEmployee)) {
            $empFull = trim((string)($profileEmployee['first_name'] ?? '') . ' ' . (string)($profileEmployee['last_name'] ?? ''));
            if ($empFull !== '') {
                $topbarDisplayName = $empFull;
            }
            if (!empty($profileEmployee['position'])) {
                $topbarDisplayRole = (string)$profileEmployee['position'];
            }
            $topbarDisplayCode = (string)($profileEmployee['employee_code'] ?? '');
        }
        $nameWords = preg_split('/\s+/', trim($topbarDisplayName));
        $userInitials = '';
        if (count($nameWords) >= 2) {
            $userInitials = strtoupper(substr($nameWords[0], 0, 1) . substr($nameWords[count($nameWords) - 1], 0, 1));
        } else {
            $userInitials = strtoupper(substr($topbarDisplayName, 0, 2));
        }

        // Header unread notifications count query
        $headerUnreadCount = 0;
        try {
            $headerDbConn = $GLOBALS['db'] ?? null;
            if ($headerDbConn && $user) {
                $headerUnreadRow = $headerDbConn->fetch(
                    'SELECT COUNT(*) c FROM notifications n JOIN employees e ON e.id=n.employee_id WHERE e.user_id=:uid AND n.is_read=0',
                    ['uid' => (int)($user['id'] ?? 0)]
                );
                $headerUnreadCount = (int)($headerUnreadRow['c'] ?? 0);
            }
        } catch (Throwable $e) { /* silent */ }
      ?>
      <header class="topbar">
        <div class="topbar-search-wrap">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          <input type="text" class="topbar-search-input" placeholder="<?= (($user['role'] ?? '') === 'Employee') ? 'Search trainings, policies, notices...' : 'Search employees, departments, reports...' ?>" aria-label="Search" onkeyup="if(event.key==='Enter'&&this.value.trim()){window.location.href='?route=recruitment&q='+encodeURIComponent(this.value.trim())}">
        </div>
        <div class="topbar-right">
          <span class="topbar-date"><?= date('D, M j, Y') ?></span>
          <a class="topbar-icon-btn" href="?route=notifications" title="Notifications">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2Zm6-6V11a6 6 0 0 0-5-5.92V4a1 1 0 0 0-2 0v1.08A6 6 0 0 0 6 11v5l-2 2v1h16v-1l-2-2Z"/></svg>
            <?php if ($headerUnreadCount > 0): ?>
              <span class="topbar-badge"><?= min($headerUnreadCount, 99) ?></span>
            <?php endif; ?>
          </a>
          <!-- Theme toggle removed per user specification -->
          <!-- User Profile Dropdown Trigger -->
          <div class="topbar-user-wrap" style="position:relative">
            <div class="topbar-user-chip" id="topbarUserChip" style="cursor:pointer">
              <div class="topbar-avatar">
                <?php if ($sidebarProfilePhotoSrc !== ''): ?>
                  <img src="<?= htmlspecialchars($sidebarProfilePhotoSrc) ?>" alt="<?= htmlspecialchars($topbarDisplayName) ?>">
                <?php else: ?>
                  <span><?= htmlspecialchars($userInitials) ?></span>
                <?php endif; ?>
              </div>
              <div class="topbar-user-meta">
                <span class="topbar-user-name"><?= htmlspecialchars($topbarDisplayName) ?></span>
                <span class="topbar-user-role"><?= htmlspecialchars($topbarDisplayRole) ?></span>
              </div>
            </div>
            <div id="topbarUserDropdown" style="display:none;position:absolute;right:0;top:calc(100% + 10px);background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 30px rgba(15,23,42,0.12);min-width:210px;padding:8px 0;z-index:1000">
              <div style="padding:10px 16px;border-bottom:1px solid #f1f5f9">
                <div style="font-weight:700;color:#0f172a;font-size:13px"><?= htmlspecialchars($topbarDisplayName) ?></div>
                <div style="font-size:11px;color:#64748b"><?= htmlspecialchars($topbarDisplayRole) ?><?= $topbarDisplayCode ? ' • ' . htmlspecialchars($topbarDisplayCode) : '' ?></div>
              </div>
              <?php if (($user['role'] ?? '') === 'Employee'): ?>
                <a href="?route=employee-profile" style="display:flex;align-items:center;gap:10px;padding:9px 16px;color:#334155;text-decoration:none;font-size:13px;font-weight:500;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="#64748b"><path d="M12 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4Zm-7 8c.5-3.4 3.4-6 7-6s6.5 2.6 7 6H5Z"/></svg>
                  My Profile
                </a>
                <a href="?route=employee-leave-permission" style="display:flex;align-items:center;gap:10px;padding:9px 16px;color:#334155;text-decoration:none;font-size:13px;font-weight:500;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="#64748b"><path d="M6 3h12v18H6V3Zm3 4h6v2H9V7Zm0 4h6v2H9v-2Zm0 4h4v2H9v-2Z"/></svg>
                  Apply Leave
                </a>
              <?php endif; ?>
              <a href="?route=logout" style="display:flex;align-items:center;gap:10px;padding:9px 16px;color:#e11d48;text-decoration:none;font-size:13px;font-weight:600;border-top:1px solid #f1f5f9;transition:background .15s" onmouseover="this.style.background='#fff1f2'" onmouseout="this.style.background='transparent'">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="#e11d48"><path d="M10 3H4v18h6v-2H6V5h4V3Zm4.6 4.4L13.2 8.8 15.4 11H9v2h6.4l-2.2 2.2 1.4 1.4L19.4 12l-4.8-4.6Z"/></svg>
                Logout
              </a>
            </div>
          </div>
        </div>
      </header>
      <script>
        (function() {
          var chip = document.getElementById('topbarUserChip');
          var drop = document.getElementById('topbarUserDropdown');
          if (chip && drop) {
            chip.addEventListener('click', function(e) {
              e.stopPropagation();
              drop.style.display = drop.style.display === 'none' ? 'block' : 'none';
            });
            document.addEventListener('click', function(e) {
              if (!e.target.closest('.topbar-user-wrap')) {
                drop.style.display = 'none';
              }
            });
          }
        })();
      </script>
    <?php endif; ?>
    <?php if ($ok = flash('ok')): ?><div class="ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if ($err = flash('error')): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (!empty($popupNotifications)): ?>
      <div class="alert-popup-stack" role="status" aria-live="polite">
        <?php foreach ($popupNotifications as $notice): ?>
          <a class="alert-popup" href="?route=notifications" data-alert-popup-id="<?= (int)$notice['id'] ?>">
            <strong><?= htmlspecialchars((string)$notice['title']) ?></strong>
            <span><?= htmlspecialchars((string)$notice['message']) ?></span>
            <small><?= htmlspecialchars(fmt_datetime($notice['created_at'])) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
