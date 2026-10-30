<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role('admin', 'receptionist', 'stylist');
$pdo = db();
$tz = new DateTimeZone('Asia/Karachi');

function sd_cal_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sd_cal_valid_month(string $v): bool { return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) === 1; }
function sd_cal_utc(string $v): string { return (new DateTimeImmutable($v, new DateTimeZone('Asia/Karachi')))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\\THis\\Z'); }
function sd_cal_ics_escape(string $v): string { return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $v); }
function sd_cal_google_url(string $title, string $start, string $end, string $details): string {
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($title)
        . '&dates=' . sd_cal_utc($start) . '/' . sd_cal_utc($end)
        . '&details=' . rawurlencode($details) . '&location=' . rawurlencode('SalonDiva');
}
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!sd_cal_valid_month($month)) $month = date('Y-m');
$first = new DateTimeImmutable($month . '-01 00:00:00', $tz);
$next = $first->modify('first day of next month');
$startSql = $first->format('Y-m-d H:i:s'); $endSql = $next->format('Y-m-d H:i:s');

try {
    $sql = "SELECT a.*, c.name AS customer_name, u.name AS stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.starts_at>=? AND a.starts_at<?";
    $args = [$startSql, $endSql];
    if ($user['role'] === 'stylist') { $sql .= ' AND a.stylist_user_id=?'; $args[] = (int)$user['id']; }
    $sql .= ' ORDER BY a.starts_at';
    $stmt = $pdo->prepare($sql); $stmt->execute($args); $appointments = $stmt->fetchAll();
    $shiftSql = "SELECT s.*,u.name AS staff_name,u.role AS staff_role FROM staff_shifts s JOIN users u ON u.id=s.staff_user_id WHERE s.shift_date>=? AND s.shift_date<?";
    $shiftArgs = [$first->format('Y-m-d'), $next->format('Y-m-d')];
    if ($user['role'] === 'stylist') { $shiftSql .= ' AND s.staff_user_id=?'; $shiftArgs[] = (int)$user['id']; }
    $shiftSql .= ' ORDER BY s.shift_date,s.start_time';
    $stmt = $pdo->prepare($shiftSql); $stmt->execute($shiftArgs); $shifts = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('SalonDiva calendar error: ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><h1>Calendar setup is incomplete</h1><p>Run the Staff Operations and Appointment Booking migrations first, then install the Calendar & Analytics migration.</p>';
    exit;
}

$eventsByDay = [];
$icsEvents = [];
foreach ($appointments as $a) {
    $date = substr((string)$a['starts_at'], 0, 10);
    $status = (string)$a['status'];
    $title = (string)$a['customer_name'] . ' · ' . (string)$a['service_name_snapshot'];
    $details = 'Customer: ' . (string)$a['customer_name'] . "\nService: " . (string)$a['service_name_snapshot'] . "\nStylist: " . (string)$a['stylist_name'] . "\nBooking code: " . (string)$a['booking_code'] . "\nStatus: " . $status;
    $event = ['kind'=>'appointment','title'=>$title,'meta'=>(string)$a['stylist_name'].' · PKR '.number_format((float)$a['service_price_snapshot'],2),'start'=>(string)$a['starts_at'],'end'=>(string)$a['ends_at'],'status'=>$status,'google'=>sd_cal_google_url('SalonDiva: '.$title,(string)$a['starts_at'],(string)$a['ends_at'],$details)];
    $eventsByDay[$date][] = $event;
    if ($status !== 'cancelled') $icsEvents[] = ['uid'=>'salondiva-appt-'.(int)$a['id'].'@salondiva.local','title'=>$title,'start'=>(string)$a['starts_at'],'end'=>(string)$a['ends_at'],'details'=>$details];
}
foreach ($shifts as $s) {
    $date = (string)$s['shift_date'];
    $start = $date . ' ' . substr((string)$s['start_time'],0,8);
    $end = $date . ' ' . substr((string)$s['end_time'],0,8);
    $title = 'Shift · ' . (string)$s['staff_name'];
    $details = 'Staff shift for ' . (string)$s['staff_name'] . "\nRole: " . (string)$s['staff_role'] . "\nStatus: " . (string)$s['status'] . ((string)($s['location'] ?? '') !== '' ? "\nLocation: " . (string)$s['location'] : '');
    $eventsByDay[$date][] = ['kind'=>'shift','title'=>$title,'meta'=>substr((string)$s['start_time'],0,5).'–'.substr((string)$s['end_time'],0,5).' · '.(string)$s['staff_role'],'start'=>$start,'end'=>$end,'status'=>(string)$s['status'],'google'=>sd_cal_google_url('SalonDiva: '.$title,$start,$end,$details)];
    if ((string)$s['status'] !== 'cancelled') $icsEvents[] = ['uid'=>'salondiva-shift-'.(int)$s['id'].'@salondiva.local','title'=>$title,'start'=>$start,'end'=>$end,'details'=>$details];
}
foreach ($eventsByDay as &$dayEvents) usort($dayEvents, fn($a,$b) => strcmp($a['start'],$b['start'])); unset($dayEvents);

if (($_GET['export'] ?? '') === 'ics') {
    $stamp = gmdate('Ymd\\THis\\Z');
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="salondiva-calendar-' . $month . '.ics"');
    header('Cache-Control: no-store');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//SalonDiva//Calendar Export//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
    foreach ($icsEvents as $event) {
        echo "BEGIN:VEVENT\r\nUID:" . $event['uid'] . "\r\nDTSTAMP:" . $stamp . "\r\nDTSTART:" . sd_cal_utc($event['start']) . "\r\nDTEND:" . sd_cal_utc($event['end']) . "\r\nSUMMARY:" . sd_cal_ics_escape($event['title']) . "\r\nDESCRIPTION:" . sd_cal_ics_escape($event['details']) . "\r\nEND:VEVENT\r\n";
    }
    echo "END:VCALENDAR\r\n"; exit;
}

$prev = $first->modify('-1 month')->format('Y-m'); $nextMonth = $first->modify('+1 month')->format('Y-m');
$today = date('Y-m-d'); $offset = (int)$first->format('N') - 1; $days = (int)$first->format('t'); $weeks = (int)ceil(($offset + $days) / 7);
function sd_cal_nav(string $label, string $url): string { return '<a class="btn light" href="'.sd_cal_h($url).'">'.sd_cal_h($label).'</a>'; }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendar · SalonDiva</title>
<style>
:root{--ink:#321d2a;--pink:#c52f63;--bg:#fff6f9;--line:#ead8e1;--muted:#806c77;--cream:#fffdf9;--green:#e3f5e8;--red:#fbe4e8;--gold:#fff1ce}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:1400px;margin:24px auto;padding:0 14px}.shell{background:var(--cream);border:1px solid var(--line);border-radius:20px;padding:clamp(12px,2vw,24px);box-shadow:0 12px 45px #6f204015}.top,.actions,.nav{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.brand{color:var(--pink);font-weight:850;font-size:1.2rem}h1{margin:.25rem 0;font-size:clamp(1.4rem,3vw,2rem)}.muted,.hint{color:var(--muted)}.actions{justify-content:flex-start;margin:16px 0}.btn{display:inline-block;border:0;border-radius:9px;background:var(--pink);color:white;text-decoration:none;padding:9px 12px;font-weight:700}.btn.light{background:#f5e5ec;color:var(--ink)}.monthbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:16px 0}.monthbar strong{font-size:1.2rem}.grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}.weekday{font-weight:750;text-align:center;padding:8px 2px;background:#f7e7ee;border-radius:7px}.day{min-height:132px;border:1px solid var(--line);border-radius:9px;padding:7px;background:#fff;min-width:0}.day.empty{background:#fbf6f8;border-style:dashed}.day.today{border:2px solid var(--pink)}.daynum{font-weight:800;margin-bottom:5px}.event{display:block;border-radius:7px;padding:5px 6px;margin:4px 0;background:#f8e9ef;font-size:.76rem;overflow-wrap:anywhere}.event.shift{background:#e5f3e9}.event.cancelled{opacity:.55;text-decoration:line-through}.event.completed{background:#dcecfb}.event a{font-weight:750;color:#872044}.event small{display:block;color:#65505c;margin-top:2px}.legend{display:flex;gap:12px;flex-wrap:wrap;color:var(--muted);font-size:.85rem;margin:12px 0}.dot{display:inline-block;width:10px;height:10px;border-radius:3px;background:#f8e9ef;margin-right:5px}.dot.shift{background:#e5f3e9}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:640px}td,th{padding:9px;text-align:left;border-bottom:1px solid var(--line)}@media(max-width:760px){.grid{gap:3px}.day{padding:3px;min-height:78px}.event{font-size:.67rem;padding:3px}.event small{display:none}.weekday{font-size:.72rem}.daynum{font-size:.8rem}}
</style></head><body><main class="wrap"><section class="shell"><div class="top"><div><div class="brand">SalonDiva</div><h1>Scheduling calendar</h1><div class="muted">Appointments and staff shifts · Pakistan Standard Time (Asia/Karachi)</div></div><div class="hint">Signed in as <?=sd_cal_h((string)$user['name'])?> (<?=sd_cal_h((string)$user['role'])?>)</div></div>
<div class="actions"><a class="btn light" href="dashboard.php">Dashboard</a> <a class="btn light" href="appointments.php">Manage appointments</a><?php if($user['role']==='admin'): ?><a class="btn light" href="staff-management.php">Manage staff shifts</a><a class="btn light" href="reports.php">Reports & analytics</a><?php endif; ?><a class="btn" href="calendar.php?month=<?=sd_cal_h($month)?>&amp;export=ics">Export month to iCal (.ics)</a></div>
<div class="monthbar"><?=sd_cal_nav('← Previous', 'calendar.php?month='.$prev)?><strong><?=sd_cal_h($first->format('F Y'))?></strong><?=sd_cal_nav('Next →', 'calendar.php?month='.$nextMonth)?></div>
<div class="grid" role="grid" aria-label="Monthly calendar"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $w): ?><div class="weekday"><?=sd_cal_h($w)?></div><?php endforeach; ?>
<?php for($cell=0;$cell<$weeks*7;$cell++): $n=$cell-$offset+1; if($n<1||$n>$days): ?><div class="day empty"></div><?php else: $date=$first->modify('+'.($n-1).' days')->format('Y-m-d'); ?><div class="day<?=$date===$today?' today':''?>"><div class="daynum"><?=$n?><?=isset($eventsByDay[$date])?' <span class="muted">· '.count($eventsByDay[$date]).'</span>':''?></div><?php foreach(($eventsByDay[$date]??[]) as $ev): $cls=$ev['kind']==='shift'?'shift':($ev['status']==='cancelled'?'cancelled':($ev['status']==='completed'?'completed':'')); ?><div class="event <?=$cls?>"><strong><?=sd_cal_h(substr((string)$ev['start'],11,5))?> <?=sd_cal_h((string)$ev['title'])?></strong><small><?=sd_cal_h((string)$ev['meta'])?><?= $ev['kind']==='appointment' ? ' · '.sd_cal_h((string)$ev['status']) : '' ?></small><?php if($ev['kind']==='appointment' && $ev['status']!=='cancelled'): ?><a target="_blank" rel="noopener" href="<?=sd_cal_h((string)$ev['google'])?>">+ Google Calendar</a><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?><?php endfor; ?></div>
<div class="legend"><span><span class="dot"></span>Appointments</span><span><span class="dot shift"></span>Staff shifts</span><span>Outlined date = today</span></div>
<p class="hint">Calendar export is a one-way download that can be imported into Google Calendar or Apple Calendar/iCal. Google Calendar links create individual events. This starter update does not perform automatic two-way synchronization; that requires Google OAuth/API setup.</p>
</section></main></body></html>
