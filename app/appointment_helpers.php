<?php
declare(strict_types=1);

function sd_appt_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sd_appt_valid_date(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date); $e = DateTime::getLastErrors();
    return $d !== false && $d->format('Y-m-d') === $date && (!is_array($e) || ($e['warning_count'] === 0 && $e['error_count'] === 0));
}
function sd_appt_valid_datetime_local(string $value): ?DateTimeImmutable {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value); $e = DateTimeImmutable::getLastErrors();
    if (!$dt || (is_array($e) && ($e['warning_count'] > 0 || $e['error_count'] > 0)) || $dt->format('Y-m-d\TH:i') !== $value) return null;
    return $dt;
}
function sd_appt_service(PDO $pdo, int $serviceId): ?array {
    $s=$pdo->prepare('SELECT id,name,price,duration_minutes FROM services WHERE id=? AND is_active=1'); $s->execute([$serviceId]); $r=$s->fetch(); return $r ?: null;
}
function sd_appt_stylists(PDO $pdo, int $serviceId): array {
    $q=$pdo->prepare("SELECT u.id,u.name FROM users u JOIN stylist_services ss ON ss.staff_user_id=u.id AND ss.is_active=1 JOIN staff_weekly_schedule ws ON ws.staff_user_id=u.id AND ws.is_day_off=0 WHERE ss.service_id=? AND u.role='stylist' AND u.is_active=1 AND u.account_status='approved' GROUP BY u.id,u.name ORDER BY u.name");
    $q->execute([$serviceId]); return $q->fetchAll();
}
function sd_appt_slots(PDO $pdo, int $serviceId, int $stylistId, string $date, ?int $excludeAppointmentId=null): array {
    if (!sd_appt_valid_date($date)) return [];
    $service=sd_appt_service($pdo,$serviceId); if (!$service) return [];
    $day=(int)(new DateTimeImmutable($date))->format('N');
    $check=$pdo->prepare("SELECT u.id FROM users u JOIN stylist_services ss ON ss.staff_user_id=u.id AND ss.is_active=1 AND ss.service_id=? WHERE u.id=? AND u.role='stylist' AND u.is_active=1 AND u.account_status='approved'");
    $check->execute([$serviceId,$stylistId]); if (!$check->fetch()) return [];
    $sch=$pdo->prepare('SELECT is_day_off,start_time,end_time FROM staff_weekly_schedule WHERE staff_user_id=? AND day_of_week=?'); $sch->execute([$stylistId,$day]); $schedule=$sch->fetch();
    if (!$schedule || (int)$schedule['is_day_off']===1 || empty($schedule['start_time']) || empty($schedule['end_time'])) return [];
    $start=new DateTimeImmutable($date.' '.substr((string)$schedule['start_time'],0,8));
    $end=new DateTimeImmutable($date.' '.substr((string)$schedule['end_time'],0,8));
    $duration=(int)$service['duration_minutes']; if ($duration<1 || $duration>720) return [];
    $now=new DateTimeImmutable('now'); $slots=[];
    for ($slot=$start; $slot->modify('+'.$duration.' minutes') <= $end; $slot=$slot->modify('+15 minutes')) {
        $slotEnd=$slot->modify('+'.$duration.' minutes');
        if ($slot <= $now) continue;
        $sql="SELECT COUNT(*) FROM appointments WHERE stylist_user_id=? AND status IN ('confirmed') AND starts_at < ? AND ends_at > ?";
        $args=[$stylistId,$slotEnd->format('Y-m-d H:i:s'),$slot->format('Y-m-d H:i:s')];
        if ($excludeAppointmentId !== null) { $sql.=' AND id<>?'; $args[]=$excludeAppointmentId; }
        $busy=$pdo->prepare($sql); $busy->execute($args); if ((int)$busy->fetchColumn()===0) $slots[]=$slot->format('H:i');
    }
    return $slots;
}
function sd_appt_load(PDO $pdo, int $id): ?array {
    $q=$pdo->prepare('SELECT a.*,c.name AS customer_name,c.email AS customer_email,c.phone AS customer_phone,u.name AS stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.id=?');
    $q->execute([$id]); $r=$q->fetch(); return $r ?: null;
}
function sd_appt_notify_safely(PDO $pdo, int $id, string $event, ?string $manageToken=null): void {
    try {
        require_once __DIR__.'/notifications.php';
        $a=sd_appt_load($pdo,$id);
        if ($a) {
            if ($manageToken !== null && preg_match('/^[a-f0-9]{48}$/', $manageToken)) {
                if (getenv('SALONDIVA_PUBLIC_BASE_URL')) {
                    $base = rtrim((string)getenv('SALONDIVA_PUBLIC_BASE_URL'), '/');
                } else {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/')), '/');
                    if (!str_ends_with($dir, '/public')) {
                        $dir = ($dir === '' || $dir === '.') ? '/public' : $dir . '/public';
                    }
                    $base = $scheme . '://' . $host . $dir;
                }
                $a['manage_url'] = $base . '/manage-booking.php?token=' . rawurlencode($manageToken);
            }
            sd_appt_notify($pdo,$id,$event,$a);
        }
    } catch (Throwable $e) {
        error_log('SalonDiva notification error: '.$e->getMessage());
    }
}
function sd_appt_page_start(string $title): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.sd_appt_h($title).' · SalonDiva</title><style>
    :root{--pink:#d84f83;--ink:#322832;--muted:#746b75;--line:#eadfe7;--bg:#fff8fb;--card:#fff;--good:#e7f8ed;--bad:#fff0f0}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}header{background:#fff;border-bottom:1px solid var(--line);padding:15px 20px}header strong{color:var(--pink);font-size:20px}.wrap{max-width:1100px;margin:26px auto;padding:0 16px}.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin:0 0 18px;box-shadow:0 5px 20px #44223b08}h1{font-size:28px;margin:0 0 8px}h2{font-size:20px;margin:0 0 14px}.muted,.hint{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}label{font-weight:650}input,select,textarea{width:100%;border:1px solid #d9ccd5;border-radius:9px;padding:10px 11px;background:#fff;color:var(--ink);font:inherit}textarea{min-height:80px}.btn{display:inline-block;border:0;border-radius:9px;padding:10px 15px;background:var(--pink);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.btn.light{background:#f4e8ef;color:#65344d}.btn.danger{background:#9f2e45}.btn:disabled{opacity:.45;cursor:not-allowed}.alert{padding:12px 14px;border-radius:10px;margin:0 0 14px;background:var(--good)}.alert.error{background:var(--bad)}.slots{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.slot{border:1px solid var(--pink);border-radius:9px;padding:8px 12px;background:#fff;color:#9c2858;cursor:pointer}.slot input{width:auto}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:740px}th,td{text-align:left;padding:11px;border-bottom:1px solid var(--line);vertical-align:top}th{background:#fff6fa}.pill{display:inline-block;padding:3px 8px;border-radius:20px;background:#f4e8ef;font-size:12px}.nav{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.nav a{color:#9c2858}.token{overflow-wrap:anywhere;font-family:ui-monospace,monospace;background:#fff4f8;padding:10px;border-radius:8px}footer{color:var(--muted);font-size:12px;padding:20px;text-align:center}@media(max-width:600px){h1{font-size:23px}.card{padding:15px}}
    </style></head><body><header><strong>SalonDiva</strong> <span class="muted">· '.sd_appt_h($title).'</span></header><main class="wrap">';
}
function sd_appt_page_end(): void { echo '</main><footer>SalonDiva · Appointment details are handled by your salon.</footer></body></html>'; }
