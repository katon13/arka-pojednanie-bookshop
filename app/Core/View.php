<?php
namespace Book100\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        if ($template === 'admin/message' && self::isAjax()) {
            $title = trim((string)($data['title'] ?? 'Gotowe'));
            $message = trim((string)($data['message'] ?? 'Operacja została wykonana.'));
            $isError = preg_match('/(?:^|[\s:])(nie(?:\s|[a-ząćęłńóśźż])|błąd|brak)/iu', $title) === 1;
            http_response_code($isError ? 422 : 200);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => !$isError,
                'title' => $title,
                'message' => $message,
                'back_url' => AdminUrl::route($data['backUrl'] ?? null),
                'form_action' => AdminUrl::route($data['formAction'] ?? null),
                'replace_url' => AdminUrl::route($data['replaceUrl'] ?? null),
                'page_title' => $data['pageTitle'] ?? null,
                'page_kicker' => $data['pageKicker'] ?? null,
                'media_url' => $data['mediaUrl'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        echo self::capture($template, $data);
    }

    public static function capture(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        $file = dirname(__DIR__, 2) . '/resources/views/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            return 'Missing view: ' . htmlspecialchars($template);
        }
        ob_start();
        include $file;
        $html = (string)ob_get_clean();
        return str_starts_with($template, 'admin/')
            ? AdminUrl::rewriteHtml($html)
            : $html;
    }

    private static function isAjax(): bool
    {
        return strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
    }
}
