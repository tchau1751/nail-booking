<?php
// ============================================================
//  Retired. The Studio dashboard was written against tables that
//  were never created (bookings, clients, staff, discounts), so it
//  could only ever show an SQL error. Everything it was meant to do
//  now lives in the POS, on real data.
//
//  Left in place rather than deleted so the nav link, old links and
//  bookmarks land somewhere useful instead of a 404.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Location: ' . BASE_PATH . '/pos/', true, 302);
exit;
