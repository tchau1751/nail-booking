<?php
// ============================================================
//  Cart + checkout JSON API. Every response returns the freshly
//  recomputed ticket so the tablet never has to do its own math.
// ============================================================
require_once __DIR__ . '/../includes/pos.php';
require_once __DIR__ . '/../includes/salon.php';
require_once __DIR__ . '/../includes/rewards.php';
if (!isLoggedIn()) jsonOut(['error' => 'Not signed in.'], 401);
// Same second lock the page forms get: SameSite=Lax is a browser default, not
// a guarantee this endpoint is allowed to rely on.
if (!posCsrfValid($_POST['_csrf'] ?? $_GET['_csrf'] ?? null)) {
    jsonOut(['error' => 'This till was signed out — sign in again.'], 419);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'state';

function techNames(): array {
    static $n = null;
    if ($n === null) {
        $n = [];
        foreach (fetchAll('SELECT id, name FROM technicians') as $t) $n[(int)$t['id']] = $t['name'];
    }
    return $n;
}

function ticketPayload(array $extra = []): array {
    $c = cart();
    $t = cartTotals();
    $names = techNames();
    $tips  = allocateTips($t['lines'], (float)$t['tip']);
    $lines = [];
    foreach ($t['lines'] as $k => $l) {
        $tech = $l['technician_id'] ? (int)$l['technician_id'] : null;
        $lines[] = [
            'key' => $k, 'name' => $l['name'], 'type' => $l['type'], 'ref_id' => $l['ref_id'],
            'qty' => $l['qty'], 'price' => (float)$l['price'],
            'discount' => (float)$l['discount'], 'total' => (float)$l['line_total'],
            'technician_id' => $tech,
            'technician'    => $tech ? ($names[$tech] ?? 'Technician ' . $tech) : null,
            'needs_tech'    => $l['type'] === 'service' && !$tech,
            'tip'           => round($tips[$k] ?? 0, 2),
        ];
    }
    $ten    = cartTenders();
    $client = $c['client_id'] ? fetchOne('SELECT id, full_name, points FROM pos_clients WHERE id=?', [$c['client_id']]) : null;

    return $extra + [
        'ok'    => true,
        'lines' => $lines,
        'count' => (int)$t['count'],
        'totals' => [
            'subtotal' => $t['subtotal'], 'discount' => $t['discount'],
            'tax' => $t['tax'], 'tip' => $t['tip'], 'total' => $t['total'],
            'tax_label' => $t['tax_label'], 'tax_rate' => $t['tax_rate'],
            'gift' => $ten['gift'], 'points_value' => $ten['points_value'],
            'due' => cartDue(),
        ],
        'meta' => [
            'customer_name'  => $c['customer_name'],
            'customer_phone' => $c['customer_phone'],
            'appointment_id' => $c['appointment_id'],
            'checkin_id'     => $c['checkin_id'],
            'technician_id'  => $c['technician_id'],
            'discount_type'  => $c['discount_type'],
            'discount_value' => (float)$c['discount_value'],
            'note'           => $c['note'],
            'client'         => $client ? [
                'id' => (int)$client['id'], 'name' => $client['full_name'],
                'points' => (int)$client['points'],
                'points_value' => pointsToMoney((int)$client['points']),
            ] : null,
            'tip_method'     => $c['tip_method'] === 'cash' ? 'cash' : 'card',
            'missing_tech'   => array_values(cartLinesMissingTech()),
            'points_redeem'  => (int)$c['points_redeem'],
            'gift_cards'     => array_values($c['gift_cards']),
        ],
        'currency' => posSettings()['currency_symbol'],
    ];
}

try {
    switch ($action) {

        case 'add_service':
            $s = fetchOne('SELECT * FROM services WHERE id=? AND is_active=1', [(int)$_POST['id']]);
            if (!$s) jsonOut(['error' => 'Service not found.'], 404);
            $tech = (int)($_POST['technician_id'] ?? 0) ?: null;
            if ($tech && !fetchOne('SELECT 1 x FROM technicians WHERE id=? AND is_active=1', [$tech])) {
                jsonOut(['error' => 'That technician is not on the floor.'], 422);
            }
            cartAdd('service', (int)$s['id'], $s['name'], (float)$s['price'], 1, $tech, 1);
            break;

        case 'set_line_tech':
            $tech = (int)($_POST['technician_id'] ?? 0) ?: null;
            if ($tech && !fetchOne('SELECT 1 x FROM technicians WHERE id=? AND is_active=1', [$tech])) {
                jsonOut(['error' => 'That technician is not on the floor.'], 422);
            }
            cartSetLineTech((string)$_POST['key'], $tech);
            break;

        case 'add_product':
            $p = fetchOne('SELECT * FROM pos_products WHERE id=? AND is_active=1', [(int)$_POST['id']]);
            if (!$p) jsonOut(['error' => 'Product not found.'], 404);
            cartAdd('product', (int)$p['id'], $p['name'], (float)$p['price'], 1, null, (int)$p['is_taxable']);
            break;

        case 'scan':   // barcode wedge / manual code entry
            $code = trim($_POST['code'] ?? '');
            $p = fetchOne('SELECT * FROM pos_products WHERE is_active=1 AND (barcode=? OR sku=?)', [$code, $code]);
            if (!$p) jsonOut(['error' => 'No product matches "' . $code . '".'], 404);
            cartAdd('product', (int)$p['id'], $p['name'], (float)$p['price'], 1, null, (int)$p['is_taxable']);
            jsonOut(ticketPayload(['scanned' => $p['name']]));

        case 'add_custom':
            $name  = trim($_POST['name'] ?? '') ?: 'Custom item';
            $price = round((float)($_POST['price'] ?? 0), 2);
            if ($price <= 0) jsonOut(['error' => 'Enter an amount above zero.'], 422);
            // A custom line is a price typed by hand, which is a discount with
            // extra steps: ring the $95 full set as a $60 custom and the
            // manager approval on Discount never fires. Same gate, then.
            if (!managerApproved()) {
                jsonOut(['error' => 'A manager has to approve a custom amount.', 'needs_manager' => true], 403);
            }
            $tech = (int)($_POST['technician_id'] ?? 0) ?: null;
            if ($tech && !fetchOne('SELECT 1 x FROM technicians WHERE id=? AND is_active=1', [$tech])) {
                jsonOut(['error' => 'That technician is not on the floor.'], 422);
            }
            // Taxed on the same rule as a service, not always-taxable: a custom
            // line at a nail bar is nearly always work, and work is untaxed in
            // most states.
            cartAdd('custom', null, $name, $price, 1, $tech, 1);
            if (!hasRole('manager')) clearManagerApproval();
            break;

        case 'set_qty':
            cartSetQty((string)$_POST['key'], (int)$_POST['qty']);
            break;

        case 'remove':
            cartRemove((string)$_POST['key']);
            break;

        case 'clear':
            cartReset();
            break;

        case 'set_meta':
            $c = &cart();
            foreach (['customer_name','customer_phone','note'] as $f) {
                if (isset($_POST[$f])) $c[$f] = trim((string)$_POST[$f]);
            }
            if (isset($_POST['technician_id']))  $c['technician_id']  = ((int)$_POST['technician_id']) ?: null;
            if (isset($_POST['appointment_id'])) $c['appointment_id'] = ((int)$_POST['appointment_id']) ?: null;
            break;

        case 'set_discount':
            $c = &cart();
            $wanted = max(0, round((float)($_POST['value'] ?? 0), 2));
            // Money off the ticket is a manager's call. Enforced here on the
            // server — hiding the button would stop nobody who can open a
            // browser console. Clearing a discount back to zero is always fine.
            if ($wanted > 0 && !managerApproved()) {
                jsonOut(['error' => 'A manager has to approve a discount.', 'needs_manager' => true], 403);
            }
            $c['discount_type']  = ($_POST['type'] ?? 'amount') === 'percent' ? 'percent' : 'amount';
            $c['discount_value'] = $wanted;
            // One approval, one discount — the next one asks again.
            if ($wanted > 0 && !hasRole('manager')) clearManagerApproval();
            break;

        case 'set_tip':
            $c = &cart();
            $c['tip'] = max(0, round((float)($_POST['value'] ?? 0), 2));
            // Cash tips are handed over at the chair and are already in the
            // technician's pocket; card tips the shop still owes them. Payroll
            // cannot tell the two apart later, so it is recorded now.
            if (isset($_POST['method'])) {
                $c['tip_method'] = $_POST['method'] === 'cash' ? 'cash' : 'card';
            }
            break;

        case 'load_appointment':
            $a = fetchOne('SELECT a.*, s.name AS service_name, s.price AS service_price
                           FROM appointments a JOIN services s ON s.id=a.service_id
                           WHERE a.id=?', [(int)$_POST['id']]);
            if (!$a) jsonOut(['error' => 'Appointment not found.'], 404);
            cartReset();
            $c = &cart();
            $c['customer_name']  = $a['full_name'];
            $c['customer_phone'] = $a['phone'];
            $c['appointment_id'] = (int)$a['id'];
            $c['technician_id']  = $a['technician_id'] ? (int)$a['technician_id'] : null;
            cartAdd('service', (int)$a['service_id'], $a['service_name'], (float)$a['service_price'], 1,
                    $a['technician_id'] ? (int)$a['technician_id'] : null, 1);
            break;

        case 'load_checkin':
            $k = fetchOne('SELECT c.*, s.name AS service_name, s.price AS service_price
                           FROM pos_checkins c LEFT JOIN services s ON s.id=c.service_id
                           WHERE c.id=?', [(int)$_POST['id']]);
            if (!$k) jsonOut(['error' => 'Check-in not found.'], 404);
            cartReset();
            $c = &cart();
            $c['customer_name']  = $k['guest_name'];
            $c['customer_phone'] = $k['guest_phone'];
            $c['client_id']      = $k['client_id'] ? (int)$k['client_id'] : null;
            $c['checkin_id']     = (int)$k['id'];
            $c['technician_id']  = $k['assigned_tech_id'] ? (int)$k['assigned_tech_id'] : null;
            $c['appointment_id'] = $k['appointment_id'] ? (int)$k['appointment_id'] : null;
            if ($k['service_id']) {
                cartAdd('service', (int)$k['service_id'], $k['service_name'], (float)$k['service_price'], 1,
                        $k['assigned_tech_id'] ? (int)$k['assigned_tech_id'] : null, 1);
            }
            break;

        case 'attach_client':
            $cl = fetchOne('SELECT * FROM pos_clients WHERE id=?', [(int)$_POST['id']]);
            if (!$cl) jsonOut(['error' => 'Client not found.'], 404);
            $c = &cart();
            $c['client_id']      = (int)$cl['id'];
            $c['customer_name']  = $cl['full_name'];
            $c['customer_phone'] = $cl['phone'];
            if (!$c['technician_id'] && $cl['preferred_tech_id']) $c['technician_id'] = (int)$cl['preferred_tech_id'];
            break;

        case 'find_client':
            $term = trim($_POST['term'] ?? '');
            if ($term === '') jsonOut(['ok' => true, 'results' => []]);
            $digits = normalisePhone($term);
            $rows = fetchAll('SELECT id, full_name, phone, points, total_visits FROM pos_clients
                              WHERE is_active=1 AND (full_name LIKE ? OR phone LIKE ?)
                              ORDER BY last_visit IS NULL, last_visit DESC LIMIT 12',
                             ["%$term%", '%' . ($digits ?: $term) . '%']);
            foreach ($rows as &$r) { $r['phone'] = formatPhone($r['phone']); $r['points'] = (int)$r['points']; }
            jsonOut(['ok' => true, 'results' => $rows]);

        case 'new_client':
            $cl = clientUpsert(trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''));
            $c = &cart();
            $c['client_id']      = (int)$cl['id'];
            $c['customer_name']  = $cl['full_name'];
            $c['customer_phone'] = $cl['phone'];
            break;

        case 'detach_client':
            $c = &cart();
            $c['client_id'] = null;
            $c['points_redeem'] = 0;
            break;

        case 'add_giftcard':
            $amt = round((float)($_POST['amount'] ?? 0), 2);
            if ($amt <= 0) jsonOut(['error' => 'Enter a gift card amount.'], 422);
            $to = trim($_POST['recipient'] ?? '');
            cartAdd('giftcard', null, 'Gift Card' . ($to ? ' for ' . $to : ''), $amt, 1, null, 0,
                    ['recipient' => $to]);
            break;

        case 'apply_giftcard':
            $card = giftCardFind($_POST['code'] ?? '');
            if (!$card) jsonOut(['error' => 'No gift card with that code.'], 404);
            if ($card['status'] !== 'active' || (float)$card['balance'] <= 0) {
                jsonOut(['error' => 'That card has no balance left.'], 422);
            }
            $c = &cart();
            foreach ($c['gift_cards'] as $g) {
                if ((int)$g['id'] === (int)$card['id']) jsonOut(['error' => 'That card is already on this ticket.'], 422);
            }
            // Never take more off the card than the ticket still owes.
            $applied = min((float)$card['balance'], cartDue());
            if ($applied <= 0) jsonOut(['error' => 'Nothing left to pay on this ticket.'], 422);
            $c['gift_cards'][] = ['id' => (int)$card['id'], 'code' => $card['code'], 'amount' => round($applied, 2)];
            jsonOut(ticketPayload(['applied' => $applied, 'card_balance' => (float)$card['balance']]));

        case 'remove_giftcard':
            $c = &cart();
            $c['gift_cards'] = array_values(array_filter($c['gift_cards'], function ($g) {
                return (int)$g['id'] !== (int)($_POST['id'] ?? 0);
            }));
            break;

        case 'set_points':
            $c = &cart();
            if (!$c['client_id']) jsonOut(['error' => 'Attach a client first.'], 422);
            $want = max(0, (int)($_POST['points'] ?? 0));
            $cl   = fetchOne('SELECT points FROM pos_clients WHERE id=?', [$c['client_id']]);
            $set  = posSettings();
            if ($want > 0 && $want < (int)($set['points_min_redeem'] ?? 0)) {
                jsonOut(['error' => 'Minimum redemption is ' . (int)$set['points_min_redeem'] . ' points.'], 422);
            }
            if ($want > (int)$cl['points']) jsonOut(['error' => 'That guest only has ' . (int)$cl['points'] . ' points.'], 422);
            // Cap the redemption at what the ticket is actually worth.
            $c['points_redeem'] = 0;
            $maxPoints = moneyToPoints(cartDue());
            $c['points_redeem'] = min($want, $maxPoints);
            break;

        case 'checkout':
            $payments = json_decode($_POST['payments'] ?? '[]', true);
            if (!is_array($payments) || !$payments) jsonOut(['error' => 'No payment entered.'], 422);
            $clean = [];
            foreach ($payments as $p) {
                $m = in_array($p['method'] ?? '', ['cash','card','gift','other'], true) ? $p['method'] : 'other';
                $clean[] = ['method' => $m, 'amount' => round((float)($p['amount'] ?? 0), 2), 'reference' => substr(trim($p['reference'] ?? ''), 0, 80)];
            }
            $saleId = checkout($clean);
            jsonOut(['ok' => true, 'sale_id' => $saleId,
                     'receipt_url' => BASE_PATH . '/pos/receipt.php?id=' . $saleId]);

        case 'state':
        default:
            break;
    }
    jsonOut(ticketPayload());
} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 400);
}
