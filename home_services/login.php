<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { redirect(dashboard_for(current_role())); }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(clean($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        [$user, $error] = attempt_login($pdo, $email, $password, false);
        if ($user) {
            login_user($user);
            // after logging in from a "Book Now" button, return to that booking form
            $next = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            if ($user['role'] === 'customer' && preg_match('/^booking\.php\?service_id=\d+$/', $next)) { redirect($next); }
            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect(dashboard_for($user['role']));
        }
    }
}

$page_title = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center"><div class="col-md-6 col-lg-5">
    <div class="panel p-4">
      <h1 class="h3 mb-1">Log in</h1>
      <p class="text-muted">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email address</label>
          <input class="form-control" type="email" id="email" name="email" required value="<?= e($email) ?>" autofocus></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-accent w-100" type="submit">Log in</button>
      </form>
    </div>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
