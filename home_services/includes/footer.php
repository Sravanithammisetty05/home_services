</main>
<footer class="footer-hs mt-5 pt-5 pb-3 text-white-50">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="text-white"><i class="bi bi-house-heart-fill text-accent me-2"></i>Home Services</h5>
        <p class="small">Find trusted electricians, plumbers, cleaners and more, and book them in a few clicks.</p>
        <div class="fs-4 d-flex gap-3">
          <a class="text-white-50" href="https://facebook.com" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a class="text-white-50" href="https://x.com" target="_blank" rel="noopener" aria-label="X"><i class="bi bi-twitter-x"></i></a>
          <a class="text-white-50" href="https://instagram.com" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a class="text-white-50" href="https://linkedin.com" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <h6 class="text-white">Quick links</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= url('index.php') ?>">Home</a></li>
          <li><a href="<?= url('services.php') ?>">Services</a></li>
          <li><a href="<?= url('index.php#about') ?>">About Us</a></li>
          <li><a href="<?= url('index.php#contact') ?>">Contact</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-3">
        <h6 class="text-white">Account</h6>
        <ul class="list-unstyled small">
          <?php if (is_logged_in()): ?>
            <li><a href="<?= url(dashboard_for(current_role())) ?>">My dashboard</a></li>
            <li><a href="<?= url('logout.php') ?>">Logout</a></li>
          <?php else: ?>
            <li><a href="<?= url('login.php') ?>">Login</a></li>
            <li><a href="<?= url('register.php') ?>">Register as customer</a></li>
            <li><a href="<?= url('register.php?role=worker') ?>">Join as a service provider</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-white">Contact</h6>
        <ul class="list-unstyled small">
          <li><i class="bi bi-envelope me-2"></i>support@homeservices.example</li>
          <li><i class="bi bi-telephone me-2"></i>1800-000-000</li>
          <li><i class="bi bi-clock me-2"></i>Mon-Sat, 8 AM to 8 PM</li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary">
    <p class="text-center small mb-0">&copy; <?= date('Y') ?> Home Services. All rights reserved.</p>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
