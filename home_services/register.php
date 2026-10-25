<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { redirect(dashboard_for(current_role())); }

$services = $pdo->query("SELECT service_name FROM services WHERE status='active' ORDER BY service_name")->fetchAll(PDO::FETCH_COLUMN);
$old = ['role' => (($_GET['role'] ?? '') === 'worker') ? 'worker' : 'customer',
        'name' => '', 'email' => '', 'phone' => '', 'address' => '', 'specialization' => '', 'experience' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $v) { $old[$k] = trim((string)($_POST[$k] ?? '')); }
    $old['name'] = clean($old['name']);
    $old['email'] = strtolower(clean($old['email']));
    $old['phone'] = clean($old['phone']);
    $old['role'] = ($old['role'] === 'worker') ? 'worker' : 'customer';
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) $errors[] = 'Enter your full name (2 to 100 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) $errors[] = 'Enter a valid email address.';
    if (!is_valid_phone($old['phone'])) $errors[] = 'Enter a valid phone number (7 to 15 digits).';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (mb_strlen($old['address']) < 5) $errors[] = 'Enter your address.';
    if ($old['role'] === 'worker') {
        if (!in_array($old['specialization'], $services, true)) $errors[] = 'Choose your service specialization.';
        if ($old['experience'] === '' || !ctype_digit($old['experience']) || (int)$old['experience'] > 60) $errors[] = 'Enter your experience in years (0 to 60).';
    }
    if (!$errors) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$old['email']]);
        if ($chk->fetch()) $errors[] = 'An account with this email already exists. Try logging in.';
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO users (name, email, phone, password, role, address) VALUES (?,?,?,?,?,?)');
            $ins->execute([$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT), $old['role'], $old['address']]);
            $uid = (int)$pdo->lastInsertId();
            if ($old['role'] === 'worker') {
                $pdo->prepare('INSERT INTO worker_profiles (user_id, specialization, experience) VALUES (?,?,?)')
                    ->execute([$uid, $old['specialization'], (int)$old['experience']]);
            }
            $pdo->commit();
            set_flash('success', $old['role'] === 'worker'
                ? 'Registration successful! You can log in now. You will receive job requests after the administrator approves your profile.'
                : 'Registration successful! Please log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Register error: ' . $ex->getMessage());
            $errors[] = ($ex->getCode() === '23000') ? 'An account with this email already exists.' : 'Could not create your account. Please try again.';
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="row justify-content-center"><div class="col-lg-7">
    <div class="panel p-4">
      <h1 class="h3 mb-1">Create your account</h1>
      <p class="text-muted">Already registered? <a href="<?= url('login.php') ?>">Log in</a></p>

      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label d-block">I want to</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="role" id="roleC" value="customer" <?= $old['role'] === 'customer' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="roleC"><i class="bi bi-person"></i> Book services (Customer)</label>
            <input type="radio" class="btn-check" name="role" id="roleW" value="worker" <?= $old['role'] === 'worker' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="roleW"><i class="bi bi-tools"></i> Offer services (Worker)</label>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="name">Full name</label>
            <input class="form-control" id="name" name="name" maxlength="100" required value="<?= e($old['name']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="email">Email address</label>
            <input class="form-control" type="email" id="email" name="email" maxlength="150" required value="<?= e($old['email']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="phone">Phone number</label>
            <input class="form-control" type="tel" id="phone" name="phone" maxlength="15" required value="<?= e($old['phone']) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
            <div class="form-text">At least 8 characters.</div></div>
          <div class="col-md-6"><label class="form-label" for="confirm_password">Confirm password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
          <div class="col-12"><label class="form-label" for="address">Address</label>
            <textarea class="form-control" id="address" name="address" rows="2" required><?= e($old['address']) ?></textarea></div>
        </div>
        <div id="workerFields" class="row g-3 mt-0 <?= $old['role'] === 'worker' ? '' : 'd-none' ?>">
          <div class="col-md-8"><label class="form-label" for="specialization">Service specialization</label>
            <select class="form-select" id="specialization" name="specialization">
              <option value="">Choose a service</option>
              <?php foreach ($services as $sv): ?><option <?= $old['specialization'] === $sv ? 'selected' : '' ?>><?= e($sv) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label" for="experience">Experience (years)</label>
            <input class="form-control" type="number" min="0" max="60" id="experience" name="experience" value="<?= e($old['experience']) ?>"></div>
        </div>
        <button class="btn btn-accent w-100 mt-4" type="submit">Create account</button>
      </form>
    </div>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
