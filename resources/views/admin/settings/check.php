<?php include __DIR__ . '/../layout_top.php'; ?>
<h1>Kontrola techniczna</h1>
<table class="admin-table"><tr><th>Element</th><th>Wynik</th></tr><?php foreach ($checks as $name => $value): ?><tr><td><?= htmlspecialchars($name) ?></td><td><?= htmlspecialchars((string)$value) ?></td></tr><?php endforeach; ?></table>
<p class="muted">Na Laragonie wymagane minimum produkcyjne: PHP 8.2+, pdo_mysql, curl, openssl, katalog storage zapisywalny.</p>
<p><a class="btn secondary" href="/settings">Wróć</a></p>
<?php include __DIR__ . '/../layout_bottom.php'; ?>
