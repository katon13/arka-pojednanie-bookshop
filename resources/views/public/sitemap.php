<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/')) ?></loc><priority>1.0</priority></url>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/kontakt')) ?></loc><priority>0.3</priority></url>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/regulamin')) ?></loc><priority>0.2</priority></url>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/polityka-prywatnosci')) ?></loc><priority>0.2</priority></url>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/wydarzenia')) ?></loc><priority>0.6</priority></url>
<?php foreach ($books as $book): ?>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/book/' . rawurlencode($book['slug']) . '/')) ?></loc><?php if (!empty($book['updated_at'])): ?><lastmod><?= htmlspecialchars(date('c', strtotime($book['updated_at']))) ?></lastmod><?php endif; ?><priority>0.8</priority></url>
<?php endforeach; ?>
<?php foreach (($pages ?? []) as $page): ?>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/' . rawurlencode($page['slug']))) ?></loc><?php if (!empty($page['updated_at'])): ?><lastmod><?= htmlspecialchars(date('c', strtotime($page['updated_at']))) ?></lastmod><?php endif; ?><priority>0.5</priority></url>
<?php endforeach; ?>
<?php foreach (($events ?? []) as $event): ?>
  <url><loc><?= htmlspecialchars(\Book100\Services\Seo\SeoBuilder::url('/wydarzenia/' . rawurlencode($event['slug']))) ?></loc><?php if (!empty($event['updated_at'])): ?><lastmod><?= htmlspecialchars(date('c', strtotime($event['updated_at']))) ?></lastmod><?php endif; ?><priority>0.6</priority></url>
<?php endforeach; ?>
</urlset>
