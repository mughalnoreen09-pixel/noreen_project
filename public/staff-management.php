<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$admin = require_role('admin');

function sd_staff_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sd_staff_len(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function sd_staff_redirect(): never { header('Location: staff-management.php'); exit; }
function sd_staff_int(mixed $value): int|false { return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); }
function sd_staff_time_ok(string $value): bool { return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1; }
function sd_staff_datetime(string $value): ?string {
    if ($value === '') return date('Y-m-d H:i:s');
    $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
    $issues = DateTime::getLastErrors();
    if (!$dt || (is_array($issues) && ($issues['warning_count'] > 0 || $issues['error_count'] > 0))) return null;
    return $dt->format('Y-m-d H:i:s');
}
function sd_staff_target(int $id, ?string $onlyRole = null): array {
    $sql = "SELECT id,name,role FROM users WHERE id=:id AND role IN ('receptionist','stylist') AND is_active=1 AND account_status='approved'";
    $stmt = db()->prepare($sql); $stmt->execute(['id' => $id]); $row = $stmt->fetch();
    if (!$row || ($onlyRole !== null && $row['role'] !== $onlyRole)) throw new InvalidArgumentException('Select an active, approved staff member with the required role.');
    return $row;
}
function sd_staff_flash(string $message): never { $_SESSION['staff_management_flash'] = $message; sd_staff_redirect(); }

