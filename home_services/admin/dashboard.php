<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$one = fn($sql) => $pdo->query($sql)->fetchColumn();
$stats = [
    ['Total customers', $one("SELECT COUNT(*) FROM users WHERE role='customer'"), 'bi-people'],
    ['Total workers', $one("SELECT COUNT(*) FROM users WHERE role='worker'"), 'bi-person-gear'],
    ['Total services', $one("SELECT COUNT(*) FROM services"), 'bi-tools'],
    ['Total bookings', $one("SELECT COUNT(*) FROM bookings"), 'bi-journal-text'],
    ['Pending bookings', $one("SELECT COUNT(*) FROM bookings WHERE status='Pending'"), 'bi-hourglass-split'],
    ['Completed bookings', $one("SELECT COUNT(*) FROM bookings WHERE status='Completed'"), 'bi-check-circle'],
    ['Recorded revenue (paid)', money($one("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='Paid'")), 'bi-cash-stack'],
    ['Workers awaiting approval', $one("SELECT COUNT(*) FROM worker_profiles WHERE approval_status='pending'"), 'bi-person-check'],
];
$recent = $pdo->query("SELECT b.id, b.booking_date, b.status, b.price, s.service_name, c.name AS customer_name, w.name AS worker_name
                       FROM bookings b JOIN services s ON s.id=b.service_id JOIN users c ON c.id=b.customer_id LEFT JOIN users w ON w.id=b.worker_id
                       ORDER BY b.created_at DESC, b.id DESC LIMIT 8")->fetchAll();

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container"><h1 class="h3 mb-0">Admin dashboard</h1><span class="text-muted">Overview of the platform</span></div></div>
<div class="container">
  <div class="row g-3 mb-4">
    <?php foreach ($stats as $s): ?>
      <div class="col-6 col-lg-3"><div class="stat-card"><span class="icon-circle sm"><i class="bi <?= $s[2] ?>"></i></span>
        <div><div class="stat-value"><?= is_numeric($s[1]) ? (int)$s[1] : e($s[1]) ?></div><div class="stat-label"><?= e($s[0]) ?></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <p class="small text-muted">Revenue is the sum of payments the administrator has recorded as paid. No online payment gateway is connected.</p>
  <div class="row g-2 mb-4">
    <?php foreach ([['Workers','workers.php','bi-person-gear'],['Customers','customers.php','bi-people'],['Services','services.php','bi-tools'],['Bookings','bookings.php','bi-journal-text'],['Reports','reports.php','bi-bar-chart']] as $m): ?>
      <div class="col-6 col-md"><a class="btn btn-outline-primary w-100" href="<?= url('admin/' . $m[1]) ?>"><i class="bi <?= $m[2] ?>"></i> <?= $m[0] ?></a></div>
    <?php endforeach; ?>
  </div>
  <div class="panel p-0">
    <div class="d-flex justify-content-between p-3 border-bottom"><h5 class="mb-0">Recent bookings</h5><a href="<?= url('admin/bookings.php') ?>" class="small">Manage all</a></div>
    <div class="table-responsive"><table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>ID</th><th>Customer</th><th>Service</th><th>Worker</th><th>Date</th><th>Price</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $b): ?>
        <tr><td><a href="<?= url('admin/bookings.php?view=' . (int)$b['id']) ?>"><?= e(booking_code($b['id'])) ?></a></td><td><?= e($b['customer_name']) ?></td><td><?= e($b['service_name']) ?></td>
          <td><?= $b['worker_name'] ? e($b['worker_name']) : '<span class="text-muted">Unassigned</span>' ?></td><td><?= e(date('d M Y', strtotime($b['booking_date']))) ?></td><td><?= money($b['price']) ?></td><td><?= status_badge($b['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="7" class="text-center text-muted py-4">No bookings yet.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
