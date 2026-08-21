<?php
namespace Book100\Services\Registrations;

use Book100\Core\Session;
use Book100\Repository\EmailLogRepository;
use Book100\Repository\RegistrationFormRepository;
use Book100\Repository\RegistrationRepository;
use Book100\Services\Mail\EmailTemplate;
use Book100\Services\Mail\Mailer;
use RuntimeException;

final class RegistrationService
{
    public function submit(int $formId, array $context, array $input): int
    {
        $form = (new RegistrationFormRepository())->find($formId);
        if (!$form || ($form['status'] ?? '') !== 'active') {
            throw new RuntimeException('Ten formularz nie jest obecnie dostępny.');
        }
        if (trim((string)($input['website'] ?? '')) !== '') {
            throw new RuntimeException('Nie udało się przyjąć zgłoszenia.');
        }
        if (empty($input['privacy_consent'])) {
            throw new RuntimeException('Zaznacz zgodę na przetwarzanie danych zgłoszenia.');
        }
        $this->checkRateLimit();

        $posted = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $values = [];
        $personParts = [];
        $email = '';
        $phone = '';
        foreach (RegistrationFormRepository::fields($form) as $field) {
            if (empty($field['enabled'])) continue;
            $key = (string)($field['key'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key)) continue;
            $type = in_array(($field['type'] ?? ''), ['email', 'tel'], true) ? (string)$field['type'] : 'text';
            $value = trim((string)($posted[$key] ?? ''));
            if (!empty($field['required']) && $value === '') {
                throw new RuntimeException('Uzupełnij pole: ' . (string)($field['label'] ?? $key) . '.');
            }
            if (mb_strlen($value) > 500) {
                throw new RuntimeException('Jedno z pól jest zbyt długie.');
            }
            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Podaj poprawny adres e-mail.');
            }
            $values[$key] = [
                'label' => trim((string)($field['label'] ?? $key)),
                'value' => $value,
            ];
            if (in_array($key, ['first_name', 'last_name'], true) && $value !== '') $personParts[] = $value;
            if ($type === 'email' && $email === '') $email = $value;
            if ($type === 'tel' && $phone === '') $phone = $value;
        }

        $sourceLabel = trim((string)($context['label'] ?? $form['name']));
        $personName = trim(implode(' ', $personParts));
        $registrationId = (new RegistrationRepository())->create([
            'form_id' => (int)$form['id'],
            'content_page_id' => ($context['type'] ?? '') === 'page' ? (int)($context['id'] ?? 0) : null,
            'event_id' => ($context['type'] ?? '') === 'event' ? (int)($context['id'] ?? 0) : null,
            'source_label' => $sourceLabel,
            'person_name' => $personName,
            'email' => $email,
            'phone' => $phone,
            'values' => $values,
            'status' => 'new',
            'consent_at' => date('Y-m-d H:i:s'),
        ]);

        $lines = [
            'Nowe zgłoszenie z formularza: ' . (string)$form['name'],
            'Źródło: ' . $sourceLabel,
            'Numer zgłoszenia: ' . $registrationId,
            '',
        ];
        foreach ($values as $value) {
            $lines[] = $value['label'] . ': ' . ($value['value'] !== '' ? $value['value'] : '—');
        }
        $subject = trim((string)($form['email_subject'] ?? '')) ?: 'Nowe zgłoszenie — ' . $sourceLabel;
        $body = (new EmailTemplate())->generic($subject, implode("\n", $lines));
        $emailLogId = (new EmailLogRepository())->queueCustom(
            (string)$form['recipient_email'],
            $subject,
            $body,
            'registration_' . $registrationId,
            $personName,
            $email
        );
        (new Mailer())->processOne($emailLogId);

        return $registrationId;
    }

    private function checkRateLimit(): void
    {
        Session::start();
        $now = time();
        $recent = array_values(array_filter(
            is_array($_SESSION['registration_attempts'] ?? null) ? $_SESSION['registration_attempts'] : [],
            static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - 600
        ));
        if (count($recent) >= 5) {
            throw new RuntimeException('Wysłano zbyt wiele zgłoszeń. Spróbuj ponownie za kilka minut.');
        }
        $recent[] = $now;
        $_SESSION['registration_attempts'] = $recent;
    }
}
