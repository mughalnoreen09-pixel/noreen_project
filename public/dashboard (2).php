<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();
$roleNames = ['admin' => 'Administrator', 'receptionist' => 'Receptionist', 'stylist' => 'Stylist'];
$features = [
    'admin' => ['Manage staff accounts', 'Manage services', 'Manage appointments', 'Manage customer records', 'Manage salon settings'],
    'receptionist' => ['Manage customer records', 'Create and update appointments', 'View staff availability'],
    'stylist' => ['View assigned appointments', 'View relevant customer service details', 'Update own appointment status'],
];
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SalonDiva · Dashboard</title>
<style>@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');body{margin:0;padding:2rem;background:linear-gradient(135deg,#ffc2d1,#ff93b0);font:16px/1.5 Outfit,system-ui,sans-serif;color:#2b1520}.wrap{max-width:900px;margin:auto;background:#fff6ec;padding:clamp(1.5rem,4vw,2rem);border-radius:30px;box-shadow:0 22px 70px #8f24452b}.logo{font-size:1.6rem;font-weight:800;color:#c8264f}h1{margin-bottom:.3rem}.role{display:inline-block;background:#ffd45c;border-radius:999px;padding:.3rem .8rem;font-weight:600}li{margin:.5rem 0}button,.linkbtn{border:0;border-radius:999px;background:#2b1520;color:#fff;padding:.75rem 1.2rem;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}.adminlinks{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0}.adminlinks .services{background:#c8264f}.adminlinks .staff{background:#2b1520}.note{color:#806b73;font-size:.95rem}</style></head><body><main class="wrap">
<div class="logo">SalonDiva</div><h1>Welcome, <?= h($user['name']) ?></h1><p class="role"><?= h($roleNames[$user['role']] ?? $user['role']) ?></p><h2>Your workspace</h2><ul><?php foreach ($features[$user['role']] ?? [] as $feature): ?><li><?= h($feature) ?></li><?php endforeach; ?></ul>
<?php if ($user['role'] === 'admin'): ?><div class="adminlinks"><a class="linkbtn staff" href="admin-users.php">Manage staff accounts</a><a class="linkbtn services" href="services.php">Manage services</a></div><?php endif; ?>
<p class="note">This is an evolving starter dashboard. Every protected business action must enforce permissions on the server.</p>
<form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><button type="submit">Log out</button></form>
</main></body></html>
