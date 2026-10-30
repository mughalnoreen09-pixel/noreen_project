<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Karachi');

function sd_bill_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sd_bill_money(mixed $value): ?float
{
    if (!is_numeric($value)) return null;
    $number = (float)$value;
    return is_finite($number) ? $number : null;
}

function sd_bill_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Karachi'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && $date->format('Y-m-d') === $value
        && (!is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function sd_bill_balance(PDO $pdo, int $invoiceId): float
{
    $stmt = $pdo->prepare('SELECT i.total_amount - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0) FROM invoices i WHERE i.id=?');
    $stmt->execute([$invoiceId]);
    return max(0.0, (float)$stmt->fetchColumn());
}

/** Send email via PHP mail(). A configured mail transport and SALONDIVA_MAIL_FROM are required. */
function sd_notify_email(string $recipient, string $subject, string $message): array
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return ['status' => 'skipped', 'detail' => 'Recipient email address is missing or invalid.'];
    $from = trim((string)(getenv('SALONDIVA_MAIL_FROM') ?: ''));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) return ['status' => 'skipped', 'detail' => 'Set SALONDIVA_MAIL_FROM and configure a PHP mail transport.'];
    $headers = "From: SalonDiva <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($recipient, $subject, $message, $headers);
    return $ok
        ? ['status' => 'sent', 'detail' => 'Accepted by PHP mail transport; final delivery is not guaranteed.']
        : ['status' => 'failed', 'detail' => 'PHP mail() failed; verify the configured mail transport.'];
}

/** Send SMS through Twilio only when environment credentials and PHP cURL are configured. */
function sd_notify_sms(string $recipient, string $message): array
{
    $sid = trim((string)(getenv('SALONDIVA_TWILIO_SID') ?: ''));
    $token = trim((string)(getenv('SALONDIVA_TWILIO_TOKEN') ?: ''));
    $from = trim((string)(getenv('SALONDIVA_TWILIO_FROM') ?: ''));
    if ($recipient === '') return ['status' => 'skipped', 'detail' => 'Recipient phone is missing.'];
    if ($sid === '' || $token === '' || $from === '' || !function_exists('curl_init')) {
        return ['status' => 'skipped', 'detail' => 'Configure Twilio environment credentials and enable PHP cURL to send SMS.'];
    }
    $curl = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['To' => $recipient, 'From' => $from, 'Body' => $message]),
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body !== false && $httpCode >= 200 && $httpCode < 300) return ['status' => 'sent', 'detail' => 'Accepted by Twilio; delivery depends on carrier.'];
    return ['status' => 'failed', 'detail' => 'Twilio request failed (HTTP ' . $httpCode . ')' . ($error !== '' ? ': ' . $error : '.')];
}

/** Log every delivery attempt; already-sent dedupe keys are never sent twice. */
function sd_notify_once(PDO $pdo, string $entityType, int $entityId, string $recipientType, string $channel,
    string $eventType, string $recipient, string $dedupeKey, string $subject, string $body): array
{
    $check = $pdo->prepare('SELECT status FROM notification_logs WHERE dedupe_key=?');
    $check->execute([$dedupeKey]);
    if ($check->fetchColumn() === 'sent') return ['status' => 'skipped', 'detail' => 'Already sent; duplicate delivery skipped.'];
    $outcome = $channel === 'email' ? sd_notify_email($recipient, $subject, $body) : sd_notify_sms($recipient, $body);
    $sql = 'INSERT INTO notification_logs (entity_type,entity_id,recipient_type,channel,event_type,recipient,dedupe_key,status,result_message,attempted_at,sent_at)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),IF(?="sent",NOW(),NULL))
            ON DUPLICATE KEY UPDATE status=VALUES(status),result_message=VALUES(result_message),attempted_at=NOW(),sent_at=IF(VALUES(status)="sent",NOW(),sent_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entityType, $entityId, $recipientType, $channel, $eventType, substr($recipient, 0, 254), $dedupeKey,
        $outcome['status'], $outcome['detail'], $outcome['status']]);
    return $outcome;
}

