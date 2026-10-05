<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="content-body">
  <!-- ── 1. Page Header ── -->
  <div class="dashboard-header-greeting" style="margin-bottom:20px">
    <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">My Official Documents & Health Records</h1>
    <p style="font-size:13.5px;color:#64748b;margin:0">Upload compliance certificates, identity proofs, and access your annual health screening reports.</p>
  </div>

  <div style="display:grid;grid-template-columns:1.2fr 1.8fr;gap:22px;align-items:start">
    <!-- Left Column: Upload Document -->
    <div class="card" style="padding:24px;margin:0;border-radius:14px">
      <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 4px">Upload Compliance Document</h3>
      <p style="font-size:12px;color:#64748b;margin:0 0 18px">Upload PDF or high-resolution photos of credentials.</p>

      <form method="post" action="?route=employee.document.store" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">
        <?= csrf_field() ?>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Document Category *</label>
          <select name="document_type" required style="width:100%">
            <option value="">Select Document Type</option>
            <option>Educational Certificate</option>
            <option>Experience Certificate</option>
            <option>ID Proof (Aadhaar / Voter ID)</option>
            <option>Address Proof</option>
            <option>NABH Mandatory Training Record</option>
            <option>Medical Fitness Certificate</option>
            <option>Other Certification</option>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">File Attachment *</label>
          <input id="employeeDocumentFile" type="file" name="document_file" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" capture="environment" data-optimize-image="document" required style="width:100%;font-size:12.5px;padding:8px;border:1px dashed #cbd5e1;border-radius:8px">
          <small style="display:block;font-size:11px;color:#64748b;margin-top:4px">Max size: 2 MB. Acceptable: PDF, JPG, PNG, WEBP.</small>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Remarks / Notes</label>
          <textarea name="remarks" rows="2" placeholder="e.g. Renewed license 2026, Degree certificate..." style="width:100%"></textarea>
        </div>

        <button type="submit" class="btn-primary" style="background:#0d474c;padding:10px 20px;font-size:13px;font-weight:700;margin-top:6px">
          Upload Document
        </button>
      </form>
    </div>

    <!-- Right Column: Document List & Health Reports -->
    <div style="display:flex;flex-direction:column;gap:20px">
      <!-- Uploaded Documents Card -->
      <div class="card" style="padding:0;margin:0;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Submitted Documents</h3>
          <span style="font-size:12px;color:#64748b"><?= count($documents ?? []) ?> document(s)</span>
        </div>

        <div class="table-scroll">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Document Type</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Uploaded</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Status</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase;text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($documents)): ?>
                <tr>
                  <td colspan="4" style="padding:24px 16px;text-align:center;color:#64748b;font-size:13px">
                    No documents uploaded yet.
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach (($documents ?? []) as $doc): ?>
                <?php
                  $vStatus = (string)($doc['verification_status'] ?? 'Pending');
                  $vColor = match(strtolower($vStatus)) {
                    'verified', 'approved' => 'background:#dcfce7;color:#15803d',
                    'rejected' => 'background:#fee2e2;color:#b91c1c',
                    default => 'background:#fef3c7;color:#b45309',
                  };
                ?>
                <tr style="border-bottom:1px solid #f1f5f9">
                  <td style="padding:10px 16px;font-size:13px;font-weight:600;color:#0f172a">
                    <?= htmlspecialchars((string)$doc['document_type']) ?>
                  </td>
                  <td style="padding:10px 16px;font-size:12px;color:#64748b">
                    <?= fmt_date($doc['uploaded_at']) ?>
                  </td>
                  <td style="padding:10px 16px">
                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;<?= $vColor ?>">
                      <?= htmlspecialchars($vStatus) ?>
                    </span>
                  </td>
                  <td style="padding:10px 16px;text-align:right">
                    <a href="?route=employee.document.download&id=<?= (int)$doc['id'] ?>" style="font-size:12px;font-weight:700;color:#0f766e;text-decoration:none">
                      Download 📥
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Annual Health Screening Reports -->
      <div class="card" style="padding:0;margin:0;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
          <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Annual Health Screening Reports</h3>
          <span style="font-size:12px;color:#64748b">NABH Staff Health Record</span>
        </div>

        <div class="table-scroll">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Checkup Date</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Facility</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Next Due</th>
                <th style="padding:10px 16px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase;text-align:right">Report</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($healthReports)): ?>
                <tr>
                  <td colspan="4" style="padding:24px 16px;text-align:center;color:#64748b;font-size:13px">
                    No hospital screening records on file.
                  </td>
                </tr>
              <?php endif; ?>

              <?php foreach (($healthReports ?? []) as $report): ?>
                <tr style="border-bottom:1px solid #f1f5f9">
                  <td style="padding:10px 16px;font-size:12.5px;color:#0f172a;font-weight:600">
                    <?= fmt_date($report['checkup_date']) ?>
                  </td>
                  <td style="padding:10px 16px;font-size:12.5px;color:#475569">
                    <?= htmlspecialchars((string)($report['hospital_name'] ?: 'Krishna Hospital')) ?>
                  </td>
                  <td style="padding:10px 16px;font-size:12px;color:#0f766e;font-weight:600">
                    <?= fmt_date($report['next_due_date']) ?>
                  </td>
                  <td style="padding:10px 16px;text-align:right">
                    <?php if (!empty($report['report_file_path'])): ?>
                      <a href="?route=employee.health.download&id=<?= (int)$report['id'] ?>" style="font-size:12px;font-weight:700;color:#0f766e;text-decoration:none">
                        Download 📄
                      </a>
                    <?php else: ?>
                      <span style="font-size:12px;color:#94a3b8">On Record</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