$error = '';
$flash = (string)($_SESSION['staff_management_flash'] ?? '');
unset($_SESSION['staff_management_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Your security token expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_profile') {
                $staffId = sd_staff_int($_POST['staff_user_id'] ?? null);
                if (!$staffId) throw new InvalidArgumentException('Invalid staff member selected.');
                sd_staff_target((int)$staffId);
                $phone = trim((string)($_POST['phone'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $emergencyName = trim((string)($_POST['emergency_contact_name'] ?? ''));
                $emergencyPhone = trim((string)($_POST['emergency_contact_phone'] ?? ''));
                $notes = trim((string)($_POST['notes'] ?? ''));
                $rateText = trim((string)($_POST['commission_rate'] ?? '0'));
                if (sd_staff_len($phone) > 40 || sd_staff_len($address) > 255 || sd_staff_len($emergencyName) > 120 || sd_staff_len($emergencyPhone) > 40 || sd_staff_len($notes) > 4000) throw new InvalidArgumentException('One or more profile fields exceed the permitted length.');
                if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $rateText) || (float)$rateText < 0 || (float)$rateText > 100) throw new InvalidArgumentException('Commission rate must be between 0 and 100, with up to two decimal places.');
                $stmt = db()->prepare('INSERT INTO staff_profiles (user_id,phone,address,emergency_contact_name,emergency_contact_phone,commission_rate,notes) VALUES (:uid,:phone,:address,:ename,:ephone,:rate,:notes) ON DUPLICATE KEY UPDATE phone=VALUES(phone),address=VALUES(address),emergency_contact_name=VALUES(emergency_contact_name),emergency_contact_phone=VALUES(emergency_contact_phone),commission_rate=VALUES(commission_rate),notes=VALUES(notes)');
                $stmt->execute(['uid'=>(int)$staffId,'phone'=>$phone===''?null:$phone,'address'=>$address===''?null:$address,'ename'=>$emergencyName===''?null:$emergencyName,'ephone'=>$emergencyPhone===''?null:$emergencyPhone,'rate'=>number_format((float)$rateText,2,'.',''),'notes'=>$notes===''?null:$notes]);
                sd_staff_flash('Staff profile saved. New commission entries will use this commission rate; existing entries keep their recorded rate.');
            } elseif ($action === 'save_schedule') {
                $staffId = sd_staff_int($_POST['staff_user_id'] ?? null);
                if (!$staffId) throw new InvalidArgumentException('Invalid staff member selected.');
                sd_staff_target((int)$staffId);
                $days = $_POST['schedule'] ?? [];
                $normalized = [];
                for ($day=1; $day<=7; $day++) {
                    $entry = is_array($days[$day] ?? null) ? $days[$day] : [];
                    $off = isset($entry['off']) && (string)$entry['off'] === '1';
                    $start = trim((string)($entry['start'] ?? '09:00'));
                    $end = trim((string)($entry['end'] ?? '17:00'));
                    if (!$off && (!sd_staff_time_ok($start) || !sd_staff_time_ok($end) || $start >= $end)) throw new InvalidArgumentException('For each working day, enter valid start and end times, with the end later than the start.');
                    $normalized[$day] = [$off ? 1 : 0, $off ? null : $start, $off ? null : $end];
                }
                $stmt = db()->prepare('INSERT INTO staff_weekly_schedule (staff_user_id,day_of_week,is_day_off,start_time,end_time) VALUES (:uid,:day,:off,:start,:end) ON DUPLICATE KEY UPDATE is_day_off=VALUES(is_day_off),start_time=VALUES(start_time),end_time=VALUES(end_time)');
                foreach ($normalized as $day=>$row) $stmt->execute(['uid'=>(int)$staffId,'day'=>$day,'off'=>$row[0],'start'=>$row[1],'end'=>$row[2]]);
                sd_staff_flash('Weekly work schedule saved.');
            } elseif ($action === 'assign_service' || $action === 'unassign_service') {
                $staffId = sd_staff_int($_POST['staff_user_id'] ?? null); $serviceId = sd_staff_int($_POST['service_id'] ?? null);
                if (!$staffId || !$serviceId) throw new InvalidArgumentException('Select a stylist and a service.');
                sd_staff_target((int)$staffId, 'stylist');
                if ($action === 'assign_service') {
                    $check = db()->prepare('SELECT id FROM services WHERE id=:id AND is_active=1'); $check->execute(['id'=>(int)$serviceId]);
                    if (!$check->fetch()) throw new InvalidArgumentException('Select an active service.');
                    $stmt = db()->prepare('INSERT INTO stylist_services (staff_user_id,service_id,is_active,assigned_by) VALUES (:uid,:sid,1,:by) ON DUPLICATE KEY UPDATE is_active=1,assigned_by=VALUES(assigned_by),assigned_at=CURRENT_TIMESTAMP');
                    $stmt->execute(['uid'=>(int)$staffId,'sid'=>(int)$serviceId,'by'=>(int)$admin['id']]);
                    sd_staff_flash('Service assigned to stylist.');
                } else {
                    $stmt = db()->prepare('UPDATE stylist_services SET is_active=0 WHERE staff_user_id=:uid AND service_id=:sid');
                    $stmt->execute(['uid'=>(int)$staffId,'sid'=>(int)$serviceId]);
                    sd_staff_flash('Service assignment removed.');
                }
            } elseif ($action === 'create_shift') {
                $staffId = sd_staff_int($_POST['staff_user_id'] ?? null); if (!$staffId) throw new InvalidArgumentException('Select a staff member.'); sd_staff_target((int)$staffId);
                $date = trim((string)($_POST['shift_date'] ?? '')); $start = trim((string)($_POST['start_time'] ?? '')); $end = trim((string)($_POST['end_time'] ?? ''));
                $d = DateTime::createFromFormat('!Y-m-d', $date); $issues = DateTime::getLastErrors();
                if (!$d || $d->format('Y-m-d') !== $date || (is_array($issues) && ($issues['warning_count']>0 || $issues['error_count']>0))) throw new InvalidArgumentException('Enter a valid shift date.');
                if (!sd_staff_time_ok($start) || !sd_staff_time_ok($end) || $start >= $end) throw new InvalidArgumentException('Shift end time must be later than a valid start time.');
                $location = trim((string)($_POST['location'] ?? '')); $notes = trim((string)($_POST['notes'] ?? ''));
                if (sd_staff_len($location)>120 || sd_staff_len($notes)>1000) throw new InvalidArgumentException('Shift location or notes are too long.');
                $stmt = db()->prepare('INSERT INTO staff_shifts (staff_user_id,shift_date,start_time,end_time,location,notes,created_by) VALUES (:uid,:date,:start,:end,:location,:notes,:creator)');
                $stmt->execute(['uid'=>(int)$staffId,'date'=>$date,'start'=>$start,'end'=>$end,'location'=>$location===''?null:$location,'notes'=>$notes===''?null:$notes,'creator'=>(int)$admin['id']]);
                sd_staff_flash('Shift assigned successfully.');
            } elseif ($action === 'create_task') {
                $staffId = sd_staff_int($_POST['assigned_to'] ?? null); if (!$staffId) throw new InvalidArgumentException('Select a staff member.'); sd_staff_target((int)$staffId);
                $title = trim((string)($_POST['title'] ?? '')); $description = trim((string)($_POST['description'] ?? '')); $priority = (string)($_POST['priority'] ?? 'normal');
                $due = trim((string)($_POST['due_at'] ?? '')); $dueSql = $due===''?null:sd_staff_datetime($due);
                if ($title==='' || sd_staff_len($title)>160) throw new InvalidArgumentException('Task title is required and must be 160 characters or fewer.');
                if (sd_staff_len($description)>4000) throw new InvalidArgumentException('Task description is too long.');
                if (!in_array($priority,['low','normal','high'],true)) throw new InvalidArgumentException('Select a valid priority.');
                if ($due!=='' && $dueSql===null) throw new InvalidArgumentException('Enter a valid task due date and time.');
                $stmt = db()->prepare('INSERT INTO staff_tasks (assigned_to,title,description,due_at,priority,created_by) VALUES (:uid,:title,:description,:due,:priority,:creator)');
                $stmt->execute(['uid'=>(int)$staffId,'title'=>$title,'description'=>$description===''?null:$description,'due'=>$dueSql,'priority'=>$priority,'creator'=>(int)$admin['id']]);
                sd_staff_flash('Task assigned successfully.');
            } elseif ($action === 'task_status') {
                $taskId = sd_staff_int($_POST['task_id'] ?? null); $status = (string)($_POST['status'] ?? '');
                if (!$taskId || !in_array($status,['pending','in_progress','completed','cancelled'],true)) throw new InvalidArgumentException('Select a valid task and status.');
                $stmt = db()->prepare('UPDATE staff_tasks SET status=:status, completed_at=:completed WHERE id=:id');
                $stmt->execute(['status'=>$status,'completed'=>$status==='completed'?date('Y-m-d H:i:s'):null,'id'=>(int)$taskId]);
                sd_staff_flash($stmt->rowCount()>0?'Task status updated.':'Task not found or status unchanged.');
            } elseif ($action === 'record_commission') {
                $staffId = sd_staff_int($_POST['stylist_user_id'] ?? null); $serviceId = sd_staff_int($_POST['service_id'] ?? null);
                if (!$staffId || !$serviceId) throw new InvalidArgumentException('Choose a stylist and service.');
                sd_staff_target((int)$staffId,'stylist');
                $serviceStmt = db()->prepare('SELECT id,name,price FROM services WHERE id=:id AND is_active=1'); $serviceStmt->execute(['id'=>(int)$serviceId]); $service = $serviceStmt->fetch();
                if (!$service) throw new InvalidArgumentException('Choose an active service.');
                $priceText = trim((string)($_POST['service_price'] ?? (string)$service['price']));
                if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/',$priceText) || (float)$priceText<0 || (float)$priceText>99999999.99) throw new InvalidArgumentException('Enter a valid performed-service price with up to two decimal places.');
                $performedInput = trim((string)($_POST['performed_at'] ?? '')); $performedAt = sd_staff_datetime($performedInput);
                if ($performedInput!=='' && $performedAt===null) throw new InvalidArgumentException('Enter a valid performance date and time.');
                $customerRaw = trim((string)($_POST['customer_id'] ?? '')); $customerId = null;
                if ($customerRaw!=='') {
                    $customerId = sd_staff_int($customerRaw); if (!$customerId) throw new InvalidArgumentException('Invalid customer selected.');
                    $c = db()->prepare('SELECT id FROM customers WHERE id=:id AND is_active=1'); $c->execute(['id'=>(int)$customerId]); if (!$c->fetch()) throw new InvalidArgumentException('Select an active customer.');
                }
                $p = db()->prepare('SELECT commission_rate FROM staff_profiles WHERE user_id=:uid'); $p->execute(['uid'=>(int)$staffId]); $profile = $p->fetch();
                $rate = (float)($profile['commission_rate'] ?? 0.00); $price = round((float)$priceText,2); $amount = round($price*$rate/100,2);
                $notes = trim((string)($_POST['commission_notes'] ?? '')); if (sd_staff_len($notes)>1000) throw new InvalidArgumentException('Commission notes are too long.');
                $stmt = db()->prepare('INSERT INTO service_commissions (stylist_user_id,service_id,customer_id,performed_at,service_name_snapshot,service_price,commission_rate,commission_amount,status,notes,created_by) VALUES (:stylist,:service,:customer,:performed,:name,:price,:rate,:amount,\'pending\',:notes,:creator)');
                $stmt->execute(['stylist'=>(int)$staffId,'service'=>(int)$serviceId,'customer'=>$customerId===null?null:(int)$customerId,'performed'=>$performedAt,'name'=>$service['name'],'price'=>number_format($price,2,'.',''),'rate'=>number_format($rate,2,'.',''),'amount'=>number_format($amount,2,'.',''),'notes'=>$notes===''?null:$notes,'creator'=>(int)$admin['id']]);
                sd_staff_flash('Service performance recorded. Commission calculated at '.number_format($rate,2).'% ('.number_format($amount,2).') and set to Pending approval.');
            } elseif ($action === 'commission_status') {
                $commissionId = sd_staff_int($_POST['commission_id'] ?? null); $next = (string)($_POST['status'] ?? '');
                if (!$commissionId || !in_array($next,['approved','paid','void'],true)) throw new InvalidArgumentException('Invalid commission action.');
                $q = db()->prepare('SELECT status FROM service_commissions WHERE id=:id'); $q->execute(['id'=>(int)$commissionId]); $record = $q->fetch();
                if (!$record) throw new InvalidArgumentException('Commission record not found.');
                $current = (string)$record['status'];
                $valid = ($next==='approved' && $current==='pending') || ($next==='paid' && $current==='approved') || ($next==='void' && in_array($current,['pending','approved'],true));
                if (!$valid) throw new InvalidArgumentException('Invalid status transition. Pending commissions can be approved or voided; approved commissions can be paid or voided. Paid/void entries cannot be changed.');
                if ($next==='approved') $stmt = db()->prepare("UPDATE service_commissions SET status='approved',approved_by=:by,approved_at=NOW() WHERE id=:id AND status='pending'");
                elseif ($next==='paid') $stmt = db()->prepare("UPDATE service_commissions SET status='paid',paid_by=:by,paid_at=NOW() WHERE id=:id AND status='approved'");
                else $stmt = db()->prepare("UPDATE service_commissions SET status='void' WHERE id=:id AND status IN ('pending','approved')");
                $params = ['id'=>(int)$commissionId]; if ($next!=='void') $params['by']=(int)$admin['id']; $stmt->execute($params);
                sd_staff_flash('Commission status updated to '.strtoupper($next).'.');
            } else {
                throw new InvalidArgumentException('Unknown staff-management action.');
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('SalonDiva staff management error: '.$e->getMessage());
            $error = 'The change could not be saved. Confirm the Staff Operations database migration is installed, then try again.';
        }
    }
}

