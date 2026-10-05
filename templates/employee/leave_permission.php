<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$permissionTimeRange = static function (array $request): string {
  if (strcasecmp((string)($request['leave_type'] ?? ''), 'Permission') !== 0) {
    return '';
  }
  $start = substr((string)($request['permission_start_time'] ?? ''), 0, 5);
  $end = substr((string)($request['permission_end_time'] ?? ''), 0, 5);
  return trim($start . ' - ' . $end, ' -') ?: '';
};

$all = array_merge($myLeaves ?? [], $myPermissions ?? []);
usort($all, static function ($a, $b) {
  return strtotime($b['created_at'] ?? $b['start_date']) <=> strtotime($a['created_at'] ?? $a['start_date']);
});

$formatBalance = static function (float $value): string {
  return rtrim(rtrim(number_format($value, 1), '0'), '.');
};

$totalLeaveNum = (float)($leaveBalance['total'] ?? 18);
$availedLeaveNum = (float)($leaveBalance['availed'] ?? 0);
$balanceLeaveNum = (float)($leaveBalance['balance'] ?? 18);
$totalPermissionNum = (float)($permissionBalance['total'] ?? 2);
?>

<div class="content-body">
  <!-- ── Page Header (Screen 5) ── -->
  <div class="dashboard-header-greeting">
    <h1>Leave Management</h1>
    <p>Apply for leave, track status and view history.</p>
  </div>

  <!-- ── Tabs Navigation (Screen 5) ── -->
  <nav class="tab-nav" id="leaveTabs">
    <button type="button" class="active" data-tab-target="applyTab">Apply Leave</button>
    <button type="button" data-tab-target="requestsTab">My Requests</button>
  </nav>

  <!-- ── Tab 1: Apply Leave (Split 2-Column Layout) ── -->
  <div id="applyTab" class="leave-split-layout" style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;align-items:start">
    <!-- Left Column: Application Form -->
    <div class="card" style="margin:0">
      <form method="post" action="?route=employee.leave.store" id="leavePermissionForm" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px">
        <?= csrf_field() ?>

        <!-- Hidden request category toggle -->
        <input type="hidden" name="request_category" id="requestCategory" value="Leave">

        <!-- Leave / Permission Type Switcher -->
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Leave Type *</label>
          <select name="leave_type" id="leaveType" required style="width:100%">
            <option value="">Select Leave Type</option>
            <option value="Casual Leave" selected>Casual Leave</option>
            <option value="Sick Leave">Sick Leave</option>
            <option value="Earned Leave">Earned Leave</option>
            <option value="Comp Off">Comp Off</option>
            <option value="Loss of Pay">Loss of Pay</option>
            <option value="Permission">Short Permission (Max 2h)</option>
          </select>
        </div>

        <!-- Dates row: From Date & To Date -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" id="datesRow">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">From Date *</label>
            <input type="date" name="start_date" id="requestStartDate" min="<?= htmlspecialchars(date('Y-m-d')) ?>" value="<?= htmlspecialchars((string)($requestStartDate ?? date('Y-m-d'))) ?>" required>
          </div>
          <div id="toDateContainer">
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">To Date *</label>
            <input type="date" name="end_date" id="requestEndDate" min="<?= htmlspecialchars(date('Y-m-d')) ?>" value="<?= htmlspecialchars((string)($requestEndDate ?? date('Y-m-d'))) ?>">
          </div>
        </div>

        <!-- Number of Days Display -->
        <div id="dayCountRow">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Number of Days</label>
          <input type="text" id="requestDayCountInput" readonly value="1 Day" style="background:#f8fafc;font-weight:600;color:#0f766e">
        </div>

        <!-- Permission Times (Conditional) -->
        <div style="display:none;grid-template-columns:1fr 1fr;gap:14px" id="permissionTimesRow">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Permission From *</label>
            <input type="time" name="permission_start_time" id="permissionStartTime">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Permission To *</label>
            <input type="time" name="permission_end_time" id="permissionEndTime">
          </div>
        </div>

        <!-- Reason -->
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Reason *</label>
          <textarea name="reason" id="requestReason" rows="3" placeholder="Enter reason for leave..." required></textarea>
        </div>

        <!-- Medical Policy Notice -->
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;color:#475569">
          <span style="font-size:14px;color:#0f766e">ℹ</span>
          <span>Medical leave longer than 3 days requires certificate upload.</span>
        </div>

        <!-- Attach Document (Optional / Drag & Drop) -->
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Attach Document (Optional)</label>
          <div style="border:2px dashed #cbd5e1;border-radius:12px;padding:24px 16px;text-align:center;background:#f8fafc;cursor:pointer;transition:border-color .15s" onclick="document.getElementById('medicalCertificate').click()">
            <svg viewBox="0 0 24 24" width="32" height="32" fill="#94a3b8" style="margin-bottom:6px"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
            <div style="font-size:13px;font-weight:600;color:#0f172a">Drag & drop file here <span style="color:#0f766e">or click to browse</span></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px">(PDF, JPG, PNG - Max 5MB)</div>
            <div id="fileUploadName" style="font-size:12px;font-weight:700;color:#0f766e;margin-top:6px"></div>
            <input type="file" name="medical_certificate" id="medicalCertificate" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" style="display:none" onchange="if(this.files[0]){document.getElementById('fileUploadName').textContent = 'Selected: ' + this.files[0].name;}">
          </div>
        </div>

        <!-- Bottom Action Buttons -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:8px">
          <button type="reset" class="btn-secondary" style="border:none">Cancel</button>
          <button type="submit" class="btn-primary" id="leavePermissionSubmit" style="background:#0d474c">Submit Request</button>
        </div>
      </form>
    </div>

    <!-- Right Column: Leave Summary & Request History -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <!-- Leave Summary Card (Screen 5) -->
      <div class="card" style="margin:0">
        <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 16px">Leave Summary</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #f1f5f9;border-radius:10px;background:#ffffff">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="width:10px;height:10px;border-radius:50%;background:#0284c7"></span>
              <span style="font-size:13px;font-weight:600;color:#475569">Total Leave</span>
            </div>
            <strong style="font-size:18px;font-weight:800;color:#0f172a"><?= $formatBalance($totalLeaveNum) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #f1f5f9;border-radius:10px;background:#ffffff">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="width:10px;height:10px;border-radius:50%;background:#38bdf8"></span>
              <span style="font-size:13px;font-weight:600;color:#475569">Available Leave</span>
            </div>
            <strong style="font-size:18px;font-weight:800;color:#0f172a"><?= $formatBalance($availedLeaveNum) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #f1f5f9;border-radius:10px;background:#ffffff">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="width:10px;height:10px;border-radius:50%;background:#10b981"></span>
              <span style="font-size:13px;font-weight:600;color:#475569">Balance Leave</span>
            </div>
            <strong style="font-size:18px;font-weight:800;color:#0f766e"><?= $formatBalance($balanceLeaveNum) ?></strong>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #f1f5f9;border-radius:10px;background:#ffffff">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="width:10px;height:10px;border-radius:50%;background:#ef4444"></span>
              <span style="font-size:13px;font-weight:600;color:#475569">Total Permission</span>
            </div>
            <strong style="font-size:18px;font-weight:800;color:#0f172a"><?= $formatBalance($totalPermissionNum) ?>h</strong>
          </div>
        </div>
      </div>

      <!-- Request History Card (Screen 5) -->
      <div class="card" style="margin:0">
        <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 14px">Request History</h3>
        <?php if (empty($all)): ?>
          <p style="font-size:13px;color:#64748b;margin:0">No leave requests found.</p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach (array_slice($all, 0, 4) as $req): ?>
              <?php
                $status = strtolower((string)($req['status'] ?? 'pending'));
                $statusClass = match($status) {
                  'approved' => 'active',
                  'rejected' => 'rejected',
                  default    => 'pending',
                };
                $dateRange = fmt_date($req['start_date']);
                if (!empty($req['end_date']) && $req['end_date'] !== $req['start_date']) {
                  $dateRange .= ' - ' . fmt_date($req['end_date']);
                }
                $timeNote = $permissionTimeRange($req);
                if ($timeNote !== '') {
                  $dateRange .= ' • ' . $timeNote;
                }
              ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-radius:10px">
                <div>
                  <strong style="display:block;font-size:13px;color:#0f172a"><?= htmlspecialchars((string)$req['leave_type']) ?></strong>
                  <span style="font-size:11.5px;color:#64748b"><?= htmlspecialchars($dateRange) ?></span>
                </div>
                <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:14px;text-align:right">
            <button type="button" onclick="document.querySelector('[data-tab-target=requestsTab]').click()" style="background:none;border:none;color:#0f766e;font-size:12.5px;font-weight:700;cursor:pointer">View All Requests →</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Tab 2: Full Requests Table (My Requests) ── -->
  <div id="requestsTab" style="display:none">
    <div class="card" style="margin:0">
      <div class="chart-card-header">
        <h3 class="chart-card-title">All Leave & Permission Requests</h3>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>From</th>
              <th>To</th>
              <th>Time</th>
              <th>Status</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all)): ?>
              <tr><td colspan="7" style="text-align:center;color:#64748b;padding:24px">No leave or permission requests recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($all as $req): ?>
                <?php
                  $s = strtolower((string)($req['status'] ?? 'pending'));
                  $sClass = match($s) {
                    'approved' => 'active',
                    'rejected' => 'rejected',
                    default    => 'pending',
                  };
                ?>
                <tr>
                  <td>#<?= (int)$req['id'] ?></td>
                  <td><strong><?= htmlspecialchars((string)$req['leave_type']) ?></strong></td>
                  <td><?= fmt_date($req['start_date']) ?></td>
                  <td><?= fmt_date($req['end_date']) ?></td>
                  <td><?= htmlspecialchars($permissionTimeRange($req) ?: '-') ?></td>
                  <td><span class="badge-status <?= $sClass ?>"><?= htmlspecialchars(ucfirst($s)) ?></span></td>
                  <td><?= htmlspecialchars((string)$req['reason']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    // Tab switching
    var tabButtons = document.querySelectorAll('#leaveTabs button');
    var applyTab = document.getElementById('applyTab');
    var requestsTab = document.getElementById('requestsTab');

    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabButtons.forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var target = btn.getAttribute('data-tab-target');
        if (target === 'applyTab') {
          applyTab.style.display = 'grid';
          requestsTab.style.display = 'none';
        } else {
          applyTab.style.display = 'none';
          requestsTab.style.display = 'block';
        }
      });
    });

    // Form logic
    var leaveTypeSelect = document.getElementById('leaveType');
    var requestCategory = document.getElementById('requestCategory');
    var startDateInput = document.getElementById('requestStartDate');
    var endDateInput = document.getElementById('requestEndDate');
    var dayCountInput = document.getElementById('requestDayCountInput');
    var toDateContainer = document.getElementById('toDateContainer');
    var dayCountRow = document.getElementById('dayCountRow');
    var permissionTimesRow = document.getElementById('permissionTimesRow');
    var permStart = document.getElementById('permissionStartTime');
    var permEnd = document.getElementById('permissionEndTime');
    var submitBtn = document.getElementById('leavePermissionSubmit');

    function updateForm() {
      var isPerm = (leaveTypeSelect.value === 'Permission');
      requestCategory.value = isPerm ? 'Permission' : 'Leave';

      if (isPerm) {
        toDateContainer.style.display = 'none';
        dayCountRow.style.display = 'none';
        permissionTimesRow.style.display = 'grid';
        if (endDateInput) endDateInput.value = startDateInput.value;
        if (permStart) permStart.required = true;
        if (permEnd) permEnd.required = true;
        submitBtn.textContent = 'Submit Permission';
      } else {
        toDateContainer.style.display = 'block';
        dayCountRow.style.display = 'block';
        permissionTimesRow.style.display = 'none';
        if (permStart) permStart.required = false;
        if (permEnd) permEnd.required = false;
        submitBtn.textContent = 'Submit Request';

        // Calculate days
        var start = startDateInput.value ? new Date(startDateInput.value + 'T00:00:00') : null;
        var end = endDateInput.value ? new Date(endDateInput.value + 'T00:00:00') : null;
        if (start && end) {
          var count = Math.max(0, Math.floor((end - start) / 86400000) + 1);
          dayCountInput.value = count + (count === 1 ? ' Day' : ' Days');
        } else if (start) {
          dayCountInput.value = '1 Day';
        }
      }
    }

    leaveTypeSelect.addEventListener('change', updateForm);
    startDateInput.addEventListener('change', function() {
      if (endDateInput && (!endDateInput.value || endDateInput.value < startDateInput.value)) {
        endDateInput.value = startDateInput.value;
      }
      updateForm();
    });
    endDateInput.addEventListener('change', updateForm);
    updateForm();
  })();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
