<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $hierarchyAssetBase = $assetBase ?? 'public/assets'; ?>
<section class="card hierarchy-page">
  <div class="hierarchy-head">
    <div>
      <h2>Employee Reporting Workflow</h2>
    </div>
  </div>
  <div class="hierarchy-chart-wrap">
    <img class="hierarchy-chart" src="<?= htmlspecialchars($hierarchyAssetBase) ?>/organisational-hierarchy.png" alt="Organisational hierarchy for employee reporting workflow">
  </div>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
