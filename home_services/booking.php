<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Visitors who click "Book Now" log in first, then come back to this form
if (!is_logged_in()) {
    $_SESSION['after_login'] = 'booking.php?service_id=' . (int)($_GET['service_id'] ?? 0);
}
require_role('customer');

$me = $pdo->prepare('SELECT name, address FROM users WHERE id = ?');
$me->execute([$_SESSION['user_id']]);
$customer = $me->fetch();

$services = $pdo->query("SELECT id, service_name, price FROM services WHERE status='active' ORDER BY service_name")->fetchAll();
$workers = $pdo->query(
    "SELECT u.id, u.name, wp.specialization, wp.experience, wp.availability_status
     FROM worker_profiles wp JOIN users u ON u.id = wp.user_id AND u.status = 'active'
     WHERE wp.approval_status = 'approved' ORDER BY u.name"
)->fetchAll();

$slots = [];
for ($h = 8; $h <= 20; $h++) { $slots[sprintf('%02d:00', $h)] = date('g:i A', strtotime(sprintf('%02d:00', $h))); }

$f = ['service_id' => (int)($_GET['service_id'] ?? 0), 'worker_id' => (int)($_GET['worker_id'] ?? 0),
      'booking_date' => '', 'booking_time' => '', 'address' => $customer['address'], 'problem_description' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $f['service_id'] = (int)($_POST['service_id'] ?? 0);
    $f['worker_id'] = (int)($_POST['worker_id'] ?? 0);
    $f['booking_date'] = trim((string)($_POST['booking_date'] ?? ''));
    $f['booking_time'] = trim((string)($_POST['booking_time'] ?? ''));
    $f['address'] = trim((string)($_POST['address'] ?? ''));
    $f['problem_description'] = trim((string)($_POST['problem_description'] ?? ''));

    // the price always comes from the database, never from the browser
    $svc = null;
    foreach ($services as $s) { if ((int)$s['id'] === $f['service_id']) $svc = $s; }
    if (!$svc) $errors[] = 'Choose a service.';

    $wid = null;
    if ($f['worker_id'] > 0) {
        foreach ($workers as $w) {
            if ((int)$w['id'] === $f['worker_id'] && $svc && $w['specialization'] === $svc['service_name']) $wid = (int)$w['id'];
        }
        if ($wid === null) $errors[] = 'The selected provider does not offer this service.';
    }
    $d = DateTime::createFromFormat('Y-m-d', $f['booking_date']);
    if (!$d || $d->format('Y-m-d') !== $f['booking_date']) $errors[] = 'Choose a valid service date.';
    elseif ($f['booking_date'] < date('Y-m-d')) $errors[] = 'The service date cannot be in the past.';
    if (!isset($slots[$f['booking_time']])) $errors[] = 'Choose a preferred time.';
    if (mb_strlen($f['address']) < 5) $errors[] = 'Enter the address where the service is needed.';
    if (mb_strlen($f['problem_description']) < 10) $errors[] = 'Describe the problem in at least 10 characters.';
    if (mb_strlen($f['problem_description']) > 1000) $errors[] = 'Problem description is too long (max 1000 characters).';

    if (!$errors) {
        try {
            $pdo->prepare('INSERT INTO bookings (customer_id, worker_id, service_id, booking_date, booking_time, address, problem_description, price, status)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$_SESSION['user_id'], $wid, $svc['id'], $f['booking_date'], $f['booking_time'] . ':00',
                           $f['address'], $f['problem_description'], $svc['price'], 'Pending']);
            $code = booking_code($pdo->lastInsertId());
            set_flash('success', "Booking confirmed! Your booking ID is $code. We will notify you here when a provider accepts it.");
            redirect('my_bookings.php');
        } catch (PDOException $ex) {
            error_log('Booking error: ' . $ex->getMessage());
            $errors[] = 'Could not save your booking. Please try again.';
        }
    }
}

$page_title = 'Book a Service';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Book a service</h1>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" class="panel p-4" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="customer_name">Customer name</label>
        <input class="form-control" id="customer_name" value="<?= e($customer['name']) ?>" readonly></div>
      <div class="col-md-6"><label class="form-label" for="service_id">Service category</label>
        <select class="form-select" id="service_id" name="service_id" required>
          <option value="">Choose a service</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-name="<?= e($s['service_name']) ?>" data-price-label="<?= e(money($s['price'])) ?> (starting price)"
              <?= $f['service_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['service_name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-6"><label class="form-label" for="worker_id">Service provider (optional)</label>
        <select class="form-select" id="worker_id" name="worker_id">
          <option value="">Any available provider</option>
          <?php foreach ($workers as $w): ?>
            <option value="<?= (int)$w['id'] ?>" data-service="<?= e($w['specialization']) ?>" <?= $f['worker_id'] === (int)$w['id'] ? 'selected' : '' ?>>
              <?= e($w['name']) ?> (<?= (int)$w['experience'] ?> yrs, <?= e($w['availability_status']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Leave on &ldquo;Any&rdquo; and the first matching provider to accept gets the job, or the administrator assigns one.</div></div>
      <div class="col-md-6"><label class="form-label" for="price_display">Estimated price</label>
        <input class="form-control" id="price_display" readonly placeholder="Select a service">
        <div class="form-text">Final price may change after the provider inspects the job.</div></div>
      <div class="col-md-6"><label class="form-label" for="booking_date">Service date</label>
        <input class="form-control" type="date" id="booking_date" name="booking_date" min="<?= date('Y-m-d') ?>" required value="<?= e($f['booking_date']) ?>"></div>
      <div class="col-md-6"><label class="form-label" for="booking_time">Preferred time</label>
        <select class="form-select" id="booking_time" name="booking_time" required>
          <option value="">Choose a time</option>
          <?php foreach ($slots as $val => $label): ?><option value="<?= e($val) ?>" <?= $f['booking_time'] === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label" for="address">Service address</label>
        <textarea class="form-control" id="address" name="address" rows="2" required><?= e($f['address']) ?></textarea></div>
      <div class="col-12"><label class="form-label" for="problem_description">Description of the problem</label>
        <textarea class="form-control" id="problem_description" name="problem_description" rows="3" maxlength="1000" required><?= e($f['problem_description']) ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-accent px-4" type="submit">Confirm booking</button>
      <a class="btn btn-outline-secondary" href="<?= url('services.php') ?>">Back to services</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
