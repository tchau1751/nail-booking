<?php
// Stamp the CSRF token into every POST form on the page. Doing it here rather
// than by hand means a form added later is covered the day it is written,
// and a form that is somehow missed still fails closed at layout_start.
$posPage  = ob_get_clean();
$posField = '<input type="hidden" name="_csrf" value="' . e(posCsrfToken()) . '">';
$posPage  = preg_replace('~(<form[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>)~i',
                         '$1' . $posField, $posPage);
echo $posPage;
?>
</main>
<script>
  (function tick(){
    var el = document.getElementById('posClock');
    if (el) el.textContent = new Date().toLocaleString([], {weekday:'short', hour:'2-digit', minute:'2-digit'});
    setTimeout(tick, 15000);
  })();

  // Offline support. Needs a secure context (https or localhost), so over the
  // salon's LAN address this quietly does nothing and the POS works as a normal
  // page. Installing to a home screen is a browser menu action, deliberately not
  // advertised here — a banner over a till is in the way more often than it helps.
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= BASE_PATH ?>/pos/sw.js', { scope: '<?= BASE_PATH ?>/pos/' })
      .catch(function (e) { console.info('POS: offline support unavailable -', e.message); });
  }
</script>
</body>
</html>
