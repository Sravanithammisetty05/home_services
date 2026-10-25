<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');

$uid = $_SESSION['user_id'];
$u = $pdo->prepare('SELECT name, email, phone, address, created_at FROM users WHERE id = ?');
$u->execute([$uid]);
$user = $u->fetch();

$c = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status='Pending'),0) AS pending, COALESCE(SUM(status='Completed'),0) AS completed FROM bookings WHERE customer_id = ?");
$c->execute([$uid]);
$counts = $c->fetch();

$r = $pdo->prepare("SELECT b.*, s.service_name FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.customer_id = ? ORDER BY b.created_at DESC, b.id DESC LIMIT 5");
$r->execute([$uid]);
$recent = $r->fetchAll();

$page_title = 'Customer Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><h1 class="h3 mb-0">Welcome, <?= e($user['name']) ?></h1><span class="text-muted">Here is what is happening with your bookings.</span></div>
    <a class="btn btn-accent" href="<?= url('services.php') ?>"><i class="bi bi-plus-lg"></i> Book a Service</a>
  </div>
</div>
<div class="container">
  <div class="row g-3 mb-4">
    <?php $cards = [['Total bookings', $counts['total'], 'bi-journal-text', 'primary'], ['Pending bookings', $counts['pending'], 'bi-hourglass-split', 'warning'], ['Completed bookings', $counts['completed'], 'bi-check-circle', 'success']];
    foreach ($cards as $cd): ?>
      <div class="col-md-4"><div class="stat-card"><span class="icon-circle sm"><i class="bi <?= $cd[2] ?>"></i></span>
        <div><div class="stat-value"><?= (int)$cd[1] ?></div><div class="stat-label"><?= e($cd[0]) ?></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="panel p-0">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom"><h5 class="mb-0">Recent bookings</h5><a href="<?= url('my_bookings.php') ?>" class="small">View all</a></div>
        <div class="table-responsive"><table class="table mb-0">
          <thead class="table-light"><tr><th>ID</th><th>Service</th><th>Date</th><th>Price</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $b): ?>
            <tr><td><?= e(booking_code($b['id'])) ?></td><td><?= e($b['service_name']) ?></td><td><?= e(date('d M Y', strtotime($b['booking_date']))) ?></td><td><?= money($b['price']) ?></td><td><?= status_badge($b['status']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-4">No bookings yet.</td></tr><?php endif; ?>
          </tbody></table></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="panel">
        <h5 class="mb-3">Profile</h5>
        <p class="mb-1"><i class="bi bi-person text-primary me-2"></i><?= e($user['name']) ?></p>
        <p class="mb-1"><i class="bi bi-envelope text-primary me-2"></i><?= e($user['email']) ?></p>
        <p class="mb-1"><i class="bi bi-telephone text-primary me-2"></i><?= e($user['phone']) ?></p>
        <p class="mb-1"><i class="bi bi-geo-alt text-primary me-2"></i><?= e($user['address']) ?></p>
        <p class="mb-0 small text-muted">Member since <?= e(date('M Y', strtotime($user['created_at']))) ?></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
