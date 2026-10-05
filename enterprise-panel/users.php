<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
requireRole('admin');

$errors = [];
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!validate_csrf_token()) {
        $errors[] = 'Security token validation failed.';
    }

    if (!$errors && $action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');
        $status = (string)($_POST['status'] ?? 'active');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
            $errors[] = 'Username must be 3-80 characters and contain only letters, numbers, dots, hyphens, or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!in_array($role, ['admin', 'editor', 'user'], true)) {
            $errors[] = 'Invalid role selected.';
        }
        if (!in_array($status, ['active', 'inactive', 'locked'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (!$errors) {
            try {
                db_execute(
                    'INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES (:username, :email, :password_hash, :role, :status, NOW())',
                    [
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'status' => $status,
                    ]
                );
                $_SESSION['flash_success'] = 'User created successfully.';
                header('Location: users.php');
                exit;
            } catch (Throwable $exception) {
                app_log_error($exception, ['file' => __FILE__, 'query' => 'insert user']);
                $errors[] = 'Unable to create user. Review the application log for details.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!validate_csrf_token()) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;

    if ($id <= 0 || $id === current_user_id()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid user selected']);
        exit;
    }

    try {
        db_execute('DELETE FROM users WHERE id = :id', ['id' => $id]);
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Throwable $exception) {
        app_log_error($exception, ['file' => __FILE__, 'query' => 'delete user']);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Delete failed']);
    }
    exit;
}

try {
    $users = db_execute('SELECT id, username, email, role, status, created_at FROM users ORDER BY id DESC')->fetchAll();
} catch (Throwable $exception) {
    app_log_error($exception, ['file' => __FILE__, 'query' => 'list users']);
    $users = [];
    $errors[] = 'Unable to load users.';
}

$exportTracking = hash_hmac('sha256', 'users-export', $_SESSION['csrf_token'] ?? '');
$pageTitle = 'Users';
require __DIR__ . '/templates/header.php';
?>

<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?>
      <div><?= e($error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="data-card">
  <div class="card-header">
    <div>
      <h2>User Directory</h2>
      <p>Role-aware account management.</p>
    </div>
    <a class="btn btn-outline" href="export.php?table=users&amp;tracking=<?= e($exportTracking) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5 20h14v-2H5v2Zm7-18-5 5h3v6h4V7h3l-5-5Z"/></svg>
      Export CSV
    </a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <tr id="user-row-<?= e($user['id']) ?>">
          <td><?= e($user['id']) ?></td>
          <td><?= e($user['username']) ?></td>
          <td><?= e($user['email']) ?></td>
          <td><span class="badge badge-warning"><?= e($user['role']) ?></span></td>
          <td><span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e($user['status']) ?></span></td>
          <td><?= e($user['created_at']) ?></td>
          <td>
            <div class="actions">
              <a class="btn btn-outline" href="users.php?edit=<?= e($user['id']) ?>">Edit</a>
              <button class="btn btn-danger js-delete-user" type="button" data-id="<?= e($user['id']) ?>">Delete</button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <footer class="pagination">
    <span><?= e(count($users)) ?> records</span>
  </footer>
</section>

<section class="data-card">
  <div class="card-header">
    <div>
      <h2>Create User</h2>
      <p>Validated server-side and protected by CSRF.</p>
    </div>
  </div>
  <form method="post" action="users.php" class="form-grid" autocomplete="off">
    <?= render_csrf_token() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group">
      <label for="username">Username</label>
      <input class="form-control" id="username" name="username" required pattern="[a-zA-Z0-9._-]{3,80}">
    </div>
    <div class="form-group">
      <label for="email">Email</label>
      <input class="form-control" id="email" name="email" type="email" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input class="form-control" id="password" name="password" type="password" minlength="8" required>
    </div>
    <div class="form-group">
      <label for="role">Role</label>
      <select class="form-control" id="role" name="role">
        <option value="user">User</option>
        <option value="editor">Editor</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="form-group">
      <label for="status">Status</label>
      <select class="form-control" id="status" name="status">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="locked">Locked</option>
      </select>
    </div>
    <div class="form-group">
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Create User</button>
    </div>
  </form>
</section>

<script>
document.querySelectorAll('.js-delete-user').forEach((button) => {
  button.addEventListener('click', async () => {
    const id = button.dataset.id;
    if (!id || !window.confirm('Delete this user?')) {
      return;
    }

    button.disabled = true;
    const response = await fetch('users.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= e($_SESSION['csrf_token'] ?? '') ?>'
      },
      body: JSON.stringify({ id })
    });

    const result = await response.json().catch(() => ({ success: false }));
    if (!response.ok || !result.success) {
      button.disabled = false;
      alert(result.message || 'Delete failed.');
      return;
    }

    const row = document.getElementById(`user-row-${id}`);
    if (row) {
      row.classList.add('is-removing');
      window.setTimeout(() => row.remove(), 220);
    }
  });
});
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
