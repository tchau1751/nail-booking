<?php
// ============================================================
//  POS core helpers — cart lives in the session, totals are
//  always recomputed server-side so the tablet can never lie.
// ============================================================
require_once __DIR__ . '/../../includes/auth.php';

/**
 * At the till, an unsigned-in tablet should land on the PIN pad, not on the
 * email form — the technicians only ever have a PIN.
 */
function requireTillLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/pos/pin.php');
        exit;
    }
}

/**
 * Cross-site request forgery token for the till.
 *
 * SameSite=Lax on the session cookie already stops a plain cross-site POST,
 * but that is one browser default standing between a stranger's web page and
 * voiding a sale or rewriting the pay rates. This is the second lock.
 */
function posCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['pos_csrf'])) {
        $_SESSION['pos_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pos_csrf'];
}

function posCsrfValid(?string $sent): bool {
    startSecureSession();
    return !empty($_SESSION['pos_csrf']) && is_string($sent)
        && hash_equals($_SESSION['pos_csrf'], $sent);
}

function posSettings(): array {
    static $s = null;
    if ($s === null) {
        $s = fetchOne('SELECT * FROM pos_settings WHERE id=1') ?: [
            'currency_symbol' => '$', 'tax_rate' => 0, 'tax_label' => 'Sales Tax',
            'tax_services' => 0, 'receipt_header' => 'Diamond Nail & Spa',
            'receipt_footer' => '', 'tip_presets' => '15,18,20,25',
        ];
    }
    return $s;
}

/** The colour schemes the till can wear, in the order Settings lists them. */
function posThemes(): array {
    return [
        'black-gold'     => ['Black & Gold',     ['#1c1c1e', '#b8912f', '#f5f4f1']],
        'green-salmon'   => ['Green & Salmon',   ['#1f4d3a', '#e98b73', '#f2f8f4']],
        'blue-lavender'  => ['Blue & Lavender',  ['#16345c', '#8b83c9', '#f3f6fb']],
        'burgundy-beige' => ['Burgundy & Beige', ['#5c1f2b', '#c2a181', '#faf5f1']],
        'pink-aqua'      => ['Pink & Aqua',      ['#8a1f5c', '#1fb6c9', '#fdf4f9']],
    ];
}

function posTheme(): string {
    $t = (string)(posSettings()['theme'] ?? 'black-gold');
    return isset(posThemes()[$t]) ? $t : 'black-gold';
}

/** The dark colour of the chosen scheme, for the browser chrome meta tag. */
function posThemeColor(): string {
    return posThemes()[posTheme()][1][0];
}

function money($n): string {
    return posSettings()['currency_symbol'] . number_format((float)$n, 2);
}

function posInstalled(): bool {
    try { fetchOne('SELECT 1 FROM pos_settings WHERE id=1'); return true; }
    catch (Throwable $e) { return false; }
}

// ── Cart ────────────────────────────────────────────────────
/** How many tickets can sit open at once before the chips stop being readable. */
const MAX_OPEN_TICKETS = 8;

function cartDefaults(): array {
    return [
        'lines'          => [],
        'discount_type'  => 'amount',
        'discount_value' => 0,
        'tip'            => 0,
        'tip_method'     => 'card',
        'customer_name'  => '',
        'customer_phone' => '',
        'appointment_id' => null,
        'technician_id'  => null,
        'client_id'      => null,
        'checkin_id'     => null,
        'points_redeem'  => 0,
        'gift_cards'     => [],
        'note'           => '',
    ];
}

/**
 * The ticket in front of you.
 *
 * A nail bar runs several at once — one guest at the pedicure chairs, another
 * at the table, a third waiting to pay — so the session holds a set of tickets
 * and one of them is active. Everything else in this file works on whichever
 * that is, which is why the rest of the code did not have to change.
 */
