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

function money($n): string {
    return posSettings()['currency_symbol'] . number_format((float)$n, 2);
}

function posInstalled(): bool {
    try { fetchOne('SELECT 1 FROM pos_settings WHERE id=1'); return true; }
    catch (Throwable $e) { return false; }
}

// ── Cart ────────────────────────────────────────────────────
function &cart(): array {
    startSecureSession();
    static $defaults = [
        'lines'          => [],
        'discount_type'  => 'amount',
        'discount_value' => 0,
        'tip'            => 0,
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
    // A till that was left open across an upgrade still holds the old cart
    // shape in its session — backfill anything the new code expects.
    if (isset($_SESSION['pos_cart'])) {
        $_SESSION['pos_cart'] += $defaults;
    }
    if (!isset($_SESSION['pos_cart'])) {
        $_SESSION['pos_cart'] = $defaults;
    }
    return $_SESSION['pos_cart'];
}

function cartReset(): void {
    startSecureSession();
    unset($_SESSION['pos_cart']);
    cart();   // rebuild it empty so callers always get the full shape
}

function lineKey(string $type, $refId, float $price): string {
    return $type . ':' . ($refId ?? '0') . ':' . number_format($price, 2, '.', '');
}

function cartAdd(string $type, ?int $refId, string $name, float $price, int $qty = 1, ?int $techId = null, int $taxable = 1, array $extra = []): void {
    $c = &cart();
    // Gift cards are separate stored-value products: each one gets its own
    // line so two $50 cards don't collapse into a single $100 line.
    $k = $type === 'giftcard'
        ? 'giftcard:' . count($c['lines']) . ':' . number_format($price, 2, '.', '')
        : lineKey($type, $refId, $price);
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

// Recompute every total. Discount is spread across lines pro-rata so
// tax stays correct on a discounted ticket.
function cartTotals(): array {
    $c   = cart();
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
        $isTaxable = $l['taxable'] && ($l['type'] !== 'service' || $taxServices);
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

function nextSaleNo(): string {
    $prefix = date('ymd');
    $row = fetchOne("SELECT sale_no FROM pos_sales WHERE sale_no LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '-%']);
    $seq = $row ? ((int)substr($row['sale_no'], -4)) + 1 : 1;
    return $prefix . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
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
                  grand_total, paid_total, change_due, points_earned, points_redeemed, note)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
            nextSaleNo(), $c['appointment_id'] ?: null, $c['checkin_id'] ?: null, $c['client_id'] ?: null,
            $c['customer_name'], $c['customer_phone'], $c['technician_id'] ?: null, $admin['id'] ?? null,
            $t['subtotal'], $t['discount'], $t['tax'], $t['tip'], $t['total'], $paid, $change,
            $earned, $ten['points'], $c['note'],
        ]);
        $saleId = (int)$pdo->lastInsertId();

        $issuedCards = [];
        foreach ($t['lines'] as $l) {
            query("INSERT INTO pos_sale_items
                     (sale_id,item_type,ref_id,name,unit_price,qty,discount,tax,line_total,technician_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?)", [
                $saleId, $l['type'], $l['ref_id'], $l['name'], $l['price'], $l['qty'],
                $l['discount'], $l['tax'], $l['line_total'], $l['technician_id'] ?: $c['technician_id'] ?: null,
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

function voidSale(int $saleId): void {
    require_once __DIR__ . '/rewards.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sale = fetchOne('SELECT * FROM pos_sales WHERE id=?', [$saleId]);
        if (!$sale || $sale['status'] !== 'completed') throw new RuntimeException('Sale cannot be voided.');

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

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
