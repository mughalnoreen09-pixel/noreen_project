<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();
if (!in_array((string)($user['role'] ?? ''), ['admin', 'receptionist'], true)) {
    http_response_code(403);
    exit('Access denied. Customer management is available to administrators and receptionists only.');
}

function customer_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function customer_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}
function customer_redirect(string $search = ''): never
{
    $url = 'customers.php';
    if ($search !== '') $url .= '?q=' . rawurlencode($search);
    header('Location: ' . $url);
    exit;
}

$error = '';
$flash = (string)($_SESSION['customers_flash'] ?? '');
unset($_SESSION['customers_flash']);
$search = trim((string)($_GET['q'] ?? ''));
if (customer_length($search) > 100) $search = customer_length($search) > 100 ? substr($search, 0, 100) : $search;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postSearch = trim((string)($_POST['q'] ?? ''));
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($action === 'toggle') {
            if (!$id) {
                $error = 'Invalid customer selected.';
            } else {
                $stmt = db()->prepare('UPDATE customers SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $_SESSION['customers_flash'] = $stmt->rowCount() > 0 ? 'Customer status updated.' : 'Customer not found or status unchanged.';
                customer_redirect($postSearch);
            }
        } elseif ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($action === 'update' && !$id) {
                $error = 'Invalid customer selected for editing.';
            } elseif ($name === '' || customer_length($name) > 120) {
                $error = 'Customer name is required and must be 120 characters or fewer.';
            } elseif ($email !== '' && (customer_length($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                $error = 'Enter a valid email address, or leave it blank.';
            } elseif (customer_length($phone) > 40) {
                $error = 'Phone number must be 40 characters or fewer.';
            } elseif ($phone === '' && $email === '') {
                $error = 'Enter a phone number or an email address so the customer can be contacted.';
            } elseif (customer_length($notes) > 2000) {
                $error = 'Notes must be 2,000 characters or fewer.';
            } else {
                $emailValue = $email === '' ? null : $email;
                $phoneValue = $phone === '' ? null : $phone;
                $notesValue = $notes === '' ? null : $notes;
                if ($action === 'create') {
                    $stmt = db()->prepare('INSERT INTO customers (name, email, phone, notes, is_active, created_by) VALUES (:name, :email, :phone, :notes, 1, :created_by)');
                    $stmt->execute(['name' => $name, 'email' => $emailValue, 'phone' => $phoneValue, 'notes' => $notesValue, 'created_by' => (int)$user['id']]);
                    $_SESSION['customers_flash'] = 'Customer added successfully.';
                } else {
                    $stmt = db()->prepare('UPDATE customers SET name = :name, email = :email, phone = :phone, notes = :notes WHERE id = :id');
                    $stmt->execute(['name' => $name, 'email' => $emailValue, 'phone' => $phoneValue, 'notes' => $notesValue, 'id' => (int)$id]);
                    $_SESSION['customers_flash'] = 'Customer details saved.';
                }
                customer_redirect($postSearch);
            }
        } else {
            $error = 'Unknown customer action.';
        }
    }
}

