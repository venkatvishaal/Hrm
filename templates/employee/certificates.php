<?php require __DIR__ . '/../layouts/header.php'; ?>
<section class="employee-fit-page employee-certificates-fit-page">

<section class="employee-fit-column">
<section class="certificate-options">
  <form method="post" action="?route=employee.internal-request.store" class="certificate-option">
    <?= csrf_field() ?>
    <input type="hidden" name="request_type" value="Employee Certificate">
    <input type="hidden" name="to_role" value="HR">
    <input type="hidden" name="message" value="Please issue my employee certificate.">
    <span>TW</span>
    <div>
      <strong>Request Employee Certificate</strong>
      <small>Send request to HR</small>
    </div>
    <button class="btn-compact">Request</button>
  </form>
  <form method="post" action="?route=employee.internal-request.store" class="certificate-option">
    <?= csrf_field() ?>
    <input type="hidden" name="request_type" value="Salary Certificate">
    <input type="hidden" name="to_role" value="HR">
    <input type="hidden" name="message" value="Please issue my salary certificate.">
    <span>SC</span>
    <div>
      <strong>Request Salary Certificate</strong>
      <small>Send request to HR</small>
    </div>
    <button class="btn-compact">Request</button>
  </form>
</section>
<section class="certificate-options no-print">
  <a class="certificate-option" href="?route=employee-certificate&type=to_whom">
    <span>EC</span>
    <div>
      <strong>Preview Employee Certificate</strong>
    </div>
  </a>
  <a class="certificate-option" href="?route=employee-certificate&type=salary">
    <span>SC</span>
    <div>
      <strong>Preview Salary Certificate</strong>
    </div>
  </a>
</section>
</section>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
