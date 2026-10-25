<?php
/* includes/header.php - shared <head> + navigation. Set $page_title before including. */
$role = current_role();
$page_title = $page_title ?? 'Home Services';
$P = fn($file, $label, $href) => compact('file', 'label', 'href');
if ($role === 'admin') {
    $links = [$P('dashboard.php','Dashboard','admin/dashboard.php'), $P('workers.php','Workers','admin/workers.php'),
              $P('customers.php','Customers','admin/customers.php'), $P('services.php','Services','admin/services.php'),
              $P('bookings.php','Bookings','admin/bookings.php'), $P('reports.php','Reports','admin/reports.php')];
} else {
    $links = [$P('index.php','Home','index.php'), $P('services.php','Services','services.php'),
              $P('','About Us','index.php#about'), $P('','Contact','index.php#contact')];
    if ($role === 'customer') {
        $links[] = $P('dashboard.php','Dashboard','customer/dashboard.php');
        $links[] = $P('my_bookings.php','My Bookings','my_bookings.php');
    } elseif ($role === 'worker') {
        $links[] = $P('dashboard.php','Dashboard','worker/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> | Home Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-hs sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url($role === 'admin' ? 'admin/dashboard.php' : 'index.php') ?>">
      <i class="bi bi-house-heart-fill text-accent me-2"></i>Home Services<?= $role === 'admin' ? ' <small class="badge bg-warning text-dark ms-1">Admin</small>' : '' ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php foreach ($links as $l): ?>
          <li class="nav-item"><a class="nav-link<?= $l['file'] ? nav_active($l['file']) : '' ?>" href="<?= url($l['href']) ?>"><?= e($l['label']) ?></a></li>
        <?php endforeach; ?>
        <?php if (is_logged_in()): ?>
          <li class="nav-item ms-lg-2"><span class="navbar-text text-white-50 small me-2"><i class="bi bi-person-circle"></i> <?= e($_SESSION['name']) ?></span></li>
          <li class="nav-item"><a class="btn btn-sm btn-accent" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('login.php') ?>" href="<?= url('login.php') ?>">Login</a></li>
          <li class="nav-item"><a class="btn btn-sm btn-accent ms-lg-1" href="<?= url('register.php') ?>">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main>
<div class="container mt-3">
  <?php foreach (get_flash() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($f['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
</div>
