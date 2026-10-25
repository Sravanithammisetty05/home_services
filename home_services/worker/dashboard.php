<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('worker');

$me = (int)$_SESSION['user_id'];
$p = $pdo->prepare('SELECT u.name, u.email, u.phone, wp.specialization, wp.experience, wp.approval_status, wp.availability_status
                    FROM users u JOIN worker_profiles wp ON wp.user_id = u.id WHERE u.id = ?');
$p->execute([$me]);
$profile = $p->fetch();
$approved = ($profile['approval_status'] === 'approved');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['booking_id'] ?? 0);
    if (!$approved) {
        set_flash('warning', 'Your profile is waiting for administrator approval.');
    } elseif ($action === 'availability') {
        $val = (($_POST['availability'] ?? '') === 'busy') ? 'busy' : 'available';
        $pdo->prepare('UPDATE worker_profiles SET availability_status = ? WHERE user_id = ?')->execute([$val, $me]);
        set_flash('success', 'Availability updated.');
    } else {
        // Every update is restricted to jobs that belong to this worker (or open jobs for their specialization)
        if ($action === 'accept') {
            $st = $pdo->prepare("UPDATE bookings SET worker_id = ?, status = 'Accepted'
                                 WHERE id = ? AND status = 'Pending'
                                   AND (worker_id = ? OR (worker_id IS NULL AND service_id IN (SELECT id FROM services WHERE service_name = ?)))");
            $st->execute([$me, $id, $me, $profile['specialization']]);
            $msg = 'Job accepted. Customer details are now visible.';
        } elseif ($action === 'reject') {
            $st = $pdo->prepare("UPDATE bookings SET status = 'Rejected' WHERE id = ? AND worker_id = ? AND status = 'Pending'");
            $st->execute([$id, $me]);
            $msg = 'Job request rejected.';
        } elseif ($action === 'start') {
            $st = $pdo->prepare("UPDATE bookings SET status = 'In Progress' WHERE id = ? AND worker_id = ? AND status = 'Accepted'");
            $st->execute([$id, $me]);
            $msg = 'Job marked as In Progress.';
        } elseif ($action === 'complete') {
            $st = $pdo->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ? AND worker_id = ? AND status = 'In Progress'");
            $st->execute([$id, $me]);
            $msg = 'Job marked as Completed. The administrator will record the payment.';
        } else {
            $st = null; $msg = '';
        }
        if ($st && $st->rowCount()) set_flash('success', $msg);
        else set_flash('danger', 'That job could not be updated. It may have been changed or taken by someone else.');
    }
    redirect('worker/dashboard.php');
}

$jobSql = "SELECT b.*, s.service_name, u.name AS customer_name, u.phone AS customer_phone
           FROM bookings b JOIN services s ON s.id = b.service_id JOIN users u ON u.id = b.customer_id ";

$q = $pdo->prepare($jobSql . "WHERE b.status = 'Pending' AND (b.worker_id = ? OR (b.worker_id IS NULL AND s.service_name = ?)) ORDER BY b.booking_date, b.booking_time");
$q->execute([$me, $profile['specialization']]);
$newJobs = $approved ? $q->fetchAll() : [];

$q = $pdo->prepare($jobSql . "WHERE b.worker_id = ? AND b.status IN ('Accepted','In Progress') ORDER BY b.booking_date, b.booking_time");
$q->execute([$me]);
$activeJobs = $q->fetchAll();

$q = $pdo->prepare($jobSql . "WHERE b.worker_id = ? AND b.status = 'Completed' ORDER BY b.booking_date DESC LIMIT 20");
$q->execute([$me]);
$doneJobs = $q->fetchAll();

$q = $pdo->prepare("SELECT COALESCE(SUM(status='Accepted'),0) a, COALESCE(SUM(status='In Progress'),0) ip, COALESCE(SUM(status='Completed'),0) c FROM bookings WHERE worker_id = ?");
$q->execute([$me]);
$cnt = $q->fetch();

$q = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE b.worker_id = ? AND p.payment_status = 'Paid'");
$q->execute([$me]);
$earned = (float)$q->fetchColumn();

