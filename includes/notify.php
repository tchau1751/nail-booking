<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Fires both the confirmation email and SMS for a booking, logging each
 * attempt to notification_log regardless of outcome. Safe to call even
 * when RESEND_API_KEY / TWILIO_* are blank — those channels are recorded
 * as "skipped" so the booking flow never fails because of them.
 */
function send_booking_notifications(array $booking, array $service, array $business, ?array $staff = null): void
{
    $when = date('l, F j', strtotime($booking['appointment_date']))
        . ' at ' . date('g:i A', strtotime($booking['appointment_time']));
    $withLine = $staff ? "\nWith: {$staff['full_name']}" : '';
    $withInline = $staff ? " with {$staff['full_name']}" : '';

    $subject = 'Your appointment is confirmed — ' . $business['business_name'];
    $body = sprintf(
        "Hi %s,\n\nYour appointment is confirmed:\n\n%s\n%s\n\nWhen: %s%s\nWhere: %s\n\nSee you soon!\n%s",
        $booking['full_name'],
        $service['name'],
        format_duration((int) $service['duration_minutes']),
        $when,
        $withLine,
        $business['business_address'],
        $business['business_name']
    );

    $sms = sprintf(
        '%s: appointment confirmed for %s (%s) on %s%s. Reply to reschedule. %s',
        $business['business_name'],
        $service['name'],
        format_duration((int) $service['duration_minutes']),
        $when,
        $withInline,
        $business['business_phone']
    );

    if (!empty($booking['email'])) {
        send_confirmation_email($booking['id'], $booking['email'], $subject, $body);
    } else {
        log_notification($booking['id'], 'email', 'skipped', 'No email address provided.');
    }

    if (!empty($booking['phone'])) {
        send_confirmation_sms($booking['id'], $booking['phone'], $sms);
    } else {
        log_notification($booking['id'], 'sms', 'skipped', 'No phone number provided.');
    }
}

/**
 * Same as send_booking_notifications() but for a combo booking (2-3
 * services back-to-back, e.g. Acrylic + Pedicure + Eyebrow Wax): sends ONE
 * combined confirmation listing every service, rather than one per service,
 * logged against $primaryBooking['id'] (the first booking in the group).
 */
function send_combo_booking_notifications(array $primaryBooking, array $services, array $business, ?array $staff = null): void
{
    $when = date('l, F j', strtotime($primaryBooking['appointment_date']))
        . ' at ' . date('g:i A', strtotime($primaryBooking['appointment_time']));
    $withLine = $staff ? "\nWith: {$staff['full_name']}" : '';
    $withInline = $staff ? " with {$staff['full_name']}" : '';

    $totalMinutes = array_sum(array_map(fn($s) => (int) $s['duration_minutes'], $services));
    $serviceNames = implode(', ', array_map(fn($s) => $s['name'], $services));
    $serviceLines = implode("\n", array_map(fn($s) => '- ' . $s['name'] . ' (' . format_duration((int) $s['duration_minutes']) . ')', $services));

    $subject = 'Your appointment is confirmed — ' . $business['business_name'];
    $body = sprintf(
        "Hi %s,\n\nYour appointment is confirmed:\n\n%s\nTotal: %s\n\nWhen: %s%s\nWhere: %s\n\nSee you soon!\n%s",
        $primaryBooking['full_name'],
        $serviceLines,
        format_duration($totalMinutes),
        $when,
        $withLine,
        $business['business_address'],
        $business['business_name']
    );

    $sms = sprintf(
        '%s: appointment confirmed for %s (%s) on %s%s. Reply to reschedule. %s',
        $business['business_name'],
        $serviceNames,
        format_duration($totalMinutes),
        $when,
        $withInline,
        $business['business_phone']
    );

    if (!empty($primaryBooking['email'])) {
        send_confirmation_email($primaryBooking['id'], $primaryBooking['email'], $subject, $body);
    } else {
        log_notification($primaryBooking['id'], 'email', 'skipped', 'No email address provided.');
    }

    if (!empty($primaryBooking['phone'])) {
        send_confirmation_sms($primaryBooking['id'], $primaryBooking['phone'], $sms);
    } else {
        log_notification($primaryBooking['id'], 'sms', 'skipped', 'No phone number provided.');
    }
}

/**
 * Sends a reminder SMS for a booking that hasn't been reminded yet.
 * Intended to be called from cron/send-reminders.php on a schedule.
 */
function send_reminder_sms_for_booking(array $booking, array $service, array $business): bool
{
    if (empty($booking['phone'])) {
        log_notification($booking['id'], 'sms', 'skipped', 'No phone number on file for reminder.');
        return false;
    }

    $when = date('l', strtotime($booking['appointment_date'])) . ' at ' . date('g:i A', strtotime($booking['appointment_time']));
    $message = sprintf(
        'Reminder from %s: your %s appointment is %s. Reply to reschedule or call %s.',
        $business['business_name'],
        $service['name'],
        $when,
        $business['business_phone']
    );

    send_confirmation_sms((int) $booking['id'], $booking['phone'], $message);
    return true;
}

