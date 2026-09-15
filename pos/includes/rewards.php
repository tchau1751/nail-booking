<?php
// ============================================================
//  Gift cards and award points.
//
//  Gift cards are stored value: issuing one takes money now and
//  creates a liability, redeeming one pays it back down. Points
//  are earned per dollar of goods and services (never on tips or
//  tax) and redeem at a configured cash value.
//
//  A card, a point and a stamp are only ever good at the salon
//  that issued them.
// ============================================================
require_once __DIR__ . '/pos.php';

/* ── Gift cards ──────────────────────────────────────────── */

function giftCardCode(): string {
    // Ambiguous characters left out so a code is readable off a card.
    $alphabet = 'ACDEFGHJKLMNPQRTUVWXY34679';
    do {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            if ($i < 3) $code .= '-';
        }
        // Codes stay unique across every salon, so one typed at the wrong
        // salon can never happen to match somebody else's card.
        $taken = unscoped(function () use ($code) {
            return fetchOne('SELECT 1 x FROM pos_gift_cards WHERE code=?', [$code]);
        });
    } while ($taken);
    return $code;
}

function giftCardFind(string $code): ?array {
    $code = strtoupper(trim($code));
    return fetchOne('SELECT * FROM pos_gift_cards WHERE tenant_id=? AND (code=? OR REPLACE(code,"-","")=?)',
                    [tenantId(), $code, str_replace('-', '', $code)]);
}

function giftCardIssue(float $amount, ?int $clientId = null, string $recipient = '',
                       ?int $saleId = null, ?string $expiresOn = null): array {
    if ($amount <= 0) throw new RuntimeException('Gift card amount must be above zero.');
    $code = giftCardCode();
    query('INSERT INTO pos_gift_cards (tenant_id, code, initial_amount, balance, client_id, recipient, issued_sale_id, expires_on)
           VALUES (?,?,?,?,?,?,?,?)', [tenantId(), $code, $amount, $amount, ownedId('pos_clients', $clientId),
                                       $recipient, $saleId, $expiresOn ?: null]);
    $id = (int)db()->lastInsertId();
    giftCardLog($id, 'issue', $amount, $amount, $saleId);
    return fetchOne('SELECT * FROM pos_gift_cards WHERE id=? AND tenant_id=?', [$id, tenantId()]);
}

