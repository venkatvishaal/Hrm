<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$itemsByDate = [];
foreach (($items ?? []) as $item) {
  $itemsByDate[(string)$item['duty_date']] = $item;
}
?>
<section class="employee-standalone-page employee-duty-roster-page">
  <section class="card employee-duty-roster-card">
    <div class="training-head">
      <div>
        <h2><?= $period === 'previous' ? 'Previous Duty Roster' : 'Duty Roster' ?></h2>
        <p class="muted">Friday to Thursday: <?= htmlspecialchars(fmt_date($start) . ' to ' . fmt_date($end)) ?></p>
      </div>
      <div class="employee-duty-roster-actions">
        <a class="btn-link" href="?route=employee-duty-roster&period=current">Duty Roster</a>
        <a class="btn-link" href="?route=employee-duty-roster&period=previous">Previous Duty Roster</a>
      </div>
    </div>
    <div class="employee-roster-week-grid employee-duty-roster-week-grid">
      <?php for ($offset = 0; $offset < 7; $offset++): ?>
        <?php $date = date('Y-m-d', strtotime($start . ' +' . $offset . ' days')); $item = $itemsByDate[$date] ?? null; ?>
        <div class="employee-roster-week-day<?= $item ? ' has-duty' : '' ?>">
          <strong><?= htmlspecialchars(date('l', strtotime($date))) ?></strong>
          <small><?= htmlspecialchars(date('d M Y', strtotime($date))) ?></small>
          <?php if ($item): ?>
            <b><?= htmlspecialchars((string)$item['shift_name']) ?></b>
            <span><?= htmlspecialchars(substr((string)$item['start_time'], 0, 5) . ' - ' . substr((string)$item['end_time'], 0, 5)) ?></span>
            <span><?= htmlspecialchars((string)($item['ward'] ?? '')) ?></span>
          <?php else: ?>
            <span class="muted">No duty scheduled</span>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <?php if (empty($items)): ?><p class="muted employee-duty-roster-empty">No duty roster scheduled for this week.</p><?php endif; ?>
  </section>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