function send_confirmation_email(int $bookingId, string $toEmail, string $subject, string $body): void
{
    if (empty(RESEND_API_KEY)) {
        log_notification($bookingId, 'email', 'skipped', 'RESEND_API_KEY not configured.');
        return;
    }

    $payload = json_encode([
        'from' => RESEND_FROM_EMAIL,
        'to' => [$toEmail],
        'subject' => $subject,
        'text' => $body,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_notification($bookingId, 'email', 'failed', $error);
        return;
    }

    log_notification($bookingId, 'email', $httpCode >= 200 && $httpCode < 300 ? 'sent' : 'failed', $response);
}

/**
 * Normalizes a US/Canada phone number to E.164 (+1XXXXXXXXXX) so Twilio
 * accepts it. Twilio rejects bare 10-digit numbers like "3854534439".
 */
function normalize_phone_e164(string $phone): string
{
    $trimmed = trim($phone);
    if ($trimmed !== '' && $trimmed[0] === '+') {
        return '+' . preg_replace('/\D/', '', substr($trimmed, 1));
    }
    $digits = preg_replace('/\D/', '', $trimmed);
    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    return '+' . $digits;
}

function send_confirmation_sms(int $bookingId, string $toPhone, string $message): void
{
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_FROM_NUMBER)) {
        log_notification($bookingId, 'sms', 'skipped', 'Twilio credentials not configured.');
        return;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
    $fields = http_build_query([
        'To' => normalize_phone_e164($toPhone),
        'From' => TWILIO_FROM_NUMBER,
        'Body' => $message,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_notification($bookingId, 'sms', 'failed', $error);
        return;
    }

    log_notification($bookingId, 'sms', $httpCode >= 200 && $httpCode < 300 ? 'sent' : 'failed', $response);
}

function log_notification(int $bookingId, string $channel, string $status, string $response): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO notification_log (booking_id, channel, status, provider_response) VALUES (:booking_id, :channel, :status, :response)'
    );
    $stmt->execute([
        'booking_id' => $bookingId,
        'channel' => $channel,
        'status' => $status,
        'response' => mb_substr($response, 0, 2000),
    ]);
}

/**
 * Sends an SMS to a client not tied to a specific booking (promotions,
 * birthday messages) and logs it to client_message_log. Returns the status
 * ('sent' | 'failed' | 'skipped') so callers can tally results.
 */
function send_client_sms(?int $clientId, string $phone, string $message, string $messageType): string
{
    $pdo = get_db();
    $status = 'skipped';
    $response = 'Twilio credentials not configured.';

    if (!empty(TWILIO_ACCOUNT_SID) && !empty(TWILIO_AUTH_TOKEN) && !empty(TWILIO_FROM_NUMBER)) {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
        $fields = http_build_query([
            'To' => normalize_phone_e164($phone),
            'From' => TWILIO_FROM_NUMBER,
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $status = 'failed';
            $response = $curlError;
        } else {
            $status = ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO client_message_log (client_id, phone, message_type, status, message, provider_response)
         VALUES (:client_id, :phone, :type, :status, :message, :response)'
    );
    $stmt->execute([
        'client_id' => $clientId,
        'phone' => $phone,
        'type' => $messageType,
        'status' => $status,
        'message' => $message,
        'response' => mb_substr((string) $response, 0, 2000),
    ]);

    return $status;
}

/**
 * Sends a gift card's QR/redeem link via SMS or email and logs the attempt
 * to gift_card_sends. Returns 'sent' | 'failed' | 'skipped'.
 */
function send_gift_card_qr(int $giftCardId, string $channel, string $destination, string $code, float $balance): string
{
    $pdo = get_db();
    $link = 'https://diamondnaillayton.com/gift-card-qr.php?code=' . urlencode($code);
    $amountStr = format_price($balance);

    $status = 'skipped';
    $response = '';

    if ($channel === 'sms') {
        $message = sprintf(
            'Diamond Nails & Spa gift card (%s): code %s. View & save your QR code here: %s',
            $amountStr,
            $code,
            $link
        );
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_FROM_NUMBER)) {
            $response = 'Twilio credentials not configured.';
        } else {
            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
            $fields = http_build_query([
                'To' => normalize_phone_e164($destination),
                'From' => TWILIO_FROM_NUMBER,
                'Body' => $message,
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $status = $curlError ? 'failed' : (($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed');
            if ($curlError) $response = $curlError;
        }
    } elseif ($channel === 'email') {
        $subject = 'Your Diamond Nails & Spa gift card';
        $body = sprintf(
            "Here's your gift card!\n\nCode: %s\nBalance: %s\n\nView & save your QR code here: %s\n\nShow this code or QR at check-in to redeem.\n\nDiamond Nails & Spa",
            $code,
            $amountStr,
            $link
        );
        if (empty(RESEND_API_KEY)) {
            $response = 'RESEND_API_KEY not configured.';
        } else {
            $payload = json_encode([
                'from' => RESEND_FROM_EMAIL,
                'to' => [$destination],
                'subject' => $subject,
                'text' => $body,
            ]);
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . RESEND_API_KEY,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $status = $curlError ? 'failed' : (($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed');
            if ($curlError) $response = $curlError;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO gift_card_sends (gift_card_id, channel, destination, status, provider_response)
         VALUES (:gift_card_id, :channel, :destination, :status, :response)'
    );
    $stmt->execute([
        'gift_card_id' => $giftCardId,
        'channel' => $channel,
        'destination' => $destination,
        'status' => $status,
        'response' => mb_substr((string) $response, 0, 2000),
    ]);

    return $status;
}
