<?php
$app = require __DIR__ . '/app.php';
$dbConfig = require __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($app['session_name']);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Core/View.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

$db = new App\Core\Database($dbConfig);
$auth = new App\Core\Auth($db);

if ($db->fetch("SHOW TABLES LIKE 'users'")) {
    if (!$db->fetch("SHOW COLUMNS FROM users LIKE 'force_password_change'")) {
        $db->execute("ALTER TABLE users ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }
    if (!$db->fetch("SHOW COLUMNS FROM users LIKE 'username'")) {
        $db->execute("ALTER TABLE users ADD COLUMN username VARCHAR(120) NULL AFTER name");
    }

    $passwordResetMarker = __DIR__ . '/../storage/.default-passwords-applied';
    if (!is_file($passwordResetMarker)) {
        $adminPasswordHash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $employeePasswordHash = password_hash('kh1234', PASSWORD_DEFAULT);
        $db->execute(
            "UPDATE users
             SET password_hash = CASE
                 WHEN role IN ('SuperAdmin','Admin','HR') THEN :admin_password
                 ELSE :employee_password
             END,
             force_password_change = 1",
            [
                'admin_password' => $adminPasswordHash,
                'employee_password' => $employeePasswordHash,
            ]
        );
        @touch($passwordResetMarker);
    }
}

// This migration is intentionally independent of the older aggregate schema marker.
// It must run on existing installations that have already completed that migration.
$leaveApprovalMarker = __DIR__ . '/../storage/.leave-approval-hierarchy-migration-complete';
if (!is_file($leaveApprovalMarker) && $db->fetch("SHOW TABLES LIKE 'leave_requests'")) {
    $db->execute("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS approval_level TINYINT NOT NULL DEFAULT 1 AFTER status");
    $db->execute("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS level1_warning_at DATETIME NULL AFTER approval_level");
    $db->execute("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS level2_escalated_at DATETIME NULL AFTER level1_warning_at");
    $db->execute("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS level2_warning_at DATETIME NULL AFTER level2_escalated_at");
    $db->execute("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS level3_escalated_at DATETIME NULL AFTER level2_warning_at");
    $db->execute("ALTER TABLE leave_requests MODIFY COLUMN status ENUM('Pending','Escalated - Level 2','Critical Escalation - Level 3','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
    @touch($leaveApprovalMarker);
}

if ($db->fetch("SHOW TABLES LIKE 'employee_internal_requests'")) {
    if (!$db->fetch("SHOW COLUMNS FROM employee_internal_requests LIKE 'reply_message'")) {
        $db->execute("ALTER TABLE employee_internal_requests ADD COLUMN reply_message TEXT NULL AFTER message");
    }
    if (!$db->fetch("SHOW COLUMNS FROM employee_internal_requests LIKE 'replied_by'")) {
        $db->execute("ALTER TABLE employee_internal_requests ADD COLUMN replied_by INT NULL AFTER reply_message");
    }
    if (!$db->fetch("SHOW COLUMNS FROM employee_internal_requests LIKE 'replied_at'")) {
        $db->execute("ALTER TABLE employee_internal_requests ADD COLUMN replied_at DATETIME NULL AFTER replied_by");
    }
    $db->execute("UPDATE employee_internal_requests SET status='In Review' WHERE status='Closed'");
    $db->execute("ALTER TABLE employee_internal_requests MODIFY COLUMN status ENUM('Open','In Review','Completed') NOT NULL DEFAULT 'Open'");
}

$db->execute(
    "CREATE TABLE IF NOT EXISTS hr_shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_name VARCHAR(80) NOT NULL UNIQUE,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        INDEX idx_hr_shifts_active_order (is_active, sort_order, shift_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$db->execute(
    "INSERT IGNORE INTO hr_shifts(shift_name,start_time,end_time,is_active,sort_order,created_at) VALUES
    ('General','09:00:00','17:00:00',1,10,NOW()),
    ('Morning','07:00:00','15:00:00',1,20,NOW()),
    ('Afternoon','13:00:00','21:00:00',1,30,NOW()),
    ('Night','21:00:00','07:00:00',1,40,NOW())"
);

$schemaMarker = __DIR__ . '/../storage/.schema-migrations-complete';
if (!is_file($schemaMarker)) {
if ($db->fetch("SHOW TABLES LIKE 'attendance'") && !$db->fetch("SHOW INDEX FROM attendance WHERE Key_name='uq_attendance_employee_date'")) {
    $db->execute("ALTER TABLE attendance ADD UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date)");
}

// Ensure SuperAdmin role is supported in existing installations.
$roleColumn = $db->fetch("SHOW COLUMNS FROM users LIKE 'role'");
if (!$roleColumn) {
    $db->execute("ALTER TABLE users ADD COLUMN role ENUM('Admin','SuperAdmin','HR','HOD','Manager','Employee') NOT NULL DEFAULT 'Employee' AFTER password_hash");
}
$roleIdColumn = $db->fetch("SHOW COLUMNS FROM users LIKE 'role_id'");
if ($roleIdColumn && $db->fetch("SHOW TABLES LIKE 'roles'")) {
    $db->execute(
        "UPDATE users u
         JOIN roles r ON r.id = u.role_id
         SET u.role = CASE
             WHEN LOWER(REPLACE(r.name, ' ', '')) = 'superadmin' THEN 'SuperAdmin'
             WHEN LOWER(r.name) = 'admin' THEN 'Admin'
             WHEN LOWER(r.name) = 'hr' THEN 'HR'
             WHEN LOWER(r.name) = 'hod' THEN 'HOD'
             WHEN LOWER(r.name) = 'manager' THEN 'Manager'
             ELSE u.role
         END"
    );
}
$db->execute("ALTER TABLE users MODIFY COLUMN role ENUM('Admin','SuperAdmin','HR','HOD','Manager','Employee') NOT NULL DEFAULT 'Employee'");
$db->execute("ALTER TABLE employees MODIFY COLUMN department VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL");
$db->execute("ALTER TABLE employees MODIFY COLUMN location ENUM('KH','KCI','KNS','MANAGEMENT') NULL");
if ($db->fetch("SHOW TABLES LIKE 'hr_departments'")) {
    $db->execute("ALTER TABLE hr_departments MODIFY COLUMN name VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
    $db->execute("ALTER TABLE hr_departments MODIFY COLUMN location ENUM('KH','KCI','KNS','MANAGEMENT') NULL");
}
if ($db->fetch("SHOW TABLES LIKE 'hr_designations'")) {
    $db->execute("ALTER TABLE hr_designations MODIFY COLUMN department_name VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL");
}

// Backward-compatible employee profile fields for detailed hospital staff records.
$profileColumns = [
    "ADD COLUMN IF NOT EXISTS employee_code VARCHAR(40) NULL AFTER user_id",
    "ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER last_name",
    "ADD COLUMN IF NOT EXISTS gender VARCHAR(20) NULL AFTER date_of_birth",
    "ADD COLUMN IF NOT EXISTS marital_status VARCHAR(30) NULL AFTER gender",
    "ADD COLUMN IF NOT EXISTS blood_group VARCHAR(10) NULL AFTER marital_status",
    "ADD COLUMN IF NOT EXISTS address_line VARCHAR(255) NULL AFTER position",
    "ADD COLUMN IF NOT EXISTS door_no VARCHAR(40) NULL AFTER address_line",
    "ADD COLUMN IF NOT EXISTS street VARCHAR(120) NULL AFTER door_no",
    "ADD COLUMN IF NOT EXISTS locality VARCHAR(120) NULL AFTER street",
    "ADD COLUMN IF NOT EXISTS city VARCHAR(80) NULL AFTER locality",
    "ADD COLUMN IF NOT EXISTS state VARCHAR(80) NULL AFTER city",
    "ADD COLUMN IF NOT EXISTS pincode VARCHAR(20) NULL AFTER state",
    "ADD COLUMN IF NOT EXISTS join_date DATE NULL AFTER pincode",
    "ADD COLUMN IF NOT EXISTS employment_type VARCHAR(40) NULL AFTER join_date",
    "ADD COLUMN IF NOT EXISTS qualification VARCHAR(120) NULL AFTER employment_type",
    "ADD COLUMN IF NOT EXISTS specialization VARCHAR(120) NULL AFTER qualification",
    "ADD COLUMN IF NOT EXISTS years_experience DECIMAL(4,1) NULL AFTER specialization",
    "ADD COLUMN IF NOT EXISTS license_number VARCHAR(80) NULL AFTER years_experience",
    "ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(120) NULL AFTER license_number",
    "ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(25) NULL AFTER emergency_contact_name",
    "ADD COLUMN IF NOT EXISTS emergency_contact_relation VARCHAR(50) NULL AFTER emergency_contact_phone",
    "ADD COLUMN IF NOT EXISTS salary DECIMAL(12,2) NULL AFTER emergency_contact_relation",
    "ADD COLUMN IF NOT EXISTS bank_name VARCHAR(120) NULL AFTER salary",
    "ADD COLUMN IF NOT EXISTS bank_account_no VARCHAR(60) NULL AFTER bank_name",
    "ADD COLUMN IF NOT EXISTS ifsc_code VARCHAR(20) NULL AFTER bank_account_no",
];
foreach ($profileColumns as $alter) {
    $db->execute("ALTER TABLE employees {$alter}");
}
// Add dependent profile fields only after their anchor columns exist.
if (!$db->fetch("SHOW COLUMNS FROM employees LIKE 'aadhaar_number'")) {
    $db->execute("ALTER TABLE employees ADD COLUMN aadhaar_number VARCHAR(20) NULL");
}
if (!$db->fetch("SHOW COLUMNS FROM employees LIKE 'esi_number'")) {
    $db->execute("ALTER TABLE employees ADD COLUMN esi_number VARCHAR(40) NULL");
}
if (!$db->fetch("SHOW COLUMNS FROM employees LIKE 'pf_number'")) {
    $db->execute("ALTER TABLE employees ADD COLUMN pf_number VARCHAR(40) NULL");
}

$leaveColumns = [
    "ADD COLUMN IF NOT EXISTS permission_start_time TIME NULL AFTER reason",
    "ADD COLUMN IF NOT EXISTS permission_end_time TIME NULL AFTER permission_start_time",
];
foreach ($leaveColumns as $alter) {
    $db->execute("ALTER TABLE leave_requests {$alter}");
}

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_onboarding (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        start_date DATE NOT NULL,
        target_completion_date DATE NULL,
        status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
        orientation_done TINYINT(1) NOT NULL DEFAULT 0,
        documents_collected TINYINT(1) NOT NULL DEFAULT 0,
        assets_issued TINYINT(1) NOT NULL DEFAULT 0,
        training_assigned TINYINT(1) NOT NULL DEFAULT 0,
        mentor_name VARCHAR(120) NULL,
        notes TEXT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_onboarding_employee (employee_id),
        CONSTRAINT fk_onboarding_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_onboarding_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS training_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        description TEXT NULL,
        session_date DATE NOT NULL,
        trainer_name VARCHAR(120) NULL,
        location VARCHAR(120) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        CONSTRAINT fk_training_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_training (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        employee_id INT NOT NULL,
        status ENUM('Assigned','In Progress','Completed') NOT NULL DEFAULT 'Assigned',
        completion_date DATE NULL,
        score DECIMAL(5,2) NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_training_employee_session (session_id, employee_id),
        CONSTRAINT fk_emp_training_session FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
        CONSTRAINT fk_emp_training_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS payroll_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        payroll_month CHAR(7) NOT NULL,
        basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
        deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
        net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('Draft','Processed','Paid','Hold') NOT NULL DEFAULT 'Draft',
        payment_date DATE NULL,
        notes TEXT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_payroll_employee_month (employee_id, payroll_month),
        CONSTRAINT fk_payroll_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_payroll_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$payrollColumns = [
    "ADD COLUMN IF NOT EXISTS work_hours DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER payroll_month",
    "ADD COLUMN IF NOT EXISTS payable_days DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER work_hours",
    "ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payable_days",
];
foreach ($payrollColumns as $alter) {
    $db->execute("ALTER TABLE payroll_records {$alter}");
}

$db->execute(
    "CREATE TABLE IF NOT EXISTS allowance_deductions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        entry_type ENUM('Allowance','Deduction') NOT NULL,
        title VARCHAR(120) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        effective_month CHAR(7) NOT NULL,
        is_recurring TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_allowance_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_allowance_creator FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS hr_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE,
        head_employee_id INT NULL,
        location ENUM('KH','KCI','KNS','MANAGEMENT') NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_hr_department_head FOREIGN KEY (head_employee_id) REFERENCES employees(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$departmentMasterCount = (int)($db->fetch('SELECT COUNT(*) c FROM hr_departments')['c'] ?? 0);
if ($departmentMasterCount === 0) {
    $db->execute(
        "INSERT IGNORE INTO hr_departments(name, location, is_active, created_at)
         SELECT DISTINCT TRIM(department), NULL, 1, NOW()
         FROM employees
         WHERE department IS NOT NULL AND TRIM(department) <> ''"
    );
}

$db->execute(
    "CREATE TABLE IF NOT EXISTS hr_designations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(120) NOT NULL UNIQUE,
        department_name VARCHAR(120) NULL,
        grade VARCHAR(40) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$defaultDesignations = [
    ['Doctor', 'Medical', 'Clinical'],
    ['DMO', 'Medical', 'Clinical'],
    ['Staff Nurse', 'Nursing', 'Nursing'],
    ['Senior Staff Nurse', 'Nursing', 'Nursing'],
    ['Nursing Assistant', 'Nursing', 'Nursing'],
    ['Receptionist', 'Front Office', 'Administration'],
    ['Pharmacist', 'Pharmacy', 'Clinical Support'],
    ['Lab Technician', 'Laboratory', 'Clinical Support'],
    ['Radiology Technician', 'Radiology', 'Clinical Support'],
    ['CT Technician', 'Radiology', 'Clinical Support'],
    ['ECG Technician', 'Diagnostics', 'Clinical Support'],
    ['OT Technician', 'Operation Theatre', 'Clinical Support'],
    ['CSSD Technician', 'CSSD', 'Clinical Support'],
    ['Cath Lab Technician', 'Cathlab', 'Clinical Support'],
    ['Endoscopy Technician', 'Endoscopy', 'Clinical Support'],
    ['Physiotherapist', 'Physiotherapy', 'Clinical Support'],
    ['Physician Assistant', 'Medical', 'Clinical'],
    ['Dietitian', 'Diet', 'Support'],
    ['Accountant', 'Accounts', 'Administration'],
    ['HR Executive', 'Administration', 'Administration'],
    ['Administrative Officer', 'Administration', 'Administration'],
    ['Maintenance Technician', 'Maintenance', 'Support'],
    ['Electrician', 'Maintenance', 'Support'],
    ['Driver', 'Transport', 'Support'],
    ['Cook', 'Diet', 'Support'],
    ['Sanitary Worker', 'Housekeeping', 'Support'],
    ['Attender', 'Administration', 'Support'],
    ['Tutor', 'Training', 'Training'],
    ['Clinical Operations Head', 'Clinical Operations', 'Management'],
    ['Respiratory Therapist', 'RT', 'Clinical Support'],
];

foreach ($defaultDesignations as [$title, $departmentName, $grade]) {
    $db->execute(
        'INSERT IGNORE INTO hr_designations(title, department_name, grade, is_active, created_at) VALUES(:title, :department_name, :grade, 1, NOW())',
        ['title' => $title, 'department_name' => $departmentName, 'grade' => $grade]
    );
}

$db->execute(
    "CREATE TABLE IF NOT EXISTS hr_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL,
        CONSTRAINT fk_hr_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS audit_trail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(120) NOT NULL,
        entity_type VARCHAR(80) NOT NULL,
        entity_id INT NULL,
        details TEXT NULL,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$db->execute("ALTER TABLE audit_trail ADD COLUMN IF NOT EXISTS is_important TINYINT(1) NOT NULL DEFAULT 0 AFTER details");

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        document_type VARCHAR(120) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NULL,
        verification_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        employee_remarks TEXT NULL,
        hr_remarks TEXT NULL,
        verified_by INT NULL,
        verified_at DATETIME NULL,
        uploaded_at DATETIME NOT NULL,
        CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_employee_documents_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$db->execute("ALTER TABLE employee_documents ADD COLUMN IF NOT EXISTS stored_mime_type VARCHAR(120) NULL AFTER original_filename");
$db->execute("ALTER TABLE employee_documents ADD COLUMN IF NOT EXISTS file_size_bytes INT NULL AFTER stored_mime_type");

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_health_checkups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        checkup_date DATE NOT NULL,
        hospital_name VARCHAR(160) NOT NULL,
        report_file_path VARCHAR(255) NULL,
        original_filename VARCHAR(255) NULL,
        next_due_date DATE NULL,
        remarks TEXT NULL,
        uploaded_by INT NOT NULL,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_health_checkups_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_health_checkups_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$db->execute("ALTER TABLE employee_health_checkups ADD COLUMN IF NOT EXISTS stored_mime_type VARCHAR(120) NULL AFTER original_filename");
$db->execute("ALTER TABLE employee_health_checkups ADD COLUMN IF NOT EXISTS file_size_bytes INT NULL AFTER stored_mime_type");

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_attendance_corrections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        correction_date DATE NOT NULL,
        correction_time TIME NOT NULL,
        correction_type ENUM('Check In','Check Out','Status Correction','Other') NOT NULL DEFAULT 'Check In',
        justification TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        original_filename VARCHAR(255) NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        hr_remarks TEXT NULL,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_attendance_correction_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_attendance_correction_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS employee_internal_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        request_type ENUM('Suggestion','Query','Resignation','Feedback','Complaint') NOT NULL,
        to_role ENUM('Admin','HR','HOD') NOT NULL,
        subject VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        reply_message TEXT NULL,
        replied_by INT NULL,
        replied_at DATETIME NULL,
        status ENUM('Open','In Review','Completed') NOT NULL DEFAULT 'Open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        CONSTRAINT fk_internal_requests_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        CONSTRAINT fk_internal_requests_replier FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->execute(
    "INSERT IGNORE INTO hr_settings (setting_key, setting_value, updated_at) VALUES
    ('hospital_name', 'Hospital HR Module', NOW()),
    ('payroll_cycle_day', '25', NOW()),
    ('default_shift_start', '09:00', NOW()),
    ('default_shift_end', '17:00', NOW())"
);

$db->execute(
    "CREATE TABLE IF NOT EXISTS sidebar_menu_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_key VARCHAR(80) NOT NULL,
        label VARCHAR(120) NOT NULL,
        route VARCHAR(120) NOT NULL,
        section_name VARCHAR(60) NOT NULL,
        icon_label VARCHAR(8) NOT NULL,
        role ENUM('Admin','SuperAdmin','HR','HOD','Manager','Employee') NOT NULL,
        is_visible TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_sidebar_menu_role (menu_key, role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$sidebarMenus = [
    ['dashboard', 'Dashboard', 'dashboard', 'Main', 'D', 10, ['Admin','SuperAdmin','HR','HOD','Manager','Employee']],
    ['employee_directory', 'Employee Directory', 'recruitment', 'Main', 'E', 20, ['Admin','SuperAdmin','HR']],
    ['attendance', 'Attendance', 'attendance', 'Main', 'A', 30, ['Admin','SuperAdmin','HR']],
    ['employee_attendance', 'Attendance Report', 'employee-attendance', 'Self Service', 'AR', 35, ['Employee']],
    ['employee_duty_roster', 'Duty Roster', 'employee-duty-roster', 'Self Service', 'R', 36, ['Employee']],
    ['employee_previous_duty_roster', 'Previous Duty Roster', 'employee-duty-roster-previous', 'Self Service', 'PR', 37, ['Employee']],
    ['employee_attendance_correction', 'Attendance Correction', 'employee-attendance-correction', 'Self Service', 'TC', 38, ['Employee']],
    ['employee_certificates', 'Certificates', 'employee-certificates', 'Self Service', 'CT', 39, ['Employee']],
    ['duty_roster', 'Duty Roster', 'duty-roster', 'Main', 'R', 40, ['Admin','SuperAdmin','HR','HOD']],
    ['organisational_hierarchy', 'Org Hierarchy', 'organisational-hierarchy', 'Workflows', 'OH', 48, ['Admin','SuperAdmin','HR','Manager']],
    ['leave_requests', 'Leave Requests', 'leave', 'Leave', 'L', 50, ['Admin','SuperAdmin','HR','Employee']],
    ['leave_approvals', 'Leave Approvals', 'leave', 'Leave', 'P', 55, []],
    ['employee_leave', 'Leave Management', 'employee-leave-permission', 'Self Service', 'L', 38, ['Employee']],
    ['onboarding', 'Onboarding', 'onboarding', 'Recruitment', 'O', 70, ['Admin','SuperAdmin','HR']],
    ['employee_onboarding', 'Onboarding', 'employee-onboarding', 'Self Service', 'O', 40, ['Employee']],
    ['training', 'Training', 'training', 'Administration', 'T', 80, ['Admin','SuperAdmin','HR','Manager']],
    ['employee_training', 'Training/KPI', 'employee-training-kpi', 'Self Service', 'K', 40, ['Employee']],
    ['employee_profile', 'Profile', 'employee-profile', 'Self Service', 'P', 190, ['Employee']],
    ['payroll', 'Salary Processing', 'payroll', 'Payroll', 'S', 90, ['Admin','SuperAdmin','HR']],
    ['allowances', 'Allowances & Deductions', 'allowances', 'Payroll', 'A', 100, ['Admin','SuperAdmin','HR']],
    ['departments', 'Departments', 'departments', 'Administration', 'D', 110, ['Admin','SuperAdmin','HR','Manager']],
    ['designations', 'Designations', 'designations', 'Administration', 'G', 120, ['Admin','SuperAdmin','HR','Manager']],
    ['users', 'Users & Roles', 'users', 'Administration', 'U', 130, ['Admin','SuperAdmin']],
    ['menu_rbac', 'Menu RBAC', 'menu-rbac', 'Administration', 'M', 135, ['Admin','SuperAdmin','HR']],
    ['settings', 'Settings', 'settings', 'Administration', 'C', 140, ['Admin','SuperAdmin']],
    ['performance', 'HR Analytics', 'performance', 'Reports', 'K', 150, ['Admin','SuperAdmin','Manager']],
    ['performance_leaderboard', 'KPI Leaderboard', 'performance-leaderboard', 'Reports', 'S', 160, ['Admin','SuperAdmin','Manager']],
    ['exit', 'Exit Records', 'exit', 'Reports', 'X', 170, ['Admin','SuperAdmin','Manager']],
    ['reports', 'Reports', 'reports', 'Reports', 'R', 180, ['Admin','SuperAdmin','HR','Manager']],
    ['notifications', 'Noticeboard', 'notifications', 'Self Service', 'N', 45, ['Admin','SuperAdmin','HR','HOD','Manager','Employee']],
    ['audit', 'Audit Trail', 'audit', 'Reports', 'I', 200, ['Admin','SuperAdmin']],
];
$roles = ['Admin','SuperAdmin','HR','HOD','Manager','Employee'];
foreach ($sidebarMenus as $menu) {
    foreach ($roles as $role) {
        $db->execute(
            'INSERT IGNORE INTO sidebar_menu_permissions(menu_key,label,route,section_name,icon_label,role,is_visible,sort_order,updated_at) VALUES(:menu_key,:label,:route,:section_name,:icon_label,:role,:is_visible,:sort_order,NOW())',
            [
                'menu_key' => $menu[0],
                'label' => $menu[1],
                'route' => $menu[2],
                'section_name' => $menu[3],
                'icon_label' => $menu[4],
                'role' => $role,
                'is_visible' => in_array($role, $menu[6], true) ? 1 : 0,
                'sort_order' => $menu[5],
            ]
        );
    }
}

$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='leave_approvals'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='employee_attendance' AND role <> 'Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET label='Attendance Report', section_name='Self Service', icon_label='AR', route='employee-attendance', sort_order=35, is_visible=1, updated_at=NOW() WHERE menu_key='employee_attendance' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key IN ('employee_duty_roster','employee_previous_duty_roster','employee_attendance_correction','employee_certificates','employee_onboarding') AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET label='Attendance Correction', section_name='Self Service', icon_label='TC', route='employee-attendance-correction', sort_order=38, is_visible=1, updated_at=NOW() WHERE menu_key='employee_attendance_correction' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE role='Employee' AND menu_key IN ('employee_duty_roster','employee_previous_duty_roster','employee_attendance_correction','employee_certificates','employee_onboarding','leave_requests')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('employee_attendance_correction','employee_certificates') AND role <> 'Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('employee_leave','employee_onboarding','employee_training') AND role <> 'Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='performance_leaderboard' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET label='Noticeboard', section_name='Self Service', icon_label='N', route='notifications', sort_order=45, is_visible=1, updated_at=NOW() WHERE menu_key='notifications'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Self Service', sort_order=38, updated_at=NOW() WHERE menu_key='employee_leave' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Self Service', sort_order=39, updated_at=NOW() WHERE menu_key='employee_onboarding' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Self Service', sort_order=40, updated_at=NOW() WHERE menu_key='employee_training' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET route='employee-onboarding', updated_at=NOW() WHERE menu_key='employee_onboarding' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET route='employee-training-kpi', updated_at=NOW() WHERE menu_key='employee_training' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET label='Profile', section_name='Self Service', icon_label='P', route='employee-profile', sort_order=190, is_visible=1, updated_at=NOW() WHERE menu_key='employee_profile' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='employee_profile' AND role <> 'Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Main', sort_order=10, updated_at=NOW() WHERE menu_key='dashboard'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Workforce', sort_order=20, updated_at=NOW() WHERE menu_key='employee_directory'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Workforce', sort_order=30, updated_at=NOW() WHERE menu_key='onboarding'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Workforce', sort_order=40, updated_at=NOW() WHERE menu_key='training'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Attendance', sort_order=50, updated_at=NOW() WHERE menu_key='attendance'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Attendance', sort_order=60, updated_at=NOW() WHERE menu_key='duty_roster'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Leave', sort_order=70, updated_at=NOW() WHERE menu_key='leave_requests'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Payroll', sort_order=80, updated_at=NOW() WHERE menu_key='payroll'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Payroll', sort_order=90, updated_at=NOW() WHERE menu_key='allowances'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key IN ('payroll','allowances') AND role IN ('Admin','SuperAdmin','HR')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('payroll','allowances') AND role NOT IN ('Admin','SuperAdmin','HR')");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Administration', sort_order=100, updated_at=NOW() WHERE menu_key='settings'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Administration', sort_order=110, updated_at=NOW() WHERE menu_key='departments'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Administration', sort_order=120, updated_at=NOW() WHERE menu_key='designations'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Administration', sort_order=130, updated_at=NOW() WHERE menu_key='users'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Administration', sort_order=140, updated_at=NOW() WHERE menu_key='menu_rbac'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Reports', sort_order=150, updated_at=NOW() WHERE menu_key='reports'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Reports', sort_order=160, updated_at=NOW() WHERE menu_key='performance'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Reports', sort_order=170, updated_at=NOW() WHERE menu_key='performance_leaderboard'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Reports', sort_order=180, updated_at=NOW() WHERE menu_key='exit'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Reports', sort_order=190, updated_at=NOW() WHERE menu_key='audit'");
$db->execute("UPDATE sidebar_menu_permissions SET section_name='Self Service', sort_order=200, updated_at=NOW() WHERE menu_key='notifications' AND role <> 'Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key='departments' AND role IN ('Admin','SuperAdmin','HR','Manager')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key='designations' AND role IN ('Admin','SuperAdmin','HR','Manager')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('departments','designations','users','menu_rbac') AND role IN ('Admin','SuperAdmin')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key='duty_roster' AND role IN ('Admin','SuperAdmin','HR','HOD')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='duty_roster' AND role NOT IN ('Admin','SuperAdmin','HR','HOD')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key='leave_requests' AND role IN ('Admin','SuperAdmin','HR')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key='leave_requests' AND role NOT IN ('Admin','SuperAdmin','HR','Employee')");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=1, updated_at=NOW() WHERE menu_key='leave_requests' AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('onboarding','training') AND role='Employee'");
$db->execute("UPDATE sidebar_menu_permissions SET is_visible=0, updated_at=NOW() WHERE menu_key IN ('performance','performance_leaderboard','exit','audit') AND role IN ('Admin','SuperAdmin','Manager')");
    @touch($schemaMarker);
}

// Add the menu to installations whose older schema migration has already run.
$menuCatalogMarker = __DIR__ . '/../storage/.menu-rbac-catalog-complete';
if (!is_file($menuCatalogMarker) && $db->fetch("SHOW TABLES LIKE 'sidebar_menu_permissions'")) {
    foreach (['Admin','SuperAdmin','HR','HOD','Manager','Employee'] as $role) {
        $db->execute(
            'INSERT IGNORE INTO sidebar_menu_permissions(menu_key,label,route,section_name,icon_label,role,is_visible,sort_order,updated_at) VALUES(:menu_key,:label,:route,:section_name,:icon_label,:role,:is_visible,:sort_order,NOW())',
            [
                'menu_key' => 'organisational_hierarchy',
                'label' => 'Org Hierarchy',
                'route' => 'organisational-hierarchy',
                'section_name' => 'Workflows',
                'icon_label' => 'OH',
                'role' => $role,
                'is_visible' => in_array($role, ['Admin','SuperAdmin','HR','Manager'], true) ? 1 : 0,
                'sort_order' => 48,
            ]
        );
    }
    @touch($menuCatalogMarker);
}