$q = $pdo->prepare("SELECT COALESCE(SUM(b.price),0) FROM bookings b LEFT JOIN payments p ON p.booking_id = b.id AND p.payment_status = 'Paid'
                    WHERE b.worker_id = ? AND b.status = 'Completed' AND p.id IS NULL");
$q->execute([$me]);
$awaiting = (float)$q->fetchColumn();

function job_form($action, $id, $label, $class, $confirm = '') {
    return '<form method="post" class="d-inline">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<input type="hidden" name="booking_id" value="' . (int)$id . '"><button class="btn btn-sm ' . $class . '"'
         . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . e($label) . '</button></form> ';
}

$page_title = 'Worker Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><h1 class="h3 mb-0">Welcome, <?= e($profile['name']) ?></h1>
      <span class="text-muted"><?= e($profile['specialization']) ?> &middot; <?= (int)$profile['experience'] ?> yrs experience</span></div>
    <?php if ($approved): ?>
    <form method="post" class="d-flex align-items-center gap-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="availability">
      <label class="form-label mb-0 small" for="availability">Availability</label>
      <select class="form-select form-select-sm w-auto" name="availability" id="availability" onchange="this.form.submit()">
        <option value="available" <?= $profile['availability_status'] === 'available' ? 'selected' : '' ?>>Available</option>
        <option value="busy" <?= $profile['availability_status'] === 'busy' ? 'selected' : '' ?>>Busy</option>
      </select>
    </form>
    <?php endif; ?>
  </div>
</div>
<div class="container">
  <?php if (!$approved): ?>
    <div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i> Your profile is <strong>waiting for administrator approval</strong>. You will see job requests as soon as you are approved.</div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <?php $cards = [['New requests', count($newJobs), 'bi-bell', ''], ['Accepted jobs', $cnt['a'], 'bi-check2-square', ''], ['In progress', $cnt['ip'], 'bi-gear', ''],
                    ['Completed jobs', $cnt['c'], 'bi-patch-check', ''], ['Earnings (paid)', money($earned), 'bi-cash-coin', ''], ['Awaiting payment', money($awaiting), 'bi-hourglass', '']];
    foreach ($cards as $cd): ?>
      <div class="col-6 col-lg-2"><div class="stat-card flex-column align-items-start gap-1">
        <span class="icon-circle sm"><i class="bi <?= $cd[2] ?>"></i></span>
        <div class="stat-value fs-5"><?= is_numeric($cd[1]) ? (int)$cd[1] : e($cd[1]) ?></div><div class="stat-label"><?= e($cd[0]) ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <p class="small text-muted">Earnings count only payments the administrator has recorded as paid. &ldquo;Awaiting payment&rdquo; is the booked price of completed jobs with no payment recorded yet (an estimate).</p>

  <div class="panel p-0 mb-4">
    <div class="p-3 border-bottom"><h5 class="mb-0">New job requests</h5></div>
    <div class="table-responsive"><table class="table mb-0">
      <thead class="table-light"><tr><th>ID</th><th>Service</th><th>Customer</th><th>When</th><th>Address</th><th>Problem</th><th>Price</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($newJobs as $j): ?>
        <tr>
          <td><?= e(booking_code($j['id'])) ?></td><td><?= e($j['service_name']) ?></td>
          <td><?= e($j['customer_name']) ?><?= $j['worker_id'] ? '<br><small class="text-muted">' . e($j['customer_phone']) . '</small>' : '' ?></td>
          <td><?= e(date('d M Y', strtotime($j['booking_date']))) ?><br><small><?= e(date('g:i A', strtotime($j['booking_time']))) ?></small></td>
          <td><?= e($j['address']) ?></td><td><?= e($j['problem_description']) ?></td><td><?= money($j['price']) ?></td>
          <td class="text-nowrap">
            <?= job_form('accept', $j['id'], 'Accept', 'btn-success') ?>
            <?= $j['worker_id'] ? job_form('reject', $j['id'], 'Reject', 'btn-outline-danger', 'Reject this job request?') : '' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$newJobs): ?><tr><td colspan="8" class="text-center text-muted py-4">No new requests right now.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>

  <div class="panel p-0 mb-4">
    <div class="p-3 border-bottom"><h5 class="mb-0">My jobs (accepted and in progress)</h5></div>
    <div class="table-responsive"><table class="table mb-0">
      <thead class="table-light"><tr><th>ID</th><th>Service</th><th>Customer details</th><th>When</th><th>Address</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
      <?php foreach ($activeJobs as $j): ?>
        <tr>
          <td><?= e(booking_code($j['id'])) ?></td><td><?= e($j['service_name']) ?><br><small class="text-muted"><?= e($j['problem_description']) ?></small></td>
          <td><?= e($j['customer_name']) ?><br><i class="bi bi-telephone"></i> <?= e($j['customer_phone']) ?></td>
          <td><?= e(date('d M Y', strtotime($j['booking_date']))) ?><br><small><?= e(date('g:i A', strtotime($j['booking_time']))) ?></small></td>
          <td><?= e($j['address']) ?></td><td><?= status_badge($j['status']) ?></td>
          <td class="text-nowrap">
            <?= $j['status'] === 'Accepted' ? job_form('start', $j['id'], 'Start job', 'btn-primary') : job_form('complete', $j['id'], 'Mark completed', 'btn-success', 'Mark this job as completed?') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$activeJobs): ?><tr><td colspan="7" class="text-center text-muted py-4">No active jobs.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>

  <div class="panel p-0">
    <div class="p-3 border-bottom"><h5 class="mb-0">Completed jobs</h5></div>
    <div class="table-responsive"><table class="table mb-0">
      <thead class="table-light"><tr><th>ID</th><th>Service</th><th>Customer</th><th>Date</th><th>Price</th></tr></thead>
      <tbody>
      <?php foreach ($doneJobs as $j): ?>
        <tr><td><?= e(booking_code($j['id'])) ?></td><td><?= e($j['service_name']) ?></td><td><?= e($j['customer_name']) ?></td>
          <td><?= e(date('d M Y', strtotime($j['booking_date']))) ?></td><td><?= money($j['price']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$doneJobs): ?><tr><td colspan="5" class="text-center text-muted py-4">No completed jobs yet.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
