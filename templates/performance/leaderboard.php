<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../reports/reports_nav.php'; ?>
<h1>Performance Leaderboard</h1>
<div class="global-nav">
  <a class="btn-link btn-nav" href="?route=performance">Performance Reviews</a>
  <a class="btn-link btn-nav" href="?route=export&type=performance">Export CSV</a>
</div>

<div class="card">
  <h2>Top KPI Performers</h2>
  <p class="leaderboard-meta">Ranked by average KPI score, then best score and latest review date.</p>
</div>

<?php if (!$items): ?>
  <div class="card leaderboard-empty">No performance reviews available yet.</div>
<?php else: ?>
  <div class="leaderboard-list">
    <?php foreach ($items as $index => $it): ?>
      <?php $scorePercent = max(0, min(100, ((float)$it['average_score'] / 10) * 100)); ?>
      <div class="card leaderboard-row">
        <div class="leaderboard-rank">#<?= $index + 1 ?></div>
        <div>
          <strong><?= htmlspecialchars($it['first_name'] . ' ' . $it['last_name']) ?></strong>
          <div class="leaderboard-meta">
            <?= htmlspecialchars((string)($it['department'] ?: 'No department')) ?>
            <?php if (!empty($it['position'])): ?> | <?= htmlspecialchars($it['position']) ?><?php endif; ?>
          </div>
        </div>
        <div>
          <div class="leaderboard-score"><?= htmlspecialchars((string)$it['average_score']) ?>/10</div>
          <div class="kpi-track"><div class="kpi-fill" style="width: <?= $scorePercent ?>%"></div></div>
        </div>
        <div class="leaderboard-meta">
          <strong><?= (int)$it['review_count'] ?></strong> review(s)<br>
          Best: <?= htmlspecialchars((string)$it['best_score']) ?>/10<br>
          Latest: <?= fmt_date($it['latest_review_date']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