$staffStmt = db()->query("SELECT u.id,u.name,u.email,u.username,u.role,u.account_status,p.phone,p.address,p.emergency_contact_name,p.emergency_contact_phone,p.commission_rate,p.notes FROM users u LEFT JOIN staff_profiles p ON p.user_id=u.id WHERE u.role IN ('receptionist','stylist') AND u.is_active=1 AND u.account_status='approved' ORDER BY FIELD(u.role,'stylist','receptionist'),u.name");
$staff = $staffStmt->fetchAll();
$staffById = []; foreach ($staff as $member) $staffById[(int)$member['id']] = $member;
$scheduleMap = [];
$schedRows = db()->query('SELECT staff_user_id,day_of_week,is_day_off,start_time,end_time FROM staff_weekly_schedule')->fetchAll();
foreach ($schedRows as $row) $scheduleMap[(int)$row['staff_user_id']][(int)$row['day_of_week']] = $row;
$services = db()->query('SELECT id,name,price,duration_minutes FROM services WHERE is_active=1 ORDER BY name')->fetchAll();
$customers = db()->query('SELECT id,name,phone,email FROM customers WHERE is_active=1 ORDER BY name LIMIT 500')->fetchAll();
$shifts = db()->query("SELECT s.*,u.name AS staff_name,u.role AS staff_role FROM staff_shifts s JOIN users u ON u.id=s.staff_user_id WHERE u.role IN ('receptionist','stylist') ORDER BY s.shift_date DESC,s.start_time DESC,s.id DESC LIMIT 30")->fetchAll();
$tasks = db()->query("SELECT t.*,u.name AS staff_name,u.role AS staff_role FROM staff_tasks t JOIN users u ON u.id=t.assigned_to ORDER BY FIELD(t.status,'pending','in_progress','completed','cancelled'),t.due_at IS NULL,t.due_at,t.id DESC LIMIT 40")->fetchAll();
$commissions = db()->query("SELECT c.*,u.name AS stylist_name,cu.name AS customer_name FROM service_commissions c JOIN users u ON u.id=c.stylist_user_id LEFT JOIN customers cu ON cu.id=c.customer_id ORDER BY c.performed_at DESC,c.id DESC LIMIT 100")->fetchAll();
$totals = db()->query("SELECT status,COALESCE(SUM(commission_amount),0) AS total,COUNT(*) AS count_rows FROM service_commissions GROUP BY status")->fetchAll();
$commissionSummary = ['pending'=>0.0,'approved'=>0.0,'paid'=>0.0,'void'=>0.0]; $commissionCounts = ['pending'=>0,'approved'=>0,'paid'=>0,'void'=>0];
foreach ($totals as $total) { $commissionSummary[$total['status']] = (float)$total['total']; $commissionCounts[$total['status']] = (int)$total['count_rows']; }
$dayNames = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SalonDiva · Staff Operations</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');
:root{--ink:#2b1520;--pink:#c8264f;--cream:#fff6ec;--muted:#806b73;--line:#eedce2;--green:#176b45;--red:#9e263b}*{box-sizing:border-box}body{margin:0;padding:1.4rem;background:linear-gradient(135deg,#ffc2d1,#ff93b0);font:15px/1.5 Outfit,system-ui,sans-serif;color:var(--ink)}.wrap{max-width:1220px;margin:auto}.shell{background:var(--cream);border-radius:26px;padding:clamp(1rem,3vw,2rem);box-shadow:0 22px 70px #8f24452b}.top{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}.logo{font-size:1.5rem;font-weight:800;color:var(--pink)}h1{margin:.2rem 0}h2{font-size:1.25rem;margin:.1rem 0 1rem}h3{font-size:1.04rem;margin:.2rem 0 .8rem}.muted,.hint{color:var(--muted)}.hint{font-size:.87rem}.section{margin-top:1.2rem;padding:1.1rem;border:1px solid var(--line);border-radius:18px;background:#fffdf9}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:.8rem}.field{display:flex;flex-direction:column;gap:.25rem;margin-bottom:.65rem}.field label{font-weight:600;font-size:.9rem}.field input,.field select,.field textarea,select,input,textarea{max-width:100%;font:inherit}.field input,.field select,.field textarea{width:100%;padding:.65rem .75rem;border:1px solid #dec9d1;border-radius:10px;background:#fff;color:var(--ink)}textarea{min-height:70px;resize:vertical}.btn,button,.link{display:inline-block;border:0;border-radius:999px;background:var(--ink);color:#fff;padding:.62rem .95rem;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}.btn.pink,.pink{background:var(--pink)}.btn.light{background:#f8e2e9;color:var(--ink)}.btn.warn{background:#8e2f46}.link{margin-bottom:.5rem}.notice{padding:.8rem 1rem;border-radius:12px;margin:1rem 0;background:#e8f7ed;color:var(--green)}.error{padding:.8rem 1rem;border-radius:12px;margin:1rem 0;background:#fde8ec;color:var(--red)}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:650px}th,td{text-align:left;padding:.65rem .55rem;vertical-align:top;border-bottom:1px solid var(--line)}th{font-size:.85rem;color:var(--muted)}td form{margin:0}.badge{display:inline-block;border-radius:999px;padding:.2rem .55rem;font-size:.8rem;background:#f6e1e8}.badge.paid,.badge.completed,.badge.approved{background:#dff3e6;color:#176b45}.badge.void,.badge.cancelled{background:#f9dfe3;color:#8b2136}.badge.pending{background:#fff0c9;color:#765600}.daygrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.7rem}.day{border:1px solid var(--line);border-radius:12px;padding:.7rem;background:#fff}.dayline{display:grid;grid-template-columns:1fr 1fr;gap:.4rem}.day .field{margin:.4rem 0}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}.stat{background:#fff;border:1px solid var(--line);padding:.9rem;border-radius:14px}.stat b{font-size:1.15rem;display:block}.sectionhead{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.two{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:1rem}.smallform{min-width:145px}.footer{margin:1rem 0;color:var(--muted);font-size:.9rem}@media(max-width:600px){body{padding:.6rem}.shell{border-radius:16px;padding:.8rem}.section{padding:.8rem}}
</style></head><body><main class="wrap"><div class="shell">
<div class="top"><div><div class="logo">SalonDiva</div><h1>Staff operations</h1><p class="muted">Profiles, schedules, shifts, tasks, stylist services, and commission records.</p></div><div><a class="btn light" href="dashboard.php">Back to dashboard</a> <a class="btn" href="my-work.php">My Work page</a></div></div>
<?php if ($flash!==''): ?><div class="notice"><?=sd_staff_h($flash)?></div><?php endif; ?>
<?php if ($error!==''): ?><div class="error"><?=sd_staff_h($error)?></div><?php endif; ?>
<section class="section"><h2>Commission overview</h2><div class="stats"><?php foreach (['pending'=>'Pending','approved'=>'Approved','paid'=>'Paid','void'=>'Voided'] as $key=>$label): ?><div class="stat"><span class="muted"><?=sd_staff_h($label)?> · <?=number_format($commissionCounts[$key])?> record(s)</span><b><?=number_format($commissionSummary[$key],2)?></b><span class="hint">PKR / configured salon currency</span></div><?php endforeach; ?></div><p class="hint">Commission amounts are calculated when a performed service is recorded. Rate and price are snapshots, so future profile or service price changes do not rewrite history.</p></section>
<section class="section"><div class="sectionhead"><h2>Staff profiles and weekly schedules</h2><span class="hint">Only active, approved receptionists and stylists appear here.</span></div>
<?php if (!$staff): ?><p>No approved active receptionists or stylists found. First approve staff requests on the Staff Accounts page.</p><?php endif; ?>
<?php foreach ($staff as $member): $sid=(int)$member['id']; $profileSchedules=$scheduleMap[$sid]??[]; ?><article class="section" style="background:#fff;border-color:#f4dce4"><div class="sectionhead"><div><h3><?=sd_staff_h((string)$member['name'])?> <span class="badge"><?=sd_staff_h(ucfirst((string)$member['role']))?></span></h3><span class="muted"><?=sd_staff_h((string)$member['email'])?><?=!empty($member['username'])?' · '.sd_staff_h((string)$member['username']):''?></span></div></div>
<form method="post" action="staff-management.php"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="save_profile"><input type="hidden" name="staff_user_id" value="<?=$sid?>"><h3>Contact and commission profile</h3><div class="grid">
<div class="field"><label>Phone</label><input name="phone" maxlength="40" value="<?=sd_staff_h((string)($member['phone']??''))?>" placeholder="Contact number"></div>
<div class="field"><label>Address</label><input name="address" maxlength="255" value="<?=sd_staff_h((string)($member['address']??''))?>" placeholder="Optional address"></div>
<div class="field"><label>Emergency contact name</label><input name="emergency_contact_name" maxlength="120" value="<?=sd_staff_h((string)($member['emergency_contact_name']??''))?>"></div>
<div class="field"><label>Emergency contact phone</label><input name="emergency_contact_phone" maxlength="40" value="<?=sd_staff_h((string)($member['emergency_contact_phone']??''))?>"></div>
<?php if ($member['role']==='stylist'): ?><div class="field"><label>Commission rate (%)</label><input type="number" name="commission_rate" min="0" max="100" step="0.01" required value="<?=sd_staff_h((string)($member['commission_rate']??'0.00'))?>"><span class="hint">Example: 25 means 25% of the recorded service price.</span></div><?php else: ?><input type="hidden" name="commission_rate" value="<?=sd_staff_h((string)($member['commission_rate']??'0.00'))?>"><div class="field"><label>Commission rate</label><input value="Not used for receptionists" disabled></div><?php endif; ?>
<div class="field"><label>Notes</label><textarea name="notes" maxlength="4000"><?=sd_staff_h((string)($member['notes']??''))?></textarea></div></div><button class="btn pink" type="submit">Save profile</button></form>
<div style="height:1px;background:#eedce2;margin:1rem 0"></div><form method="post" action="staff-management.php"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="save_schedule"><input type="hidden" name="staff_user_id" value="<?=$sid?>"><h3>Weekly work schedule</h3><div class="daygrid"><?php foreach($dayNames as $day=>$dayName): $sch=$profileSchedules[$day]??null; $isOff=$sch ? (int)$sch['is_day_off']===1 : in_array($day,[6,7],true); $st=$sch && $sch['start_time'] ? substr((string)$sch['start_time'],0,5) : '09:00'; $en=$sch && $sch['end_time'] ? substr((string)$sch['end_time'],0,5) : '17:00'; ?><div class="day"><strong><?=sd_staff_h($dayName)?></strong><label style="display:block;margin:.35rem 0"><input type="checkbox" name="schedule[<?=$day?>][off]" value="1" <?=$isOff?'checked':''?>> Day off</label><div class="dayline"><div class="field"><label>Start</label><input type="time" name="schedule[<?=$day?>][start]" value="<?=sd_staff_h($st)?>"></div><div class="field"><label>End</label><input type="time" name="schedule[<?=$day?>][end]" value="<?=sd_staff_h($en)?>"></div></div></div><?php endforeach; ?></div><button class="btn" type="submit">Save weekly schedule</button></form>
<?php if ($member['role']==='stylist'): ?><div style="height:1px;background:#eedce2;margin:1rem 0"></div><h3>Assigned services</h3><?php $as=db()->prepare('SELECT ss.service_id,s.name,s.price FROM stylist_services ss JOIN services s ON s.id=ss.service_id WHERE ss.staff_user_id=:uid AND ss.is_active=1 ORDER BY s.name'); $as->execute(['uid'=>$sid]); $assigned=$as->fetchAll(); ?><?php if (!$assigned): ?><p class="hint">No services assigned yet.</p><?php else: ?><div class="tablewrap"><table><thead><tr><th>Service</th><th>Price</th><th>Action</th></tr></thead><tbody><?php foreach($assigned as $a): ?><tr><td><?=sd_staff_h((string)$a['name'])?></td><td><?=number_format((float)$a['price'],2)?></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="unassign_service"><input type="hidden" name="staff_user_id" value="<?=$sid?>"><input type="hidden" name="service_id" value="<?=(int)$a['service_id']?>"><button class="btn light" type="submit">Remove</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?></article><?php endforeach; ?></section>
<section class="section"><h2>Assign a service to a stylist</h2><?php if (!$services): ?><p>No active services found. Add services first in <a href="services.php">Services Management</a>.</p><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="assign_service"><div class="grid"><div class="field"><label>Stylist</label><select required name="staff_user_id"><option value="">Choose stylist</option><?php foreach($staff as $s): if($s['role']!=='stylist')continue; ?><option value="<?=(int)$s['id']?>"><?=sd_staff_h((string)$s['name'])?></option><?php endforeach; ?></select></div><div class="field"><label>Service</label><select required name="service_id"><option value="">Choose service</option><?php foreach($services as $sv): ?><option value="<?=(int)$sv['id']?>"><?=sd_staff_h((string)$sv['name'])?> — <?=number_format((float)$sv['price'],2)?></option><?php endforeach; ?></select></div></div><button class="btn pink" type="submit">Assign service</button></form><?php endif; ?></section>
<section class="section"><h2>Assign a shift</h2><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="create_shift"><div class="grid"><div class="field"><label>Staff member</label><select required name="staff_user_id"><option value="">Choose staff</option><?php foreach($staff as $s): ?><option value="<?=(int)$s['id']?>"><?=sd_staff_h((string)$s['name'])?> — <?=sd_staff_h(ucfirst((string)$s['role']))?></option><?php endforeach; ?></select></div><div class="field"><label>Date</label><input required type="date" name="shift_date" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>"></div><div class="field"><label>Start time</label><input required type="time" name="start_time" value="09:00"></div><div class="field"><label>End time</label><input required type="time" name="end_time" value="17:00"></div><div class="field"><label>Location/area</label><input name="location" maxlength="120" placeholder="Salon floor, branch, station"></div><div class="field"><label>Notes</label><input name="notes" maxlength="1000" placeholder="Optional shift notes"></div></div><button class="btn pink" type="submit">Assign shift</button></form>
<h3 style="margin-top:1.2rem">Recent shifts</h3><div class="tablewrap"><table><thead><tr><th>Staff</th><th>Date</th><th>Time</th><th>Location / notes</th><th>Status</th></tr></thead><tbody><?php foreach($shifts as $s): ?><tr><td><?=sd_staff_h((string)$s['staff_name'])?><br><span class="hint"><?=sd_staff_h((string)$s['staff_role'])?></span></td><td><?=sd_staff_h((string)$s['shift_date'])?></td><td><?=sd_staff_h(substr((string)$s['start_time'],0,5))?>–<?=sd_staff_h(substr((string)$s['end_time'],0,5))?></td><td><?=sd_staff_h((string)($s['location']??''))?><br><?=sd_staff_h((string)($s['notes']??''))?></td><td><span class="badge <?=sd_staff_h((string)$s['status'])?>"><?=sd_staff_h(ucfirst((string)$s['status']))?></span></td></tr><?php endforeach; ?><?php if(!$shifts): ?><tr><td colspan="5">No shifts assigned yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="section"><h2>Assign a task</h2><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="create_task"><div class="grid"><div class="field"><label>Assign to</label><select required name="assigned_to"><option value="">Choose staff</option><?php foreach($staff as $s): ?><option value="<?=(int)$s['id']?>"><?=sd_staff_h((string)$s['name'])?> — <?=sd_staff_h(ucfirst((string)$s['role']))?></option><?php endforeach; ?></select></div><div class="field"><label>Task title</label><input required name="title" maxlength="160" placeholder="Prepare station, clean tools..."></div><div class="field"><label>Priority</label><select name="priority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option></select></div><div class="field"><label>Due date and time</label><input type="datetime-local" name="due_at"></div><div class="field"><label>Description</label><textarea name="description" maxlength="4000"></textarea></div></div><button class="btn pink" type="submit">Assign task</button></form>
<h3 style="margin-top:1.2rem">Task list</h3><div class="tablewrap"><table><thead><tr><th>Task</th><th>Assigned to</th><th>Due</th><th>Priority</th><th>Status</th><th>Update</th></tr></thead><tbody><?php foreach($tasks as $t): ?><tr><td><strong><?=sd_staff_h((string)$t['title'])?></strong><br><span class="hint"><?=sd_staff_h((string)($t['description']??''))?></span></td><td><?=sd_staff_h((string)$t['staff_name'])?></td><td><?=sd_staff_h((string)($t['due_at']??'—'))?></td><td><?=sd_staff_h(ucfirst((string)$t['priority']))?></td><td><span class="badge <?=sd_staff_h((string)$t['status'])?>"><?=sd_staff_h(str_replace('_',' ',ucfirst((string)$t['status'])))?></span></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?=(int)$t['id']?>"><select name="status"><?php foreach(['pending','in_progress','completed','cancelled'] as $st): ?><option value="<?=sd_staff_h($st)?>" <?=$t['status']===$st?'selected':''?>><?=sd_staff_h(str_replace('_',' ',ucfirst($st)))?></option><?php endforeach; ?></select> <button class="btn light" type="submit">Save</button></form></td></tr><?php endforeach; ?><?php if(!$tasks): ?><tr><td colspan="6">No tasks assigned yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="section"><h2>Record a service performed</h2><p class="hint">Choose a stylist and service, confirm the amount actually charged, and optionally select a customer. The commission is calculated as actual price × the stylist's current commission rate. New entries start as Pending.</p><?php if(!$services): ?><p>No active services found. Add services first.</p><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="record_commission"><div class="grid"><div class="field"><label>Stylist</label><select required name="stylist_user_id"><option value="">Choose stylist</option><?php foreach($staff as $s): if($s['role']!=='stylist')continue; ?><option value="<?=(int)$s['id']?>"><?=sd_staff_h((string)$s['name'])?> (<?=number_format((float)($s['commission_rate']??0),2)?>%)</option><?php endforeach; ?></select></div><div class="field"><label>Service</label><select required name="service_id" id="commission_service"><?php foreach($services as $sv): ?><option value="<?=(int)$sv['id']?>" data-price="<?=sd_staff_h((string)$sv['price'])?>"><?=sd_staff_h((string)$sv['name'])?> — <?=number_format((float)$sv['price'],2)?></option><?php endforeach; ?></select></div><div class="field"><label>Actual service price (PKR)</label><input required type="number" name="service_price" id="commission_price" min="0" max="99999999.99" step="0.01" value="<?=sd_staff_h((string)$services[0]['price'])?>"></div><div class="field"><label>Customer (optional)</label><select name="customer_id"><option value="">No customer selected</option><?php foreach($customers as $c): ?><option value="<?=(int)$c['id']?>"><?=sd_staff_h((string)$c['name'])?><?=!empty($c['phone'])?' · '.sd_staff_h((string)$c['phone']):''?></option><?php endforeach; ?></select></div><div class="field"><label>Performed date and time</label><input type="datetime-local" name="performed_at" value="<?=date('Y-m-d\TH:i')?>"></div><div class="field"><label>Notes</label><input name="commission_notes" maxlength="1000" placeholder="Optional transaction notes"></div></div><button class="btn pink" type="submit">Record service and calculate commission</button></form><?php endif; ?></section>
<section class="section"><h2>Commission records</h2><div class="tablewrap"><table><thead><tr><th>Performed</th><th>Stylist</th><th>Service</th><th>Customer</th><th>Price</th><th>Rate</th><th>Commission</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($commissions as $c): ?><tr><td><?=sd_staff_h((string)$c['performed_at'])?></td><td><?=sd_staff_h((string)$c['stylist_name'])?></td><td><?=sd_staff_h((string)$c['service_name_snapshot'])?></td><td><?=sd_staff_h((string)($c['customer_name']??'—'))?></td><td><?=number_format((float)$c['service_price'],2)?></td><td><?=number_format((float)$c['commission_rate'],2)?>%</td><td><strong><?=number_format((float)$c['commission_amount'],2)?></strong></td><td><span class="badge <?=sd_staff_h((string)$c['status'])?>"><?=sd_staff_h(ucfirst((string)$c['status']))?></span></td><td><?php if($c['status']==='pending'): ?><form method="post" style="display:inline-block"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="commission_status"><input type="hidden" name="commission_id" value="<?=(int)$c['id']?>"><button class="btn light" name="status" value="approved">Approve</button> <button class="btn warn" name="status" value="void">Void</button></form><?php elseif($c['status']==='approved'): ?><form method="post" style="display:inline-block"><input type="hidden" name="_csrf" value="<?=sd_staff_h(csrf_token())?>"><input type="hidden" name="action" value="commission_status"><input type="hidden" name="commission_id" value="<?=(int)$c['id']?>"><button class="btn pink" name="status" value="paid">Mark paid</button> <button class="btn warn" name="status" value="void">Void</button></form><?php else: ?><span class="hint"><?=!empty($c['paid_at'])?'Paid '.sd_staff_h((string)$c['paid_at']):'No further action'?></span><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$commissions): ?><tr><td colspan="9">No service commission records yet.</td></tr><?php endif; ?></tbody></table></div><p class="hint">Workflow: Pending → Approved → Paid. Pending/approved records can be voided. Paid or voided records are locked. Keep this page restricted to authorized administrators because commission entries and rates affect payroll.</p></section>
<p class="footer">SalonDiva Staff Operations · Admin-only controls are enforced on the server. Rates and service prices are preserved per commission record.</p>
</div></main><script>
(function(){const service=document.getElementById('commission_service');const price=document.getElementById('commission_price');if(service&&price){service.addEventListener('change',()=>{const opt=service.options[service.selectedIndex];if(opt&&opt.dataset.price!==undefined)price.value=opt.dataset.price;});}})();
</script></body></html>
