<?php require __DIR__ . '/../layouts/header.php'; ?>
<section class="employee-standalone-page employee-onboarding-page">
  <section class="card employee-onboarding-card">
    <?php
      $onboardComplete = (int)$onboarding['orientation_done'] + (int)$onboarding['documents_collected'] + (int)$onboarding['assets_issued'] + (int)$onboarding['training_assigned'];
      $onboardProgress = $onboardComplete * 25;
    ?>
    <div class="training-head">
      <div>
        <h2>My Onboarding</h2>
        <p><strong>Status:</strong> <?= htmlspecialchars($onboarding['status']) ?> | <strong>Started:</strong> <?= fmt_date($onboarding['start_date']) ?></p>
      </div>
    </div>
    <div class="onboarding-progressbar"><span style="width:<?= $onboardProgress ?>%"></span></div>
    <div class="onboarding-checks">
      <label><input type="checkbox" disabled <?= $onboarding['orientation_done'] ? 'checked' : '' ?>> Orientation</label>
      <label><input type="checkbox" disabled <?= $onboarding['documents_collected'] ? 'checked' : '' ?>> Documents</label>
      <label><input type="checkbox" disabled <?= $onboarding['assets_issued'] ? 'checked' : '' ?>> Assets</label>
      <label><input type="checkbox" disabled <?= $onboarding['training_assigned'] ? 'checked' : '' ?>> Training</label>
    </div>
  </section>

  <section class="card onboarding-details-card">
    <div class="training-head">
      <div>
        <h2>Employee Details</h2>
        <p class="muted">Fill available details now. Remaining details can be updated later.</p>
      </div>
      <a class="btn-link" href="?route=dashboard">Fill Later</a>
    </div>
    <form method="post" action="?route=employee.onboarding-profile.update" class="grid onboarding-details-form">
      <?= csrf_field() ?>
      <input type="hidden" name="address_line" value="<?= htmlspecialchars((string)($employee['address_line'] ?? '')) ?>">
      <label>Date of Birth<input type="date" name="date_of_birth" value="<?= htmlspecialchars((string)($employee['date_of_birth'] ?? '')) ?>"></label>
      <label>Gender
        <select name="gender">
          <?php foreach (['' => 'Select Gender', 'Female' => 'Female', 'Male' => 'Male', 'Other' => 'Other'] as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= (string)($employee['gender'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Marital Status
        <select name="marital_status">
          <?php foreach (['' => 'Select Marital Status', 'Single' => 'Single', 'Married' => 'Married', 'Divorced' => 'Divorced', 'Widowed' => 'Widowed', 'Separated' => 'Separated'] as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= (string)($employee['marital_status'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Blood Group
        <select name="blood_group">
          <?php foreach (['' => 'Select Blood Group', 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-'] as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= (string)($employee['blood_group'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Aadhaar Number<input name="aadhaar_number" inputmode="numeric" value="<?= htmlspecialchars((string)($employee['aadhaar_number'] ?? '')) ?>"></label>
      <label>ESI Number<input name="esi_number" value="<?= htmlspecialchars((string)($employee['esi_number'] ?? '')) ?>"></label>
      <label>PF Number<input name="pf_number" value="<?= htmlspecialchars((string)($employee['pf_number'] ?? '')) ?>"></label>
      <label>Email<input type="email" name="email" value="<?= htmlspecialchars((string)($employee['email'] ?? '')) ?>"></label>
      <label>Phone<input name="phone" value="<?= htmlspecialchars((string)($employee['phone'] ?? '')) ?>"></label>
      <label>Door No<input name="door_no" value="<?= htmlspecialchars((string)($employee['door_no'] ?? '')) ?>"></label>
      <label>Street<input name="street" value="<?= htmlspecialchars((string)($employee['street'] ?? '')) ?>"></label>
      <label>Locality<input name="locality" value="<?= htmlspecialchars((string)($employee['locality'] ?? '')) ?>"></label>
      <label>City<input name="city" value="<?= htmlspecialchars((string)($employee['city'] ?? '')) ?>"></label>
      <label>State<input name="state" value="<?= htmlspecialchars((string)($employee['state'] ?? '')) ?>"></label>
      <label>Pincode<input name="pincode" value="<?= htmlspecialchars((string)($employee['pincode'] ?? '')) ?>"></label>
      <label>Join Date<input type="date" name="join_date" value="<?= htmlspecialchars((string)($employee['join_date'] ?? '')) ?>"></label>
      <label>Employment Type<input name="employment_type" value="<?= htmlspecialchars((string)($employee['employment_type'] ?? '')) ?>"></label>
      <label>Qualification<input name="qualification" value="<?= htmlspecialchars((string)($employee['qualification'] ?? '')) ?>"></label>
      <label>Specialization<input name="specialization" value="<?= htmlspecialchars((string)($employee['specialization'] ?? '')) ?>"></label>
      <label>Years Experience<input type="number" step="0.1" name="years_experience" value="<?= htmlspecialchars((string)($employee['years_experience'] ?? '')) ?>"></label>
      <label>License Number<input name="license_number" value="<?= htmlspecialchars((string)($employee['license_number'] ?? '')) ?>"></label>
      <label>Emergency Contact Name<input name="emergency_contact_name" value="<?= htmlspecialchars((string)($employee['emergency_contact_name'] ?? '')) ?>"></label>
      <label>Emergency Contact Phone<input name="emergency_contact_phone" value="<?= htmlspecialchars((string)($employee['emergency_contact_phone'] ?? '')) ?>"></label>
      <label>Emergency Relation<input name="emergency_contact_relation" value="<?= htmlspecialchars((string)($employee['emergency_contact_relation'] ?? '')) ?>"></label>
      <label>Bank Name<input name="bank_name" value="<?= htmlspecialchars((string)($employee['bank_name'] ?? '')) ?>"></label>
      <label>Bank Account No<input name="bank_account_no" value="<?= htmlspecialchars((string)($employee['bank_account_no'] ?? '')) ?>"></label>
      <label>IFSC Code<input name="ifsc_code" value="<?= htmlspecialchars((string)($employee['ifsc_code'] ?? '')) ?>"></label>
      <button>Save Details</button>
    </form>
  </section>

  <section class="card onboarding-certificate-card">
    <div class="training-head">
      <div>
        <h2>Certificate Upload</h2>
        <p class="muted">Upload educational, experience, licence, and other onboarding certificates.</p>
      </div>
    </div>
    <form method="post" action="?route=employee.document.store" enctype="multipart/form-data" class="grid onboarding-certificate-form">
      <input type="hidden" name="return_to" value="employee-onboarding">
      <label>Certificate Type
        <select name="document_type" required>
          <option value="">Select Certificate</option>
          <option>Educational Certificate</option>
          <option>Experience Certificate</option>
          <option>Professional Licence</option>
          <option>Training Certificate</option>
          <option>Medical Fitness Certificate</option>
          <option>ID Proof</option>
          <option>Address Proof</option>
          <option>Other Certification</option>
        </select>
      </label>
      <label>Attachment
        <input id="employeeCertificateFile" type="file" name="document_file" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" capture="environment" data-optimize-image="document" required>
        <small class="muted">PDF up to 2 MB. Images are resized and compressed before upload.</small>
        <button type="button" class="btn-compact" onclick="document.getElementById('employeeCertificateFile').click()">Scan / Choose File</button>
      </label>
      <textarea name="remarks" placeholder="Remarks"></textarea>
      <button>Upload Certificate</button>
    </form>
    <div class="table-scroll onboarding-certificate-list">
      <table>
        <tr><th>Certificate</th><th>Uploaded</th><th>Status</th><th>File</th></tr>
        <?php if (empty($documents)): ?>
          <tr><td colspan="4">No certificates uploaded.</td></tr>
        <?php else: ?>
          <?php foreach ($documents as $doc): ?>
            <tr>
              <td><?= htmlspecialchars((string)$doc['document_type']) ?></td>
              <td><?= fmt_date($doc['uploaded_at']) ?></td>
              <td><span class="status-badge status-<?= strtolower((string)$doc['verification_status']) ?>"><?= htmlspecialchars((string)$doc['verification_status']) ?></span></td>
              <td><a href="?route=employee.document.download&id=<?= (int)$doc['id'] ?>">Download</a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>
  </section>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
