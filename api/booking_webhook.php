<?php
/**
 * Booking Webhook — Multi-tenant SaaS
 *
 * Receives booking data from salon websites and creates appointments in their POS.
 * Each salon sends their ID + API key to authenticate.
 *
 * Usage:
 *   POST /nail-booking/api/booking_webhook.php
 *   Headers: Content-Type: application/json
 *   Body: {
 *     "salon_id": 1,
 *     "api_key": "sk_...",
 *     "name": "Jane Doe",
 *     "phone": "(801) 555-0123",
 *     "email": "jane@email.com",
 *     "dob": "1990-01-15",
 *     "service": "Acrylic Full Set",
 *     "date": "2026-09-18",
 *     "time": "10:15",
 *     "technician_name": "Tom",
 *     "notes": "First time customer"
 *   }
 */

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/tenant.php';

    // Get JSON payload
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        throw new Exception('Invalid JSON payload');
    }

    // Validate required fields
    $required = ['salon_id', 'api_key', 'name', 'phone', 'email', 'service', 'date', 'time'];
    foreach ($required as $field) {
        if (empty($payload[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $salon_id = (int)$payload['salon_id'];
    $api_key = trim($payload['api_key']);

    // TODO: Validate API key (query salon settings for stored key)
    // For now, accept all with salon_id validation
    if ($salon_id < 1) {
        throw new Exception('Invalid salon_id');
    }

    // Connect as the salon (set tenant context)
    $pdo = db();

    // Verify salon exists
    $salon = $pdo->query("SELECT id FROM business_settings WHERE id = ? AND tenant_id = ?")->fetchOne($salon_id, $salon_id);
    if (!$salon) {
        throw new Exception('Salon not found');
    }

    // Extract data
    $name = trim($payload['name']);
    $phone = trim($payload['phone']);
    $email = trim($payload['email']);
    $dob = !empty($payload['dob']) ? trim($payload['dob']) : null;
    $service_name = trim($payload['service']);
    $date = trim($payload['date']);  // YYYY-MM-DD
    $time = trim($payload['time']);  // HH:MM
    $technician_name = !empty($payload['technician_name']) ? trim($payload['technician_name']) : null;
    $notes = !empty($payload['notes']) ? trim($payload['notes']) : null;

    // Find or create customer
    $customer = $pdo->query(
        "SELECT id FROM pos_clients WHERE phone = ? AND tenant_id = ?"
    )->fetchOne($phone, $salon_id);

    if ($customer) {
        $customer_id = $customer['id'];
        // Update existing customer
        $pdo->query(
            "UPDATE pos_clients SET name = ?, email = ?, dob = ? WHERE id = ? AND tenant_id = ?",
            [$name, $email, $dob, $customer_id, $salon_id]
        )->execute();
    } else {
        // Create new customer
        $pdo->query(
            "INSERT INTO pos_clients (tenant_id, name, phone, email, dob) VALUES (?, ?, ?, ?, ?)",
            [$salon_id, $name, $phone, $email, $dob]
        )->execute();
        $customer_id = $pdo->lastInsertId();
    }

    // Find service ID by name
    $service = $pdo->query(
        "SELECT id FROM services WHERE name = ? AND tenant_id = ? LIMIT 1"
    )->fetchOne($service_name, $salon_id);

    if (!$service) {
        throw new Exception("Service not found: $service_name");
    }
    $service_id = $service['id'];

    // Find technician ID by name (optional)
    $technician_id = null;
    if ($technician_name) {
        $tech = $pdo->query(
            "SELECT id FROM technicians WHERE name = ? AND tenant_id = ? LIMIT 1"
        )->fetchOne($technician_name, $salon_id);
        if ($tech) {
            $technician_id = $tech['id'];
        }
    }

    // Parse datetime
    $datetime = "$date $time:00";

    // Create appointment
    $pdo->query(
        "INSERT INTO appointments (tenant_id, customer_id, service_id, technician_id, appointment_date, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [$salon_id, $customer_id, $service_id, $technician_id, $datetime, $notes]
    )->execute();

    $booking_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'booking_id' => $booking_id,
        'customer_id' => $customer_id,
        'salon_id' => $salon_id,
        'message' => 'Appointment created successfully',
        'appointment_time' => $datetime
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
