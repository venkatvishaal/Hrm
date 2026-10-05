<?php require __DIR__ . '/../layouts/header.php'; ?>
<h1>Document Verification</h1>

<div class="stats">
  <div class="card"><strong>Total</strong><br><?= (int)($summary['total'] ?? 0) ?></div>
  <div class="card status-pending"><strong>Pending</strong><br><?= (int)($summary['pending'] ?? 0) ?></div>
  <div class="card status-approved"><strong>Approved</strong><br><?= (int)($summary['approved'] ?? 0) ?></div>
  <div class="card status-rejected"><strong>Rejected</strong><br><?= (int)($summary['rejected'] ?? 0) ?></div>
</div>

<section class="card" id="credentials">
  <div class="training-head">
    <div>
      <h2>Credential / Licence Expiry Tracking</h2>
      <p class="muted">Track nursing council registration, doctor licence, technician licence, BLS/ACLS, fire safety, and infection control renewals.</p>
    </div>
  </div>
  <div class="stats">
    <div class="card"><strong>Credentials</strong><br><?= (int)($credentialSummary['total'] ?? 0) ?></div>
    <div class="card status-rejected"><strong>Expired</strong><br><?= (int)($credentialSummary['expired'] ?? 0) ?></div>
    <div class="card status-pending"><strong>Expiring 30 Days</strong><br><?= (int)($credentialSummary['expiring_30'] ?? 0) ?></div>
    <div class="card status-onduty"><strong>Expiring 60 Days</strong><br><?= (int)($credentialSummary['expiring_60'] ?? 0) ?></div>
  </div>
  <form method="post" action="?route=credentials.store" class="grid">
    <?= csrf_field() ?>
    <label>Employee
      <select name="employee_id" required>
        <option value="">Select Employee</option>
        <?php foreach (($employees ?? []) as $employee): ?>
          <option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars(trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']) . ' - ' . (string)$employee['employee_code']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Credential Type
      <select name="credential_type" required>
        <?php foreach (['Nursing Council Registration','Doctor Licence','Technician Licence','BLS','ACLS','Fire Safety','Infection Control Certificate'] as $type): ?>
          <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Credential No<input name="credential_number"></label>
    <label>Issuing Authority<input name="issuing_authority"></label>
    <label>Issue Date<input type="date" name="issue_date"></label>
    <label>Expiry Date<input type="date" name="expiry_date"></label>
    <label>Status
      <select name="status">
        <option>Active</option>
        <option>Renewal Pending</option>
        <option>Expired</option>
      </select>
    </label>
    <label class="wide-field">Remarks<textarea name="remarks"></textarea></label>
    <button>Save Credential</button>
  </form>
  <div class="table-scroll">
    <table>
      <tr><th>Employee</th><th>Credential</th><th>Authority</th><th>Issue</th><th>Expiry</th><th>Status</th><th>Action</th></tr>
      <?php foreach (($credentials ?? []) as $credential): ?>
        <?php
          $expiryClass = 'status-approved';
          if (!empty($credential['expiry_date']) && $credential['expiry_date'] < date('Y-m-d')) { $expiryClass = 'status-rejected'; }
          elseif (!empty($credential['expiry_date']) && $credential['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) { $expiryClass = 'status-pending'; }
        ?>
        <tr>
          <td><?= htmlspecialchars(trim((string)$credential['first_name'] . ' ' . (string)$credential['last_name'])) ?><br><span class="muted"><?= htmlspecialchars((string)$credential['employee_code']) ?></span></td>
          <td><?= htmlspecialchars((string)$credential['credential_type']) ?><br><span class="muted"><?= htmlspecialchars((string)$credential['credential_number']) ?></span></td>
          <td><?= htmlspecialchars((string)$credential['issuing_authority']) ?></td>
          <td><?= htmlspecialchars(fmt_date($credential['issue_date'] ?? null)) ?></td>
          <td><span class="status-badge <?= $expiryClass ?>"><?= htmlspecialchars(fmt_date($credential['expiry_date'] ?? null)) ?></span></td>
          <td><?= htmlspecialchars((string)$credential['status']) ?></td>
          <td>
            <form method="post" action="?route=credentials.delete" onsubmit="return confirm('Delete credential?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$credential['id'] ?>">
              <button class="btn-compact btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<form method="get" class="card grid">
  <input type="hidden" name="route" value="documents">
  <select name="status">
    <option value="">All Statuses</option>
    <?php foreach (['Pending','Approved','Rejected'] as $s): ?>
      <option value="<?= $s ?>" <?= ($status ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button>Filter</button>
</form>

<div class="table-scroll">
<table>
  <tr><th>Employee</th><th>Department</th><th>Type</th><th>Uploaded</th><th>Status</th><th>Verification</th></tr>
  <?php foreach (($items ?? []) as $item): ?>
    <tr>
      <td><?= htmlspecialchars(trim((string)$item['first_name'] . ' ' . (string)$item['last_name'])) ?><br><span class="muted"><?= htmlspecialchars((string)$item['employee_code']) ?></span></td>
      <td><?= htmlspecialchars((string)$item['department']) ?></td>
      <td><?= htmlspecialchars((string)$item['document_type']) ?></td>
      <td><?= fmt_date($item['uploaded_at']) ?></td>
      <td><span class="status-badge status-<?= strtolower((string)$item['verification_status']) ?>"><?= htmlspecialchars((string)$item['verification_status']) ?></span></td>
      <td>
        <form method="post" action="?route=documents.update" class="leave-status-quick">
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <select name="verification_status">
            <?php foreach (['Pending','Approved','Rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $item['verification_status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <input name="hr_remarks" value="<?= htmlspecialchars((string)$item['hr_remarks']) ?>" placeholder="HR remarks">
          <button class="btn-compact">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
