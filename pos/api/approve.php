<?php
// ============================================================
//  Manager approval at the till.
//
//  A technician is signed in, a guest wants money off, and the
//  manager is standing right there. Rather than sign out and back
//  in, the manager taps their own PIN into a prompt and the till
//  gets a short-lived approval — good for one discount, two
//  minutes, and nothing else.
// ============================================================
require_once __DIR__ . '/../includes/pos.php';
if (!isLoggedIn()) jsonOut(['error' => 'Not signed in.'], 401);
// Same second lock the page forms get: SameSite=Lax is a browser default, not
// a guarantee this endpoint is allowed to rely on.
if (!posCsrfValid($_POST['_csrf'] ?? $_GET['_csrf'] ?? null)) {
    jsonOut(['error' => 'This till was signed out — sign in again.'], 419);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error' => 'POST only.'], 405);

$pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
if (strlen($pin) < PIN_MIN_DIGITS) jsonOut(['error' => 'Enter the manager PIN.'], 400);

// Deliberately slow to brute force: one guess costs a whole request, and a
// manager whose PIN is locked out from failed sign-ins cannot approve either.
$manager = managerByPin($pin);
if (!$manager) {
    usleep(400000);
    jsonOut(['error' => 'That PIN is not a manager PIN.'], 403);
}

grantManagerApproval($manager);
jsonOut(['ok' => true, 'by' => $manager['name'], 'seconds' => MANAGER_APPROVAL_SECONDS]);
