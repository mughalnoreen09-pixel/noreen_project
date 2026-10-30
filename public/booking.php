<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Karachi');
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/appointment_helpers.php';
$pdo=db();
$error=''; $success=null; $manageUrl='';
$services=$pdo->query('SELECT id,name,description,price,duration_minutes FROM services WHERE is_active=1 ORDER BY name')->fetchAll();
$serviceId=filter_var($_GET['service_id'] ?? $_POST['service_id'] ?? null,FILTER_VALIDATE_INT) ?: 0;
$stylistId=filter_var($_GET['stylist_id'] ?? $_POST['stylist_id'] ?? null,FILTER_VALIDATE_INT) ?: 0;
$date=(string)($_GET['date'] ?? $_POST['appointment_date'] ?? date('Y-m-d',strtotime('+1 day')));
$selectedTime=(string)($_POST['start_time'] ?? '');
$service= $serviceId ? sd_appt_service($pdo,$serviceId) : null;
$stylists=$service ? sd_appt_stylists($pdo,$serviceId) : [];
$slots=($service && $stylistId && sd_appt_valid_date($date)) ? sd_appt_slots($pdo,$serviceId,$stylistId,$date) : [];

if ($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action'] ?? '')==='book') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) $error='Your session security token expired. Refresh and try again.';
    else {
        try {
            $name=trim((string)($_POST['customer_name'] ?? '')); $email=trim((string)($_POST['customer_email'] ?? '')); $phone=trim((string)($_POST['customer_phone'] ?? '')); $notes=trim((string)($_POST['notes'] ?? ''));
            if ($name==='' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name))>120) throw new InvalidArgumentException('Enter your name (up to 120 characters).');
            if ($email!=='' && (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254)) throw new InvalidArgumentException('Enter a valid email address or leave it blank.');
            if ($phone==='' || (function_exists('mb_strlen') ? mb_strlen($phone) : strlen($phone))>40) throw new InvalidArgumentException('Enter a phone number so the salon can contact you.');
            if ((function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes))>1000) throw new InvalidArgumentException('Notes must be 1000 characters or fewer.');
            $serviceId=(int)($_POST['service_id'] ?? 0); $stylistId=(int)($_POST['stylist_id'] ?? 0); $date=trim((string)($_POST['appointment_date'] ?? '')); $selectedTime=trim((string)($_POST['start_time'] ?? ''));
            $service=sd_appt_service($pdo,$serviceId); if (!$service) throw new InvalidArgumentException('Select an available service.');
            if (!sd_appt_valid_date($date) || $date<date('Y-m-d') || $date>date('Y-m-d',strtotime('+90 days'))) throw new InvalidArgumentException('Choose a date from today through the next 90 days.');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$selectedTime)) throw new InvalidArgumentException('Select an available time slot.');
            $token=bin2hex(random_bytes(24)); $bookingCode='SD-'.strtoupper(bin2hex(random_bytes(4)));
            $pdo->beginTransaction();
            // Lock the stylist row so two simultaneous requests cannot book overlapping slots for the same stylist.
            $lock=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='stylist' AND is_active=1 AND account_status='approved' FOR UPDATE"); $lock->execute([$stylistId]);
            if (!$lock->fetch()) throw new InvalidArgumentException('That stylist is not available for booking.');
            $available=sd_appt_slots($pdo,$serviceId,$stylistId,$date);
            if (!in_array($selectedTime,$available,true)) throw new InvalidArgumentException('That time slot is no longer available. Please choose another time.');
            $start=new DateTimeImmutable($date.' '.$selectedTime.':00'); $end=$start->modify('+'.(int)$service['duration_minutes'].' minutes');
            $customerId=null;
            if ($email!=='') { $q=$pdo->prepare('SELECT id FROM customers WHERE email=? AND is_active=1 ORDER BY id LIMIT 1'); $q->execute([$email]); $customerId=$q->fetchColumn() ?: null; }
            if (!$customerId) { $q=$pdo->prepare('SELECT id FROM customers WHERE phone=? AND is_active=1 ORDER BY id LIMIT 1'); $q->execute([$phone]); $customerId=$q->fetchColumn() ?: null; }
            if ($customerId) {
                $q=$pdo->prepare('UPDATE customers SET name=?,email=?,phone=?,notes=CASE WHEN ? <> "" THEN ? ELSE notes END WHERE id=?');
                $q->execute([$name,$email===''?null:$email,$phone,$notes,$notes,(int)$customerId]);
            } else {
                $q=$pdo->prepare('INSERT INTO customers (name,email,phone,notes,is_active) VALUES (?,?,?,?,1)'); $q->execute([$name,$email===''?null:$email,$phone,$notes===''?null:$notes]); $customerId=(int)$pdo->lastInsertId();
            }
            $q=$pdo->prepare('INSERT INTO appointments (booking_code,customer_id,stylist_user_id,service_id,starts_at,ends_at,service_name_snapshot,service_price_snapshot,status,notes,manage_token_hash) VALUES (?,?,?,?,?,?,?,?,\'confirmed\',?,?)');
            $q->execute([$bookingCode,(int)$customerId,$stylistId,$serviceId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$service['name'],$service['price'],$notes===''?null:$notes,hash('sha256',$token)]);
            $appointmentId=(int)$pdo->lastInsertId(); $pdo->commit();
            sd_appt_notify_safely($pdo,$appointmentId,'confirmation',$token);
            header('Location: booking.php?confirmed='.rawurlencode($bookingCode).'&manage='.rawurlencode($token)); exit;
        } catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$e->getMessage(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('SalonDiva booking error: '.$e->getMessage()); $error='We could not save the booking. Check that the appointment migration was run and try again.'; }
    }
    $serviceId=(int)($_POST['service_id'] ?? 0); $stylistId=(int)($_POST['stylist_id'] ?? 0); $service=sd_appt_service($pdo,$serviceId); $stylists=$service?sd_appt_stylists($pdo,$serviceId):[]; $slots=($service && $stylistId && sd_appt_valid_date($date))?sd_appt_slots($pdo,$serviceId,$stylistId,$date):[];
}
if (isset($_GET['confirmed'],$_GET['manage'])) {
    $code=(string)$_GET['confirmed']; $token=(string)$_GET['manage'];
    $q=$pdo->prepare('SELECT a.*,c.name customer_name,u.name stylist_name FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id WHERE a.booking_code=? AND a.manage_token_hash=?');
    $q->execute([$code,hash('sha256',$token)]); $success=$q->fetch() ?: null;
    if ($success) $manageUrl='manage-booking.php?token='.rawurlencode($token);
}

