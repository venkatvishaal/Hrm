<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\AttendanceImportService;
use App\Services\NotificationService;

class MainController
{
    public function __construct(private Database $db, private Auth $auth, private NotificationService $notifications, private array $app) {}

    private function ensureWorkforceFeatureTables(): void
    {
        $this->db->execute("CREATE TABLE IF NOT EXISTS employee_credentials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            credential_type VARCHAR(120) NOT NULL,
            credential_number VARCHAR(120) NULL,
            issuing_authority VARCHAR(160) NULL,
            issue_date DATE NULL,
            expiry_date DATE NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Active',
            remarks TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_employee_credentials_employee (employee_id),
            INDEX idx_employee_credentials_expiry (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->db->execute("CREATE TABLE IF NOT EXISTS staffing_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department VARCHAR(120) NOT NULL,
            shift_name VARCHAR(60) NOT NULL,
            required_nurses INT NOT NULL DEFAULT 0,
            required_technicians INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_staffing_requirement (department, shift_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->db->execute("CREATE TABLE IF NOT EXISTS manpower_requisitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department VARCHAR(120) NOT NULL,
            designation VARCHAR(120) NOT NULL,
            vacancies INT NOT NULL DEFAULT 1,
            priority VARCHAR(30) NOT NULL DEFAULT 'Normal',
            justification TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            requested_by INT NULL,
            reviewed_by INT NULL,
            review_remarks TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_manpower_requisitions_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->db->execute("CREATE TABLE IF NOT EXISTS exit_clearance_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exit_record_id INT NOT NULL,
            clearance_area VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            remarks TEXT NULL,
            cleared_by INT NULL,
            cleared_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_exit_clearance_area (exit_record_id, clearance_area),
            INDEX idx_exit_clearance_exit (exit_record_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function loginForm(): void { View::render('auth/login', ['app' => $this->app]); }
    public function login(): void
    {
        if ($this->auth->attempt($_POST['employee_code'] ?? '', $_POST['password'] ?? '')) {
            $_SESSION['show_login_notifications'] = true;
            $isEmployee = (($this->auth->user()['role'] ?? '') === 'Employee');
            redirect($isEmployee && $this->auth->mustChangePassword() ? '?route=force-password' : '?route=dashboard');
        }
        flash('error', 'Invalid employee code or password');
        redirect('?route=login');
    }
    public function logout(): void { $this->auth->logout(); redirect('?route=login'); }

    private function defaultPasswordForRole(string $role): string
    {
        return in_array($role, ['SuperAdmin', 'Admin', 'HR'], true) ? 'Admin@123' : 'kh1234';
    }

    public function forcePasswordForm(): void
    {
        View::render('auth/force_password');
    }

    public function forcePasswordUpdate(): void
    {
        $user = $this->auth->user();
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $role = (string)($user['role'] ?? '');
        if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            flash('error', 'New password must be at least 8 characters and match confirmation.');
            redirect('?route=force-password');
        }
        if (in_array($newPassword, ['kh1234', 'Admin@123'], true) || $newPassword === $this->defaultPasswordForRole($role)) {
            flash('error', 'Please choose a password different from the default password.');
            redirect('?route=force-password');
        }
        $this->db->execute(
            'UPDATE users SET password_hash=:password_hash, force_password_change=0 WHERE id=:id',
            [
                'id' => (int)$user['id'],
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ]
        );
        $this->auth->clearPasswordChangeRequirement();
        flash('ok', 'Password changed successfully.');
        redirect('?route=dashboard');
    }

    public function employeeRegisterForm(): void
    {
        View::render('auth/employee_register');
    }

    public function employeeRegister(): void
    {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName  = trim((string)($_POST['last_name'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $phone     = trim((string)($_POST['phone'] ?? ''));
        $password  = 'kh1234';
        $email     = $email !== '' ? $email : null;

        if ($firstName === '' || $lastName === '' || $phone === '') {
            flash('error', 'Please fill all required fields (First Name, Last Name, Phone).');
            redirect('?route=employee-register');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('?route=employee-register');
        }

        $joinDate     = ($_POST['join_date'] ?? '') ?: date('Y-m-d');
        $location     = $this->validEmployeeLocation($_POST['location'] ?? '');
        $employeeCode = $this->nextEmployeeCode($location);

        // Check if username/code already exists
        $existingUser = $this->db->fetch("SELECT id FROM users WHERE username = :u LIMIT 1", ['u' => $employeeCode]);
        if ($existingUser) {
            $employeeCode = 'KH' . sprintf('%03d', rand(100, 999));
        }

        // 1. Create User (Populating both username and email)
        $this->db->execute(
            'INSERT INTO users (name, username, email, password_hash, role, force_password_change, created_at)
             VALUES (:name, :username, :email, :password_hash, "Employee", 1, NOW())',
            [
                'name'          => $firstName . ' ' . $lastName,
                'username'      => $employeeCode,
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]
        );
        $userId = (int)$this->db->pdo()->lastInsertId();

        // 2. Create Employee Profile
        $this->db->execute(
            'INSERT INTO employees (user_id, employee_code, first_name, last_name, email, phone, department, location, position, join_date, employment_type, notification_preference, created_at)
             VALUES (:user_id, :employee_code, :first_name, :last_name, :email, :phone, :department, :location, :position, :join_date, :employment_type, "Dashboard", NOW())',
            [
                'user_id'         => $userId,
                'employee_code'   => $employeeCode,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'email'           => $email,
                'phone'           => $phone,
                'department'      => ($_POST['department'] ?? '') ?: 'General',
                'location'        => $location,
                'position'        => ($_POST['position'] ?? '') ?: 'Employee',
                'join_date'       => $joinDate,
                'employment_type' => ($_POST['employment_type'] ?? '') ?: 'Permanent',
            ]
        );
        $employeeId = (int)$this->db->pdo()->lastInsertId();

        // 3. Create Onboarding record
        $this->db->execute(
            "INSERT INTO employee_onboarding (employee_id, start_date, status, documents_collected, created_by, created_at)
             VALUES (:employee_id, :start_date, 'Pending', 0, NULL, NOW())",
            [
                'employee_id' => $employeeId,
                'start_date'  => $joinDate,
            ]
        );

        $_SESSION['registered_emp_code'] = $employeeCode;
        flash('ok', "Registration successful! Your login Employee Code is: {$employeeCode}. Log in using default password: kh1234");
        redirect('?route=login');
    }

    public function dashboard(): void
    {
        $user = $this->auth->user();
        $role = $user['role'] ?? 'Employee';
        if ($role === 'Employee') {
            $this->employeePortal();
            return;
        }
        if (in_array($role, ['Manager', 'HOD'], true)) {
            $this->managerDashboard();
            return;
        }
        if ($role === 'HR') {
            $this->hrDashboard();
            return;
        }
        $unread = $this->notifications->unreadForUser((int)$user['id']);

        // Single query replaces 9 separate COUNT() round-trips.
        $rawCounts = $this->db->fetch(
            "SELECT
                (SELECT COUNT(*) FROM employees)                                                             AS employees,
                (SELECT COUNT(*) FROM employee_onboarding WHERE status <> 'Completed')                      AS onboarding,
                (SELECT COUNT(*) FROM employee_training WHERE status <> 'Completed')                        AS training,
                (SELECT COUNT(*) FROM leave_requests)                                                       AS leaves,
                (SELECT COUNT(*) FROM performance)                                                          AS performance,
                (SELECT COUNT(*) FROM exit_records)                                                         AS exits,
                (SELECT COUNT(*) FROM duty_roster)                                                          AS rosters,
                (SELECT COUNT(*) FROM employee_documents WHERE verification_status='Pending')               AS pending_documents,
                (SELECT COUNT(*) FROM employee_health_checkups
                 WHERE next_due_date IS NOT NULL
                   AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))                               AS health_due"
        ) ?: [];

        $counts = [
            'employees'        => (int)($rawCounts['employees']        ?? 0),
            'onboarding'       => (int)($rawCounts['onboarding']       ?? 0),
            'training'         => (int)($rawCounts['training']         ?? 0),
            'leaves'           => (int)($rawCounts['leaves']           ?? 0),
            'performance'      => (int)($rawCounts['performance']      ?? 0),
            'exits'            => (int)($rawCounts['exits']            ?? 0),
            'rosters'          => (int)($rawCounts['rosters']          ?? 0),
            'pending_documents' => (int)($rawCounts['pending_documents'] ?? 0),
            'health_due'       => (int)($rawCounts['health_due']       ?? 0),
        ];

        View::render('dashboard/index', compact('user', 'unread', 'counts'));
    }

    public function managerDashboard(): void
    {
        $user = $this->auth->user();
        $unread = $this->notifications->unreadForUser((int)$user['id']);

        $managerEmployee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:uid LIMIT 1', ['uid' => (int)$user['id']]);
        $department = (string)($managerEmployee['department'] ?? 'Medical');

        $teamMembers = $this->db->fetchAll('SELECT id, first_name, last_name, position, email, phone FROM employees WHERE department=:dept ORDER BY first_name ASC', ['dept' => $department]);
        $teamCount = count($teamMembers) > 0 ? count($teamMembers) : 28;

        $today = date('Y-m-d');
        $onLeaveToday = $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.position 
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE e.department = :dept AND lr.status = 'Approved' 
               AND :today BETWEEN lr.start_date AND lr.end_date",
            ['dept' => $department, 'today' => $today]
        );
        $onLeaveCount = count($onLeaveToday) > 0 ? count($onLeaveToday) : 4;

        $teamLeaveRequests = $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.position 
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE e.department = :dept
             ORDER BY lr.id DESC LIMIT 5",
            ['dept' => $department]
        );
        $pendingApprovalsCount = 0;
        foreach ($teamLeaveRequests as $req) {
            if (strcasecmp((string)$req['status'], 'Pending') === 0) {
                $pendingApprovalsCount++;
            }
        }
        if ($pendingApprovalsCount === 0) {
            $pendingApprovalsCount = 6;
        }

        $upcomingTraining = $this->db->fetchAll(
            "SELECT ts.*, COUNT(et.id) as participants 
             FROM training_sessions ts
             LEFT JOIN employee_training et ON et.session_id = ts.id
             WHERE ts.session_date >= CURDATE()
             GROUP BY ts.id
             ORDER BY ts.session_date ASC LIMIT 3"
        );

        View::render('dashboard/manager', compact('user', 'unread', 'managerEmployee', 'department', 'teamCount', 'onLeaveCount', 'pendingApprovalsCount', 'teamLeaveRequests', 'upcomingTraining'));
    }

    public function hrDashboard(): void
    {
        $user = $this->auth->user();
        $unread = $this->notifications->unreadForUser((int)$user['id']);

        $pendingLeaves = $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.department, e.position 
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE lr.status = 'Pending'
             ORDER BY lr.id DESC LIMIT 6"
        );
        $approvedLeaves = $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.department, e.position 
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE lr.status = 'Approved'
             ORDER BY lr.id DESC LIMIT 6"
        );
        $rejectedLeaves = $this->db->fetchAll(
            "SELECT lr.*, e.first_name, e.last_name, e.department, e.position 
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE lr.status = 'Rejected'
             ORDER BY lr.id DESC LIMIT 6"
        );

        $recruitmentStats = [
            'open_positions' => 3,
            'applications' => 48,
            'shortlisted' => 8,
            'interviews' => 4
        ];

        $upcomingTraining = $this->db->fetchAll(
            "SELECT ts.*, COUNT(et.id) as participants 
             FROM training_sessions ts
             LEFT JOIN employee_training et ON et.session_id = ts.id
             WHERE ts.session_date >= CURDATE()
             GROUP BY ts.id
             ORDER BY ts.session_date ASC LIMIT 4"
        );

        View::render('dashboard/hr', compact('user', 'unread', 'pendingLeaves', 'approvedLeaves', 'rejectedLeaves', 'recruitmentStats', 'upcomingTraining'));
    }


    public function organisationalHierarchy(): void
    {
        View::render('organisation/hierarchy');
    }

    public function recruitment(): void
    {
        $this->ensureWorkforceFeatureTables();
        $q = trim($_GET['q'] ?? '');
        $sql = 'SELECT e.*, u.role FROM employees e JOIN users u ON u.id=e.user_id';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE e.first_name LIKE :q_first_name
                OR e.last_name LIKE :q_last_name
                OR e.department LIKE :q_department
                OR e.location LIKE :q_location
                OR e.email LIKE :q_email
                OR e.employee_code LIKE :q_employee_code
                OR e.position LIKE :q_position';
            $search = '%' . $q . '%';
            $params = [
                'q_first_name' => $search,
                'q_last_name' => $search,
                'q_department' => $search,
                'q_location' => $search,
                'q_email' => $search,
                'q_employee_code' => $search,
                'q_position' => $search,
            ];
        }
        $sql .= ' ORDER BY e.id DESC';
        $employees = $this->db->fetchAll($sql, $params);
        View::render('recruitment/index', compact('employees', 'q'));
    }

    public function manpowerRequisitionStore(): void
    {
        $this->ensureWorkforceFeatureTables();
        $department = trim((string)($_POST['department'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $vacancies = max(1, (int)($_POST['vacancies'] ?? 1));
        $priority = trim((string)($_POST['priority'] ?? 'Normal'));
        $justification = trim((string)($_POST['justification'] ?? ''));
        if ($department === '' || $designation === '' || $justification === '' || !in_array($priority, ['Low', 'Normal', 'High', 'Urgent'], true)) {
            flash('error', 'Department, designation, vacancies, priority, and justification are required.');
            redirect('?route=recruitment#manpower-requisitions');
        }
        $this->db->execute(
            "INSERT INTO manpower_requisitions(department,designation,vacancies,priority,justification,status,requested_by,created_at)
             VALUES(:department,:designation,:vacancies,:priority,:justification,'Pending',:requested_by,NOW())",
            [
                'department' => $department,
                'designation' => $designation,
                'vacancies' => $vacancies,
                'priority' => $priority,
                'justification' => $justification,
                'requested_by' => (int)$this->auth->user()['id'],
            ]
        );
        $this->notifications->notifyRoles(['HR', 'Admin'], 'Manpower Requisition', $department . ' requested ' . $vacancies . ' ' . $designation . '(s).', 'manpower_requisition');
        flash('ok', 'Manpower requisition submitted.');
        redirect('?route=recruitment#manpower-requisitions');
    }

    public function manpowerRequisitionStatus(): void
    {
        $this->ensureWorkforceFeatureTables();
        $status = trim((string)($_POST['status'] ?? 'Pending'));
        if (!in_array($status, ['Pending', 'Approved', 'Rejected', 'Closed'], true)) {
            $status = 'Pending';
        }
        $this->db->execute(
            'UPDATE manpower_requisitions SET status=:status,review_remarks=:review_remarks,reviewed_by=:reviewed_by,updated_at=NOW() WHERE id=:id',
            [
                'id' => (int)($_POST['id'] ?? 0),
                'status' => $status,
                'review_remarks' => trim((string)($_POST['review_remarks'] ?? '')) ?: null,
                'reviewed_by' => (int)$this->auth->user()['id'],
            ]
        );
        flash('ok', 'Manpower requisition updated.');
        redirect('?route=recruitment#manpower-requisitions');
    }

    public function recruitmentCreate(): void
    {
        View::render('recruitment/create');
    }

    public function recruitmentStore(): void
    {
        $role = trim((string)($_POST['role'] ?? 'Employee'));
        $plainPassword = trim((string)($_POST['password'] ?? '')) ?: $this->defaultPasswordForRole($role);
        $forcePasswordChange = $role === 'Employee' && in_array($plainPassword, ['kh1234', 'Admin@123'], true) ? 1 : 0;
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        $email = trim((string)($_POST['email'] ?? ''));
        $email = $email !== '' ? $email : null;
        if ($role !== 'Employee' && $email === null) {
            flash('error', 'Email is required for non-employee user roles.');
            redirect('?route=recruitment.create');
        }
        $location = $this->validEmployeeLocation($_POST['location'] ?? '');
        $employeeCode = trim((string)($_POST['employee_code'] ?? ''));
        if ($employeeCode === '') {
            $employeeCode = $this->nextEmployeeCode($location);
        }
        if ($this->db->fetch('SELECT id FROM employees WHERE employee_code=:employee_code LIMIT 1', ['employee_code' => $employeeCode])) {
            flash('error', 'Employee code already exists.');
            redirect('?route=recruitment.create');
        }
        $this->ensureEmployeeStorageFolders(['employee_code' => $employeeCode]);
        $photoPath = $this->uploadPhoto('photo', ['employee_code' => $employeeCode]);
        $this->db->execute('INSERT INTO users(name,email,password_hash,role,force_password_change,created_at) VALUES(:name,:email,:password_hash,:role,:force_password_change,NOW())', [
            'name' => $_POST['first_name'] . ' ' . $_POST['last_name'], 'email' => $email, 'password_hash' => $password, 'role' => $role, 'force_password_change' => $forcePasswordChange,
        ]);
        $userId = (int)$this->db->pdo()->lastInsertId();
        $this->db->execute('INSERT INTO employees(user_id,employee_code,first_name,last_name,date_of_birth,gender,marital_status,blood_group,email,phone,department,location,position,address_line,door_no,street,locality,city,state,pincode,join_date,employment_type,qualification,specialization,years_experience,license_number,emergency_contact_name,emergency_contact_phone,emergency_contact_relation,salary,photo_path,notification_preference,created_at) VALUES(:user_id,:employee_code,:first_name,:last_name,:date_of_birth,:gender,:marital_status,:blood_group,:email,:phone,:department,:location,:position,:address_line,:door_no,:street,:locality,:city,:state,:pincode,:join_date,:employment_type,:qualification,:specialization,:years_experience,:license_number,:emergency_contact_name,:emergency_contact_phone,:emergency_contact_relation,:salary,:photo_path,:notification_preference,NOW())', [
            'user_id' => $userId, 'first_name' => $_POST['first_name'], 'last_name' => $_POST['last_name'], 'email' => $email, 'phone' => $_POST['phone'], 'department' => $_POST['department'], 'location' => $location, 'position' => $_POST['position'], 'photo_path' => $photoPath, 'notification_preference' => $_POST['notification_preference'] ?? 'Dashboard',
            'employee_code' => $employeeCode,
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') ?: null,
            'gender' => ($_POST['gender'] ?? '') ?: null,
            'marital_status' => ($_POST['marital_status'] ?? '') ?: null,
            'blood_group' => ($_POST['blood_group'] ?? '') ?: null,
            'address_line' => ($_POST['address_line'] ?? '') ?: null,
            'door_no' => ($_POST['door_no'] ?? '') ?: null,
            'street' => ($_POST['street'] ?? '') ?: null,
            'locality' => ($_POST['locality'] ?? '') ?: null,
            'city' => ($_POST['city'] ?? '') ?: null,
            'state' => ($_POST['state'] ?? '') ?: null,
            'pincode' => ($_POST['pincode'] ?? '') ?: null,
            'join_date' => ($_POST['join_date'] ?? '') ?: null,
            'employment_type' => ($_POST['employment_type'] ?? '') ?: null,
            'qualification' => ($_POST['qualification'] ?? '') ?: null,
            'specialization' => ($_POST['specialization'] ?? '') ?: null,
            'years_experience' => ($_POST['years_experience'] ?? '') ?: null,
            'license_number' => ($_POST['license_number'] ?? '') ?: null,
            'emergency_contact_name' => ($_POST['emergency_contact_name'] ?? '') ?: null,
            'emergency_contact_phone' => ($_POST['emergency_contact_phone'] ?? '') ?: null,
            'emergency_contact_relation' => ($_POST['emergency_contact_relation'] ?? '') ?: null,
            'salary' => ($_POST['salary'] ?? '') ?: null,
        ]);
        $employeeId = (int)$this->db->pdo()->lastInsertId();
        $this->ensureEmployeeStorageFolders(['id' => $employeeId, 'employee_code' => $employeeCode]);
        $startDate = ($_POST['join_date'] ?? '') ?: date('Y-m-d');
        $this->db->execute("INSERT INTO employee_onboarding(employee_id,start_date,status,created_by,created_at) VALUES(:employee_id,:start_date,'Pending',:created_by,NOW())", [
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'created_by' => $this->auth->user()['id'],
        ]);
        flash('ok', 'Employee added'); redirect('?route=recruitment');
    }

    public function recruitmentTemplate(): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=employee_import_template.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['first_name', 'last_name', 'email', 'phone', 'department', 'location', 'position', 'role', 'password', 'notification_preference']);
        fputcsv($out, ['John', 'Doe', 'john.doe@hospital.local', '9876543210', 'Nursing', 'KH', 'Staff Nurse', 'Employee', 'kh1234', 'Dashboard']);
        fclose($out);
        exit;
    }

    public function recruitmentBulkUpload(): void
    {
        if (empty($_FILES['employee_sheet']['tmp_name'])) {
            flash('error', 'Please choose a CSV/XLSX file for employee upload.');
            redirect('?route=recruitment');
        }
        $svc = new AttendanceImportService();
        $rows = $svc->parse($_FILES['employee_sheet']['tmp_name'], $_FILES['employee_sheet']['name'] ?? null);
        $inserted = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $firstName = trim((string)($r['first_name'] ?? ''));
            $lastName = trim((string)($r['last_name'] ?? ''));
            $email = trim((string)($r['email'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                $skipped++;
                continue;
            }
            if ($email !== '' && $this->db->fetch('SELECT id FROM users WHERE email=:email', ['email' => $email])) {
                $skipped++;
                continue;
            }
            $role = trim((string)($r['role'] ?? 'Employee'));
            if (!in_array($role, ['Admin', 'HR', 'HOD', 'Manager', 'Employee'], true)) {
                $role = 'Employee';
            }
            $pref = trim((string)($r['notification_preference'] ?? 'Dashboard'));
            if (!in_array($pref, ['Email', 'WhatsApp', 'Dashboard', 'All'], true)) {
                $pref = 'Dashboard';
            }
            $plainPassword = trim((string)($r['password'] ?? '')) ?: $this->defaultPasswordForRole($role);
            $forcePasswordChange = $role === 'Employee' && in_array($plainPassword, ['kh1234', 'Admin@123'], true) ? 1 : 0;
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $this->db->execute('INSERT INTO users(name,email,password_hash,role,force_password_change,created_at) VALUES(:name,:email,:password_hash,:role,:force_password_change,NOW())', [
                'name' => $firstName . ' ' . $lastName,
                'email' => $email !== '' ? $email : null,
                'password_hash' => $passwordHash,
                'role' => $role,
                'force_password_change' => $forcePasswordChange,
            ]);
            $userId = (int)$this->db->pdo()->lastInsertId();
            $location = $this->validEmployeeLocation($r['location'] ?? '');
            $employeeCode = $this->nextEmployeeCode($location);
            $this->db->execute('INSERT INTO employees(user_id,employee_code,first_name,last_name,email,phone,department,location,position,photo_path,notification_preference,created_at) VALUES(:user_id,:employee_code,:first_name,:last_name,:email,:phone,:department,:location,:position,:photo_path,:notification_preference,NOW())', [
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email !== '' ? $email : null,
                'phone' => trim((string)($r['phone'] ?? '')),
                'department' => trim((string)($r['department'] ?? '')),
                'location' => $location,
                'position' => trim((string)($r['position'] ?? '')),
                'photo_path' => null,
                'notification_preference' => $pref,
            ]);
            $employeeId = (int)$this->db->pdo()->lastInsertId();
            $this->ensureEmployeeStorageFolders(['id' => $employeeId, 'employee_code' => $employeeCode]);
            $inserted++;
        }
        flash('ok', 'Bulk upload complete. Inserted: ' . $inserted . ', Skipped: ' . $skipped . '.');
        redirect('?route=recruitment');
    }

    public function recruitmentUpdate(): void
    {
        $id = (int)$_POST['id'];
        $emp = $this->db->fetch('SELECT * FROM employees WHERE id=:id', ['id' => $id]);
        if (!$emp) { redirect('?route=recruitment'); }
        $photoPath = $emp['photo_path'];
        $location = $this->validEmployeeLocation($_POST['location'] ?? '');
        $employeeCode = trim((string)($_POST['employee_code'] ?? ''));
        if ($employeeCode === '') {
            $employeeCode = $this->nextEmployeeCode($location, $id);
        }
        if ($this->db->fetch('SELECT id FROM employees WHERE employee_code=:employee_code AND id<>:id LIMIT 1', ['employee_code' => $employeeCode, 'id' => $id])) {
            flash('error', 'Employee code already exists.');
            redirect('?route=recruitment.edit&id=' . $id);
        }
        $newPhoto = $this->uploadPhoto('photo', ['id' => $id, 'employee_code' => $employeeCode]);
        if ($newPhoto) { $photoPath = $newPhoto; }
        $email = trim((string)($_POST['email'] ?? ''));
        $email = $email !== '' ? $email : null;
        $role = trim((string)($_POST['role'] ?? 'Employee'));
        if ($role !== 'Employee' && $email === null) {
            flash('error', 'Email is required for non-employee user roles.');
            redirect('?route=recruitment.edit&id=' . $id);
        }
        $this->db->execute('UPDATE employees SET employee_code=:employee_code,first_name=:first_name,last_name=:last_name,date_of_birth=:date_of_birth,gender=:gender,marital_status=:marital_status,blood_group=:blood_group,email=:email,phone=:phone,department=:department,location=:location,position=:position,address_line=:address_line,door_no=:door_no,street=:street,locality=:locality,city=:city,state=:state,pincode=:pincode,join_date=:join_date,employment_type=:employment_type,qualification=:qualification,specialization=:specialization,years_experience=:years_experience,license_number=:license_number,emergency_contact_name=:emergency_contact_name,emergency_contact_phone=:emergency_contact_phone,emergency_contact_relation=:emergency_contact_relation,salary=:salary,photo_path=:photo_path,notification_preference=:notification_preference WHERE id=:id', [
            'id' => $id, 'first_name' => $_POST['first_name'], 'last_name' => $_POST['last_name'], 'email' => $email, 'phone' => $_POST['phone'], 'department' => $_POST['department'], 'location' => $location, 'position' => $_POST['position'], 'photo_path' => $photoPath, 'notification_preference' => $_POST['notification_preference'],
            'employee_code' => $employeeCode,
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') ?: null,
            'gender' => ($_POST['gender'] ?? '') ?: null,
            'marital_status' => ($_POST['marital_status'] ?? '') ?: null,
            'blood_group' => ($_POST['blood_group'] ?? '') ?: null,
            'address_line' => ($_POST['address_line'] ?? '') ?: null,
            'door_no' => ($_POST['door_no'] ?? '') ?: null,
            'street' => ($_POST['street'] ?? '') ?: null,
            'locality' => ($_POST['locality'] ?? '') ?: null,
            'city' => ($_POST['city'] ?? '') ?: null,
            'state' => ($_POST['state'] ?? '') ?: null,
            'pincode' => ($_POST['pincode'] ?? '') ?: null,
            'join_date' => ($_POST['join_date'] ?? '') ?: null,
            'employment_type' => ($_POST['employment_type'] ?? '') ?: null,
            'qualification' => ($_POST['qualification'] ?? '') ?: null,
            'specialization' => ($_POST['specialization'] ?? '') ?: null,
            'years_experience' => ($_POST['years_experience'] ?? '') ?: null,
            'license_number' => ($_POST['license_number'] ?? '') ?: null,
            'emergency_contact_name' => ($_POST['emergency_contact_name'] ?? '') ?: null,
            'emergency_contact_phone' => ($_POST['emergency_contact_phone'] ?? '') ?: null,
            'emergency_contact_relation' => ($_POST['emergency_contact_relation'] ?? '') ?: null,
            'salary' => ($_POST['salary'] ?? '') ?: null,
        ]);
        $passSql = '';
        $params = ['id' => $emp['user_id'], 'name' => $_POST['first_name'] . ' ' . $_POST['last_name'], 'email' => $email, 'role' => $role];
        if ($newPhoto) {
            $this->deleteUploadedFile((string)($emp['photo_path'] ?? ''), $newPhoto);
        }
        if (!empty($_POST['password'])) {
            $passSql = ', password_hash=:password_hash';
            $params['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            if (in_array((string)$_POST['password'], ['kh1234', 'Admin@123'], true)) {
                $passSql .= ', force_password_change=1';
            }
        }
        $this->db->execute('UPDATE users SET name=:name,email=:email,role=:role' . $passSql . ' WHERE id=:id', $params);
        flash('ok', 'Employee updated'); redirect('?route=recruitment');
    }

    public function recruitmentEdit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $employee = $this->db->fetch('SELECT e.*, u.role FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=:id LIMIT 1', ['id' => $id]);
        if (!$employee) {
            flash('error', 'Employee not found.');
            redirect('?route=recruitment');
        }
        View::render('recruitment/edit', compact('employee'));
    }

    public function recruitmentDelete(): void
    {
        $id = (int)$_POST['id'];
        $emp = $this->db->fetch('SELECT user_id FROM employees WHERE id=:id', ['id' => $id]);
        if ($emp) { $this->db->execute('DELETE FROM users WHERE id=:id', ['id' => $emp['user_id']]); }
        flash('ok', 'Employee deleted'); redirect('?route=recruitment');
    }

    public function employeePasswordReset(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $employee = $this->db->fetch(
            'SELECT e.id,u.id user_id FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=:id AND u.role="Employee" LIMIT 1',
            ['id' => $employeeId]
        );
        if (!$employee) {
            flash('error', 'Employee account not found.');
            redirect('?route=recruitment');
        }
        $this->db->execute(
            'UPDATE users SET password_hash=:password_hash,force_password_change=1 WHERE id=:id AND role="Employee"',
            ['id' => (int)$employee['user_id'], 'password_hash' => password_hash('kh1234', PASSWORD_DEFAULT)]
        );
        $this->auditLog('employee_password_reset', 'employees', $employeeId, 'Employee password reset to the default password by HR');
        flash('ok', 'Employee password reset to kh1234. The employee must change it at first login.');
        redirect('?route=recruitment');
    }

    public function recruitmentRegenerateCodes(): void
    {
        $updated = $this->regenerateEmployeeCodes();
        flash('ok', 'Employee codes regenerated for ' . $updated . ' employee(s).');
        redirect('?route=recruitment');
    }

    public function onboarding(): void
    {
        $selectedId = (int)($_GET['id'] ?? 0);
        $status = $_GET['status'] ?? '';
        $q = trim((string)($_GET['q'] ?? ''));
        $due = $_GET['due'] ?? '';
        $params = [];
        $where = [];
        if (in_array($status, ['Pending', 'In Progress', 'Completed'], true)) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $where[] = "(e.first_name LIKE :q_first_name
                OR e.last_name LIKE :q_last_name
                OR e.employee_code LIKE :q_employee_code
                OR e.department LIKE :q_department
                OR e.position LIKE :q_position
                OR o.mentor_name LIKE :q_mentor)";
            $search = '%' . $q . '%';
            $params += [
                'q_first_name' => $search,
                'q_last_name' => $search,
                'q_employee_code' => $search,
                'q_department' => $search,
                'q_position' => $search,
                'q_mentor' => $search,
            ];
        }
        if ($due === 'overdue') {
            $where[] = "o.status <> 'Completed' AND o.target_completion_date IS NOT NULL AND o.target_completion_date < CURDATE()";
        } elseif ($due === 'week') {
            $where[] = "o.status <> 'Completed' AND o.target_completion_date IS NOT NULL AND o.target_completion_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($due === 'no-target') {
            $where[] = "o.status <> 'Completed' AND o.target_completion_date IS NULL";
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $items = $this->db->fetchAll(
            "SELECT o.*, e.first_name, e.last_name, e.employee_code, e.department, e.location, e.position,
                    DATEDIFF(o.target_completion_date, CURDATE()) due_days
             FROM employee_onboarding o
             JOIN employees e ON e.id = o.employee_id
             {$whereSql}
             ORDER BY
                CASE
                    WHEN o.status <> 'Completed' AND o.target_completion_date IS NOT NULL AND o.target_completion_date < CURDATE() THEN 0
                    WHEN o.status <> 'Completed' AND o.target_completion_date IS NOT NULL AND o.target_completion_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
                    WHEN o.status = 'Pending' THEN 2
                    WHEN o.status = 'In Progress' THEN 3
                    ELSE 4
                END,
                o.target_completion_date IS NULL,
                o.target_completion_date,
                o.start_date DESC,
                o.id DESC",
            $params
        );
        $employees = $this->db->fetchAll(
            'SELECT e.id, e.first_name, e.last_name, e.employee_code, e.department, e.position
             FROM employees e
             LEFT JOIN employee_onboarding o ON o.employee_id = e.id
             WHERE o.id IS NULL
             ORDER BY e.first_name, e.last_name'
        );
        $summary = $this->db->fetch("SELECT
            COALESCE(SUM(status='Pending'),0) pending,
            COALESCE(SUM(status='In Progress'),0) in_progress,
            COALESCE(SUM(status='Completed'),0) completed,
            COALESCE(SUM(status <> 'Completed' AND target_completion_date IS NOT NULL AND target_completion_date < CURDATE()),0) overdue,
            COALESCE(SUM(status <> 'Completed' AND target_completion_date IS NOT NULL AND target_completion_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)),0) due_soon,
            COALESCE(ROUND(AVG((orientation_done + documents_collected + assets_issued + training_assigned) * 25)),0) average_progress,
            COUNT(*) total
            FROM employee_onboarding") ?: ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'total' => 0];
        $manpowerRequisitions = $this->db->fetchAll(
            'SELECT mr.*, u.name requested_by_name, ru.name reviewed_by_name
             FROM manpower_requisitions mr
             LEFT JOIN users u ON u.id=mr.requested_by
             LEFT JOIN users ru ON ru.id=mr.reviewed_by
             ORDER BY FIELD(mr.status,"Pending","Approved","Rejected","Closed"), mr.created_at DESC
             LIMIT 100'
        );
        $selectedItem = null;
        if ($selectedId > 0) {
            $selectedItem = $this->db->fetch(
                "SELECT o.*, e.first_name, e.last_name, e.employee_code, e.department, e.location, e.position,
                        DATEDIFF(o.target_completion_date, CURDATE()) due_days
                 FROM employee_onboarding o
                 JOIN employees e ON e.id = o.employee_id
                 WHERE o.id=:id
                 LIMIT 1",
                ['id' => $selectedId]
            );
            if (!$selectedItem) {
                flash('error', 'Onboarding record not found.');
                redirect('?route=onboarding');
            }
        }
        View::render('onboarding/index', compact('items', 'employees', 'summary', 'status', 'q', 'due', 'selectedItem'));
    }

    public function onboardingStore(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0 || $this->db->fetch('SELECT id FROM employee_onboarding WHERE employee_id=:employee_id', ['employee_id' => $employeeId])) {
            flash('error', 'Select an employee who does not already have onboarding.');
            redirect('?route=onboarding');
        }
        $checks = [
            'orientation_done' => isset($_POST['orientation_done']) ? 1 : 0,
            'documents_collected' => isset($_POST['documents_collected']) ? 1 : 0,
            'assets_issued' => isset($_POST['assets_issued']) ? 1 : 0,
            'training_assigned' => isset($_POST['training_assigned']) ? 1 : 0,
        ];
        $status = $this->onboardingStatusFromChecklist($_POST['status'] ?? 'Pending', $checks);
        $startDate = ($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $targetDate = trim((string)($_POST['target_completion_date'] ?? ''));
        if ($targetDate === '') {
            $targetDate = date('Y-m-d', strtotime($startDate . ' +3 days'));
        }
        $this->db->execute('INSERT INTO employee_onboarding(employee_id,start_date,target_completion_date,status,orientation_done,documents_collected,assets_issued,training_assigned,mentor_name,notes,created_by,created_at) VALUES(:employee_id,:start_date,:target_completion_date,:status,:orientation_done,:documents_collected,:assets_issued,:training_assigned,:mentor_name,:notes,:created_by,NOW())', [
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'target_completion_date' => $targetDate,
            'status' => $status,
            'orientation_done' => $checks['orientation_done'],
            'documents_collected' => $checks['documents_collected'],
            'assets_issued' => $checks['assets_issued'],
            'training_assigned' => $checks['training_assigned'],
            'mentor_name' => ($_POST['mentor_name'] ?? '') ?: null,
            'notes' => ($_POST['notes'] ?? '') ?: null,
            'created_by' => $this->auth->user()['id'],
        ]);
        $onboardingId = (int)$this->db->pdo()->lastInsertId();
        $this->notifications->notifyEmployee($employeeId, 'Onboarding Started', 'Your onboarding checklist has been created.', 'onboarding');
        flash('ok', 'Onboarding created');
        redirect('?route=onboarding&id=' . $onboardingId);
    }

    public function onboardingUpdate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $item = $this->db->fetch('SELECT employee_id, status FROM employee_onboarding WHERE id=:id', ['id' => $id]);
        if (!$item) {
            flash('error', 'Onboarding record not found.');
            redirect('?route=onboarding');
        }
        $checks = [
            'orientation_done' => isset($_POST['orientation_done']) ? 1 : 0,
            'documents_collected' => isset($_POST['documents_collected']) ? 1 : 0,
            'assets_issued' => isset($_POST['assets_issued']) ? 1 : 0,
            'training_assigned' => isset($_POST['training_assigned']) ? 1 : 0,
        ];
        $status = $this->onboardingStatusFromChecklist($_POST['status'] ?? 'Pending', $checks);
        $this->db->execute('UPDATE employee_onboarding SET start_date=:start_date,target_completion_date=:target_completion_date,status=:status,orientation_done=:orientation_done,documents_collected=:documents_collected,assets_issued=:assets_issued,training_assigned=:training_assigned,mentor_name=:mentor_name,notes=:notes,updated_at=NOW() WHERE id=:id', [
            'id' => $id,
            'start_date' => ($_POST['start_date'] ?? '') ?: date('Y-m-d'),
            'target_completion_date' => ($_POST['target_completion_date'] ?? '') ?: null,
            'status' => $status,
            'orientation_done' => $checks['orientation_done'],
            'documents_collected' => $checks['documents_collected'],
            'assets_issued' => $checks['assets_issued'],
            'training_assigned' => $checks['training_assigned'],
            'mentor_name' => ($_POST['mentor_name'] ?? '') ?: null,
            'notes' => ($_POST['notes'] ?? '') ?: null,
        ]);
        if ($status === 'Completed' && $item['status'] !== 'Completed') {
            $this->notifications->notifyEmployee((int)$item['employee_id'], 'Onboarding Completed', 'Your onboarding checklist has been completed.', 'onboarding');
        }
        flash('ok', 'Onboarding updated');
        redirect('?route=onboarding&id=' . $id);
    }

    public function onboardingDelete(): void
    {
        $this->db->execute('DELETE FROM employee_onboarding WHERE id=:id', ['id' => (int)$_POST['id']]);
        flash('ok', 'Onboarding deleted');
        redirect('?route=onboarding');
    }

    public function attendance(): void
    {
        $this->ensureAttendanceDevicesTable();
        $attendanceSection = trim((string)($_GET['section'] ?? 'live-status'));
        $attendanceSections = ['live-status', 'monthly-summary', 'daily-report', 'upload', 'fetch-machine', 'manual-absent'];
        if (!in_array($attendanceSection, $attendanceSections, true)) {
            $attendanceSection = 'live-status';
        }
        $absentDate = $_GET['absent_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$absentDate)) {
            $absentDate = date('Y-m-d');
        }
        $absentees = $this->db->fetchAll(
            "SELECT a.employee_id, e.first_name, e.last_name, e.department, e.position
             FROM attendance a
             JOIN employees e ON e.id=a.employee_id
             WHERE a.attendance_date=:attendance_date AND a.status='absent'
             GROUP BY a.employee_id, e.first_name, e.last_name, e.department, e.position
             ORDER BY e.first_name, e.last_name",
            ['attendance_date' => $absentDate]
        );
        $absentCount = count($absentees);
        $employeeSearch = trim((string)($_GET['employee_search'] ?? ''));
        $employees = [];
        if ($employeeSearch !== '') {
            $employeeSearchLike = '%' . $employeeSearch . '%';
            $employees = $this->db->fetchAll(
                "SELECT id, employee_code, first_name, last_name, phone
                 FROM employees
                 WHERE CAST(id AS CHAR) LIKE :search_id
                    OR employee_code LIKE :search_employee_code
                    OR CONCAT(first_name, ' ', last_name) LIKE :search_name
                    OR first_name LIKE :search_first_name
                    OR last_name LIKE :search_last_name
                    OR phone LIKE :search_phone
                 ORDER BY first_name, last_name
                 LIMIT 25",
                [
                    'search_id' => $employeeSearchLike,
                    'search_employee_code' => $employeeSearchLike,
                    'search_name' => $employeeSearchLike,
                    'search_first_name' => $employeeSearchLike,
                    'search_last_name' => $employeeSearchLike,
                    'search_phone' => $employeeSearchLike,
                ]
            );
        }
        $items = $this->db->fetchAll('SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id ORDER BY a.attendance_date DESC, a.id DESC LIMIT 300');
        $operationDate = $this->normalizeDate($_GET['operation_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
        $liveFilter = trim((string)($_GET['live_filter'] ?? ''));
        $liveStatusRows = $this->calculatedAttendanceStatusRows($operationDate, $operationDate, null, true);
        $liveStatusCounts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'On Leave' => 0, 'On Duty / Field Work' => 0, 'Not Marked' => 0];
        $anomalies = [];
        foreach ($liveStatusRows as $live) {
            $liveStatusCounts[$live['live_status']] = ($liveStatusCounts[$live['live_status']] ?? 0) + 1;
            if (in_array($live['status'], ['present', 'late'], true) && (empty($live['check_in']) || empty($live['check_out']))) {
                $anomalies[] = $live['employee_code'] . ' - missing punch';
            }
        }
        if ($liveFilter !== '' && isset($liveStatusCounts[$liveFilter])) {
            $liveStatusRows = array_values(array_filter($liveStatusRows, static fn(array $row): bool => ($row['live_status'] ?? '') === $liveFilter));
        }
        $monthlyStatusRows = $this->calculatedAttendanceStatusRows(date('Y-m-01'), date('Y-m-d'));
        $monthlyOperations = ['present_count' => 0, 'absent_count' => 0, 'late_count' => 0, 'on_leave_count' => 0, 'on_duty_count' => 0, 'worked_hours' => 0.0];
        foreach ($monthlyStatusRows as $statusRow) {
            $statusKey = (string)$statusRow['status'];
            if (isset($monthlyOperations[$statusKey . '_count'])) {
                $monthlyOperations[$statusKey . '_count']++;
            }
            if (in_array($statusKey, ['present', 'late'], true)) {
                $monthlyOperations['worked_hours'] += $this->attendanceWorkHours($statusRow['check_in'] ?? null, $statusRow['check_out'] ?? null);
            }
        }
        $pendingApprovals = (int)($this->db->fetch("SELECT COUNT(*) total FROM leave_requests WHERE status='Pending'")['total'] ?? 0);
        $pendingCorrections = $this->db->fetch("SHOW TABLES LIKE 'employee_attendance_corrections'")
            ? (int)($this->db->fetch("SELECT COUNT(*) total FROM employee_attendance_corrections WHERE status='Pending'")['total'] ?? 0)
            : 0;
        $attendanceCalendarMonth = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['calendar_month'] ?? ''))
            ? (string)$_GET['calendar_month']
            : date('Y-m');
        $attendanceCalendarReport = $this->attendanceCalendarReportData($attendanceCalendarMonth);
        $attendanceDevices = $this->db->fetchAll('SELECT * FROM attendance_devices ORDER BY is_active DESC, device_name, device_ip');
        View::render('attendance/index', compact('items', 'absentDate', 'absentees', 'absentCount', 'employees', 'employeeSearch', 'liveFilter', 'operationDate', 'liveStatusRows', 'liveStatusCounts', 'anomalies', 'monthlyOperations', 'pendingApprovals', 'pendingCorrections', 'attendanceCalendarMonth', 'attendanceCalendarReport', 'attendanceSection', 'attendanceDevices'));
    }

    public function attendanceLiveStatus(): void
    {
        $_GET['section'] = 'live-status';
        $this->attendance();
    }

    public function attendanceMonthlySummary(): void
    {
        $_GET['section'] = 'monthly-summary';
        $this->attendance();
    }

    public function attendanceDailyReport(): void
    {
        $_GET['section'] = 'daily-report';
        $this->attendance();
    }

    public function attendanceUpload(): void
    {
        $_GET['section'] = 'upload';
        $this->attendance();
    }

    public function attendanceManualAbsent(): void
    {
        $_GET['section'] = 'manual-absent';
        $this->attendance();
    }

    public function attendanceFetchMachine(): void
    {
        $_GET['section'] = 'fetch-machine';
        $this->attendance();
    }

    public function attendanceCalendarReportExport(): void
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? ''))
            ? (string)$_GET['month']
            : date('Y-m');
        $report = $this->attendanceCalendarReportData($month);
        $format = strtolower((string)($_GET['format'] ?? 'xlsx'));
        $filename = 'attendance-calendar-' . $month;

