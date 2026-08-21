</main><footer class="foot"><?= htmlspecialchars($adminShopName ?? 'Sklep') ?> · Panel administracyjny</footer>
<?php
$adminScript = dirname(__DIR__, 3) . '/admin/assets/admin.js';
$adminScriptVersion = is_file($adminScript) ? (string)filemtime($adminScript) : '1';
?>
<script src="/assets/admin.js?v=<?= htmlspecialchars($adminScriptVersion) ?>" defer></script>
<?php if (str_starts_with($adminPath ?? '', '/homepage')):
  $homepageScript = dirname(__DIR__, 3) . '/admin/assets/homepage.js';
  $homepageScriptVersion = is_file($homepageScript) ? (string)filemtime($homepageScript) : '1';
?>
<script src="/assets/homepage.js?v=<?= htmlspecialchars($homepageScriptVersion) ?>" defer></script>
<?php endif; ?>
</body></html>
