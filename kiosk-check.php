<?php
// kiosk.php was renamed to kiosk-login.php — EasyWP's edge cache strips
// session cookies from paths that don't contain "login", which broke this
// page's CSRF/session flow. Redirecting old links/bookmarks here.
header('Location: /kiosk-login.php', true, 301);
exit;
