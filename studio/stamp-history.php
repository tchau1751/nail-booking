<?php
// ============================================================
//  Retired. This page was written against tables that were never
//  created (clients, bookings), so it could only ever show an SQL
//  error. Stamp cards now live in the POS, on real data.
//
//  Left in place rather than deleted so old links and bookmarks
//  land somewhere useful instead of a 404.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Location: ' . BASE_PATH . '/pos/stamps.php', true, 302);
exit;
