<?php
namespace Book100\Services\Sales;

use RuntimeException;

final class SimpleXlsxWriter
{
    /**
     * @param list<array{name:string,rows:list<list<mixed>>,widths?:list<float>,freeze_row?:int,filter_range?:string,merges?:list<string>}> $sheets
     */
    public function write(string $path, array $sheets): void
    {
        if ($sheets === []) throw new RuntimeException('Arkusz XLSX nie zawiera danych.');
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu raportu.');
        }

        $entries = [
            '[Content_Types].xml' => $this->contentTypes(count($sheets)),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/app.xml' => $this->appProperties($sheets),
            'docProps/core.xml' => $this->coreProperties(),
            'xl/workbook.xml' => $this->workbook($sheets),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(count($sheets)),
            'xl/styles.xml' => $this->styles(),
        ];
        foreach ($sheets as $index => $sheet) {
            $entries['xl/worksheets/sheet' . ($index + 1) . '.xml'] = $this->worksheet($sheet);
        }

        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
        $this->writeZip($temporary, $entries);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Nie udało się zapisać pliku XLSX.');
        }
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $this->xmlHeader()
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $overrides . '</Types>';
    }

    private function rootRelationships(): string
    {
        return $this->xmlHeader()
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function appProperties(array $sheets): string
    {
        $titles = '';
        foreach ($sheets as $sheet) $titles .= '<vt:lpstr>' . $this->escape((string)$sheet['name']) . '</vt:lpstr>';
        return $this->xmlHeader()
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>ARKA</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Arkusze</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($sheets) . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private function coreProperties(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return $this->xmlHeader()
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Agencja ARKA</dc:creator><cp:lastModifiedBy>Agencja ARKA</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function workbook(array $sheets): string
    {
        $xml = '';
        foreach ($sheets as $index => $sheet) {
            $name = mb_substr((string)$sheet['name'], 0, 31);
            $xml .= '<sheet name="' . $this->escape($name) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return $this->xmlHeader()
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<workbookPr date1904="false"/><sheets>' . $xml . '</sheets><calcPr calcId="0" fullCalcOnLoad="1"/></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $xml = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $this->xmlHeader() . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $xml . '</Relationships>';
    }

    private function styles(): string
    {
        return $this->xmlHeader() . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0.00 &quot;PLN&quot;"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/><numFmt numFmtId="166" formatCode="0.00%"/></numFmts>'
            . '<fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FF5D462D"/><name val="Aptos Display"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF8B6F47"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2EEE8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9D2C8"/></left><right style="thin"><color rgb="FFD9D2C8"/></right><top style="thin"><color rgb="FFD9D2C8"/></top><bottom style="thin"><color rgb="FFD9D2C8"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function worksheet(array $sheet): string
    {
        $rows = $sheet['rows'] ?? [];
        $maxColumns = 1;
        foreach ($rows as $row) $maxColumns = max($maxColumns, count($row));
        $dimension = 'A1:' . $this->columnName($maxColumns) . max(1, count($rows));
        $columns = '';
        foreach (($sheet['widths'] ?? []) as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="' . $column . '" max="' . $column . '" width="' . max(6, min(60, (float)$width)) . '" customWidth="1"/>';
        }
        $sheetViews = '<sheetViews><sheetView workbookViewId="0">';
        $freezeRow = max(0, (int)($sheet['freeze_row'] ?? 0));
        if ($freezeRow > 0) {
            $sheetViews .= '<pane ySplit="' . $freezeRow . '" topLeftCell="A' . ($freezeRow + 1) . '" activePane="bottomLeft" state="frozen"/>';
        }
        $sheetViews .= '</sheetView></sheetViews>';
        $rowXml = '';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $cells .= $this->cell($this->columnName($columnIndex + 1) . $number, $value);
            }
            $height = $number === 1 ? ' ht="24" customHeight="1"' : '';
            $rowXml .= '<row r="' . $number . '"' . $height . '>' . $cells . '</row>';
        }
        $merges = '';
        if (($sheet['merges'] ?? []) !== []) {
            $merges = '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $range) $merges .= '<mergeCell ref="' . $this->escape((string)$range) . '"/>';
            $merges .= '</mergeCells>';
        }
        $filter = !empty($sheet['filter_range']) ? '<autoFilter ref="' . $this->escape((string)$sheet['filter_range']) . '"/>' : '';
        return $this->xmlHeader()
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="' . $dimension . '"/>'
            . $sheetViews . '<sheetFormatPr defaultRowHeight="15"/>' . ($columns !== '' ? '<cols>' . $columns . '</cols>' : '')
            . '<sheetData>' . $rowXml . '</sheetData>' . $filter . $merges
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
    }

    private function cell(string $reference, mixed $cell): string
    {
        $style = 0;
        $type = null;
        $value = $cell;
        if (is_array($cell) && array_key_exists('value', $cell)) {
            $value = $cell['value'];
            $style = max(0, (int)($cell['style'] ?? 0));
            $type = $cell['type'] ?? null;
        }
        $attributes = ' r="' . $reference . '"' . ($style > 0 ? ' s="' . $style . '"' : '');
        if ($value === null || $value === '') return '<c' . $attributes . '/>';
        if ($type === 'date') {
            $timestamp = strtotime((string)$value . ' 00:00:00 UTC');
            if ($timestamp === false) return '<c' . $attributes . '/>';
            $serial = ($timestamp / 86400) + 25569;
            return '<c' . $attributes . '><v>' . number_format($serial, 0, '.', '') . '</v></c>';
        }
        if ($type === 'number' || is_int($value) || is_float($value)) {
            return '<c' . $attributes . '><v>' . $this->number($value) . '</v></c>';
        }
        return '<c' . $attributes . ' t="inlineStr"><is><t xml:space="preserve">' . $this->escape((string)$value) . '</t></is></c>';
    }

    /** @param array<string,string> $entries */
    private function writeZip(string $path, array $entries): void
    {
        $file = fopen($path, 'wb');
        if ($file === false) throw new RuntimeException('Nie można utworzyć archiwum XLSX.');
        $central = '';
        $offset = 0;
        $count = 0;
        [$dosTime, $dosDate] = $this->dosTimestamp();
        try {
            foreach ($entries as $name => $data) {
                $compressed = gzdeflate($data, 9);
                if ($compressed === false) throw new RuntimeException('Nie udało się skompresować raportu XLSX.');
                $nameBytes = (string)$name;
                $crc = crc32($data);
                $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($compressed), strlen($data), strlen($nameBytes), 0)
                    . $nameBytes . $compressed;
                if (fwrite($file, $local) !== strlen($local)) throw new RuntimeException('Nie udało się zapisać raportu XLSX.');
                $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($compressed), strlen($data), strlen($nameBytes), 0, 0, 0, 0, 0, $offset)
                    . $nameBytes;
                $offset += strlen($local);
                $count++;
            }
            $centralOffset = $offset;
            if (fwrite($file, $central) !== strlen($central)) throw new RuntimeException('Nie udało się zapisać indeksu XLSX.');
            $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $centralOffset, 0);
            if (fwrite($file, $end) !== strlen($end)) throw new RuntimeException('Nie udało się zamknąć raportu XLSX.');
        } finally {
            fclose($file);
        }
    }

    /** @return array{int,int} */
    private function dosTimestamp(): array
    {
        $date = getdate();
        $year = max(1980, (int)$date['year']);
        return [
            ((int)$date['hours'] << 11) | ((int)$date['minutes'] << 5) | intdiv((int)$date['seconds'], 2),
            (($year - 1980) << 9) | ((int)$date['mon'] << 5) | (int)$date['mday'],
        ];
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function number(mixed $value): string
    {
        if (is_int($value)) return (string)$value;
        $number = rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.');
        return $number === '-0' ? '0' : $number;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    }
}
