<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$admin = require_role('admin');
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token invalid. Refresh the page and try again.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['account_status'] ?? '');
        if ($id < 1 || !in_array($status, ['approved','rejected'], true)) {
            $error = 'Invalid account action.';
        } else {
            $stmt = db()->prepare("UPDATE users SET account_status = :status WHERE id = :id AND role IN ('receptionist','stylist') AND account_status = 'pending'");
            $stmt->execute(['status' => $status, 'id' => $id]);
            $message = $stmt->rowCount() ? ('Account request ' . $status . '.') : 'Request not found or already processed.';
        }
    }
}
$users = db()->query("SELECT id,name,email,username,role,account_status,created_at FROM users WHERE role IN ('receptionist','stylist') ORDER BY (account_status='pending') DESC, created_at DESC LIMIT 300")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SalonDiva · Staff accounts</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box}body{margin:0;padding:24px;background:linear-gradient(135deg,#ffc2d1,#ff93b0);font:16px/1.5 Outfit,system-ui,sans-serif;color:#2b1520}.wrap{max-width:1050px;margin:auto;background:#fff6ec;padding:clamp(20px,4vw,38px);border-radius:28px;box-shadow:0 22px 70px #8f24452b}.brand{font-size:1.6rem;font-weight:800;color:#c8264f}h1{margin-bottom:4px}.muted{color:#806b73}.notice{padding:12px 14px;border-radius:12px;background:#e5f7e9;margin:12px 0}.error{background:#ffe2e6}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #ead9dc;vertical-align:top}th{font-size:.82rem;color:#806b73} .pill{display:inline-block;padding:3px 10px;border-radius:999px;background:#ffedbc;font-size:.82rem;font-weight:700}.approved{background:#d9f3df}.rejected{background:#ffe0e5}.actions{display:flex;gap:8px;flex-wrap:wrap}button,.back{border:0;border-radius:999px;padding:8px 12px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.approve{background:#267447;color:white}.reject{background:#a31e40;color:white}.back{background:#2b1520;color:white;margin-top:18px}.empty{padding:20px;background:#fff;border-radius:14px}@media(max-width:720px){.table-wrap{overflow-x:auto}table{min-width:700px}}
</style></head><body><main class="wrap"><div class="brand">SalonDiva</div><h1>Staff accounts</h1><p class="muted">Review new receptionist and stylist account requests. Only approved users can sign in.</p>
<?php if ($message): ?><div class="notice"><?= h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
<?php if (!$users): ?><div class="empty">No staff accounts yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email / username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td><?= h($u['name']) ?></td><td><?= h($u['email']) ?><br><small><?= h($u['username'] ?? '') ?></small></td><td><?= h(ucfirst($u['role'])) ?></td><td><span class="pill <?= h($u['account_status']) ?>"><?= h(ucfirst($u['account_status'])) ?></span></td><td><?= h($u['created_at']) ?></td><td><?php if ($u['account_status'] === 'pending'): ?><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="account_status" value="approved"><button class="approve" type="submit">Approve</button></form><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="account_status" value="rejected"><button class="reject" type="submit">Reject</button></form></div><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?><a class="back" href="dashboard.php">Back to dashboard</a></main></body></html>
