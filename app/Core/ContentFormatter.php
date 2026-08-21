<?php
namespace Book100\Core;

final class ContentFormatter
{
    public static function text(?string $content): string
    {
        $content = (string)$content;
        if ($content === '') {
            return '';
        }

        $content = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', ' ', $content) ?? $content;
        $content = preg_replace('#<(br|hr)\b[^>]*\/?>#i', "\n", $content) ?? $content;
        $content = preg_replace('#<li\b[^>]*>#i', "\n• ", $content) ?? $content;
        $content = preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|blockquote|li|ul|ol|section|article)>#i', "\n\n", $content) ?? $content;
        $content = preg_replace('/\[(?:\/)?(?:embed|caption|gallery|video|audio)[^\]]*\]/iu', ' ', $content) ?? $content;
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\r\n", "\r", "\u{00A0}", "\u{FEFF}"], ["\n", "\n", ' ', ''], $content);
        $content = preg_replace('/[ \t]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/ *\n */u', "\n", $content) ?? $content;
        $content = preg_replace('/\n{3,}/u', "\n\n", $content) ?? $content;

        return trim($content);
    }

    public static function html(?string $content): string
    {
        $text = self::text($content);
        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/u', $text) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $html[] = '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }

        return implode("\n", $html);
    }

    public static function richHtml(?string $content): string
    {
        $content = trim((string)$content);
        if ($content === '') {
            return '';
        }
        if (!preg_match('/<[^>]+>/u', $content)
            && !preg_match('#\[embed\].+?\[/embed\]#isu', $content)) {
            return self::html($content);
        }
        if (!class_exists(\DOMDocument::class)) {
            return self::html($content);
        }

        $content = preg_replace_callback(
            '#\[embed\]\s*(https?://[^\s\[]+)\s*\[/embed\]#iu',
            static function (array $match): string {
                $url = html_entity_decode((string)$match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $youtube = self::youtubeEmbedUrl($url);
                if ($youtube !== null) {
                    return '<iframe src="' . htmlspecialchars($youtube, ENT_QUOTES, 'UTF-8') . '" title="Film YouTube" allowfullscreen></iframe>';
                }
                if (!self::safeRichLink($url)) {
                    return '';
                }
                $escaped = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<p><a href="' . $escaped . '" rel="noopener noreferrer">Zobacz powiązaną publikację</a></p>';
            },
            $content
        ) ?? $content;
        $content = preg_replace(
            '#<(script|style|object|embed|form|input|button|textarea|select|option|svg|math)\b[^>]*>.*?</\1>#is',
            '',
            $content
        ) ?? $content;
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="book100-rich-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return self::html($content);
        }

        $root = null;
        foreach ($document->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === 'book100-rich-root') {
                $root = $div;
                break;
            }
        }
        if (!$root) {
            return self::html($content);
        }

        self::sanitizeRichChildren($root, $document);
        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }
        $output = trim($output);

        return $output !== '' ? $output : self::html($content);
    }

    public static function documentHtml(?string $content): string
    {
        $text = self::text($content);
        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\n/u', $text) ?: [];
        $html = [];
        $paragraph = [];
        $listType = null;

        $flushParagraph = static function () use (&$paragraph, &$html): void {
            if ($paragraph === []) {
                return;
            }
            $value = trim(implode(' ', $paragraph));
            if ($value !== '') {
                $html[] = '<p>' . self::linkify($value) . '</p>';
            }
            $paragraph = [];
        };

        $closeList = static function () use (&$listType, &$html): void {
            if ($listType === null) {
                return;
            }
            $html[] = '</' . $listType . '>';
            $listType = null;
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $heading) === 1) {
                $flushParagraph();
                $closeList();
                $level = strlen($heading[1]);
                $html[] = '<h' . $level . '>' . self::linkify($heading[2]) . '</h' . $level . '>';
                continue;
            }

            $type = null;
            $item = null;
            $itemNumber = null;
            if (preg_match('/^[-•]\s+(.+)$/u', $line, $match) === 1) {
                $type = 'ul';
                $item = $match[1];
            } elseif (preg_match('/^(\d+)[.)]\s+(.+)$/u', $line, $match) === 1) {
                $type = 'ol';
                $itemNumber = max(1, (int)$match[1]);
                $item = $match[2];
            }

            if ($type !== null) {
                $flushParagraph();
                if ($listType !== $type) {
                    $closeList();
                    $start = $type === 'ol' && $itemNumber !== null && $itemNumber > 1
                        ? ' start="' . $itemNumber . '"'
                        : '';
                    $html[] = '<' . $type . $start . '>';
                    $listType = $type;
                }
                $html[] = '<li>' . self::linkify((string)$item) . '</li>';
                continue;
            }

            $closeList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    public static function excerpt(?string $content, int $length = 180): string
    {
        $text = preg_replace('/\s+/u', ' ', self::text($content)) ?? '';
        $text = trim($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $short = rtrim(mb_substr($text, 0, $length - 1));
        $lastSpace = mb_strrpos($short, ' ');
        if ($lastSpace !== false && $lastSpace > (int)($length * 0.65)) {
            $short = mb_substr($short, 0, $lastSpace);
        }
        return rtrim($short, " \t\n\r\0\x0B,.;:!?-") . '…';
    }

    private static function linkify(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return preg_replace_callback(
            '#https://[^\s<]+|(?<![/\w])[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}(?![\w])#u',
            static function (array $match): string {
                if (!str_starts_with($match[0], 'https://')) {
                    return '<a href="mailto:' . $match[0] . '">' . $match[0] . '</a>';
                }
                $url = rtrim($match[0], '.,;:)');
                $suffix = substr($match[0], strlen($url));
                return '<a href="' . $url . '" rel="noopener">' . $url . '</a>' . $suffix;
            },
            $escaped
        ) ?? $escaped;
    }

    private static function sanitizeRichChildren(\DOMNode $parent, \DOMDocument $document): void
    {
        $allowed = [
            'p', 'br', 'strong', 'em', 'u', 's',
            'h2', 'h3', 'h4', 'ul', 'ol', 'li',
            'blockquote', 'a', 'span', 'div', 'img', 'iframe',
            'audio', 'video', 'source',
        ];
        $discardWithContent = [
            'script', 'style', 'object', 'embed', 'form',
            'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        ];

        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $parent->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, $discardWithContent, true)) {
                $parent->removeChild($child);
                continue;
            }

            $originalTag = $tag;
            $tag = match ($tag) {
                'b' => 'strong',
                'i' => 'em',
                'h1' => 'h2',
                'font' => 'span',
                default => $tag,
            };
            if ($tag !== $originalTag) {
                $replacement = $document->createElement($tag);
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $replacement->setAttribute($attribute->name, $attribute->value);
                }
                while ($child->firstChild) {
                    $replacement->appendChild($child->firstChild);
                }
                $parent->replaceChild($replacement, $child);
                $child = $replacement;
            }

            if (!in_array($tag, $allowed, true)) {
                self::sanitizeRichChildren($child, $document);
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            self::sanitizeRichAttributes($child, $originalTag);
            self::sanitizeRichChildren($child, $document);
        }
    }

    private static function sanitizeRichAttributes(\DOMElement $element, string $originalTag): void
    {
        $href = trim($element->getAttribute('href'));
        $target = trim($element->getAttribute('target'));
        $style = (string)$element->getAttribute('style');
        $align = strtolower(trim($element->getAttribute('align')));
        $fontColor = $originalTag === 'font' ? trim($element->getAttribute('color')) : '';
        $listStart = trim($element->getAttribute('start'));
        $listValue = trim($element->getAttribute('value'));
        $source = trim($element->getAttribute('src'));
        $alternative = trim($element->getAttribute('alt'));
        $mediaTitle = trim($element->getAttribute('title'));
        $poster = trim($element->getAttribute('poster'));
        $preload = strtolower(trim($element->getAttribute('preload')));
        $mediaType = strtolower(trim($element->getAttribute('type')));
        $className = trim($element->getAttribute('class'));
        $hasControls = $element->hasAttribute('controls');
        $hasDownload = $element->hasAttribute('download');

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($element->tagName === 'a' && self::safeRichLink($href)) {
            $element->setAttribute('href', $href);
            if ($target === '_blank') {
                $element->setAttribute('target', '_blank');
            }
            $element->setAttribute('rel', 'noopener noreferrer');
            if ($hasDownload && preg_match('#^/uploads/[a-z0-9_./-]+\.(?:mp3|mp4|m4a|wav|ogg|webm)$#i', $href)) {
                $element->setAttribute('download', '');
            }
            $linkText = trim((string)$element->textContent);
            if ($linkText !== '' && rtrim($linkText, '/') === rtrim($href, '/')) {
                while ($element->firstChild) {
                    $element->removeChild($element->firstChild);
                }
                $element->appendChild($element->ownerDocument->createTextNode(
                    str_contains(strtolower($href), 'arka-pojednanie.pl')
                        ? 'Zobacz powiązaną publikację'
                        : 'Otwórz stronę'
                ));
            }
        }

        if ($element->tagName === 'ol' && preg_match('/^\d{1,4}$/', $listStart)) {
            $element->setAttribute('start', (string)max(1, (int)$listStart));
        }
        if ($element->tagName === 'li' && preg_match('/^\d{1,4}$/', $listValue)) {
            $element->setAttribute('value', (string)max(1, (int)$listValue));
        }

        if ($element->tagName === 'img' && !self::safeRichImage($source)) {
            $element->parentNode?->removeChild($element);
            return;
        }
        if ($element->tagName === 'img') {
            $element->setAttribute('src', $source);
            $element->setAttribute('alt', mb_substr($alternative, 0, 180));
            $element->setAttribute('loading', 'lazy');
            $element->setAttribute('decoding', 'async');
        }

        if ($element->tagName === 'iframe') {
            $youtube = self::youtubeEmbedUrl($source);
            if ($youtube === null) {
                $element->parentNode?->removeChild($element);
                return;
            }
            $element->setAttribute('src', $youtube);
            $element->setAttribute('title', mb_substr($mediaTitle !== '' ? $mediaTitle : 'Film YouTube', 0, 180));
            $element->setAttribute('loading', 'lazy');
            $element->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
            $element->setAttribute('allowfullscreen', '');
            $element->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        }

        if ($element->tagName === 'audio') {
            if ($source !== '' && !self::safeRichMedia($source, ['mp3', 'm4a', 'wav', 'ogg'])) {
                $element->parentNode?->removeChild($element);
                return;
            }
            if ($source !== '') {
                $element->setAttribute('src', $source);
            }
            if ($hasControls) {
                $element->setAttribute('controls', '');
            }
            $element->setAttribute('preload', in_array($preload, ['none', 'metadata'], true) ? $preload : 'metadata');
        }

        if ($element->tagName === 'video') {
            if ($source !== '' && !self::safeRichMedia($source, ['mp4', 'webm'])) {
                $element->parentNode?->removeChild($element);
                return;
            }
            if ($source !== '') {
                $element->setAttribute('src', $source);
            }
            if ($poster !== '' && self::safeRichImage($poster)) {
                $element->setAttribute('poster', $poster);
            }
            if ($hasControls) {
                $element->setAttribute('controls', '');
            }
            $element->setAttribute('preload', in_array($preload, ['none', 'metadata'], true) ? $preload : 'metadata');
        }

        if ($element->tagName === 'source') {
            $parentTag = strtolower((string)($element->parentNode?->nodeName ?? ''));
            $extensions = $parentTag === 'audio' ? ['mp3', 'm4a', 'wav', 'ogg'] : ['mp4', 'webm'];
            if (!in_array($parentTag, ['audio', 'video'], true) || !self::safeRichMedia($source, $extensions)) {
                $element->parentNode?->removeChild($element);
                return;
            }
            $element->setAttribute('src', $source);
            $allowedTypes = $parentTag === 'audio'
                ? ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg']
                : ['video/mp4', 'video/webm'];
            if (in_array($mediaType, $allowedTypes, true)) {
                $element->setAttribute('type', $mediaType);
            }
        }

        $allowedClasses = [
            'publication-entry', 'publication-entry--media', 'publication-entry--audio', 'publication-entry--video',
            'publication-media', 'publication-media--document', 'publication-media--poster',
            'publication-media__meta', 'publication-media__caption',
            'publication-addendum', 'publication-addendum__media',
            'publication-archive', 'publication-archive__heading', 'publication-archive__gallery',
            'publication-archive__media',
            'publication-audio', 'publication-audio__heading', 'publication-audio__player', 'publication-audio__download',
            'publication-video__heading', 'publication-video__player', 'publication-video__caption', 'publication-video__download',
            'publication-video-link', 'publication-video-link__media', 'publication-video-link__play',
            'publication-video-link__text',
            'publication-youtube-archive', 'publication-youtube-archive__intro', 'publication-youtube-grid',
            'publication-youtube-card', 'publication-youtube-card__media', 'publication-youtube-card__play',
            'publication-youtube-card__title', 'publication-youtube-archive__channel',
        ];
        $classes = array_values(array_unique(array_filter(
            preg_split('/\s+/', $className) ?: [],
            static fn(string $class): bool => in_array($class, $allowedClasses, true)
        )));
        if ($classes !== []) {
            $element->setAttribute('class', implode(' ', $classes));
        }

        $safeStyles = [];
        if (preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/i', $style, $colorMatch)) {
            $color = self::safeRichColor(trim($colorMatch[1]));
            if ($color !== null) {
                $safeStyles[] = 'color:' . $color;
            }
        } elseif ($fontColor !== '') {
            $color = self::safeRichColor($fontColor);
            if ($color !== null) {
                $safeStyles[] = 'color:' . $color;
            }
        }

        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $style, $alignMatch)) {
            $safeStyles[] = 'text-align:' . strtolower($alignMatch[1]);
        } elseif (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            $safeStyles[] = 'text-align:' . $align;
        }

        if ($safeStyles !== []) {
            $element->setAttribute('style', implode(';', array_unique($safeStyles)));
        }
    }

    private static function safeRichLink(string $href): bool
    {
        if ($href === '') {
            return false;
        }
        return preg_match('#^(?:https?://|mailto:|/|\\#)#i', $href) === 1;
    }

    private static function safeRichImage(string $source): bool
    {
        if (preg_match('#^/uploads/[a-z0-9_./-]+$#i', $source)) {
            return true;
        }
        return filter_var($source, FILTER_VALIDATE_URL) !== false
            && strtolower((string)parse_url($source, PHP_URL_SCHEME)) === 'https';
    }

    private static function safeRichMedia(string $source, array $extensions): bool
    {
        if (!preg_match('#^/uploads/[a-z0-9_./-]+$#i', $source)) {
            return false;
        }
        $extension = strtolower(pathinfo((string)parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, $extensions, true);
    }

    private static function youtubeEmbedUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        $videoId = '';

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0] ?? '';
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            if ($path === 'watch') {
                $videoId = (string)($query['v'] ?? '');
            } elseif (preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $match)) {
                $videoId = (string)$match[1];
            }
        }

        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
            return null;
        }
        return 'https://www.youtube-nocookie.com/embed/' . $videoId;
    }

    private static function safeRichColor(string $color): ?string
    {
        $color = strtolower(trim($color));
        if (preg_match('/^#[0-9a-f]{3,8}$/', $color)) {
            return $color;
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/', $color)) {
            return $color;
        }
        $named = [
            'black', 'white', 'red', 'green', 'blue', 'navy', 'teal',
            'maroon', 'purple', 'gray', 'grey', 'silver', 'orange',
            'yellow', 'olive', 'aqua', 'fuchsia',
        ];
        return in_array($color, $named, true) ? $color : null;
    }
}
