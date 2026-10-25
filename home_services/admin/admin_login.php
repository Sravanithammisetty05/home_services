<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in() && current_role() === 'admin') { redirect('admin/dashboard.php'); }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(clean($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    [$user, $error] = attempt_login($pdo, $email, $password, true);   // true = administrators only
    if ($user) {
        login_user($user);
        set_flash('success', 'Welcome, ' . $user['name'] . '.');
        redirect('admin/dashboard.php');
    }
}

$page_title = 'Administrator Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center"><div class="col-md-6 col-lg-4">
    <div class="panel p-4">
      <h1 class="h4 mb-1"><i class="bi bi-shield-lock text-primary"></i> Administrator login</h1>
      <p class="text-muted small">Authorized staff only.</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email address</label>
          <input class="form-control" type="email" id="email" name="email" required value="<?= e($email) ?>" autofocus></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-primary w-100" type="submit">Log in</button>
      </form>
    </div>
  </div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
