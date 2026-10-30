<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$admin = require_role('admin');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_services(): never
{
    header('Location: services.php');
    exit;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if (!isset($_SESSION['services_flash'])) {
    $_SESSION['services_flash'] = null;
}

$error = '';
$flash = $_SESSION['services_flash'];
unset($_SESSION['services_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'toggle') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                $error = 'Invalid service selected.';
            } else {
                $stmt = db()->prepare('UPDATE services SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $_SESSION['services_flash'] = $stmt->rowCount() > 0
                    ? 'Service status updated.'
                    : 'Service not found or its status was already unchanged.';
                redirect_services();
            }
        } elseif ($action === 'create' || $action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $priceText = trim((string)($_POST['price'] ?? ''));
            $durationText = trim((string)($_POST['duration_minutes'] ?? ''));

            if ($action === 'update' && !$id) {
                $error = 'Invalid service selected for editing.';
            } elseif ($name === '' || text_length($name) > 120) {
                $error = 'Service name is required and must be 120 characters or fewer.';
            } elseif (text_length($description) > 2000) {
                $error = 'Description must be 2,000 characters or fewer.';
            } elseif ($priceText === '' || !is_numeric($priceText) || (float)$priceText < 0 || (float)$priceText > 99999999.99 || preg_match('/^\d+(?:\.\d{1,2})?$/', $priceText) !== 1) {
                $error = 'Enter a valid price with up to two decimal places (for example, 25.00).';
            } elseif (filter_var($durationText, FILTER_VALIDATE_INT) === false || (int)$durationText < 5 || (int)$durationText > 720) {
                $error = 'Duration must be a whole number from 5 to 720 minutes.';
            } else {
                $price = number_format((float)$priceText, 2, '.', '');
                $descriptionValue = $description === '' ? null : $description;
                if ($action === 'create') {
                    $stmt = db()->prepare(
                        'INSERT INTO services (name, description, price, duration_minutes, is_active, created_by)
                         VALUES (:name, :description, :price, :duration, 1, :created_by)'
                    );
                    $stmt->execute([
                        'name' => $name,
                        'description' => $descriptionValue,
                        'price' => $price,
                        'duration' => (int)$durationText,
                        'created_by' => (int)$admin['id'],
                    ]);
                    $_SESSION['services_flash'] = 'Service added successfully.';
                } else {
                    $stmt = db()->prepare(
                        'UPDATE services
                         SET name = :name, description = :description, price = :price, duration_minutes = :duration
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'name' => $name,
                        'description' => $descriptionValue,
                        'price' => $price,
                        'duration' => (int)$durationText,
                        'id' => (int)$id,
                    ]);
                    $_SESSION['services_flash'] = 'Service details saved.';
                }
                redirect_services();
            }
        } else {
            $error = 'Unknown service action.';
        }
    }
}

