<?php
// ============================================================
//  Clearing out practice data.
//
//  A shop opens the till weeks before it opens its doors, and by the
//  time it goes live the books are full of made-up tickets. This wipes
//  them. It is the one place in the POS that destroys money records on
//  purpose, so every function here counts first, deletes second, and
//  recomputes the client rollups afterwards rather than leaving a
//  lifetime-spend figure that no longer matches any ticket.
//
//  What it never touches: services, products, technicians, staff
//  accounts and settings. Clearing history should not cost you the
//  menu you spent an afternoon typing in.
// ============================================================
require_once __DIR__ . '/pos.php';

/** The tables a set of sales drags with it, children first. */
function purgeSaleIds(array $saleIds): void
{
    if (!$saleIds) return;
    $in = implode(',', array_map('intval', $saleIds));

    // A gift card sold on a doomed ticket goes with it — otherwise the
    // ticket vanishes and a live card with a balance is left behind.
    db()->exec("DELETE t FROM pos_gift_card_txns t
                JOIN pos_gift_cards c ON c.id = t.gift_card_id
                WHERE c.issued_sale_id IN ($in)");
    db()->exec("DELETE FROM pos_gift_cards WHERE issued_sale_id IN ($in)");
    db()->exec("DELETE FROM pos_gift_card_txns WHERE sale_id IN ($in)");

    db()->exec("DELETE ri FROM pos_refund_items ri
                JOIN pos_refunds r ON r.id = ri.refund_id
                WHERE r.sale_id IN ($in)");
    db()->exec("DELETE FROM pos_refunds     WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_payments    WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_sale_items  WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_feedback    WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_loyalty_txns WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_stamp_txns  WHERE sale_id IN ($in)");
    db()->exec("UPDATE pos_checkins SET sale_id = NULL WHERE sale_id IN ($in)");
    db()->exec("DELETE FROM pos_sales       WHERE id IN ($in)");
}

/**
 * Rebuild a client's lifetime figures from the tickets that are still there.
 * Points and stamps are rebuilt from their own ledgers the same way, so a
 * balance can never survive the transaction that created it.
 *
 * Only the clients named are touched. Rebuilding the whole book would quietly
 * restate balances for people who had nothing to do with the tickets being
 * cleared — and a guest whose points move for no reason they can see is a
 * conversation at the counter nobody wants.
 */
function purgeRecomputeClients(array $clientIds): void
{
    $clientIds = array_values(array_unique(array_filter(array_map('intval', $clientIds))));
    if (!$clientIds) return;
    $in = implode(',', $clientIds);

    db()->exec("UPDATE pos_clients c SET
        total_visits = (SELECT COUNT(*) FROM pos_sales s
                         WHERE s.client_id = c.id AND s.status = 'completed'),
        total_spend  = (SELECT COALESCE(SUM(s.grand_total), 0) FROM pos_sales s
                         WHERE s.client_id = c.id AND s.status = 'completed')
                     - (SELECT COALESCE(SUM(r.total), 0) FROM pos_refunds r
                         JOIN pos_sales s2 ON s2.id = r.sale_id
                        WHERE s2.client_id = c.id),
        first_visit  = (SELECT MIN(DATE(s.created_at)) FROM pos_sales s
                         WHERE s.client_id = c.id AND s.status = 'completed'),
        last_visit   = (SELECT MAX(DATE(s.created_at)) FROM pos_sales s
                         WHERE s.client_id = c.id AND s.status = 'completed'),
        points       = GREATEST(0, (SELECT COALESCE(SUM(t.points), 0)
                                      FROM pos_loyalty_txns t WHERE t.client_id = c.id)),
        stamps       = GREATEST(0, (SELECT COALESCE(SUM(t.stamps), 0)
                                      FROM pos_stamp_txns t WHERE t.client_id = c.id)),
        rewards_redeemed = (SELECT COUNT(*) FROM pos_stamp_txns t
                             WHERE t.client_id = c.id AND t.type = 'redeem'),
        rewards_earned = GREATEST(rewards_earned, (SELECT COUNT(*) FROM pos_stamp_txns t
                                                    WHERE t.client_id = c.id AND t.type = 'redeem'))
        WHERE c.id IN ($in)");
    db()->exec("UPDATE pos_clients SET total_spend = 0 WHERE total_spend < 0 AND id IN ($in)");
}

/**
 * What a range clear would remove. Counted before anything is touched so the
 * screen can show real numbers rather than a promise.
 */
function purgeRangePreview(string $from, string $to, array $opts): array
{
    $rng = [$from, $to];
    $n = [];
    $n['sales'] = (int)fetchOne('SELECT COUNT(*) v FROM pos_sales
                                 WHERE DATE(created_at) BETWEEN ? AND ?', $rng)['v'];
    $n['taken'] = (float)fetchOne("SELECT COALESCE(SUM(grand_total),0) v FROM pos_sales
                                   WHERE status <> 'voided' AND DATE(created_at) BETWEEN ? AND ?", $rng)['v'];
    $n['refunds'] = (int)fetchOne('SELECT COUNT(*) v FROM pos_refunds
                                   WHERE DATE(created_at) BETWEEN ? AND ?', $rng)['v'];
    $n['checkins'] = !empty($opts['checkins'])
        ? (int)fetchOne('SELECT COUNT(*) v FROM pos_checkins
                         WHERE DATE(checked_in_at) BETWEEN ? AND ?', $rng)['v'] : 0;
    $n['shifts'] = !empty($opts['checkins'])
        ? (int)fetchOne('SELECT COUNT(*) v FROM pos_tech_shifts
                         WHERE shift_date BETWEEN ? AND ?', $rng)['v'] : 0;
    $n['drawer'] = !empty($opts['drawer'])
        ? (int)fetchOne('SELECT COUNT(*) v FROM pos_cash_movements
                         WHERE DATE(created_at) BETWEEN ? AND ?', $rng)['v'] : 0;
    $n['expenses'] = !empty($opts['expenses'])
        ? (int)fetchOne('SELECT COUNT(*) v FROM pos_expenses
                         WHERE expense_date BETWEEN ? AND ?', $rng)['v'] : 0;
    return $n;
}

/** Clear practice tickets between two dates. Returns what it removed. */
function purgeRange(string $from, string $to, array $opts): array
{
    $done = purgeRangePreview($from, $to, $opts);
    $rng  = [$from, $to];
    $pdo  = db();
    $pdo->beginTransaction();
    try {
        $doomed = fetchAll('SELECT id, client_id FROM pos_sales
                            WHERE DATE(created_at) BETWEEN ? AND ?', $rng);
        $ids     = array_column($doomed, 'id');
        $clients = array_column($doomed, 'client_id');
        purgeSaleIds($ids);

        if (!empty($opts['checkins'])) {
            query('DELETE FROM pos_checkins WHERE DATE(checked_in_at) BETWEEN ? AND ?', $rng);
            query('DELETE FROM pos_tech_shifts WHERE shift_date BETWEEN ? AND ?', $rng);
        }
        if (!empty($opts['drawer'])) {
            query('DELETE FROM pos_cash_movements WHERE DATE(created_at) BETWEEN ? AND ?', $rng);
        }
        if (!empty($opts['expenses'])) {
            query('DELETE FROM pos_expenses WHERE expense_date BETWEEN ? AND ?', $rng);
        }
        purgeRecomputeClients($clients);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $done;
}

/** Everything a full reset would remove. */
function purgeAllPreview(array $opts): array
{
    $count = function (string $t): int {
        return (int)fetchOne("SELECT COUNT(*) v FROM `$t`")['v'];
    };
    $n = [
        'sales'     => $count('pos_sales'),
        'taken'     => (float)fetchOne("SELECT COALESCE(SUM(grand_total),0) v FROM pos_sales
                                        WHERE status <> 'voided'")['v'],
        'refunds'   => $count('pos_refunds'),
        'checkins'  => $count('pos_checkins'),
        'shifts'    => $count('pos_tech_shifts'),
        'drawer'    => $count('pos_cash_movements'),
        'expenses'  => $count('pos_expenses'),
        'feedback'  => $count('pos_feedback'),
        'campaigns' => $count('pos_campaigns'),
        'giftcards' => !empty($opts['giftcards']) ? $count('pos_gift_cards') : 0,
        'clients'   => !empty($opts['clients'])   ? $count('pos_clients') : 0,
        'bookings'  => !empty($opts['bookings'])  ? $count('appointments') : 0,
    ];
    return $n;
}

/**
 * Back to opening day. Wipes the whole transaction history and zeroes every
 * client's points, stamps and lifetime figures, keeping the menu, the retail
 * shelf, the staff and the settings.
 */
function purgeAll(array $opts): array
{
    $done = purgeAllPreview($opts);
    $pdo  = db();
    $pdo->beginTransaction();
    try {
        foreach (['pos_refund_items', 'pos_refunds', 'pos_payments', 'pos_sale_items',
                  'pos_feedback', 'pos_loyalty_txns', 'pos_stamp_txns', 'pos_gift_card_txns',
                  'pos_cash_movements', 'pos_expenses', 'pos_campaigns',
                  'pos_tech_shifts', 'pos_checkins', 'pos_sales'] as $t) {
            db()->exec("DELETE FROM `$t`");
        }
        // Back to opening day means back to ticket 0001. The counters are only
        // there to stop two tills picking the same number; with no tickets left
        // there is nothing for them to collide with.
        db()->exec('DELETE FROM pos_counters');
        if (!empty($opts['giftcards'])) {
            db()->exec('DELETE FROM pos_gift_cards');
        } else {
            // Cards that outlive the reset keep their balance but lose the
            // ticket that sold them, which no longer exists.
            db()->exec('UPDATE pos_gift_cards SET issued_sale_id = NULL');
        }
        if (!empty($opts['bookings'])) {
            db()->exec('DELETE FROM sms_log');
            db()->exec('DELETE FROM appointments');
        }
        if (!empty($opts['clients'])) {
            db()->exec('DELETE FROM pos_client_notes');
            db()->exec('DELETE FROM pos_consents');
            db()->exec('UPDATE pos_gift_cards SET client_id = NULL');
            db()->exec('DELETE FROM pos_clients');
        } else {
            db()->exec('UPDATE pos_clients SET points = 0, stamps = 0, rewards_earned = 0,
                        rewards_redeemed = 0, total_visits = 0, total_spend = 0,
                        first_visit = NULL, last_visit = NULL, birthday_sms_year = NULL');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $done;
}
