<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $bk = $pdo->prepare('SELECT b.*, s.service_name FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.id = ?');
    $bk->execute([$id]);
    $b = $bk->fetch();
    if (!$b) { set_flash('danger', 'Booking not found.'); redirect('admin/bookings.php'); }

    if ($action === 'assign') {
        $wid = (int)($_POST['worker_id'] ?? 0);
        $chk = $pdo->prepare("SELECT u.id FROM users u JOIN worker_profiles wp ON wp.user_id = u.id
                              WHERE u.id = ? AND u.status='active' AND wp.approval_status='approved' AND wp.specialization = ?");
        $chk->execute([$wid, $b['service_name']]);
        if (!in_array($b['status'], ['Pending', 'Rejected', 'Accepted'], true)) {
            set_flash('danger', 'A ' . strtolower($b['status']) . ' booking cannot be reassigned.');
        } elseif (!$chk->fetch()) {
            set_flash('danger', 'Choose an approved worker who offers this service.');
        } else {
            $new = ($b['status'] === 'Accepted') ? 'Accepted' : 'Pending';   // reassigned jobs go back to the new worker for acceptance
            if ($b['status'] === 'Accepted' && (int)$b['worker_id'] !== $wid) $new = 'Pending';
            $pdo->prepare('UPDATE bookings SET worker_id = ?, status = ? WHERE id = ?')->execute([$wid, $new, $id]);
            set_flash('success', 'Worker assigned. The job request is now on their dashboard.');
        }
    } elseif ($action === 'status') {
        $new = $_POST['status'] ?? '';
        if (!in_array($new, BOOKING_STATUSES, true)) {
            set_flash('danger', 'Invalid status.');
        } elseif (in_array($new, ['Accepted', 'In Progress', 'Completed'], true) && !$b['worker_id']) {
            set_flash('danger', 'Assign a worker before setting this status.');
        } else {
            $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$new, $id]);
            set_flash('success', 'Booking status updated to ' . $new . '.');
        }
    } elseif ($action === 'payment') {
        $method = $_POST['payment_method'] ?? '';
        $ref = clean($_POST['transaction_reference'] ?? '');
        if ($b['status'] !== 'Completed') {
            set_flash('danger', 'Payments can only be recorded for completed bookings.');
        } elseif (!in_array($method, ['Cash', 'UPI', 'Card', 'Bank Transfer'], true) || strlen($ref) > 100) {
            set_flash('danger', 'Choose a valid payment method (reference max 100 characters).');
        } else {
            $ex = $pdo->prepare('SELECT id FROM payments WHERE booking_id = ?');
            $ex->execute([$id]);
            if ($pid = $ex->fetchColumn()) {
                $pdo->prepare("UPDATE payments SET amount=?, payment_method=?, payment_status='Paid', transaction_reference=? WHERE id=?")
                    ->execute([$b['price'], $method, $ref ?: null, $pid]);
            } else {
                $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status, transaction_reference) VALUES (?,?,?,'Paid',?)")
                    ->execute([$id, $b['price'], $method, $ref ?: null]);
            }
            set_flash('success', 'Payment of ' . money($b['price']) . ' recorded.');
        }
    }
    redirect('admin/bookings.php?view=' . $id);
}

$filter = in_array($_GET['status'] ?? '', BOOKING_STATUSES, true) ? $_GET['status'] : '';
$sql = "SELECT b.id, b.booking_date, b.booking_time, b.status, b.price, s.service_name, c.name AS customer_name, w.name AS worker_name, p.payment_status
        FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.customer_id
        LEFT JOIN users w ON w.id = b.worker_id LEFT JOIN payments p ON p.booking_id = b.id";
$params = [];
if ($filter !== '') { $sql .= ' WHERE b.status = ?'; $params[] = $filter; }
$st = $pdo->prepare($sql . ' ORDER BY b.created_at DESC, b.id DESC LIMIT 200');
$st->execute($params);
$bookings = $st->fetchAll();

