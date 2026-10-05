<?php
require __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/Services/NotificationService.php';
require_once __DIR__ . '/../app/Services/AttendanceImportService.php';
require_once __DIR__ . '/../app/Controllers/MainController.php';

// ── Security Headers ─────────────────────────────────────────────────────────
// Must be sent before ANY output (HTML, whitespace, redirects).
if (!headers_sent()) {
    // Prevent MIME-type sniffing attacks.
    header('X-Content-Type-Options: nosniff');
    // Deny embedding this app in iframes (clickjacking protection).
    header('X-Frame-Options: SAMEORIGIN');
    // Enable browser XSS filter (legacy but harmless).
    header('X-XSS-Protection: 1; mode=block');
    // Do not send full URL in Referer header to third parties.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Remove server signature.
    header_remove('X-Powered-By');
    // Content-Security-Policy: allow self + Chart.js CDN + Google Fonts.
    // Adjust script-src hashes or nonces if more inline scripts are introduced.
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: blob:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'self';"
    );
    // Force HTTPS on production (only when already on HTTPS to avoid redirect loops).
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
// ─────────────────────────────────────────────────────────────────────────────

use App\Controllers\MainController;
use App\Services\NotificationService;

$app = require __DIR__ . '/../config/app.php';
$controller = new MainController($db, $auth, new NotificationService($db), $app);
$route = $_GET['route'] ?? 'dashboard';


if ($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'GET') { $controller->loginForm(); return; }
if ($route === 'login.submit' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $controller->login(); return; }
if ($route === 'employee-register' && $_SERVER['REQUEST_METHOD'] === 'GET') { $controller->employeeRegisterForm(); return; }
if ($route === 'employee-register.submit' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $controller->employeeRegister(); return; }
if ($route === 'logout') { $controller->logout(); return; }

require_auth($auth);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}
if ($route === 'force-password' && $_SERVER['REQUEST_METHOD'] === 'GET') { $controller->forcePasswordForm(); return; }
if ($route === 'force-password.update' && $_SERVER['REQUEST_METHOD'] === 'POST') { $controller->forcePasswordUpdate(); return; }
require_password_current($auth, $route);
$controller->processLeaveApprovalEscalations();

