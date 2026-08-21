<?php include __DIR__ . '/layout_top.php'; ?>
<h1><?= htmlspecialchars($title) ?></h1><div class="notice"><?= htmlspecialchars($message) ?></div><p><a class="btn" href="<?= htmlspecialchars($backUrl ?? '/') ?>">Wróć</a></p>
<?php include __DIR__ . '/layout_bottom.php'; ?>