// details panel
$detail = null; $eligible = []; $payment = null;
if (isset($_GET['view'])) {
    $d = $pdo->prepare("SELECT b.*, s.service_name, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                               w.name AS worker_name, w.phone AS worker_phone
                        FROM bookings b JOIN services s ON s.id = b.service_id JOIN users c ON c.id = b.customer_id LEFT JOIN users w ON w.id = b.worker_id WHERE b.id = ?");
    $d->execute([(int)$_GET['view']]);
    $detail = $d->fetch();
    if ($detail) {
        $e = $pdo->prepare("SELECT u.id, u.name, wp.availability_status FROM users u JOIN worker_profiles wp ON wp.user_id = u.id
                            WHERE u.status='active' AND wp.approval_status='approved' AND wp.specialization = ? ORDER BY u.name");
        $e->execute([$detail['service_name']]);
        $eligible = $e->fetchAll();
        $pp = $pdo->prepare('SELECT * FROM payments WHERE booking_id = ?');
        $pp->execute([$detail['id']]);
        $payment = $pp->fetch();
    }
}

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container"><h1 class="h3 mb-0">Manage bookings</h1><span class="text-muted">Assign workers, update statuses and record payments</span></div></div>
<div class="container">
  <?php if ($detail): ?>
  <div class="panel mb-4">
    <div class="d-flex justify-content-between align-items-start">
      <h5 class="mb-3">Booking <?= e(booking_code($detail['id'])) ?> <?= status_badge($detail['status']) ?></h5>
      <a class="btn-close" href="<?= url('admin/bookings.php' . ($filter ? '?status=' . urlencode($filter) : '')) ?>" aria-label="Close"></a>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <p class="mb-1"><strong>Service:</strong> <?= e($detail['service_name']) ?> (<?= money($detail['price']) ?>)</p>
        <p class="mb-1"><strong>When:</strong> <?= e(date('d M Y', strtotime($detail['booking_date']))) ?>, <?= e(date('g:i A', strtotime($detail['booking_time']))) ?></p>
        <p class="mb-1"><strong>Address:</strong> <?= e($detail['address']) ?></p>
        <p class="mb-1"><strong>Problem:</strong> <?= e($detail['problem_description']) ?></p>
        <p class="mb-1"><strong>Booked on:</strong> <?= e(date('d M Y, g:i A', strtotime($detail['created_at']))) ?></p>
      </div>
      <div class="col-md-6">
        <p class="mb-1"><strong>Customer:</strong> <?= e($detail['customer_name']) ?></p>
        <p class="mb-1"><i class="bi bi-envelope"></i> <?= e($detail['customer_email']) ?> &middot; <i class="bi bi-telephone"></i> <?= e($detail['customer_phone']) ?></p>
        <p class="mb-1"><strong>Worker:</strong> <?= $detail['worker_name'] ? e($detail['worker_name']) . ' (' . e($detail['worker_phone']) . ')' : '<span class="text-muted">Not assigned</span>' ?></p>
        <p class="mb-1"><strong>Payment:</strong> <?= $payment ? e($payment['payment_status']) . ' &middot; ' . money($payment['amount']) . ' via ' . e($payment['payment_method']) . ($payment['transaction_reference'] ? ' (ref ' . e($payment['transaction_reference']) . ')' : '') : '<span class="text-muted">None recorded</span>' ?></p>
      </div>
    </div>
    <hr>
    <div class="row g-3">
      <div class="col-md-4">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="booking_id" value="<?= (int)$detail['id'] ?>">
          <label class="form-label" for="worker_id">Assign worker</label>
          <div class="input-group"><select class="form-select" name="worker_id" id="worker_id" required>
            <option value="">Choose a worker</option>
            <?php foreach ($eligible as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$detail['worker_id'] === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?> (<?= e($w['availability_status']) ?>)</option><?php endforeach; ?>
          </select><button class="btn btn-primary">Assign</button></div>
          <?php if (!$eligible): ?><div class="form-text text-danger">No approved workers offer this service yet.</div><?php endif; ?>
        </form>
      </div>
      <div class="col-md-4">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="booking_id" value="<?= (int)$detail['id'] ?>">
          <label class="form-label" for="status">Update status</label>
          <div class="input-group"><select class="form-select" name="status" id="status">
            <?php foreach (BOOKING_STATUSES as $s): ?><option <?= $detail['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
          </select><button class="btn btn-primary">Update</button></div>
        </form>
      </div>
      <div class="col-md-4">
        <?php if ($detail['status'] === 'Completed'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="booking_id" value="<?= (int)$detail['id'] ?>">
          <label class="form-label" for="payment_method">Record payment (<?= money($detail['price']) ?>)</label>
          <div class="input-group mb-1"><select class="form-select" name="payment_method" id="payment_method"><option>Cash</option><option>UPI</option><option>Card</option><option>Bank Transfer</option></select>
            <button class="btn btn-success">Mark paid</button></div>
          <input class="form-control form-control-sm" name="transaction_reference" maxlength="100" placeholder="Transaction reference (optional)" value="<?= e($payment['transaction_reference'] ?? '') ?>">
        </form>
        <?php else: ?><label class="form-label">Record payment</label><div class="form-text">Available once the booking is Completed.</div><?php endif; ?>
      </div>
    </div>
  </div>
  <?php elseif (isset($_GET['view'])): ?>
    <div class="alert alert-warning">That booking does not exist.</div>
  <?php endif; ?>

  <div class="mb-3 d-flex flex-wrap gap-2">
    <a class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/bookings.php') ?>">All</a>
    <?php foreach (BOOKING_STATUSES as $s): ?><a class="btn btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/bookings.php?status=' . urlencode($s)) ?>"><?= e($s) ?></a><?php endforeach; ?>
  </div>
  <div class="panel p-0"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>ID</th><th>Customer</th><th>Service</th><th>Worker</th><th>Date</th><th>Price</th><th>Payment</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td><?= e(booking_code($b['id'])) ?></td><td><?= e($b['customer_name']) ?></td><td><?= e($b['service_name']) ?></td>
        <td><?= $b['worker_name'] ? e($b['worker_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
        <td><?= e(date('d M Y', strtotime($b['booking_date']))) ?><br><small><?= e(date('g:i A', strtotime($b['booking_time']))) ?></small></td>
        <td><?= money($b['price']) ?></td><td><?= $b['payment_status'] ? e($b['payment_status']) : '<span class="text-muted">-</span>' ?></td>
        <td><?= status_badge($b['status']) ?></td>
        <td><a class="btn btn-sm btn-outline-primary" href="<?= url('admin/bookings.php?view=' . (int)$b['id'] . ($filter ? '&status=' . urlencode($filter) : '')) ?>">View / manage</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?><tr><td colspan="9" class="text-center text-muted py-4">No bookings found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