function giftCardLog(int $cardId, string $type, float $amount, float $balanceAfter, ?int $saleId = null): void {
    $admin = currentAdmin();
    query('INSERT INTO pos_gift_card_txns (tenant_id, gift_card_id, sale_id, type, amount, balance_after, admin_id)
           VALUES (?,?,?,?,?,?,?)', [tenantId(), $cardId, $saleId, $type, $amount, $balanceAfter, $admin['id'] ?? null]);
}

/** Takes money off a card. Returns the amount actually redeemed. */
function giftCardRedeem(int $cardId, float $amount, ?int $saleId = null): float {
    $card = fetchOne('SELECT * FROM pos_gift_cards WHERE id=? AND tenant_id=? FOR UPDATE', [$cardId, tenantId()]);
    if (!$card) throw new RuntimeException('Gift card not found.');
    if ($card['status'] === 'void') throw new RuntimeException('That gift card has been voided.');
    if ($card['expires_on'] && $card['expires_on'] < date('Y-m-d')) {
        throw new RuntimeException('That gift card expired on ' . date('m/d/Y', strtotime($card['expires_on'])) . '.');
    }
    $take = min($amount, (float)$card['balance']);
    if ($take <= 0) throw new RuntimeException('That gift card has no balance left.');
    $after = round((float)$card['balance'] - $take, 2);
    query('UPDATE pos_gift_cards SET balance=?, status=? WHERE id=? AND tenant_id=?',
          [$after, $after <= 0 ? 'used' : 'active', $cardId, tenantId()]);
    giftCardLog($cardId, 'redeem', $take, $after, $saleId);
    return $take;
}

function giftCardReload(int $cardId, float $amount, ?int $saleId = null): float {
    if ($amount <= 0) throw new RuntimeException('Reload amount must be above zero.');
    $card = fetchOne('SELECT * FROM pos_gift_cards WHERE id=? AND tenant_id=?', [$cardId, tenantId()]);
    if (!$card) throw new RuntimeException('Gift card not found.');
    if ($card['status'] === 'void') throw new RuntimeException('That gift card has been voided.');
    $after = round((float)$card['balance'] + $amount, 2);
    query("UPDATE pos_gift_cards SET balance=?, status='active' WHERE id=? AND tenant_id=?", [$after, $cardId, tenantId()]);
    giftCardLog($cardId, 'reload', $amount, $after, $saleId);
    return $after;
}

/** Money customers have paid us but not yet spent — a real liability. */
function giftCardLiability(): float {
    $r = fetchOne("SELECT COALESCE(SUM(balance),0) v FROM pos_gift_cards WHERE tenant_id=? AND status='active'", [tenantId()]);
    return (float)($r['v'] ?? 0);
}

/* ── Points ──────────────────────────────────────────────── */

function loyaltyEnabled(): bool {
    return (int)(posSettings()['loyalty_enabled'] ?? 1) === 1;
}

/** Cash value of a points balance, e.g. 200 points at 5c = $10.00. */
function pointsToMoney(int $points): float {
    return round($points * (float)(posSettings()['point_value_cents'] ?? 5) / 100, 2);
}

function moneyToPoints(float $amount): int {
    $cents = (float)(posSettings()['point_value_cents'] ?? 5);
    return $cents > 0 ? (int)ceil($amount * 100 / $cents) : 0;
}

/* ── Stamp cards ─────────────────────────────────────────────
   The paper punch card, kept honestly. One stamp per visit — not
   per dollar, which is what points are for. Filling a card earns
   a reward; the front desk redeems it when the guest claims it,
   so an unclaimed reward is never silently spent.
*/

function stampsEnabled(): bool {
    return (int)(posSettings()['stamps_enabled'] ?? 1) === 1;
}

function stampsPerCard(): int {
    return max(1, (int)(posSettings()['stamps_per_card'] ?? 10));
}

/**
 * The state of a client's card: how far round the current card they
 * are, and how many finished cards are waiting to be claimed.
 */
function stampCard(array $client): array {
    $per     = stampsPerCard();
    $stamps  = max(0, (int)($client['stamps'] ?? 0));
    $pending = max(0, (int)($client['rewards_earned'] ?? 0) - (int)($client['rewards_redeemed'] ?? 0));
    return [
        'stamps'        => $stamps,
        'per_card'      => $per,
        'on_card'       => $stamps % $per,
        'to_next'       => ($per - ($stamps % $per)) % $per,
        'pending'       => $pending,
        'has_reward'    => $pending > 0,
        'reward'        => posSettings()['stamp_reward'] ?? 'Free service',
    ];
}

function stampLog(int $clientId, string $type, int $delta, string $note = '', ?int $saleId = null): int {
    $c = fetchOne('SELECT stamps FROM pos_clients WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    if (!$c) throw new RuntimeException('Client not found.');
    $after = max(0, (int)$c['stamps'] + $delta);
    $admin = currentAdmin();
    query('UPDATE pos_clients SET stamps=? WHERE id=? AND tenant_id=?', [$after, $clientId, tenantId()]);
    query('INSERT INTO pos_stamp_txns (tenant_id, client_id, sale_id, type, stamps, balance_after, note, admin_id)
           VALUES (?,?,?,?,?,?,?,?)',
          [tenantId(), $clientId, $saleId, $type, $delta, $after, $note, $admin['id'] ?? null]);
    return $after;
}

/**
 * Give one stamp for a visit. Completing a card banks a reward that
 * stays pending until someone actually hands it over.
 * Returns true if this stamp completed a card.
 */
function stampAward(int $clientId, ?int $saleId = null, string $note = 'Visit'): bool {
    $before = (int)(fetchOne('SELECT stamps FROM pos_clients WHERE id=? AND tenant_id=?', [$clientId, tenantId()])['stamps'] ?? 0);
    $after  = stampLog($clientId, 'earn', 1, $note, $saleId);
    $per    = stampsPerCard();
    $completed = intdiv($after, $per) > intdiv($before, $per);
    if ($completed) {
        query('UPDATE pos_clients SET rewards_earned = rewards_earned + 1 WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    }
    return $completed;
}

/** Take a stamp back — used when a sale is voided. */
function stampRevoke(int $clientId, ?int $saleId = null, string $note = 'Sale voided'): void {
    $before = (int)(fetchOne('SELECT stamps FROM pos_clients WHERE id=? AND tenant_id=?', [$clientId, tenantId()])['stamps'] ?? 0);
    if ($before <= 0) return;
    $after = stampLog($clientId, 'adjust', -1, $note, $saleId);
    $per   = stampsPerCard();
    if (intdiv($before, $per) > intdiv($after, $per)) {
        // That stamp had completed a card; un-bank the reward, but never
        // claw back one the guest has already been given.
        query('UPDATE pos_clients
                 SET rewards_earned = GREATEST(rewards_redeemed, rewards_earned - 1)
               WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    }
}

/** Hand a completed card's reward to the guest. */
function stampRedeemReward(int $clientId, string $note = ''): void {
    $c = fetchOne('SELECT * FROM pos_clients WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    if (!$c) throw new RuntimeException('Client not found.');
    $card = stampCard($c);
    if (!$card['has_reward']) throw new RuntimeException('No completed card to redeem yet.');
    query('UPDATE pos_clients SET rewards_redeemed = rewards_redeemed + 1 WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    query('INSERT INTO pos_stamp_txns (tenant_id, client_id, type, stamps, balance_after, note, admin_id)
           VALUES (?,?,?,?,?,?,?)',
          [tenantId(), $clientId, 'redeem', 0, (int)$c['stamps'],
           $note ?: ('Redeemed: ' . $card['reward']), currentAdmin()['id'] ?? null]);
}

function pointsLog(int $clientId, string $type, int $points, string $note = '', ?int $saleId = null): int {
    $client = fetchOne('SELECT points FROM pos_clients WHERE id=? AND tenant_id=?', [$clientId, tenantId()]);
    if (!$client) throw new RuntimeException('Client not found.');
    $after = (int)$client['points'] + $points;
    if ($after < 0) throw new RuntimeException('That would leave a negative points balance.');
    query('UPDATE pos_clients SET points=? WHERE id=? AND tenant_id=?', [$after, $clientId, tenantId()]);
    query('INSERT INTO pos_loyalty_txns (tenant_id, client_id, sale_id, type, points, balance_after, note)
           VALUES (?,?,?,?,?,?,?)', [tenantId(), $clientId, $saleId, $type, $points, $after, $note]);
    return $after;
}

/* ── Birthday texts ──────────────────────────────────────────
   One text per guest per year, only to guests who opted in. The
   daily job (cron/send_birthday_sms.php) sends them salon by salon;
   Settings shows a manager today's list before anything goes out.
*/

function birthdayTextsOn(): bool {
    return (int)(posSettings()['birthday_sms_enabled'] ?? 0) === 1;
}

/** This salon's guests due a birthday text today, each with its message written out as 'body'. */
function birthdayTextsDue(): array {
    $rows = fetchAll(
        "SELECT id, full_name, phone, points
         FROM pos_clients
         WHERE tenant_id = ?
           AND is_active = 1
           AND marketing_opt_in = 1
           AND phone <> ''
           AND birthday IS NOT NULL
           AND DAY(birthday)   = DAY(CURDATE())
           AND MONTH(birthday) = MONTH(CURDATE())
           AND (birthday_sms_year IS NULL OR birthday_sms_year < ?)
         ORDER BY full_name",
        [tenantId(), (int)date('Y')]
    );
    $template = posSettings()['birthday_sms_text'] ?? 'Happy birthday {name}!';
    $salon    = settings()['business_name'] ?? '';
    return array_map(function (array $c) use ($template, $salon) {
        $c['body'] = strtr($template, [
            '{name}'   => explode(' ', trim($c['full_name']))[0],
            '{salon}'  => $salon,
            '{points}' => (string)(int)$c['points'],
        ]);
        return $c;
    }, $rows);
}

/** The text went out: nothing more for this guest until next year. */
function birthdayTextSent(int $clientId): void {
    query('UPDATE pos_clients SET birthday_sms_year=? WHERE id=? AND tenant_id=?',
          [(int)date('Y'), $clientId, tenantId()]);
}
