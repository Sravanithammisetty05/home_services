<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$q = clean($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    $st = $pdo->prepare("SELECT * FROM services WHERE status='active' AND (service_name LIKE ? OR description LIKE ?) ORDER BY service_name");
    $st->execute([$like, $like]);
} else {
    $st = $pdo->query("SELECT * FROM services WHERE status='active' ORDER BY service_name");
}
$services = $st->fetchAll();

$page_title = 'Services';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h1 class="h2 mb-0">Our services</h1>
      <p class="text-muted mb-0"><?= $q !== '' ? count($services) . ' result(s) for &ldquo;' . e($q) . '&rdquo;' : 'Book a trusted professional for any job at home' ?></p>
    </div>
    <form class="d-flex" method="get" action="">
      <input class="form-control me-2" type="search" name="q" value="<?= e($q) ?>" placeholder="Search services" aria-label="Search services">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <?php if (!$services): ?>
    <div class="panel text-center py-5">
      <p class="mb-3">No services match your search.</p>
      <a class="btn btn-primary" href="<?= url('services.php') ?>">Show all services</a>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($services as $s): ?>
      <div class="col-sm-6 col-lg-3">
        <div class="service-card p-4 h-100 d-flex flex-column">
          <span class="icon-circle mb-3"><i class="bi <?= e($s['image']) ?>"></i></span>
          <h5><?= e($s['service_name']) ?></h5>
          <p class="text-muted small flex-grow-1"><?= e($s['description']) ?></p>
          <div class="d-flex justify-content-between align-items-center">
            <span class="price-tag">From <?= money($s['price']) ?></span>
            <a class="btn btn-accent btn-sm" href="<?= url('booking.php?service_id=' . (int)$s['id']) ?>">Book Now</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
