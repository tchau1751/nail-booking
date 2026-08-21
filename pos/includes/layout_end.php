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
