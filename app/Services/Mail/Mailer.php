<?php
namespace Book100\Services\Mail;

use Book100\Core\Database;
use Book100\Core\Env;
use Book100\Core\StoreUrl;
use Book100\Core\Utf8Sanitizer;
use Book100\Repository\SettingsRepository;
use PDO;
use RuntimeException;
use Throwable;

final class Mailer
{
    public function processQueue(int $limit = 50, bool $dryRun = false): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE status IN ('queued','failed_retry') ORDER BY id ASC LIMIT ?");
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $this->processRows($stmt->fetchAll(PDO::FETCH_ASSOC), $dryRun);
    }

    public function processOne(int $id, bool $dryRun = false): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM email_logs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'dry_run' => $dryRun,
                'total' => 0,
                'sent' => 0,
                'logged' => 0,
                'failed' => 1,
                'items' => [['id' => $id, 'status' => 'failed_retry', 'message' => 'Nie znaleziono wiadomości.']],
            ];
        }
        return $this->processRows([$row], $dryRun);
    }

    private function processRows(array $rows, bool $dryRun): array
    {
        $pdo = Database::pdo();
        $report = ['dry_run' => $dryRun, 'total' => count($rows), 'sent' => 0, 'logged' => 0, 'failed' => 0, 'items' => []];

        foreach ($rows as $row) {
            $status = 'queued';
            $message = 'not_processed';
            try {
                $result = $dryRun ? ['ok' => true, 'mode' => 'dry_run'] : $this->send($row);
                if (!empty($result['ok'])) {
                    $status = 'sent';
                    $message = (string)($result['mode'] ?? 'sent');
                    if (($result['mode'] ?? '') === 'log') {
                        $report['logged']++;
                    } else {
                        $report['sent']++;
                    }
                } else {
                    $status = 'failed_retry';
                    $message = (string)($result['error'] ?? 'mail_error');
                    $report['failed']++;
                }
            } catch (Throwable $exception) {
                $status = 'failed_retry';
                $message = $exception->getMessage();
                $report['failed']++;
            }

            if (!$dryRun) {
                $update = $pdo->prepare(
                    'UPDATE email_logs
                     SET status = ?, sent_at = ?, last_error = ?, attempts = COALESCE(attempts,0)+1
                     WHERE id = ?'
                );
                $update->execute([
                    $status,
                    $status === 'sent' ? date('Y-m-d H:i:s') : null,
                    $status === 'sent' ? null : mb_substr($message, 0, 4000),
                    (int)$row['id'],
                ]);
            }
            $report['items'][] = [
                'id' => (int)$row['id'],
                'to' => (string)($row['to_email'] ?? ''),
                'subject' => (string)($row['subject'] ?? ''),
                'status' => $status,
                'message' => $message,
            ];
        }

        return $report;
    }

    private function send(array $row): array
    {
        $settings = new SettingsRepository();
        $transport = strtolower(trim((string)Env::get('MAIL_TRANSPORT', 'log')));
        $shopName = trim(Utf8Sanitizer::normalize((string)$settings->get('shop_name', 'Wydawnictwo Katolickie ARKA')));
        if ($shopName === '') $shopName = 'Wydawnictwo Katolickie ARKA';
        $from = trim((string)Env::get('MAIL_FROM', $settings->get('shop_email', 'biuro@arka-pojednanie.pl')));
        $fromName = trim(Utf8Sanitizer::normalize((string)Env::get('MAIL_FROM_NAME', $shopName))) ?: $shopName;
        $replyTo = trim((string)($row['reply_to'] ?? ''));
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = trim((string)Env::get('MAIL_REPLY_TO', $from));
        }
        $to = trim((string)($row['to_email'] ?? ''));
        $subject = $this->headerValue(Utf8Sanitizer::normalize((string)($row['subject'] ?? '')));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '') {
            return ['ok' => false, 'error' => 'Nieprawidłowy adres nadawcy, odbiorcy albo temat wiadomości.'];
        }
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = $from;

        $html = trim(Utf8Sanitizer::normalize((string)($row['body'] ?? '')));
        if ($html === '') $html = (new EmailTemplate())->generic($subject, 'Wiadomość ze sklepu ' . $shopName . '.');
        if (!preg_match('/<\s*(?:html|body|table|p|div|h[1-6])\b/i', $html)) {
            $html = (new EmailTemplate())->generic($subject, $html);
        }

        $unsubscribeUrl = '';
        if (str_starts_with((string)($row['template'] ?? ''), 'mailing_campaign_')) {
            $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#href=["\'](https?://[^"\']+/newsletter/wypisz/[a-f0-9]{40})["\']#i', $decodedHtml, $match)) {
                $unsubscribeUrl = $this->safeUrl((string)$match[1]);
            }
        }

        $message = $this->buildMessage($to, $from, $fromName, $replyTo, $subject, $html, $unsubscribeUrl);

        if ($transport === 'log') {
            $directory = dirname(__DIR__, 3) . '/storage/logs/mail';
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                return ['ok' => false, 'error' => 'Nie udało się utworzyć katalogu logów poczty.'];
            }
            $file = $directory . '/mail-' . date('Ymd-His') . '-' . (int)$row['id'] . '.eml';
            if (file_put_contents($file, $message['raw']) === false) {
                return ['ok' => false, 'error' => 'Nie udało się zapisać kontrolnej wiadomości EML.'];
            }
            return ['ok' => true, 'mode' => 'log', 'file' => $file];
        }

        if ($transport === 'mail') {
            $headers = $message['headers'];
            unset($headers['To'], $headers['Subject']);
            $ok = mail(
                $to,
                $message['headers']['Subject'],
                $message['body'],
                $this->headersToString($headers)
            );
            return $ok
                ? ['ok' => true, 'mode' => 'mail']
                : ['ok' => false, 'error' => 'PHP mail() zwrócił błąd.'];
        }

        if ($transport === 'smtp') {
            $this->sendSmtp($from, $to, $message['raw']);
            return ['ok' => true, 'mode' => 'smtp'];
        }

        return ['ok' => false, 'error' => 'Nieobsługiwany MAIL_TRANSPORT: ' . $transport];
    }

    private function buildMessage(
        string $to,
        string $from,
        string $fromName,
        string $replyTo,
        string $subject,
        string $html,
        string $unsubscribeUrl = ''
    ): array {
        $domain = strtolower((string)substr(strrchr($from, '@') ?: '@localhost', 1));
        if (!preg_match('/^[a-z0-9.-]+$/', $domain)) $domain = 'localhost';
        $boundary = 'b100_' . bin2hex(random_bytes(18));
        $encodedSubject = $this->encodeHeader($subject);
        $encodedName = $this->encodeHeader($fromName);
        $plain = $this->plainText($html);
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($plain) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n"
            . '--' . $boundary . "--\r\n";

        $headers = [
            'Date' => date(DATE_RFC2822),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . '.' . time() . '@' . $domain . '>',
            'From' => $encodedName . ' <' . $from . '>',
            'Reply-To' => $replyTo,
            'To' => $to,
            'Subject' => $encodedSubject,
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer' => 'ARKA Transactional Mailer',
        ];
        if ($unsubscribeUrl !== '') {
            $headers['List-Unsubscribe'] = '<' . $unsubscribeUrl . '>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        if ($this->boolEnv('MAIL_DKIM_ENABLED')) {
            $dkim = $this->dkimHeader($headers, $body, $from);
            if ($dkim !== '') $headers = ['DKIM-Signature' => $dkim] + $headers;
        }

        return [
            'headers' => $headers,
            'body' => $body,
            'raw' => $this->headersToString($headers) . "\r\n\r\n" . $body,
        ];
    }

    private function sendSmtp(string $from, string $to, string $rawMessage): void
    {
        $host = trim((string)Env::get('SMTP_HOST', ''));
        $encryption = strtolower(trim((string)Env::get('SMTP_ENCRYPTION', 'tls')));
        $port = (int)Env::get('SMTP_PORT', $encryption === 'ssl' ? '465' : '587');
        $username = trim((string)Env::get('SMTP_USERNAME', ''));
        $password = (string)Env::get('SMTP_PASSWORD', '');
        if ($host === '' || $port < 1 || $port > 65535) {
            throw new RuntimeException('Uzupełnij host i port SMTP w Integracjach.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
            ],
        ]);
        $socket = @stream_socket_client($remote, $errorNumber, $errorMessage, 20, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new RuntimeException('Nie można połączyć się z SMTP: ' . ($errorMessage ?: 'błąd ' . $errorNumber));
        }
        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $clientName = parse_url(StoreUrl::base(), PHP_URL_HOST) ?: 'localhost';
            $this->command($socket, 'EHLO ' . $clientName, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Serwer SMTP nie uruchomił szyfrowania TLS.');
                }
                $this->command($socket, 'EHLO ' . $clientName, [250]);
            } elseif (!in_array($encryption, ['ssl', 'none', ''], true)) {
                throw new RuntimeException('Nieznany tryb szyfrowania SMTP.');
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334], true);
                $this->command($socket, base64_encode($password), [235], true);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $payload = preg_replace('/(?m)^\./', '..', $rawMessage) . "\r\n.";
            $this->command($socket, $payload, [250], true);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $expected, bool $sensitive = false): string
    {
        if (@fwrite($socket, $command . "\r\n") === false) {
            throw new RuntimeException('Połączenie SMTP zostało przerwane podczas wysyłki.');
        }
        try {
            return $this->expect($socket, $expected);
        } catch (Throwable $exception) {
            if ($sensitive) {
                throw new RuntimeException('Serwer SMTP odrzucił uwierzytelnienie lub treść wiadomości.');
            }
            throw $exception;
        }
    }

    private function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 4096)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $meta = stream_get_meta_data($socket);
        if (!empty($meta['timed_out'])) throw new RuntimeException('Serwer SMTP nie odpowiedział w wymaganym czasie.');
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $safe = trim((string)preg_replace('/[\r\n]+/', ' ', $response));
            throw new RuntimeException('SMTP ' . $code . ': ' . mb_substr($safe, 0, 500));
        }
        return $response;
    }

    private function dkimHeader(array $headers, string $body, string $from): string
    {
        $domain = strtolower(trim((string)Env::get('MAIL_DKIM_DOMAIN', substr(strrchr($from, '@') ?: '@', 1))));
        $selector = strtolower(trim((string)Env::get('MAIL_DKIM_SELECTOR', 'arka')));
        $encodedKey = trim((string)Env::get('MAIL_DKIM_PRIVATE_KEY_BASE64', ''));
        if (!preg_match('/^[a-z0-9.-]+$/', $domain) || !preg_match('/^[a-z0-9._-]+$/', $selector) || $encodedKey === '') {
            throw new RuntimeException('DKIM jest włączony, ale brakuje domeny, selektora albo klucza prywatnego.');
        }
        $privateKey = base64_decode($encodedKey, true);
        if ($privateKey === false) $privateKey = str_replace('\n', "\n", $encodedKey);
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) throw new RuntimeException('Klucz prywatny DKIM jest nieprawidłowy.');

        $signedNames = ['from', 'to', 'subject', 'date', 'message-id', 'mime-version', 'content-type'];
        $canonicalBody = $this->canonicalBody($body);
        $value = 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=' . $domain
            . '; s=' . $selector . '; t=' . time()
            . '; h=' . implode(':', $signedNames)
            . '; bh=' . base64_encode(hash('sha256', $canonicalBody, true))
            . '; b=';

        $headerLookup = [];
        foreach ($headers as $name => $headerValue) {
            $headerLookup[strtolower($name)] = (string)$headerValue;
        }

        $signing = '';
        foreach ($signedNames as $signedName) {
            if (array_key_exists($signedName, $headerLookup)) {
                $signing .= $this->canonicalHeader($signedName, $headerLookup[$signedName]) . "\r\n";
            }
        }
        $signing .= $this->canonicalHeader('DKIM-Signature', $value);
        $signature = '';
        if (!openssl_sign($signing, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Nie udało się podpisać wiadomości kluczem DKIM.');
        }
        return $value . base64_encode($signature);
    }

    private function canonicalHeader(string $name, string $value): string
    {
        $value = preg_replace('/\r?\n[ \t]+/', ' ', $value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        return strtolower($name) . ':' . trim((string)$value);
    }

    private function canonicalBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = array_map(
            static fn(string $line): string => rtrim((string)preg_replace('/[ \t]+/', ' ', $line)),
            explode("\n", $body)
        );
        while ($lines !== [] && end($lines) === '') array_pop($lines);
        return implode("\r\n", $lines) . "\r\n";
    }

    private function headersToString(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) $lines[] = $name . ': ' . $value;
        return implode("\r\n", $lines);
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/h[1-6]|/tr)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", (string)$text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string)$text);
        return trim((string)$text) . "\n";
    }

    private function encodeHeader(string $value): string
    {
        return mb_encode_mimeheader($this->headerValue($value), 'UTF-8', 'B', "\r\n");
    }

    private function headerValue(string $value): string
    {
        return trim((string)preg_replace('/[\r\n]+/', ' ', $value));
    }

    private function safeUrl(string $value): string
    {
        $value = trim((string)preg_replace('/[\r\n]+/', '', $value));
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function boolEnv(string $key): bool
    {
        return in_array(strtolower((string)Env::get($key, '0')), ['1', 'true', 'yes', 'on'], true);
    }
}
