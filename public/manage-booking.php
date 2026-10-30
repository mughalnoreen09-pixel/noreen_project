<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/appointment_helpers.php';
$pdo=db(); $token=(string)($_GET['token'] ?? $_POST['token'] ?? ''); $error=''; $notice=''; $appt=null; $date=(string)($_GET['date'] ?? $_POST['appointment_date'] ?? date('Y-m-d',strtotime('+1 day'))); $slots=[];
if (!preg_match('/^[a-f0-9]{48}$/',$token)) { http_response_code(404); $error='This booking management link is invalid. Use the private link provided when the appointment was booked.'; }
else { $q=$pdo->prepare('SELECT a.*,c.name customer_name,c.email customer_email,c.phone customer_phone,u.name stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.manage_token_hash=?'); $q->execute([hash('sha256',$token)]); $appt=$q->fetch() ?: null; if (!$appt) { http_response_code(404); $error='Booking not found. Please check the private management link.'; } }
if ($appt && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) $error='Your security token expired. Refresh the page and try again.';
    elseif ($appt['status']!=='confirmed' || strtotime((string)$appt['starts_at']) <= time()) $error='Only future confirmed appointments can be changed from this link. Contact the salon for help.';
    else {
        $action=(string)($_POST['action'] ?? '');
        try {
            if ($action==='cancel') {
                $q=$pdo->prepare("UPDATE appointments SET status='cancelled',cancelled_at=NOW(),cancelled_by=NULL WHERE id=? AND manage_token_hash=? AND status='confirmed' AND starts_at>NOW()"); $q->execute([(int)$appt['id'],hash('sha256',$token)]);
                if ($q->rowCount()!==1) throw new InvalidArgumentException('The appointment could not be cancelled; it may have changed already.');
                sd_appt_notify_safely($pdo,(int)$appt['id'],'cancelled',$token); $notice='Your appointment has been cancelled.';
            } elseif ($action==='reschedule') {
                $date=trim((string)($_POST['appointment_date'] ?? '')); $time=trim((string)($_POST['start_time'] ?? ''));
                if (!sd_appt_valid_date($date) || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+90 days'))) throw new InvalidArgumentException('Choose a date from today through the next 90 days.');
                if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)) throw new InvalidArgumentException('Select an available time.');
                $pdo->beginTransaction(); $lock=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->execute([(int)$appt['stylist_user_id']]);
                $available=sd_appt_slots($pdo,(int)$appt['service_id'],(int)$appt['stylist_user_id'],$date,(int)$appt['id']);
                if (!in_array($time,$available,true)) throw new InvalidArgumentException('That slot is no longer available. Choose another time.');
                $start=new DateTimeImmutable($date.' '.$time.':00'); $end=$start->modify('+'.(int)round((strtotime((string)$appt['ends_at'])-strtotime((string)$appt['starts_at']))/60).' minutes');
                $q=$pdo->prepare("UPDATE appointments SET starts_at=?,ends_at=? WHERE id=? AND status='confirmed'"); $q->execute([$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),(int)$appt['id']]); $pdo->commit();
                sd_appt_notify_safely($pdo,(int)$appt['id'],'rescheduled',$token); $notice='Your appointment has been rescheduled.';
            } else throw new InvalidArgumentException('Unknown action.');
        } catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('SalonDiva manage booking error: '.$e->getMessage()); $error='We could not update this appointment. Please contact the salon.'; }
        $q=$pdo->prepare('SELECT a.*,c.name customer_name,c.email customer_email,c.phone customer_phone,u.name stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.manage_token_hash=?'); $q->execute([hash('sha256',$token)]); $appt=$q->fetch() ?: $appt;
    }
}
$slots=($appt && sd_appt_valid_date($date) && $appt['status']==='confirmed')?sd_appt_slots($pdo,(int)$appt['service_id'],(int)$appt['stylist_user_id'],$date,(int)$appt['id']):[];
sd_appt_page_start('Manage your booking');
?>
<section class="card"><h1>Manage your appointment</h1><p class="muted">Use this private page to review, reschedule or cancel your booking.</p><?php if($error!==''): ?><div class="alert error"><?=sd_appt_h($error)?></div><?php endif; ?><?php if($notice!==''): ?><div class="alert"><?=sd_appt_h($notice)?></div><?php endif; ?>
<?php if($appt): ?><div class="card"><h2>Booking <?=sd_appt_h((string)$appt['booking_code'])?></h2><p><strong><?=sd_appt_h((string)$appt['service_name_snapshot'])?></strong> with <?=sd_appt_h((string)$appt['stylist_name'])?></p><p><?=sd_appt_h(date('D, d M Y H:i',strtotime((string)$appt['starts_at'])))?> · PKR <?=number_format((float)$appt['service_price_snapshot'],2)?></p><p>Status: <span class="pill"><?=sd_appt_h(ucfirst((string)$appt['status']))?></span></p>
<?php if($appt['status']==='confirmed' && strtotime((string)$appt['starts_at'])>time()): ?><h2>Reschedule</h2><form method="get" class="grid"><input type="hidden" name="token" value="<?=sd_appt_h($token)?>"><div class="field"><label for="date">New date</label><input type="date" id="date" name="date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+90 days'))?>" value="<?=sd_appt_h($date)?>" required></div><div style="align-self:end"><button class="btn light" type="submit">Show available times</button></div></form><?php if(sd_appt_valid_date($date)): ?><form method="post"><input type="hidden" name="_csrf" value="<?=sd_appt_h(csrf_token())?>"><input type="hidden" name="token" value="<?=sd_appt_h($token)?>"><input type="hidden" name="action" value="reschedule"><input type="hidden" name="appointment_date" value="<?=sd_appt_h($date)?>"><p class="muted">Available times for this stylist and service:</p><?php if(!$slots): ?><p>No available times on this date. Try another date.</p><?php else: ?><div class="slots"><?php foreach($slots as $slot): ?><label class="slot"><input type="radio" name="start_time" value="<?=sd_appt_h($slot)?>" required> <?=sd_appt_h($slot)?></label><?php endforeach; ?></div><button class="btn" type="submit">Confirm reschedule</button><?php endif; ?></form><?php endif; ?><hr style="border:0;border-top:1px solid #eadfe7;margin:22px 0"><h2>Cancel appointment</h2><form method="post" onsubmit="return confirm('Cancel this appointment?');"><input type="hidden" name="_csrf" value="<?=sd_appt_h(csrf_token())?>"><input type="hidden" name="token" value="<?=sd_appt_h($token)?>"><input type="hidden" name="action" value="cancel"><button class="btn danger" type="submit">Cancel appointment</button></form><?php else: ?><p class="muted">This appointment cannot be changed through this link. Contact the salon if you need assistance.</p><?php endif; ?></div><?php endif; ?></section>
<?php sd_appt_page_end(); ?>
