<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$categories = $pdo->query("SELECT id, service_name, image, price FROM services WHERE status='active' ORDER BY id LIMIT 8")->fetchAll();
$providers = $pdo->query(
    "SELECT u.id AS worker_id, u.name, wp.specialization, wp.experience, wp.availability_status, s.id AS service_id
     FROM worker_profiles wp
     JOIN users u ON u.id = wp.user_id AND u.status = 'active'
     LEFT JOIN services s ON s.service_name = wp.specialization AND s.status = 'active'
     WHERE wp.approval_status = 'approved'
     ORDER BY wp.experience DESC LIMIT 4"
)->fetchAll();

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container text-center">
    <h1 class="display-5">Reliable Home Services at Your Doorstep</h1>
    <p class="lead mx-auto mb-4" style="max-width:640px">Book verified electricians, plumbers, cleaners, AC technicians and more. Pick a service, choose a time, and a professional comes to you.</p>
    <form class="search-box mx-auto d-flex" action="<?= url('services.php') ?>" method="get">
      <input type="search" name="q" class="form-control form-control-lg" placeholder="What do you need? e.g. plumbing, AC, painting" aria-label="Search services">
      <button class="btn btn-accent rounded-pill px-4" type="submit"><i class="bi bi-search"></i> Search</button>
    </form>
  </div>
</section>

<section class="container py-5">
  <div class="text-center mb-4">
    <h2 class="section-title">Popular service categories</h2>
    <p class="text-muted">Choose from our most requested home services</p>
  </div>
  <div class="row g-3">
    <?php foreach ($categories as $c): ?>
      <div class="col-6 col-md-3">
        <a href="<?= url('booking.php?service_id=' . (int)$c['id']) ?>" class="text-decoration-none text-reset">
          <div class="service-card text-center p-3 h-100">
            <span class="icon-circle mb-2"><i class="bi <?= e($c['image']) ?>"></i></span>
            <h6 class="mb-1"><?= e($c['service_name']) ?></h6>
            <small class="price-tag">From <?= money($c['price']) ?></small>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-4"><a href="<?= url('services.php') ?>" class="btn btn-primary">View all services</a></div>
</section>

<section class="bg-white py-5 border-top border-bottom">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">Featured service providers</h2>
      <p class="text-muted">Approved professionals with the most experience</p>
    </div>
    <div class="row g-3">
      <?php if (!$providers): ?>
        <p class="text-center text-muted">Providers will appear here once the administrator approves them.</p>
      <?php endif; ?>
      <?php foreach ($providers as $p): ?>
        <div class="col-sm-6 col-lg-3">
          <div class="provider-card text-center p-4 h-100">
            <span class="avatar mb-2"><?= e(strtoupper(substr($p['name'], 0, 1))) ?></span>
            <h6 class="mb-0"><?= e($p['name']) ?></h6>
            <div class="text-muted small"><?= e($p['specialization']) ?></div>
            <div class="small my-2"><i class="bi bi-award text-accent"></i> <?= (int)$p['experience'] ?> yrs experience</div>
            <span class="badge <?= $p['availability_status'] === 'available' ? 'bg-success' : 'bg-secondary' ?> mb-2"><?= e(ucfirst($p['availability_status'])) ?></span><br>
            <?php if ($p['service_id']): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= url('booking.php?service_id=' . (int)$p['service_id'] . '&worker_id=' . (int)$p['worker_id']) ?>">Book <?= e(explode(' ', $p['name'])[0]) ?></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="text-center mb-4"><h2 class="section-title">How it works</h2></div>
  <div class="row g-3 text-center">
    <?php $steps = [['bi-search','Choose a service','Browse services or search for what you need.'],
                    ['bi-calendar-check','Book a time','Pick a date, time and describe the problem.'],
                    ['bi-person-check','Provider confirms','A professional accepts your request.'],
                    ['bi-emoji-smile','Job done','Track the status and pay once the work is finished.']];
    foreach ($steps as $i => $s): ?>
      <div class="col-6 col-md-3">
        <div class="step-card p-3 h-100">
          <span class="step-no mb-2"><?= $i + 1 ?></span>
          <h6><i class="bi <?= $s[0] ?> text-primary me-1"></i><?= e($s[1]) ?></h6>
          <p class="small text-muted mb-0"><?= e($s[2]) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-primary-soft py-5">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">What customers say</h2>
      <p class="text-muted small">Sample testimonials for demonstration</p>
    </div>
    <div class="row g-3">
      <?php $t = [['Priya N.','The plumber arrived on time and fixed the leak in under an hour. Booking was simple.'],
                  ['Arjun M.','Clear pricing and a polite technician for my AC service. I will book again.'],
                  ['Kavitha R.','The cleaning team did a thorough job. Being able to track the booking helped a lot.']];
      foreach ($t as $x): ?>
        <div class="col-md-4"><div class="testimonial-card p-4 h-100">
          <div class="text-warning mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
          <p class="mb-2">&ldquo;<?= e($x[1]) ?>&rdquo;</p><strong class="small"><?= e($x[0]) ?></strong>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="about" class="container py-5">
  <div class="row align-items-center g-4">
    <div class="col-md-6">
      <h2 class="section-title">About Us</h2>
      <p>Home Services connects households with skilled, approved professionals for everyday repairs and maintenance. Customers book online, providers manage their jobs from a simple dashboard, and our team keeps every booking on track.</p>
    </div>
    <div class="col-md-6" id="contact">
      <div class="panel">
        <h4 class="mb-3">Contact us</h4>
        <p class="mb-1"><i class="bi bi-envelope text-primary me-2"></i>support@homeservices.example</p>
        <p class="mb-1"><i class="bi bi-telephone text-primary me-2"></i>1800-000-000</p>
        <p class="mb-0"><i class="bi bi-clock text-primary me-2"></i>Monday to Saturday, 8 AM to 8 PM</p>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
