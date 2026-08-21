<?php include __DIR__ . '/../partials/header.php'; ?>
<section class="page-shell payment-pending">
  <div class="page-copy">
    <p class="eyebrow">Przelewy24</p>
    <h1>Oczekujemy na potwierdzenie płatności</h1>
    <p>
      Przelewy24 przetwarza płatność za zamówienie
      <strong><?= htmlspecialchars((string)$order['order_number']) ?></strong>.
      Potwierdzenie zwykle dociera w ciągu kilkunastu sekund.
    </p>
    <p>Nie składaj drugiego zamówienia. Odśwież tę stronę za chwilę.</p>
    <p>
      <a class="btn" href="/dziekujemy/<?= rawurlencode((string)$order['order_token']) ?>">
        Sprawdź status płatności
      </a>
      <a class="btn secondary" href="/">Wróć do księgarni</a>
    </p>
  </div>
</section>
<?php include __DIR__ . '/../partials/footer.php'; ?>