/** Dispatch upcoming appointment reminders, overdue invoice reminders, and daily low-stock alerts. */
function sd_notifications_run(PDO $pdo): array
{
    $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'considered' => 0];
    $record = static function(array $outcome) use (&$stats): void {
        $stats['considered']++;
        $key = $outcome['status'] ?? 'skipped';
        $stats[$key] = ($stats[$key] ?? 0) + 1;
    };

    // Appointment reminders to customers and the assigned stylist for confirmed bookings in the next 24 hours.
    $appointments = $pdo->query("SELECT a.id,a.booking_code,a.starts_at,a.service_name_snapshot,a.service_price_snapshot,c.name customer_name,c.email customer_email,c.phone customer_phone,u.name stylist_name,u.email stylist_email
        FROM appointments a JOIN customers c ON c.id=a.customer_id JOIN users u ON u.id=a.stylist_user_id
        WHERE a.status='confirmed' AND a.starts_at>=NOW() AND a.starts_at<=DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ORDER BY a.starts_at LIMIT 500")->fetchAll();
    foreach ($appointments as $a) {
        $date = date('D, d M Y H:i', strtotime((string)$a['starts_at']));
        $baseMessage = "SalonDiva appointment reminder\nBooking: {$a['booking_code']}\nService: {$a['service_name_snapshot']}\nStylist: {$a['stylist_name']}\nWhen: {$date}\nEstimated service price: PKR " . number_format((float)$a['service_price_snapshot'], 2);
        $id = (int)$a['id'];
        $email = trim((string)($a['customer_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $record(sd_notify_once($pdo,'appointment',$id,'customer','email','appointment_reminder_24h',$email,"appt24_{$id}_customer_email",'Upcoming SalonDiva appointment',$baseMessage . "\n\nWe look forward to seeing you."));
        }
        $phone = trim((string)($a['customer_phone'] ?? ''));
        if ($phone !== '') $record(sd_notify_once($pdo,'appointment',$id,'customer','sms','appointment_reminder_24h',$phone,"appt24_{$id}_customer_sms",'',$baseMessage));
        $staffEmail = trim((string)($a['stylist_email'] ?? ''));
        if ($staffEmail !== '' && filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
            $staffMessage = "Staff reminder for an upcoming SalonDiva appointment\nCustomer: {$a['customer_name']}\nBooking: {$a['booking_code']}\nService: {$a['service_name_snapshot']}\nWhen: {$date}";
            $record(sd_notify_once($pdo,'appointment',$id,'staff','email','appointment_reminder_24h',$staffEmail,"appt24_{$id}_staff_email",'Upcoming client appointment',$staffMessage));
        }
    }

    // Pending invoices overdue before today: notify the customer and active administrators.
    $overdue = $pdo->query("SELECT i.id,i.invoice_number,i.due_date,i.total_amount,c.name customer_name,c.email customer_email,c.phone customer_phone,
        (i.total_amount-COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id),0)) balance
        FROM invoices i JOIN customers c ON c.id=i.customer_id
        WHERE i.status IN ('open','partially_paid') AND i.due_date<CURDATE()
        HAVING balance>0.009 ORDER BY i.due_date LIMIT 500")->fetchAll();
    $admins = $pdo->query("SELECT id,name,email FROM users WHERE role='admin' AND is_active=1 AND account_status='approved' AND email<>'' ORDER BY id")->fetchAll();
    foreach ($overdue as $inv) {
        $id = (int)$inv['id'];
        $body = "Hello {$inv['customer_name']},\n\nOur records show that invoice {$inv['invoice_number']} is overdue.\nDue date: {$inv['due_date']}\nOutstanding balance: PKR " . number_format((float)$inv['balance'], 2) . "\n\nPlease contact SalonDiva if you have already paid. Thank you.";
        $email = trim((string)($inv['customer_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $record(sd_notify_once($pdo,'invoice',$id,'customer','email','overdue_payment',$email,"overdue_{$id}_customer_email",'SalonDiva payment reminder',$body));
        $phone = trim((string)($inv['customer_phone'] ?? ''));
        if ($phone !== '') {
            $sms = "SalonDiva payment reminder: invoice {$inv['invoice_number']} is overdue. Balance PKR " . number_format((float)$inv['balance'], 2) . ". Please contact the salon if already paid.";
            $record(sd_notify_once($pdo,'invoice',$id,'customer','sms','overdue_payment',$phone,"overdue_{$id}_customer_sms",'',$sms));
        }
        foreach ($admins as $admin) {
            $adminEmail = trim((string)$admin['email']);
            if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) continue;
            $adminBody = "Outstanding invoice alert\nInvoice: {$inv['invoice_number']}\nCustomer: {$inv['customer_name']}\nDue date: {$inv['due_date']}\nOutstanding balance: PKR " . number_format((float)$inv['balance'], 2);
            $record(sd_notify_once($pdo,'invoice',$id,'admin','email','overdue_payment',$adminEmail,"overdue_{$id}_admin_".(int)$admin['id'].'_email','SalonDiva overdue invoice alert',$adminBody));
        }
    }

    // Low stock notices are deduplicated per item/day to avoid repeated messages every scheduler run.
    $lowStock = $pdo->query('SELECT id,name,quantity_on_hand,reorder_level,unit FROM inventory_items WHERE is_active=1 AND quantity_on_hand<=reorder_level ORDER BY name LIMIT 500')->fetchAll();
    $dayKey = date('Ymd');
    foreach ($lowStock as $item) {
        foreach ($admins as $admin) {
            $email = trim((string)$admin['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $id = (int)$item['id'];
            $body = "SalonDiva low-stock alert\nItem: {$item['name']}\nAvailable: " . number_format((float)$item['quantity_on_hand'], 3) . " {$item['unit']}\nReorder level: " . number_format((float)$item['reorder_level'], 3) . "\n\nOpen the Inventory page to review stock.";
            $record(sd_notify_once($pdo,'inventory',$id,'admin','email','low_inventory',$email,"lowstock_{$id}_{$dayKey}_admin_".(int)$admin['id'].'_email','SalonDiva low-stock alert',$body));
        }
    }
    return $stats;
}
