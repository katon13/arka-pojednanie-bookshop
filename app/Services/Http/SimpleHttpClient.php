<?php
namespace Book100\Services\Http;

use Book100\Core\Utf8Sanitizer;

final class SimpleHttpClient
{
    public function get(string $url, array $headers = [], ?string $bearerToken = null): array
    {
        if ($bearerToken) $headers[] = 'Authorization: Bearer ' . $bearerToken;
        return $this->request('GET', $url, '', $headers);
    }

    public function postJson(string $url, array $payload, array $headers = [], ?array $basicAuth = null): array
    {
        $payload = $this->normalizePayload($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $this->request(
            'POST',
            $url,
            $json === false ? '{}' : $json,
            array_merge(['Content-Type: application/json'], $headers),
            $basicAuth
        );
    }

    public function putJson(string $url, array $payload, array $headers = [], ?array $basicAuth = null): array
    {
        $payload = $this->normalizePayload($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $this->request(
            'PUT',
            $url,
            $json === false ? '{}' : $json,
            array_merge(['Content-Type: application/json'], $headers),
            $basicAuth
        );
    }

    public function postForm(string $url, array $payload, array $headers = [], ?string $bearerToken = null): array
    {
        $payload = $this->normalizePayload($payload);
        if ($bearerToken) $headers[] = 'Authorization: Bearer ' . $bearerToken;
        return $this->request('POST', $url, http_build_query($payload), array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers));
    }

    private function request(string $method, string $url, string $body, array $headers, ?array $basicAuth = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'body' => '', 'error' => 'Brak rozszerzenia cURL w PHP.'];
        }
        $ch = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 25,
        ];
        if ($method !== 'GET') $options[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($ch, $options);
        if ($basicAuth) curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . $basicAuth[1]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $json = null;
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) $json = $decoded;
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'json' => $json, 'body' => is_string($response) ? $response : '', 'error' => $error ?: null];
    }

    private function normalizePayload(mixed $value): mixed
    {
        if (is_string($value)) {
            return Utf8Sanitizer::normalize($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $entry) {
                $out[$key] = $this->normalizePayload($entry);
            }
            return $out;
        }
        return $value;
    }
}
