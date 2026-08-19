<?php
require_once __DIR__ . '/../includes/functions.php';

json_response(['ok' => true, 'csrf_token' => csrf_token()]);
