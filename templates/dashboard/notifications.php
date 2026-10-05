<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$notificationGroups = [
  'Leave' => [],
  'Permission' => [],
  'Correction' => [],
  'Others' => [],
];
$categoryForNotification = static function (array $item): string {
  $text = strtolower(trim(
    (string)($item['event_type'] ?? '') . ' ' .
    (string)($item['title'] ?? '') . ' ' .
    (string)($item['message'] ?? '')
  ));
  if (str_contains($text, 'permission')) {
    return 'Permission';
  }
  if (str_contains($text, 'correction') || str_contains($text, 'time attendance')) {
    return 'Correction';
  }
  if (str_contains($text, 'leave')) {
    return 'Leave';
  }
  return 'Others';
};
foreach (($items ?? []) as $item) {
  $cat = $categoryForNotification($item);
  $notificationGroups[$cat][] = $item;
}
$totalCount = count($items ?? []);
?>

<div class="content-body">
  <!-- ── Page Header (Screen 7) ── -->
  <div class="dashboard-header-greeting">
    <h1>Notifications</h1>
    <p>Stay updated with important announcements.</p>
  </div>

  <!-- ── Category Filter Pills (Screen 7) ── -->
  <div class="filter-pills" id="notifFilterPills">
    <button type="button" class="filter-pill active" data-filter="all">All (<?= $totalCount ?>)</button>
    <button type="button" class="filter-pill" data-filter="Leave">Leave (<?= count($notificationGroups['Leave']) ?>)</button>
    <button type="button" class="filter-pill" data-filter="Permission">Permission (<?= count($notificationGroups['Permission']) ?>)</button>
    <button type="button" class="filter-pill" data-filter="Correction">Correction (<?= count($notificationGroups['Correction']) ?>)</button>
    <button type="button" class="filter-pill" data-filter="Others">Others (<?= count($notificationGroups['Others']) ?>)</button>
  </div>

  <!-- ── Search notifications box ── -->
  <div style="position:relative;max-width:420px;margin-bottom:10px">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="#94a3b8" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);pointer-events:none"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
    <input type="text" id="notifSearchInput" placeholder="Search notifications..." style="padding-left:38px !important;border-radius:100px !important;background:#ffffff !important">
  </div>

  <!-- ── Notifications List or Empty State (Screen 7) ── -->
  <div id="notifListContainer">
    <?php if (empty($items)): ?>
      <!-- Elegant Empty State Illustration matching Screen 7 -->
      <div class="card" style="text-align:center;padding:60px 20px;margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="width:140px;height:120px;margin-bottom:20px;position:relative;display:flex;align-items:center;justify-content:center">
          <svg viewBox="0 0 160 140" width="140" height="120" fill="none">
            <!-- Decorative soft background leaves / waves -->
            <path d="M20 120 C 15 80, 45 60, 40 30 C 60 50, 65 90, 50 125 Z" fill="#e6f4f1"/>
            <path d="M140 120 C 145 80, 115 60, 120 30 C 100 50, 95 90, 110 125 Z" fill="#e6f4f1"/>
            <!-- Center document paper sheet -->
            <rect x="45" y="20" width="70" height="95" rx="8" fill="#ffffff" stroke="#cbd5e1" stroke-width="2"/>
            <path d="M95 20 L 115 40 L 95 40 Z" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
            <!-- Text lines on document -->
            <line x1="58" y1="42" x2="84" y2="42" stroke="#e2e8f0" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58" y1="54" x2="102" y2="54" stroke="#e2e8f0" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58" y1="66" x2="95" y2="66" stroke="#e2e8f0" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58" y1="78" x2="88" y2="78" stroke="#e2e8f0" stroke-width="2.5" stroke-linecap="round"/>
            <!-- Centered Bell Icon badge -->
            <circle cx="80" cy="95" r="22" fill="#ffffff" filter="drop-shadow(0 4px 6px rgba(0,0,0,0.06))"/>
            <circle cx="80" cy="95" r="20" fill="#f0fdf4"/>
            <path d="M80 86 C 76.5 86, 74 88.5, 74 92 L 74 96 L 72 98 L 72 99 L 88 99 L 88 98 L 86 96 L 86 92 C 86 88.5, 83.5 86, 80 86 Z M 80 102 C 81.1 102, 82 101.1, 82 100 L 78 100 C 78 101.1, 78.9 102, 80 102 Z" fill="#0f766e"/>
          </svg>
        </div>
        <h3 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 6px">No notifications yet</h3>
        <p style="font-size:13.5px;color:#64748b;margin:0;max-width:360px;line-height:1.5">You'll see important updates, approvals and announcements here.</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($items as $it): ?>
          <?php
            $cat = $categoryForNotification($it);
            $msgFull = (string)$it['message'];
            $isRead = !empty($it['is_read']);
            $badgeColor = match($cat) {
              'Leave' => 'background:#e0f2fe;color:#0369a1',
              'Permission' => 'background:#fef3c7;color:#b45309',
              'Correction' => 'background:#ffe4e6;color:#be123c',
              default => 'background:#f1f5f9;color:#475569',
            };
          ?>
          <div class="card notif-item-card" data-category="<?= htmlspecialchars($cat) ?>" style="margin:0;padding:16px 20px;border-left:4px solid <?= $isRead ? '#cbd5e1' : '#0f766e' ?>;display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:100px;<?= $badgeColor ?>"><?= htmlspecialchars($cat) ?></span>
                <strong style="font-size:14px;color:#0f172a"><?= htmlspecialchars($it['title']) ?></strong>
                <?php if (!$isRead): ?>
                  <span style="width:7px;height:7px;border-radius:50%;background:#0f766e" title="Unread"></span>
                <?php endif; ?>
              </div>
              <p style="font-size:13px;color:#475569;margin:0 0 8px;line-height:1.4"><?= htmlspecialchars($msgFull) ?></p>
              <div style="font-size:11px;color:#94a3b8">
                <?= htmlspecialchars(fmt_datetime($it['created_at'])) ?>
                <?php if (!empty($it['channel'])): ?> • Via <?= htmlspecialchars($it['channel']) ?><?php endif; ?>
              </div>
            </div>
            <?php if (!empty($canManageNotifications)): ?>
              <form method="post" action="?route=notification.delete" onsubmit="return confirm('Delete notification?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button type="submit" style="background:transparent;border:none;color:#94a3b8;cursor:pointer;padding:4px" title="Delete">✕</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($internalRequests)): ?>
    <div class="card" style="margin-top:24px">
      <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 14px">Internal Communication Replies</h3>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Employee</th><th>Type</th><th>Message</th><th>Status</th><th>Reply</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($internalRequests as $request): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars(trim((string)$request['first_name'] . ' ' . (string)$request['last_name'])) ?></strong><br>
                  <small style="color:#64748b"><?= htmlspecialchars((string)$request['employee_code']) ?> to <?= htmlspecialchars((string)$request['to_role']) ?></small>
                </td>
                <td><?= htmlspecialchars((string)$request['request_type']) ?></td>
                <td><?= htmlspecialchars((string)$request['message']) ?></td>
                <td><span class="badge-status <?= $request['status'] === 'Completed' ? 'active' : 'pending' ?>"><?= htmlspecialchars((string)$request['status']) ?></span></td>
                <td>
                  <?php if (!empty($request['reply_message'])): ?>
                    <?= htmlspecialchars((string)$request['reply_message']) ?><br>
                    <small style="color:#94a3b8"><?= htmlspecialchars((string)($request['replied_by_name'] ?? '')) ?> <?= htmlspecialchars(fmt_datetime($request['replied_at'] ?? null)) ?></small>
                  <?php else: ?>
                    <span style="color:#94a3b8">No reply yet</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" action="?route=internal-request.reply" style="display:flex;gap:6px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                    <input type="text" name="reply_message" placeholder="Quick reply" required style="width:140px !important;padding:6px 10px !important;font-size:12px !important">
                    <button class="btn-primary" style="padding:6px 12px !important;font-size:12px !important">Send</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  (function () {
    var pills = document.querySelectorAll('#notifFilterPills .filter-pill');
    var cards = document.querySelectorAll('.notif-item-card');
    var search = document.getElementById('notifSearchInput');

    function filterCards() {
      var activePill = document.querySelector('#notifFilterPills .filter-pill.active');
      var filterCat = activePill ? activePill.getAttribute('data-filter') : 'all';
      var query = search ? search.value.toLowerCase().trim() : '';

      cards.forEach(function (card) {
        var cardCat = card.getAttribute('data-category');
        var cardText = card.textContent.toLowerCase();
        var matchCat = (filterCat === 'all' || cardCat === filterCat);
        var matchQuery = (!query || cardText.indexOf(query) !== -1);
        card.style.display = (matchCat && matchQuery) ? 'flex' : 'none';
      });
    }

    pills.forEach(function (pill) {
      pill.addEventListener('click', function () {
        pills.forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        filterCards();
      });
    });

    if (search) {
      search.addEventListener('input', filterCards);
    }
  })();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