function &cart(): array {
    startSecureSession();
    if (!isset($_SESSION['pos_carts']) || !is_array($_SESSION['pos_carts'])) {
        $_SESSION['pos_carts'] = [];
        // A till left open across the upgrade still holds the single old cart.
        if (isset($_SESSION['pos_cart'])) {
            $_SESSION['pos_carts'][1]    = $_SESSION['pos_cart'];
            $_SESSION['pos_cart_active'] = 1;
            unset($_SESSION['pos_cart']);
        }
    }
    if (!$_SESSION['pos_carts']) {
        $_SESSION['pos_carts'][1]    = cartDefaults();
        $_SESSION['pos_cart_active'] = 1;
    }
    $id = $_SESSION['pos_cart_active'] ?? null;
    if ($id === null || !isset($_SESSION['pos_carts'][$id])) {
        $id = array_key_first($_SESSION['pos_carts']);
        $_SESSION['pos_cart_active'] = $id;
    }
    // Backfill anything a newer version expects but an older session lacks.
    $_SESSION['pos_carts'][$id] += cartDefaults();
    return $_SESSION['pos_carts'][$id];
}

function cartActiveId(): int {
    cart();
    return (int)$_SESSION['pos_cart_active'];
}

/** Empty the ticket in front of you, leaving it open for the next guest. */
function cartReset(): void {
    $id = cartActiveId();
    $_SESSION['pos_carts'][$id] = cartDefaults();
}

/** Start a fresh ticket and switch to it. */
function ticketNew(): int {
    cart();
    if (count($_SESSION['pos_carts']) >= MAX_OPEN_TICKETS) {
        throw new RuntimeException('That is ' . MAX_OPEN_TICKETS . ' tickets open already — finish or clear one first.');
    }
    $id = max(array_keys($_SESSION['pos_carts'])) + 1;
    $_SESSION['pos_carts'][$id]  = cartDefaults();
    $_SESSION['pos_cart_active'] = $id;
    return $id;
}

function ticketSwitch(int $id): void {
    cart();
    if (!isset($_SESSION['pos_carts'][$id])) throw new RuntimeException('That ticket is no longer open.');
    $_SESSION['pos_cart_active'] = $id;
}

/** Close a ticket outright. There is always at least one left open. */
function ticketClose(int $id): void {
    cart();
    if (!isset($_SESSION['pos_carts'][$id])) return;
    unset($_SESSION['pos_carts'][$id]);
    if (!$_SESSION['pos_carts']) {
        $_SESSION['pos_carts'][1]    = cartDefaults();
        $_SESSION['pos_cart_active'] = 1;
        return;
    }
    if ((int)($_SESSION['pos_cart_active'] ?? 0) === $id) {
        $_SESSION['pos_cart_active'] = array_key_first($_SESSION['pos_carts']);
    }
}

/** Every open ticket, for the chips along the top of the ticket panel. */
function ticketList(): array {
    $active = cartActiveId();
    $out = [];
    foreach ($_SESSION['pos_carts'] as $id => $c) {
        $t = cartTotalsFor($c);
        $out[] = [
            'id'     => (int)$id,
            'count'  => (int)$t['count'],
            'total'  => (float)$t['total'],
            'name'   => $c['customer_name'] ?: '',
            'active' => (int)$id === $active,
        ];
    }
    return $out;
}

function lineKey(string $type, $refId, float $price, ?int $techId = null): string {
    // The technician is part of the identity of a line: a $45 full set by
    // Ann and a $45 full set by Bee are two lines, two payouts, two turns —
    // never one line of qty 2.
    return $type . ':' . ($refId ?? '0') . ':' . number_format($price, 2, '.', '')
         . ':t' . ($techId ?: '0');
}

function cartAdd(string $type, ?int $refId, string $name, float $price, int $qty = 1, ?int $techId = null, int $taxable = 1, array $extra = []): void {
    $c = &cart();
    // Gift cards are separate stored-value products: each one gets its own
    // line so two $50 cards don't collapse into a single $100 line.
    $k = $type === 'giftcard'
        ? 'giftcard:' . count($c['lines']) . ':' . number_format($price, 2, '.', '')
        : lineKey($type, $refId, $price, $techId);
    if (isset($c['lines'][$k])) {
        $c['lines'][$k]['qty'] += $qty;
    } else {
        $c['lines'][$k] = $extra + [
            'type' => $type, 'ref_id' => $refId, 'name' => $name,
            'price' => $price, 'qty' => max(1, $qty),
            'technician_id' => $techId, 'taxable' => $taxable ? 1 : 0,
        ];
    }
    if ($c['lines'][$k]['qty'] < 1) unset($c['lines'][$k]);
}

