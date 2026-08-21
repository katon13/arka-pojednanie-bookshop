<?php
namespace Book100\Services\Sales;

use Book100\Core\Paths;
use Book100\Repository\EmailLogRepository;
use Book100\Repository\SalesReportRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Mail\Mailer;
use DateTimeImmutable;
use RuntimeException;

final class SalesReportService
{
    private SalesReportRepository $reports;
    private SettingsRepository $settings;
    private SimpleXlsxWriter $xlsx;

    public function __construct(
        ?SalesReportRepository $reports = null,
        ?SettingsRepository $settings = null,
        ?SimpleXlsxWriter $xlsx = null
    ) {
        $this->reports = $reports ?? new SalesReportRepository();
        $this->settings = $settings ?? new SettingsRepository();
        $this->xlsx = $xlsx ?? new SimpleXlsxWriter();
    }

    public function selectedPeriod(mixed $year, mixed $month): array
    {
        $now = new DateTimeImmutable('now');
        $year = filter_var($year, FILTER_VALIDATE_INT) ?: (int)$now->format('Y');
        $month = filter_var($month, FILTER_VALIDATE_INT) ?: (int)$now->format('n');
        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            throw new RuntimeException('Nieprawidłowy miesiąc raportu.');
        }
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('+1 month');
        return [
            'year'=>$year,
            'month'=>$month,
            'start'=>$start->format('Y-m-d H:i:s'),
            'end'=>$end->format('Y-m-d H:i:s'),
            'start_date'=>$start->format('Y-m-d'),
            'end_date'=>$end->modify('-1 day')->format('Y-m-d'),
            'label'=>$this->monthName($month) . ' ' . $year,
        ];
    }

    public function dataset(int $year, int $month): array
    {
        return $this->datasetForRange('month', $year, $month);
    }

    public function datasetForRange(mixed $range, mixed $year, mixed $month = null): array
    {
        return $this->datasetForPeriod($this->selectedRange($range, $year, $month));
    }

    public function selectedRange(mixed $range, mixed $year, mixed $month = null): array
    {
        $range = strtolower(trim((string)$range));
        if ($range === '' || $range === 'month') {
            $period = $this->selectedPeriod($year, $month);
            $period['range'] = 'month';
            $period['slug'] = sprintf('%04d-%02d', (int)$period['year'], (int)$period['month']);
            return $period;
        }
        if ($range !== 'year') {
            throw new RuntimeException('Wybierz raport miesięczny albo roczny.');
        }

        $now = new DateTimeImmutable('now');
        $year = filter_var($year, FILTER_VALIDATE_INT) ?: (int)$now->format('Y');
        if ($year < 2020 || $year > (int)$now->format('Y')) {
            throw new RuntimeException('Nieprawidłowy rok raportu.');
        }
        $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
        $calendarEnd = $start->modify('+1 year');
        $currentYear = $year === (int)$now->format('Y');
        $end = $currentYear ? new DateTimeImmutable('tomorrow 00:00:00') : $calendarEnd;
        return [
            'year'=>$year,
            'month'=>1,
            'start'=>$start->format('Y-m-d H:i:s'),
            'end'=>$end->format('Y-m-d H:i:s'),
            'start_date'=>$start->format('Y-m-d'),
            'end_date'=>$currentYear ? $now->format('Y-m-d') : $calendarEnd->modify('-1 day')->format('Y-m-d'),
            'label'=>$currentYear ? 'rok ' . $year . ' — do dziś' : 'rok ' . $year,
            'range'=>'year',
            'slug'=>(string)$year,
        ];
    }

    public function exportFilename(array $dataset, string $extension): string
    {
        $extension = strtolower(trim($extension));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw new RuntimeException('Nieprawidłowy format raportu.');
        }
        $slug = preg_replace('/[^0-9a-z-]/i', '', (string)($dataset['period']['slug'] ?? ''));
        if ($slug === '') throw new RuntimeException('Nieprawidłowy okres raportu.');
        return 'sprzedaz-' . $slug . '.' . $extension;
    }

    private function datasetForPeriod(array $period): array
    {
        $orders = $this->reports->ordersForPeriod($period['start'], $period['end']);
        $summary = [
            'paid_orders'=>0, 'units'=>0, 'returned_units'=>0,
            'sales_net_cents'=>0, 'sales_vat_cents'=>0, 'sales_gross_cents'=>0,
            'shipping_net_cents'=>0, 'shipping_vat_cents'=>0, 'shipping_gross_cents'=>0,
            'discount_net_cents'=>0, 'discount_vat_cents'=>0, 'discount_gross_cents'=>0,
            'total_net_cents'=>0, 'total_vat_cents'=>0, 'total_gross_cents'=>0,
            'refund_net_cents'=>0, 'refund_vat_cents'=>0, 'refund_gross_cents'=>0,
            'final_net_cents'=>0, 'final_vat_cents'=>0, 'final_gross_cents'=>0,
        ];
        $rows = [];
        $rates = [];

        foreach ($orders as $order) {
            $rate = $this->orderRate($order);
            $rates[Money::normalizedRate($rate)] = true;
            $subtotal = $this->component($order, 'subtotal_gross', 'subtotal_net', 'subtotal_vat', $rate);
            $discount = $this->component($order, 'discount_gross', 'discount_net', 'discount_vat', $rate);
            $shipping = $this->component($order, 'shipping_gross', 'shipping_net', 'shipping_vat', $rate);
            $total = $this->component($order, 'total_gross', 'total_net', 'total_vat', $rate);
            $productAfterDiscount = [
                'net'=>$subtotal['net'] - $discount['net'],
                'vat'=>$subtotal['vat'] - $discount['vat'],
                'gross'=>$subtotal['gross'] - $discount['gross'],
            ];

            if ($this->inPeriod((string)($order['paid_at'] ?? ''), $period)) {
                $summary['paid_orders']++;
                $summary['units'] += $this->orderUnits($order);
                $summary['sales_net_cents'] += $productAfterDiscount['net'];
                $summary['sales_vat_cents'] += $productAfterDiscount['vat'];
                $summary['sales_gross_cents'] += $productAfterDiscount['gross'];
                $summary['shipping_net_cents'] += $shipping['net'];
                $summary['shipping_vat_cents'] += $shipping['vat'];
                $summary['shipping_gross_cents'] += $shipping['gross'];
                $summary['discount_net_cents'] += $discount['net'];
                $summary['discount_vat_cents'] += $discount['vat'];
                $summary['discount_gross_cents'] += $discount['gross'];
                $summary['total_net_cents'] += $total['net'];
                $summary['total_vat_cents'] += $total['vat'];
                $summary['total_gross_cents'] += $total['gross'];
                $rows = array_merge($rows, $this->eventRows($order, 1, (string)$order['paid_at'], $rate, $discount, $shipping, $total));
            }

            if ($this->inPeriod((string)($order['refunded_at'] ?? ''), $period)) {
                $summary['returned_units'] += $this->orderUnits($order);
                $summary['refund_net_cents'] += $total['net'];
                $summary['refund_vat_cents'] += $total['vat'];
                $summary['refund_gross_cents'] += $total['gross'];
                $rows = array_merge($rows, $this->eventRows($order, -1, (string)$order['refunded_at'], $rate, $discount, $shipping, $total));
            }
        }

        $summary['final_net_cents'] = $summary['total_net_cents'] - $summary['refund_net_cents'];
        $summary['final_vat_cents'] = $summary['total_vat_cents'] - $summary['refund_vat_cents'];
        $summary['final_gross_cents'] = $summary['total_gross_cents'] - $summary['refund_gross_cents'];
        foreach ($summary as $key => $value) {
            if (str_ends_with($key, '_cents')) {
                $summary[substr($key, 0, -6)] = Money::decimal((int)$value);
            }
        }

        usort($rows, static fn(array $left, array $right): int => strcmp($left['date'] . $left['order_number'], $right['date'] . $right['order_number']));
        return [
            'period'=>$period,
            'summary'=>$summary,
            'rows'=>$rows,
            'vat_rates'=>array_keys($rates),
            'seller'=>$this->seller(),
        ];
    }

    public function csv(array $dataset): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) throw new RuntimeException('Nie można utworzyć eksportu CSV.');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'typ','data','zamowienie','sku','tytul','ilosc','cena_brutto_szt','netto','stawka_vat','vat','brutto',
            'rabat','wysylka_netto','vat_wysylki','wysylka_brutto','suma_zamowienia','platnosc','status',
        ], ';');
        foreach ($dataset['rows'] as $row) {
            fputcsv($stream, [
                $row['type'], $row['date'], $row['order_number'], $row['sku'], $row['title'], $row['quantity'],
                Money::decimal($row['unit_gross_cents']), Money::decimal($row['item_net_cents']), $row['vat_rate'],
                Money::decimal($row['item_vat_cents']), Money::decimal($row['item_gross_cents']), Money::decimal($row['discount_gross_cents']),
                Money::decimal($row['shipping_net_cents']), Money::decimal($row['shipping_vat_cents']), Money::decimal($row['shipping_gross_cents']),
                Money::decimal($row['order_total_gross_cents']), $row['payment_provider'], $row['status'],
            ], ';');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) throw new RuntimeException('Nie można odczytać eksportu CSV.');
        return $csv;
    }

    public function writeXlsx(array $dataset, string $path): void
    {
        $this->xlsx->write($path, $this->xlsxSheets($dataset));
    }

    public function generateStored(int $year, int $month, string $recipient = ''): array
    {
        $period = $this->selectedPeriod($year, $month);
        $currentMonth = new DateTimeImmutable('first day of this month 00:00:00');
        if (new DateTimeImmutable($period['end']) > $currentMonth) {
            throw new RuntimeException('Raport w historii można zapisać dopiero po zakończeniu miesiąca. Bieżące dane można pobrać jako CSV lub XLSX.');
        }
        $claim = $this->reports->claim($year, $month, $period['start'], $period['end'], $recipient);
        $report = $claim['report'];
        if (!$claim['claimed']) {
            $absolute = $this->absoluteReportPath((string)($report['file_path'] ?? ''));
            if (($report['status'] ?? '') === 'generated' && $absolute !== null && is_file($absolute)) return $report;
            if (($report['status'] ?? '') === 'generating') return $report;
        }

        $id = (int)$report['id'];
        try {
            $dataset = $this->dataset($year, $month);
            $relative = 'storage/reports/sprzedaz-' . sprintf('%04d-%02d', $year, $month) . '.xlsx';
            $absolute = Paths::projectRoot() . '/' . $relative;
            $this->writeXlsx($dataset, $absolute);
            $this->reports->markGenerated($id, $relative, $dataset['summary']);
            return $this->reports->find($id) ?? $report;
        } catch (\Throwable $exception) {
            $this->reports->markFailed($id, $exception->getMessage());
            throw $exception;
        }
    }

    public function queueReport(array $report, string $recipient, bool $sendNow = false): array
    {
        $recipient = trim($recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Podaj poprawny e-mail raportu.');
        $absolute = $this->absoluteReportPath((string)($report['file_path'] ?? ''));
        if ($absolute === null || !is_file($absolute)) throw new RuntimeException('Plik raportu nie istnieje.');
        $year = (int)$report['period_year'];
        $month = (int)$report['period_month'];
        $subject = 'Raport sprzedaży — ' . $this->monthName($month) . ' ' . $year;
        $body = 'W załączniku miesięczny raport sprzedaży za okres '
            . date('d.m.Y', strtotime((string)$report['period_start'])) . '–'
            . date('d.m.Y', strtotime((string)$report['period_end'])) . '.';
        $emailLogId = (new EmailLogRepository())->queueCustom(
            $recipient,
            $subject,
            $body,
            'sales_report_monthly',
            '',
            '',
            [[
                'path'=>(string)$report['file_path'],
                'name'=>'sprzedaz-' . sprintf('%04d-%02d', $year, $month) . '.xlsx',
                'mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]]
        );
        $this->reports->attachEmail((int)$report['id'], $emailLogId, $recipient);
        if ($sendNow) {
            (new Mailer())->processOne($emailLogId);
            return $this->reports->syncEmailStatus((int)$report['id']) ?? $report;
        }
        return $this->reports->find((int)$report['id']) ?? $report;
    }

    public function resend(int $reportId, string $recipient): array
    {
        $report = $this->reports->find($reportId);
        if (!$report) throw new RuntimeException('Nie znaleziono raportu.');
        $recipient = trim($recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Podaj poprawny e-mail raportu.');
        $this->reports->clearEmailForResend($reportId);
        return $this->queueReport($report, $recipient, true);
    }

    public function absoluteReportPath(string $relative): ?string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if (!preg_match('#^storage/reports/[a-zA-Z0-9._-]+\.xlsx$#D', $relative)) return null;
        $root = str_replace('\\', '/', Paths::projectRoot());
        $path = $root . '/' . $relative;
        return str_starts_with(str_replace('\\', '/', $path), $root . '/storage/reports/') ? $path : null;
    }

    public function reports(): array
    {
        $reports = $this->reports->recent();
        foreach ($reports as &$report) {
            if (!empty($report['email_log_id'])) $report = $this->reports->syncEmailStatus((int)$report['id']) ?? $report;
        }
        unset($report);
        return $reports;
    }

    private function eventRows(array $order, int $sign, string $date, string $rate, array $discount, array $shipping, array $total): array
    {
        $rows = [];
        foreach (($order['items'] ?? []) as $index => $item) {
            $itemGross = Money::cents((string)($item['total_gross'] ?? '0.00'));
            $itemSplit = $this->component($item, 'total_gross', 'total_net', 'total_vat', $item['vat_rate'] ?? $rate);
            $unitGross = Money::cents((string)($item['unit_price_gross'] ?? '0.00'));
            $itemRate = Money::normalizedRate((string)($item['vat_rate'] ?? $rate));
            $first = $index === 0;
            $rows[] = [
                'type'=>$sign > 0 ? 'SPRZEDAŻ' : 'ZWROT',
                'order_id'=>(int)($order['id'] ?? 0),
                'date'=>substr($date, 0, 10),
                'order_number'=>(string)($order['order_number'] ?? ''),
                'sku'=>(string)($item['sku'] ?? ''),
                'title'=>(string)($item['title'] ?? ''),
                'quantity'=>$sign * (int)($item['quantity'] ?? 0),
                'unit_gross_cents'=>$sign * $unitGross,
                'item_net_cents'=>$sign * $itemSplit['net'],
                'item_vat_cents'=>$sign * $itemSplit['vat'],
                'item_gross_cents'=>$sign * $itemGross,
                'vat_rate'=>$itemRate,
                'discount_gross_cents'=>$first ? $sign * $discount['gross'] : 0,
                'shipping_net_cents'=>$first ? $sign * $shipping['net'] : 0,
                'shipping_vat_cents'=>$first ? $sign * $shipping['vat'] : 0,
                'shipping_gross_cents'=>$first ? $sign * $shipping['gross'] : 0,
                'order_total_gross_cents'=>$first ? $sign * $total['gross'] : 0,
                'payment_provider'=>(string)($order['payment_provider'] ?? ''),
                'status'=>$sign > 0 ? (string)($order['payment_status'] ?? 'paid') : 'refunded',
            ];
        }
        return $rows;
    }

    private function component(array $row, string $grossKey, string $netKey, string $vatKey, string $rate): array
    {
        $gross = Money::cents((string)($row[$grossKey] ?? '0.00'));
        if (array_key_exists($netKey, $row) && $row[$netKey] !== null && array_key_exists($vatKey, $row) && $row[$vatKey] !== null) {
            return ['net'=>Money::cents((string)$row[$netKey]), 'vat'=>Money::cents((string)$row[$vatKey]), 'gross'=>$gross];
        }
        return Money::splitGross($gross, $rate);
    }

    private function orderRate(array $order): string
    {
        $rate = trim((string)($order['vat_rate'] ?? ''));
        if ($rate === '') $rate = $this->settings->get('sales_vat_rate', '5.00');
        return Money::normalizedRate($rate);
    }

    private function orderUnits(array $order): int
    {
        return array_sum(array_map(static fn(array $item): int => (int)($item['quantity'] ?? 0), $order['items'] ?? []));
    }

    private function inPeriod(string $date, array $period): bool
    {
        return $date !== '' && $date >= $period['start'] && $date < $period['end'];
    }

    private function seller(): array
    {
        return [
            'legal_name'=>$this->settings->get('seller_legal_name', 'Agencja ARKA'),
            'owner_name'=>$this->settings->get('seller_owner_name', 'Maciej Karwacki-Niecewicz'),
            'street'=>$this->settings->get('seller_street', 'ul. Św. Wawrzyńca 38/10'),
            'post_code'=>$this->settings->get('seller_post_code', '31-052'),
            'city'=>$this->settings->get('seller_city', 'Kraków'),
            'nip'=>$this->settings->get('seller_nip', '7791563475'),
        ];
    }

    private function xlsxSheets(array $dataset): array
    {
        $summary = $dataset['summary'];
        $seller = $dataset['seller'];
        $period = $dataset['period'];
        $rateLabel = count($dataset['vat_rates']) === 1 ? $dataset['vat_rates'][0] . '%' : (count($dataset['vat_rates']) > 1 ? 'stawki mieszane' : Money::normalizedRate($this->settings->get('sales_vat_rate', '5.00')) . '%');
        $money = static fn(string $key): array => ['value'=>((int)$summary[$key . '_cents']) / 100, 'type'=>'number', 'style'=>3];
        $summaryRows = [
            [['value'=>'RAPORT SPRZEDAŻY', 'style'=>1], '', '', ''],
            ['Podmiot', $seller['legal_name'], '', ''],
            ['Właściciel', $seller['owner_name'], '', ''],
            ['Adres', trim($seller['street'] . ', ' . $seller['post_code'] . ' ' . $seller['city']), '', ''],
            ['NIP', $seller['nip'], '', ''],
            ['Okres', $period['start_date'] . ' – ' . $period['end_date'], '', ''],
            ['Stawka VAT', $rateLabel, '', ''],
            ['', '', '', ''],
            [['value'=>'Pozycja', 'style'=>2], ['value'=>'Wartość', 'style'=>2], '', ''],
            ['Liczba opłaconych zamówień', ['value'=>(int)$summary['paid_orders'], 'type'=>'number', 'style'=>5], '', ''],
            ['Liczba sprzedanych książek', ['value'=>(int)$summary['units'], 'type'=>'number', 'style'=>5], '', ''],
            ['Sprzedaż produktów netto po rabatach', $money('sales_net'), '', ''],
            ['VAT produktów', $money('sales_vat'), '', ''],
            ['Sprzedaż produktów brutto po rabatach', $money('sales_gross'), '', ''],
            ['Rabaty brutto', $money('discount_gross'), '', ''],
            ['Wysyłka netto', $money('shipping_net'), '', ''],
            ['VAT wysyłki', $money('shipping_vat'), '', ''],
            ['Wysyłka brutto', $money('shipping_gross'), '', ''],
            ['Razem sprzedaż netto', $money('total_net'), '', ''],
            ['Razem VAT', $money('total_vat'), '', ''],
            ['Razem sprzedaż brutto', $money('total_gross'), '', ''],
            ['Zwroty netto', $money('refund_net'), '', ''],
            ['Zwroty VAT', $money('refund_vat'), '', ''],
            ['Zwroty brutto', $money('refund_gross'), '', ''],
            ['Sprzedaż końcowa netto', ['value'=>((int)$summary['final_net_cents']) / 100, 'type'=>'number', 'style'=>7], '', ''],
            ['VAT końcowy', ['value'=>((int)$summary['final_vat_cents']) / 100, 'type'=>'number', 'style'=>7], '', ''],
            ['Sprzedaż końcowa brutto', ['value'=>((int)$summary['final_gross_cents']) / 100, 'type'=>'number', 'style'=>7], '', ''],
        ];

        $headers = ['Typ','Data','Numer zamówienia','SKU','Tytuł książki','Ilość','Cena brutto szt.','Netto','Stawka VAT','VAT','Brutto','Rabat','Wysyłka netto','VAT wysyłki','Wysyłka brutto','Suma zamówienia','Metoda płatności','Status'];
        $salesRows = [
            [['value'=>'SZCZEGÓŁY SPRZEDAŻY', 'style'=>1], '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Okres', $period['start_date'] . ' – ' . $period['end_date'], '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            array_fill(0, count($headers), ''),
            array_map(static fn(string $header): array => ['value'=>$header, 'style'=>2], $headers),
        ];
        foreach ($dataset['rows'] as $row) {
            $salesRows[] = [
                ['value'=>$row['type'], 'style'=>5],
                ['value'=>$row['date'], 'type'=>'date', 'style'=>4],
                ['value'=>$row['order_number'], 'style'=>5],
                ['value'=>$row['sku'], 'style'=>5],
                ['value'=>$row['title'], 'style'=>5],
                ['value'=>$row['quantity'], 'type'=>'number', 'style'=>5],
                ['value'=>$row['unit_gross_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['item_net_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>Money::rateBasisPoints($row['vat_rate']) / 10000, 'type'=>'number', 'style'=>6],
                ['value'=>$row['item_vat_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['item_gross_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['discount_gross_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['shipping_net_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['shipping_vat_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['shipping_gross_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['order_total_gross_cents'] / 100, 'type'=>'number', 'style'=>3],
                ['value'=>$row['payment_provider'], 'style'=>5],
                ['value'=>$row['status'], 'style'=>5],
            ];
        }
        return [
            ['name'=>'PODSUMOWANIE', 'rows'=>$summaryRows, 'widths'=>[42,22,2,2], 'freeze_row'=>9, 'merges'=>['A1:D1']],
            ['name'=>'SPRZEDAŻ', 'rows'=>$salesRows, 'widths'=>[12,12,20,16,38,9,16,14,12,14,14,14,16,15,17,18,18,18], 'freeze_row'=>4, 'filter_range'=>'A4:R' . max(4, count($salesRows)), 'merges'=>['A1:R1']],
        ];
    }

    private function monthName(int $month): string
    {
        return [1=>'styczeń',2=>'luty',3=>'marzec',4=>'kwiecień',5=>'maj',6=>'czerwiec',7=>'lipiec',8=>'sierpień',9=>'wrzesień',10=>'październik',11=>'listopad',12=>'grudzień'][$month] ?? '';
    }
}
