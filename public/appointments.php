<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/appointment_helpers.php';
$user=require_role('admin','receptionist','stylist'); $pdo=db(); $error=''; $notice='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) $error='Your security token expired. Refresh and try again.';
    else {
        $id=filter_var($_POST['appointment_id'] ?? null,FILTER_VALIDATE_INT); $action=(string)($_POST['action'] ?? '');
        try {
            if (!$id || $id<1) throw new InvalidArgumentException('Invalid appointment selected.');
            $q=$pdo->prepare('SELECT * FROM appointments WHERE id=?'); $q->execute([(int)$id]); $appt=$q->fetch();
            if (!$appt || ($user['role']==='stylist' && (int)$appt['stylist_user_id']!==(int)$user['id'])) throw new InvalidArgumentException('Appointment not found or you do not have access.');
            if ($action==='reschedule') {
                if (!in_array($user['role'],['admin','receptionist'],true)) throw new InvalidArgumentException('Only an administrator or receptionist can reschedule appointments.');
                if ($appt['status']!=='confirmed') throw new InvalidArgumentException('Only confirmed appointments can be rescheduled.');
                $date=trim((string)($_POST['appointment_date'] ?? '')); $time=trim((string)($_POST['start_time'] ?? ''));
                if (!sd_appt_valid_date($date) || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+90 days'))) throw new InvalidArgumentException('Choose a date from today through the next 90 days.');
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)) throw new InvalidArgumentException('Select a valid available time.');
                $pdo->beginTransaction(); $lock=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->execute([(int)$appt['stylist_user_id']]);
                $available=sd_appt_slots($pdo,(int)$appt['service_id'],(int)$appt['stylist_user_id'],$date,(int)$appt['id']);
                if (!in_array($time,$available,true)) throw new InvalidArgumentException('That slot is no longer available. Choose another time.');
                $start=new DateTimeImmutable($date.' '.$time.':00'); $duration=max(1,(int)round((strtotime((string)$appt['ends_at'])-strtotime((string)$appt['starts_at']))/60)); $end=$start->modify('+'.$duration.' minutes');
                $q=$pdo->prepare("UPDATE appointments SET starts_at=?,ends_at=? WHERE id=? AND status='confirmed'"); $q->execute([$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),(int)$id]); $pdo->commit();
                sd_appt_notify_safely($pdo,(int)$id,'rescheduled'); $notice='Appointment rescheduled.';
            } elseif ($action==='cancel') {
                if (!in_array($user['role'],['admin','receptionist'],true)) throw new InvalidArgumentException('Only an administrator or receptionist can cancel appointments from this page.');
                if ($appt['status']!=='confirmed') throw new InvalidArgumentException('Only confirmed appointments can be cancelled.');
                $q=$pdo->prepare("UPDATE appointments SET status='cancelled',cancelled_at=NOW(),cancelled_by=? WHERE id=? AND status='confirmed'"); $q->execute([(int)$user['id'],(int)$id]);
                if ($q->rowCount()!==1) throw new InvalidArgumentException('Appointment status changed; refresh and try again.');
                sd_appt_notify_safely($pdo,(int)$id,'cancelled'); $notice='Appointment cancelled.';
            } elseif ($action==='completed' || $action==='no_show') {
                if (!in_array($user['role'],['admin','receptionist','stylist'],true)) throw new InvalidArgumentException('You do not have permission for that action.');
                if ($appt['status']!=='confirmed') throw new InvalidArgumentException('Only confirmed appointments can be updated.');
                $q=$pdo->prepare('UPDATE appointments SET status=? WHERE id=? AND status=\'confirmed\''); $q->execute([$action,(int)$id]); $notice=$action==='completed'?'Appointment marked completed.':'Appointment marked as no-show.';
            } else throw new InvalidArgumentException('Unknown appointment action.');
        } catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('SalonDiva appointments error: '.$e->getMessage()); $error='We could not update this appointment. Please try again.'; }
    }
}
if ($user['role']==='stylist') {
    $q=$pdo->prepare('SELECT a.*,c.name customer_name,c.phone customer_phone,u.name stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.stylist_user_id=? AND a.starts_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY a.starts_at LIMIT 200'); $q->execute([(int)$user['id']]); $rows=$q->fetchAll();
} else {
    $rows=$pdo->query('SELECT a.*,c.name customer_name,c.phone customer_phone,u.name stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.starts_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY a.starts_at LIMIT 300')->fetchAll();
}
sd_appt_page_start('Appointments');
?>
<section class="card"><h1>Appointments</h1><p class="muted">Review bookings, reschedule or cancel confirmed appointments, and record completed or missed visits.</p><div class="nav"><a href="booking.php">Public booking page</a><a href="dashboard.php">Dashboard</a></div><?php if($error!==''): ?><div class="alert error"><?=sd_appt_h($error)?></div><?php endif; ?><?php if($notice!==''): ?><div class="alert"><?=sd_appt_h($notice)?></div><?php endif; ?><div class="tablewrap"><table><thead><tr><th>Date & time</th><th>Customer</th><th>Service</th><th>Stylist</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $a): ?><tr><td><?=sd_appt_h(date('D, d M Y H:i',strtotime((string)$a['starts_at'])))?><br><span class="hint"><?=sd_appt_h((string)$a['booking_code'])?></span></td><td><?=sd_appt_h((string)$a['customer_name'])?><br><span class="hint"><?=sd_appt_h((string)($a['customer_phone']??''))?></span></td><td><?=sd_appt_h((string)$a['service_name_snapshot'])?></td><td><?=sd_appt_h((string)$a['stylist_name'])?></td><td>PKR <?=number_format((float)$a['service_price_snapshot'],2)?></td><td><span class="pill"><?=sd_appt_h(ucfirst(str_replace('_',' ',(string)$a['status'])))?></span></td><td><?php if($a['status']==='confirmed'): ?><form method="post" style="min-width:220px"><input type="hidden" name="_csrf" value="<?=sd_appt_h(csrf_token())?>"><input type="hidden" name="appointment_id" value="<?=(int)$a['id']?>"><?php if(in_array($user['role'],['admin','receptionist'],true)): ?><input type="hidden" name="action" value="reschedule"><div class="field"><label>New date</label><input type="date" name="appointment_date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+90 days'))?>" required></div><div class="field"><label>New time</label><input type="time" name="start_time" step="900" required></div><button class="btn light" type="submit">Reschedule</button> <button class="btn danger" type="submit" name="action" value="cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button><?php endif; ?><button class="btn" type="submit" name="action" value="completed">Completed</button> <button class="btn light" type="submit" name="action" value="no_show">No-show</button></form><?php else: ?><span class="hint">No actions</span><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="7">No appointments in the last 30 days or upcoming.</td></tr><?php endif; ?></tbody></table></div><p class="hint">Availability uses the stylist's saved weekly schedule, assigned services, service duration, and existing confirmed appointments. Configure schedules and service assignments in Staff Management first.</p></section>
<?php sd_appt_page_end(); ?>