function cartSetQty(string $key, int $qty): void {
    $c = &cart();
    if (!isset($c['lines'][$key])) return;
    if ($qty <= 0) unset($c['lines'][$key]);
    else $c['lines'][$key]['qty'] = min(999, $qty);
}

function cartRemove(string $key): void {
    $c = &cart();
    unset($c['lines'][$key]);
}

/**
 * Move a line to a different technician. The technician is baked into the
 * line key, so this re-keys the line rather than editing it in place — and
 * if that lands on a line the other technician already has, the two merge.
 */
function cartSetLineTech(string $key, ?int $techId): void {
    $c = &cart();
    if (!isset($c['lines'][$key])) return;
    $line = $c['lines'][$key];
    $line['technician_id'] = $techId;
    unset($c['lines'][$key]);
    $newKey = $line['type'] === 'giftcard'
        ? $key
        : lineKey($line['type'], $line['ref_id'], (float)$line['price'], $techId);
    if (isset($c['lines'][$newKey])) {
        $c['lines'][$newKey]['qty'] += $line['qty'];
    } else {
        $c['lines'][$newKey] = $line;
    }
}

/**
 * Type a different price straight onto the line — the off-menu fill, the
 * half-price fix, the add-on nobody has a button for. Price is part of the
 * line key, so this re-keys rather than edits in place, and lands on the
 * matching line if one already exists.
 */
function cartSetLinePrice(string $key, float $price): void {
    $c = &cart();
    if (!isset($c['lines'][$key])) return;
    $line = $c['lines'][$key];
    $line['price'] = round(max(0, $price), 2);
    unset($c['lines'][$key]);
    $newKey = $line['type'] === 'giftcard'
        ? $key
        : lineKey($line['type'], $line['ref_id'], (float)$line['price'],
                  $line['technician_id'] ? (int)$line['technician_id'] : null);
    if (isset($c['lines'][$newKey])) {
        $c['lines'][$newKey]['qty'] += $line['qty'];
    } else {
        $c['lines'][$newKey] = $line;
    }
}

/** Service lines still waiting on a technician. Checkout refuses while any remain. */
function cartLinesMissingTech(): array {
    $missing = [];
    foreach (cart()['lines'] as $k => $l) {
        if (($l['type'] === 'service' || $l['type'] === 'custom') && empty($l['technician_id'])) {
            $missing[$k] = $l['name'];
        }
    }
    return $missing;
}

/**
 * Split the ticket's tip across the people who actually did the work,
 * pro-rata on what each line earned. Whole cents only, with the rounding
 * remainder going to the largest line so the parts always sum to the tip.
 * Retail lines are excluded: nobody tips the shelf.
 */
function allocateTips(array $lines, float $tip): array {
    $out = [];
    foreach ($lines as $k => $l) $out[$k] = 0.0;
    $tip = round($tip, 2);
    if ($tip <= 0) return $out;

    $base = [];
    foreach ($lines as $k => $l) {
        if (($l['type'] !== 'service' && $l['type'] !== 'custom') || empty($l['technician_id'])) continue;
        $net = $l['price'] * $l['qty'] - $l['discount'];
        if ($net > 0) $base[$k] = $net;
    }
    // A tip on a ticket with no attributable service line (retail only, or a
    // service nobody was assigned to) stays on the ticket and out of payroll.
    if (!$base) return $out;

    $sum = array_sum($base);
    $cents = (int)round($tip * 100);
    $given = 0;
    foreach ($base as $k => $net) {
        $share = (int)floor($cents * $net / $sum);
        $out[$k] = $share / 100;
        $given += $share;
    }
    if ($given < $cents) {
        arsort($base);
        $biggest = array_key_first($base);
        $out[$biggest] = round($out[$biggest] + ($cents - $given) / 100, 2);
    }
    return $out;
}

// Recompute every total. Discount is spread across lines pro-rata so
// tax stays correct on a discounted ticket.
function cartTotals(): array {
    return cartTotalsFor(cart());
}

