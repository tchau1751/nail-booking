<?php
// ============================================================
//  Plain-text version of a receipt, laid out for an 80mm Star
//  printer (48 characters per line in Font A).
//
//  The browser cannot reach a Bluetooth TSP650II, so receipt.php
//  hands these lines to the tablet's till app over a dnstill://
//  link and the app sends them to the printer.
// ============================================================

const RECEIPT_COLS = 48;

function rtCenter(string $text, int $cols = RECEIPT_COLS): string {
    $text = trim($text);
    if ($text === '' || strlen($text) >= $cols) return substr($text, 0, $cols);
    return str_repeat(' ', intdiv($cols - strlen($text), 2)) . $text;
}

function rtDivider(int $cols = RECEIPT_COLS): string {
    return str_repeat('-', $cols);
}

/** Label on the left, amount hard against the right edge. */
function rtRow(string $label, string $value, int $cols = RECEIPT_COLS): string {
    $room  = $cols - strlen($value) - 1;
    $label = strlen($label) > $room ? substr($label, 0, max($room, 0)) : $label;
    $gap   = max(1, $cols - strlen($label) - strlen($value));
    return $label . str_repeat(' ', $gap) . $value;
}

/** Strips anything the printer's ASCII character set cannot render. */
function rtAscii(string $text): string {
    $text = strtr($text, ['×' => 'x', '−' => '-', '–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"']);
    return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
}

/**
 * Builds the whole receipt as an array of lines. Mirrors what
 * receipt.php shows on screen, so paper and screen never disagree.
 */
function receiptLines(array $sale, array $items, array $payments, array $cards, ?array $client, array $set, array $biz): array {
    $lines = [];
    $add = function (string $line) use (&$lines) { $lines[] = rtAscii($line); };

    $add(rtCenter($set['receipt_header'] ?: ($biz['business_name'] ?? 'Nail Salon')));
    if (!empty($biz['business_address'])) $add(rtCenter($biz['business_address']));
    if (!empty($biz['business_phone']))   $add(rtCenter($biz['business_phone']));
    $add(rtDivider());

    if ($sale['status'] !== 'completed') {
        $add(rtCenter('*** ' . strtoupper($sale['status']) . ' ***'));
        $add(rtDivider());
    }

    $add(rtRow('Sale', (string)$sale['sale_no']));
    $add(rtRow('Date', date('m/d/Y g:i A', strtotime($sale['created_at']))));
    if ($sale['customer_name'])  $add(rtRow('Guest', (string)$sale['customer_name']));
    if ($sale['cashier_name'])   $add(rtRow('Cashier', (string)$sale['cashier_name']));
    $add(rtDivider());

    foreach ($items as $it) {
        $name = $it['name'] . ((int)$it['qty'] > 1 ? ' x' . (int)$it['qty'] : '');
        $add(rtRow($name, money($it['line_total'])));
        // Who did it, on the line — a two-chair ticket needs two names.
        if (!empty($it['item_tech'])) $add('  ' . $it['item_tech']);
        if ($it['discount'] > 0) $add('  discount -' . money($it['discount']));
    }
    $add(rtDivider());

    $add(rtRow('Subtotal', money($sale['subtotal'])));
    if ($sale['discount_total'] > 0) $add(rtRow('Discount', '-' . money($sale['discount_total'])));
    if ($sale['tax_total'] > 0)      $add(rtRow($set['tax_label'], money($sale['tax_total'])));
    if ($sale['tip_total'] > 0)      $add(rtRow('Tip', money($sale['tip_total'])));
    $add(rtRow('TOTAL', money($sale['grand_total'])));
    $add(rtDivider());

    foreach ($payments as $p) {
        $label = paymentLabel($p['method']) . ($p['reference'] ? ' (' . $p['reference'] . ')' : '');
        $add(rtRow($label, money($p['amount'])));
    }
    if ($sale['change_due'] > 0) $add(rtRow('Change', money($sale['change_due'])));

    if ($cards) {
        $add(rtDivider());
        $add(rtCenter('GIFT CARD' . (count($cards) > 1 ? 'S' : '')));
        foreach ($cards as $gc) {
            $add(rtCenter($gc['code']));
            $add(rtCenter(money($gc['initial_amount']) . ($gc['recipient'] ? ' for ' . $gc['recipient'] : '')));
        }
        $add(rtCenter('Keep this receipt - the code is the card.'));
    }

    if ($client && ((int)$sale['points_earned'] > 0 || (int)$sale['points_redeemed'] > 0)) {
        $add(rtDivider());
        if ((int)$sale['points_redeemed'] > 0) $add(rtRow('Points redeemed', '-' . (int)$sale['points_redeemed']));
        if ((int)$sale['points_earned'] > 0)   $add(rtRow('Points earned', '+' . (int)$sale['points_earned']));
        $add(rtRow('Points balance', (string)(int)$client['points']));
    }

    $add(rtDivider());
    if (!empty($set['receipt_footer'])) $add(rtCenter($set['receipt_footer']));
    if (!empty($set['owner_name']))     $add(rtCenter($set['owner_name'] . ', Owner'));
    if (!empty($set['owner_phone']))    $add(rtCenter($set['owner_phone']));
    if (!empty($set['license_no']))     $add(rtCenter('Licence ' . $set['license_no']));

    return $lines;
}

/** The receipt as one string, ready to hand to the till app. */
function receiptText(array $sale, array $items, array $payments, array $cards, ?array $client, array $set, array $biz): string {
    return implode("\n", receiptLines($sale, $items, $payments, $cards, $client, $set, $biz));
}