switch ($route) {
    case 'dashboard': $controller->dashboard(); break;
    case 'organisational-hierarchy': require_role($auth, ['Admin','HR','Manager']); $controller->organisationalHierarchy(); break;
    case 'employee-portal': require_role($auth, ['Employee']); $controller->employeePortal(); break;
    case 'employee-attendance': require_role($auth, ['Employee']); $controller->employeeAttendance(); break;
    case 'employee-attendance-correction': require_role($auth, ['Employee']); $controller->employeeAttendanceCorrection(); break;
    case 'employee.attendance-correction.store': require_role($auth, ['Employee']); $controller->employeeAttendanceCorrectionStore(); break;
    case 'employee-profile': require_role($auth, ['Employee']); $controller->employeeProfilePage(); break;
    case 'employee-duty-roster': require_role($auth, ['Employee']); $controller->employeeDutyRosterPage(); break;
    case 'employee-duty-roster-previous': require_role($auth, ['Employee']); $_GET['period'] = 'previous'; $controller->employeeDutyRosterPage(); break;
    case 'employee.internal-request.store': require_role($auth, ['Employee']); $controller->employeeInternalRequestStore(); break;
    case 'employee-onboarding': require_role($auth, ['Employee']); $controller->employeeOnboardingPage(); break;
    case 'employee.onboarding-profile.update': require_role($auth, ['Employee']); $controller->employeeOnboardingProfileUpdate(); break;
    case 'employee-training-kpi': require_role($auth, ['Employee']); $controller->employeeTrainingKpiPage(); break;
    case 'employee-certificates': require_role($auth, ['Employee']); $controller->employeeCertificates(); break;
    case 'employee-certificate': require_role($auth, ['Employee']); $controller->employeeCertificate(); break;
    case 'employee-availed': require_role($auth, ['Employee']); $controller->employeeAvailedHistory(); break;
    case 'employee-leave': require_role($auth, ['Employee']); $controller->employeeLeavePage(); break;
    case 'employee-permission': require_role($auth, ['Employee']); $controller->employeePermissionPage(); break;
    case 'employee-leave-permission': require_role($auth, ['Employee']); $controller->employeeLeavePermissionPage(); break;
    case 'employee-documents': require_role($auth, ['Employee']); $controller->employeeDocumentsPage(); break;
    case 'employee-payslip': require_role($auth, ['Employee']); $controller->employeePayslip(); break;
    case 'employee.leave.store': require_role($auth, ['Employee']); $controller->employeeLeaveStore(); break;
    case 'employee.document.store': require_role($auth, ['Employee']); $controller->employeeDocumentStore(); break;
    case 'employee.password.update': require_role($auth, ['Employee']); $controller->employeePasswordUpdate(); break;
    case 'employee.photo.update': require_role($auth, ['Employee']); $controller->employeePhotoUpdate(); break;
    case 'employee.document.download': require_role($auth, ['Employee']); $controller->employeeDocumentDownload(); break;
    case 'employee.health.download': require_role($auth, ['Employee']); $controller->employeeHealthDownload(); break;
    case 'employee.training.update': require_role($auth, ['Employee']); $controller->employeeTrainingUpdate(); break;

    case 'recruitment': require_role($auth, ['Admin','HR','HOD']); $controller->recruitment(); break;
    case 'recruitment.create': require_role($auth, ['Admin','HR']); $controller->recruitmentCreate(); break;
    case 'recruitment.edit': require_role($auth, ['Admin','HR']); $controller->recruitmentEdit(); break;
    case 'recruitment.store': require_role($auth, ['Admin','HR']); $controller->recruitmentStore(); break;
    case 'recruitment.template': require_role($auth, ['Admin','HR']); $controller->recruitmentTemplate(); break;
    case 'recruitment.bulk-upload': require_role($auth, ['Admin','HR']); $controller->recruitmentBulkUpload(); break;
    case 'recruitment.regenerate-codes': require_role($auth, ['Admin']); $controller->recruitmentRegenerateCodes(); break;
    case 'recruitment.update': require_role($auth, ['Admin','HR']); $controller->recruitmentUpdate(); break;
    case 'recruitment.delete': require_role($auth, ['Admin','HR']); $controller->recruitmentDelete(); break;
    case 'employee.password.reset': require_role($auth, ['HR']); $controller->employeePasswordReset(); break;
    case 'manpower-requisition.store': require_role($auth, ['Admin','HR','HOD']); $controller->manpowerRequisitionStore(); break;
    case 'manpower-requisition.status': require_role($auth, ['Admin','HR']); $controller->manpowerRequisitionStatus(); break;

    case 'onboarding': require_role($auth, ['Admin','HR']); $controller->onboarding(); break;
    case 'onboarding.store': require_role($auth, ['Admin','HR']); $controller->onboardingStore(); break;
    case 'onboarding.update': require_role($auth, ['Admin','HR']); $controller->onboardingUpdate(); break;
    case 'onboarding.delete': require_role($auth, ['Admin']); $controller->onboardingDelete(); break;

    case 'training': require_role($auth, ['Admin','HR','Manager']); $controller->training(); break;
    case 'training.store': require_role($auth, ['Admin','HR','Manager']); $controller->trainingStore(); break;
    case 'training.update': require_role($auth, ['Admin','HR','Manager']); $controller->trainingUpdate(); break;
    case 'training.delete': require_role($auth, ['Admin']); $controller->trainingDelete(); break;

    case 'attendance': require_role($auth, ['Admin','HR']); $controller->attendance(); break;
    case 'attendance-live-status': require_role($auth, ['Admin','HR']); $controller->attendanceLiveStatus(); break;
    case 'attendance-monthly-summary': require_role($auth, ['Admin','HR']); $controller->attendanceMonthlySummary(); break;
    case 'attendance-daily-report': require_role($auth, ['Admin','HR']); $controller->attendanceDailyReport(); break;
    case 'attendance-upload': require_role($auth, ['Admin','HR']); $controller->attendanceUpload(); break;
    case 'attendance-manual-absent': require_role($auth, ['Admin','HR']); $controller->attendanceManualAbsent(); break;
    case 'attendance.calendar-report': require_role($auth, ['Admin','HR']); $controller->attendanceCalendarReportExport(); break;
    case 'attendance.template': require_role($auth, ['Admin','HR']); $controller->attendanceTemplate(); break;
    case 'attendance.import': require_role($auth, ['Admin','HR']); $controller->attendanceImport(); break;
    case 'attendance.mark-absent': require_role($auth, ['Admin','HR']); $controller->attendanceMarkAbsent(); break;
    case 'attendance.update': require_role($auth, ['Admin','HR']); $controller->attendanceUpdate(); break;
    case 'attendance.delete': require_role($auth, ['Admin']); $controller->attendanceDelete(); break;
    case 'duty-roster': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRoster(); break;
    case 'duty-roster.export': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterExport(); break;
    case 'duty-roster.store': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterStore(); break;
    case 'duty-roster.assign': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterAssign(); break;
    case 'duty-roster.department-assign': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterDepartmentAssign(); break;
    case 'staffing-requirement.store': require_role($auth, ['Admin','HR','HOD']); $controller->staffingRequirementStore(); break;
    case 'duty-roster.auto': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterAutoGenerate(); break;
    case 'duty-roster.toggle': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterToggle(); break;
    case 'duty-roster.bulk-apply': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterBulkApply(); break;
    case 'duty-roster.bulk-upload': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterBulkUpload(); break;
    case 'duty-roster.update': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterUpdate(); break;
    case 'duty-roster.delete': require_role($auth, ['Admin','HR','HOD']); $controller->dutyRosterDelete(); break;

    case 'leave': require_role($auth, ['Admin','SuperAdmin','HR','HOD','Manager']); $controller->leave(); break;
    case 'leave.decision': require_role($auth, ['Admin','SuperAdmin','HR','HOD','Manager']); $controller->leaveDecision(); break;
    case 'leave.edit': require_role($auth, ['Admin']); $controller->leaveEdit(); break;
    case 'leave.store': require_role($auth, ['Admin','HR']); $controller->leaveStore(); break;
    case 'leave.status': require_role($auth, ['Admin','SuperAdmin','HR','HOD','Manager']); $controller->leaveUpdateStatus(); break;
    case 'leave.update': require_role($auth, ['Admin']); $controller->leaveUpdate(); break;
    case 'leave.delete': require_role($auth, ['Admin']); $controller->leaveDelete(); break;
    case 'documents': require_role($auth, ['Admin','HR']); $controller->documents(); break;
    case 'documents.update': require_role($auth, ['Admin','HR']); $controller->documentUpdate(); break;
    case 'documents.download': require_role($auth, ['Admin','HR']); $controller->documentDownload(); break;
    case 'credentials.store': require_role($auth, ['Admin','HR']); $controller->credentialStore(); break;
    case 'credentials.delete': require_role($auth, ['Admin','HR']); $controller->credentialDelete(); break;
    case 'health-checkups': require_role($auth, ['Admin','HR']); $controller->healthCheckups(); break;
    case 'health-checkups.store': require_role($auth, ['Admin','HR']); $controller->healthCheckupStore(); break;
    case 'health-checkups.download': require_role($auth, ['Admin','HR']); $controller->healthCheckupDownload(); break;

    case 'performance': require_role($auth, ['Admin','Manager']); $controller->performance(); break;
    case 'performance-leaderboard': require_role($auth, ['Admin','Manager']); $controller->performanceLeaderboard(); break;
    case 'performance.store': require_role($auth, ['Admin','Manager']); $controller->performanceStore(); break;
    case 'performance.update': require_role($auth, ['Admin']); $controller->performanceUpdate(); break;
    case 'performance.delete': require_role($auth, ['Admin']); $controller->performanceDelete(); break;

    case 'exit': require_role($auth, ['Admin','Manager']); $controller->exitModule(); break;
    case 'exit.store': require_role($auth, ['Admin','Manager']); $controller->exitStore(); break;
    case 'exit.update': require_role($auth, ['Admin']); $controller->exitUpdate(); break;
    case 'exit.clearance.update': require_role($auth, ['Admin','HR','Manager']); $controller->exitClearanceUpdate(); break;
    case 'exit.delete': require_role($auth, ['Admin']); $controller->exitDelete(); break;

    case 'payroll':
    case 'payroll.store':
    case 'payroll.generate':
    case 'payroll.status':
    case 'payroll.delete':
    case 'allowances':
    case 'allowances.store':
    case 'allowances.delete':
        require_role($auth, ['Admin','SuperAdmin','HR']);
        $controller->payrollDisabled();
        break;
    case 'departments': require_role($auth, ['Admin','HR','Manager']); $controller->departments(); break;
    case 'departments.store': require_role($auth, ['Admin','HR','Manager']); $controller->departmentStore(); break;
    case 'departments.delete': require_role($auth, ['Admin','HR','Manager']); $controller->departmentDelete(); break;
    case 'designations': require_role($auth, ['Admin','HR','Manager']); $controller->designations(); break;
    case 'designations.store': require_role($auth, ['Admin','HR','Manager']); $controller->designationStore(); break;
    case 'designations.delete': require_role($auth, ['Admin','HR','Manager']); $controller->designationDelete(); break;
    case 'users': require_role($auth, ['Admin']); $controller->users(); break;
    case 'users.role': require_role($auth, ['Admin']); $controller->userRole(); break;
    case 'menu-rbac': require_role($auth, ['Admin','HR']); $controller->menuRbac(); break;
    case 'menu-rbac.save': require_role($auth, ['Admin','HR']); $controller->menuRbacSave(); break;
    case 'settings': require_role($auth, ['Admin']); $controller->settings(); break;
    case 'settings.store': require_role($auth, ['Admin']); $controller->settingStore(); break;
    case 'settings.shifts': require_role($auth, ['Admin']); $controller->shiftSettings(); break;
    case 'settings.shifts.store': require_role($auth, ['Admin']); $controller->shiftSettingsStore(); break;
    case 'settings.shifts.delete': require_role($auth, ['Admin']); $controller->shiftSettingsDelete(); break;
    case 'settings.leave-permission.store': require_role($auth, ['Admin']); $controller->leavePermissionStandardsStore(); break;
    case 'settings.leave-permission-hierarchy': require_role($auth, ['Admin']); $controller->leavePermissionApprovalHierarchy(); break;
    case 'settings.leave-permission-hierarchy.store': require_role($auth, ['Admin']); $controller->leavePermissionHierarchyStore(); break;
    case 'reports': require_role($auth, ['Admin','HR','Manager']); $controller->reports(); break;
    case 'report-detail': require_role($auth, ['Admin','HR','Manager']); $controller->reportDetail(); break;
    case 'report-export': require_role($auth, ['Admin','HR','Manager']); $controller->reportExport(); break;
    case 'audit': require_role($auth, ['Admin','SuperAdmin']); $controller->audit(); break;
    case 'audit.important': require_role($auth, ['Admin','SuperAdmin']); $controller->auditImportant(); break;
    case 'audit.clear': require_role($auth, ['Admin','SuperAdmin']); $controller->auditClear(); break;

    case 'notifications': $controller->notifications(); break;
    case 'internal-request.reply': require_role($auth, ['Admin','HR','HOD']); $controller->internalRequestReply(); break;
    case 'notification.update': require_role($auth, ['Admin']); $controller->notificationUpdate(); break;
    case 'notification.delete': require_role($auth, ['Admin']); $controller->notificationDelete(); break;

    case 'export': require_role($auth, ['Admin','HR','Manager']); $controller->csv($_GET['type'] ?? ''); break;
    default: http_response_code(404); echo 'Not Found';
}
