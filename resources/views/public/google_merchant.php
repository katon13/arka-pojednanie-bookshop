<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title><?= htmlspecialchars((string)($store['shop_name'] ?? 'Wydawnictwo Katolickie ARKA'), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/'), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></link>
    <description><?= htmlspecialchars((string)($store['seo_home_description'] ?? 'Księgarnia internetowa'), ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></description>
<?php foreach ($products as $product): ?>
    <item>
      <g:id><?= htmlspecialchars($product['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:id>
      <g:title><?= htmlspecialchars($product['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:title>
      <g:description><?= htmlspecialchars($product['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:description>
      <g:link><?= htmlspecialchars($product['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:link>
      <g:image_link><?= htmlspecialchars($product['image_link'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:image_link>
      <g:availability><?= htmlspecialchars($product['availability'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:availability>
      <?php if (!empty($product['availability_date'])): ?><g:availability_date><?= htmlspecialchars($product['availability_date'] . 'T00:00:00+02:00', ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:availability_date><?php endif; ?>
      <g:price><?= htmlspecialchars($product['price'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:price>
      <g:condition><?= htmlspecialchars($product['condition'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:condition>
      <g:brand><?= htmlspecialchars($product['brand'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:brand>
      <g:product_type><?= htmlspecialchars($product['product_type'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:product_type>
      <g:google_product_category><?= htmlspecialchars($product['google_product_category'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:google_product_category>
<?php if ($product['gtin'] !== ''): ?>
      <g:gtin><?= htmlspecialchars($product['gtin'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:gtin>
<?php else: ?>
      <g:mpn><?= htmlspecialchars($product['mpn'], ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></g:mpn>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
