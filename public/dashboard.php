<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();

$role = (string)($user['role'] ?? '');
$name = (string)($user['name'] ?? 'SalonDiva user');
$roleNames = ['admin' => 'Administrator', 'receptionist' => 'Receptionist', 'stylist' => 'Stylist'];

function sd_dashboard_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$sections = [];
$sections[] = [
    'title' => 'Workspace',
    'items' => [
        ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => '⌂', 'desc' => 'Overview and shortcuts'],
    ],
];

if ($role === 'admin') {
    $sections[] = [
        'title' => 'Salon management',
        'items' => [
            ['label' => 'Staff Accounts', 'href' => 'admin-users.php', 'icon' => '♙', 'desc' => 'Approve and manage staff access'],
            ['label' => 'Staff & Shifts', 'href' => 'staff-management.php', 'icon' => '♧', 'desc' => 'Profiles, schedules, tasks, commissions'],
            ['label' => 'Services', 'href' => 'services.php', 'icon' => '✂', 'desc' => 'Manage salon services and prices'],
            ['label' => 'Customers', 'href' => 'customers.php', 'icon' => '♡', 'desc' => 'Customer records and contact details'],
        ],
    ];
    $sections[] = [
        'title' => 'Appointments & planning',
        'items' => [
            ['label' => 'Appointments', 'href' => 'appointments.php', 'icon' => '▦', 'desc' => 'View, reschedule and update bookings'],
            ['label' => 'Calendar & Shifts', 'href' => 'calendar.php', 'icon' => '▣', 'desc' => 'Monthly schedule and iCal export'],
            ['label' => 'Public Booking Page', 'href' => 'booking.php', 'icon' => '↗', 'desc' => 'Preview the customer booking form'],
        ],
    ];
    $sections[] = [
        'title' => 'Business & finance',
        'items' => [
            ['label' => 'Reports & Analytics', 'href' => 'reports.php', 'icon' => '▤', 'desc' => 'Revenue, popular services and staff performance'],
            ['label' => 'Inventory', 'href' => 'inventory.php', 'icon' => '▣', 'desc' => 'Stock levels, usage and restocking'],
            ['label' => 'Payments & Invoices', 'href' => 'payments.php', 'icon' => '＄', 'desc' => 'Invoices, receipts and payment history'],
            ['label' => 'Notifications & Reminders', 'href' => 'notifications-center.php', 'icon' => '♧', 'desc' => 'Appointment reminders and critical alerts'],
        ],
    ];
} elseif ($role === 'receptionist') {
    $sections[] = [
        'title' => 'Front desk',
        'items' => [
            ['label' => 'Customers', 'href' => 'customers.php', 'icon' => '♡', 'desc' => 'Customer records and contact details'],
            ['label' => 'Appointments', 'href' => 'appointments.php', 'icon' => '▦', 'desc' => 'Manage customer bookings'],
            ['label' => 'Calendar', 'href' => 'calendar.php', 'icon' => '▣', 'desc' => 'Appointments and staff shifts'],
            ['label' => 'Payments & Invoices', 'href' => 'payments.php', 'icon' => '＄', 'desc' => 'Invoices, receipts and balances'],
            ['label' => 'Notifications & Reminders', 'href' => 'notifications-center.php', 'icon' => '♧', 'desc' => 'Upcoming appointments and alerts'],
            ['label' => 'Public Booking Page', 'href' => 'booking.php', 'icon' => '↗', 'desc' => 'Preview customer booking'],
        ],
    ];
} elseif ($role === 'stylist') {
    $sections[] = [
        'title' => 'My work',
        'items' => [
            ['label' => 'My Work & Tasks', 'href' => 'my-work.php', 'icon' => '✓', 'desc' => 'Assigned tasks and shift details'],
            ['label' => 'Appointments', 'href' => 'appointments.php', 'icon' => '▦', 'desc' => 'View your appointments and update outcomes'],
            ['label' => 'Calendar', 'href' => 'calendar.php', 'icon' => '▣', 'desc' => 'Review scheduled appointments and shifts'],
        ],
    ];
}

$cards = [];
foreach ($sections as $section) {
    foreach ($section['items'] as $item) {
        if ($item['href'] !== 'dashboard.php') {
            $cards[] = $item;
        }
    }
}