$services = db()->query(
    'SELECT id, name, description, price, duration_minutes, is_active, created_at, updated_at
     FROM services
     ORDER BY is_active DESC, name ASC, id DESC'
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SalonDiva · Services management</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
:root{--pink-1:#ffc2d1;--pink-2:#ff93b0;--cream:#fff6ec;--yellow:#ffd45c;--crimson:#c8264f;--ink:#2b1520;--muted:#806b73;--line:#ead9dc;--white:#fff}
*{box-sizing:border-box}body{margin:0;padding:24px;background:linear-gradient(135deg,var(--pink-1),var(--pink-2));font:16px/1.5 Outfit,system-ui,sans-serif;color:var(--ink)}
.wrap{max-width:1180px;margin:auto}.panel{background:var(--cream);padding:clamp(20px,4vw,36px);border-radius:28px;box-shadow:0 22px 70px #8f24452b;margin-bottom:20px}
.brand{font-size:1.55rem;font-weight:800;color:var(--crimson)}h1{font-size:clamp(1.8rem,4vw,2.5rem);margin:.3rem 0}.muted{color:var(--muted);margin-top:0}.top{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.notice{padding:12px 14px;border-radius:13px;background:#dff4e5;margin:12px 0}.error{background:#ffe2e6;color:#8e1633}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.field label{display:block;font-size:.9rem;font-weight:600;color:var(--muted);margin-bottom:5px}.field input,.field textarea{width:100%;border:1px solid #e5cbd2;border-radius:13px;padding:12px 13px;font:inherit;background:#fff;color:var(--ink)}.field textarea{min-height:88px;resize:vertical}.field.full{grid-column:1/-1}.help{font-size:.83rem;color:var(--muted);margin-top:4px}
button,.linkbtn{border:0;border-radius:999px;padding:10px 16px;font:600 .95rem Outfit,system-ui,sans-serif;cursor:pointer;text-decoration:none;display:inline-flex;justify-content:center;align-items:center}.primary{background:var(--crimson);color:white}.dark{background:var(--ink);color:#fff}.soft{background:#ffe5ed;color:var(--ink)}.status{display:inline-block;border-radius:999px;padding:4px 10px;font-size:.8rem;font-weight:700}.active{background:#d9f3df;color:#1e6038}.inactive{background:#ffe0e5;color:#92213e}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:840px}th,td{text-align:left;vertical-align:top;border-bottom:1px solid var(--line);padding:12px 9px}th{font-size:.82rem;color:var(--muted)}td .field input,td .field textarea{padding:8px 10px;border-radius:10px;min-width:90px}td .field textarea{min-width:190px;min-height:58px}.rowform{display:contents}.actions{display:flex;gap:7px;flex-wrap:wrap}.actionform{display:inline}.empty{background:white;border-radius:14px;padding:22px}.navlinks{display:flex;gap:9px;flex-wrap:wrap}.fine{font-size:.85rem;color:var(--muted)}
@media(max-width:700px){body{padding:12px}.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.panel{border-radius:20px}}
</style>
</head>
<body>
<main class="wrap">
  <section class="panel">
    <div class="top"><div><div class="brand">SalonDiva</div><h1>Services management</h1><p class="muted">Add services, maintain prices and durations, or deactivate services you no longer offer.</p></div><div class="navlinks"><a class="linkbtn dark" href="dashboard.php">Back to dashboard</a></div></div>
    <?php if ($flash): ?><div class="notice"><?= h((string)$flash) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
  </section>

  <section class="panel">
    <h2>Add a service</h2>
    <p class="muted">New services are active as soon as you save them.</p>
    <form method="post" action="services.php">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="grid">
        <div class="field"><label for="new-name">Service name *</label><input id="new-name" name="name" maxlength="120" required placeholder="e.g. Haircut"></div>
        <div class="field"><label for="new-price">Price *</label><input id="new-price" name="price" type="number" min="0" max="99999999.99" step="0.01" required placeholder="e.g. 1500.00"></div>
        <div class="field"><label for="new-duration">Duration in minutes *</label><input id="new-duration" name="duration_minutes" type="number" min="5" max="720" step="1" value="30" required></div>
        <div class="field"><label for="new-description">Description</label><textarea id="new-description" name="description" maxlength="2000" placeholder="Optional details about this service"></textarea></div>
      </div>
      <p><button class="primary" type="submit">Add service</button></p>
    </form>
  </section>

  <section class="panel">
    <div class="top"><div><h2>Service catalogue</h2><p class="muted">Edit a service and press Save changes. Deactivating hides it from future booking flows without deleting its record.</p></div><span class="status active"><?= count($services) ?> total</span></div>
    <?php if (!$services): ?>
      <div class="empty">No services have been added yet. Use the form above to create your first service.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Status</th><th>Name</th><th>Description</th><th>Price</th><th>Duration (min)</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($services as $service): ?>
          <tr>
            <td><span class="status <?= (int)$service['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$service['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <td colspan="4">
              <form id="edit-<?= (int)$service['id'] ?>" method="post" action="services.php" class="rowform">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                <div class="grid" style="grid-template-columns:1fr 1.4fr .8fr .7fr;gap:8px">
                  <div class="field"><label class="fine">Name</label><input name="name" required maxlength="120" value="<?= h((string)$service['name']) ?>"></div>
                  <div class="field"><label class="fine">Description</label><textarea name="description" maxlength="2000"><?= h((string)($service['description'] ?? '')) ?></textarea></div>
                  <div class="field"><label class="fine">Price</label><input name="price" type="number" min="0" max="99999999.99" step="0.01" required value="<?= h((string)$service['price']) ?>"></div>
                  <div class="field"><label class="fine">Minutes</label><input name="duration_minutes" type="number" min="5" max="720" step="1" required value="<?= (int)$service['duration_minutes'] ?>"></div>
                </div>
              </form>
            </td>
            <td><div class="actions"><button class="primary" type="submit" form="edit-<?= (int)$service['id'] ?>">Save changes</button>
              <form class="actionform" method="post" action="services.php"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$service['id'] ?>"><button class="<?= (int)$service['is_active'] === 1 ? 'soft' : 'dark' ?>" type="submit"><?= (int)$service['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <p class="fine">Only administrators can access this page. All changes are validated on the server and protected with a CSRF token.</p>
  </section>
</main>
</body>
</html>
