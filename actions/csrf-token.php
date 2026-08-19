<?php
require_once __DIR__ . '/../includes/functions.php';

ensure_public_session();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

json_response(['ok' => true, 'csrf_token' => csrf_token()]);
