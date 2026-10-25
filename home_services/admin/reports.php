<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$valid = fn($d) => ($x = DateTime::createFromFormat('Y-m-d', (string)$d)) && $x->format('Y-m-d') === $d;
$from = $valid($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01', strtotime('-5 months'));
$to   = $valid($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d', strtotime('+1 year'));

$q = $pdo->prepare('SELECT status, COUNT(*) AS n, COALESCE(SUM(price),0) AS value FROM bookings WHERE booking_date BETWEEN ? AND ? GROUP BY status');
$q->execute([$from, $to]);
$byStatus = array_column($q->fetchAll(), null, 'status');

$q = $pdo->prepare("SELECT s.service_name, COUNT(b.id) AS total, COALESCE(SUM(b.status='Completed'),0) AS done,
                           COALESCE(SUM(CASE WHEN p.payment_status='Paid' THEN p.amount END),0) AS paid
                    FROM services s LEFT JOIN bookings b ON b.service_id = s.id AND b.booking_date BETWEEN ? AND ?
                    LEFT JOIN payments p ON p.booking_id = b.id GROUP BY s.id, s.service_name ORDER BY total DESC, s.service_name");
$q->execute([$from, $to]);
$byService = $q->fetchAll();

$q = $pdo->prepare("SELECT u.name, wp.specialization, COUNT(b.id) AS done,
                           COALESCE(SUM(CASE WHEN p.payment_status='Paid' THEN p.amount END),0) AS paid
                    FROM users u JOIN worker_profiles wp ON wp.user_id = u.id
                    LEFT JOIN bookings b ON b.worker_id = u.id AND b.status='Completed' AND b.booking_date BETWEEN ? AND ?
                    LEFT JOIN payments p ON p.booking_id = b.id
                    WHERE u.role='worker' GROUP BY u.id, u.name, wp.specialization ORDER BY done DESC, paid DESC LIMIT 10");
$q->execute([$from, $to]);
$byWorker = $q->fetchAll();

$monthly = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS m, SUM(amount) AS total FROM payments
                        WHERE payment_status='Paid' AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 5 MONTH)
                        GROUP BY m ORDER BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
$months = [];
for ($i = 5; $i >= 0; $i--) { $k = date('Y-m', strtotime("first day of -$i month")); $months[$k] = (float)($monthly[$k] ?? 0); }
$maxMonth = max($months) ?: 1;

$q = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.payment_status='Paid' AND b.booking_date BETWEEN ? AND ?");
$q->execute([$from, $to]);
$paidTotal = (float)$q->fetchColumn();
$q = $pdo->prepare("SELECT COALESCE(SUM(b.price),0) FROM bookings b LEFT JOIN payments p ON p.booking_id = b.id AND p.payment_status='Paid'
                    WHERE b.status='Completed' AND p.id IS NULL AND b.booking_date BETWEEN ? AND ?");
$q->execute([$from, $to]);
$unpaidEstimate = (float)$q->fetchColumn();

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div><h1 class="h3 mb-0">Reports</h1><span class="text-muted">Booking and revenue summary by service date</span></div>
  <button class="btn btn-outline-primary d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div></div>
<div class="container">
  <form class="row g-2 align-items-end mb-4 d-print-none" method="get">
    <div class="col-auto"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="col-auto"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-primary">Apply</button> <a class="btn btn-outline-secondary" href="<?= url('admin/reports.php') ?>">Reset</a></div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><span class="icon-circle sm"><i class="bi bi-cash-stack"></i></span><div><div class="stat-value"><?= money($paidTotal) ?></div><div class="stat-label">Recorded revenue (paid payments)</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><span class="icon-circle sm"><i class="bi bi-hourglass"></i></span><div><div class="stat-value"><?= money($unpaidEstimate) ?></div><div class="stat-label">Estimate: completed jobs, no payment recorded</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><span class="icon-circle sm"><i class="bi bi-journal-text"></i></span><div><div class="stat-value"><?= array_sum(array_column($byStatus, 'n')) ?></div><div class="stat-label">Bookings in this period</div></div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5"><div class="panel h-100"><h5>Bookings by status</h5>
      <table class="table table-sm mb-0"><thead><tr><th>Status</th><th class="text-end">Bookings</th><th class="text-end">Booked value</th></tr></thead><tbody>
      <?php foreach (BOOKING_STATUSES as $s): $r = $byStatus[$s] ?? ['n' => 0, 'value' => 0]; ?>
        <tr><td><?= status_badge($s) ?></td><td class="text-end"><?= (int)$r['n'] ?></td><td class="text-end"><?= money($r['value']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div></div>
    <div class="col-lg-7"><div class="panel h-100"><h5>Recorded revenue, last 6 months</h5>
      <?php foreach ($months as $m => $v): ?>
        <div class="d-flex align-items-center gap-2 mb-2"><span class="small text-muted" style="width:70px"><?= e(date('M Y', strtotime($m . '-01'))) ?></span>
          <div class="progress flex-grow-1" style="height:18px"><div class="progress-bar" style="width:<?= round($v / $maxMonth * 100) ?>%"></div></div>
          <span class="small" style="width:100px;text-align:right"><?= money($v) ?></span></div>
      <?php endforeach; ?></div></div>
    <div class="col-lg-7"><div class="panel"><h5>Bookings and revenue by service</h5>
      <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Service</th><th class="text-end">Bookings</th><th class="text-end">Completed</th><th class="text-end">Paid revenue</th></tr></thead><tbody>
      <?php foreach ($byService as $r): ?><tr><td><?= e($r['service_name']) ?></td><td class="text-end"><?= (int)$r['total'] ?></td><td class="text-end"><?= (int)$r['done'] ?></td><td class="text-end"><?= money($r['paid']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div></div>
    <div class="col-lg-5"><div class="panel"><h5>Top workers</h5>
      <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Worker</th><th class="text-end">Jobs done</th><th class="text-end">Paid</th></tr></thead><tbody>
      <?php foreach ($byWorker as $r): ?><tr><td><?= e($r['name']) ?><br><small class="text-muted"><?= e($r['specialization']) ?></small></td><td class="text-end"><?= (int)$r['done'] ?></td><td class="text-end"><?= money($r['paid']) ?></td></tr><?php endforeach; ?>
      <?php if (!$byWorker): ?><tr><td colspan="3" class="text-muted">No workers yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