function cartTotalsFor(array $c): array {
    $set = posSettings();
    $rate = (float)$set['tax_rate'] / 100;
    $taxServices = (int)$set['tax_services'] === 1;

    $subtotal = 0.0;
    foreach ($c['lines'] as $l) $subtotal += $l['price'] * $l['qty'];

    $discount = 0.0;
    if ((float)$c['discount_value'] > 0) {
        $discount = $c['discount_type'] === 'percent'
            ? $subtotal * ((float)$c['discount_value'] / 100)
            : (float)$c['discount_value'];
        $discount = min($discount, $subtotal);
    }
    $ratio = $subtotal > 0 ? ($subtotal - $discount) / $subtotal : 0;

    $lines = []; $tax = 0.0; $discSpread = 0.0;
    foreach ($c['lines'] as $k => $l) {
        $gross     = $l['price'] * $l['qty'];
        $net       = round($gross * $ratio, 2);
        $lineDisc  = round($gross - $net, 2);
        // A custom line is hand-typed work, so it follows the service rule.
        $isWork    = $l['type'] === 'service' || $l['type'] === 'custom';
        $isTaxable = $l['taxable'] && (!$isWork || $taxServices);
        $lineTax   = $isTaxable ? round($net * $rate, 2) : 0.0;
        $tax       += $lineTax;
        $discSpread += $lineDisc;
        $lines[$k] = $l + [
            'key' => $k, 'gross' => $gross, 'discount' => $lineDisc,
            'tax' => $lineTax, 'line_total' => round($net + $lineTax, 2),
        ];
    }

    $tip   = max(0, (float)$c['tip']);
    $total = round($subtotal - $discSpread + $tax + $tip, 2);

    return [
        'lines'     => $lines,
        'count'     => array_sum(array_column($c['lines'], 'qty')),
        'subtotal'  => round($subtotal, 2),
        'discount'  => round($discSpread, 2),
        'tax'       => round($tax, 2),
        'tip'       => round($tip, 2),
        'total'     => $total,
        'tax_label' => $set['tax_label'],
        'tax_rate'  => (float)$set['tax_rate'],
    ];
}

/**
 * Hand out the next number in a daily series, atomically.
 *
 * Reading the highest number and adding one is a race two tills can lose: both
 * read 0007, both try to write 0008, and the unique index turns the second
 * guest's sale into an error while they are standing there. One statement does
 * the read, the increment and the reservation together, and the row stays
 * locked until the surrounding transaction commits — so a checkout that rolls
 * back gives its number back rather than leaving a hole.
 */
function nextSeq(string $series): int {
    query('INSERT INTO pos_counters (name, seq) VALUES (?, LAST_INSERT_ID(1))
           ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)', [$series]);
    return (int)db()->lastInsertId();
}

function nextSaleNo(): string {
    $prefix = date('ymd');
    return $prefix . '-' . str_pad((string)nextSeq('sale:' . $prefix), 4, '0', STR_PAD_LEFT);
}

/**
 * What is already tendered before cash/card: gift cards attached to the
 * ticket, plus any points the guest is spending.
 */
function cartTenders(): array {
    $c = cart();
    $gift = 0.0;
    foreach ($c['gift_cards'] as $g) $gift += (float)$g['amount'];
    $points = (int)$c['points_redeem'];
    return [
        'gift'         => round($gift, 2),
        'points'       => $points,
        'points_value' => $points > 0 ? pointsToMoney($points) : 0.0,
    ];
}

/** What still has to be paid with cash or a card. */
function cartDue(): float {
    $t = cartTotals();
    $ten = cartTenders();
    return max(0, round($t['total'] - $ten['gift'] - $ten['points_value'], 2));
}

/**
 * Persist the current cart as a sale. $payments = [['method'=>..,'amount'=>..,'reference'=>..], ...]
 * covers whatever is left after gift cards and points. Returns the new sale id.
 */
