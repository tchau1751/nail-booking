<?php
// admin/ was renamed to studio/ — this whole path prefix ("/admin/") strips
// incoming session cookies on this host, which silently broke login and
// every admin action. Redirecting old links/bookmarks to the working URL.
header('Location: /studio/login.php', true, 301);
exit;
