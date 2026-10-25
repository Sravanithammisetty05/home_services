<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$errors = [];
$edit = ['id' => 0, 'service_name' => '', 'description' => '', 'price' => '', 'image' => 'bi-tools', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            set_flash('success', 'Service deleted.');
        } catch (PDOException $ex) {
            set_flash('danger', 'This service already has bookings, so it cannot be deleted. Set its status to Inactive instead.');
        }
        redirect('admin/services.php');
    }
    if ($action === 'save') {
        $edit = ['id' => (int)($_POST['id'] ?? 0), 'service_name' => clean($_POST['service_name'] ?? ''), 'description' => trim((string)($_POST['description'] ?? '')),
                 'price' => trim((string)($_POST['price'] ?? '')), 'image' => (string)($_POST['image'] ?? ''), 'status' => (string)($_POST['status'] ?? '')];
        if (mb_strlen($edit['service_name']) < 2 || mb_strlen($edit['service_name']) > 100) $errors[] = 'Service name must be 2 to 100 characters.';
        if (mb_strlen($edit['description']) < 10) $errors[] = 'Enter a description (at least 10 characters).';
        if (!is_numeric($edit['price']) || $edit['price'] <= 0 || $edit['price'] > 9999999) $errors[] = 'Enter a valid starting price greater than 0.';
        if (!isset(SERVICE_ICONS[$edit['image']])) $errors[] = 'Choose an icon.';
        if (!in_array($edit['status'], ['active', 'inactive'], true)) $errors[] = 'Choose a status.';
        if (!$errors) {
            try {
                if ($edit['id'] > 0) {
                    $pdo->beginTransaction();
                    $old = $pdo->prepare('SELECT service_name FROM services WHERE id = ?');
                    $old->execute([$edit['id']]);
                    $oldName = $old->fetchColumn();
                    $pdo->prepare('UPDATE services SET service_name=?, description=?, price=?, image=?, status=? WHERE id=?')
                        ->execute([$edit['service_name'], $edit['description'], $edit['price'], $edit['image'], $edit['status'], $edit['id']]);
                    if ($oldName !== false && $oldName !== $edit['service_name']) {   // keep workers linked to the renamed service
                        $pdo->prepare('UPDATE worker_profiles SET specialization = ? WHERE specialization = ?')->execute([$edit['service_name'], $oldName]);
                    }
                    $pdo->commit();
                    set_flash('success', 'Service updated.');
                } else {
                    $pdo->prepare('INSERT INTO services (service_name, description, price, image, status) VALUES (?,?,?,?,?)')
                        ->execute([$edit['service_name'], $edit['description'], $edit['price'], $edit['image'], $edit['status']]);
                    set_flash('success', 'Service added.');
                }
                redirect('admin/services.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = ($ex->getCode() === '23000') ? 'A service with this name already exists.' : 'Could not save the service.';
                error_log('Service save: ' . $ex->getMessage());
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    if ($row = $st->fetch()) $edit = $row;
}

$services = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS total FROM services s ORDER BY s.service_name')->fetchAll();

$page_title = 'Manage Services';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container"><h1 class="h3 mb-0">Manage services</h1><span class="text-muted">Add, edit and delete the services customers can book</span></div></div>
<div class="container">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="panel">
        <h5 class="mb-3"><?= $edit['id'] ? 'Edit service' : 'Add a service' ?></h5>
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" novalidate>
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
          <div class="mb-3"><label class="form-label" for="service_name">Service name</label><input class="form-control" id="service_name" name="service_name" maxlength="100" required value="<?= e($edit['service_name']) ?>"></div>
          <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" required><?= e($edit['description']) ?></textarea></div>
          <div class="mb-3"><label class="form-label" for="price">Starting price (<?= e(CURRENCY) ?>)</label><input class="form-control" type="number" step="0.01" min="0.01" id="price" name="price" required value="<?= e($edit['price']) ?>"></div>
          <div class="mb-3"><label class="form-label" for="image">Icon</label>
            <select class="form-select" id="image" name="image"><?php foreach (SERVICE_ICONS as $cls => $lbl): ?><option value="<?= e($cls) ?>" <?= $edit['image'] === $cls ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
          <div class="mb-3"><label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="active" <?= $edit['status'] === 'active' ? 'selected' : '' ?>>Active (visible to customers)</option><option value="inactive" <?= $edit['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option></select></div>
          <button class="btn btn-primary" type="submit"><?= $edit['id'] ? 'Save changes' : 'Add service' ?></button>
          <?php if ($edit['id']): ?><a class="btn btn-outline-secondary" href="<?= url('admin/services.php') ?>">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="panel p-0"><div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Service</th><th>Price</th><th>Bookings</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($services as $s): ?>
          <tr>
            <td><i class="bi <?= e($s['image']) ?> text-primary me-2"></i><strong><?= e($s['service_name']) ?></strong><div class="small text-muted"><?= e($s['description']) ?></div></td>
            <td><?= money($s['price']) ?></td><td><?= (int)$s['total'] ?></td>
            <td><span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/services.php?edit=' . (int)$s['id']) ?>">Edit</a>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= e($s['service_name']) ?>? This cannot be undone.">Delete</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
