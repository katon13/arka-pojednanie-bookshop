<?php
namespace Book100\Services\Mail;

final class MailDeliverabilityChecker
{
    public function check(string $fromEmail, string $dkimDomain = '', string $selector = 'default'): array
    {
        $emailDomain = strtolower((string)substr(strrchr($fromEmail, '@') ?: '', 1));
        $domain = strtolower(trim($dkimDomain)) ?: $emailDomain;
        $selector = strtolower(trim($selector)) ?: 'default';
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            return [
                'domain' => $domain,
                'spf' => ['ok'=>false, 'value'=>'Brak poprawnej domeny nadawcy'],
                'dkim' => ['ok'=>false, 'value'=>'Brak poprawnej domeny DKIM'],
                'dmarc' => ['ok'=>false, 'value'=>'Brak poprawnej domeny nadawcy'],
            ];
        }

        return [
            'domain' => $domain,
            'spf' => $this->txtCheck($domain, 'v=spf1'),
            'dkim' => $this->txtCheck($selector . '._domainkey.' . $domain, 'v=dkim1'),
            'dmarc' => $this->txtCheck('_dmarc.' . $domain, 'v=dmarc1'),
        ];
    }

    private function txtCheck(string $host, string $marker): array
    {
        if (!function_exists('dns_get_record')) {
            return ['ok'=>false, 'value'=>'Serwer PHP nie udostępnia kontroli DNS'];
        }
        $values = [];
        try {
            foreach (dns_get_record($host, DNS_TXT) ?: [] as $record) {
                $value = trim((string)($record['txt'] ?? ''));
                if ($value !== '') $values[] = $value;
            }
        } catch (\Throwable) {
            return ['ok'=>false, 'value'=>'Nie udało się odczytać DNS'];
        }
        $matched = '';
        foreach ($values as $value) {
            if (str_contains(strtolower($value), strtolower($marker))) {
                $matched = $value;
                break;
            }
        }
        return [
            'ok' => $matched !== '',
            'value' => $matched !== '' ? mb_substr($matched, 0, 240) : 'Nie znaleziono rekordu ' . strtoupper($marker),
        ];
    }
}