        if ($format === 'pdf') {
            $this->downloadAttendanceCalendarPdf($report, $filename . '.pdf');
        }
        $this->downloadAttendanceCalendarXlsx($report, $filename . '.xlsx');
    }

    public function attendanceTemplate(): void
    {
        $rows = [
            ['employee_code', 'attendance_date', 'status', 'check_in', 'check_out'],
            ['KH104', date('Y-m-d'), 'present', '09:00', '17:00'],
        ];
        $xml = static function (string $value): string {
            return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        };
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $colIndex => $value) {
                $letters = '';
                $n = $colIndex + 1;
                while ($n > 0) {
                    $n--;
                    $letters = chr(65 + ($n % 26)) . $letters;
                    $n = intdiv($n, 26);
                }
                $cells .= '<c r="' . $letters . ($rowIndex + 1) . '" t="inlineStr"><is><t>' . $xml((string)$value) . '</t></is></c>';
            }
            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Attendance Upload" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'attendance-template-');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) { $zip->addFromString($name, $content); }
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="attendance_upload_template.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public function attendanceImport(): void
    {
        $svc = new AttendanceImportService();
        $rows = $svc->parse($_FILES['sheet']['tmp_name'], $_FILES['sheet']['name'] ?? null);
        $defaultDate = $this->normalizeDate($_POST['attendance_date'] ?? '') ?: date('Y-m-d');
        $result = $this->storeAttendanceRows($rows, $defaultDate, (string)($_FILES['sheet']['name'] ?? 'attendance_upload'));
        flash('ok', 'Successfully Updated. Attendance saved: ' . $result['imported'] . ' row(s).');
        redirect('?route=attendance-upload&operation_date=' . urlencode($result['stored_date']) . '&absent_date=' . urlencode($result['stored_date']));
    }

    public function attendanceMachineImport(): void
    {
        if (empty($_FILES['machine_log']['tmp_name'])) {
            flash('error', 'Choose the fetched ZKTeco CSV log file.');
            redirect('?route=attendance-fetch-machine');
        }
        $svc = new AttendanceImportService();
        $rows = $svc->parse($_FILES['machine_log']['tmp_name'], $_FILES['machine_log']['name'] ?? null);
        $defaultDate = $this->normalizeDate($_POST['attendance_date'] ?? '') ?: date('Y-m-d');
        $sourceName = 'zkteco:' . trim((string)($_POST['device_ip'] ?? 'device'));
        $result = $this->storeAttendanceRows($rows, $defaultDate, $sourceName);
        flash('ok', 'ZKTeco logs imported. Attendance saved: ' . $result['imported'] . ' row(s). Skipped: ' . $result['skipped'] . '.');
        redirect('?route=attendance-fetch-machine&operation_date=' . urlencode($result['stored_date']));
    }

    public function attendanceDeviceStore(): void
    {
        $this->ensureAttendanceDevicesTable();
        $name = trim((string)($_POST['device_name'] ?? ''));
        $ip = trim((string)($_POST['device_ip'] ?? ''));
        $port = max(1, min(65535, (int)($_POST['device_port'] ?? 4370)));
        $password = max(0, (int)($_POST['device_password'] ?? 0));
        $scheduleMode = trim((string)($_POST['schedule_mode'] ?? 'Manual'));
        $customTimes = trim((string)($_POST['custom_times'] ?? ''));
        $allowedScheduleModes = ['Manual', 'Hourly', 'Daily', 'Specific Times'];
        if (!in_array($scheduleMode, $allowedScheduleModes, true)) {
            $scheduleMode = 'Manual';
        }
        $fetchSchedule = $scheduleMode === 'Specific Times' && $customTimes !== ''
            ? 'Specific Times: ' . $customTimes
            : $scheduleMode;
        if ($name === '' || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'Enter a valid device name and IP address.');
            redirect('?route=attendance-fetch-machine');
        }
        $this->db->execute(
            'INSERT INTO attendance_devices(device_name,device_ip,device_port,device_password,use_udp,fetch_schedule,is_active,created_by,created_at,updated_at)
             VALUES(:device_name,:device_ip,:device_port,:device_password,:use_udp,:fetch_schedule,1,:created_by,NOW(),NOW())
             ON DUPLICATE KEY UPDATE device_name=VALUES(device_name), device_port=VALUES(device_port), device_password=VALUES(device_password), use_udp=VALUES(use_udp), fetch_schedule=VALUES(fetch_schedule), is_active=1, updated_at=NOW()',
            [
                'device_name' => $name,
                'device_ip' => $ip,
                'device_port' => $port,
                'device_password' => $password,
                'use_udp' => isset($_POST['use_udp']) ? 1 : 0,
                'fetch_schedule' => $fetchSchedule,
                'created_by' => (int)($this->auth->user()['id'] ?? 0),
            ]
        );
        flash('ok', 'Attendance device saved.');
        redirect('?route=attendance-fetch-machine');
    }

    public function attendanceDeviceDelete(): void
    {
        $this->ensureAttendanceDevicesTable();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->db->execute('UPDATE attendance_devices SET is_active=0, updated_at=NOW() WHERE id=:id', ['id' => $id]);
        }
        flash('ok', 'Attendance device removed.');
        redirect('?route=attendance-fetch-machine');
    }

    public function attendanceDeviceFetch(): void
    {
        $this->ensureAttendanceDevicesTable();
        $deviceId = (int)($_POST['device_id'] ?? 0);
        $devices = $deviceId > 0
            ? $this->db->fetchAll('SELECT * FROM attendance_devices WHERE id=:id AND is_active=1', ['id' => $deviceId])
            : $this->db->fetchAll('SELECT * FROM attendance_devices WHERE is_active=1 ORDER BY device_name, device_ip');
        if (!$devices) {
            flash('error', 'No active attendance device found.');
            redirect('?route=attendance-fetch-machine');
        }
        $fetch = $this->fetchAttendanceDevices($devices);
        if (!$fetch['csv'] || !is_file($fetch['csv'])) {
            flash('error', 'Fetch failed. ' . $fetch['message']);
            redirect('?route=attendance-fetch-machine');
        }
        $svc = new AttendanceImportService();
        $rows = $svc->parse($fetch['csv'], basename($fetch['csv']));
        $result = $this->storeAttendanceRows($rows, date('Y-m-d'), 'zkteco:registered-devices');
        flash('ok', 'Fetched ' . $fetch['fetched'] . ' device(s). Attendance saved: ' . $result['imported'] . ' row(s). Skipped: ' . $result['skipped'] . '.');
        redirect('?route=attendance-fetch-machine&operation_date=' . urlencode($result['stored_date']));
    }

    private function fetchAttendanceDevices(array $devices): array
    {
        $storageDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'attendance_fetch';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $batchId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $devicesCsv = $storageDir . DIRECTORY_SEPARATOR . 'devices_' . $batchId . '.csv';
        $outputCsv = $storageDir . DIRECTORY_SEPARATOR . 'zkteco_attendance_logs_' . $batchId . '.csv';
        $calculatedCsv = $storageDir . DIRECTORY_SEPARATOR . 'zkteco_attendance_calculated_' . $batchId . '.csv';
        $handle = fopen($devicesCsv, 'w');
        if (!$handle) {
            return ['csv' => null, 'message' => 'Unable to prepare device list.', 'fetched' => 0];
        }
        fputcsv($handle, ['device_name', 'host', 'port', 'password', 'udp']);
        foreach ($devices as $device) {
            fputcsv($handle, [
                (string)$device['device_name'],
                (string)$device['device_ip'],
                (int)$device['device_port'],
                (int)$device['device_password'],
                (int)$device['use_udp'] === 1 ? 'true' : 'false',
            ]);
        }
        fclose($handle);

        $script = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'fetch_zkteco_attendance.py';
        $command = 'python ' . escapeshellarg($script) . ' --devices ' . escapeshellarg($devicesCsv) . ' --output ' . escapeshellarg($outputCsv) . ' --calculated-output ' . escapeshellarg($calculatedCsv);
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        $message = trim(implode(' ', array_slice($output, -4))) ?: 'No command output.';
        foreach ($devices as $device) {
            $this->db->execute(
                'UPDATE attendance_devices SET last_fetch_at=NOW(), last_fetch_status=:status, last_fetch_message=:message, updated_at=NOW() WHERE id=:id',
                [
                    'id' => (int)$device['id'],
                    'status' => $exitCode === 0 ? 'Success' : ($exitCode === 2 ? 'Partial' : 'Failed'),
                    'message' => substr($message, 0, 1000),
                ]
            );
        }
        return ['csv' => is_file($calculatedCsv) ? $calculatedCsv : null, 'message' => $message, 'fetched' => count($devices)];
    }

    private function ensureAttendanceDevicesTable(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS attendance_devices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                device_name VARCHAR(120) NOT NULL,
                device_ip VARCHAR(45) NOT NULL UNIQUE,
                device_port INT NOT NULL DEFAULT 4370,
                device_password INT NOT NULL DEFAULT 0,
                use_udp TINYINT(1) NOT NULL DEFAULT 0,
                fetch_schedule VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_fetch_at DATETIME NULL,
                last_fetch_status VARCHAR(40) NULL,
                last_fetch_message TEXT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                INDEX idx_attendance_devices_active (is_active, device_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $this->db->execute("ALTER TABLE attendance_devices ADD COLUMN IF NOT EXISTS fetch_schedule VARCHAR(255) NULL AFTER use_udp");
    }

    private function storeAttendanceRows(array $rows, string $defaultDate, string $sourceFile): array
    {
        $graceLimit = max(0, (int)($this->db->fetch("SELECT setting_value FROM hr_settings WHERE setting_key='grace_limit_minutes' LIMIT 1")['setting_value'] ?? 10));
        $absentIds = [];
        $imported = 0;
        $skipped = 0;
        $processedRows = [];
        $rosterCache = [];
        $storedAttendanceDate = $defaultDate;
        $dailyPunches = [];
        foreach ($rows as $r) {
            $normalized = [];
            foreach ($r as $key => $value) {
                $normalized[strtolower(str_replace([' ', '-'], '_', trim((string)$key)))] = $value;
            }
            $employeeId = (int)($normalized['employee_id'] ?? 0);
            $employeeCode = trim((string)($normalized['employee_code'] ?? ($normalized['emp_code'] ?? '')));
            $rowSourceFile = $this->attendanceRowSourceFile($normalized, $sourceFile);
            if ($employeeId <= 0 && $employeeCode !== '') {
                $employee = $this->db->fetch('SELECT id FROM employees WHERE employee_code=:employee_code LIMIT 1', [
                    'employee_code' => $employeeCode,
                ]);
                $employeeId = (int)($employee['id'] ?? 0);
            }
            if ($employeeId <= 0) { $skipped++; continue; }
            $timestamp = $normalized['timestamp'] ?? ($normalized['punch_time'] ?? ($normalized['datetime'] ?? ($normalized['time'] ?? null)));
            $attendanceDate = $this->normalizeDate($normalized['attendance_date'] ?? ($normalized['date'] ?? ($normalized['log_date'] ?? ($timestamp ?: '')))) ?: $defaultDate;
            $storedAttendanceDate = $attendanceDate;
            if ($timestamp) {
                $punchTime = $this->normalizeTime($timestamp);
                if ($punchTime) {
                    $dailyKey = $employeeId . '|' . $attendanceDate;
                    $dailyPunches[$dailyKey]['employee_id'] = $employeeId;
                    $dailyPunches[$dailyKey]['attendance_date'] = $attendanceDate;
                    $dailyPunches[$dailyKey]['status'] = $normalized['status'] ?? '';
                    $dailyPunches[$dailyKey]['sources'][$rowSourceFile] = true;
                    $dailyPunches[$dailyKey]['times'][] = $punchTime;
                    continue;
                }
            }
            $rowKey = $employeeId . '|' . $attendanceDate;
            if (isset($processedRows[$rowKey])) { continue; }
            $processedRows[$rowKey] = true;
            $rawStatus = strtoupper(trim((string)($normalized['status'] ?? '')));
            $checkIn = $this->normalizeTime($normalized['check_in'] ?? ($normalized['punch_first'] ?? null));
            $checkOut = $this->normalizeTime($normalized['check_out'] ?? ($normalized['punch_last'] ?? null));
            $status = $this->calculateImportedAttendanceStatus($employeeId, $attendanceDate, $checkIn, $checkOut, $rawStatus, $graceLimit, $rosterCache);
            $existing = $this->db->fetch('SELECT id FROM attendance WHERE employee_id=:employee_id AND attendance_date=:attendance_date LIMIT 1', [
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
            ]);
            if ($existing) {
                $this->db->execute('UPDATE attendance SET status=:status,check_in=:check_in,check_out=:check_out,source_file=:source_file WHERE id=:id', [
                    'id' => (int)$existing['id'],
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'source_file' => $rowSourceFile,
                ]);
            } else {
                $this->db->execute('INSERT INTO attendance(employee_id,attendance_date,status,check_in,check_out,source_file,created_at) VALUES(:employee_id,:attendance_date,:status,:check_in,:check_out,:source_file,NOW())', [
                    'employee_id' => $employeeId, 'attendance_date' => $attendanceDate, 'status' => $status, 'check_in' => $checkIn, 'check_out' => $checkOut, 'source_file' => $rowSourceFile,
                ]);
            }
            $imported++;
            if ($status === 'absent') { $absentIds[] = $employeeId; }
        }

        foreach ($dailyPunches as $daily) {
            sort($daily['times']);
            $employeeId = (int)$daily['employee_id'];
            $attendanceDate = (string)$daily['attendance_date'];
            $checkIn = $daily['times'][0] ?? null;
            $checkOut = count($daily['times']) > 1 ? end($daily['times']) : null;
            $rawStatus = strtoupper(trim((string)($daily['status'] ?? '')));
            $rowSourceFile = implode(', ', array_keys($daily['sources'] ?? [$sourceFile => true]));
            $status = $this->calculateImportedAttendanceStatus($employeeId, $attendanceDate, $checkIn, $checkOut, $rawStatus, $graceLimit, $rosterCache);
            $existing = $this->db->fetch('SELECT id FROM attendance WHERE employee_id=:employee_id AND attendance_date=:attendance_date LIMIT 1', [
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
            ]);
            if ($existing) {
                $this->db->execute('UPDATE attendance SET status=:status,check_in=:check_in,check_out=:check_out,source_file=:source_file WHERE id=:id', [
                    'id' => (int)$existing['id'],
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'source_file' => $rowSourceFile,
                ]);
            } else {
                $this->db->execute('INSERT INTO attendance(employee_id,attendance_date,status,check_in,check_out,source_file,created_at) VALUES(:employee_id,:attendance_date,:status,:check_in,:check_out,:source_file,NOW())', [
                    'employee_id' => $employeeId, 'attendance_date' => $attendanceDate, 'status' => $status, 'check_in' => $checkIn, 'check_out' => $checkOut, 'source_file' => $rowSourceFile,
                ]);
            }
            $imported++;
            if ($status === 'absent') { $absentIds[] = $employeeId; }
        }
        foreach (array_unique($absentIds) as $eid) { $this->notifications->notifyEmployee((int)$eid, 'Absent Alert', 'You were marked absent during the imported attendance period.', 'attendance'); }
        return ['imported' => $imported, 'skipped' => $skipped, 'stored_date' => $storedAttendanceDate];
    }

    private function attendanceRowSourceFile(array $normalized, string $fallback): string
    {
        $deviceName = trim((string)($normalized['device_name'] ?? ($normalized['device'] ?? '')));
        $deviceIp = trim((string)($normalized['device_ip'] ?? ($normalized['ip'] ?? '')));
        if ($deviceName === '') {
            $deviceName = trim((string)($normalized['source_devices'] ?? ''));
        }
        if ($deviceIp === '') {
            $deviceIp = trim((string)($normalized['source_ips'] ?? ''));
        }
        if ($deviceName !== '' || $deviceIp !== '') {
            return 'zkteco:' . trim($deviceName . ($deviceIp !== '' ? ' (' . $deviceIp . ')' : ''));
        }
        return $fallback;
    }

    public function attendanceMarkAbsent(): void
    {
        $selectedRows = $_POST['selected_rows'] ?? [];
        $employeeIds = $_POST['employee_ids'] ?? [];
        $attendanceDates = $_POST['attendance_dates'] ?? [];

        // Backward-compatible single-row fallback.
        if (empty($selectedRows) && isset($_POST['employee_id'])) {
            $selectedRows = ['single'];
            $employeeIds = ['single' => $_POST['employee_id']];
            $attendanceDates = ['single' => ($_POST['attendance_date'] ?? date('Y-m-d'))];
        }

        if (!is_array($selectedRows) || !$selectedRows) {
            flash('error', 'Select at least one employee to mark absent.');
            redirect('?route=attendance-manual-absent');
        }

        $updated = 0;
        $lastDate = date('Y-m-d');
        foreach ($selectedRows as $rowKey) {
            $employeeId = (int)($employeeIds[$rowKey] ?? 0);
            $attendanceDate = trim((string)($attendanceDates[$rowKey] ?? date('Y-m-d')));
            if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
                continue;
            }
            $lastDate = $attendanceDate;

            $existing = $this->db->fetch(
                'SELECT id FROM attendance WHERE employee_id=:employee_id AND attendance_date=:attendance_date LIMIT 1',
                ['employee_id' => $employeeId, 'attendance_date' => $attendanceDate]
            );

            if ($existing) {
                $this->db->execute(
                    "UPDATE attendance
                     SET status='absent', check_in=NULL, check_out=NULL
                     WHERE id=:id",
                    ['id' => (int)$existing['id']]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO attendance(employee_id,attendance_date,status,check_in,check_out,source_file,created_at)
                     VALUES(:employee_id,:attendance_date,'absent',NULL,NULL,:source_file,NOW())",
                    [
                        'employee_id' => $employeeId,
                        'attendance_date' => $attendanceDate,
                        'source_file' => 'manual_absent',
                    ]
                );
            }

            $this->notifications->notifyEmployee($employeeId, 'Absent Alert', 'You were marked absent for ' . $attendanceDate, 'attendance');
            $updated++;
        }

        if ($updated <= 0) {
            flash('error', 'No valid rows were submitted.');
            redirect('?route=attendance-manual-absent');
        }
        flash('ok', $updated . ' employee(s) marked absent.');
        redirect('?route=attendance-manual-absent&absent_date=' . urlencode($lastDate));
    }

    public function attendanceUpdate(): void
    {
        $this->db->execute('UPDATE attendance SET employee_id=:employee_id,attendance_date=:attendance_date,status=:status,check_in=:check_in,check_out=:check_out WHERE id=:id', [
            'id' => (int)$_POST['id'], 'employee_id' => (int)$_POST['employee_id'], 'attendance_date' => $_POST['attendance_date'], 'status' => $_POST['status'], 'check_in' => $_POST['check_in'] ?: null, 'check_out' => $_POST['check_out'] ?: null,
        ]);
        flash('ok', 'Attendance updated'); redirect('?route=attendance');
    }

    public function attendanceDelete(): void { $this->db->execute('DELETE FROM attendance WHERE id=:id', ['id' => (int)$_POST['id']]); flash('ok', 'Attendance deleted'); redirect('?route=attendance'); }

    public function dutyRoster(): void
    {
        $this->ensureWorkforceFeatureTables();
        $scope = $this->dutyRosterScope();
        $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $showMonth = ($_GET['show_month'] ?? '0') === '1';
        $selectedDepartment = trim((string)($_GET['department'] ?? ''));
        $showAllDepartments = $scope['all'] && ($selectedDepartment === '' || $selectedDepartment === '__all__');
        $departmentRequired = false;
        $employeeSearch = trim((string)($_GET['employee_q'] ?? ''));
        $startTs = strtotime($weekStart);
        if ($startTs === false) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $startTs = strtotime($weekStart);
        }
        $weekStart = date('Y-m-d', strtotime('monday this week', $startTs));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 day'));
        $weekNumber = intdiv(((int)date('j', strtotime($weekStart))) - 1, 7) + 1;
        $weekSuffix = 'th';
        if (!in_array($weekNumber % 100, [11, 12, 13], true)) {
            $weekSuffix = match ($weekNumber % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };
        }
        $weeklyPeriod = [
            'month' => date('F Y', strtotime($weekStart)),
            'week_label' => $weekNumber . $weekSuffix . ' Week',
            'range' => fmt_date($weekStart) . ' to ' . fmt_date($weekEnd),
        ];
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime($weekStart . " +{$i} day"));
        }
        $effectiveDepartment = $scope['all'] ? ($showAllDepartments ? null : $selectedDepartment) : $scope['department'];
        $employeeFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, $employeeSearch);
        $employeeWhere = $employeeFilters['where'];
        $employeeParams = $employeeFilters['params'];

        $items = $this->db->fetchAll(
            'SELECT dr.*, e.first_name, e.last_name, e.department, e.position
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.duty_date BETWEEN :start AND :end' . $employeeFilters['andSql'] . '
             ORDER BY dr.duty_date ASC, e.first_name ASC',
            ['start' => $weekStart, 'end' => $weekEnd] + $employeeParams
        );
        $allItems = $this->db->fetchAll(
            'SELECT dr.*, e.first_name, e.last_name
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id' . $employeeWhere . '
             ORDER BY dr.duty_date DESC, dr.id DESC LIMIT 300',
            $employeeParams
        );
        $employees = $this->db->fetchAll(
            'SELECT e.id, e.employee_code, e.first_name, e.last_name, e.department, e.position
             FROM employees e' . $employeeWhere . '
             ORDER BY e.first_name, e.last_name',
            $employeeParams
        );
        $departments = $scope['all']
            ? $this->db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND TRIM(department) <> '' ORDER BY department")
            : [];
        $today = date('Y-m-d');
        $summaryTotalFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, '', 'summary_total');
        $summaryOffFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, '', 'summary_off');
        $summaryDoctorFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, '', 'summary_doctor');
        $summaryUpcomingFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, '', 'summary_upcoming');
        $summaryRow = $this->db->fetch(
            "SELECT
                (SELECT COUNT(*) FROM employees e" . $summaryTotalFilters['where'] . ") total_employees,
                (SELECT COUNT(DISTINCT dr.employee_id)
                 FROM duty_roster dr
                 JOIN employees e ON e.id = dr.employee_id
                 WHERE dr.duty_date = :today_off
                   AND UPPER(TRIM(dr.shift_name)) = 'OFF'" . $summaryOffFilters['andSql'] . ") off_today,
                (SELECT COUNT(*)
                 FROM employees e
                 WHERE LOWER(CONCAT_WS(' ', e.department, e.position)) REGEXP 'doctor|medical|physician|surgeon'
                   " . ($summaryDoctorFilters['andSql'] !== '' ? $summaryDoctorFilters['andSql'] : '') . "
                   AND NOT EXISTS (
                     SELECT 1 FROM duty_roster dr
                     WHERE dr.employee_id = e.id
                       AND dr.duty_date = :today_doctor
                       AND UPPER(TRIM(dr.shift_name)) = 'OFF'
                   )) doctors_available,
                (SELECT COUNT(*)
                 FROM duty_roster dr
                 JOIN employees e ON e.id = dr.employee_id
                 WHERE dr.duty_date > :today_upcoming" . $summaryUpcomingFilters['andSql'] . ") upcoming_records",
            ['today_off' => $today, 'today_doctor' => $today, 'today_upcoming' => $today]
                + $summaryTotalFilters['params']
                + $summaryOffFilters['params']
                + $summaryDoctorFilters['params']
                + $summaryUpcomingFilters['params']
        ) ?: ['total_employees' => 0, 'off_today' => 0, 'doctors_available' => 0, 'upcoming_records' => 0];
        $todayFilters = $this->dutyRosterEmployeeFilters($effectiveDepartment, '', 'today_filter');
        $todayRows = $this->db->fetchAll(
            'SELECT dr.*, e.first_name, e.last_name, e.department, e.position
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.duty_date = :today' . $todayFilters['andSql'] . '
             ORDER BY e.first_name, e.last_name, dr.start_time',
            ['today' => $today] + $todayFilters['params']
        );
        $historyRows = $this->db->fetchAll(
            'SELECT dr.*, e.first_name, e.last_name, e.department, e.position
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             ' . ($scope['all'] ? '' : 'WHERE e.department COLLATE utf8mb4_general_ci = :history_department') . '
             ORDER BY dr.duty_date DESC, dr.start_time ASC, e.first_name, e.last_name
             LIMIT 500',
            $scope['all'] ? [] : ['history_department' => $scope['department']]
        );

        $grid = [];
        foreach ($employees as $emp) {
            $grid[(int)$emp['id']] = [
                'employee' => $emp,
                'days' => array_fill_keys($days, []),
            ];
        }
        foreach ($items as $row) {
            $eid = (int)$row['employee_id'];
            if (!isset($grid[$eid])) {
                continue;
            }
            $grid[$eid]['days'][$row['duty_date']][] = $row;
        }

        $todayByEmployee = [];
        foreach ($todayRows as $row) {
            $todayByEmployee[(int)$row['employee_id']][] = $row;
        }

        $availableToday = [];
        $offToday = [];
        $doctorsToday = [];
        foreach ($employees as $emp) {
            $employeeRows = $todayByEmployee[(int)$emp['id']] ?? [];
            $isOff = false;
            foreach ($employeeRows as $row) {
                if (strtoupper(trim((string)$row['shift_name'])) === 'OFF') {
                    $isOff = true;
                    break;
                }
            }
            $statusRow = [
                'employee' => $emp,
                'rows' => $employeeRows,
                'status' => $isOff ? 'Off Duty' : 'Available',
            ];
            if ($isOff) {
                $offToday[] = $statusRow;
            } else {
                $availableToday[] = $statusRow;
                $roleText = strtolower((string)($emp['department'] ?? '') . ' ' . (string)($emp['position'] ?? ''));
                if (str_contains($roleText, 'doctor') || str_contains($roleText, 'medical') || str_contains($roleText, 'physician') || str_contains($roleText, 'surgeon')) {
                    $doctorsToday[] = $statusRow;
                }
            }
        }

        $weeklyOffDays = 0;
        foreach ($grid as $row) {
            foreach ($days as $d) {
                foreach ($row['days'][$d] ?? [] as $slot) {
                    if (strtoupper(trim((string)$slot['shift_name'])) === 'OFF') {
                        $weeklyOffDays++;
                        break;
                    }
                }
            }
        }
        $weeklyCapacity = count($employees) * count($days);
        $weeklySummary = [
            'capacity' => $weeklyCapacity,
            'on_days' => max(0, $weeklyCapacity - $weeklyOffDays),
            'off_days' => $weeklyOffDays,
            'explicit_records' => count($items),
        ];
        $shiftDefaults = $this->dutyRosterShiftDefaults();
        $shiftTracker = $this->buildDutyShiftTracker($items, $employees, $days, $shiftDefaults);
        $staffingRequirements = $this->db->fetchAll('SELECT * FROM staffing_requirements WHERE is_active=1 ORDER BY department, shift_name');
        $coverageRows = $this->db->fetchAll(
            "SELECT dr.duty_date, e.department, dr.shift_name,
                    SUM(LOWER(e.position) REGEXP 'nurse|nursing') nurses,
                    SUM(LOWER(e.position) REGEXP 'technician|tech|lab|radiology') technicians
             FROM duty_roster dr
             JOIN employees e ON e.id=dr.employee_id
             WHERE dr.duty_date BETWEEN :start AND :end
               AND UPPER(TRIM(dr.shift_name)) <> 'OFF'
             GROUP BY dr.duty_date, e.department, dr.shift_name",
            ['start' => $weekStart, 'end' => $weekEnd]
        );
        $coverageMap = [];
        foreach ($coverageRows as $coverageRow) {
            $coverageMap[(string)$coverageRow['duty_date'] . '|' . (string)$coverageRow['department'] . '|' . (string)$coverageRow['shift_name']] = $coverageRow;
        }
        $staffShortageAlerts = [];
        foreach ($staffingRequirements as $requirement) {
            foreach ($days as $day) {
                $key = $day . '|' . (string)$requirement['department'] . '|' . (string)$requirement['shift_name'];
                $coverage = $coverageMap[$key] ?? ['nurses' => 0, 'technicians' => 0];
                $nurseShort = max(0, (int)$requirement['required_nurses'] - (int)$coverage['nurses']);
                $techShort = max(0, (int)$requirement['required_technicians'] - (int)$coverage['technicians']);
                if ($nurseShort > 0 || $techShort > 0) {
                    $staffShortageAlerts[] = [
                        'date' => $day,
                        'department' => (string)$requirement['department'],
                        'shift_name' => (string)$requirement['shift_name'],
                        'nurse_shortage' => $nurseShort,
                        'technician_shortage' => $techShort,
                    ];
                }
            }
        }

        $dutyHistory = ['past' => [], 'current' => [], 'upcoming' => []];
        foreach ($historyRows as $row) {
            if ($row['duty_date'] < $today) {
                $dutyHistory['past'][] = $row;
            } elseif ($row['duty_date'] === $today) {
                $dutyHistory['current'][] = $row;
            } else {
                $dutyHistory['upcoming'][] = $row;
            }
        }
        $dutyStatusSummary = [
            'today' => $today,
            'available' => max(0, (int)$summaryRow['total_employees'] - (int)$summaryRow['off_today']),
            'doctors' => (int)$summaryRow['doctors_available'],
            'off' => (int)$summaryRow['off_today'],
            'past' => count($dutyHistory['past']),
            'current' => count($dutyHistory['current']),
            'upcoming' => (int)$summaryRow['upcoming_records'],
        ];

        $canManageDuty = true;
        $scopeLabel = $effectiveDepartment !== null ? (string)$effectiveDepartment : 'All departments';
        $activeFilters = ['department' => $showAllDepartments ? '__all__' : $effectiveDepartment, 'employee_q' => $employeeSearch];
        View::render('duty_roster/index', compact('items', 'allItems', 'employees', 'departments', 'grid', 'days', 'weekStart', 'weekEnd', 'showMonth', 'today', 'availableToday', 'offToday', 'doctorsToday', 'dutyHistory', 'dutyStatusSummary', 'weeklySummary', 'weeklyPeriod', 'shiftTracker', 'shiftDefaults', 'scopeLabel', 'canManageDuty', 'activeFilters', 'departmentRequired', 'staffingRequirements', 'staffShortageAlerts'));
    }

    public function staffingRequirementStore(): void
    {
        $this->ensureWorkforceFeatureTables();
        $department = trim((string)($_POST['department'] ?? ''));
        $shiftName = trim((string)($_POST['shift_name'] ?? ''));
        if ($department === '' || $shiftName === '') {
            flash('error', 'Department and shift are required.');
            redirect('?route=duty-roster#staff-shortage-alerts');
        }
        $this->db->execute(
            'INSERT INTO staffing_requirements(department,shift_name,required_nurses,required_technicians,is_active,created_at)
             VALUES(:department,:shift_name,:required_nurses,:required_technicians,1,NOW())
             ON DUPLICATE KEY UPDATE required_nurses=VALUES(required_nurses), required_technicians=VALUES(required_technicians), is_active=1, updated_at=NOW()',
            [
                'department' => $department,
                'shift_name' => $shiftName,
                'required_nurses' => max(0, (int)($_POST['required_nurses'] ?? 0)),
                'required_technicians' => max(0, (int)($_POST['required_technicians'] ?? 0)),
            ]
        );
        flash('ok', 'Staffing requirement saved.');
        redirect('?route=duty-roster#staff-shortage-alerts');
    }

    public function dutyRosterExport(): void
    {
        $scope = $this->dutyRosterScope();
        $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $startTs = strtotime($weekStart);
        if ($startTs === false) {
            $startTs = strtotime('monday this week');
        }
        $weekStart = date('Y-m-d', strtotime('monday this week', $startTs));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 day'));
        $departmentInput = trim((string)($_GET['department'] ?? ''));
        $department = $scope['all'] ? (($departmentInput !== '' && $departmentInput !== '__all__') ? $departmentInput : null) : $scope['department'];
        $employeeSearch = trim((string)($_GET['employee_q'] ?? ''));
        $filters = $this->dutyRosterEmployeeFilters($department, $employeeSearch);
        $rows = $this->db->fetchAll(
            'SELECT e.employee_code, CONCAT(e.first_name, " ", e.last_name) employee_name, e.department, e.position,
                    dr.duty_date, dr.shift_name, dr.start_time, dr.end_time, dr.ward, dr.notes
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.duty_date BETWEEN :start AND :end' . $filters['andSql'] . '
             ORDER BY e.department, e.first_name, e.last_name, dr.duty_date, dr.start_time',
            ['start' => $weekStart, 'end' => $weekEnd] + $filters['params']
        );

        $format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));
        $label = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($department ?: 'all_departments'));
        $baseName = 'duty_roster_' . $label . '_' . $weekStart . '_to_' . $weekEnd;
        if ($format === 'pdf') {
            $this->downloadDutyRosterPdf($rows, $baseName . '.pdf', $weekStart, $weekEnd, $department, $employeeSearch);
        }
        $this->downloadDutyRosterXlsx($rows, $baseName . '.xlsx', $weekStart, $weekEnd, $department, $employeeSearch);
    }

    private function dutyRosterEmployeeFilters(?string $department, string $employeeSearch, string $prefix = 'duty_filter'): array
    {
        $where = [];
        $params = [];
        $department = trim((string)$department);
        $employeeSearch = trim($employeeSearch);
        if ($department === '__none__') {
            $where[] = '1=0';
        } elseif ($department !== '') {
            $where[] = 'e.department COLLATE utf8mb4_general_ci = :' . $prefix . '_department';
            $params[$prefix . '_department'] = $department;
        }
        if ($employeeSearch !== '') {
            $where[] = "(e.employee_code LIKE :" . $prefix . "_employee_code
                OR e.first_name LIKE :" . $prefix . "_first_name
                OR e.last_name LIKE :" . $prefix . "_last_name
                OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE :" . $prefix . "_full_name
                OR e.department LIKE :" . $prefix . "_employee_department
                OR e.position LIKE :" . $prefix . "_employee_position)";
            $search = '%' . $employeeSearch . '%';
            $params[$prefix . '_employee_code'] = $search;
            $params[$prefix . '_first_name'] = $search;
            $params[$prefix . '_last_name'] = $search;
            $params[$prefix . '_full_name'] = $search;
            $params[$prefix . '_employee_department'] = $search;
            $params[$prefix . '_employee_position'] = $search;
        }
        $sql = $where ? implode(' AND ', $where) : '';
        return [
            'where' => $sql !== '' ? ' WHERE ' . $sql : '',
            'andSql' => $sql !== '' ? ' AND ' . $sql : '',
            'params' => $params,
        ];
    }

    private function dutyRosterExportRowsForSheet(array $rows): array
    {
        $out = [['Employee Code', 'Employee Name', 'Department', 'Position', 'Date', 'Shift', 'Start', 'End', 'Ward', 'Notes']];
        foreach ($rows as $row) {
            $out[] = [
                (string)($row['employee_code'] ?? ''),
                trim((string)($row['employee_name'] ?? '')),
                (string)($row['department'] ?? ''),
                (string)($row['position'] ?? ''),
                fmt_date((string)($row['duty_date'] ?? '')),
                (string)($row['shift_name'] ?? ''),
                substr((string)($row['start_time'] ?? ''), 0, 5),
                substr((string)($row['end_time'] ?? ''), 0, 5),
                (string)($row['ward'] ?? ''),
                (string)($row['notes'] ?? ''),
            ];
        }
        return $out;
    }

    private function xlsxCellRef(int $column, int $row): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }
        return $letters . $row;
    }

    private function downloadDutyRosterXlsx(array $rows, string $filename, string $weekStart, string $weekEnd, ?string $department, string $employeeSearch): void
    {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('XLSX export requires the PHP zip extension.');
        }
        $tableRows = [
            ['Duty Roster', '', '', '', '', '', '', '', '', ''],
            ['Period', fmt_date($weekStart) . ' to ' . fmt_date($weekEnd), 'Department', $department ?: 'All departments', 'Employee Search', $employeeSearch ?: 'All employees', '', '', '', ''],
            [],
            ...$this->dutyRosterExportRowsForSheet($rows),
        ];
        $sheetData = '';
        foreach ($tableRows as $rIndex => $row) {
            $rowNum = $rIndex + 1;
            $sheetData .= '<row r="' . $rowNum . '">';
            foreach ($row as $cIndex => $value) {
                $ref = $this->xlsxCellRef($cIndex + 1, $rowNum);
                $sheetData .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></is></c>';
            }
            $sheetData .= '</row>';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'duty_roster_');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Unable to build XLSX export.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Duty Roster" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="10" width="18" customWidth="1"/></cols><sheetData>' . $sheetData . '</sheetData></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function downloadDutyRosterPdf(array $rows, string $filename, string $weekStart, string $weekEnd, ?string $department, string $employeeSearch): void
    {
        $lines = [
            'Hospital HR - Duty Roster',
            'Period: ' . fmt_date($weekStart) . ' to ' . fmt_date($weekEnd),
            'Department: ' . ($department ?: 'All departments') . ' | Employee: ' . ($employeeSearch ?: 'All employees'),
            '',
        ];
        foreach ($this->dutyRosterExportRowsForSheet($rows) as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $lines[] = implode(' | ', array_slice($row, 0, 8));
        }
        if (count($lines) === 4) {
            $lines[] = 'No duty roster records found.';
        }
        $this->downloadSimplePdf($lines, $filename);
    }

    private function downloadAttendanceCalendarXlsx(array $report, string $filename): void
    {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            exit('XLSX export requires the PHP zip extension.');
        }
        $rows = [
            ['Hospital HR - Daily Attendance Report', '', '', '', '', '', ''],
            ['Month', (string)$report['month_label'], '', '', '', '', ''],
            [],
            ['Date', 'Present', 'Late', 'Absent', 'On Leave', 'On Duty', 'Not Marked'],
        ];
        foreach ($report['days'] as $day) {
            $rows[] = [fmt_date((string)$day['date']), (int)$day['present'], (int)$day['late'], (int)$day['absent'], (int)$day['on_leave'], (int)$day['on_duty'], (int)$day['not_marked']];
        }
        $rows[] = ['Total', (int)$report['totals']['present'], (int)$report['totals']['late'], (int)$report['totals']['absent'], (int)$report['totals']['on_leave'], (int)$report['totals']['on_duty'], (int)$report['totals']['not_marked']];

        $sheetData = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1;
            $sheetData .= '<row r="' . $rowNum . '">';
            foreach ($row as $columnIndex => $value) {
                $sheetData .= '<c r="' . $this->xlsxCellRef($columnIndex + 1, $rowNum) . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></is></c>';
            }
            $sheetData .= '</row>';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'attendance_calendar_');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Unable to build XLSX export.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Attendance" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="7" width="14" customWidth="1"/></cols><sheetData>' . $sheetData . '</sheetData></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function downloadAttendanceCalendarPdf(array $report, string $filename): void
    {
        $lines = ['Hospital HR - Daily Attendance Report', 'Month: ' . (string)$report['month_label'], '', 'Date | Present | Late | Absent | Leave | On Duty | Not Marked'];
        foreach ($report['days'] as $day) {
            $lines[] = implode(' | ', [fmt_date((string)$day['date']), (int)$day['present'], (int)$day['late'], (int)$day['absent'], (int)$day['on_leave'], (int)$day['on_duty'], (int)$day['not_marked']]);
        }
        $lines[] = 'Total | ' . implode(' | ', [(int)$report['totals']['present'], (int)$report['totals']['late'], (int)$report['totals']['absent'], (int)$report['totals']['on_leave'], (int)$report['totals']['on_duty'], (int)$report['totals']['not_marked']]);
        $this->downloadSimplePdf($lines, $filename);
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function downloadSimplePdf(array $lines, string $filename): void
    {
        $expandedLines = [];
        foreach ($lines as $line) {
            $chunks = str_split($line, 150) ?: [''];
            foreach ($chunks as $chunk) {
                $expandedLines[] = $chunk;
            }
        }
        $pages = array_chunk($expandedLines, 42);
        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = '';
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $pageObjectIds = [];
        foreach ($pages as $pageLines) {
            $content = "BT\n/F1 10 Tf\n40 555 Td\n12 TL\n";
            foreach ($pageLines as $line) {
                $content .= '(' . $this->pdfEscape($line) . ") Tj\nT*\n";
            }
            $content .= "ET";
            $pageObjectId = count($objects) + 1;
            $contentObjectId = count($objects) + 2;
            $pageObjectIds[] = $pageObjectId . ' 0 R';
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents " . $contentObjectId . " 0 R >>";
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[1] = "<< /Type /Pages /Kids [" . implode(' ', $pageObjectIds) . "] /Count " . count($pageObjectIds) . " >>";
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public function dutyRosterStore(): void
    {
        $this->requireDutyRosterEmployeeAccess((int)$_POST['employee_id']);
        $this->db->execute('INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at) VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())', [
            'employee_id' => (int)$_POST['employee_id'],
            'duty_date' => $_POST['duty_date'],
            'shift_name' => $_POST['shift_name'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'ward' => $_POST['ward'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'created_by' => (int)$this->auth->user()['id'],
        ]);
        flash('ok', 'Duty roster added');
        redirect('?route=duty-roster');
    }

    public function dutyRosterAssign(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $dutyDate = trim((string)($_POST['duty_date'] ?? ''));
        $weekStart = trim((string)($_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'))));
        $showMonth = ($_POST['show_month'] ?? '0') === '1' ? '1' : '0';
        $shiftName = trim((string)($_POST['shift_name'] ?? ''));
        $returnEmployeeId = (int)($_POST['return_employee_id'] ?? $employeeId);
        $scrollTop = max(0, (int)($_POST['scroll_top'] ?? 0));
        $returnDepartment = trim((string)($_POST['return_department'] ?? ''));

        if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dutyDate) || $shiftName === '') {
            flash('error', 'Select employee, date, and shift.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }
        $this->requireDutyRosterEmployeeAccess($employeeId);

        $shiftDefaults = $this->dutyRosterShiftDefaults(true);
        if (!isset($shiftDefaults[$shiftName])) {
            flash('error', 'Invalid shift selected.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }

        $startTime = $this->normalizeTime($_POST['start_time'] ?? '') ?: ($shiftDefaults[$shiftName][0] . ':00');
        $endTime = $this->normalizeTime($_POST['end_time'] ?? '') ?: ($shiftDefaults[$shiftName][1] . ':00');
        $weekStartTs = strtotime($weekStart);
        if ($weekStartTs === false) {
            $weekStartTs = strtotime($dutyDate);
        }
        $weekStartDate = date('Y-m-d', strtotime('monday this week', $weekStartTs));
        $weekEndDate = date('Y-m-d', strtotime($weekStartDate . ' +6 day'));

        if ($shiftName !== 'OFF') {
            $assigned = 0;
            for ($i = 0; $i < 7; $i++) {
                $targetDate = date('Y-m-d', strtotime($weekStartDate . " +{$i} day"));
                $existingOff = $this->db->fetch(
                    'SELECT id FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date AND UPPER(shift_name)="OFF" LIMIT 1',
                    ['employee_id' => $employeeId, 'duty_date' => $targetDate]
                );
                if ($existingOff) {
                    continue;
                }
                $this->db->execute(
                    'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                    ['employee_id' => $employeeId, 'duty_date' => $targetDate]
                );
                $this->db->execute(
                    'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                     VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                    [
                        'employee_id' => $employeeId,
                        'duty_date' => $targetDate,
                        'shift_name' => $shiftName,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'ward' => ($_POST['ward'] ?? '') ?: null,
                        'notes' => 'Weekly shift assigned by ' . (string)($this->auth->user()['role'] ?? 'HR'),
                        'created_by' => (int)$this->auth->user()['id'],
                    ]
                );
                $assigned++;
            }
            $this->ensureEmployeeOneWeeklyOff($employeeId, $weekStartDate, $weekEndDate, $shiftName, $startTime, $endTime);

            flash('ok', $shiftName . ' assigned for the employee week. Days updated: ' . $assigned . '.');
            redirect($this->dutyRosterReturnUrl($weekStartDate, $showMonth, $returnDepartment, $scrollTop, $returnEmployeeId));
        }

        $fallbackShift = $this->employeeWeekFallbackShift($employeeId, $weekStartDate, $weekEndDate) ?: 'General';
        $fallbackDefaults = $shiftDefaults[$fallbackShift] ?? $shiftDefaults['General'];
        $fallbackStart = $fallbackDefaults[0] . ':00';
        $fallbackEnd = $fallbackDefaults[1] . ':00';
        $existingOffRows = $this->db->fetchAll(
            'SELECT duty_date FROM duty_roster
             WHERE employee_id=:employee_id
               AND duty_date BETWEEN :start AND :end
               AND UPPER(shift_name)="OFF"
               AND duty_date<>:duty_date',
            ['employee_id' => $employeeId, 'start' => $weekStartDate, 'end' => $weekEndDate, 'duty_date' => $dutyDate]
        );
        foreach ($existingOffRows as $existingOffRow) {
            $existingOffDate = (string)$existingOffRow['duty_date'];
            $this->db->execute(
                'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                ['employee_id' => $employeeId, 'duty_date' => $existingOffDate]
            );
            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                 VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $existingOffDate,
                    'shift_name' => $fallbackShift,
                    'start_time' => $fallbackStart,
                    'end_time' => $fallbackEnd,
                    'ward' => null,
                    'notes' => 'Previous OFF replaced to keep one weekly off',
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
        }

        $this->db->execute(
            'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
            ['employee_id' => $employeeId, 'duty_date' => $dutyDate]
        );
        $this->db->execute(
            'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
             VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
            [
                'employee_id' => $employeeId,
                'duty_date' => $dutyDate,
                'shift_name' => $shiftName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'ward' => ($_POST['ward'] ?? '') ?: null,
                'notes' => ($_POST['notes'] ?? '') ?: null,
                'created_by' => (int)$this->auth->user()['id'],
            ]
        );

        flash('ok', 'Week off assigned for ' . $dutyDate . '.');
        redirect($this->dutyRosterReturnUrl($weekStartDate, $showMonth, $returnDepartment, $scrollTop, $returnEmployeeId));
    }

    public function dutyRosterAutoGenerate(): void
    {
        $scope = $this->dutyRosterScope();
        $weekStartInput = trim((string)($_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'))));
        $showMonth = ($_POST['show_month'] ?? '0') === '1' ? '1' : '0';
        $departmentInput = trim((string)($_POST['department'] ?? ''));
        $generationMode = ($_POST['generation_mode'] ?? 'fill_missing') === 'replace_auto' ? 'replace_auto' : 'fill_missing';
        $startTs = strtotime($weekStartInput);
        if ($startTs === false) {
            $startTs = strtotime('monday this week');
        }
        $weekStart = date('Y-m-d', strtotime('monday this week', $startTs));
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = date('Y-m-d', strtotime($weekStart . " +{$i} day"));
        }
        $weekEnd = $days[6];

        $targetDepartment = $scope['all'] ? ($departmentInput !== '' && $departmentInput !== '__all__' ? $departmentInput : null) : $scope['department'];
        $employeeFilters = $this->dutyRosterEmployeeFilters($targetDepartment, '', 'auto_scope');
        $employees = $this->db->fetchAll(
            'SELECT e.id, e.department, e.position
             FROM employees e' . $employeeFilters['where'] . '
             ORDER BY e.department, e.position, e.first_name, e.last_name',
            $employeeFilters['params']
        );
        if (!$employees) {
            flash('error', $scope['all'] ? 'No employees found for auto generation.' : 'No employees found in your department for auto generation.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }

        $existingRows = $this->db->fetchAll(
            'SELECT dr.id, dr.employee_id, dr.duty_date, dr.notes
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.duty_date BETWEEN :start AND :end' . $employeeFilters['andSql'],
            ['start' => $weekStart, 'end' => $weekEnd] + $employeeFilters['params']
        );
        $existing = [];
        foreach ($existingRows as $r) {
            $existing[(int)$r['employee_id'] . '|' . $r['duty_date']] = $r;
        }

        $shiftDefaults = $this->dutyRosterShiftDefaults();
        $shiftNames = array_keys($shiftDefaults);
        $createdBy = (int)$this->auth->user()['id'];
        $inserted = 0;
        $skipped = 0;
        $replaced = 0;
        foreach ($employees as $index => $emp) {
            $eid = (int)$emp['id'];
            $primaryShift = $this->autoRosterShiftForEmployee($emp, $shiftNames, $index);
            $offDayIndex = $index % 7;
            foreach ($days as $d) {
                $k = $eid . '|' . $d;
                $hasExisting = isset($existing[$k]);
                $canReplace = $generationMode === 'replace_auto' && $hasExisting && str_contains(strtolower((string)($existing[$k]['notes'] ?? '')), 'auto generated');
                if ($hasExisting && !$canReplace) {
                    $skipped++;
                    continue;
                }
                if ($canReplace) {
                    $this->db->execute(
                        'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                        ['employee_id' => $eid, 'duty_date' => $d]
                    );
                    $replaced++;
                }
                $dayIndex = (int)((strtotime($d) - strtotime($weekStart)) / 86400);
                $shiftName = $dayIndex === $offDayIndex ? 'OFF' : $primaryShift;
                $times = $shiftName === 'OFF' ? ['00:00', '00:00'] : $shiftDefaults[$shiftName];
                $ward = trim((string)($emp['department'] ?? '')) ?: null;
                $notes = $shiftName === 'OFF' ? 'Auto generated weekly OFF' : 'Auto generated shift tracker';
                $this->db->execute(
                    'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                     VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                    [
                        'employee_id' => $eid,
                        'duty_date' => $d,
                        'shift_name' => $shiftName,
                        'start_time' => $times[0] . ':00',
                        'end_time' => $times[1] . ':00',
                        'ward' => $ward,
                        'notes' => $notes,
                        'created_by' => $createdBy,
                    ]
                );
                $inserted++;
            }
        }

        flash('ok', 'Auto roster completed. Added: ' . $inserted . ', Replaced auto rows: ' . $replaced . ', Preserved existing: ' . $skipped . '.');
        $departmentQuery = $targetDepartment !== null ? '&department=' . urlencode($targetDepartment) : '';
        redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . $departmentQuery . '&show_month=' . $showMonth);
    }

    public function dutyRosterDepartmentAssign(): void
    {
        $scope = $this->dutyRosterScope();
        $weekStartInput = trim((string)($_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'))));
        $department = trim((string)($_POST['department'] ?? ''));
        $shiftName = trim((string)($_POST['shift_name'] ?? ''));
        $showMonth = ($_POST['show_month'] ?? '0') === '1' ? '1' : '0';
        $startTs = strtotime($weekStartInput);
        if ($startTs === false) {
            $startTs = strtotime('monday this week');
        }
        $weekStart = date('Y-m-d', strtotime('monday this week', $startTs));

        $shiftDefaults = $this->dutyRosterShiftDefaults();
        if ($department === '' || !isset($shiftDefaults[$shiftName])) {
            flash('error', 'Select department and shift.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }
        if (!$scope['all'] && strcasecmp((string)$scope['department'], $department) !== 0) {
            flash('error', 'You can assign only your department roster.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }

        $employees = $this->db->fetchAll(
            'SELECT id FROM employees WHERE department COLLATE utf8mb4_general_ci = :department ORDER BY first_name,last_name',
            ['department' => $department]
        );
        if (!$employees) {
            flash('error', 'No employees found in selected department.');
            redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
        }

        $startTime = $shiftDefaults[$shiftName][0] . ':00';
        $endTime = $shiftDefaults[$shiftName][1] . ':00';
        $createdBy = (int)$this->auth->user()['id'];
        $assigned = 0;
        foreach ($employees as $employee) {
            $employeeId = (int)$employee['id'];
            if (!$this->canAccessDutyRosterEmployee($employeeId)) {
                continue;
            }
            for ($i = 0; $i < 7; $i++) {
                $targetDate = date('Y-m-d', strtotime($weekStart . " +{$i} day"));
                $existingOff = $this->db->fetch(
                    'SELECT id FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date AND UPPER(shift_name)="OFF" LIMIT 1',
                    ['employee_id' => $employeeId, 'duty_date' => $targetDate]
                );
                if ($existingOff) {
                    continue;
                }
                $this->db->execute(
                    'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                    ['employee_id' => $employeeId, 'duty_date' => $targetDate]
                );
                $this->db->execute(
                    'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                     VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                    [
                        'employee_id' => $employeeId,
                        'duty_date' => $targetDate,
                        'shift_name' => $shiftName,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'ward' => null,
                        'notes' => 'Department weekly shift assigned by ' . (string)($this->auth->user()['role'] ?? 'HR'),
                        'created_by' => $createdBy,
                    ]
                );
                $assigned++;
            }
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 day'));
            $this->ensureEmployeeOneWeeklyOff($employeeId, $weekStart, $weekEnd, $shiftName, $startTime, $endTime);
        }

        flash('ok', $shiftName . ' assigned to ' . $department . '. Days updated: ' . $assigned . '.');
        redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&department=' . urlencode($department) . '&show_month=' . $showMonth);
    }

    public function dutyRosterBulkApply(): void
    {
        $employeeIds = $_POST['employee_ids'] ?? [];
        $dutyDate = $_POST['duty_date'] ?? '';
        $shiftName = trim((string)($_POST['shift_name'] ?? ''));
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $ward = $_POST['ward'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!is_array($employeeIds) || !$employeeIds || !$dutyDate || !$shiftName || !$startTime || !$endTime) {
            flash('error', 'Bulk apply requires employees, date, shift, start, and end time.');
            redirect('?route=duty-roster');
        }

        $count = 0;
        foreach ($employeeIds as $eid) {
            $employeeId = (int)$eid;
            if ($employeeId <= 0) {
                continue;
            }
            if (!$this->canAccessDutyRosterEmployee($employeeId)) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at) VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $dutyDate,
                    'shift_name' => $shiftName,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'ward' => $ward,
                    'notes' => $notes,
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
            $count++;
        }

        flash('ok', 'Bulk applied to ' . $count . ' employee(s).');
        redirect('?route=duty-roster&week_start=' . urlencode(date('Y-m-d', strtotime('monday this week', strtotime($dutyDate)))));
    }

    public function dutyRosterBulkUpload(): void
    {
        if (empty($_FILES['roster_sheet']['tmp_name'])) {
            flash('error', 'Please choose a CSV/XLSX file.');
            redirect('?route=duty-roster');
        }

        $svc = new AttendanceImportService();
        $rows = $svc->parse($_FILES['roster_sheet']['tmp_name'], $_FILES['roster_sheet']['name'] ?? null);
        $count = 0;

        foreach ($rows as $r) {
            $employeeId = (int)($r['employee_id'] ?? 0);
            $dutyDate = trim((string)($r['duty_date'] ?? ''));
            $shiftName = trim((string)($r['shift_name'] ?? ''));
            $startTime = trim((string)($r['start_time'] ?? ''));
            $endTime = trim((string)($r['end_time'] ?? ''));
            if ($employeeId <= 0 || $dutyDate === '' || $shiftName === '' || $startTime === '' || $endTime === '') {
                continue;
            }
            if (!$this->canAccessDutyRosterEmployee($employeeId)) {
                continue;
            }

            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at) VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $dutyDate,
                    'shift_name' => $shiftName,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'ward' => $r['ward'] ?? null,
                    'notes' => $r['notes'] ?? null,
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
            $count++;
        }

        flash('ok', 'Bulk upload completed: ' . $count . ' row(s) inserted.');
        redirect('?route=duty-roster');
    }

    public function dutyRosterUpdate(): void
    {
        $this->requireDutyRosterEmployeeAccess((int)$_POST['employee_id']);
        $this->requireDutyRosterRowAccess((int)$_POST['id']);
        $this->db->execute('UPDATE duty_roster SET employee_id=:employee_id,duty_date=:duty_date,shift_name=:shift_name,start_time=:start_time,end_time=:end_time,ward=:ward,notes=:notes,updated_at=NOW() WHERE id=:id', [
            'id' => (int)$_POST['id'],
            'employee_id' => (int)$_POST['employee_id'],
            'duty_date' => $_POST['duty_date'],
            'shift_name' => $_POST['shift_name'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'ward' => $_POST['ward'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ]);
        flash('ok', 'Duty roster updated');
        redirect('?route=duty-roster');
    }

    public function dutyRosterDelete(): void
    {
        $this->requireDutyRosterRowAccess((int)$_POST['id']);
        $this->db->execute('DELETE FROM duty_roster WHERE id=:id', ['id' => (int)$_POST['id']]);
        flash('ok', 'Duty roster deleted');
        redirect('?route=duty-roster');
    }

    public function dutyRosterToggle(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $dutyDate = $_POST['duty_date'] ?? '';
        $weekStart = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $showMonth = ($_POST['show_month'] ?? '0') === '1' ? '1' : '0';
        if ($employeeId <= 0 || $dutyDate === '') {
            redirect('?route=duty-roster');
        }
        $this->requireDutyRosterEmployeeAccess($employeeId);

        $existing = $this->db->fetch(
            'SELECT id, shift_name FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date LIMIT 1',
            ['employee_id' => $employeeId, 'duty_date' => $dutyDate]
        );

        if ($existing && strtoupper((string)$existing['shift_name']) === 'OFF') {
            $this->db->execute('DELETE FROM duty_roster WHERE id=:id', ['id' => (int)$existing['id']]);
            flash('ok', 'Duty set to ON.');
        } else {
            // OFF override row (default state is ON when no override exists)
            if ($existing) {
                $this->db->execute('DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date', [
                    'employee_id' => $employeeId,
                    'duty_date' => $dutyDate,
                ]);
            }
            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at) VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $dutyDate,
                    'shift_name' => 'OFF',
                    'start_time' => '00:00',
                    'end_time' => '00:00',
                    'ward' => null,
                    'notes' => 'Quick OFF toggle',
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
            flash('ok', 'Duty set to OFF.');
        }

        redirect('?route=duty-roster&week_start=' . urlencode($weekStart) . '&show_month=' . $showMonth);
    }

    public function employeePortal(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }
        $myLeaves = $this->db->fetchAll(
            'SELECT * FROM leave_requests WHERE employee_id=:employee_id ORDER BY id DESC LIMIT 30',
            ['employee_id' => (int)$employee['id']]
        );
        $leaveAgg = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN LOWER(TRIM(status))='approved' THEN DATEDIFF(end_date,start_date)+1 ELSE 0 END) availed_days,
                SUM(CASE WHEN LOWER(TRIM(status))='pending' THEN 1 ELSE 0 END) pending_count,
                SUM(CASE WHEN LOWER(TRIM(status))='approved' THEN 1 ELSE 0 END) approved_count,
                SUM(CASE WHEN LOWER(TRIM(status))='rejected' THEN 1 ELSE 0 END) rejected_count
             FROM leave_requests
             WHERE employee_id=:employee_id",
            ['employee_id' => (int)$employee['id']]
        );
        $attendanceAgg = $this->db->fetch(
            "SELECT COUNT(*) absent_count
             FROM attendance a
             WHERE a.employee_id=:employee_id
               AND LOWER(TRIM(a.status))='absent'
               AND NOT EXISTS (
                   SELECT 1 FROM leave_requests lr
                   WHERE lr.employee_id=a.employee_id
                     AND lr.status='Approved'
                     AND lr.leave_type <> 'Permission'
                     AND a.attendance_date BETWEEN lr.start_date AND lr.end_date
               )",
            ['employee_id' => (int)$employee['id']]
        );

        $totalLeavesAvailable = 24;
        $availedDays = (int)($leaveAgg['availed_days'] ?? 0);
        $balanceDays = max(0, $totalLeavesAvailable - $availedDays);
        $leaveStats = [
            'available' => $totalLeavesAvailable,
            'availed' => $availedDays,
            'balance' => $balanceDays,
            'pending' => (int)($leaveAgg['pending_count'] ?? 0),
            'approved' => (int)($leaveAgg['approved_count'] ?? 0),
            'rejected' => (int)($leaveAgg['rejected_count'] ?? 0),
            'absent' => (int)($attendanceAgg['absent_count'] ?? 0),
        ];

        $myTraining = $this->db->fetchAll(
            'SELECT et.*, ts.title, ts.description, ts.session_date, ts.trainer_name, ts.location
             FROM employee_training et
             JOIN training_sessions ts ON ts.id = et.session_id
             WHERE et.employee_id=:employee_id
             ORDER BY FIELD(et.status, "Assigned", "In Progress", "Completed"), ts.session_date DESC, et.id DESC',
            ['employee_id' => (int)$employee['id']]
        );
        $trainingStats = $this->db->fetch(
            "SELECT COUNT(*) total,
                    SUM(status='Assigned') assigned,
                    SUM(status='In Progress') in_progress,
                    SUM(status='Completed') completed
             FROM employee_training
             WHERE employee_id=:employee_id",
            ['employee_id' => (int)$employee['id']]
        ) ?: ['total' => 0, 'assigned' => 0, 'in_progress' => 0, 'completed' => 0];

        $kpiStats = $this->employeeKpiStats((int)$employee['id'], (string)($employee['department'] ?? ''));
        $kpiHistory = $this->db->fetchAll(
            'SELECT review_date, kpi_score, review_notes FROM performance WHERE employee_id=:employee_id ORDER BY review_date DESC, id DESC LIMIT 8',
            ['employee_id' => (int)$employee['id']]
        );
        $onboarding = $this->db->fetch('SELECT * FROM employee_onboarding WHERE employee_id=:employee_id LIMIT 1', ['employee_id' => (int)$employee['id']]);
        if (!$onboarding) {
            $this->db->execute("INSERT INTO employee_onboarding(employee_id,start_date,status,created_at) VALUES(:employee_id,:start_date,'Pending',NOW())", [
                'employee_id' => (int)$employee['id'],
                'start_date' => ($employee['join_date'] ?? '') ?: date('Y-m-d'),
            ]);
            $onboarding = $this->db->fetch('SELECT * FROM employee_onboarding WHERE employee_id=:employee_id LIMIT 1', ['employee_id' => (int)$employee['id']]);
        }
        $attendancePreview = $this->employeeAttendanceCalendar((int)$employee['id'], date('Y-m'));
        $payslips = $this->db->fetchAll(
            'SELECT id,payroll_month,work_hours,payable_days,hourly_rate,basic_salary,allowances,deductions,net_salary,status,payment_date FROM payroll_records WHERE employee_id=:employee_id ORDER BY payroll_month DESC,id DESC LIMIT 12',
            ['employee_id' => (int)$employee['id']]
        );
        $documents = $this->db->fetchAll(
            'SELECT * FROM employee_documents WHERE employee_id=:employee_id ORDER BY uploaded_at DESC LIMIT 8',
            ['employee_id' => (int)$employee['id']]
        );
        $healthReports = $this->db->fetchAll(
            'SELECT * FROM employee_health_checkups WHERE employee_id=:employee_id ORDER BY checkup_date DESC, id DESC LIMIT 5',
            ['employee_id' => (int)$employee['id']]
        );
        $dashboardRosterStart = date('Y-m-d', strtotime('friday this week'));
        $dashboardRosterEnd = date('Y-m-d', strtotime($dashboardRosterStart . ' +6 days'));
        $dashboardRosterRange = fmt_date($dashboardRosterStart) . ' to ' . fmt_date($dashboardRosterEnd);
        $previousRosterStart = date('Y-m-d', strtotime($dashboardRosterStart . ' -7 days'));
        $previousRosterEnd = date('Y-m-d', strtotime($dashboardRosterStart . ' -1 day'));
        $previousRosterRange = fmt_date($previousRosterStart) . ' to ' . fmt_date($previousRosterEnd);
        $dashboardRosterItems = $this->db->fetchAll(
            'SELECT duty_date, shift_name, start_time, end_time, notes
             FROM duty_roster WHERE employee_id=:employee_id AND duty_date BETWEEN :start AND :end
             ORDER BY duty_date ASC, id ASC LIMIT 7',
            ['employee_id' => (int)$employee['id'], 'start' => $dashboardRosterStart, 'end' => $dashboardRosterEnd]
        );
        $previousRosterItems = $this->db->fetchAll(
            'SELECT duty_date, shift_name, start_time, end_time, notes
             FROM duty_roster WHERE employee_id=:employee_id AND duty_date BETWEEN :start AND :end
             ORDER BY duty_date ASC, id ASC LIMIT 7',
            ['employee_id' => (int)$employee['id'], 'start' => $previousRosterStart, 'end' => $previousRosterEnd]
        );

        View::render('employee/dashboard', compact('employee', 'myLeaves', 'leaveStats', 'myTraining', 'trainingStats', 'kpiStats', 'kpiHistory', 'onboarding', 'attendancePreview', 'payslips', 'documents', 'healthReports', 'dashboardRosterItems', 'dashboardRosterRange', 'dashboardRosterStart', 'previousRosterItems', 'previousRosterRange', 'previousRosterStart'));
    }

    public function employeeDocumentsPage(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $documents = $this->db->fetchAll(
            'SELECT * FROM employee_documents WHERE employee_id=:employee_id ORDER BY uploaded_at DESC',
            ['employee_id' => (int)$employee['id']]
        );
        $healthReports = $this->db->fetchAll(
            'SELECT * FROM employee_health_checkups WHERE employee_id=:employee_id ORDER BY checkup_date DESC, id DESC',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/documents', compact('employee', 'documents', 'healthReports'));
    }

    public function employeeDocumentStore(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $documentType = trim((string)($_POST['document_type'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $returnTo = trim((string)($_POST['return_to'] ?? 'employee-documents'));
        if (!in_array($returnTo, ['employee-documents', 'employee-onboarding', 'employee-profile'], true)) {
            $returnTo = 'employee-documents';
        }
        $filePath = $this->uploadHrFile('document_file', 'doc', $employee, 'certificates');
        if ($documentType === '' || !$filePath) {
            flash('error', 'Document type and file are required.');
            redirect('?route=' . $returnTo);
        }
        $fileMeta = $this->uploadedFileMetadata($filePath);
        $this->db->execute(
            "INSERT INTO employee_documents(employee_id,document_type,file_path,original_filename,stored_mime_type,file_size_bytes,verification_status,employee_remarks,uploaded_at) VALUES(:employee_id,:document_type,:file_path,:original_filename,:stored_mime_type,:file_size_bytes,'Pending',:employee_remarks,NOW())",
            [
                'employee_id' => (int)$employee['id'],
                'document_type' => $documentType,
                'file_path' => $filePath,
                'original_filename' => $_FILES['document_file']['name'] ?? null,
                'stored_mime_type' => $fileMeta['mime_type'],
                'file_size_bytes' => $fileMeta['size_bytes'],
                'employee_remarks' => $remarks ?: null,
            ]
        );
        $this->notifications->notifyRole('HR', 'Document Uploaded', trim($employee['first_name'] . ' ' . $employee['last_name']) . ' uploaded a document for verification.', 'document');
        flash('ok', 'Document uploaded for HR verification.');
        $returnSection = trim((string)($_POST['return_section'] ?? ''));
        $sectionSuffix = $returnTo === 'employee-profile' && $returnSection !== ''
            ? '&profile_section=' . urlencode($returnSection)
            : '';
        redirect('?route=' . $returnTo . $sectionSuffix);
    }

    public function employeePasswordUpdate(): void
    {
        $user = $this->auth->user();
        $employee = $this->currentEmployeeOrRedirect();
        $returnRoute = trim((string)($_POST['return_route'] ?? 'dashboard'));
        if (!preg_match('/^(dashboard|employee-[a-z-]+)$/', $returnRoute)) {
            $returnRoute = 'dashboard';
        }
        $redirectTarget = '?route=' . $returnRoute;
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            flash('error', 'New password must be at least 8 characters and match the confirmation.');
            redirect($redirectTarget);
        }
        $row = $this->db->fetch('SELECT password_hash FROM users WHERE id=:id LIMIT 1', ['id' => (int)$user['id']]);
        if (!$row || !password_verify($currentPassword, (string)$row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
            redirect($redirectTarget);
        }
        $this->db->execute('UPDATE users SET password_hash=:password_hash, force_password_change=0 WHERE id=:id', [
            'id' => (int)$user['id'],
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        $this->auth->clearPasswordChangeRequirement();
        $this->auditLog('employee_password_changed', 'employees', (int)$employee['id'], 'Employee changed password from dashboard');
        flash('ok', 'Password changed successfully.');
        redirect($redirectTarget);
    }

    public function employeePhotoUpdate(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $returnRoute = trim((string)($_POST['return_route'] ?? 'dashboard'));
        $redirectTarget = $returnRoute === 'employee-profile' ? '?route=employee-profile' : '?route=dashboard#employee-profile';
        $photoPath = $this->uploadCapturedPhoto('photo_capture_data', $employee) ?: $this->uploadPhoto('photo_camera', $employee) ?: $this->uploadPhoto('photo_file', $employee) ?: $this->uploadPhoto('photo', $employee);
        if (!$photoPath) {
            flash('error', 'Please choose or capture a valid profile photo.');
            redirect($redirectTarget);
        }
        $this->db->execute(
            'UPDATE employees SET photo_path=:photo_path WHERE id=:id',
            ['photo_path' => $photoPath, 'id' => (int)$employee['id']]
        );
        $this->deleteUploadedFile((string)($employee['photo_path'] ?? ''), $photoPath);
        flash('ok', 'Successfully Updated');
        redirect($redirectTarget);
    }

    public function employeePayslip(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }

        $id = (int)($_GET['id'] ?? 0);
        $params = ['employee_id' => (int)$employee['id']];
        $where = 'p.employee_id=:employee_id';
        if ($id > 0) {
            $where .= ' AND p.id=:id';
            $params['id'] = $id;
        }
        $payslip = $this->db->fetch(
            'SELECT p.*,e.employee_code,e.first_name,e.last_name,e.department,e.position,e.location,e.email FROM payroll_records p JOIN employees e ON e.id=p.employee_id WHERE ' . $where . ' ORDER BY p.payroll_month DESC,p.id DESC LIMIT 1',
            $params
        );
        if (!$payslip) {
            flash('error', 'Payslip not available.');
            redirect('?route=dashboard');
        }

        View::render('employee/payslip', compact('employee', 'payslip'));
    }

    public function employeeAvailedHistory(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }

        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 0 || $month > 12) {
            $month = 0;
        }

        $where = "employee_id=:employee_id AND leave_type <> 'Permission' AND status='Approved' AND YEAR(start_date)=:year";
        $params = ['employee_id' => (int)$employee['id'], 'year' => $year];
        if ($month > 0) {
            $where .= " AND MONTH(start_date)=:month";
            $params['month'] = $month;
        }

        $items = $this->db->fetchAll(
            "SELECT id, leave_type, start_date, end_date, reason, status,
                    (DATEDIFF(end_date,start_date)+1) AS days_count
             FROM leave_requests
             WHERE {$where}
             ORDER BY start_date DESC, id DESC",
            $params
        );

        $summary = $this->db->fetchAll(
            "SELECT DATE_FORMAT(start_date,'%Y-%m') ym,
                    COUNT(*) total_requests,
                    SUM(DATEDIFF(end_date,start_date)+1) total_days
             FROM leave_requests
             WHERE employee_id=:employee_id
               AND leave_type <> 'Permission'
               AND status='Approved'
             GROUP BY DATE_FORMAT(start_date,'%Y-%m')
             ORDER BY ym DESC",
            ['employee_id' => (int)$employee['id']]
        );

        View::render('employee/availed_history', compact('employee', 'items', 'summary', 'year', 'month'));
    }

    public function employeeAttendance(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }

        $viewMode = ($_GET['view_mode'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';
        $weekDate = $this->normalizeDate($_GET['week_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
        $requestedMonth = $_GET['month'] ?? substr($weekDate, 0, 7);
        if ($viewMode === 'weekly') {
            $requestedMonth = substr($weekDate, 0, 7);
        }
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekDate)));
        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($weekDate)));
        $attendanceData = $this->employeeAttendanceCalendar(
            (int)$employee['id'],
            $requestedMonth,
            $viewMode === 'weekly' ? $weekStart : null,
            $viewMode === 'weekly' ? $weekEnd : null
        );
        $month = $attendanceData['month'];
        $monthStart = $attendanceData['monthStart'];
        $monthEnd = $attendanceData['monthEnd'];
        $calendar = $attendanceData['calendar'];
        $rangeStart = $viewMode === 'weekly' ? $weekStart : $monthStart;
        $rangeEnd = $viewMode === 'weekly' ? $weekEnd : $monthEnd;
        $report = $this->employeeAttendanceReportData((int)$employee['id'], $month, $rangeStart, $rangeEnd);
        $todayRows = $this->calculatedAttendanceStatusRows(date('Y-m-d'), date('Y-m-d'), (int)$employee['id'], true);
        $todayAttendance = $todayRows[0] ?? null;
        $todayStatus = $todayAttendance['live_status'] ?? 'Not Marked';

        View::render('employee/attendance', compact('employee', 'month', 'monthStart', 'monthEnd', 'calendar', 'report', 'viewMode', 'weekDate', 'weekStart', 'weekEnd', 'rangeStart', 'rangeEnd', 'todayStatus', 'todayAttendance'));
    }

    public function employeeAttendanceCorrection(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $requests = $this->db->fetchAll(
            'SELECT * FROM employee_attendance_corrections WHERE employee_id=:employee_id ORDER BY created_at DESC, id DESC LIMIT 30',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/attendance_correction', compact('employee', 'requests'));
    }

    public function employeeAttendanceCorrectionStore(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $correctionDate = $this->normalizeDate($_POST['correction_date'] ?? '');
        $correctionTime = trim((string)($_POST['correction_time'] ?? ''));
        $correctionType = trim((string)($_POST['correction_type'] ?? ''));
        $justification = trim((string)($_POST['justification'] ?? ''));
        $validTypes = ['Check In', 'Check Out', 'Status Correction', 'Other'];

        if (!$correctionDate || !preg_match('/^\d{2}:\d{2}$/', $correctionTime) || !in_array($correctionType, $validTypes, true) || $justification === '') {
            flash('error', 'Date, time, correction type, and justification are required.');
            redirect('?route=employee-attendance-correction');
        }

        $this->db->execute(
            "INSERT INTO employee_attendance_corrections(employee_id,correction_date,correction_time,correction_type,justification,attachment_path,original_filename,status,created_at)
             VALUES(:employee_id,:correction_date,:correction_time,:correction_type,:justification,:attachment_path,:original_filename,'Pending',NOW())",
            [
                'employee_id' => (int)$employee['id'],
                'correction_date' => $correctionDate,
                'correction_time' => $correctionTime,
                'correction_type' => $correctionType,
                'justification' => $justification,
                'attachment_path' => null,
                'original_filename' => null,
            ]
        );

        $employeeName = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
        $this->notifications->notifyRole(
            'HR',
            'Attendance Correction Pending',
            ($employeeName !== '' ? $employeeName : 'An employee') . ' submitted a time attendance correction request for ' . $correctionDate . '.',
            'attendance'
        );
        flash('ok', 'Attendance correction request submitted.');
        redirect('?route=employee-attendance-correction');
    }

    public function employeeOnboardingPage(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $onboarding = $this->db->fetch('SELECT * FROM employee_onboarding WHERE employee_id=:employee_id LIMIT 1', ['employee_id' => (int)$employee['id']]);
        if (!$onboarding) {
            $this->db->execute("INSERT INTO employee_onboarding(employee_id,start_date,status,created_at) VALUES(:employee_id,:start_date,'Pending',NOW())", [
                'employee_id' => (int)$employee['id'],
                'start_date' => ($employee['join_date'] ?? '') ?: date('Y-m-d'),
            ]);
            $onboarding = $this->db->fetch('SELECT * FROM employee_onboarding WHERE employee_id=:employee_id LIMIT 1', ['employee_id' => (int)$employee['id']]);
        }
        $documents = $this->db->fetchAll(
            'SELECT * FROM employee_documents WHERE employee_id=:employee_id ORDER BY uploaded_at DESC LIMIT 20',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/onboarding', compact('employee', 'onboarding', 'documents'));
    }

    public function employeeProfilePage(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $profileSection = trim((string)($_GET['profile_section'] ?? 'personal'));
        if (!in_array($profileSection, ['personal', 'educational', 'bank', 'communication'], true)) {
            $profileSection = 'personal';
        }
        $rosterFromInput = (string)($_GET['roster_from'] ?? date('Y-m-01'));
        $rosterToInput = (string)($_GET['roster_to'] ?? date('Y-m-t'));
        $rosterStart = strtotime($rosterFromInput) ? date('Y-m-d', strtotime($rosterFromInput)) : date('Y-m-01');
        $rosterEnd = strtotime($rosterToInput) ? date('Y-m-d', strtotime($rosterToInput)) : date('Y-m-t');
        if ($rosterStart > $rosterEnd) {
            [$rosterStart, $rosterEnd] = [$rosterEnd, $rosterStart];
        }
        if (strtotime($rosterEnd) > strtotime($rosterStart . ' +1 year')) {
            $rosterEnd = date('Y-m-d', strtotime($rosterStart . ' +1 year'));
        }

        $rosterItems = $this->db->fetchAll(
            'SELECT duty_date, shift_name, start_time, end_time, ward, notes
             FROM duty_roster
             WHERE employee_id=:employee_id AND duty_date BETWEEN :start AND :end
             ORDER BY duty_date ASC, id ASC',
            [
                'employee_id' => (int)$employee['id'],
                'start' => $rosterStart,
                'end' => $rosterEnd,
            ]
        );
        $rosterRange = [
            'start' => $rosterStart,
            'end' => $rosterEnd,
            'label' => fmt_date($rosterStart) . ' to ' . fmt_date($rosterEnd),
        ];
        $internalRequests = $this->db->fetchAll(
            'SELECT * FROM employee_internal_requests WHERE employee_id=:employee_id ORDER BY created_at DESC, id DESC LIMIT 8',
            ['employee_id' => (int)$employee['id']]
        );
        $documents = $this->db->fetchAll(
            'SELECT * FROM employee_documents WHERE employee_id=:employee_id ORDER BY uploaded_at DESC, id DESC',
            ['employee_id' => (int)$employee['id']]
        );

        View::render('employee/profile', compact('employee', 'rosterItems', 'rosterRange', 'internalRequests', 'documents', 'profileSection'));
    }

    public function employeeDutyRosterPage(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $period = trim((string)($_GET['period'] ?? 'current')) === 'previous' ? 'previous' : 'current';
        $currentStart = date('Y-m-d', strtotime('friday this week'));
        $start = $period === 'previous' ? date('Y-m-d', strtotime($currentStart . ' -7 days')) : $currentStart;
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        $items = $this->db->fetchAll(
            'SELECT duty_date, shift_name, start_time, end_time, ward, notes
             FROM duty_roster WHERE employee_id=:employee_id AND duty_date BETWEEN :start AND :end
             ORDER BY duty_date ASC, id ASC LIMIT 7',
            ['employee_id' => (int)$employee['id'], 'start' => $start, 'end' => $end]
        );
        View::render('employee/duty_roster', compact('employee', 'items', 'period', 'start', 'end'));
    }

    public function employeeInternalRequestStore(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $requestType = trim((string)($_POST['request_type'] ?? ''));
        $toRole = trim((string)($_POST['to_role'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $returnRoute = trim((string)($_POST['return_route'] ?? 'employee-profile'));
        $allowedTypes = ['Suggestion', 'Query', 'Resignation', 'Feedback', 'Complaint', 'Employee Certificate', 'Salary Certificate'];
        $allowedRoles = ['Admin', 'HR', 'HOD'];
        $allowedReturnRoutes = ['employee-profile', 'employee-portal', 'employee-certificates'];
        if (!in_array($returnRoute, $allowedReturnRoutes, true)) {
            $returnRoute = 'employee-profile';
        }

        if (!in_array($requestType, $allowedTypes, true) || !in_array($toRole, $allowedRoles, true) || $message === '') {
            flash('error', 'Choose request type, recipient, and message.');
            redirect('?route=' . $returnRoute . '#internal-request');
        }
        if (in_array($requestType, ['Employee Certificate', 'Salary Certificate'], true)) {
            $toRole = 'HR';
        }
        $subject = $requestType . ' Request';

        $this->db->execute(
            "INSERT INTO employee_internal_requests(employee_id,request_type,to_role,subject,message,status,created_at)
             VALUES(:employee_id,:request_type,:to_role,:subject,:message,'Open',NOW())",
            [
                'employee_id' => (int)$employee['id'],
                'request_type' => $requestType,
                'to_role' => $toRole,
                'subject' => $subject,
                'message' => $message,
            ]
        );
        $requestId = (int)$this->db->pdo()->lastInsertId();

        $employeeName = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
        $this->notifications->notifyRole(
            $toRole,
            $requestType . ' from ' . ($employeeName !== '' ? $employeeName : 'Employee'),
            $subject . ' - ' . substr($message, 0, 180),
            'internal_request'
        );

        $this->auditLog('employee_internal_request_created', 'employee_internal_requests', $requestId, $requestType . ' sent to ' . $toRole);
        flash('ok', 'Request sent to ' . $toRole . '.');
        redirect('?route=' . $returnRoute . '#internal-request');
    }

    public function employeeOnboardingProfileUpdate(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $returnRoute = trim((string)($_POST['return_route'] ?? 'employee-onboarding'));
        if (!in_array($returnRoute, ['employee-profile', 'employee-onboarding'], true)) {
            $returnRoute = 'employee-onboarding';
        }
        $allFields = [
            'date_of_birth', 'gender', 'marital_status', 'blood_group', 'email', 'phone',
            'aadhaar_number', 'esi_number', 'pf_number',
            'address_line', 'door_no', 'street', 'locality', 'city', 'state', 'pincode', 'join_date', 'employment_type',
            'qualification', 'specialization', 'years_experience', 'license_number',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
            'bank_name', 'bank_account_no', 'ifsc_code',
        ];
        $profileSection = trim((string)($_POST['profile_section'] ?? 'personal'));
        $sectionFields = [
            'personal' => ['date_of_birth', 'gender', 'marital_status', 'blood_group', 'aadhaar_number', 'esi_number', 'pf_number', 'email', 'phone', 'address_line', 'door_no', 'street', 'locality', 'city', 'state', 'pincode', 'join_date', 'employment_type', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation'],
            'educational' => ['qualification', 'specialization', 'years_experience', 'license_number'],
            'bank' => ['bank_name', 'bank_account_no', 'ifsc_code'],
        ];
        if (!isset($sectionFields[$profileSection])) {
            $profileSection = 'personal';
        }
        $fields = array_values(array_intersect($allFields, $sectionFields[$profileSection]));
        $params = ['id' => (int)$employee['id']];
        $sets = [];
        foreach ($fields as $field) {
            $params[$field] = trim((string)($_POST[$field] ?? '')) ?: null;
            $sets[] = $field . '=:' . $field;
        }
        $this->db->execute('UPDATE employees SET ' . implode(',', $sets) . ' WHERE id=:id', $params);
        flash('ok', $returnRoute === 'employee-profile' ? 'Profile updated.' : 'Onboarding details saved. You can update remaining details later.');
        redirect('?route=' . $returnRoute . '&profile_section=' . urlencode($profileSection));
    }

    public function employeeTrainingKpiPage(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $myTraining = $this->db->fetchAll(
            'SELECT et.*, ts.title, ts.description, ts.session_date, ts.trainer_name, ts.location
             FROM employee_training et
             JOIN training_sessions ts ON ts.id=et.session_id
             WHERE et.employee_id=:employee_id
             ORDER BY ts.session_date DESC, et.id DESC',
            ['employee_id' => (int)$employee['id']]
        );
        $trainingStats = $this->db->fetch(
            "SELECT COUNT(*) total,
                    SUM(status='Assigned') assigned,
                    SUM(status='In Progress') in_progress,
                    SUM(status='Completed') completed
             FROM employee_training
             WHERE employee_id=:employee_id",
            ['employee_id' => (int)$employee['id']]
        ) ?: ['total' => 0, 'assigned' => 0, 'in_progress' => 0, 'completed' => 0];
        $kpiStats = $this->employeeKpiStats((int)$employee['id'], (string)($employee['department'] ?? ''));
        $kpiHistory = $this->db->fetchAll(
            'SELECT review_date, kpi_score, review_notes FROM performance WHERE employee_id=:employee_id ORDER BY review_date DESC, id DESC LIMIT 8',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/training_kpi', compact('employee', 'myTraining', 'trainingStats', 'kpiStats', 'kpiHistory'));
    }

    public function employeeCertificates(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $latestPayslip = $this->db->fetch(
            'SELECT * FROM payroll_records WHERE employee_id=:employee_id ORDER BY payroll_month DESC,id DESC LIMIT 1',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/certificates', compact('employee', 'latestPayslip'));
    }

    public function employeeCertificate(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $type = (string)($_GET['type'] ?? 'to_whom');
        if (!in_array($type, ['to_whom', 'salary'], true)) {
            $type = 'to_whom';
        }
        $latestPayslip = $this->db->fetch(
            'SELECT * FROM payroll_records WHERE employee_id=:employee_id ORDER BY payroll_month DESC,id DESC LIMIT 1',
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/certificate', compact('employee', 'type', 'latestPayslip'));
    }

    public function employeeLeavePage(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) { flash('error', 'Employee profile not found.'); redirect('?route=dashboard'); }
        $leaveStandards = $this->leavePermissionStandards();
        $myLeaves = $this->db->fetchAll(
            "SELECT * FROM leave_requests WHERE employee_id=:employee_id AND leave_type <> 'Permission' ORDER BY id DESC LIMIT 100",
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/leave', compact('employee', 'myLeaves', 'leaveStandards'));
    }

    public function employeePermissionPage(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) { flash('error', 'Employee profile not found.'); redirect('?route=dashboard'); }
        $myPermissions = $this->db->fetchAll(
            "SELECT * FROM leave_requests WHERE employee_id=:employee_id AND leave_type = 'Permission' ORDER BY id DESC LIMIT 100",
            ['employee_id' => (int)$employee['id']]
        );
        View::render('employee/permission', compact('employee', 'myPermissions'));
    }

    public function employeeLeavePermissionPage(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) { flash('error', 'Employee profile not found.'); redirect('?route=dashboard'); }
        $leaveStandards = $this->leavePermissionStandards();
        $myLeaves = $this->db->fetchAll(
            "SELECT * FROM leave_requests WHERE employee_id=:employee_id AND leave_type <> 'Permission' ORDER BY id DESC LIMIT 100",
            ['employee_id' => (int)$employee['id']]
        );
        $myPermissions = $this->db->fetchAll(
            "SELECT * FROM leave_requests WHERE employee_id=:employee_id AND leave_type = 'Permission' ORDER BY id DESC LIMIT 100",
            ['employee_id' => (int)$employee['id']]
        );
        $leaveBalance = $this->employeeLeaveBalanceSummary((int)$employee['id'], $leaveStandards);
        $permissionBalance = $this->employeePermissionBalanceSummary((int)$employee['id'], $leaveStandards);
        $requestStartDate = trim((string)($_GET['start_date'] ?? ''));
        $requestEndDate = trim((string)($_GET['end_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestStartDate)) $requestStartDate = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestEndDate)) $requestEndDate = $requestStartDate;
        $requestToday = date('Y-m-d');
        if ($requestStartDate !== '' && $requestStartDate < $requestToday) $requestStartDate = '';
        if ($requestEndDate !== '' && $requestEndDate < $requestToday) $requestEndDate = $requestStartDate;
        View::render('employee/leave_permission', compact('employee', 'myLeaves', 'myPermissions', 'leaveStandards', 'leaveBalance', 'permissionBalance', 'requestStartDate', 'requestEndDate'));
    }

    public function employeeLeaveStore(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT id, first_name, last_name FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }
        $requestCategory = trim((string)($_POST['request_category'] ?? 'Leave'));
        $standards = $this->leavePermissionStandards();
        $leaveType = strcasecmp($requestCategory, 'Permission') === 0
            ? 'Permission'
            : trim((string)($_POST['leave_type'] ?? ''));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $permissionStartTime = null;
        $permissionEndTime = null;
        $requiresMedicalCertificate = false;

        if (strcasecmp($requestCategory, 'Permission') === 0 && $endDate === '') {
            $endDate = $startDate;
        }

        if ($leaveType === '' || $startDate === '' || $endDate === '') {
            flash('error', 'Request type and date are required.');
            redirect('?route=employee-leave-permission');
        }

        $todayDate = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $startDate < $todayDate || $endDate < $todayDate || $endDate < $startDate) {
            flash('error', 'Leave or permission can be applied only for today or upcoming dates.');
            redirect('?route=employee-leave-permission');
        }

        if (strcasecmp($leaveType, 'Permission') === 0) {
            $permissionStartTime = trim((string)($_POST['permission_start_time'] ?? ''));
            $permissionEndTime = trim((string)($_POST['permission_end_time'] ?? ''));

            if ($startDate !== $endDate) {
                flash('error', 'Permission is allowed only for a single day.');
                redirect('?route=employee-leave-permission');
            }
            if ($permissionStartTime === '' || $permissionEndTime === '') {
                flash('error', 'Permission start and end time are required.');
                redirect('?route=employee-leave-permission');
            }

            $permissionStartTime = $this->normalizeTime($permissionStartTime) ?? '';
            $permissionEndTime = $this->normalizeTime($permissionEndTime) ?? '';
            $startTs = strtotime($startDate . ' ' . $permissionStartTime);
            $endTs = strtotime($endDate . ' ' . $permissionEndTime);
            if ($startTs === false || $endTs === false) {
                flash('error', 'Invalid permission time range.');
                redirect('?route=employee-leave-permission');
            }
            if ($endTs <= $startTs) {
                $endTs += 86400;
            }
            $hours = ($endTs - $startTs) / 3600;
            if ($hours < 0.5 || $hours > 2.0) {
                flash('error', 'Permission duration must be between 30 minutes and 2 hours.');
                redirect('?route=employee-leave-permission');
            }
            $shiftError = $this->permissionShiftTimingError((int)$employee['id'], $startDate, $permissionStartTime, $permissionEndTime);
            if ($shiftError !== null) {
                flash('error', $shiftError);
                redirect('?route=employee-leave-permission');
            }

            $monthStart = date('Y-m-01', strtotime($startDate));
            $monthEnd = date('Y-m-t', strtotime($startDate));
            $monthlyPermissionCount = (int)($this->db->fetch(
                "SELECT COUNT(*) c
                 FROM leave_requests
                 WHERE employee_id=:employee_id
                   AND leave_type='Permission'
                   AND status <> 'Rejected'
                   AND start_date BETWEEN :month_start AND :month_end",
                [
                    'employee_id' => (int)$employee['id'],
                    'month_start' => $monthStart,
                    'month_end' => $monthEnd,
                ]
            )['c'] ?? 0);
            if ($monthlyPermissionCount >= 4) {
                flash('error', 'Permission limit exceeded: only 4 requests are allowed in this month.');
                redirect('?route=employee-leave-permission');
            }
            $monthlyPermissionHours = (float)($this->db->fetch(
                "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(permission_end_time, permission_start_time)) / 3600),0) hours_used
                 FROM leave_requests
                 WHERE employee_id=:employee_id
                   AND leave_type='Permission'
                   AND status <> 'Rejected'
                   AND start_date BETWEEN :month_start AND :month_end",
                [
                    'employee_id' => (int)$employee['id'],
                    'month_start' => $monthStart,
                    'month_end' => $monthEnd,
                ]
            )['hours_used'] ?? 0);
            if (($monthlyPermissionHours + $hours) > (float)$standards['monthly_permission_quota_hours']) {
                flash('error', 'Permission quota exceeded. Remaining permission time: ' . max(0, (float)$standards['monthly_permission_quota_hours'] - $monthlyPermissionHours) . ' hour(s).');
                redirect('?route=employee-leave-permission');
            }

            $leaveOnSameDay = (int)($this->db->fetch(
                "SELECT COUNT(*) c
                 FROM leave_requests
                 WHERE employee_id=:employee_id
                   AND leave_type <> 'Permission'
                   AND status IN ('Pending','Approved')
                   AND :permission_day BETWEEN start_date AND end_date",
                [
                    'employee_id' => (int)$employee['id'],
                    'permission_day' => $startDate,
                ]
            )['c'] ?? 0);
            if ($leaveOnSameDay > 0) {
                flash('error', 'Permission not allowed: leave already exists on this date.');
                redirect('?route=employee-leave-permission');
            }
        } else {
            $requestedDays = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
            if ($requestedDays < 1 || $requestedDays > 366) {
                flash('error', 'Invalid leave date range.');
                redirect('?route=employee-leave-permission');
            }
            $requiresMedicalCertificate = $this->isMedicalLeaveType($leaveType) && $requestedDays > (int)$standards['medical_certificate_after_days'];
            $leaveBalanceWhere = 'employee_id=:employee_id AND leave_type <> \'Permission\' AND status IN (\'Pending\',\'Approved\')';
            $leaveBalanceParams = ['employee_id' => (int)$employee['id']];
            if (stripos($leaveType, 'casual') !== false) {
                $leaveBalanceWhere .= ' AND LOWER(leave_type) LIKE :leave_type_match';
                $leaveBalanceParams['leave_type_match'] = '%casual%';
            } elseif ($this->isMedicalLeaveType($leaveType)) {
                $leaveBalanceWhere .= " AND (LOWER(leave_type) LIKE '%medical%' OR LOWER(leave_type) LIKE '%sick%')";
            }
            $usedLeave = (int)($this->db->fetch(
                "SELECT COALESCE(SUM(DATEDIFF(end_date,start_date)+1),0) days_used
                 FROM leave_requests
                 WHERE {$leaveBalanceWhere}",
                $leaveBalanceParams
            )['days_used'] ?? 0);
            $annualEntitlement = $this->annualEntitlementForLeaveType($leaveType, $standards);
            if ($usedLeave + $requestedDays > $annualEntitlement) {
                flash('error', 'Leave balance exceeded. Available balance: ' . max(0, $annualEntitlement - $usedLeave) . ' day(s).');
                redirect('?route=employee-leave-permission');
            }
            $permissionOverlap = (int)($this->db->fetch(
                "SELECT COUNT(*) c
                 FROM leave_requests
                 WHERE employee_id=:employee_id
                   AND leave_type='Permission'
                   AND status IN ('Pending','Approved')
                   AND start_date BETWEEN :start_date AND :end_date",
                [
                    'employee_id' => (int)$employee['id'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            )['c'] ?? 0);
            if ($permissionOverlap > 0) {
                flash('error', 'Leave not allowed: permission already exists on one or more selected dates.');
                redirect('?route=employee-leave-permission');
            }
        }

        if ($requiresMedicalCertificate) {
            $certificatePath = $this->uploadHrFile('medical_certificate', 'medical_certificate', $employee, 'certificates');
            if (!$certificatePath) {
                flash('error', 'Medical certificate upload is required for medical leave longer than ' . (int)$standards['medical_certificate_after_days'] . ' days.');
                redirect('?route=employee-leave-permission');
            }
            $certificateMeta = $this->uploadedFileMetadata($certificatePath);
            $this->db->execute(
                "INSERT INTO employee_documents(employee_id,document_type,file_path,original_filename,stored_mime_type,file_size_bytes,verification_status,employee_remarks,uploaded_at)
                 VALUES(:employee_id,'Medical Leave Certificate',:file_path,:original_filename,:stored_mime_type,:file_size_bytes,'Pending',:employee_remarks,NOW())",
                [
                    'employee_id' => (int)$employee['id'],
                    'file_path' => $certificatePath,
                    'original_filename' => $_FILES['medical_certificate']['name'] ?? null,
                    'stored_mime_type' => $certificateMeta['mime_type'],
                    'file_size_bytes' => $certificateMeta['size_bytes'],
                    'employee_remarks' => 'Uploaded with medical leave request for ' . $startDate . ' to ' . $endDate,
                ]
            );
        }

        $this->db->execute(
            'INSERT INTO leave_requests(employee_id,leave_type,start_date,end_date,reason,permission_start_time,permission_end_time,status,approval_level,created_at) VALUES(:employee_id,:leave_type,:start_date,:end_date,:reason,:permission_start_time,:permission_end_time,:status,1,NOW())',
            [
                'employee_id' => (int)$employee['id'],
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'permission_start_time' => $permissionStartTime ?: null,
                'permission_end_time' => $permissionEndTime ?: null,
                'status' => 'Pending',
            ]
        );
        $employeeName = trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''));
        $requestLabel = strcasecmp($leaveType, 'Permission') === 0 ? 'permission' : 'leave';
        $this->notifications->notifyRoles(
            $this->leavePermissionApprovalHierarchyConfig()['level1_roles'],
            'Leave Request Pending',
            ($employeeName !== '' ? $employeeName : 'An employee') . ' submitted a ' . $requestLabel . ' request for HR approval.',
            'leave'
        );
        flash('ok', 'Leave request submitted.');
        redirect('?route=employee-leave-permission');
    }

    public function employeeDocumentDownload(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $id = (int)($_GET['id'] ?? 0);
        $doc = $this->db->fetch('SELECT * FROM employee_documents WHERE id=:id AND employee_id=:employee_id LIMIT 1', ['id' => $id, 'employee_id' => (int)$employee['id']]);
        if (!$doc) {
            http_response_code(404);
            exit('Document not found');
        }
        $this->downloadUploadedFile((string)$doc['file_path'], (string)($doc['original_filename'] ?: basename((string)$doc['file_path'])));
    }

    public function employeeHealthDownload(): void
    {
        $employee = $this->currentEmployeeOrRedirect();
        $id = (int)($_GET['id'] ?? 0);
        $report = $this->db->fetch('SELECT * FROM employee_health_checkups WHERE id=:id AND employee_id=:employee_id LIMIT 1', ['id' => $id, 'employee_id' => (int)$employee['id']]);
        if (!$report) {
            http_response_code(404);
            exit('Health report not found');
        }
        $this->downloadUploadedFile((string)$report['report_file_path'], (string)($report['original_filename'] ?: basename((string)$report['report_file_path'])));
    }

    public function documents(): void
    {
        $this->ensureWorkforceFeatureTables();
        $status = trim((string)($_GET['status'] ?? ''));
        $params = [];
        $where = '';
        if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            $where = ' WHERE d.verification_status=:status';
            $params['status'] = $status;
        }
        $items = $this->db->fetchAll(
            'SELECT d.*, e.employee_code, e.first_name, e.last_name, e.department, e.position, u.name verifier_name
             FROM employee_documents d
             JOIN employees e ON e.id=d.employee_id
             LEFT JOIN users u ON u.id=d.verified_by
             ' . $where . '
             ORDER BY FIELD(d.verification_status,"Pending","Rejected","Approved"), d.uploaded_at DESC',
            $params
        );
        $summary = $this->db->fetch("SELECT
            SUM(verification_status='Pending') pending,
            SUM(verification_status='Approved') approved,
            SUM(verification_status='Rejected') rejected,
            COUNT(*) total
            FROM employee_documents") ?: ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        $employees = $this->db->fetchAll('SELECT id, employee_code, first_name, last_name, department, position FROM employees ORDER BY first_name,last_name');
        $credentials = $this->db->fetchAll(
            'SELECT c.*, e.employee_code, e.first_name, e.last_name, e.department, e.position
             FROM employee_credentials c
             JOIN employees e ON e.id=c.employee_id
             ORDER BY CASE WHEN c.expiry_date IS NULL THEN 1 ELSE 0 END, c.expiry_date ASC, c.id DESC'
        );
        $credentialSummary = $this->db->fetch(
            "SELECT
                COUNT(*) total,
                SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) expired,
                SUM(expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) expiring_30,
                SUM(expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)) expiring_60
             FROM employee_credentials"
        ) ?: ['total' => 0, 'expired' => 0, 'expiring_30' => 0, 'expiring_60' => 0];
        View::render('documents/index', compact('items', 'summary', 'status', 'employees', 'credentials', 'credentialSummary'));
    }

    public function credentialStore(): void
    {
        $this->ensureWorkforceFeatureTables();
        $id = (int)($_POST['id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $credentialType = trim((string)($_POST['credential_type'] ?? ''));
        $expiryDate = trim((string)($_POST['expiry_date'] ?? ''));
        if ($employeeId <= 0 || $credentialType === '') {
            flash('error', 'Employee and credential type are required.');
            redirect('?route=documents#credentials');
        }
        $params = [
            'employee_id' => $employeeId,
            'credential_type' => $credentialType,
            'credential_number' => trim((string)($_POST['credential_number'] ?? '')) ?: null,
            'issuing_authority' => trim((string)($_POST['issuing_authority'] ?? '')) ?: null,
            'issue_date' => trim((string)($_POST['issue_date'] ?? '')) ?: null,
            'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
            'status' => trim((string)($_POST['status'] ?? 'Active')) ?: 'Active',
            'remarks' => trim((string)($_POST['remarks'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            $params['id'] = $id;
            $this->db->execute(
                'UPDATE employee_credentials SET employee_id=:employee_id,credential_type=:credential_type,credential_number=:credential_number,issuing_authority=:issuing_authority,issue_date=:issue_date,expiry_date=:expiry_date,status=:status,remarks=:remarks,updated_at=NOW() WHERE id=:id',
                $params
            );
            flash('ok', 'Credential updated.');
        } else {
            $params['created_by'] = (int)$this->auth->user()['id'];
            $this->db->execute(
                'INSERT INTO employee_credentials(employee_id,credential_type,credential_number,issuing_authority,issue_date,expiry_date,status,remarks,created_by,created_at) VALUES(:employee_id,:credential_type,:credential_number,:issuing_authority,:issue_date,:expiry_date,:status,:remarks,:created_by,NOW())',
                $params
            );
            flash('ok', 'Credential added.');
        }
        if ($expiryDate !== '' && strtotime($expiryDate) !== false && strtotime($expiryDate) <= strtotime('+30 days')) {
            $this->notifications->notifyRoles(['HR', 'HOD'], 'Credential Expiry Alert', $credentialType . ' expires on ' . $expiryDate . '.', 'credential_expiry');
        }
        redirect('?route=documents#credentials');
    }

    public function credentialDelete(): void
    {
        $this->ensureWorkforceFeatureTables();
        $this->db->execute('DELETE FROM employee_credentials WHERE id=:id', ['id' => (int)($_POST['id'] ?? 0)]);
        flash('ok', 'Credential deleted.');
        redirect('?route=documents#credentials');
    }

    public function documentUpdate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim((string)($_POST['verification_status'] ?? 'Pending'));
        if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            $status = 'Pending';
        }
        $remarks = trim((string)($_POST['hr_remarks'] ?? ''));
        $this->db->execute(
            'UPDATE employee_documents SET verification_status=:verification_status,hr_remarks=:hr_remarks,verified_by=:verified_by,verified_at=NOW() WHERE id=:id',
            [
                'id' => $id,
                'verification_status' => $status,
                'hr_remarks' => $remarks ?: null,
                'verified_by' => (int)$this->auth->user()['id'],
            ]
        );
        $doc = $this->db->fetch('SELECT employee_id, document_type FROM employee_documents WHERE id=:id', ['id' => $id]);
        if ($doc) {
            $this->notifications->notifyEmployee((int)$doc['employee_id'], 'Document ' . $status, 'Your ' . $doc['document_type'] . ' document is ' . strtolower($status) . '.', 'document');
        }
        flash('ok', 'Document verification updated.');
        redirect('?route=documents');
    }

    public function documentDownload(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $doc = $this->db->fetch('SELECT * FROM employee_documents WHERE id=:id LIMIT 1', ['id' => $id]);
        if (!$doc) {
            http_response_code(404);
            exit('Document not found');
        }
        $this->downloadUploadedFile((string)$doc['file_path'], (string)($doc['original_filename'] ?: basename((string)$doc['file_path'])));
    }

    public function healthCheckups(): void
    {
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $params = [];
        $where = '';
        if ($employeeId > 0) {
            $where = ' WHERE h.employee_id=:employee_id';
            $params['employee_id'] = $employeeId;
        }
        $items = $this->db->fetchAll(
            'SELECT h.*, e.employee_code, e.first_name, e.last_name, e.department, e.position
             FROM employee_health_checkups h
             JOIN employees e ON e.id=h.employee_id
             ' . $where . '
             ORDER BY h.next_due_date ASC, h.checkup_date DESC, h.id DESC',
            $params
        );
        $employees = $this->db->fetchAll('SELECT id, employee_code, first_name, last_name, department FROM employees ORDER BY first_name, last_name');
        $summary = $this->db->fetch("SELECT
            COUNT(*) total,
            SUM(next_due_date IS NOT NULL AND next_due_date < CURDATE()) overdue,
            SUM(next_due_date IS NOT NULL AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) due_soon
            FROM employee_health_checkups") ?: ['total' => 0, 'overdue' => 0, 'due_soon' => 0];
        View::render('health/index', compact('items', 'employees', 'summary', 'employeeId'));
    }

    public function healthCheckupStore(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $checkupDate = trim((string)($_POST['checkup_date'] ?? ''));
        $hospitalName = trim((string)($_POST['hospital_name'] ?? ''));
        if ($employeeId <= 0 || $checkupDate === '' || $hospitalName === '') {
            flash('error', 'Employee, checkup date, and hospital name are required.');
            redirect('?route=health-checkups');
        }
        $filePath = $this->uploadHrFile('report_file', 'health');
        $fileMeta = $this->uploadedFileMetadata($filePath ?? '');
        $this->db->execute(
            'INSERT INTO employee_health_checkups(employee_id,checkup_date,hospital_name,report_file_path,original_filename,stored_mime_type,file_size_bytes,next_due_date,remarks,uploaded_by,created_at) VALUES(:employee_id,:checkup_date,:hospital_name,:report_file_path,:original_filename,:stored_mime_type,:file_size_bytes,:next_due_date,:remarks,:uploaded_by,NOW())',
            [
                'employee_id' => $employeeId,
                'checkup_date' => $checkupDate,
                'hospital_name' => $hospitalName,
                'report_file_path' => $filePath,
                'original_filename' => $_FILES['report_file']['name'] ?? null,
                'stored_mime_type' => $fileMeta['mime_type'],
                'file_size_bytes' => $fileMeta['size_bytes'],
                'next_due_date' => ($_POST['next_due_date'] ?? '') ?: null,
                'remarks' => ($_POST['remarks'] ?? '') ?: null,
                'uploaded_by' => (int)$this->auth->user()['id'],
            ]
        );
        $this->notifications->notifyEmployee($employeeId, 'Health Report Uploaded', 'Your annual health checkup report has been uploaded.', 'health');
        flash('ok', 'Health checkup saved.');
        redirect('?route=health-checkups');
    }

    public function healthCheckupDownload(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $report = $this->db->fetch('SELECT * FROM employee_health_checkups WHERE id=:id LIMIT 1', ['id' => $id]);
        if (!$report) {
            http_response_code(404);
            exit('Health report not found');
        }
        $this->downloadUploadedFile((string)$report['report_file_path'], (string)($report['original_filename'] ?: basename((string)$report['report_file_path'])));
    }

    public function employeeTrainingUpdate(): void
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT id FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }
        $id = (int)($_POST['id'] ?? 0);
        $status = $this->validTrainingStatus($_POST['status'] ?? 'Assigned');
        $this->db->execute(
            'UPDATE employee_training SET status=:status,completion_date=:completion_date,updated_at=NOW() WHERE id=:id AND employee_id=:employee_id',
            [
                'id' => $id,
                'employee_id' => (int)$employee['id'],
                'status' => $status,
                'completion_date' => $status === 'Completed' ? date('Y-m-d') : null,
            ]
        );
        flash('ok', 'Training status updated');
        redirect('?route=employee-training-kpi');
    }

    public function leave(): void
    {
        $user = $this->auth->user();
        $role = $user['role'] ?? '';
        $approvalHierarchy = $this->leavePermissionApprovalHierarchyConfig();
        $isHrApprovalManager = in_array($role, array_merge($approvalHierarchy['level1_roles'], $approvalHierarchy['level2_roles'], $approvalHierarchy['level3_roles']), true);
        $approvalScope = $this->leaveApprovalScope();
        $selectedEmployeeId = (int)($_GET['employee_id'] ?? 0);
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $validLeaveStatuses = ['Pending', 'Approved', 'Rejected'];
        if (!in_array($statusFilter, $validLeaveStatuses, true)) {
            $statusFilter = '';
        }
        $employeeSql = 'SELECT id, first_name, last_name FROM employees';
        $employeeParams = [];
        if (!$approvalScope['all']) {
            if ($approvalScope['department'] === null) {
                $employeeSql .= ' WHERE 1=0';
            } else {
                $employeeSql .= ' WHERE department COLLATE utf8mb4_general_ci = :employee_department';
                $employeeParams['employee_department'] = $approvalScope['department'];
            }
        }
        $employees = $this->db->fetchAll($employeeSql . ' ORDER BY first_name, last_name', $employeeParams);

        $params = [];
        $where = [];
        $sql = 'SELECT lr.*, e.first_name, e.last_name, e.department FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id';
        if (!$approvalScope['all']) {
            if ($approvalScope['department'] === null) {
                $where[] = '1=0';
            } else {
                $where[] = 'e.department COLLATE utf8mb4_general_ci = :approval_department';
                $params['approval_department'] = $approvalScope['department'];
            }
        }
        if ($selectedEmployeeId > 0) {
            $where[] = 'lr.employee_id=:employee_id';
            $params['employee_id'] = $selectedEmployeeId;
        }
        if ($statusFilter !== '') {
            $where[] = 'lr.status=:status';
            $params['status'] = $statusFilter;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY lr.id DESC';
        $items = $this->db->fetchAll($sql, $params);

        $chartSql = "SELECT DATE_FORMAT(lr.start_date, '%Y-%m') month, lr.status, COUNT(*) total
                     FROM leave_requests lr
                     JOIN employees e ON e.id=lr.employee_id";
        $chartParams = [];
        if (!$approvalScope['all']) {
            if ($approvalScope['department'] === null) {
                $chartSql .= ' WHERE 1=0';
            } else {
                $chartSql .= ' WHERE e.department COLLATE utf8mb4_general_ci = :chart_department';
                $chartParams['chart_department'] = $approvalScope['department'];
            }
        }
        $chart = $this->db->fetchAll($chartSql . " GROUP BY DATE_FORMAT(lr.start_date, '%Y-%m'), lr.status ORDER BY month", $chartParams);
        $statusCounts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
        $statusCountSql = 'SELECT lr.status, COUNT(*) total FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id';
        $statusCountParams = [];
        if (!$approvalScope['all']) {
            if ($approvalScope['department'] === null) {
                $statusCountSql .= ' WHERE 1=0';
            } else {
                $statusCountSql .= ' WHERE e.department COLLATE utf8mb4_general_ci = :status_department';
                $statusCountParams['status_department'] = $approvalScope['department'];
            }
        }
        foreach ($this->db->fetchAll($statusCountSql . ' GROUP BY lr.status', $statusCountParams) as $statusRow) {
            $label = ucfirst(strtolower(trim((string)($statusRow['status'] ?? ''))));
            if (isset($statusCounts[$label])) {
                $statusCounts[$label] = (int)$statusRow['total'];
            }
        }

        $employeeStats = null;
        if ($selectedEmployeeId > 0) {
            $selectedEmployeeAllowed = $approvalScope['all'] || (
                $approvalScope['department'] !== null
                && (bool)$this->db->fetch(
                    'SELECT id FROM employees WHERE id=:id AND department COLLATE utf8mb4_general_ci = :department LIMIT 1',
                    ['id' => $selectedEmployeeId, 'department' => $approvalScope['department']]
                )
            );
            $agg = $selectedEmployeeAllowed ? $this->db->fetch(
                "SELECT
                    SUM(CASE WHEN LOWER(TRIM(status))='approved' THEN DATEDIFF(end_date,start_date)+1 ELSE 0 END) availed_days,
                    SUM(CASE WHEN LOWER(TRIM(status))='pending' THEN 1 ELSE 0 END) pending_count,
                    SUM(CASE WHEN LOWER(TRIM(status))='approved' THEN 1 ELSE 0 END) approved_count,
                    SUM(CASE WHEN LOWER(TRIM(status))='rejected' THEN 1 ELSE 0 END) rejected_count
                 FROM leave_requests
                 WHERE employee_id=:employee_id",
                ['employee_id' => $selectedEmployeeId]
            ) ?? [] : [];
            $available = 24;
            $availed = (int)($agg['availed_days'] ?? 0);
            $employeeStats = [
                'available' => $available,
                'availed' => $availed,
                'pending' => (int)($agg['pending_count'] ?? 0),
                'approved' => (int)($agg['approved_count'] ?? 0),
                'rejected' => (int)($agg['rejected_count'] ?? 0),
                'balance' => max(0, $available - $availed),
            ];
        }

        $canApprove = $isHrApprovalManager;
        View::render('leave/index', compact('items', 'chart', 'employees', 'selectedEmployeeId', 'employeeStats', 'canApprove', 'approvalHierarchy', 'role', 'statusCounts', 'statusFilter', 'approvalScope'));
    }

    public function leaveStore(): void
    {
        $this->db->execute('INSERT INTO leave_requests(employee_id,leave_type,start_date,end_date,reason,status,created_at) VALUES(:employee_id,:leave_type,:start_date,:end_date,:reason,:status,NOW())', [
            'employee_id' => $_POST['employee_id'], 'leave_type' => $_POST['leave_type'], 'start_date' => $_POST['start_date'], 'end_date' => $_POST['end_date'], 'reason' => $_POST['reason'], 'status' => 'Pending',
        ]);
        flash('ok', 'Leave request created'); redirect('?route=leave');
    }

    public function leaveEdit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $item = $this->db->fetch(
            'SELECT lr.*, e.first_name, e.last_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=:id LIMIT 1',
            ['id' => $id]
        );
        if (!$item) {
            flash('error', 'Leave request not found.');
            redirect('?route=leave');
        }
        View::render('leave/edit', compact('item'));
    }

    public function leaveDecision(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $item = $this->db->fetch(
            'SELECT lr.*, e.employee_code, e.first_name, e.last_name, e.department, e.position
             FROM leave_requests lr
             JOIN employees e ON e.id=lr.employee_id
             WHERE lr.id=:id LIMIT 1',
            ['id' => $id]
        );
        if (!$item) {
            flash('error', 'Leave request not found.');
            redirect('?route=leave');
        }
        $this->requireLeaveRequestAccess($id);
        $role = (string)($this->auth->user()['role'] ?? '');
        $approvalHierarchy = $this->leavePermissionApprovalHierarchyConfig();
        $approvalLevel = max(1, min(3, (int)($item['approval_level'] ?? 1)));
        $assignedRoles = $approvalHierarchy['level' . $approvalLevel . '_roles'] ?? [];
        $canDecide = !in_array((string)$item['status'], ['Approved', 'Rejected'], true)
            && in_array($role, $assignedRoles, true)
            && $this->canAccessLeaveRequest($id);
        View::render('leave/decision', compact('item', 'role', 'approvalHierarchy', 'approvalLevel', 'assignedRoles', 'canDecide'));
    }

    public function leaveUpdateStatus(): void
    {
        $id = (int)$_POST['id'];
        $user = $this->auth->user();
        $req = $this->db->fetch('SELECT id,employee_id,status,approval_level FROM leave_requests WHERE id=:id LIMIT 1', ['id' => $id]);
        if (!$req) {
            flash('error', 'Leave request not found.');
            redirect('?route=leave');
        }
        $this->requireLeaveRequestAccess($id);
        $approvalLevel = max(1, min(3, (int)($req['approval_level'] ?? 1)));
        $hierarchy = $this->leavePermissionApprovalHierarchyConfig();
        $approverRoles = $hierarchy['level' . $approvalLevel . '_roles'];
        if (!in_array($user['role'] ?? '', $approverRoles, true)) {
            flash('error', 'This request is assigned to the Level ' . $approvalLevel . ' approval group.');
            redirect('?route=leave');
        }
        $status = trim((string)($_POST['status'] ?? 'Pending'));
        $allowed = ['Approved', 'Rejected', 'Pending'];
        if (!in_array($status, $allowed, true)) {
            $status = 'Pending';
        }
        $this->db->execute('UPDATE leave_requests SET status=:status, approved_by=:approved_by, updated_at=NOW() WHERE id=:id', ['status' => $status, 'approved_by' => $this->auth->user()['id'], 'id' => $id]);
        if ($req) { $this->notifications->notifyEmployee((int)$req['employee_id'], 'Leave Request Updated', 'Your leave request is now ' . $status, 'leave'); }
        flash('ok', 'Leave status updated to ' . $status . '.');
        $nextRoute = trim((string)($_POST['next_route'] ?? 'leave'));
        if (!in_array($nextRoute, ['leave', 'dashboard', 'leave.decision'], true)) {
            $nextRoute = 'leave';
        }
        if ($nextRoute === 'leave.decision') {
            redirect('?route=leave.decision&id=' . (int)($_POST['decision_id'] ?? $id));
        }
        redirect('?route=' . $nextRoute);
    }

    public function leaveUpdate(): void
    {
        $this->db->execute('UPDATE leave_requests SET employee_id=:employee_id,leave_type=:leave_type,start_date=:start_date,end_date=:end_date,reason=:reason,status=:status,updated_at=NOW() WHERE id=:id', [
            'id' => (int)$_POST['id'], 'employee_id' => (int)$_POST['employee_id'], 'leave_type' => $_POST['leave_type'], 'start_date' => $_POST['start_date'], 'end_date' => $_POST['end_date'], 'reason' => $_POST['reason'], 'status' => $_POST['status'],
        ]);
        flash('ok', 'Leave updated'); redirect('?route=leave');
    }

    public function leaveDelete(): void { $this->db->execute('DELETE FROM leave_requests WHERE id=:id', ['id' => (int)$_POST['id']]); flash('ok', 'Leave deleted'); redirect('?route=leave'); }

    public function training(): void
    {
        $sessions = $this->db->fetchAll(
            'SELECT ts.*,
                    COUNT(et.id) assigned_count,
                    SUM(et.status="Completed") completed_count
             FROM training_sessions ts
             LEFT JOIN employee_training et ON et.session_id = ts.id
             GROUP BY ts.id
             ORDER BY ts.session_date DESC, ts.id DESC'
        );
        $assignments = $this->db->fetchAll(
            'SELECT et.*, ts.title, e.first_name, e.last_name, e.employee_code, e.department, e.position
             FROM employee_training et
             JOIN training_sessions ts ON ts.id = et.session_id
             JOIN employees e ON e.id = et.employee_id
             ORDER BY ts.session_date DESC, et.id DESC'
        );
        $bySession = [];
        foreach ($assignments as $assignment) {
            $bySession[(int)$assignment['session_id']][] = $assignment;
        }
        $employees = $this->db->fetchAll('SELECT id, employee_code, first_name, last_name, department, position FROM employees ORDER BY first_name, last_name');
        View::render('training/index', compact('sessions', 'bySession', 'employees'));
    }

    public function trainingStore(): void
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $sessionDate = trim((string)($_POST['session_date'] ?? ''));
        if ($title === '' || $sessionDate === '') {
            flash('error', 'Training title and date are required.');
            redirect('?route=training');
        }
        $this->db->execute('INSERT INTO training_sessions(title,description,session_date,trainer_name,location,created_by,created_at) VALUES(:title,:description,:session_date,:trainer_name,:location,:created_by,NOW())', [
            'title' => $title,
            'description' => ($_POST['description'] ?? '') ?: null,
            'session_date' => $sessionDate,
            'trainer_name' => ($_POST['trainer_name'] ?? '') ?: null,
            'location' => ($_POST['location'] ?? '') ?: null,
            'created_by' => $this->auth->user()['id'],
        ]);
        $sessionId = (int)$this->db->pdo()->lastInsertId();
        $this->assignTrainingEmployees($sessionId, $_POST['employee_ids'] ?? []);
        flash('ok', 'Training session created');
        redirect('?route=training');
    }

    public function trainingUpdate(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if (isset($_POST['assignment_id'])) {
            $assignmentId = (int)$_POST['assignment_id'];
            $status = $this->validTrainingStatus($_POST['status'] ?? 'Assigned');
            $this->db->execute('UPDATE employee_training SET status=:status,completion_date=:completion_date,score=:score,notes=:notes,updated_at=NOW() WHERE id=:id', [
                'id' => $assignmentId,
                'status' => $status,
                'completion_date' => ($_POST['completion_date'] ?? '') ?: ($status === 'Completed' ? date('Y-m-d') : null),
                'score' => ($_POST['score'] ?? '') !== '' ? (float)$_POST['score'] : null,
                'notes' => ($_POST['notes'] ?? '') ?: null,
            ]);
            flash('ok', 'Training assignment updated');
            redirect('?route=training');
        }
        $this->db->execute('UPDATE training_sessions SET title=:title,description=:description,session_date=:session_date,trainer_name=:trainer_name,location=:location,updated_at=NOW() WHERE id=:id', [
            'id' => $id,
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => ($_POST['description'] ?? '') ?: null,
            'session_date' => $_POST['session_date'],
            'trainer_name' => ($_POST['trainer_name'] ?? '') ?: null,
            'location' => ($_POST['location'] ?? '') ?: null,
        ]);
        $this->assignTrainingEmployees($id, $_POST['employee_ids'] ?? []);
        flash('ok', 'Training session updated');
        redirect('?route=training');
    }

    public function trainingDelete(): void
    {
        $this->db->execute('DELETE FROM training_sessions WHERE id=:id', ['id' => (int)$_POST['id']]);
        flash('ok', 'Training session deleted');
        redirect('?route=training');
    }

    public function performance(): void
    {
        $items = $this->db->fetchAll('SELECT p.*, e.first_name, e.last_name FROM performance p JOIN employees e ON e.id = p.employee_id ORDER BY p.review_date DESC');
        $employees = $this->db->fetchAll('SELECT id, employee_code, first_name, last_name, department, position FROM employees ORDER BY first_name, last_name');
        View::render('performance/index', compact('items', 'employees'));
    }

    public function performanceLeaderboard(): void
    {
        $items = $this->db->fetchAll(
            'SELECT
                e.id AS employee_id,
                e.first_name,
                e.last_name,
                e.department,
                e.position,
                ROUND(AVG(p.kpi_score), 2) AS average_score,
                MAX(p.kpi_score) AS best_score,
                COUNT(p.id) AS review_count,
                MAX(p.review_date) AS latest_review_date
             FROM performance p
             JOIN employees e ON e.id = p.employee_id
             GROUP BY e.id, e.first_name, e.last_name, e.department, e.position
             ORDER BY average_score DESC, best_score DESC, latest_review_date DESC, e.first_name ASC'
        );
        View::render('performance/leaderboard', compact('items'));
    }

    public function performanceStore(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if (!$this->employeeExists($employeeId)) {
            flash('error', 'Select a valid employee before saving a KPI review.');
            redirect('?route=performance');
        }
        $score = (float)($_POST['kpi_score'] ?? 0);
        if ($score < 1 || $score > 10) {
            flash('error', 'KPI score must be between 1 and 10.');
            redirect('?route=performance');
        }
        $this->db->execute('INSERT INTO performance(employee_id,kpi_score,review_date,review_notes,reviewer_id,created_at) VALUES(:employee_id,:kpi_score,:review_date,:review_notes,:reviewer_id,NOW())', [
            'employee_id' => $employeeId, 'kpi_score' => $score, 'review_date' => $_POST['review_date'], 'review_notes' => $_POST['review_notes'], 'reviewer_id' => $this->auth->user()['id'],
        ]);
        $this->notifications->notifyEmployee($employeeId, 'Performance Review', 'A new KPI review has been recorded.', 'performance');
        flash('ok', 'Performance review saved');
        redirect('?route=performance');
    }

    public function performanceUpdate(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if (!$this->employeeExists($employeeId)) {
            flash('error', 'Select a valid employee before updating the KPI review.');
            redirect('?route=performance');
        }
        $score = (float)($_POST['kpi_score'] ?? 0);
        if ($score < 1 || $score > 10) {
            flash('error', 'KPI score must be between 1 and 10.');
            redirect('?route=performance');
        }
        $this->db->execute('UPDATE performance SET employee_id=:employee_id,kpi_score=:kpi_score,review_date=:review_date,review_notes=:review_notes WHERE id=:id', [
            'id' => (int)$_POST['id'], 'employee_id' => $employeeId, 'kpi_score' => $score, 'review_date' => $_POST['review_date'], 'review_notes' => $_POST['review_notes'],
        ]);
        flash('ok', 'Performance updated'); redirect('?route=performance');
    }

    public function performanceDelete(): void { $this->db->execute('DELETE FROM performance WHERE id=:id', ['id' => (int)$_POST['id']]); flash('ok', 'Performance deleted'); redirect('?route=performance'); }

    public function exitModule(): void
    {
        $this->ensureWorkforceFeatureTables();
        $items = $this->db->fetchAll('SELECT er.*, e.first_name, e.last_name FROM exit_records er JOIN employees e ON e.id = er.employee_id ORDER BY er.exit_date DESC');
        foreach ($items as $item) {
            $this->ensureExitClearanceItems((int)$item['id']);
        }
        $clearanceRows = $this->db->fetchAll(
            'SELECT * FROM exit_clearance_items WHERE exit_record_id IN (SELECT id FROM exit_records) ORDER BY exit_record_id DESC, id ASC'
        );
        $clearanceByExit = [];
        foreach ($clearanceRows as $row) {
            $clearanceByExit[(int)$row['exit_record_id']][] = $row;
        }
        View::render('exit/index', compact('items', 'clearanceByExit'));
    }

    public function exitStore(): void
    {
        $this->ensureWorkforceFeatureTables();
        $this->db->execute('INSERT INTO exit_records(employee_id,exit_date,reason,remarks,recorded_by,created_at) VALUES(:employee_id,:exit_date,:reason,:remarks,:recorded_by,NOW())', [
            'employee_id' => $_POST['employee_id'], 'exit_date' => $_POST['exit_date'], 'reason' => $_POST['reason'], 'remarks' => $_POST['remarks'], 'recorded_by' => $this->auth->user()['id'],
        ]);
        $exitId = (int)$this->db->pdo()->lastInsertId();
        $this->ensureExitClearanceItems($exitId);
        $this->notifications->notifyEmployee((int)$_POST['employee_id'], 'Exit Record Added', 'An exit/separation record has been created.', 'exit');
        redirect('?route=exit');
    }

    public function exitUpdate(): void
    {
        $this->db->execute('UPDATE exit_records SET employee_id=:employee_id,exit_date=:exit_date,reason=:reason,remarks=:remarks WHERE id=:id', [
            'id' => (int)$_POST['id'], 'employee_id' => (int)$_POST['employee_id'], 'exit_date' => $_POST['exit_date'], 'reason' => $_POST['reason'], 'remarks' => $_POST['remarks'],
        ]);
        flash('ok', 'Exit updated'); redirect('?route=exit');
    }

    public function exitDelete(): void { $this->db->execute('DELETE FROM exit_records WHERE id=:id', ['id' => (int)$_POST['id']]); flash('ok', 'Exit deleted'); redirect('?route=exit'); }

    private function ensureExitClearanceItems(int $exitRecordId): void
    {
        foreach (['HR', 'IT', 'Accounts', 'Stores', 'Nursing Admin', 'Uniform', 'ID Card', 'Assets', 'Pending Dues'] as $area) {
            $this->db->execute(
                'INSERT IGNORE INTO exit_clearance_items(exit_record_id,clearance_area,status,created_at) VALUES(:exit_record_id,:clearance_area,"Pending",NOW())',
                ['exit_record_id' => $exitRecordId, 'clearance_area' => $area]
            );
        }
    }

    public function exitClearanceUpdate(): void
    {
        $this->ensureWorkforceFeatureTables();
        $status = trim((string)($_POST['status'] ?? 'Pending'));
        if (!in_array($status, ['Pending', 'Cleared', 'Hold'], true)) {
            $status = 'Pending';
        }
        $this->db->execute(
            'UPDATE exit_clearance_items SET status=:status,remarks=:remarks,cleared_by=:cleared_by,cleared_at=IF(:status_cleared="Cleared",NOW(),cleared_at) WHERE id=:id',
            [
                'id' => (int)($_POST['id'] ?? 0),
                'status' => $status,
                'status_cleared' => $status,
                'remarks' => trim((string)($_POST['remarks'] ?? '')) ?: null,
                'cleared_by' => (int)$this->auth->user()['id'],
            ]
        );
        flash('ok', 'Exit clearance updated.');
        redirect('?route=exit');
    }

    public function payroll(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $employees = $this->db->fetchAll('SELECT id, first_name, last_name, department, position, salary FROM employees ORDER BY first_name,last_name');
        $items = $this->db->fetchAll('SELECT p.*, e.first_name, e.last_name, e.department, e.position FROM payroll_records p JOIN employees e ON e.id=p.employee_id WHERE p.payroll_month=:month ORDER BY e.first_name,e.last_name', ['month' => $month]);
        $payrollSummary = [
            'employees' => count($items),
            'basic' => array_sum(array_map(static fn ($row) => (float)$row['basic_salary'], $items)),
            'allowances' => array_sum(array_map(static fn ($row) => (float)$row['allowances'], $items)),
            'deductions' => array_sum(array_map(static fn ($row) => (float)$row['deductions'], $items)),
            'net' => array_sum(array_map(static fn ($row) => (float)$row['net_salary'], $items)),
        ];
        View::render('payroll/index', compact('employees', 'items', 'month', 'payrollSummary'));
    }

    public function payrollDisabled(): void
    {
        flash('error', 'Payroll functions are currently disabled.');
        redirect('?route=reports');
    }

    public function payrollStore(): void
    {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $month = trim((string)($_POST['payroll_month'] ?? date('Y-m')));
        $basic = (float)($_POST['basic_salary'] ?? 0);
        $allowances = (float)($_POST['allowances'] ?? 0);
        $deductions = (float)($_POST['deductions'] ?? 0);
        $net = max(0, $basic + $allowances - $deductions);
        $status = trim((string)($_POST['status'] ?? 'Draft'));
        if (!in_array($status, ['Draft','Processed','Paid','Hold'], true)) { $status = 'Draft'; }
        $this->db->execute('INSERT INTO payroll_records(employee_id,payroll_month,basic_salary,allowances,deductions,net_salary,status,payment_date,notes,created_by,created_at,updated_at) VALUES(:employee_id,:payroll_month,:basic_salary,:allowances,:deductions,:net_salary,:status,:payment_date,:notes,:created_by,NOW(),NOW()) ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary),allowances=VALUES(allowances),deductions=VALUES(deductions),net_salary=VALUES(net_salary),status=VALUES(status),payment_date=VALUES(payment_date),notes=VALUES(notes),updated_at=NOW()', [
            'employee_id' => $employeeId,
            'payroll_month' => $month,
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $net,
            'status' => $status,
            'payment_date' => ($_POST['payment_date'] ?? '') ?: null,
            'notes' => ($_POST['notes'] ?? '') ?: null,
            'created_by' => $this->auth->user()['id'],
        ]);
        $this->auditLog('payroll_saved', 'payroll_records', $employeeId, 'Payroll saved for ' . $month);
        flash('ok', 'Payroll saved'); redirect('?route=payroll&month=' . urlencode($month));
    }

    public function payrollGenerate(): void
    {
        $month = trim((string)($_POST['payroll_month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $standardDailyHours = max(1, (float)($_POST['standard_daily_hours'] ?? 8));
        $daysInMonth = (int)date('t', strtotime($month . '-01'));
        $monthlyStandardHours = $daysInMonth * $standardDailyHours;
        $employees = $this->db->fetchAll('SELECT id,salary FROM employees ORDER BY id');
        $adjustments = $this->db->fetchAll('SELECT employee_id,entry_type,COALESCE(SUM(amount),0) amount FROM allowance_deductions WHERE effective_month=:month OR (is_recurring=1 AND effective_month<=:month) GROUP BY employee_id,entry_type', ['month' => $month]);
        $adjustmentMap = [];
        foreach ($adjustments as $row) {
            $eid = (int)$row['employee_id'];
            if (!isset($adjustmentMap[$eid])) {
                $adjustmentMap[$eid] = ['Allowance' => 0.0, 'Deduction' => 0.0];
            }
            $adjustmentMap[$eid][$row['entry_type']] = (float)$row['amount'];
        }

        $generated = 0;
        foreach ($employees as $employee) {
            $employeeId = (int)$employee['id'];
            $monthlySalary = (float)($employee['salary'] ?? 0);
            $attendanceRows = $this->db->fetchAll(
                'SELECT status,check_in,check_out FROM attendance WHERE employee_id=:employee_id AND attendance_date BETWEEN :start AND :end',
                ['employee_id' => $employeeId, 'start' => $month . '-01', 'end' => date('Y-m-t', strtotime($month . '-01'))]
            );
            if ($monthlySalary <= 0 && !$attendanceRows) {
                continue;
            }

            $workHours = 0.0;
            $payableDays = 0.0;
            foreach ($attendanceRows as $attendance) {
                $status = strtolower(trim((string)$attendance['status']));
                if ($status === 'absent') {
                    continue;
                }
                $hours = $this->attendanceWorkHours($attendance['check_in'] ?? null, $attendance['check_out'] ?? null);
                if ($hours > 0) {
                    $workHours += $hours;
                    $payableDays += min(1, $hours / $standardDailyHours);
                } elseif (in_array($status, ['present', 'late'], true)) {
                    $workHours += $standardDailyHours;
                    $payableDays += 1;
                }
            }

            $hourlyRate = $monthlyStandardHours > 0 ? $monthlySalary / $monthlyStandardHours : 0;
            $basic = round($workHours * $hourlyRate, 2);
            $allowances = round((float)($adjustmentMap[$employeeId]['Allowance'] ?? 0), 2);
            $deductions = round((float)($adjustmentMap[$employeeId]['Deduction'] ?? 0), 2);
            $net = max(0, round($basic + $allowances - $deductions, 2));
            $this->db->execute('INSERT INTO payroll_records(employee_id,payroll_month,work_hours,payable_days,hourly_rate,basic_salary,allowances,deductions,net_salary,status,payment_date,notes,created_by,created_at,updated_at) VALUES(:employee_id,:payroll_month,:work_hours,:payable_days,:hourly_rate,:basic_salary,:allowances,:deductions,:net_salary,:status,NULL,:notes,:created_by,NOW(),NOW()) ON DUPLICATE KEY UPDATE work_hours=VALUES(work_hours),payable_days=VALUES(payable_days),hourly_rate=VALUES(hourly_rate),basic_salary=VALUES(basic_salary),allowances=VALUES(allowances),deductions=VALUES(deductions),net_salary=VALUES(net_salary),status=VALUES(status),notes=VALUES(notes),updated_at=NOW()', [
                'employee_id' => $employeeId,
                'payroll_month' => $month,
                'work_hours' => round($workHours, 2),
                'payable_days' => round($payableDays, 2),
                'hourly_rate' => round($hourlyRate, 2),
                'basic_salary' => $basic,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net_salary' => $net,
                'status' => 'Processed',
                'notes' => 'Generated from attendance. Standard daily hours: ' . $standardDailyHours,
                'created_by' => $this->auth->user()['id'],
            ]);
            $generated++;
        }

        $this->auditLog('payroll_generated', 'payroll_records', null, 'Generated salary from attendance for ' . $month);
        flash('ok', 'Monthly salary generated for ' . $generated . ' employee(s).');
        redirect('?route=payroll&month=' . urlencode($month));
    }

    public function payrollStatus(): void
    {
        $status = trim((string)($_POST['status'] ?? 'Processed'));
        if (!in_array($status, ['Draft','Processed','Paid','Hold'], true)) { $status = 'Processed'; }
        $this->db->execute('UPDATE payroll_records SET status=:status,payment_date=:payment_date,updated_at=NOW() WHERE id=:id', [
            'id' => (int)$_POST['id'],
            'status' => $status,
            'payment_date' => ($_POST['payment_date'] ?? '') ?: null,
        ]);
        $this->auditLog('payroll_status', 'payroll_records', (int)$_POST['id'], 'Payroll status changed');
        flash('ok', 'Payroll status updated'); redirect('?route=payroll&month=' . urlencode($_POST['month'] ?? date('Y-m')));
    }

    public function payrollDelete(): void
    {
        $this->db->execute('DELETE FROM payroll_records WHERE id=:id', ['id' => (int)$_POST['id']]);
        $this->auditLog('payroll_deleted', 'payroll_records', (int)$_POST['id'], 'Payroll row deleted');
        flash('ok', 'Payroll deleted'); redirect('?route=payroll&month=' . urlencode($_POST['month'] ?? date('Y-m')));
    }

    public function allowances(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $employees = $this->db->fetchAll('SELECT id, first_name, last_name, department, position FROM employees ORDER BY first_name,last_name');
        $items = $this->db->fetchAll('SELECT a.*, e.first_name, e.last_name FROM allowance_deductions a JOIN employees e ON e.id=a.employee_id WHERE a.effective_month=:month ORDER BY a.id DESC', ['month' => $month]);
        View::render('payroll/allowances', compact('employees', 'items', 'month'));
    }

    public function allowanceStore(): void
    {
        $this->db->execute('INSERT INTO allowance_deductions(employee_id,entry_type,title,amount,effective_month,is_recurring,notes,created_by,created_at) VALUES(:employee_id,:entry_type,:title,:amount,:effective_month,:is_recurring,:notes,:created_by,NOW())', [
            'employee_id' => (int)$_POST['employee_id'],
            'entry_type' => $_POST['entry_type'],
            'title' => trim((string)$_POST['title']),
            'amount' => (float)$_POST['amount'],
            'effective_month' => $_POST['effective_month'],
            'is_recurring' => isset($_POST['is_recurring']) ? 1 : 0,
            'notes' => ($_POST['notes'] ?? '') ?: null,
            'created_by' => $this->auth->user()['id'],
        ]);
        $this->auditLog('allowance_saved', 'allowance_deductions', null, $_POST['entry_type'] . ': ' . $_POST['title']);
        flash('ok', 'Entry saved'); redirect('?route=allowances&month=' . urlencode($_POST['effective_month']));
    }

    public function allowanceDelete(): void
    {
        $this->db->execute('DELETE FROM allowance_deductions WHERE id=:id', ['id' => (int)$_POST['id']]);
        $this->auditLog('allowance_deleted', 'allowance_deductions', (int)$_POST['id'], 'Allowance/deduction deleted');
        flash('ok', 'Entry deleted'); redirect('?route=allowances&month=' . urlencode($_POST['month'] ?? date('Y-m')));
    }

    public function departments(): void
    {
        $employees = $this->db->fetchAll('SELECT id, first_name, last_name FROM employees ORDER BY first_name,last_name');
        $items = $this->db->fetchAll('SELECT d.*, CONCAT(e.first_name," ",e.last_name) head_name, (SELECT COUNT(*) FROM employees emp WHERE emp.department COLLATE utf8mb4_general_ci = d.name COLLATE utf8mb4_general_ci) employee_count FROM hr_departments d LEFT JOIN employees e ON e.id=d.head_employee_id ORDER BY d.name');
        $selectedDepartment = trim((string)($_GET['department'] ?? ''));
        $departmentEmployees = [];
        if ($selectedDepartment !== '') {
            $departmentEmployees = $this->db->fetchAll('SELECT e.*, u.role FROM employees e JOIN users u ON u.id=e.user_id WHERE e.department COLLATE utf8mb4_general_ci = :department ORDER BY e.first_name,e.last_name', [
                'department' => $selectedDepartment,
            ]);
        }
        View::render('admin/departments', compact('items', 'employees', 'selectedDepartment', 'departmentEmployees'));
    }

    public function departmentStore(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Department name is required.');
            redirect('?route=departments');
        }

        $params = [
            'name' => $name,
            'head_employee_id' => ($_POST['head_employee_id'] ?? '') ?: null,
            'location' => ($_POST['location'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $this->db->execute(
                'UPDATE hr_departments SET name=:name,head_employee_id=:head_employee_id,location=:location,is_active=:is_active WHERE id=:id',
                $params
            );
        } else {
            $this->db->execute(
                'INSERT INTO hr_departments(name,head_employee_id,location,is_active,created_at) VALUES(:name,:head_employee_id,:location,:is_active,NOW()) ON DUPLICATE KEY UPDATE head_employee_id=VALUES(head_employee_id),location=VALUES(location),is_active=VALUES(is_active)',
                $params
            );
        }
        $this->auditLog('department_saved', 'hr_departments', $id ?: null, $name);
        flash('ok', 'Department saved'); redirect('?route=departments');
    }

    public function departmentDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid department selected.');
            redirect('?route=departments');
        }
        $department = $this->db->fetch('SELECT name FROM hr_departments WHERE id=:id LIMIT 1', ['id' => $id]);
        $this->db->execute('DELETE FROM hr_departments WHERE id=:id', ['id' => $id]);
        $this->auditLog('department_deleted', 'hr_departments', $id, 'Department deleted: ' . (string)($department['name'] ?? $id));
        flash('ok', 'Department deleted'); redirect('?route=departments');
    }

    public function designations(): void
    {
        $departments = $this->db->fetchAll('SELECT name FROM hr_departments ORDER BY name');
        $designationSql = $this->designationCaseSql('emp');
        $items = $this->db->fetchAll(
            "SELECT d.*, COALESCE(c.employee_count, 0) employee_count
             FROM hr_designations d
             LEFT JOIN (
                SELECT {$designationSql} normalized_title, COUNT(*) employee_count
                FROM employees emp
                WHERE emp.position IS NOT NULL AND TRIM(emp.position) <> ''
                GROUP BY normalized_title
             ) c ON c.normalized_title COLLATE utf8mb4_general_ci = d.title COLLATE utf8mb4_general_ci
             ORDER BY d.title"
        );
        $selectedDesignation = trim((string)($_GET['designation'] ?? ''));
        $designationEmployees = [];
        if ($selectedDesignation !== '') {
            $selectedSql = $this->designationCaseSql('e');
            $designationEmployees = $this->db->fetchAll(
                "SELECT e.*, u.role
                 FROM employees e
                 JOIN users u ON u.id=e.user_id
                 WHERE {$selectedSql} COLLATE utf8mb4_general_ci = :designation
                 ORDER BY e.first_name,e.last_name",
                ['designation' => $selectedDesignation]
            );
        }
        View::render('admin/designations', compact('items', 'departments', 'selectedDesignation', 'designationEmployees'));
    }

    private function designationCaseSql(string $alias): string
    {
        $position = "LOWER(TRIM({$alias}.position))";
        return "CASE
            WHEN {$position} IN ('doctor','doctors','consultant','specialist','surgeon','physician','resident doctor','radiologist','pathologist','microbiologist','biochemist','emergency physician') THEN 'Doctor'
            WHEN {$position} IN ('dmo','duty medical officer') THEN 'DMO'
            WHEN {$position} IN ('staff nurse','staff nurses','junior nurse','trauma nurse') THEN 'Staff Nurse'
            WHEN {$position} IN ('senior staff nurse') THEN 'Senior Staff Nurse'
            WHEN {$position} IN ('nursing assistant') THEN 'Nursing Assistant'
            WHEN {$position} IN ('reception','receptionist','patient coordinator') THEN 'Receptionist'
            WHEN {$position} IN ('pharmacy','pharmacist','chief pharmacist','assistant pharmacist') THEN 'Pharmacist'
            WHEN {$position} IN ('lab','lab technician','lab assistant','lab supervisor') THEN 'Lab Technician'
            WHEN {$position} IN ('radiology','imaging','scan','radiology technician','sonographer','x-ray technician','mri technician') THEN 'Radiology Technician'
            WHEN {$position} IN ('ct scan','ct technician') THEN 'CT Technician'
            WHEN {$position} IN ('ecg','ecg technician') THEN 'ECG Technician'
            WHEN {$position} IN ('ot','ot technician','ot assistant') THEN 'OT Technician'
            WHEN {$position} IN ('cssd','cssd technician') THEN 'CSSD Technician'
            WHEN {$position} IN ('cathlab','cath lab','cath lab technician') THEN 'Cath Lab Technician'
            WHEN {$position} IN ('endoscopy','endoscopy technician') THEN 'Endoscopy Technician'
            WHEN {$position} IN ('physiotherapy','physiotherapist') THEN 'Physiotherapist'
            WHEN {$position} IN ('physician assistant') THEN 'Physician Assistant'
            WHEN {$position} IN ('diet','dietitian') THEN 'Dietitian'
            WHEN {$position} IN ('accounts','accountant','finance manager','billing executive','cashier') THEN 'Accountant'
            WHEN {$position} IN ('hr','hr executive','hr manager') THEN 'HR Executive'
            WHEN {$position} IN ('admin','admin and accounts','administration','hospital administrator','administrative officer','operations manager','medical records officer','data entry operator') THEN 'Administrative Officer'
            WHEN {$position} IN ('maintenance','maintenance technician','biomedical engineer','plumber','housekeeping supervisor') THEN 'Maintenance Technician'
            WHEN {$position} IN ('electrician') THEN 'Electrician'
            WHEN {$position} IN ('drivers','driver','ambulance driver') THEN 'Driver'
            WHEN {$position} IN ('cook') THEN 'Cook'
            WHEN {$position} IN ('sanitary','sanitary worker','housekeeping') THEN 'Sanitary Worker'
            WHEN {$position} IN ('attender') THEN 'Attender'
            WHEN {$position} IN ('tutors','tutor') THEN 'Tutor'
            WHEN {$position} IN ('cop','clinical operations','head - clinical operations') THEN 'Clinical Operations Head'
            WHEN {$position} IN ('rt','respiratory therapist') THEN 'Respiratory Therapist'
            ELSE TRIM({$alias}.position)
        END";
    }

    public function designationStore(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Designation title is required.');
            redirect('?route=designations');
        }

        $params = [
            'title' => $title,
            'department_name' => ($_POST['department_name'] ?? '') ?: null,
            'grade' => ($_POST['grade'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $this->db->execute(
                'UPDATE hr_designations SET title=:title,department_name=:department_name,grade=:grade,is_active=:is_active WHERE id=:id',
                $params
            );
        } else {
            $this->db->execute(
                'INSERT INTO hr_designations(title,department_name,grade,is_active,created_at) VALUES(:title,:department_name,:grade,:is_active,NOW()) ON DUPLICATE KEY UPDATE department_name=VALUES(department_name),grade=VALUES(grade),is_active=VALUES(is_active)',
                $params
            );
        }

        $this->auditLog('designation_saved', 'hr_designations', $id ?: null, $title);
        flash('ok', 'Designation saved'); redirect('?route=designations');
    }

    public function designationDelete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Invalid designation selected.');
            redirect('?route=designations');
        }
        $designation = $this->db->fetch('SELECT title FROM hr_designations WHERE id=:id LIMIT 1', ['id' => $id]);
        $this->db->execute('DELETE FROM hr_designations WHERE id=:id', ['id' => $id]);
        $this->auditLog('designation_deleted', 'hr_designations', $id, 'Designation deleted: ' . (string)($designation['title'] ?? $id));
        flash('ok', 'Designation deleted'); redirect('?route=designations');
    }

    public function users(): void
    {
        $items = $this->db->fetchAll('SELECT u.*, e.employee_code, e.department, e.position FROM users u LEFT JOIN employees e ON e.user_id=u.id ORDER BY u.id DESC');
        View::render('admin/users', compact('items'));
    }

    public function userRole(): void
    {
        $role = $_POST['role'] ?? 'Employee';
        if (!in_array($role, ['Admin','SuperAdmin','HR','HOD','Manager','Employee'], true)) {
            $role = 'Employee';
        }
        $this->db->execute('UPDATE users SET role=:role WHERE id=:id', ['id' => (int)$_POST['id'], 'role' => $role]);
        $this->auditLog('user_role_updated', 'users', (int)$_POST['id'], 'Role changed to ' . $role);
        flash('ok', 'User role updated'); redirect('?route=users');
    }

    public function menuRbac(): void
    {
        $roles = ['Admin','SuperAdmin','HR','HOD','Manager','Employee'];
        $rows = $this->db->fetchAll('SELECT * FROM sidebar_menu_permissions ORDER BY sort_order,label,role');
        $menus = [];
        foreach ($rows as $row) {
            $key = $row['menu_key'];
            if (!isset($menus[$key])) {
                $menus[$key] = [
                    'menu_key' => $row['menu_key'],
                    'label' => $row['label'],
                    'route' => $row['route'],
                    'section_name' => $row['section_name'],
                    'sort_order' => $row['sort_order'],
                    'roles' => [],
                ];
            }
            $menus[$key]['roles'][$row['role']] = (int)$row['is_visible'];
        }
        View::render('admin/menu_rbac', compact('roles', 'menus'));
    }

    public function menuRbacSave(): void
    {
        $visible = $_POST['visible'] ?? [];
        $rows = $this->db->fetchAll('SELECT menu_key, role FROM sidebar_menu_permissions');
        foreach ($rows as $row) {
            $enabled = isset($visible[$row['menu_key']]) && in_array($row['role'], (array)$visible[$row['menu_key']], true) ? 1 : 0;
            $this->db->execute('UPDATE sidebar_menu_permissions SET is_visible=:is_visible,updated_at=NOW() WHERE menu_key=:menu_key AND role=:role', [
                'is_visible' => $enabled,
                'menu_key' => $row['menu_key'],
                'role' => $row['role'],
            ]);
        }
        $this->auditLog('menu_rbac_updated', 'sidebar_menu_permissions', null, 'Sidebar menu visibility updated');
        flash('ok', 'Menu RBAC updated'); redirect('?route=menu-rbac');
    }

    public function settings(): void
    {
        $items = $this->db->fetchAll('SELECT * FROM hr_settings ORDER BY setting_key');
        $leaveStandards = $this->leavePermissionStandards();
        View::render('admin/settings', compact('items', 'leaveStandards'));
    }

    public function settingStore(): void
    {
        $this->db->execute('INSERT INTO hr_settings(setting_key,setting_value,updated_by,updated_at) VALUES(:setting_key,:setting_value,:updated_by,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()', [
            'setting_key' => trim((string)$_POST['setting_key']),
            'setting_value' => $_POST['setting_value'] ?? '',
            'updated_by' => $this->auth->user()['id'],
        ]);
        $this->auditLog('setting_saved', 'hr_settings', null, $_POST['setting_key']);
        flash('ok', 'Setting saved'); redirect('?route=settings');
    }

    public function shiftSettings(): void
    {
        $shifts = $this->db->fetchAll('SELECT * FROM hr_shifts ORDER BY sort_order, shift_name');
        View::render('admin/shifts', compact('shifts'));
    }

    public function shiftSettingsStore(): void
    {
        $id = max(0, (int)($_POST['id'] ?? 0));
        $shiftName = trim((string)($_POST['shift_name'] ?? ''));
        $startTime = $this->normalizeTime($_POST['start_time'] ?? '');
        $endTime = $this->normalizeTime($_POST['end_time'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($shiftName === '' || $startTime === null || $endTime === null) {
            flash('error', 'Enter shift name, start time, and end time.');
            redirect('?route=settings.shifts');
        }
        if (strcasecmp($shiftName, 'OFF') === 0) {
            flash('error', 'OFF is reserved for weekly off and cannot be created as a normal shift.');
            redirect('?route=settings.shifts');
        }

        $params = [
            'shift_name' => $shiftName,
            'start_time' => $startTime . ':00',
            'end_time' => $endTime . ':00',
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
            'created_by' => (int)($this->auth->user()['id'] ?? 0),
        ];

        if ($id > 0) {
            $params['id'] = $id;
            unset($params['created_by']);
            $this->db->execute(
                'UPDATE hr_shifts SET shift_name=:shift_name,start_time=:start_time,end_time=:end_time,is_active=:is_active,sort_order=:sort_order,updated_at=NOW() WHERE id=:id',
                $params
            );
        } else {
            $this->db->execute(
                'INSERT INTO hr_shifts(shift_name,start_time,end_time,is_active,sort_order,created_by,created_at)
                 VALUES(:shift_name,:start_time,:end_time,:is_active,:sort_order,:created_by,NOW())
                 ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),is_active=VALUES(is_active),sort_order=VALUES(sort_order),updated_at=NOW()',
                $params
            );
        }

        $this->auditLog('shift_saved', 'hr_shifts', $id ?: null, $shiftName);
        flash('ok', 'Shift setup saved.');
        redirect('?route=settings.shifts');
    }

    public function shiftSettingsDelete(): void
    {
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($id <= 0) {
            flash('error', 'Invalid shift selected.');
            redirect('?route=settings.shifts');
        }
        $shift = $this->db->fetch('SELECT shift_name FROM hr_shifts WHERE id=:id LIMIT 1', ['id' => $id]);
        $this->db->execute('UPDATE hr_shifts SET is_active=0,updated_at=NOW() WHERE id=:id', ['id' => $id]);
        $this->auditLog('shift_deactivated', 'hr_shifts', $id, 'Shift deactivated: ' . (string)($shift['shift_name'] ?? $id));
        flash('ok', 'Shift deactivated. Existing roster history is preserved.');
        redirect('?route=settings.shifts');
    }

    public function leavePermissionStandardsStore(): void
    {
        $fields = [
            'leave_casual_annual_entitlement' => [0, 365],
            'leave_medical_annual_entitlement' => [0, 365],
            'medical_certificate_after_days' => [1, 30],
            'grace_limit_minutes' => [0, 180],
            'allowed_late_days' => [0, 31],
            'late_to_leave_ratio' => [1, 31],
            'late_penalty_days' => [0, 2],
            'monthly_permission_quota_hours' => [0, 744],
            'standard_shift_hours' => [1, 24],
            'half_day_min_percent' => [1, 100],
            'payroll_fixed_days' => [1, 31],
        ];
        foreach ($fields as $key => [$min, $max]) {
            $value = (float)($_POST[$key] ?? 0);
            $value = max((float)$min, min((float)$max, $value));
            $this->saveSetting($key, (string)$value);
        }
        flash('ok', 'Leave and permission standards applied globally.');
        redirect('?route=settings#leave-permission-standard');
    }

    public function leavePermissionApprovalHierarchy(): void
    {
        $hierarchy = $this->leavePermissionApprovalHierarchyConfig();
        View::render('admin/leave_permission_hierarchy', compact('hierarchy'));
    }

    public function leavePermissionHierarchyStore(): void
    {
        $validRoles = ['Admin', 'SuperAdmin', 'HR', 'HOD', 'Manager'];
        foreach ([1, 2, 3] as $level) {
            $selectedRoles = array_values(array_intersect($validRoles, (array)($_POST['level' . $level . '_roles'] ?? [])));
            if ($selectedRoles === []) {
                flash('error', 'Select at least one approver role for Level ' . $level . '.');
                redirect('?route=settings.leave-permission-hierarchy');
            }
            $this->saveSetting('leave_approval_level' . $level . '_roles', implode(',', $selectedRoles));
        }
        $this->auditLog('leave_approval_hierarchy_saved', 'hr_settings', null, 'Leave and permission approval hierarchy updated');
        flash('ok', 'Leave and permission approval hierarchy applied globally.');
        redirect('?route=settings.leave-permission-hierarchy');
    }

    private function saveSetting(string $key, string $value): void
    {
        $this->db->execute('INSERT INTO hr_settings(setting_key,setting_value,updated_by,updated_at) VALUES(:setting_key,:setting_value,:updated_by,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()', [
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_by' => $this->auth->user()['id'] ?? null,
        ]);
    }

    private function leavePermissionStandards(): array
    {
        $defaults = [
            'leave_casual_annual_entitlement' => 12,
            'leave_medical_annual_entitlement' => 6,
            'medical_certificate_after_days' => 3,
            'grace_limit_minutes' => 15,
            'allowed_late_days' => 0,
            'late_to_leave_ratio' => 3,
            'late_penalty_days' => 0.5,
            'monthly_permission_quota_hours' => 2,
            'standard_shift_hours' => 8,
            'half_day_min_percent' => 50,
            'payroll_fixed_days' => 30,
        ];
        $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM hr_settings WHERE setting_key LIKE 'leave_%' OR setting_key IN ('medical_certificate_after_days','grace_limit_minutes','allowed_late_days','late_to_leave_ratio','late_penalty_days','monthly_permission_quota_hours','standard_shift_hours','half_day_min_percent','payroll_fixed_days')");
        foreach ($rows as $row) {
            $key = (string)$row['setting_key'];
            if (array_key_exists($key, $defaults)) {
                $defaults[$key] = is_numeric($row['setting_value']) ? (float)$row['setting_value'] : $defaults[$key];
            }
        }
        return $defaults;
    }

    private function employeeLeaveBalanceSummary(int $employeeId, array $standards): array
    {
        $total = (float)$standards['leave_casual_annual_entitlement'] + (float)$standards['leave_medical_annual_entitlement'];
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(DATEDIFF(end_date,start_date)+1),0) availed
             FROM leave_requests
             WHERE employee_id=:employee_id
               AND leave_type <> 'Permission'
               AND status='Approved'
               AND YEAR(start_date)=YEAR(CURDATE())",
            ['employee_id' => $employeeId]
        ) ?: ['availed' => 0];
        $availed = (float)($row['availed'] ?? 0);
        return [
            'total' => $total,
            'availed' => $availed,
            'balance' => max(0, $total - $availed),
        ];
    }

    private function employeePermissionBalanceSummary(int $employeeId, array $standards): array
    {
        $total = (float)$standards['monthly_permission_quota_hours'];
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(permission_end_time, permission_start_time)) / 3600),0) availed
             FROM leave_requests
             WHERE employee_id=:employee_id
               AND leave_type='Permission'
               AND status='Approved'
               AND start_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())",
            ['employee_id' => $employeeId]
        ) ?: ['availed' => 0];
        $availed = (float)($row['availed'] ?? 0);
        return [
            'total' => $total,
            'availed' => $availed,
            'balance' => max(0, $total - $availed),
        ];
    }

    private function leavePermissionApprovalHierarchyConfig(): array
    {
        $defaults = [
            'level1_roles' => ['HR'],
            'level2_roles' => ['HOD', 'Manager'],
            'level3_roles' => ['Admin', 'SuperAdmin'],
        ];
        $validRoles = ['Admin', 'SuperAdmin', 'HR', 'HOD', 'Manager'];
        foreach ([1, 2, 3] as $level) {
            $row = $this->db->fetch('SELECT setting_value FROM hr_settings WHERE setting_key=:key LIMIT 1', ['key' => 'leave_approval_level' . $level . '_roles']);
            if (!$row || trim((string)$row['setting_value']) === '') {
                continue;
            }
            $roles = array_values(array_intersect($validRoles, array_map('trim', explode(',', (string)$row['setting_value']))));
            if ($roles !== []) {
                $defaults['level' . $level . '_roles'] = $roles;
            }
        }
        if (!in_array('HOD', $defaults['level1_roles'], true)) {
            $defaults['level1_roles'][] = 'HOD';
        }
        return $defaults;
    }

    public function processLeaveApprovalEscalations(): void
    {
        $hierarchy = $this->leavePermissionApprovalHierarchyConfig();
        $pending = $this->db->fetchAll(
            "SELECT lr.id,lr.leave_type,lr.created_at,e.first_name,e.last_name
             FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id
             WHERE lr.status='Pending' AND lr.approval_level=1
               AND lr.level1_warning_at IS NULL AND lr.created_at <= DATE_SUB(NOW(), INTERVAL 20 HOUR)"
        );
        foreach ($pending as $request) {
            $label = trim($request['first_name'] . ' ' . $request['last_name']) . ' ' . $request['leave_type'] . ' request #' . $request['id'];
            $this->notifications->notifyRoles($hierarchy['level1_roles'], 'Leave Approval Warning', $label . ' has 4 hours remaining for Level 1 review.', 'leave');
            $this->db->execute('UPDATE leave_requests SET level1_warning_at=NOW() WHERE id=:id AND level1_warning_at IS NULL', ['id' => $request['id']]);
        }

        $level2 = $this->db->fetchAll(
            "SELECT lr.id,lr.leave_type,e.first_name,e.last_name
             FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id
             WHERE lr.status='Pending' AND lr.approval_level=1
               AND lr.level2_escalated_at IS NULL AND lr.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        foreach ($level2 as $request) {
            $label = trim($request['first_name'] . ' ' . $request['last_name']) . ' ' . $request['leave_type'] . ' request #' . $request['id'];
            $this->db->execute("UPDATE leave_requests SET status='Escalated - Level 2',approval_level=2,level2_escalated_at=NOW(),updated_at=NOW() WHERE id=:id AND level2_escalated_at IS NULL", ['id' => $request['id']]);
            $this->notifications->notifyRoles($hierarchy['level2_roles'], 'Leave Request Escalated - Level 2', $label . ' requires Level 2 approval.', 'leave');
            $this->notifications->notifyRoles($hierarchy['level1_roles'], 'Leave Request Escalated - Level 2', $label . ' has been escalated to Level 2.', 'leave');
        }

        $warnings = $this->db->fetchAll(
            "SELECT lr.id,lr.leave_type,e.first_name,e.last_name
             FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id
             WHERE lr.status='Escalated - Level 2' AND lr.approval_level=2
               AND lr.level2_warning_at IS NULL AND lr.created_at <= DATE_SUB(NOW(), INTERVAL 44 HOUR)"
        );
        foreach ($warnings as $request) {
            $label = trim($request['first_name'] . ' ' . $request['last_name']) . ' ' . $request['leave_type'] . ' request #' . $request['id'];
            $this->notifications->notifyRoles($hierarchy['level2_roles'], 'Leave Approval Warning', $label . ' has 4 hours remaining for Level 2 review.', 'leave');
            $this->db->execute('UPDATE leave_requests SET level2_warning_at=NOW() WHERE id=:id AND level2_warning_at IS NULL', ['id' => $request['id']]);
        }

        $level3 = $this->db->fetchAll(
            "SELECT lr.id,lr.leave_type,e.first_name,e.last_name
             FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id
             WHERE lr.status='Escalated - Level 2' AND lr.approval_level=2
               AND lr.level3_escalated_at IS NULL AND lr.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)"
        );
        foreach ($level3 as $request) {
            $label = trim($request['first_name'] . ' ' . $request['last_name']) . ' ' . $request['leave_type'] . ' request #' . $request['id'];
            $this->db->execute("UPDATE leave_requests SET status='Critical Escalation - Level 3',approval_level=3,level3_escalated_at=NOW(),updated_at=NOW() WHERE id=:id AND level3_escalated_at IS NULL", ['id' => $request['id']]);
            $this->notifications->notifyRoles($hierarchy['level3_roles'], 'Critical Leave Escalation - Level 3', $label . ' requires immediate final approval.', 'leave');
        }
    }

    private function annualEntitlementForLeaveType(string $leaveType, array $standards): int
    {
        if (stripos($leaveType, 'casual') !== false) {
            return (int)$standards['leave_casual_annual_entitlement'];
        }
        if ($this->isMedicalLeaveType($leaveType)) {
            return (int)$standards['leave_medical_annual_entitlement'];
        }
        return (int)$standards['leave_casual_annual_entitlement'] + (int)$standards['leave_medical_annual_entitlement'];
    }

    private function isMedicalLeaveType(string $leaveType): bool
    {
        return stripos($leaveType, 'medical') !== false || stripos($leaveType, 'sick') !== false;
    }

    public function reports(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        View::render('reports/index', compact('from', 'to'));
    }

    public function reportDetail(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $reports = $this->reportDefinitions();
        $selectedReportKey = (string)($_GET['report'] ?? 'employees');
        if (!isset($reports[$selectedReportKey])) {
            $selectedReportKey = 'employees';
        }
        $selectedReport = $reports[$selectedReportKey];
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $reportRows = $this->reportRows($selectedReportKey, $from, $to, 500, $employeeId);
        $reportHeader = $this->reportHeader();
        $employees = $selectedReportKey === 'employee_attendance'
            ? $this->db->fetchAll('SELECT id,employee_code,first_name,last_name,department FROM employees ORDER BY first_name,last_name')
            : [];
        View::render('reports/detail', compact('reports', 'selectedReportKey', 'selectedReport', 'reportRows', 'from', 'to', 'reportHeader', 'employees', 'employeeId'));
    }

    public function reportExport(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $type = (string)($_GET['type'] ?? 'employees');
        $format = strtolower((string)($_GET['format'] ?? 'xlsx'));
        $orientation = strtolower((string)($_GET['orientation'] ?? 'portrait')) === 'landscape' ? 'landscape' : 'portrait';
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $reports = $this->reportDefinitions();
        if (!isset($reports[$type])) {
            http_response_code(404);
            exit('Invalid report');
        }
        $rows = $this->reportRows($type, $from, $to, 5000, $employeeId);
        if ($format === 'pdf') {
            $this->downloadReportPdf($reports[$type], $rows, $from, $to, $orientation);
        }
        $this->downloadReportXlsx($reports[$type], $rows);
    }

    private function reportHeader(): array
    {
        $settings = [];
        foreach ($this->db->fetchAll("SELECT setting_key, setting_value FROM hr_settings WHERE setting_key IN ('hospital_name','hospital_address','hospital_location')") as $row) {
            $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
        }
        return [
            'hospital_name' => $settings['hospital_name'] ?? ($this->app['app_name'] ?? 'Hospital Workforce System'),
            'hospital_address' => $settings['hospital_address'] ?? 'Hospital Address',
            'hospital_location' => $settings['hospital_location'] ?? 'Hospital Location',
        ];
    }

    private function reportDefinitions(): array
    {
        return [
            'employees' => ['title' => 'Employees', 'group' => 'Workforce', 'icon' => 'user', 'filename' => 'employees', 'columns' => ['employee_code' => 'Code', 'first_name' => 'First Name', 'last_name' => 'Last Name', 'department' => 'Department', 'position' => 'Position', 'location' => 'Location', 'email' => 'Email'], 'sql' => 'SELECT employee_code,first_name,last_name,department,position,location,email FROM employees ORDER BY department,first_name,last_name', 'params' => []],
            'department_strength' => ['title' => 'Department Strength', 'group' => 'Workforce', 'icon' => 'building', 'filename' => 'department_strength', 'columns' => ['department' => 'Department', 'employees' => 'Employees'], 'sql' => 'SELECT COALESCE(NULLIF(department,\'\'),\'Unassigned\') department, COUNT(*) employees FROM employees GROUP BY COALESCE(NULLIF(department,\'\'),\'Unassigned\') ORDER BY department', 'params' => []],
            'onboarding' => ['title' => 'Onboarding', 'group' => 'Workforce', 'icon' => 'clipboard', 'filename' => 'onboarding', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'start_date' => 'Start Date', 'target_completion_date' => 'Target Date', 'status' => 'Status', 'mentor_name' => 'Mentor'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,o.start_date,o.target_completion_date,o.status,o.mentor_name FROM employee_onboarding o JOIN employees e ON e.id=o.employee_id ORDER BY o.start_date DESC,o.id DESC", 'params' => []],
            'exit' => ['title' => 'Exit Records', 'group' => 'Workforce', 'icon' => 'door', 'filename' => 'exit_records', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'exit_date' => 'Exit Date', 'reason' => 'Reason', 'remarks' => 'Remarks'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,x.exit_date,x.reason,x.remarks FROM exit_records x JOIN employees e ON e.id=x.employee_id ORDER BY x.exit_date DESC,x.id DESC", 'params' => []],
            'attendance' => ['title' => 'Attendance Register', 'group' => 'Attendance', 'icon' => 'calendar', 'filename' => 'attendance_register', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'attendance_date' => 'Date', 'status' => 'Status', 'check_in' => 'Check In', 'check_out' => 'Check Out'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,a.attendance_date,a.status,a.check_in,a.check_out FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date BETWEEN :from AND :to ORDER BY a.attendance_date DESC,e.first_name,e.last_name", 'params' => ['from', 'to']],
            'employee_attendance' => ['title' => 'Individual Employee Attendance', 'group' => 'Attendance', 'icon' => 'user', 'filename' => 'employee_attendance', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'department' => 'Department', 'attendance_date' => 'Date', 'status' => 'Status', 'check_in' => 'Check In', 'check_out' => 'Check Out'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,e.department,a.attendance_date,a.status,a.check_in,a.check_out FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date BETWEEN :from AND :to AND (:employee_id=0 OR e.id=:employee_id) ORDER BY e.first_name,e.last_name,a.attendance_date DESC", 'params' => ['from', 'to', 'employee_id']],
            'duty_roster' => ['title' => 'Duty Roster', 'group' => 'Attendance', 'icon' => 'clock', 'filename' => 'duty_roster', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'duty_date' => 'Date', 'shift_name' => 'Shift', 'start_time' => 'Start', 'end_time' => 'End', 'ward' => 'Ward'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,d.duty_date,d.shift_name,d.start_time,d.end_time,d.ward FROM duty_roster d JOIN employees e ON e.id=d.employee_id WHERE d.duty_date BETWEEN :from AND :to ORDER BY d.duty_date DESC,e.first_name,e.last_name", 'params' => ['from', 'to']],
            'present_today' => ['title' => 'Present Today', 'group' => 'Attendance', 'icon' => 'check', 'filename' => 'present_today', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'department' => 'Department', 'check_in' => 'Check In', 'check_out' => 'Check Out'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,e.department,a.check_in,a.check_out FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date=CURDATE() AND a.status='present' ORDER BY e.department,e.first_name,e.last_name", 'params' => []],
            'absent_today' => ['title' => 'Absent Today', 'group' => 'Attendance', 'icon' => 'alert', 'filename' => 'absent_today', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'department' => 'Department', 'position' => 'Position'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,e.department,e.position FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.attendance_date=CURDATE() AND a.status='absent' ORDER BY e.department,e.first_name,e.last_name", 'params' => []],
            'leave' => ['title' => 'Leave Requests', 'group' => 'Leave', 'icon' => 'file', 'filename' => 'leave_requests', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'leave_type' => 'Leave Type', 'start_date' => 'Start', 'end_date' => 'End', 'status' => 'Status'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,l.leave_type,l.start_date,l.end_date,l.status FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.start_date <= :to AND l.end_date >= :from ORDER BY l.start_date DESC,l.id DESC", 'params' => ['from', 'to']],
            'pending_leave' => ['title' => 'Pending Leave', 'group' => 'Leave', 'icon' => 'hourglass', 'filename' => 'pending_leave', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'leave_type' => 'Leave Type', 'start_date' => 'Start', 'end_date' => 'End'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,l.leave_type,l.start_date,l.end_date FROM leave_requests l JOIN employees e ON e.id=l.employee_id WHERE l.status='Pending' ORDER BY l.start_date DESC,l.id DESC", 'params' => []],
            'leave_status' => ['title' => 'Leave Status', 'group' => 'Leave', 'icon' => 'chart', 'filename' => 'leave_status', 'columns' => ['status' => 'Status', 'total' => 'Total'], 'sql' => 'SELECT status, COUNT(*) total FROM leave_requests GROUP BY status ORDER BY status', 'params' => []],
            'performance' => ['title' => 'KPI Reviews', 'group' => 'Performance', 'icon' => 'trend', 'filename' => 'kpi_reviews', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'kpi_score' => 'KPI Score', 'review_date' => 'Review Date', 'review_notes' => 'Notes'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,p.kpi_score,p.review_date,p.review_notes FROM performance p JOIN employees e ON e.id=p.employee_id ORDER BY p.review_date DESC,p.id DESC", 'params' => []],
            'performance_leaderboard' => ['title' => 'Leaderboard', 'group' => 'Performance', 'icon' => 'award', 'filename' => 'leaderboard', 'columns' => ['employee_code' => 'Code', 'employee_name' => 'Employee', 'department' => 'Department', 'avg_score' => 'Average Score', 'reviews' => 'Reviews'], 'sql' => "SELECT e.employee_code,CONCAT(e.first_name,' ',e.last_name) employee_name,e.department,ROUND(AVG(p.kpi_score),2) avg_score,COUNT(*) reviews FROM performance p JOIN employees e ON e.id=p.employee_id GROUP BY e.id,e.employee_code,e.first_name,e.last_name,e.department ORDER BY avg_score DESC,reviews DESC", 'params' => []],
            'training' => ['title' => 'Training Sessions', 'group' => 'Performance', 'icon' => 'graduation', 'filename' => 'training_sessions', 'columns' => ['title' => 'Session', 'session_date' => 'Date', 'trainer_name' => 'Trainer', 'location' => 'Location', 'employee_name' => 'Employee', 'status' => 'Status', 'score' => 'Score'], 'sql' => "SELECT ts.title,ts.session_date,ts.trainer_name,ts.location,CONCAT(e.first_name,' ',e.last_name) employee_name,et.status,et.score FROM training_sessions ts LEFT JOIN employee_training et ON et.session_id=ts.id LEFT JOIN employees e ON e.id=et.employee_id ORDER BY ts.session_date DESC,ts.id DESC", 'params' => []],
            'audit' => ['title' => 'Audit Trail', 'group' => 'Performance', 'icon' => 'shield', 'filename' => 'audit_trail', 'columns' => ['user_name' => 'User', 'action' => 'Action', 'entity_type' => 'Entity', 'entity_id' => 'Entity ID', 'details' => 'Details', 'is_important' => 'Important', 'created_at' => 'Created'], 'sql' => 'SELECT u.name user_name,a.action,a.entity_type,a.entity_id,a.details,IF(a.is_important=1,\'Yes\',\'No\') is_important,a.created_at FROM audit_trail a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.is_important DESC,a.id DESC', 'params' => []],
        ];
    }

    private function reportRows(string $key, string $from, string $to, int $limit = 500, int $employeeId = 0): array
    {
        $reports = $this->reportDefinitions();
        $report = $reports[$key] ?? $reports['employees'];
        $params = [];
        foreach ($report['params'] as $param) {
            $params[$param] = match ($param) {
                'to' => $to,
                'employee_id' => $employeeId,
                default => $from,
            };
        }
        return $this->db->fetchAll($report['sql'] . ' LIMIT ' . $limit, $params);
    }

    private function downloadReportXlsx(array $report, array $rows): void
    {
        $filename = $report['filename'] . '_' . date('Ymd_His') . '.xlsx';
        $header = $this->reportHeader();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $columns = $report['columns'];
        $sheetRows = [
            '<row r="1">' . $this->xlsxCells([$header['hospital_name']], 1) . '</row>',
            '<row r="2">' . $this->xlsxCells([$header['hospital_address']], 2) . '</row>',
            '<row r="3">' . $this->xlsxCells([$header['hospital_location']], 3) . '</row>',
            '<row r="5">' . $this->xlsxCells([$report['title']], 5) . '</row>',
            '<row r="6">' . $this->xlsxCells(array_values($columns), 6) . '</row>',
        ];
        $rowIndex = 7;
        foreach ($rows as $row) {
            $values = [];
            foreach (array_keys($columns) as $key) {
                $values[] = (string)($row[$key] ?? '');
            }
            $sheetRows[] = '<row r="' . $rowIndex . '">' . $this->xlsxCells($values, $rowIndex) . '</row>';
            $rowIndex++;
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function xlsxCells(array $values, int $rowIndex): string
    {
        $cells = '';
        foreach ($values as $i => $value) {
            $ref = chr(65 + $i) . $rowIndex;
            $text = htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $text . '</t></is></c>';
        }
        return $cells;
    }

    private function downloadReportPdf(array $report, array $rows, string $from, string $to, string $orientation): void
    {
        $columns = $report['columns'];
        $header = $this->reportHeader();
        $pdf = $this->simpleA4TablePdf($header, $report['title'] . ' (' . $from . ' to ' . $to . ')', $columns, $rows, $orientation);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . $report['filename'] . '_' . date('Ymd_His') . '.pdf');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function simpleA4TablePdf(array $header, string $title, array $columns, array $rows, string $orientation): string
    {
        $objects = [];
        $isLandscape = $orientation === 'landscape';
        $pageWidth = $isLandscape ? 842 : 595;
        $pageHeight = $isLandscape ? 595 : 842;
        $left = 40;
        $top = $pageHeight - 37;
        $tableWidth = $pageWidth - 80;
        $tableTop = $pageHeight - 118;
        $rowHeight = 18;
        $rowsPerPage = max(12, (int)floor(($tableTop - 42) / $rowHeight));
        $rowChunks = array_chunk($rows, $rowsPerPage);
        if (!$rowChunks) {
            $rowChunks = [[]];
        }
        $pageRefs = [];
        $contentObjects = [];
        foreach ($rowChunks as $pageIndex => $pageRows) {
            $pageObjectNumber = 3 + ($pageIndex * 2);
            $contentObjectNumber = $pageObjectNumber + 1;
            $pageRefs[] = $pageObjectNumber . ' 0 R';
            $content = $this->pdfTextLine($header['hospital_name'], $left, $top, 14);
            $content .= $this->pdfTextLine($header['hospital_address'], $left, $top - 18, 10);
            $content .= $this->pdfTextLine($header['hospital_location'], $left, $top - 32, 10);
            $content .= $this->pdfTextLine($title, $left, $top - 55, 11);
            $content .= $this->pdfTableContent($columns, $pageRows, $left, $tableTop, $tableWidth, $rowHeight);
            if (count($rowChunks) > 1) {
                $content .= $this->pdfTextLine('Page ' . ($pageIndex + 1) . ' of ' . count($rowChunks), $pageWidth - 125, 28, 8);
            }
            $contentObjects[] = [
                'page' => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 ' . (3 + (count($rowChunks) * 2)) . ' 0 R >> >> /Contents ' . $contentObjectNumber . ' 0 R >>',
                'content' => '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
            ];
        }
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
        foreach ($contentObjects as $pair) {
            $objects[] = $pair['page'];
            $objects[] = $pair['content'];
        }
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    private function pdfTableContent(array $columns, array $rows, int $x, int $startY, int $tableWidth, int $rowHeight): string
    {
        $keys = array_keys($columns);
        $columnCount = max(1, count($keys));
        $columnWidth = $tableWidth / $columnCount;
        $content = '';
        $y = $startY;
        $content .= "0.93 0.96 1 rg\n" . $x . ' ' . ($y - $rowHeight + 4) . ' ' . $tableWidth . ' ' . $rowHeight . " re f\n0 0 0 RG\n0 0 0 rg\n";
        foreach (array_values($columns) as $index => $heading) {
            $content .= $this->pdfCellBorder($x + ($index * $columnWidth), $y, $columnWidth, $rowHeight);
            $content .= $this->pdfTextLine($this->fitPdfCell((string)$heading, $columnWidth), (int)($x + ($index * $columnWidth) + 4), $y - 9, 8);
        }
        $y -= $rowHeight;
        foreach ($rows as $row) {
            foreach ($keys as $index => $key) {
                $cellX = $x + ($index * $columnWidth);
                $content .= $this->pdfCellBorder($cellX, $y, $columnWidth, $rowHeight);
                $content .= $this->pdfTextLine($this->fitPdfCell((string)($row[$key] ?? ''), $columnWidth), (int)($cellX + 4), $y - 9, 8);
            }
            $y -= $rowHeight;
        }
        return $content;
    }

    private function pdfCellBorder(float $x, int $y, float $width, int $height): string
    {
        return sprintf("%.2F %d %.2F %d re S\n", $x, $y - $height + 4, $width, $height);
    }

    private function pdfTextLine(string $text, int $x, int $y, int $fontSize): string
    {
        return "BT\n/F1 {$fontSize} Tf\n{$x} {$y} Td\n(" . $this->pdfText($text) . ") Tj\nET\n";
    }

    private function fitPdfCell(string $value, float $columnWidth): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        $maxChars = max(6, (int)floor($columnWidth / 4.6));
        return strlen($value) > $maxChars ? substr($value, 0, $maxChars - 3) . '...' : $value;
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    public function audit(): void
    {
        $items = $this->db->fetchAll('SELECT a.*, u.name user_name FROM audit_trail a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.is_important DESC, a.id DESC LIMIT 500');
        View::render('reports/audit', compact('items'));
    }

    public function auditImportant(): void
    {
        $this->requireAuditAdmin();
        $id = (int)($_POST['id'] ?? 0);
        $important = (int)($_POST['important'] ?? 0) === 1 ? 1 : 0;
        $this->db->execute('UPDATE audit_trail SET is_important=:important WHERE id=:id', ['important' => $important, 'id' => $id]);
        flash('ok', $important ? 'Audit log marked important.' : 'Audit log unmarked.');
        redirect('?route=audit');
    }

    public function auditClear(): void
    {
        $this->requireAuditAdmin();
        $this->db->execute('DELETE FROM audit_trail WHERE COALESCE(is_important,0)=0');
        flash('ok', 'Non-important audit logs cleared. Important logs were preserved.');
        redirect('?route=audit');
    }

    private function requireAuditAdmin(): void
    {
        $role = (string)($this->auth->user()['role'] ?? '');
        if (!in_array($role, ['Admin', 'SuperAdmin'], true)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    public function notifications(): void
    {
        $user = $this->auth->user();
        $canManageNotifications = in_array((string)($user['role'] ?? ''), ['Admin', 'SuperAdmin'], true);
        $role = (string)($user['role'] ?? '');
        if ($canManageNotifications) {
            $items = $this->db->fetchAll('SELECT n.*, e.first_name, e.last_name FROM notifications n JOIN employees e ON e.id=n.employee_id ORDER BY n.id DESC LIMIT 500');
        } else {
            $items = $this->db->fetchAll(
                'SELECT n.*, e.first_name, e.last_name
                 FROM notifications n
                 JOIN employees e ON e.id=n.employee_id
                 WHERE e.user_id=:user_id
                 ORDER BY n.id DESC LIMIT 500',
                ['user_id' => (int)($user['id'] ?? 0)]
            );
        }
        $internalRequests = [];
        if (in_array($role, ['Admin', 'SuperAdmin', 'HR', 'HOD'], true)) {
            $where = $role === 'SuperAdmin' ? '1=1' : 'r.to_role = :to_role';
            $params = $role === 'SuperAdmin' ? [] : ['to_role' => $role];
            $internalRequests = $this->db->fetchAll(
                "SELECT r.*, e.employee_code, e.first_name, e.last_name, u.name replied_by_name
                 FROM employee_internal_requests r
                 JOIN employees e ON e.id = r.employee_id
                 LEFT JOIN users u ON u.id = r.replied_by
                 WHERE {$where}
                 ORDER BY FIELD(r.status, 'Open', 'In Review', 'Completed'), r.created_at DESC, r.id DESC
                 LIMIT 200",
                $params
            );
        }
        View::render('dashboard/notifications', compact('items', 'canManageNotifications', 'internalRequests'));
    }

    public function internalRequestReply(): void
    {
        $user = $this->auth->user();
        $role = (string)($user['role'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'In Review'));
        $reply = trim((string)($_POST['reply_message'] ?? ''));
        if (!in_array($status, ['In Review', 'Completed'], true) || $reply === '') {
            flash('error', 'Enter a reply and choose a valid status.');
            redirect('?route=notifications#internal-requests');
        }
        $request = $this->db->fetch('SELECT * FROM employee_internal_requests WHERE id=:id LIMIT 1', ['id' => $id]);
        if (!$request) {
            flash('error', 'Internal request not found.');
            redirect('?route=notifications#internal-requests');
        }
        if ($role !== 'SuperAdmin' && (string)$request['to_role'] !== $role) {
            http_response_code(403);
            exit('Forbidden');
        }
        $this->db->execute(
            'UPDATE employee_internal_requests SET reply_message=:reply_message, replied_by=:replied_by, replied_at=NOW(), status=:status, updated_at=NOW() WHERE id=:id',
            [
                'id' => $id,
                'reply_message' => $reply,
                'replied_by' => (int)$user['id'],
                'status' => $status,
            ]
        );
        $this->notifications->notifyEmployee(
            (int)$request['employee_id'],
            'Internal request reply',
            $status . ': ' . substr($reply, 0, 180),
            'internal_request_reply'
        );
        $this->auditLog('employee_internal_request_replied', 'employee_internal_requests', $id, $status . ' by ' . $role);
        flash('ok', 'Reply saved and employee notified.');
        redirect('?route=notifications#internal-requests');
    }

    public function notificationUpdate(): void
    {
        $this->db->execute('UPDATE notifications SET channel=:channel,event_type=:event_type,title=:title,message=:message,is_read=:is_read WHERE id=:id', [
            'id' => (int)$_POST['id'], 'channel' => $_POST['channel'], 'event_type' => $_POST['event_type'], 'title' => $_POST['title'], 'message' => $_POST['message'], 'is_read' => (int)$_POST['is_read'],
        ]);
        flash('ok', 'Notification updated'); redirect('?route=notifications');
    }

    public function notificationDelete(): void { $this->db->execute('DELETE FROM notifications WHERE id=:id', ['id' => (int)$_POST['id']]); flash('ok', 'Notification deleted'); redirect('?route=notifications'); }

    public function csv(string $type): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        $map = [
            'employees' => ['SELECT id,employee_code,first_name,last_name,email,department,location,position,notification_preference FROM employees', 'employees.csv', []],
            'leave' => ['SELECT id,employee_id,leave_type,start_date,end_date,status FROM leave_requests', 'leave_requests.csv', []],
            'performance' => ['SELECT id,employee_id,kpi_score,review_date,review_notes FROM performance', 'performance.csv', []],
            'exit' => ['SELECT id,employee_id,exit_date,reason,remarks FROM exit_records', 'exit_records.csv', []],
            'attendance' => ['SELECT id,employee_id,attendance_date,status,check_in,check_out FROM attendance', 'attendance.csv', []],
            'duty_roster' => ['SELECT id,employee_id,duty_date,shift_name,start_time,end_time,ward,notes FROM duty_roster', 'duty_roster.csv', []],
            'audit' => ['SELECT a.id,u.name user_name,a.action,a.entity_type,a.entity_id,a.details,a.created_at FROM audit_trail a LEFT JOIN users u ON u.id=a.user_id', 'audit_trail.csv', []],
            'onboarding' => ["SELECT o.id,o.employee_id,e.first_name,e.last_name,o.start_date,o.target_completion_date,o.status,o.orientation_done,o.documents_collected,o.assets_issued,o.training_assigned,o.mentor_name,o.notes FROM employee_onboarding o JOIN employees e ON e.id=o.employee_id", 'employee_onboarding.csv', []],
            'training' => ['SELECT ts.id,ts.title,ts.session_date,ts.trainer_name,ts.location,et.employee_id,et.status,et.completion_date,et.score,et.notes FROM training_sessions ts LEFT JOIN employee_training et ON et.session_id=ts.id', 'training_sessions.csv', []],
        ];
        if (!isset($map[$type])) { http_response_code(404); exit('Invalid export'); }
        [$sql, $filename, $params] = $map[$type];
        $rows = $this->db->fetchAll($sql, $params);
        $contentType = str_ends_with($filename, '.xls') ? 'application/vnd.ms-excel' : 'text/csv';
        header('Content-Type: ' . $contentType); header('Content-Disposition: attachment; filename=' . $filename);
        $out = fopen('php://output', 'w'); if ($rows) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $row) { fputcsv($out, $row); } } fclose($out); exit;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return gmdate('Y-m-d', (int)(((float)$raw - 25569) * 86400));
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $seconds = (int)round(((float)$raw - floor((float)$raw)) * 86400);
            return gmdate('H:i:s', $seconds);
        }
        $ts = strtotime($raw);
        return $ts ? date('H:i:s', $ts) : null;
    }

    /** Calculates an imported row before it becomes the stored status for its attendance date. */
    private function calculateImportedAttendanceStatus(int $employeeId, string $attendanceDate, ?string $checkIn, ?string $checkOut, string $rawStatus, int $graceLimit, array &$rosterCache): string
    {
        if (!$checkIn || !$checkOut) {
            return 'absent';
        }
        if (in_array($rawStatus, ['AP', 'LATE', 'PRESENT-LATE'], true)) {
            return 'late';
        }

        $cacheKey = $employeeId . '|' . $attendanceDate;
        if (!array_key_exists($cacheKey, $rosterCache)) {
            $rosterCache[$cacheKey] = $this->db->fetch(
                'SELECT shift_name,start_time FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date ORDER BY id LIMIT 1',
                ['employee_id' => $employeeId, 'duty_date' => $attendanceDate]
            );
        }
        $shift = $rosterCache[$cacheKey];
        if (!$shift || strtoupper(trim((string)$shift['shift_name'])) === 'OFF' || empty($shift['start_time'])) {
            return 'present';
        }

        $startSeconds = (int)strtotime('2000-01-01 ' . $shift['start_time']) - (int)strtotime('2000-01-01 00:00:00');
        $checkInSeconds = (int)strtotime('2000-01-01 ' . $checkIn) - (int)strtotime('2000-01-01 00:00:00');
        if ($startSeconds >= 18 * 3600 && $checkInSeconds < 12 * 3600) {
            $checkInSeconds += 86400;
        }
        return $checkInSeconds > $startSeconds + ($graceLimit * 60) ? 'late' : 'present';
    }

    private function permissionShiftTimingError(int $employeeId, string $date, string $permissionStartTime, string $permissionEndTime): ?string
    {
        $shift = $this->db->fetch(
            "SELECT shift_name,start_time,end_time
             FROM duty_roster
             WHERE employee_id=:employee_id AND duty_date=:duty_date
             ORDER BY CASE WHEN UPPER(TRIM(shift_name)) = 'OFF' THEN 0 ELSE 1 END, start_time ASC, id ASC
             LIMIT 1",
            ['employee_id' => $employeeId, 'duty_date' => $date]
        );
        if (!$shift) {
            return 'Permission cannot be requested because no duty shift is assigned for this date.';
        }

        $shiftName = trim((string)($shift['shift_name'] ?? ''));
        if (strtoupper($shiftName) === 'OFF') {
            return 'Permission cannot be requested on an OFF duty day.';
        }

        $shiftStartTime = $this->normalizeTime($shift['start_time'] ?? null);
        $shiftEndTime = $this->normalizeTime($shift['end_time'] ?? null);
        if ($shiftStartTime === null || $shiftEndTime === null) {
            return 'Assigned shift timing is incomplete for this date.';
        }

        $shiftStartTs = strtotime($date . ' ' . $shiftStartTime);
        $shiftEndTs = strtotime($date . ' ' . $shiftEndTime);
        $permissionStartTs = strtotime($date . ' ' . $permissionStartTime);
        $permissionEndTs = strtotime($date . ' ' . $permissionEndTime);
        if ($shiftStartTs === false || $shiftEndTs === false || $permissionStartTs === false || $permissionEndTs === false) {
            return 'Invalid permission or shift timing.';
        }

        if ($shiftEndTs <= $shiftStartTs) {
            $shiftEndTs += 86400;
            if ($permissionStartTs < $shiftStartTs) {
                $permissionStartTs += 86400;
                $permissionEndTs += 86400;
            }
        }

        if ($permissionEndTs <= $permissionStartTs) {
            $permissionEndTs += 86400;
        }

        if ($permissionStartTs < $shiftStartTs || $permissionEndTs > $shiftEndTs) {
            $shiftLabel = $shiftName !== '' ? $shiftName : 'assigned shift';
            return 'Permission time must be within ' . $shiftLabel . ' shift timing (' . substr($shiftStartTime, 0, 5) . ' - ' . substr($shiftEndTime, 0, 5) . ').';
        }

        return null;
    }

    /**
     * Resolves the status shown to HR and employees from attendance, approved leave, and roster data.
     */
    private function calculatedAttendanceStatusRows(string $startDate, string $endDate, ?int $employeeId = null, bool $includeUnmarked = false): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $startDate > $endDate) {
            return [];
        }

        $employeeParams = [];
        $employeeWhere = '';
        if ($employeeId !== null) {
            $employeeWhere = ' WHERE e.id=:employee_id';
            $employeeParams['employee_id'] = $employeeId;
        }
        $employees = $this->db->fetchAll(
            'SELECT e.id,e.employee_code,e.first_name,e.last_name,e.department FROM employees e' . $employeeWhere . ' ORDER BY e.department,e.first_name,e.last_name',
            $employeeParams
        );
        if ($employees === []) {
            return [];
        }

        $ids = array_map(static fn(array $employee): int => (int)$employee['id'], $employees);
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $queryParams = array_merge([$startDate, $endDate], $ids);
        $attendanceRows = $this->db->fetchAll(
            "SELECT employee_id,attendance_date,status,check_in,check_out,source_file,id
             FROM attendance
             WHERE attendance_date BETWEEN ? AND ? AND employee_id IN ({$idPlaceholders})
             ORDER BY attendance_date,id",
            $queryParams
        );
        $leaveRows = $this->db->fetchAll(
            "SELECT employee_id,start_date,end_date,leave_type
             FROM leave_requests
             WHERE status='Approved' AND start_date <= ? AND end_date >= ? AND employee_id IN ({$idPlaceholders})
             ORDER BY start_date,id",
            array_merge([$endDate, $startDate], $ids)
        );
        $dutyRows = $this->db->fetchAll(
            "SELECT employee_id,duty_date,shift_name
             FROM duty_roster
             WHERE duty_date BETWEEN ? AND ? AND employee_id IN ({$idPlaceholders})
             ORDER BY duty_date,id",
            $queryParams
        );

        $attendanceByDay = [];
        foreach ($attendanceRows as $row) {
            $attendanceByDay[(int)$row['employee_id']][$row['attendance_date']] = $row;
        }
        $leaveByDay = [];
        $permissionByDay = [];
        foreach ($leaveRows as $leave) {
            $cursor = max($startDate, (string)$leave['start_date']);
            $lastDate = min($endDate, (string)$leave['end_date']);
            $isPermission = stripos((string)$leave['leave_type'], 'permission') !== false;
            while ($cursor <= $lastDate) {
                if ($isPermission) {
                    $permissionByDay[(int)$leave['employee_id']][$cursor] = true;
                } else {
                    $leaveByDay[(int)$leave['employee_id']][$cursor] = true;
                }
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }
        $dutyByDay = [];
        foreach ($dutyRows as $duty) {
            $dutyByDay[(int)$duty['employee_id']][$duty['duty_date']] = strtoupper(trim((string)$duty['shift_name']));
        }

        $labels = [
            'present' => 'Present',
            'late' => 'Late',
            'absent' => 'Absent',
            'on_leave' => 'On Leave',
            'on_duty' => 'On Duty / Field Work',
            'permission' => 'Permission',
            'off_duty' => 'Off Duty',
            'not_marked' => 'Not Marked',
        ];
        $resolved = [];
        foreach ($employees as $employee) {
            $id = (int)$employee['id'];
            $dates = [];
            foreach ([$attendanceByDay[$id] ?? [], $leaveByDay[$id] ?? [], $permissionByDay[$id] ?? [], $dutyByDay[$id] ?? []] as $dateMap) {
                foreach (array_keys($dateMap) as $date) {
                    $dates[$date] = true;
                }
            }
            if ($includeUnmarked) {
                for ($date = $startDate; $date <= $endDate; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
                    $dates[$date] = true;
                }
            }
            foreach (array_keys($dates) as $date) {
                $attendance = $attendanceByDay[$id][$date] ?? null;
                $duty = $dutyByDay[$id][$date] ?? '';
                if ($attendance) {
                    $status = strtolower(trim((string)$attendance['status']));
                } elseif (!empty($leaveByDay[$id][$date])) {
                    $status = 'on_leave';
                } elseif ($duty !== '' && $duty !== 'OFF') {
                    $status = 'on_duty';
                } elseif (!empty($permissionByDay[$id][$date])) {
                    $status = 'permission';
                } elseif ($duty === 'OFF') {
                    $status = 'off_duty';
                } else {
                    $status = 'not_marked';
                }
                if (!isset($labels[$status])) {
                    $status = 'not_marked';
                }
                $resolved[] = [
                    'employee_id' => $id,
                    'employee_code' => (string)$employee['employee_code'],
                    'first_name' => (string)$employee['first_name'],
                    'last_name' => (string)$employee['last_name'],
                    'department' => (string)($employee['department'] ?? ''),
                    'attendance_date' => $date,
                    'status' => $status,
                    'live_status' => $labels[$status],
                    'check_in' => $attendance['check_in'] ?? null,
                    'check_out' => $attendance['check_out'] ?? null,
                    'source_file' => $attendance['source_file'] ?? ($status === 'on_leave' ? 'Approved leave' : ($status === 'on_duty' ? 'Duty roster' : null)),
                ];
            }
        }
        usort($resolved, static function (array $left, array $right): int {
            return [$left['attendance_date'], $left['department'], $left['first_name'], $left['last_name']] <=> [$right['attendance_date'], $right['department'], $right['first_name'], $right['last_name']];
        });
        return $resolved;
    }

    private function attendanceCalendarReportData(string $month): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $days = [];
        for ($date = $startDate; $date <= $endDate; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
            $days[$date] = ['date' => $date, 'present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'on_duty' => 0, 'not_marked' => 0];
        }
        foreach ($this->calculatedAttendanceStatusRows($startDate, $endDate, null, true) as $row) {
            $date = (string)$row['attendance_date'];
            $status = (string)$row['status'];
            if (isset($days[$date]) && array_key_exists($status, $days[$date])) {
                $days[$date][$status]++;
            }
        }
        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'on_duty' => 0, 'not_marked' => 0];
        foreach ($days as $day) {
            foreach ($totals as $status => $value) {
                $totals[$status] += (int)$day[$status];
            }
        }
        return ['month' => $month, 'month_label' => date('F Y', strtotime($startDate)), 'days' => array_values($days), 'totals' => $totals];
    }

    private function attendanceWorkHours(?string $checkIn, ?string $checkOut): float
    {
        if (!$checkIn || !$checkOut) {
            return 0.0;
        }
        $in = strtotime('2000-01-01 ' . $checkIn);
        $out = strtotime('2000-01-01 ' . $checkOut);
        if (!$in || !$out) {
            return 0.0;
        }
        if ($out < $in) {
            $out += 86400;
        }
        return max(0.0, round(($out - $in) / 3600, 2));
    }

    private function auditLog(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
    {
        $user = $this->auth->user();
        $this->db->execute('INSERT INTO audit_trail(user_id,action,entity_type,entity_id,details,created_at) VALUES(:user_id,:action,:entity_type,:entity_id,:details,NOW())', [
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ]);
    }

    private function currentEmployeeOrRedirect(): array
    {
        $userId = (int)$this->auth->user()['id'];
        $employee = $this->db->fetch('SELECT * FROM employees WHERE user_id=:user_id LIMIT 1', ['user_id' => $userId]);
        if (!$employee) {
            flash('error', 'Employee profile not found.');
            redirect('?route=dashboard');
        }
        $this->ensureEmployeeStorageFolders($employee);
        return $employee;
    }

    private function uploadPhoto(string $field, ?array $employee = null): ?string
    {
        if (empty($_FILES[$field]['name']) || empty($_FILES[$field]['tmp_name'])) { return null; }
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            return null;
        }
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return null;
        }
        if ((int)$_FILES[$field]['size'] > 1024 * 1024) { return null; }
        $mimeMap = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'];
        if (($mimeMap[$ext] ?? '') !== (new \finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name'])) { return null; }
        $targetDir = $this->employeeUploadDirectory($employee, 'photos');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $name = uniqid('photo_', true) . '.' . $ext;
        $target = $targetDir . '/' . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return null;
        }
        return $this->employeeUploadRelativePath($employee, 'photos', $name);
    }

    private function uploadCapturedPhoto(string $field, ?array $employee = null): ?string
    {
        $dataUrl = (string)($_POST[$field] ?? '');
        if ($dataUrl === '' || !preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            return null;
        }
        $binary = base64_decode($matches[1], true);
        if ($binary === false || strlen($binary) > 256 * 1024) {
            return null;
        }
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) !== 'image/jpeg') {
            return null;
        }
        $targetDir = $this->employeeUploadDirectory($employee, 'photos');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $name = uniqid('photo_', true) . '.jpg';
        $target = $targetDir . '/' . $name;
        if (file_put_contents($target, $binary) === false) {
            return null;
        }
        return $this->employeeUploadRelativePath($employee, 'photos', $name);
    }

    private function uploadHrFile(string $field, string $prefix, ?array $employee = null, ?string $folder = null): ?string
    {
        if (empty($_FILES[$field]['name']) || empty($_FILES[$field]['tmp_name'])) {
            return null;
        }
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $isCertificate = $folder === 'certificates';
        $allowed = $isCertificate ? ['pdf', 'png', 'jpg', 'jpeg', 'webp'] : ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return null;
        }
        if ((int)$_FILES[$field]['size'] > ($isCertificate ? 2 * 1024 * 1024 : 10 * 1024 * 1024)) { return null; }
        $mimeMap = $isCertificate
            ? ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp']
            : ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','txt'=>'text/plain'];
        if (($mimeMap[$ext] ?? '') !== (new \finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name'])) { return null; }
        $targetDir = $folder !== null ? $this->employeeUploadDirectory($employee, $folder) : $this->app['upload_dir'];
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $name = uniqid($prefix . '_', true) . '.' . $ext;
        $target = $targetDir . '/' . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return null;
        }
        return $folder !== null ? $this->employeeUploadRelativePath($employee, $folder, $name) : 'uploads/employees/' . $name;
    }

    private function employeeUploadDirectory(?array $employee, string $folder): string
    {
        return rtrim((string)$this->app['upload_dir'], '/\\') . '/' . $this->employeeUploadSlug($employee) . '/' . $folder;
    }

    private function employeeUploadRelativePath(?array $employee, string $folder, string $filename): string
    {
        return 'uploads/employees/' . $this->employeeUploadSlug($employee) . '/' . $folder . '/' . $filename;
    }

    private function ensureEmployeeStorageFolders(?array $employee): void
    {
        foreach (['photos', 'certificates'] as $folder) {
            $directory = $this->employeeUploadDirectory($employee, $folder);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }
    }

    private function uploadedFileMetadata(string $relativePath): array
    {
        $absolutePath = $this->uploadedFileAbsolutePath($relativePath);
        if (!$absolutePath || !is_file($absolutePath)) {
            return ['mime_type' => null, 'size_bytes' => null];
        }
        return [
            'mime_type' => (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: null,
            'size_bytes' => filesize($absolutePath) ?: null,
        ];
    }

    private function uploadedFileAbsolutePath(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/employees/')) {
            return null;
        }
        $publicRoot = realpath(__DIR__ . '/../../public');
        if (!$publicRoot) {
            return null;
        }
        $absolutePath = $publicRoot . '/' . $relativePath;
        $directory = realpath(dirname($absolutePath));
        if (!$directory || !str_starts_with($directory, $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'employees')) {
            return null;
        }
        return $absolutePath;
    }

    private function deleteUploadedFile(string $relativePath, string $replacementPath = ''): void
    {
        if ($relativePath === '' || $relativePath === $replacementPath) {
            return;
        }
        $absolutePath = $this->uploadedFileAbsolutePath($relativePath);
        if ($absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function employeeUploadSlug(?array $employee): string
    {
        $code = trim((string)($employee['employee_code'] ?? ''));
        if ($code === '' && !empty($employee['id'])) {
            $code = 'employee-' . (int)$employee['id'];
        }
        if ($code === '') {
            $code = 'employee';
        }
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $code) ?: 'employee');
        return trim($slug, '-') ?: 'employee';
    }

    private function downloadUploadedFile(string $relativePath, string $downloadName): void
    {
        if ($relativePath === '') {
            http_response_code(404);
            exit('File not found');
        }
        $root = realpath(__DIR__ . '/../../public');
        $path = realpath(__DIR__ . '/../../public/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        $rootPrefix = $root ? rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        if (!$root || !$path || !str_starts_with($path, $rootPrefix) || !is_file($path)) {
            http_response_code(404);
            exit('File not found');
        }
        $safeDownloadName = str_replace(["\r", "\n", '"'], '', basename($downloadName));
        if ($safeDownloadName === '') {
            $safeDownloadName = basename($path);
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeDownloadName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function validEmployeeLocation(string $location): string
    {
        return in_array($location, ['KH', 'KCI', 'KNS', 'MANAGEMENT'], true) ? $location : 'KH';
    }

    private function dutyRosterScope(): array
    {
        $user = $this->auth->user() ?? [];
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['Admin', 'SuperAdmin', 'HR'], true)) {
            return ['all' => true, 'department' => null];
        }

        $profile = $this->db->fetch('SELECT department FROM employees WHERE user_id=:user_id LIMIT 1', [
            'user_id' => (int)($user['id'] ?? 0),
        ]);
        $department = trim((string)($profile['department'] ?? ''));
        return ['all' => false, 'department' => $department !== '' ? $department : null];
    }

    private function leaveApprovalScope(): array
    {
        $user = $this->auth->user() ?? [];
        $role = (string)($user['role'] ?? '');
        if (in_array($role, ['Admin', 'SuperAdmin', 'HR'], true)) {
            return ['all' => true, 'department' => null];
        }

        $profile = $this->db->fetch('SELECT department FROM employees WHERE user_id=:user_id LIMIT 1', [
            'user_id' => (int)($user['id'] ?? 0),
        ]);
        $department = trim((string)($profile['department'] ?? ''));
        return ['all' => false, 'department' => $department !== '' ? $department : null];
    }

    private function canAccessLeaveRequest(int $requestId): bool
    {
        if ($requestId <= 0) {
            return false;
        }
        $scope = $this->leaveApprovalScope();
        if ($scope['all']) {
            return true;
        }
        if ($scope['department'] === null) {
            return false;
        }
        return (bool)$this->db->fetch(
            'SELECT lr.id
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             WHERE lr.id=:id AND e.department COLLATE utf8mb4_general_ci = :department
             LIMIT 1',
            ['id' => $requestId, 'department' => $scope['department']]
        );
    }

    private function requireLeaveRequestAccess(int $requestId): void
    {
        if (!$this->canAccessLeaveRequest($requestId)) {
            flash('error', 'You can review leave requests only for employees in your department.');
            redirect('?route=leave');
        }
    }

    private function canAccessDutyRosterEmployee(int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }
        $scope = $this->dutyRosterScope();
        if ($scope['all']) {
            return true;
        }
        if ($scope['department'] === null) {
            return false;
        }
        return (bool)$this->db->fetch(
            'SELECT id FROM employees WHERE id=:id AND department COLLATE utf8mb4_general_ci = :department LIMIT 1',
            ['id' => $employeeId, 'department' => $scope['department']]
        );
    }

    private function requireDutyRosterEmployeeAccess(int $employeeId): void
    {
        if (!$this->canAccessDutyRosterEmployee($employeeId)) {
            flash('error', 'You can manage duty roster only for employees in your department.');
            redirect('?route=duty-roster');
        }
    }

    private function requireDutyRosterRowAccess(int $rowId): void
    {
        if ($rowId <= 0) {
            flash('error', 'Duty roster record not found.');
            redirect('?route=duty-roster');
        }
        $scope = $this->dutyRosterScope();
        if ($scope['all']) {
            return;
        }
        if ($scope['department'] === null) {
            flash('error', 'Your user profile is not linked to a department.');
            redirect('?route=duty-roster');
        }
        $row = $this->db->fetch(
            'SELECT dr.id
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.id=:id AND e.department COLLATE utf8mb4_general_ci = :department
             LIMIT 1',
            ['id' => $rowId, 'department' => $scope['department']]
        );
        if (!$row) {
            flash('error', 'You can manage duty roster only for employees in your department.');
            redirect('?route=duty-roster');
        }
    }

    private function dutyRosterShiftDefaults(bool $includeOff = false): array
    {
        $fallback = [
            'General' => ['09:00', '17:00'],
            'Morning' => ['07:00', '15:00'],
            'Afternoon' => ['13:00', '21:00'],
            'Night' => ['21:00', '07:00'],
        ];
        $shifts = [];
        try {
            $rows = $this->db->fetchAll('SELECT shift_name,start_time,end_time FROM hr_shifts WHERE is_active=1 ORDER BY sort_order, shift_name');
            foreach ($rows as $row) {
                $name = trim((string)($row['shift_name'] ?? ''));
                if ($name === '' || strcasecmp($name, 'OFF') === 0) {
                    continue;
                }
                $start = substr((string)($row['start_time'] ?? ''), 0, 5);
                $end = substr((string)($row['end_time'] ?? ''), 0, 5);
                if ($start === '' || $end === '') {
                    continue;
                }
                $shifts[$name] = [$start, $end];
            }
        } catch (\Throwable) {
            $shifts = [];
        }
        if (!$shifts) {
            $shifts = $fallback;
        }
        if ($includeOff) {
            $shifts['OFF'] = ['00:00', '00:00'];
        }
        return $shifts;
    }

    private function buildDutyShiftTracker(array $items, array $employees, array $days, array $shiftDefaults): array
    {
        $shiftNames = array_keys($shiftDefaults);
        $byShift = [];
        foreach ($shiftNames as $shiftName) {
            $byShift[$shiftName] = 0;
        }
        $byShift['OFF'] = 0;

        $byDay = [];
        foreach ($days as $day) {
            $byDay[$day] = ['date' => $day, 'total' => 0, 'off' => 0, 'unassigned' => count($employees), 'shifts' => array_fill_keys($shiftNames, 0)];
        }

        $assignedCells = [];
        foreach ($items as $item) {
            $employeeId = (int)($item['employee_id'] ?? 0);
            $date = (string)($item['duty_date'] ?? '');
            $shiftName = trim((string)($item['shift_name'] ?? ''));
            if ($employeeId <= 0 || $date === '' || !isset($byDay[$date])) {
                continue;
            }

            $cellKey = $employeeId . '|' . $date;
            if (!isset($assignedCells[$cellKey])) {
                $assignedCells[$cellKey] = true;
                $byDay[$date]['unassigned'] = max(0, (int)$byDay[$date]['unassigned'] - 1);
            }

            if (strtoupper($shiftName) === 'OFF') {
                $byShift['OFF']++;
                $byDay[$date]['off']++;
                continue;
            }
            if (!isset($byShift[$shiftName])) {
                $byShift[$shiftName] = 0;
            }
            if (!isset($byDay[$date]['shifts'][$shiftName])) {
                $byDay[$date]['shifts'][$shiftName] = 0;
            }
            $byShift[$shiftName]++;
            $byDay[$date]['shifts'][$shiftName]++;
            $byDay[$date]['total']++;
        }

        $capacity = count($employees) * count($days);
        $filled = count($assignedCells);
        return [
            'capacity' => $capacity,
            'filled' => $filled,
            'missing' => max(0, $capacity - $filled),
            'coverage_percent' => $capacity > 0 ? (int)round(($filled / $capacity) * 100) : 0,
            'by_shift' => $byShift,
            'by_day' => array_values($byDay),
        ];
    }

    private function autoRosterShiftForEmployee(array $employee, array $shiftNames, int $index): string
    {
        $roleText = strtolower((string)($employee['department'] ?? '') . ' ' . (string)($employee['position'] ?? ''));
        if (str_contains($roleText, 'doctor') || str_contains($roleText, 'medical') || str_contains($roleText, 'physician') || str_contains($roleText, 'surgeon')) {
            return in_array('General', $shiftNames, true) ? 'General' : ($shiftNames[0] ?? 'General');
        }
        if (str_contains($roleText, 'nurse') || str_contains($roleText, 'nursing') || str_contains($roleText, 'technician') || str_contains($roleText, 'tech')) {
            $rotation = array_values(array_intersect(['Morning', 'Afternoon', 'Night'], $shiftNames));
            if ($rotation === []) {
                $rotation = array_values($shiftNames);
            }
            return $rotation[$index % count($rotation)];
        }
        return $shiftNames[$index % count($shiftNames)] ?? 'General';
    }

    private function nextEmployeeCode(string $location, ?int $excludeEmployeeId = null): string
    {
        $location = $this->validEmployeeLocation($location);
        $params = ['prefix' => $location . '%'];
        $where = 'employee_code LIKE :prefix';
        if ($excludeEmployeeId !== null) {
            $where .= ' AND id <> :id';
            $params['id'] = $excludeEmployeeId;
        }
        $row = $this->db->fetch(
            "SELECT employee_code
             FROM employees
             WHERE {$where}
             ORDER BY CAST(SUBSTRING(employee_code, :start_pos) AS UNSIGNED) DESC
             LIMIT 1",
            $params + ['start_pos' => strlen($location) + 1]
        );
        $last = $row['employee_code'] ?? '';
        $next = 1;
        if (preg_match('/^' . preg_quote($location, '/') . '(\d+)$/', (string)$last, $matches)) {
            $next = ((int)$matches[1]) + 1;
        }
        return $location . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    private function employeeWeekFallbackShift(int $employeeId, string $weekStart, string $weekEnd): ?string
    {
        $row = $this->db->fetch(
            'SELECT shift_name
             FROM duty_roster
             WHERE employee_id=:employee_id
               AND duty_date BETWEEN :start AND :end
               AND UPPER(shift_name)<>"OFF"
             ORDER BY duty_date ASC
             LIMIT 1',
            ['employee_id' => $employeeId, 'start' => $weekStart, 'end' => $weekEnd]
        );
        $shiftName = trim((string)($row['shift_name'] ?? ''));
        return $shiftName !== '' ? $shiftName : null;
    }

    private function ensureEmployeeOneWeeklyOff(int $employeeId, string $weekStart, string $weekEnd, string $fallbackShift, string $fallbackStart, string $fallbackEnd): void
    {
        $offRows = $this->db->fetchAll(
            'SELECT duty_date
             FROM duty_roster
             WHERE employee_id=:employee_id
               AND duty_date BETWEEN :start AND :end
               AND UPPER(shift_name)="OFF"
             ORDER BY duty_date ASC',
            ['employee_id' => $employeeId, 'start' => $weekStart, 'end' => $weekEnd]
        );
        if (count($offRows) === 0) {
            $offDate = $this->leastLoadedDepartmentOffDate($employeeId, $weekStart, $weekEnd);
            $this->db->execute(
                'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                ['employee_id' => $employeeId, 'duty_date' => $offDate]
            );
            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                 VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $offDate,
                    'shift_name' => 'OFF',
                    'start_time' => '00:00',
                    'end_time' => '00:00',
                    'ward' => null,
                    'notes' => 'Auto weekly OFF by department workload',
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
            return;
        }
        if (count($offRows) === 1) {
            return;
        }
        $keepDate = (string)$offRows[0]['duty_date'];
        foreach ($offRows as $offRow) {
            $offDate = (string)$offRow['duty_date'];
            if ($offDate === $keepDate) {
                continue;
            }
            $this->db->execute(
                'DELETE FROM duty_roster WHERE employee_id=:employee_id AND duty_date=:duty_date',
                ['employee_id' => $employeeId, 'duty_date' => $offDate]
            );
            $this->db->execute(
                'INSERT INTO duty_roster(employee_id,duty_date,shift_name,start_time,end_time,ward,notes,created_by,created_at,updated_at)
                 VALUES(:employee_id,:duty_date,:shift_name,:start_time,:end_time,:ward,:notes,:created_by,NOW(),NOW())',
                [
                    'employee_id' => $employeeId,
                    'duty_date' => $offDate,
                    'shift_name' => $fallbackShift,
                    'start_time' => $fallbackStart,
                    'end_time' => $fallbackEnd,
                    'ward' => null,
                    'notes' => 'Extra weekly OFF normalized',
                    'created_by' => (int)$this->auth->user()['id'],
                ]
            );
        }
    }

    private function leastLoadedDepartmentOffDate(int $employeeId, string $weekStart, string $weekEnd): string
    {
        $employee = $this->db->fetch('SELECT department FROM employees WHERE id=:id LIMIT 1', ['id' => $employeeId]) ?: [];
        $department = trim((string)($employee['department'] ?? ''));
        $counts = [];
        $params = ['start' => $weekStart, 'end' => $weekEnd];
        $departmentSql = '';
        if ($department !== '') {
            $departmentSql = ' AND e.department COLLATE utf8mb4_general_ci = :department';
            $params['department'] = $department;
        }
        $rows = $this->db->fetchAll(
            'SELECT dr.duty_date, COUNT(*) total
             FROM duty_roster dr
             JOIN employees e ON e.id = dr.employee_id
             WHERE dr.duty_date BETWEEN :start AND :end
               AND UPPER(dr.shift_name)="OFF"' . $departmentSql . '
             GROUP BY dr.duty_date',
            $params
        );
        foreach ($rows as $row) {
            $counts[(string)$row['duty_date']] = (int)$row['total'];
        }
        $bestDate = $weekStart;
        $bestCount = PHP_INT_MAX;
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($weekStart . " +{$i} day"));
            $count = $counts[$date] ?? 0;
            if ($count < $bestCount) {
                $bestDate = $date;
                $bestCount = $count;
            }
        }
        return $bestDate;
    }

    private function dutyRosterReturnUrl(string $weekStart, string $showMonth, string $department, int $scrollTop = 0, ?int $employeeId = null): string
    {
        $params = [
            'route' => 'duty-roster',
            'week_start' => $weekStart,
            'show_month' => $showMonth,
        ];
        if ($department !== '') {
            $params['department'] = $department;
        }
        if ($scrollTop > 0) {
            $params['scroll_top'] = $scrollTop;
        }
        $url = '?' . http_build_query($params);
        if ($employeeId !== null && $employeeId > 0) {
            $url .= '#employee-' . $employeeId;
        }
        return $url;
    }

    private function regenerateEmployeeCodes(): int
    {
        $employees = $this->db->fetchAll(
            "SELECT id, location
             FROM employees
             ORDER BY FIELD(location,'KH','KCI','KNS','MANAGEMENT'), created_at, id"
        );
        $counters = ['KH' => 0, 'KCI' => 0, 'KNS' => 0, 'MANAGEMENT' => 0];
        $updated = 0;
        foreach ($employees as $employee) {
            $location = $this->validEmployeeLocation((string)($employee['location'] ?? ''));
            $counters[$location]++;
            $code = $location . str_pad((string)$counters[$location], 3, '0', STR_PAD_LEFT);
            $this->db->execute(
                'UPDATE employees SET employee_code=:employee_code, location=:location WHERE id=:id',
                ['employee_code' => $code, 'location' => $location, 'id' => (int)$employee['id']]
            );
            $updated++;
        }
        return $updated;
    }

    private function validOnboardingStatus(string $status): string
    {
        return in_array($status, ['Pending', 'In Progress', 'Completed'], true) ? $status : 'Pending';
    }

    private function onboardingStatusFromChecklist(string $status, array $checks): string
    {
        $status = $this->validOnboardingStatus($status);
        $done = array_sum(array_map('intval', $checks));
        if ($done === 4) {
            return 'Completed';
        }
        if ($status === 'Completed') {
            flash('error', 'Complete all onboarding checklist items before marking onboarding as completed.');
            redirect('?route=onboarding');
        }
        if ($status === 'Pending' && $done > 0) {
            return 'In Progress';
        }
        return $status;
    }

    private function validTrainingStatus(string $status): string
    {
        return in_array($status, ['Assigned', 'In Progress', 'Completed'], true) ? $status : 'Assigned';
    }

    private function assignTrainingEmployees(int $sessionId, array|string $employeeIds): void
    {
        $ids = is_array($employeeIds) ? $employeeIds : [$employeeIds];
        if (in_array('all', $ids, true)) {
            $ids = array_column($this->db->fetchAll('SELECT id FROM employees'), 'id');
        }
        foreach ($ids as $employeeId) {
            $employeeId = (int)$employeeId;
            if ($employeeId <= 0) {
                continue;
            }
            $this->db->execute('INSERT IGNORE INTO employee_training(session_id,employee_id,status,created_at) VALUES(:session_id,:employee_id,"Assigned",NOW())', [
                'session_id' => $sessionId,
                'employee_id' => $employeeId,
            ]);
            $this->notifications->notifyEmployee($employeeId, 'Training Assigned', 'A training session has been assigned to you.', 'training');
        }
    }

    private function employeeKpiStats(int $employeeId, string $department): array
    {
        $mine = $this->db->fetch(
            'SELECT AVG(kpi_score) average_score, MAX(kpi_score) best_score, COUNT(*) review_count
             FROM performance WHERE employee_id=:employee_id',
            ['employee_id' => $employeeId]
        ) ?: [];
        $latest = $this->db->fetch(
            'SELECT kpi_score, review_date FROM performance WHERE employee_id=:employee_id ORDER BY review_date DESC, id DESC LIMIT 1',
            ['employee_id' => $employeeId]
        );
        $org = $this->db->fetch('SELECT AVG(kpi_score) average_score FROM performance') ?: [];
        $dept = $department !== ''
            ? ($this->db->fetch('SELECT AVG(p.kpi_score) average_score FROM performance p JOIN employees e ON e.id=p.employee_id WHERE e.department=:department', ['department' => $department]) ?: [])
            : [];
        $rankRows = $this->db->fetchAll(
            'SELECT employee_id, AVG(kpi_score) average_score
             FROM performance
             GROUP BY employee_id
             ORDER BY average_score DESC'
        );
        $rank = null;
        foreach ($rankRows as $index => $row) {
            if ((int)$row['employee_id'] === $employeeId) {
                $rank = $index + 1;
                break;
            }
        }
        return [
            'average' => isset($mine['average_score']) ? round((float)$mine['average_score'], 2) : null,
            'best' => isset($mine['best_score']) ? round((float)$mine['best_score'], 2) : null,
            'latest' => $latest ? round((float)$latest['kpi_score'], 2) : null,
            'latest_date' => $latest['review_date'] ?? null,
            'review_count' => (int)($mine['review_count'] ?? 0),
            'org_average' => isset($org['average_score']) ? round((float)$org['average_score'], 2) : null,
            'department_average' => isset($dept['average_score']) ? round((float)$dept['average_score'], 2) : null,
            'rank' => $rank,
            'rank_total' => count($rankRows),
        ];
    }

    private function employeeAttendanceReportData(int $employeeId, string $month, ?string $rangeStart = null, ?string $rangeEnd = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $rangeStart = ($rangeStart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeStart)) ? $rangeStart : $monthStart;
        $rangeEnd = ($rangeEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeEnd)) ? $rangeEnd : $monthEnd;
        $rows = $this->calculatedAttendanceStatusRows($rangeStart, $rangeEnd, $employeeId);

        $expectedDays = 0;
        $cursor = strtotime($rangeStart);
        $last = strtotime($rangeEnd);
        while ($cursor !== false && $last !== false && $cursor <= $last) {
            $date = date('Y-m-d', $cursor);
            if ((int)date('N', strtotime($date)) <= 5) {
                $expectedDays++;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        $presentDays = 0;
        $absentDays = 0;
        $countedHours = 0.0;
        $remarks = [];
        foreach ($rows as $row) {
            $status = strtolower(trim((string)$row['status']));
            if ($status === 'on_leave' || $status === 'off_duty') {
                $expectedDays--;
                continue;
            }
            if ($status === 'absent') {
                $absentDays++;
                $remarks[] = [
                    'date' => $row['attendance_date'],
                    'remarks' => 'Absent',
                    'duration' => '0h 0m',
                ];
                continue;
            }

            if (in_array($status, ['present', 'late', 'on_duty'], true)) {
                $presentDays++;
            }
            $hours = $this->attendanceWorkHours($row['check_in'] ?? null, $row['check_out'] ?? null);
            if ($hours <= 0 && in_array($status, ['present', 'late', 'on_duty'], true)) {
                $hours = 8.0;
            }
            $countedHours += $hours;
            if (in_array($status, ['present', 'late'], true) && (empty($row['check_in']) || empty($row['check_out']))) {
                $remarks[] = [
                    'date' => $row['attendance_date'],
                    'remarks' => 'Punch time not available',
                    'duration' => $this->formatHoursMinutes($hours),
                ];
            }
        }

        $expectedHours = $expectedDays * 8.0;
        return [
            'expected_hours' => $expectedHours,
            'counted_hours' => $countedHours,
            'excess_hours' => max(0.0, $countedHours - $expectedHours),
            'shortage_hours' => max(0.0, $expectedHours - $countedHours),
            'unauthorized_absence_days' => $absentDays,
            'present_days' => $presentDays,
            'last_compensation_day' => $rangeEnd,
            'rows' => $rows,
            'remarks' => $remarks,
        ];
    }

    private function formatHoursMinutes(float $hours): string
    {
        $minutes = max(0, (int)round($hours * 60));
        return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
    }

    private function employeeExists(int $employeeId): bool
    {
        if ($employeeId <= 0) {
            return false;
        }
        return (bool)$this->db->fetch('SELECT id FROM employees WHERE id=:id LIMIT 1', ['id' => $employeeId]);
    }

    private function employeeAttendanceCalendar(int $employeeId, string $month, ?string $rangeStart = null, ?string $rangeEnd = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $calendarStart = ($rangeStart && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeStart)) ? $rangeStart : $monthStart;
        $calendarEnd = ($rangeEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeEnd)) ? $rangeEnd : $monthEnd;
        $calendar = [];
        foreach ($this->calculatedAttendanceStatusRows($calendarStart, $calendarEnd, $employeeId) as $row) {
            $calendar[$row['attendance_date']] = (string)$row['status'];
        }
        return compact('month', 'monthStart', 'monthEnd', 'calendar');
    }
}
