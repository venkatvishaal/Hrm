<?php
namespace App\Services;

class AttendanceImportService
{
    public function parse(string $filePath, ?string $originalName = null): array
    {
        $nameForExt = $originalName ?: $filePath;
        $ext = strtolower(pathinfo($nameForExt, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $this->parseCsv($filePath);
        }
        if ($ext === 'xlsx') {
            return $this->parseXlsx($filePath);
        }
        if ($ext === 'xls') {
            return $this->parseXls($filePath);
        }
        throw new \RuntimeException('Unsupported file format. Use CSV, XLS, or XLSX.');
    }

    private function parseXls(string $filePath): array
    {
        $script = __DIR__ . DIRECTORY_SEPARATOR . 'parse_legacy_xls.py';
        if (!is_file($script)) {
            throw new \RuntimeException('Legacy XLS parser is not installed. Save the file as XLSX and try again.');
        }
        $command = 'python ' . escapeshellarg($script) . ' ' . escapeshellarg($filePath);
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Unable to read the XLS file. Save it as XLSX and try again.');
        }
        $rows = json_decode(implode("\n", $output), true);
        if (!is_array($rows)) {
            throw new \RuntimeException('The XLS file could not be read. Save it as XLSX and try again.');
        }
        return $rows;
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $h = fopen($filePath, 'r');
        $header = array_map(static fn($value) => trim((string)$value, "\xEF\xBB\xBF \t\n\r\0\x0B"), fgetcsv($h) ?: []);
        while (($line = fgetcsv($h)) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($h);
        return $rows;
    }

    private function parseXlsx(string $filePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Cannot open XLSX file.');
        }

        $strings = [];
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared) {
            $xml = simplexml_load_string($shared);
            foreach ($xml->si as $si) {
                $strings[] = (string)$si->t;
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheet) {
            throw new \RuntimeException('sheet1.xml missing in XLSX.');
        }

        $xml = simplexml_load_string($sheet);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $vals = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $idx = $this->columnIndex($col);
                $type = (string)$c['t'];
                $v = (string)$c->v;
                if ($type === 'inlineStr') {
                    $value = (string)($c->is->t ?? '');
                } else {
                    $value = ($type === 's') ? ($strings[(int)$v] ?? '') : $v;
                }
                $vals[$idx] = trim((string)$value);
            }
            if ($vals) {
                ksort($vals);
                $rows[] = $vals;
            }
        }

        if (!$rows) {
            return [];
        }
        $headerRow = 0;
        $bestScore = -1;
        foreach ($rows as $i => $candidate) {
            if ($i > 12) {
                break;
            }
            $score = 0;
            foreach ($candidate as $value) {
                $key = trim(strtolower((string)$value));
                $compact = preg_replace('/[^a-z0-9]/', '', $key);
                if (in_array($compact, ['employeeid', 'employeecode', 'empcode', 'ec', 'employeename', 'name', 'staffname', 'dept', 'department', 'basicsalary', 'salary', 'grosssalary', 'netsalary', 'total', 'amount'], true)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $headerRow = $i;
            }
        }
        $header = [];
        foreach ($rows[$headerRow] as $col => $value) {
            $header[$col] = trim((string)$value);
        }
        $out = [];
        $maxCol = max(array_keys($header));
        for ($i = $headerRow + 1; $i < count($rows); $i++) {
            $line = [];
            for ($col = 0; $col <= $maxCol; $col++) {
                $line[] = $rows[$i][$col] ?? '';
            }
            $headerValues = [];
            for ($col = 0; $col <= $maxCol; $col++) {
                $headerValues[] = $header[$col] ?? '';
            }
            $combined = array_combine($headerValues, $line);
            if ($combined && array_filter($combined, static fn($value) => trim((string)$value) !== '')) {
                $out[] = $combined;
            }
        }
        return $out;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }
}
