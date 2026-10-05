<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$permissionTime = '-';
if (strcasecmp((string)($item['leave_type'] ?? ''), 'Permission') === 0) {
  $permissionTime = trim(substr((string)($item['permission_start_time'] ?? ''), 0, 5) . ' - ' . substr((string)($item['permission_end_time'] ?? ''), 0, 5), ' -') ?: '-';
}
$empName = trim((string)$item['first_name'] . ' ' . (string)$item['last_name']);
$st = (string)($item['status'] ?? 'Pending');
$isPending = strcasecmp($st, 'Pending') === 0;
$isApproved = strcasecmp($st, 'Approved') === 0;
$isRejected = strcasecmp($st, 'Rejected') === 0;
$statusBg = $isPending ? '#fef3c7' : ($isApproved ? '#dcfce7' : '#fee2e2');
$statusColor = $isPending ? '#b45309' : ($isApproved ? '#15803d' : '#b91c1c');
?>

<div class="content-body" style="max-width:880px;margin:0 auto">
  <!-- Back Link -->
  <div style="margin-bottom:12px">
    <a href="?route=leave" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#64748b;text-decoration:none">
      ← Back to Leave Requests
    </a>
  </div>

  <!-- Review Card -->
  <div class="card" style="padding:28px 32px;margin:0">
    <!-- Header with Status -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;padding-bottom:18px;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:12px">
      <div style="display:flex;align-items:center;gap:16px">
        <div style="width:48px;height:48px;border-radius:50%;background:#e6f4f1;border:2px solid #0f766e;color:#0f766e;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px">
          <?= htmlspecialchars(strtoupper(substr($empName ?: 'S', 0, 2))) ?>
        </div>
        <div>
          <h2 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 4px"><?= htmlspecialchars($empName) ?></h2>
          <div style="font-size:12.5px;color:#64748b">
            <?= htmlspecialchars((string)$item['position']) ?> • <?= htmlspecialchars((string)$item['department']) ?> • <?= htmlspecialchars((string)$item['employee_code']) ?>
          </div>
        </div>
      </div>
      <div>
        <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $statusBg ?>;color:<?= $statusColor ?>">
          <?= htmlspecialchars($st) ?>
        </span>
      </div>
    </div>

    <!-- Details Grid -->
    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:18px;margin-bottom:24px">
      <div style="background:#f8fafc;padding:14px;border-radius:10px">
        <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Request Type</span>
        <strong style="font-size:14px;color:#0f172a"><?= htmlspecialchars((string)$item['leave_type']) ?></strong>
      </div>
      <div style="background:#f8fafc;padding:14px;border-radius:10px">
        <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Date Range</span>
        <strong style="font-size:13.5px;color:#0f172a"><?= htmlspecialchars(fmt_date($item['start_date'])) ?> – <?= htmlspecialchars(fmt_date($item['end_date'])) ?></strong>
      </div>
      <div style="background:#f8fafc;padding:14px;border-radius:10px">
        <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Permission Time</span>
        <strong style="font-size:13.5px;color:#0f172a"><?= htmlspecialchars($permissionTime) ?></strong>
      </div>
      <div style="background:#f8fafc;padding:14px;border-radius:10px">
        <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Approval Level</span>
        <strong style="font-size:13.5px;color:#0f172a">Level <?= (int)$approvalLevel ?></strong>
      </div>
      <div style="background:#f8fafc;padding:14px;border-radius:10px;grid-column:span 2">
        <span style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Assigned Approval Roles</span>
        <strong style="font-size:13px;color:#0f172a"><?= htmlspecialchars(implode(', ', $assignedRoles)) ?></strong>
      </div>
    </div>

    <!-- Reason Box -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin-bottom:28px">
      <span style="display:block;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px">Reason for Leave</span>
      <p style="font-size:13.5px;color:#1e293b;line-height:1.5;margin:0">
        <?= nl2br(htmlspecialchars((string)($item['reason'] ?? 'None provided.'))) ?>
      </p>
    </div>

    <!-- Decision Action Buttons -->
    <?php if ($canDecide): ?>
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;padding-top:18px;border-top:1px solid #e2e8f0">
        <form method="post" action="?route=leave.status">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <input type="hidden" name="status" value="Rejected">
          <input type="hidden" name="next_route" value="leave.decision">
          <input type="hidden" name="decision_id" value="<?= (int)$item['id'] ?>">
          <button type="submit" class="btn-danger" style="padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700">Reject Request</button>
        </form>

        <form method="post" action="?route=leave.status">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <input type="hidden" name="status" value="Approved">
          <input type="hidden" name="next_route" value="leave.decision">
          <input type="hidden" name="decision_id" value="<?= (int)$item['id'] ?>">
          <button type="submit" class="btn-primary" style="background:#15803d;padding:10px 24px;border-radius:10px;font-size:13px;font-weight:700">Approve Leave</button>
        </form>
      </div>
    <?php else: ?>
      <div style="background:#f1f5f9;border-radius:10px;padding:14px 18px;font-size:13px;color:#64748b;text-align:center">
        This request has been finalized or is pending with another role in the hierarchy.
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
