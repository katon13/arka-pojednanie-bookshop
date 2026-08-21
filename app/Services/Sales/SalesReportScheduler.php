<?php
namespace Book100\Services\Sales;

use Book100\Repository\SalesReportRepository;
use Book100\Repository\SettingsRepository;
use DateTimeImmutable;

final class SalesReportScheduler
{
    public function run(?DateTimeImmutable $now = null): array
    {
        $settings = new SettingsRepository();
        if ($settings->get('sales_report_enabled', '0') !== '1') {
            return ['status'=>'disabled', 'message'=>'Automatyczny raport jest wyłączony.'];
        }
        $recipient = trim($settings->get('sales_report_email', ''));
        $day = max(1, min(28, (int)$settings->get('sales_report_day', '5')));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return ['status'=>'invalid_email', 'message'=>'Brak poprawnego adresu raportu.'];
        }
        $now ??= new DateTimeImmutable('now');
        if ((int)$now->format('j') < $day) {
            return ['status'=>'waiting', 'message'=>'Dzień wysyłki jeszcze nie nadszedł.'];
        }
        $period = $now->modify('first day of last month');
        $year = (int)$period->format('Y');
        $month = (int)$period->format('n');
        $service = new SalesReportService();
        $report = $service->generateStored($year, $month, $recipient);
        if (!empty($report['email_log_id'])) {
            (new SalesReportRepository())->syncEmailStatus((int)$report['id']);
            return ['status'=>'already_queued', 'report_id'=>(int)$report['id'], 'message'=>'Raport za ten okres został już przygotowany do wysyłki.'];
        }
        if (($report['status'] ?? '') !== 'generated') {
            return ['status'=>'generating', 'report_id'=>(int)$report['id'], 'message'=>'Raport jest w trakcie generowania.'];
        }
        $report = $service->queueReport($report, $recipient, false);
        return ['status'=>'queued', 'report_id'=>(int)$report['id'], 'message'=>'Raport został dodany do kolejki pocztowej.'];
    }
}