// Dashboard alerts are best-effort: the page still loads if the notifications migration is missing.
$notificationRows = [];
$notificationProblemCount = 0;
$notificationTableReady = true;
try {
    $pdo = db();
    $countStmt = $pdo->query("SELECT COUNT(*) FROM appointment_notifications WHERE status IN ('failed','skipped')");
    $notificationProblemCount = (int)$countStmt->fetchColumn();
    $recentStmt = $pdo->query("SELECT id, channel, recipient, event_type, status, result_message FROM appointment_notifications ORDER BY id DESC LIMIT 5");
    $notificationRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $notificationTableReady = false;
    error_log('SalonDiva dashboard notifications: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SalonDiva · Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--pink-1:#FFC2D1;--pink-2:#FF93B0;--cream:#FFF6EC;--yellow:#FFD45C;--crimson:#C8264F;--ink:#2B1520;--muted:#6E4A57;--line:#F0D5C4;--rose:var(--crimson);--rose-dark:#9F1D3C;--rose-pale:#FFE8EF;--paper:var(--cream);--canvas:var(--cream);--gold:var(--yellow);--sidebar:var(--cream);--sidebar-muted:var(--muted);--shadow:0 16px 44px rgba(43,21,32,.10)}
*{box-sizing:border-box}html{min-height:100%;background:linear-gradient(135deg,var(--pink-1),var(--pink-2)) fixed}body{margin:0;color:var(--ink);font:400 15px/1.5 "Outfit",system-ui,sans-serif;background:linear-gradient(135deg,var(--pink-1),var(--pink-2)) fixed}a{color:inherit;text-decoration:none}a:focus-visible,button:focus-visible{outline:3px solid var(--ink);outline-offset:3px}.app{min-height:100vh;display:grid;grid-template-columns:270px minmax(0,1fr)}
.sidebar{position:sticky;top:0;height:100vh;overflow:auto;background:var(--sidebar);color:var(--ink);border-right:1px solid var(--line);padding:25px 16px 18px;display:flex;flex-direction:column}.brand{display:flex;align-items:center;gap:11px;padding:2px 12px 25px;border-bottom:1px solid var(--line)}.brandmark{width:39px;height:39px;border-radius:13px;background:var(--crimson);display:grid;place-items:center;color:#fff;font-size:22px;font-weight:800}.brandname{font-size:21px;letter-spacing:-.5px;font-weight:800;color:var(--crimson)}.brandtag{font-size:11px;letter-spacing:1.6px;text-transform:uppercase;color:var(--sidebar-muted)}.navgroup{margin-top:20px}.navheading{padding:0 12px;margin:0 0 8px;color:var(--muted);font-size:10px;letter-spacing:1.5px;text-transform:uppercase;font-weight:800}.navlink{display:flex;align-items:center;gap:11px;min-height:43px;padding:9px 11px;border-radius:11px;margin:3px 0;color:var(--ink);font-size:13px;font-weight:600;transition:background .15s ease,color .15s ease}.navlink:hover{background:var(--pink-1);color:var(--ink)}.navlink.active{background:var(--crimson);box-shadow:0 6px 16px rgba(200,38,79,.22);color:#fff}.navicon{width:25px;height:25px;display:grid;place-items:center;flex:0 0 25px;border-radius:8px;background:#ffffff10;font-size:17px}.navlink.active .navicon{background:rgba(255,255,255,.20)}.navfooter{margin-top:auto;padding:18px 9px 0;border-top:1px solid var(--line)}.identity{padding:0 5px 13px}.identityname{font-size:13px;font-weight:700;overflow-wrap:anywhere}.identityrole{font-size:11px;color:var(--sidebar-muted);margin-top:2px}.logout{width:100%;border:1px solid var(--line);color:var(--ink);background:transparent;padding:10px 12px;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer}.logout:hover{background:var(--pink-1)}
.main{min-width:0;padding:30px clamp(18px,3vw,42px) 44px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:26px}.eyebrow{font-size:11px;color:var(--rose);letter-spacing:1.5px;font-weight:800;text-transform:uppercase}.welcome{font-size:clamp(25px,3vw,34px);line-height:1.15;letter-spacing:-1.15px;margin:5px 0 7px}.subtitle{color:var(--muted);margin:0;max-width:670px}.topmeta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}.rolebadge{white-space:nowrap;padding:8px 12px;border-radius:999px;background:var(--cream);border:1px solid var(--line);font-size:11px;color:var(--rose-dark);font-weight:800}.hero{position:relative;overflow:hidden;background:linear-gradient(120deg,var(--crimson),var(--ink));border-radius:22px;color:#fff;padding:26px clamp(20px,3vw,34px);box-shadow:var(--shadow);margin-bottom:27px}.hero:after{content:'S';position:absolute;right:35px;top:-51px;font-size:200px;font-weight:900;color:#ffffff12;line-height:1;pointer-events:none}.herotag{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--pink-1);font-weight:800}.hero h2{position:relative;z-index:1;font-size:clamp(22px,2.5vw,30px);line-height:1.2;letter-spacing:-.6px;margin:7px 0 8px;max-width:640px}.hero p{position:relative;z-index:1;color:var(--cream);margin:0;max-width:650px;font-size:13px}.sectionhead{display:flex;align-items:end;justify-content:space-between;gap:14px;margin:0 0 13px}.sectionhead h2{margin:0;font-size:19px;letter-spacing:-.4px}.sectionhead p{margin:4px 0 0;color:var(--muted);font-size:12px}.count{background:var(--pink-1);color:var(--ink);padding:4px 8px;border-radius:999px;font-weight:800;font-size:11px;white-space:nowrap}.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.module{background:var(--cream);border:1px solid var(--line);border-radius:17px;padding:18px;min-height:157px;box-shadow:0 5px 18px rgba(85,29,55,.035);display:flex;flex-direction:column;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}.module:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--crimson)}.moduletop{display:flex;align-items:center;justify-content:space-between;gap:10px}.moduleicon{width:40px;height:40px;border-radius:13px;background:var(--pink-1);color:var(--crimson);display:grid;place-items:center;font-size:20px;font-weight:800}.arrow{font-size:18px;color:var(--crimson)}.moduletitle{font-size:14px;font-weight:800;margin:13px 0 4px}.moduledesc{font-size:12px;line-height:1.5;color:var(--muted);margin:0}.modulebottom{margin-top:auto;padding-top:12px;color:var(--rose-dark);font-size:11px;font-weight:800}.quickrow{margin-top:24px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.quicklabel{color:var(--muted);font-size:12px;font-weight:700;margin-right:2px}.quicklink{background:var(--cream);border:1px solid var(--line);border-radius:999px;padding:9px 13px;font-size:12px;font-weight:700}.quicklink:hover{border-color:var(--crimson);background:var(--pink-1)}.footer{padding-top:28px;color:var(--muted);font-size:11px}.mobilehead{display:none}
.booking-cta{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 24px;padding:18px 20px;border:1px solid #e8b3c3;border-radius:18px;background:linear-gradient(110deg,#fff7ec,#ffe5ec)}.booking-cta strong{display:block;font-size:15px}.booking-cta p{margin:4px 0 0;color:var(--muted);font-size:12px}.booking-cta a{display:inline-flex;align-items:center;justify-content:center;background:var(--crimson);color:#fff;padding:12px 17px;border-radius:999px;font-size:12px;font-weight:800;white-space:nowrap}.alerts-panel{background:var(--cream);border:1px solid var(--line);border-radius:18px;padding:20px;margin:0 0 25px;box-shadow:0 5px 18px rgba(85,29,55,.035)}.alerts-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:13px}.alerts-head h2{font-size:19px;margin:0}.alerts-head p{font-size:12px;color:var(--muted);margin:4px 0 0}.alert-count{font-size:11px;font-weight:800;padding:6px 10px;border-radius:999px;background:#ffe2e8;color:#9b1637;white-space:nowrap}.alert-count.ok{background:#e4f5e8;color:#236238}.alert-banner{padding:13px 14px;border-radius:13px;background:#fff0f2;border:1px solid #f0b8c6;color:#8b1738;font-size:12px;line-height:1.65;margin-bottom:13px}.alert-banner.warn{background:#fff6df;border-color:#efd58b;color:#6c5115}.alert-banner.ok{background:#edf8ef;border-color:#bfe4c6;color:#245d34}.notification-list{display:grid;gap:9px}.notification-row{display:grid;grid-template-columns:70px minmax(0,1fr) auto;align-items:start;gap:11px;padding:12px 13px;border:1px solid var(--line);border-radius:12px;background:#fffaf6}.notification-channel{font-size:10px;text-transform:uppercase;letter-spacing:.7px;font-weight:800;color:var(--rose-dark);padding-top:2px}.notification-copy{min-width:0}.notification-copy strong{display:block;font-size:12px}.notification-copy span{display:block;color:var(--muted);font-size:11px;line-height:1.55;overflow-wrap:anywhere;margin-top:3px}.status-pill{font-size:10px;font-weight:800;text-transform:uppercase;padding:5px 8px;border-radius:999px;background:#e5f5e9;color:#28643a}.status-pill.failed,.status-pill.skipped{background:#ffe2e8;color:#9b1637}.status-pill.pending{background:#fff0c9;color:#79570b}.alert-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.alert-actions a{font-size:11px;font-weight:800;border:1px solid var(--line);border-radius:999px;padding:9px 12px;background:#fff}.alert-actions a:first-child{background:var(--crimson);border-color:var(--crimson);color:#fff}
@media(max-width:1100px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.app{grid-template-columns:245px minmax(0,1fr)}}@media(max-width:760px){.app{display:block}.sidebar{position:relative;width:100%;height:auto;padding:14px}.brand{padding:2px 7px 14px}.navgroup{margin-top:13px}.navheading{padding-left:7px}.sidebar nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3px}.navlink{margin:0;font-size:12px;padding:8px}.navfooter{margin-top:15px;padding-top:12px}.main{padding:20px 14px 32px}.topbar{align-items:flex-start}.hero{padding:22px 19px}.hero:after{right:5px}.cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.module{padding:14px;min-height:160px}.welcome{font-size:27px}}@media(max-width:430px){.cards{grid-template-columns:1fr}.module{min-height:140px}.topbar{display:block}.topmeta{justify-content:flex-start;margin-top:12px}.sidebar nav{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" aria-label="Main navigation">
  <a class="brand" href="dashboard.php" aria-label="SalonDiva dashboard">
    <span class="brandmark">S</span><span><span class="brandname">SalonDiva</span><br><span class="brandtag">Salon management</span></span>
  </a>
  <nav>
  <?php foreach ($sections as $section): ?>
    <div class="navgroup">
      <h2 class="navheading"><?= sd_dashboard_h($section['title']) ?></h2>
      <?php foreach ($section['items'] as $item): ?>
        <a class="navlink<?= $item['href'] === 'dashboard.php' ? ' active' : '' ?>" href="<?= sd_dashboard_h($item['href']) ?>"<?= $item['href'] === 'dashboard.php' ? ' aria-current="page"' : '' ?>>
          <span class="navicon" aria-hidden="true"><?= sd_dashboard_h($item['icon']) ?></span><span><?= sd_dashboard_h($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </nav>
  <div class="navfooter">
    <div class="identity"><div class="identityname"><?= sd_dashboard_h($name) ?></div><div class="identityrole"><?= sd_dashboard_h($roleNames[$role] ?? $role) ?></div></div>
    <form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= sd_dashboard_h(csrf_token()) ?>"><button type="submit" class="logout">↗ &nbsp; Log out</button></form>
  </div>
</aside>
<main class="main">
  <header class="topbar">
    <div><div class="eyebrow">Your workspace</div><h1 class="welcome">Welcome back, <?= sd_dashboard_h($name) ?>.</h1><p class="subtitle">Everything you need to run the salon, organized in one place.</p></div>
    <div class="topmeta"><span class="rolebadge"><?= sd_dashboard_h($roleNames[$role] ?? ucfirst($role)) ?></span></div>
  </header>
  <section class="hero">
    <div class="herotag">SalonDiva management suite</div>
    <?php if ($role === 'admin'): ?>
      <h2>Run your salon from one clear dashboard.</h2>
      <p>Manage your team, appointments, services, customers, payments, stock, reporting and reminders from the navigation on the left.</p>
    <?php elseif ($role === 'receptionist'): ?>
      <h2>Your front desk, all in one place.</h2>
      <p>Handle customer records, bookings, calendar schedules, payments and appointment reminders.</p>
    <?php else: ?>
      <h2>Your schedule and tasks, in one place.</h2>
      <p>Review your appointments, shifts and assigned work from the navigation on the left.</p>
    <?php endif; ?>
  </section>
  <section class="booking-cta" aria-label="Public booking shortcut">
    <div><strong>Public Booking Page</strong><p>Open the customer booking form directly. This shortcut stays visible above the feature cards.</p></div>
    <a href="booking.php">Open booking page &nbsp; →</a>
  </section>

  <section class="alerts-panel" aria-labelledby="alertsTitle">
    <div class="alerts-head"><div><h2 id="alertsTitle">Emergency & notification centre</h2><p>Recent email/SMS results and anything that needs your attention.</p></div>
      <?php if (!$notificationTableReady): ?><span class="alert-count">Needs setup</span><?php elseif ($notificationProblemCount > 0): ?><span class="alert-count"><?= $notificationProblemCount ?> need attention</span><?php else: ?><span class="alert-count ok">No recorded failures</span><?php endif; ?>
    </div>
    <?php if (!$notificationTableReady): ?>
      <div class="alert-banner warn"><strong>Notification history is unavailable.</strong> The <code>appointment_notifications</code> table may not exist yet. Run the Payments & Notifications migration, then refresh this dashboard. The rest of the dashboard remains available.</div>
    <?php elseif ($notificationProblemCount > 0): ?>
      <div class="alert-banner"><strong>Action required:</strong> <?= $notificationProblemCount ?> notification attempt(s) were marked failed or skipped. Open Notifications & Reminders to review the result details. Skipped usually means email/SMS delivery settings are not configured.</div>
    <?php else: ?>
      <div class="alert-banner ok"><strong>No failed or skipped notification attempts are currently recorded.</strong> This does not prove that email/SMS delivery is configured; check the recent results below after making a test booking.</div>
    <?php endif; ?>
    <?php if ($notificationRows): ?>
      <div class="notification-list">
      <?php foreach ($notificationRows as $notice): ?>
        <div class="notification-row">
          <div class="notification-channel"><?= sd_dashboard_h((string)$notice['channel']) ?></div>
          <div class="notification-copy"><strong><?= sd_dashboard_h(ucfirst((string)$notice['event_type'])) ?> · <?= sd_dashboard_h((string)$notice['recipient']) ?></strong><span><?= sd_dashboard_h((string)($notice['result_message'] ?? 'No delivery detail recorded.')) ?></span></div>
          <span class="status-pill <?= sd_dashboard_h(strtolower((string)$notice['status'])) ?>"><?= sd_dashboard_h((string)$notice['status']) ?></span>
        </div>
      <?php endforeach; ?>
      </div>
    <?php elseif ($notificationTableReady): ?>
      <div class="notification-row"><div class="notification-channel">Status</div><div class="notification-copy"><strong>No notification attempts recorded yet</strong><span>Make a test booking with a customer email/phone, then check this panel. Email/SMS only send when the delivery settings are configured.</span></div><span class="status-pill pending">Waiting</span></div>
    <?php endif; ?>
    <div class="alert-actions"><a href="notifications-center.php">Open notifications →</a><a href="appointments.php">Review appointments</a><a href="booking.php">Open booking page</a></div>
  </section>

  <div class="sectionhead"><div><h2><?= $role === 'admin' ? 'All management features' : 'Your tools' ?></h2><p>Select a feature to open its workspace.</p></div><span class="count"><?= count($cards) ?> modules</span></div>
  <section class="cards" aria-label="Dashboard feature shortcuts">
    <?php foreach ($cards as $card): ?>
      <a class="module" href="<?= sd_dashboard_h($card['href']) ?>">
        <div class="moduletop"><span class="moduleicon" aria-hidden="true"><?= sd_dashboard_h($card['icon']) ?></span><span class="arrow" aria-hidden="true">↗</span></div>
        <div class="moduletitle"><?= sd_dashboard_h($card['label']) ?></div>
        <p class="moduledesc"><?= sd_dashboard_h($card['desc']) ?></p>
        <div class="modulebottom">Open feature →</div>
      </a>
    <?php endforeach; ?>
  </section>
  <?php if ($role === 'admin'): ?>
    <div class="quickrow"><span class="quicklabel">Quick access</span><a class="quicklink" href="appointments.php">View appointments</a><a class="quicklink" href="payments.php">Record a payment</a><a class="quicklink" href="notifications-center.php">Check alerts</a></div>
  <?php endif; ?>
  <footer class="footer">SalonDiva · Salon management workspace</footer>
</main>
</div>
</body>
</html>