if ($search !== '') {
    $stmt = db()->prepare("SELECT id, name, email, phone, notes, is_active, created_at FROM customers WHERE name LIKE :q OR email LIKE :q OR phone LIKE :q ORDER BY is_active DESC, name ASC, id DESC");
    $stmt->execute(['q' => '%' . $search . '%']);
    $customers = $stmt->fetchAll();
} else {
    $customers = db()->query('SELECT id, name, email, phone, notes, is_active, created_at FROM customers ORDER BY is_active DESC, name ASC, id DESC')->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SalonDiva · Customer management</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
:root{--pink-1:#ffc2d1;--pink-2:#ff93b0;--cream:#fff6ec;--crimson:#c8264f;--ink:#2b1520;--muted:#806b73;--line:#ead9dc;--white:#fff}
*{box-sizing:border-box}body{margin:0;padding:24px;background:linear-gradient(135deg,var(--pink-1),var(--pink-2));font:16px/1.5 Outfit,system-ui,sans-serif;color:var(--ink)}.wrap{max-width:1180px;margin:auto}.panel{background:var(--cream);padding:clamp(20px,4vw,36px);border-radius:28px;box-shadow:0 22px 70px #8f24452b;margin-bottom:20px}.brand{font-size:1.55rem;font-weight:800;color:var(--crimson)}h1{font-size:clamp(1.8rem,4vw,2.5rem);margin:.3rem 0}.muted{color:var(--muted);margin-top:0}.top{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.notice{padding:12px 14px;border-radius:13px;background:#dff4e5;margin:12px 0}.error{background:#ffe2e6;color:#8e1633}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.field label{display:block;font-size:.9rem;font-weight:600;color:var(--muted);margin-bottom:5px}.field input,.field textarea{width:100%;border:1px solid #e5cbd2;border-radius:13px;padding:12px 13px;font:inherit;background:#fff;color:var(--ink)}.field textarea{min-height:88px;resize:vertical}.full{grid-column:1/-1}button,.linkbtn{border:0;border-radius:999px;padding:10px 16px;font:600 .95rem Outfit,system-ui,sans-serif;cursor:pointer;text-decoration:none;display:inline-flex;justify-content:center;align-items:center}.primary{background:var(--crimson);color:white}.dark{background:var(--ink);color:#fff}.soft{background:#ffe5ed;color:var(--ink)}.searchrow{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.searchrow input{flex:1;min-width:200px;border:1px solid #e5cbd2;border-radius:999px;padding:11px 15px;font:inherit}.status{display:inline-block;border-radius:999px;padding:4px 10px;font-size:.8rem;font-weight:700}.active{background:#d9f3df;color:#1e6038}.inactive{background:#ffe0e5;color:#92213e}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:left;vertical-align:top;border-bottom:1px solid var(--line);padding:12px 9px}th{font-size:.82rem;color:var(--muted)}td input,td textarea{width:100%;border:1px solid #e5cbd2;border-radius:10px;padding:8px;font:inherit}td textarea{min-height:55px}.actions{display:flex;gap:7px;flex-wrap:wrap}.actionform{display:inline}.empty{background:white;border-radius:14px;padding:22px}.fine{font-size:.85rem;color:var(--muted)}.namecell{min-width:170px}.notes{max-width:210px;white-space:pre-wrap;overflow-wrap:anywhere}@media(max-width:700px){body{padding:12px}.grid{grid-template-columns:1fr}.full{grid-column:auto}.panel{border-radius:20px}}
</style></head><body><main class="wrap">
<section class="panel"><div class="top"><div><div class="brand">SalonDiva</div><h1>Customer management</h1><p class="muted">Add customers, keep contact details current, and search customer records.</p></div><a class="linkbtn dark" href="dashboard.php">Back to dashboard</a></div>
<?php if ($flash !== ''): ?><div class="notice"><?= customer_h($flash) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="notice error"><?= customer_h($error) ?></div><?php endif; ?></section>
<section class="panel"><h2>Add a customer</h2><p class="muted">Provide at least a phone number or email address. Notes are optional.</p>
<form method="post" action="customers.php"><input type="hidden" name="_csrf" value="<?= customer_h(csrf_token()) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="q" value="<?= customer_h($search) ?>">
<div class="grid"><div class="field"><label for="new-name">Full name *</label><input id="new-name" name="name" maxlength="120" required placeholder="Customer name"></div><div class="field"><label for="new-phone">Phone number</label><input id="new-phone" name="phone" maxlength="40" autocomplete="tel" placeholder="e.g. +92 300 1234567"></div><div class="field"><label for="new-email">Email address</label><input id="new-email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="Optional email"></div><div class="field"><label for="new-notes">Notes</label><textarea id="new-notes" name="notes" maxlength="2000" placeholder="Optional customer notes"></textarea></div></div><p><button class="primary" type="submit">Add customer</button></p></form></section>
<section class="panel"><div class="top"><div><h2>Customer records</h2><p class="muted">Edit a row and press Save changes. Deactivate rather than deleting a record.</p></div><span class="status active"><?= count($customers) ?> shown</span></div>
<form method="get" action="customers.php" class="searchrow"><input name="q" value="<?= customer_h($search) ?>" maxlength="100" placeholder="Search by name, email, or phone"><button class="dark" type="submit">Search</button><?php if ($search !== ''): ?><a class="linkbtn soft" href="customers.php">Clear</a><?php endif; ?></form><br>
<?php if (!$customers): ?><div class="empty"><?= $search !== '' ? 'No customers match your search.' : 'No customers yet. Add the first customer using the form above.' ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Status</th><th>Name</th><th>Phone</th><th>Email</th><th>Notes</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($customers as $customer): $formId = 'edit-' . (int)$customer['id']; ?><tr><td><span class="status <?= (int)$customer['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$customer['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td colspan="4"><form id="<?= customer_h($formId) ?>" method="post" action="customers.php" style="display:grid;grid-template-columns:1.1fr 1fr 1.2fr 1.4fr;gap:8px"><input type="hidden" name="_csrf" value="<?= customer_h(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$customer['id'] ?>"><input type="hidden" name="q" value="<?= customer_h($search) ?>"><div><label class="fine">Name</label><input name="name" maxlength="120" required value="<?= customer_h((string)$customer['name']) ?>"></div><div><label class="fine">Phone</label><input name="phone" maxlength="40" value="<?= customer_h((string)($customer['phone'] ?? '')) ?>"></div><div><label class="fine">Email</label><input name="email" type="email" maxlength="254" value="<?= customer_h((string)($customer['email'] ?? '')) ?>"></div><div><label class="fine">Notes</label><textarea name="notes" maxlength="2000"><?= customer_h((string)($customer['notes'] ?? '')) ?></textarea></div></form></td><td><div class="actions"><button class="primary" type="submit" form="<?= customer_h($formId) ?>">Save changes</button><form class="actionform" method="post" action="customers.php"><input type="hidden" name="_csrf" value="<?= customer_h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$customer['id'] ?>"><input type="hidden" name="q" value="<?= customer_h($search) ?>"><button class="<?= (int)$customer['is_active'] === 1 ? 'soft' : 'dark' ?>" type="submit"><?= (int)$customer['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<p class="fine">Administrators and receptionists can manage customer records. Stylists are denied access to this page. All changes use server-side validation and CSRF protection.</p></section></main></body></html>
