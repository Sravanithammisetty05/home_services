<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid = (int)($_POST['user_id'] ?? 0);
    $new = (($_POST['action'] ?? '') === 'block') ? 'blocked' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'")->execute([$new, $uid]);
    set_flash('success', $new === 'blocked' ? 'Customer blocked.' : 'Customer unblocked.');
    redirect('admin/customers.php');
}

$customers = $pdo->query(
    "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = u.id) AS total_bookings
     FROM users u WHERE u.role = 'customer' ORDER BY u.created_at DESC"
)->fetchAll();

$page_title = 'Manage Customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container"><h1 class="h3 mb-0">Manage customers</h1><span class="text-muted">View accounts and block misuse</span></div></div>
<div class="container">
  <div class="panel p-0"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Customer</th><th>Contact</th><th>Address</th><th>Bookings</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
      <tr>
        <td><strong><?= e($c['name']) ?></strong><br><small class="text-muted">Joined <?= e(date('d M Y', strtotime($c['created_at']))) ?></small></td>
        <td><?= e($c['email']) ?><br><small><?= e($c['phone']) ?></small></td>
        <td><?= e($c['address']) ?></td><td><?= (int)$c['total_bookings'] ?></td>
        <td><span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'danger' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>">
            <?php if ($c['status'] === 'active'): ?>
              <input type="hidden" name="action" value="block"><button class="btn btn-sm btn-outline-danger" data-confirm="Block <?= e($c['name']) ?>? They will be signed out and cannot log in.">Block</button>
            <?php else: ?>
              <input type="hidden" name="action" value="unblock"><button class="btn btn-sm btn-outline-success">Unblock</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$customers): ?><tr><td colspan="6" class="text-center text-muted py-4">No customers yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