function checkout(array $payments): int {
    require_once __DIR__ . '/rewards.php';
    $c = cart();
    $t = cartTotals();
    if ($t['count'] < 1) throw new RuntimeException('Cart is empty.');

    // Every service has to belong to somebody. Without this the ticket rings
    // up fine and then nobody gets paid for it — the one failure the shop
    // only notices on payday.
    if ($missing = cartLinesMissingTech()) {
        throw new RuntimeException('Choose a technician for: ' . implode(', ', $missing) . '.');
    }

    $ten  = cartTenders();
    $due  = cartDue();
    $cashCard = 0.0;
    foreach ($payments as $p) $cashCard += (float)$p['amount'];
    $cashCard = round($cashCard, 2);

    if ($cashCard + 0.001 < $due) {
        throw new RuntimeException('Payment ' . money($cashCard) . ' is less than the ' . money($due) . ' still due.');
    }
    // Only cash gives change back.
    $cash = 0.0;
    foreach ($payments as $p) if ($p['method'] === 'cash') $cash += (float)$p['amount'];
    $change = round(min($cashCard - $due, $cash), 2);
    $paid   = round($cashCard + $ten['gift'] + $ten['points_value'], 2);

    $set  = posSettings();
    $pdo  = db();
    $pdo->beginTransaction();
    try {
        $admin = currentAdmin();

        // Points are earned on goods and services only — never on tax, tips
        // or the purchase of a gift card.
        $earnBase = 0.0;
        foreach ($t['lines'] as $l) {
            if ($l['type'] === 'giftcard') continue;
            $earnBase += $l['price'] * $l['qty'] - $l['discount'];
        }
        $earned = ($c['client_id'] && loyaltyEnabled())
            ? (int)floor(max(0, $earnBase) * (float)($set['points_per_dollar'] ?? 1))
            : 0;

        query("INSERT INTO pos_sales
                 (sale_no, appointment_id, checkin_id, client_id, customer_name, customer_phone,
                  technician_id, cashier_id, subtotal, discount_total, tax_total, tip_total,
                  tip_method, grand_total, paid_total, change_due, points_earned, points_redeemed, note)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
            nextSaleNo(), $c['appointment_id'] ?: null, $c['checkin_id'] ?: null, $c['client_id'] ?: null,
            $c['customer_name'], $c['customer_phone'], $c['technician_id'] ?: null, $admin['id'] ?? null,
            $t['subtotal'], $t['discount'], $t['tax'], $t['tip'],
            $c['tip_method'] === 'cash' ? 'cash' : 'card',
            $t['total'], $paid, $change,
            $earned, $ten['points'], $c['note'],
        ]);
        $saleId = (int)$pdo->lastInsertId();

        $issuedCards = [];
        $lineTips = allocateTips($t['lines'], (float)$t['tip']);
        foreach ($t['lines'] as $k => $l) {
            query("INSERT INTO pos_sale_items
                     (sale_id,item_type,ref_id,name,unit_price,qty,discount,tax,line_total,technician_id,tip)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)", [
                $saleId, $l['type'], $l['ref_id'], $l['name'], $l['price'], $l['qty'],
                $l['discount'], $l['tax'], $l['line_total'], $l['technician_id'] ?: $c['technician_id'] ?: null,
                $lineTips[$k] ?? 0,
            ]);
            if ($l['type'] === 'product' && $l['ref_id']) {
                query("UPDATE pos_products SET stock_qty = stock_qty - ? WHERE id = ?", [$l['qty'], $l['ref_id']]);
            }
            if ($l['type'] === 'giftcard') {
                for ($i = 0; $i < $l['qty']; $i++) {
                    $issuedCards[] = giftCardIssue((float)$l['price'], $c['client_id'] ?: null,
                                                   $l['recipient'] ?? '', $saleId);
                }
            }
        }

        // Tenders, in the order they are applied.
        foreach ($c['gift_cards'] as $g) {
            $took = giftCardRedeem((int)$g['id'], (float)$g['amount'], $saleId);
            query("INSERT INTO pos_payments (sale_id,method,amount,reference) VALUES (?,?,?,?)",
                  [$saleId, 'gift', $took, $g['code']]);
        }
        if ($ten['points'] > 0) {
            if (!$c['client_id']) throw new RuntimeException('Attach a client before redeeming points.');
            pointsLog((int)$c['client_id'], 'redeem', -$ten['points'], 'Redeemed on sale', $saleId);
            query("INSERT INTO pos_payments (sale_id,method,amount,reference) VALUES (?,?,?,?)",
                  [$saleId, 'other', $ten['points_value'], $ten['points'] . ' points']);
        }
        foreach ($payments as $p) {
            if ((float)$p['amount'] <= 0) continue;
            query("INSERT INTO pos_payments (sale_id,method,amount,reference) VALUES (?,?,?,?)",
                  [$saleId, $p['method'], (float)$p['amount'], $p['reference'] ?? '']);
        }

        // Client record: points earned and lifetime figures.
        if ($c['client_id']) {
            if ($earned > 0) pointsLog((int)$c['client_id'], 'earn', $earned, 'Earned on sale', $saleId);

            // One stamp per visit — not per dollar, which is what points are.
            // A ticket that is only a gift card isn't a visit, so it earns none.
            $isVisit = false;
            foreach ($t['lines'] as $l) if ($l['type'] !== 'giftcard') { $isVisit = true; break; }
            if (stampsEnabled() && $isVisit) {
                stampAward((int)$c['client_id'], $saleId, 'Visit');
                query('UPDATE pos_sales SET stamp_awarded=1 WHERE id=?', [$saleId]);
            }
            query('UPDATE pos_clients
                     SET total_visits = total_visits + 1,
                         total_spend  = total_spend + ?,
                         last_visit   = CURDATE(),
                         first_visit  = COALESCE(first_visit, CURDATE())
                   WHERE id = ?', [$t['total'], $c['client_id']]);
        }

        // Close out the queue entry and the booking this ticket came from.
        if ($c['checkin_id']) {
            query("UPDATE pos_checkins SET status='done', completed_at=NOW(), sale_id=? WHERE id=?",
                  [$saleId, $c['checkin_id']]);
        }
        if ($c['appointment_id']) {
            query("UPDATE appointments SET status='completed' WHERE id=? AND status<>'cancelled'", [$c['appointment_id']]);
        }

        // Queue up a feedback request the guest can answer from their phone.
        if ((int)($set['feedback_enabled'] ?? 1) === 1 && $c['client_id']) {
            query('INSERT INTO pos_feedback (token, client_id, sale_id, technician_id)
                   VALUES (?,?,?,?)', [bin2hex(random_bytes(16)), $c['client_id'], $saleId,
                                       $c['technician_id'] ?: null]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    cartReset();
    return $saleId;
}

/** Lines on a ticket with quantity still left to give back. */
function refundableLines(int $saleId): array {
    return fetchAll(
        'SELECT i.*, t.name AS tech_name, (i.qty - i.refunded_qty) AS left_qty
           FROM pos_sale_items i
           LEFT JOIN technicians t ON t.id = i.technician_id
          WHERE i.sale_id = ? ORDER BY i.id', [$saleId]);
}

function nextRefundNo(): string {
    $prefix = 'R' . date('ymd');
    return $prefix . '-' . str_pad((string)nextSeq('refund:' . $prefix), 4, '0', STR_PAD_LEFT);
}

/**
 * Give part of a ticket back. $qtys maps sale_item_id => quantity returned.
 *
 * A void undoes a whole sale; this undoes some of it, so the arithmetic is
 * per unit: a line's discount and tax are already baked into line_total, and
 * one unit is that figure divided by the quantity sold.
 *
 * The tip is only handed back when asked for. Refunding a service does not
 * normally claw back what the guest chose to give the technician.
 *
 * Returns the new refund id.
 */
function refundSale(int $saleId, array $qtys, string $method, string $reason = '', bool $withTip = false): int
{
    require_once __DIR__ . '/rewards.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sale = fetchOne('SELECT * FROM pos_sales WHERE id=? FOR UPDATE', [$saleId]);
        if (!$sale) throw new RuntimeException('Sale not found.');
        if ($sale['status'] === 'voided') throw new RuntimeException('That sale was voided — there is nothing to refund.');

        $items = [];
        foreach (fetchAll('SELECT * FROM pos_sale_items WHERE sale_id=?', [$saleId]) as $it) {
            $items[(int)$it['id']] = $it;
        }

        $picked = [];
        $sumAmount = $sumTax = $sumTip = 0.0;
        foreach ($qtys as $itemId => $qty) {
            $qty = (int)$qty;
            if ($qty < 1) continue;
            $it = $items[(int)$itemId] ?? null;
            if (!$it) throw new RuntimeException('That line is not on this ticket.');

            $left = (int)$it['qty'] - (int)$it['refunded_qty'];
            if ($qty > $left) {
                throw new RuntimeException($it['name'] . ': only ' . $left . ' left to refund.');
            }
            // Gift cards are stored value. Handing the money back would leave a
            // live card in the guest's wallet, so those go through a void.
            if ($it['item_type'] === 'giftcard') {
                throw new RuntimeException('A gift card cannot be refunded here — void the sale instead.');
            }

            $soldQty = max(1, (int)$it['qty']);
            $unitNet = ((float)$it['line_total'] - (float)$it['tax']) / $soldQty;
            $unitTax = (float)$it['tax'] / $soldQty;
            $unitTip = $withTip ? (float)$it['tip'] / $soldQty : 0.0;

            $amount = round($unitNet * $qty, 2);
            $tax    = round($unitTax * $qty, 2);
            $tip    = round($unitTip * $qty, 2);

            $picked[] = ['item' => $it, 'qty' => $qty, 'amount' => $amount, 'tax' => $tax, 'tip' => $tip];
            $sumAmount += $amount; $sumTax += $tax; $sumTip += $tip;
        }
        if (!$picked) throw new RuntimeException('Choose at least one line to refund.');

        $sumAmount = round($sumAmount, 2);
        $sumTax    = round($sumTax, 2);
        $sumTip    = round($sumTip, 2);
        $total     = round($sumAmount + $sumTax + $sumTip, 2);

        $admin = currentAdmin();
        query('INSERT INTO pos_refunds (refund_no, sale_id, amount, tax, tip, total, method, reason, admin_id)
               VALUES (?,?,?,?,?,?,?,?,?)',
              [nextRefundNo(), $saleId, $sumAmount, $sumTax, $sumTip, $total,
               in_array($method, ['cash', 'card', 'gift', 'other'], true) ? $method : 'cash',
               mb_substr(trim($reason), 0, 255), $admin['id'] ?? null]);
        $refundId = (int)$pdo->lastInsertId();

        foreach ($picked as $p) {
            $it = $p['item'];
            query('INSERT INTO pos_refund_items (refund_id, sale_item_id, qty, amount, tax, tip)
                   VALUES (?,?,?,?,?,?)',
                  [$refundId, (int)$it['id'], $p['qty'], $p['amount'], $p['tax'], $p['tip']]);
            query('UPDATE pos_sale_items SET refunded_qty = refunded_qty + ? WHERE id=?',
                  [$p['qty'], (int)$it['id']]);
            // Retail comes back onto the shelf. Services obviously do not.
            if ($it['item_type'] === 'product' && $it['ref_id']) {
                query('UPDATE pos_products SET stock_qty = stock_qty + ? WHERE id=?', [$p['qty'], $it['ref_id']]);
            }
        }

        // Points were earned on goods and services before tax and tip, so the
        // share handed back is measured the same way — and never more than was
        // earned, however many part-refunds the ticket ends up with.
        if ($sale['client_id']) {
            $earnBase = (float)$sale['subtotal'] - (float)$sale['discount_total'];
            $earned   = (int)$sale['points_earned'];
            if ($earned > 0 && $earnBase > 0) {
                $alreadyBack = (int)(fetchOne(
                    "SELECT COALESCE(-SUM(points), 0) v FROM pos_loyalty_txns
                      WHERE sale_id = ? AND type = 'adjust' AND points < 0", [$saleId])['v'] ?? 0);
                $back = (int)round($earned * min(1, $sumAmount / $earnBase));
                $back = max(0, min($back, $earned - $alreadyBack));
                if ($back > 0) {
                    pointsLog((int)$sale['client_id'], 'adjust', -$back, 'Refunded on sale', $saleId);
                }
            }
            query('UPDATE pos_clients SET total_spend = GREATEST(0, total_spend - ?) WHERE id=?',
                  [$total, $sale['client_id']]);
        }

        // Nothing left on any line means the whole ticket came back.
        $outstanding = (int)(fetchOne('SELECT COALESCE(SUM(qty - refunded_qty), 0) v
                                       FROM pos_sale_items WHERE sale_id=?', [$saleId])['v'] ?? 0);
        if ($outstanding === 0) {
            query("UPDATE pos_sales SET status='refunded' WHERE id=?", [$saleId]);
            if ($sale['client_id']) {
                query('UPDATE pos_clients SET total_visits = GREATEST(0, total_visits - 1) WHERE id=?',
                      [$sale['client_id']]);
                if (!empty($sale['stamp_awarded'])) {
                    stampRevoke((int)$sale['client_id'], $saleId, 'Sale refunded');
                    query('UPDATE pos_sales SET stamp_awarded=0 WHERE id=?', [$saleId]);
                }
            }
        }

        $pdo->commit();
        return $refundId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function voidSale(int $saleId): void {
    require_once __DIR__ . '/rewards.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sale = fetchOne('SELECT * FROM pos_sales WHERE id=?', [$saleId]);
        if (!$sale || $sale['status'] !== 'completed') throw new RuntimeException('Sale cannot be voided.');
        // Part of this ticket has already gone back. Voiding would return the
        // stock and the points a second time, so the rest has to be refunded.
        if (fetchOne('SELECT 1 x FROM pos_refunds WHERE sale_id=? LIMIT 1', [$saleId])) {
            throw new RuntimeException('This ticket has already been refunded in part — refund the rest instead of voiding.');
        }

        foreach (fetchAll('SELECT * FROM pos_sale_items WHERE sale_id=?', [$saleId]) as $it) {
            if ($it['item_type'] === 'product' && $it['ref_id']) {
                query('UPDATE pos_products SET stock_qty = stock_qty + ? WHERE id=?', [$it['qty'], $it['ref_id']]);
            }
        }

        // Any gift card sold on this ticket is cancelled; any card spent on it
        // gets its balance back.
        foreach (fetchAll('SELECT * FROM pos_gift_cards WHERE issued_sale_id=?', [$saleId]) as $card) {
            query("UPDATE pos_gift_cards SET status='void', balance=0 WHERE id=?", [$card['id']]);
            giftCardLog((int)$card['id'], 'void', -(float)$card['balance'], 0, $saleId);
        }
        foreach (fetchAll("SELECT * FROM pos_gift_card_txns WHERE sale_id=? AND type='redeem'", [$saleId]) as $tx) {
            giftCardReload((int)$tx['gift_card_id'], (float)$tx['amount'], $saleId);
        }

        // Points move back the other way: earned points are taken off,
        // redeemed points are handed back.
        if ($sale['client_id']) {
            if ((int)$sale['points_earned'] > 0) {
                pointsLog((int)$sale['client_id'], 'adjust', -(int)$sale['points_earned'], 'Sale voided', $saleId);
            }
            if ((int)$sale['points_redeemed'] > 0) {
                pointsLog((int)$sale['client_id'], 'adjust', (int)$sale['points_redeemed'], 'Sale voided', $saleId);
            }
            if (!empty($sale['stamp_awarded'])) {
                stampRevoke((int)$sale['client_id'], $saleId, 'Sale voided');
                query('UPDATE pos_sales SET stamp_awarded=0 WHERE id=?', [$saleId]);
            }
            query('UPDATE pos_clients
                     SET total_visits = GREATEST(0, total_visits - 1),
                         total_spend  = GREATEST(0, total_spend - ?)
                   WHERE id = ?', [$sale['grand_total'], $sale['client_id']]);
        }

        query("UPDATE pos_sales SET status='voided', voided_at=NOW() WHERE id=?", [$saleId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Send a table to the browser as a CSV download and stop.
 *
 * Called after layout_start has already run, so the page's own buffer is
 * dropped first — that way the role gate and the sign-in check still happen
 * before a single row goes out. Amounts are written as bare numbers, not
 * money(), so the spreadsheet can add them up.
 */
function posCsvOut(string $filename, array $rows): void
{
    // The name is built from user-supplied dates; anything that could break out
    // of the header goes first.
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
    if ($filename === '' || $filename === null) $filename = 'export.csv';

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));   // byte order mark, or Excel mangles accents
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
