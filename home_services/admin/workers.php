<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid = (int)($_POST['user_id'] ?? 0);
    $map = ['approve' => 'approved', 'deactivate' => 'deactivated', 'reactivate' => 'approved'];
    $action = $_POST['action'] ?? '';
    if (isset($map[$action])) {
        $st = $pdo->prepare('UPDATE worker_profiles SET approval_status = ? WHERE user_id = ?');
        $st->execute([$map[$action], $uid]);
        set_flash('success', 'Worker status updated.');
    }
    redirect('admin/workers.php');
}

$workers = $pdo->query(
    "SELECT u.id, u.name, u.email, u.phone, u.address, u.created_at, wp.specialization, wp.experience, wp.approval_status, wp.availability_status,
            (SELECT COUNT(*) FROM bookings b WHERE b.worker_id = u.id AND b.status = 'Completed') AS done
     FROM users u JOIN worker_profiles wp ON wp.user_id = u.id WHERE u.role = 'worker'
     ORDER BY FIELD(wp.approval_status,'pending','approved','deactivated'), u.created_at DESC"
)->fetchAll();
$badge = ['pending' => 'warning text-dark', 'approved' => 'success', 'deactivated' => 'secondary'];

function worker_btn($action, $uid, $label, $class) {
    return '<form method="post" class="d-inline">' . csrf_field() . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="user_id" value="' . (int)$uid . '">'
         . '<button class="btn btn-sm ' . $class . '">' . $label . '</button></form> ';
}

$page_title = 'Manage Workers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container"><h1 class="h3 mb-0">Manage workers</h1><span class="text-muted">Approve new providers and deactivate accounts</span></div></div>
<div class="container">
  <div class="panel p-0"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Worker</th><th>Contact</th><th>Specialization</th><th>Experience</th><th>Jobs done</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($workers as $w): ?>
      <tr>
        <td><strong><?= e($w['name']) ?></strong><br><small class="text-muted">Joined <?= e(date('d M Y', strtotime($w['created_at']))) ?></small></td>
        <td><?= e($w['email']) ?><br><small><?= e($w['phone']) ?></small></td>
        <td><?= e($w['specialization']) ?></td><td><?= (int)$w['experience'] ?> yrs</td><td><?= (int)$w['done'] ?></td>
        <td><span class="badge bg-<?= $badge[$w['approval_status']] ?>"><?= e(ucfirst($w['approval_status'])) ?></span></td>
        <td class="text-nowrap">
          <?php if ($w['approval_status'] === 'pending') echo worker_btn('approve', $w['id'], 'Approve', 'btn-success'); ?>
          <?php if ($w['approval_status'] === 'approved') echo worker_btn('deactivate', $w['id'], 'Deactivate', 'btn-outline-danger'); ?>
          <?php if ($w['approval_status'] === 'deactivated') echo worker_btn('reactivate', $w['id'], 'Reactivate', 'btn-outline-success'); ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$workers): ?><tr><td colspan="7" class="text-center text-muted py-4">No workers have registered yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
