<?php
declare(strict_types=1);

/**
 * Optional notifications. Email uses PHP mail() and requires a configured mail transport.
 * SMS uses Twilio only when SALONDIVA_TWILIO_SID, SALONDIVA_TWILIO_TOKEN,
 * SALONDIVA_TWILIO_FROM are configured. No provider credentials are stored in source.
 */
function sd_appt_notify(PDO $pdo, int $appointmentId, string $eventType, array $appointment): array
{
    $subjectMap = [
        'confirmation' => 'SalonDiva appointment confirmation',
        'rescheduled' => 'Your SalonDiva appointment was rescheduled',
        'cancelled' => 'Your SalonDiva appointment was cancelled',
    ];
    $subject = $subjectMap[$eventType] ?? 'SalonDiva appointment update';
    $message = "Hello " . (string)$appointment['customer_name'] . ",\n\n";
    if ($eventType === 'confirmation') $message .= "Your appointment is confirmed.\n";
    elseif ($eventType === 'rescheduled') $message .= "Your appointment has been rescheduled.\n";
    else $message .= "Your appointment has been cancelled.\n";
    $message .= "Booking code: " . (string)$appointment['booking_code'] . "\n";
    $message .= "Service: " . (string)$appointment['service_name_snapshot'] . "\n";
    $message .= "Stylist: " . (string)$appointment['stylist_name'] . "\n";
    $message .= "Date and time: " . date('D, d M Y H:i', strtotime((string)$appointment['starts_at'])) . "\n";
    if ($eventType !== 'cancelled') $message .= "Estimated price: PKR " . number_format((float)$appointment['service_price_snapshot'], 2) . "\n";
    $message .= "\nThank you for choosing SalonDiva.";
    if (!empty($appointment['manage_url'])) $message .= "\nManage or cancel this booking: " . (string)$appointment['manage_url'];
    $outcomes = [];

    $email = trim((string)($appointment['customer_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sent = false; $detail = 'PHP mail() returned false; configure a mail transport in PHP/XAMPP.';
        $from = getenv('SALONDIVA_MAIL_FROM') ?: '';
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $headers = "From: SalonDiva <{$from}>\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail($email, $subject, $message, $headers);
            $detail = $sent ? 'Accepted by PHP mail transport; final delivery is not guaranteed.' : $detail;
        } else {
            $detail = 'Skipped: set SALONDIVA_MAIL_FROM and configure PHP mail transport.';
        }
        $status = $sent ? 'sent' : 'skipped';
        $pdo->prepare('INSERT INTO appointment_notifications (appointment_id,channel,recipient,event_type,status,result_message) VALUES (?,?,?,?,?,?)')
            ->execute([$appointmentId, 'email', $email, $eventType, $status, $detail]);
        $outcomes[] = ['channel'=>'email','status'=>$status,'detail'=>$detail];
    }

    $phone = trim((string)($appointment['customer_phone'] ?? ''));
    $sid = getenv('SALONDIVA_TWILIO_SID') ?: '';
    $token = getenv('SALONDIVA_TWILIO_TOKEN') ?: '';
    $fromPhone = getenv('SALONDIVA_TWILIO_FROM') ?: '';
    if ($phone !== '') {
        if ($sid !== '' && $token !== '' && $fromPhone !== '' && function_exists('curl_init')) {
            $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['To'=>$phone,'From'=>$fromPhone,'Body'=>$message]),
                CURLOPT_USERPWD => $sid . ':' . $token,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
            ]);
            $response = curl_exec($ch); $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
            $sent = $response !== false && $httpCode >= 200 && $httpCode < 300;
            $status = $sent ? 'sent' : 'failed';
            $detail = $sent ? 'Accepted by Twilio; delivery depends on carrier.' : ('Twilio request failed (HTTP ' . $httpCode . ')' . ($curlError ? ': '.$curlError : '.'));
        } else {
            $status = 'skipped';
            $detail = 'Skipped: configure Twilio environment variables and enable PHP cURL to send SMS.';
        }
        $pdo->prepare('INSERT INTO appointment_notifications (appointment_id,channel,recipient,event_type,status,result_message) VALUES (?,?,?,?,?,?)')
            ->execute([$appointmentId, 'sms', $phone, $eventType, $status, $detail]);
        $outcomes[] = ['channel'=>'sms','status'=>$status,'detail'=>$detail];
    }
    return $outcomes;
}