sd_appt_page_start('Book an appointment');
?>
<style>
/* Page-scoped redesign: keeps booking visible without changing other appointment pages. */
:root{--sd-pink-1:#FFC2D1;--sd-pink-2:#FF93B0;--sd-cream:#FFF6EC;--sd-yellow:#FFD45C;--sd-crimson:#C8264F;--sd-ink:#2B1520;--sd-muted:#6E4A57;--sd-line:#F0D5C4}
.booking-page{max-width:1040px;margin:0 auto 30px;color:var(--sd-ink)}
.booking-page *{box-sizing:border-box}
.booking-page .booking-hero{position:relative;overflow:hidden;padding:clamp(24px,4vw,44px);border-radius:30px;background:linear-gradient(125deg,#fff6ec 0%,#ffe5ec 56%,#ffbfd0 100%);border:1px solid #f4d3dc;box-shadow:0 18px 50px rgba(83,28,48,.08);margin-bottom:20px}
.booking-page .booking-hero:after{content:'✦';position:absolute;right:34px;top:-35px;font-size:160px;line-height:1;color:#c8264f0d;pointer-events:none}
.booking-page .booking-kicker{display:inline-flex;align-items:center;gap:8px;color:var(--sd-crimson);font-weight:800;font-size:11px;letter-spacing:1.6px;text-transform:uppercase}
.booking-page .booking-kicker:before{content:'';width:8px;height:8px;border-radius:50%;background:var(--sd-yellow);box-shadow:0 0 0 4px #ffd45c44}
.booking-page h1{font-size:clamp(30px,4.4vw,48px);line-height:1.07;letter-spacing:-1.5px;margin:13px 0 12px;max-width:720px;color:var(--sd-ink)}
.booking-page .booking-hero p{margin:0;max-width:680px;color:var(--sd-muted);font-size:15px;line-height:1.8}
.booking-page .booking-hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:21px;position:relative;z-index:1}
.booking-page .booking-link{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 17px;border-radius:999px;text-decoration:none;font-size:12px;font-weight:800;border:1px solid #e9cbd4}
.booking-page .booking-link.primary{background:var(--sd-crimson);color:#fff;border-color:var(--sd-crimson)}
.booking-page .booking-link.soft{background:#fffaf5;color:var(--sd-ink)}
.booking-page .booking-card{padding:clamp(18px,3vw,30px);margin:0 0 18px;background:#fffdfa;border:1px solid var(--sd-line);border-radius:24px;box-shadow:0 10px 35px rgba(83,28,48,.055)}
.booking-page .booking-card h2{font-size:clamp(20px,2.5vw,26px);line-height:1.2;letter-spacing:-.6px;margin:0 0 7px;color:var(--sd-ink)}
.booking-page .booking-sub{color:var(--sd-muted);font-size:13px;line-height:1.75;margin:0 0 20px}
.booking-page .booking-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.booking-page .booking-field{display:flex;flex-direction:column;gap:7px;min-width:0;margin-bottom:14px}
.booking-page .booking-field label{display:block;font-size:12px;font-weight:800;color:var(--sd-ink)}
.booking-page .booking-field select,.booking-page .booking-field input,.booking-page .booking-field textarea{display:block;width:100%;min-width:0;min-height:48px;padding:12px 13px;border:1px solid #e8d6d9;border-radius:13px;background:#fffaf6;color:var(--sd-ink);font:inherit;font-size:13px;outline:none}
.booking-page .booking-field textarea{min-height:100px;resize:vertical}
.booking-page .booking-field select:focus,.booking-page .booking-field input:focus,.booking-page .booking-field textarea:focus{border-color:var(--sd-pink-2);box-shadow:0 0 0 3px #ff93b026}
.booking-page .booking-hint{font-size:11px;color:var(--sd-muted);margin-top:2px;line-height:1.65}
.booking-page .booking-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;border:0;border-radius:999px;background:var(--sd-crimson);color:#fff;padding:0 22px;font:inherit;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 8px 19px #c8264f20;transition:transform .16s,box-shadow .16s}
.booking-page .booking-btn:hover{transform:translateY(-1px);box-shadow:0 12px 24px #c8264f30}
.booking-page .booking-btn.yellow{background:var(--sd-yellow);color:var(--sd-ink);box-shadow:none}
.booking-page .booking-alert{padding:14px 16px;border:1px solid #bfe5c9;background:#ecf9ef;border-radius:15px;color:#245b34;margin:0 0 17px;font-size:13px;line-height:1.75;overflow-wrap:anywhere}
.booking-page .booking-alert.error{border-color:#f2c2cd;background:#fff0f3;color:#8a1738}
.booking-page .booking-slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(95px,1fr));gap:10px;margin:19px 0 22px}
.booking-page .booking-slot{position:relative;display:flex;align-items:center;justify-content:center;gap:7px;min-height:44px;border:1px solid #eac3d0;border-radius:12px;background:#fff8fa;color:var(--sd-crimson);font-size:12px;font-weight:800;cursor:pointer;transition:background .15s,border-color .15s,transform .15s}
.booking-page .booking-slot:hover{background:#ffe8ef;transform:translateY(-1px)}
.booking-page .booking-slot input{accent-color:var(--sd-crimson);width:14px;height:14px;margin:0}
.booking-page .booking-empty{padding:17px 18px;background:#fff6e2;border:1px solid #f1dfae;border-radius:15px;color:#6d5114;font-size:13px;line-height:1.75}
.booking-page .booking-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:19px 0}
.booking-page .booking-detail{padding:15px;border-radius:16px;background:#fff5f7;border:1px solid #f2dfe4;min-width:0}
.booking-page .booking-detail span{display:block;color:var(--sd-muted);font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;margin-bottom:5px}
.booking-page .booking-detail strong{font-size:13px;overflow-wrap:anywhere}
.booking-page .booking-code{font-weight:900;font-size:20px;letter-spacing:1px;color:var(--sd-crimson)}
.booking-page .booking-private{margin-top:15px;padding:15px;border-radius:15px;background:#fff6ec;border:1px dashed #e8bfc9;overflow-wrap:anywhere;font-size:12px}
.booking-page .booking-private a{color:var(--sd-crimson);font-weight:800}
.booking-page .booking-footer-note{color:var(--sd-muted);font-size:11px;line-height:1.8;margin:18px 0 0}
.booking-page .booking-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}
.booking-page .booking-step{padding:15px 16px;border-radius:17px;background:#fffdfa;border:1px solid var(--sd-line)}
.booking-page .booking-step b{display:block;font-size:12px;margin-bottom:4px}
.booking-page .booking-step span{display:block;font-size:11px;color:var(--sd-muted);line-height:1.6}
@media(max-width:760px){.booking-page .booking-grid{grid-template-columns:1fr 1fr}.booking-page .booking-grid>.booking-field:last-child{grid-column:1/-1}.booking-page .booking-steps{grid-template-columns:1fr}.booking-page .booking-details{grid-template-columns:1fr}}
@media(max-width:520px){.booking-page .booking-grid{grid-template-columns:1fr}.booking-page .booking-grid>.booking-field:last-child{grid-column:auto}.booking-page .booking-hero:after{right:5px;font-size:110px}.booking-page .booking-btn{width:100%}.booking-page .booking-hero-actions{display:grid;grid-template-columns:1fr}.booking-page .booking-slots{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<section class="booking-page">
  <div class="booking-hero">
    <span class="booking-kicker">SalonDiva · Your time to shine</span>
    <h1>Make time for <span style="color:#C8264F">you.</span></h1>
    <p>Book your next salon visit in a few simple steps. Choose a service, your preferred stylist, a date and a real available time. All times are Pakistan Standard Time.</p>
    <div class="booking-hero-actions">
      <a class="booking-link primary" href="../index.html">Explore the salon</a>
      <a class="booking-link soft" href="login.php">Staff sign in</a>
    </div>
  </div>

  <?php if ($error!==''): ?><div class="booking-alert error" role="alert"><?=sd_appt_h($error)?></div><?php endif; ?>
  <?php if ($success): ?>
    <section class="booking-card" aria-live="polite">
      <span class="booking-kicker">Booking confirmed</span>
      <h2 style="margin-top:12px">You're all set!</h2>
      <p class="booking-sub">Your appointment has been saved. Keep your booking code and private management link somewhere safe.</p>
      <div class="booking-code"><?=sd_appt_h((string)$success['booking_code'])?></div>
      <div class="booking-details">
        <div class="booking-detail"><span>Service</span><strong><?=sd_appt_h((string)$success['service_name_snapshot'])?></strong></div>
        <div class="booking-detail"><span>Stylist</span><strong><?=sd_appt_h((string)$success['stylist_name'])?></strong></div>
        <div class="booking-detail"><span>Date & time</span><strong><?=sd_appt_h(date('D, d M Y H:i',strtotime((string)$success['starts_at'])))?></strong></div>
      </div>
      <p><strong>Total: PKR <?=number_format((float)$success['service_price_snapshot'],2)?></strong></p>
      <div class="booking-private"><strong>Manage this booking privately</strong><br><a href="<?=sd_appt_h($manageUrl)?>">Reschedule or cancel appointment</a><br><span><?=sd_appt_h($manageUrl)?></span></div>
      <p class="booking-footer-note">A confirmation email or SMS is attempted only when your salon has configured notification delivery.</p>
      <div class="booking-hero-actions"><a class="booking-link primary" href="booking.php">Book another visit</a><a class="booking-link soft" href="../index.html">Back to homepage</a></div>
    </section>
  <?php else: ?>
    <form method="get" action="booking.php" id="bookingFilter" class="booking-card">
      <h2>Plan your visit</h2>
      <p class="booking-sub">Step 1 · Choose the service and stylist, then select a date to view available time slots.</p>
      <div class="booking-grid">
        <div class="booking-field"><label for="service_id">1. Service</label><select id="service_id" name="service_id" required><option value="">Choose a service</option><?php foreach($services as $sv): ?><option value="<?=(int)$sv['id']?>" <?=$serviceId===(int)$sv['id']?'selected':''?>><?=sd_appt_h((string)$sv['name'])?> · PKR <?=number_format((float)$sv['price'],2)?> · <?=(int)$sv['duration_minutes']?> min</option><?php endforeach; ?></select><span class="booking-hint">Price and duration are shown for each service.</span></div>
        <div class="booking-field"><label for="stylist_id">2. Stylist</label><select id="stylist_id" name="stylist_id" <?=$service ? 'required' : 'disabled'?>><option value=""><?= $service ? 'Choose a stylist' : 'Select a service first' ?></option><?php foreach($stylists as $st): ?><option value="<?=(int)$st['id']?>" <?=$stylistId===(int)$st['id']?'selected':''?>><?=sd_appt_h((string)$st['name'])?></option><?php endforeach; ?></select><span class="booking-hint"><?= $service && !$stylists ? 'No active stylist is assigned to this service yet.' : 'Choose who you would like to book with.' ?></span></div>
        <div class="booking-field"><label for="date">3. Date</label><input id="date" type="date" name="date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+90 days'))?>" value="<?=sd_appt_h($date)?>" required><span class="booking-hint">Bookings are available up to 90 days ahead.</span></div>
      </div>
      <?php if ($service): ?><div class="booking-detail" style="margin:0 0 16px"><span>Selected service</span><strong><?=sd_appt_h((string)$service['name'])?> · PKR <?=number_format((float)$service['price'],2)?> · <?=(int)$service['duration_minutes']?> minutes</strong></div><?php endif; ?>
      <button class="booking-btn yellow" type="submit">Show available time slots <span aria-hidden="true">→</span></button>
    </form>

    <?php if ($service && $stylistId && sd_appt_valid_date($date)): ?>
      <section class="booking-card" aria-live="polite">
        <h2>Available times</h2>
        <p class="booking-sub"><?=sd_appt_h((string)$service['name'])?> with <?=sd_appt_h((string)(array_values(array_filter($stylists,fn($st)=>(int)$st['id']===$stylistId))[0]['name'] ?? 'your stylist'))?> · <?=(int)$service['duration_minutes']?> minutes</p>
        <?php if (!$slots): ?>
          <div class="booking-empty"><strong>No times available for this selection.</strong><br>Try another date or stylist. If this continues, ask the salon manager to confirm that the stylist has a weekly schedule and this service assigned.</div>
        <?php else: ?>
          <form method="post" action="booking.php">
            <input type="hidden" name="_csrf" value="<?=sd_appt_h(csrf_token())?>"><input type="hidden" name="action" value="book"><input type="hidden" name="service_id" value="<?=$serviceId?>"><input type="hidden" name="stylist_id" value="<?=$stylistId?>"><input type="hidden" name="appointment_date" value="<?=sd_appt_h($date)?>">
            <p class="booking-sub">Step 2 · Select an available start time, then enter your contact details.</p>
            <div class="booking-slots"><?php foreach($slots as $slot): ?><label class="booking-slot"><input type="radio" name="start_time" value="<?=sd_appt_h($slot)?>" required <?=$selectedTime===$slot?'checked':''?>> <?=sd_appt_h($slot)?></label><?php endforeach; ?></div>
            <h2 style="margin:18px 0 15px">Your details</h2>
            <div class="booking-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
              <div class="booking-field"><label for="customer_name">Full name</label><input id="customer_name" name="customer_name" maxlength="120" required autocomplete="name" value="<?=sd_appt_h((string)($_POST['customer_name']??''))?>"></div>
              <div class="booking-field"><label for="customer_phone">Phone number</label><input id="customer_phone" name="customer_phone" maxlength="40" required autocomplete="tel" value="<?=sd_appt_h((string)($_POST['customer_phone']??''))?>"></div>
              <div class="booking-field"><label for="customer_email">Email (optional)</label><input id="customer_email" type="email" name="customer_email" maxlength="254" autocomplete="email" value="<?=sd_appt_h((string)($_POST['customer_email']??''))?>"></div>
            </div>
            <div class="booking-field"><label for="notes">Anything we should know? (optional)</label><textarea id="notes" name="notes" maxlength="1000" placeholder="Preferences or service notes"><?=sd_appt_h((string)($_POST['notes']??''))?></textarea></div>
            <button class="booking-btn" type="submit">Confirm appointment <span aria-hidden="true">✓</span></button>
            <p class="booking-footer-note">Your contact details are used by the salon to manage this appointment.</p>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <div class="booking-steps" aria-label="Booking steps">
    <div class="booking-step"><b>01 · Choose your service</b><span>See prices and estimated service duration before selecting.</span></div>
    <div class="booking-step"><b>02 · Pick a time</b><span>Only open slots for the selected stylist and date are listed.</span></div>
    <div class="booking-step"><b>03 · Confirm your visit</b><span>Keep your booking code and private management link.</span></div>
  </div>
</section>
<script>
(function(){
  const form=document.getElementById('bookingFilter');
  const service=document.getElementById('service_id');
  const stylist=document.getElementById('stylist_id');
  if(service&&stylist&&form){
    service.addEventListener('change',function(){
      stylist.value='';
      stylist.disabled=true;
      form.requestSubmit();
    });
  }
})();
</script>
<?php sd_appt_page_end(); ?>
