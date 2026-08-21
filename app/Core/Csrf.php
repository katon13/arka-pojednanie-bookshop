<?php
namespace Book100\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['_csrf'];
    }

    public static function check(): void
    {
        Session::start();
        $ok = isset($_POST['_csrf'], $_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf']);
        if (!$ok) { http_response_code(419); echo 'Błędny token formularza.'; exit; }
    }
}
