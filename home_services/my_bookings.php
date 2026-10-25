<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'cancel') {
        $st = $pdo->prepare("UPDATE bookings SET status='Cancelled' WHERE id = ? AND customer_id = ? AND status IN ('Pending','Accepted')");
        $st->execute([(int)($_POST['booking_id'] ?? 0), $_SESSION['user_id']]);
        if ($st->rowCount()) set_flash('success', 'Booking cancelled.');
        else set_flash('danger', 'This booking can no longer be cancelled.');
    }
    redirect('my_bookings.php');
}

$st = $pdo->prepare(
    "SELECT b.*, s.service_name, w.name AS worker_name, w.phone AS worker_phone
     FROM bookings b JOIN services s ON s.id = b.service_id LEFT JOIN users w ON w.id = b.worker_id
     WHERE b.customer_id = ? ORDER BY b.created_at DESC, b.id DESC"
);
$st->execute([$_SESSION['user_id']]);
$bookings = $st->fetchAll();

$page_title = 'My Bookings';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">My bookings</h1>
    <a class="btn btn-accent" href="<?= url('services.php') ?>"><i class="bi bi-plus-lg"></i> Book a Service</a>
  </div>
  <div class="panel p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Booking ID</th><th>Service</th><th>Date &amp; time</th><th>Provider</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="fw-semibold"><?= e(booking_code($b['id'])) ?></td>
            <td><?= e($b['service_name']) ?><div class="small text-muted text-truncate" style="max-width:220px"><?= e($b['problem_description']) ?></div></td>
            <td><?= e(date('d M Y', strtotime($b['booking_date']))) ?><br><small class="text-muted"><?= e(date('g:i A', strtotime($b['booking_time']))) ?></small></td>
            <td><?= $b['worker_name'] ? e($b['worker_name']) . '<br><small class="text-muted">' . e($b['worker_phone']) . '</small>' : '<span class="text-muted">Not assigned yet</span>' ?></td>
            <td><?= money($b['price']) ?></td>
            <td><?= status_badge($b['status']) ?></td>
            <td class="text-end">
              <?php if (in_array($b['status'], ['Pending', 'Accepted'], true)): ?>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" data-confirm="Cancel booking <?= e(booking_code($b['id'])) ?>?">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bookings): ?><tr><td colspan="7" class="text-center text-muted py-4">You have no bookings yet. <a href="<?= url('services.php') ?>">Book your first service</a>.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
