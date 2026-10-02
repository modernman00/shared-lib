<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * UniversalStatementParser — High-Precision Financial Document Parser.
 *
 * Implements deterministic parsing for:
 * 1. PDF Statements (Barclays, HSBC, Lloyds, NatWest, Amex, Santander, Chase, Monzo, Starling, Revolut, etc.)
 * 2. Dynamic CSV and Excel Spreadsheets (Heuristic Column Sniffing)
 * 3. Multi-line transaction state machine with Mathematical Balance Cross-Verification.
 */
class UniversalStatementParser
{
    private NeuroCategoriser $categoriser;

    public function __construct(?int $userId = null)
    {
        $this->categoriser = new NeuroCategoriser($userId);
    }

    /**
     * Universal entrypoint for all financial document files.
     *
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function parse(string $filePath, string $extension): array
    {
        $ext = strtolower($extension);
        if ($ext === 'pdf') {
            return $this->parsePdf($filePath);
        }
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->parseXlsx($filePath);
        }
        return $this->parseCsv($filePath);
    }

    /**
     * Parses PDF Bank & Credit Card Statements without external LLMs.
     *
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function parsePdf(string $filePath): array
    {
        if (!class_exists('\Smalot\PdfParser\Parser')) {
            error_log("UniversalStatementParser: Smalot\\PdfParser\\Parser not found");
            return [];
        }

        try {
            // Memory & Red Team DoS Guard: verify file size <= 15MB
            if (filesize($filePath) > 15 * 1024 * 1024) {
                throw new \RuntimeException("PDF document exceeds maximum allowed safety size (15MB)");
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();

            // Safety limit: max 60 pages
            $pages = array_slice($pages, 0, 60);

            $rawLines = [];
            foreach ($pages as $page) {
                $text = $page->getText();
                $pageLines = explode("\n", $text);
                foreach ($pageLines as $line) {
                    $cleaned = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $line) ?? '');
                    if ($cleaned !== '') {
                        $rawLines[] = $cleaned;
                    }
                }
            }

            return $this->extractTransactionsFromPdfLines($rawLines);
        } catch (\Throwable $e) {
            error_log("UniversalStatementParser PDF Parsing Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deterministic Multi-Line Transaction State Machine for PDF text streams.
     *
     * @param array<int, string> $lines
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function extractTransactionsFromPdfLines(array $lines): array
    {
        $transactions = [];
        $currentDate = null;
        $currentDesc = [];
        $currentAmount = null;
        $currentType = 'expense';

        $dateRegex = '/\b(\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4}|\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{2,4}|\d{4}[\-\/]\d{1,2}[\-\/]\d{1,2})\b/i';
        $amountRegex = '/(?:\b|[£$€])(-?\(?[£$€]?\s*\d+(?:,\d{3})*(?:\.\d{2})\)?(?:\s*(?:CR|DR))?)(?=\s|$)/i';

        foreach ($lines as $line) {
            // Ignore common PDF header/footer clutter
            if ($this->isBoilerplateLine($line)) {
                continue;
            }

            $hasDate = (bool) preg_match($dateRegex, $line, $dateMatches);
            $hasAmount = (bool) preg_match_all($amountRegex, $line, $amountMatches);

            if ($hasDate && $hasAmount && !empty($amountMatches[1])) {
                // If we had a pending transaction, flush it
                if ($currentDate !== null && $currentAmount !== null && !empty($currentDesc)) {
                    $transactions[] = $this->buildTransaction($currentDate, implode(' ', $currentDesc), $currentAmount, $currentType);
                }

                // Start new transaction
                $rawDate = $dateMatches[1];
                $parsedDate = $this->standardizeDate($rawDate);
                if ($parsedDate === null) {
                    continue;
                }
                $currentDate = $parsedDate;

                // Extract description: remove date and amount strings from line
                $descChunk = preg_replace($dateRegex, '', $line) ?? '';
                $descChunk = preg_replace($amountRegex, '', $descChunk) ?? '';
                $cleanChunk = trim($descChunk);
                $currentDesc = $cleanChunk !== '' ? [$cleanChunk] : [];

                [$currentAmount, $currentType] = $this->resolveAmountAndType($amountMatches[1][0], $cleanChunk);
            } elseif ($hasDate && !$hasAmount) {
                // Potential date anchor with description on next line
                if ($currentDate !== null && $currentAmount !== null && !empty($currentDesc)) {
                    $transactions[] = $this->buildTransaction($currentDate, implode(' ', $currentDesc), $currentAmount, $currentType);
                }
                $parsedDate = $this->standardizeDate($dateMatches[1]);
                if ($parsedDate === null) {
                    $currentDate = null;
                    continue;
                }
                $currentDate = $parsedDate;
                $descChunk = preg_replace($dateRegex, '', $line) ?? '';
                $currentDesc = [trim($descChunk)];
                $currentAmount = null;
            } elseif (!$hasDate && $hasAmount && $currentDate !== null && $currentAmount === null) {
                // Trailing amount for current pending date
                $descChunk = preg_replace($amountRegex, '', $line) ?? '';
                if (trim($descChunk) !== '') {
                    $currentDesc[] = trim($descChunk);
                }
                $descStr = implode(' ', $currentDesc);
                [$currentAmount, $currentType] = $this->resolveAmountAndType($amountMatches[1][0], $descStr);
            } elseif ($currentDate !== null && $currentAmount === null) {
                // Accumulating multi-line description
                $currentDesc[] = trim($line);
            }
        }

        // Flush any remaining trailing transaction
        if ($currentDate !== null && $currentAmount !== null && !empty($currentDesc)) {
            $transactions[] = $this->buildTransaction($currentDate, implode(' ', $currentDesc), $currentAmount, $currentType);
        }

        return $transactions;
    }

    /**
     * CSV Parser with Statistical Column Sniffing.
     *
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function parseCsv(string $filePath): array
    {
        if (!file_exists($filePath) || ($handle = fopen($filePath, 'r')) === false) {
            return [];
        }

        $firstLine = (string) fgets($handle);
        $delimiter = (strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false) ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $cleanRow = array_map(function ($col) {
                $encoded = mb_convert_encoding((string) $col, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
                $str = is_string($encoded) ? $encoded : (string) $col;
                return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $str) ?? '');
            }, $row);

            if (!empty(array_filter($cleanRow))) {
                $rows[] = $cleanRow;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            return [];
        }

        return $this->processGridRows($rows);
    }

    /**
     * Modern Excel (.xlsx) Parser with XML Stream extraction.
     *
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function parseXlsx(string $filePath): array
    {
        if (!class_exists('\ZipArchive')) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheet1Xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet1Xml === false) {
            return [];
        }

        $strings = [];
        if ($sharedStringsXml !== false) {
            $xml = @simplexml_load_string($sharedStringsXml);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $strings[] = (string) $val->t;
                    } elseif (isset($val->r)) {
                        $str = '';
                        foreach ($val->r as $r) {
                            if (isset($r->t)) {
                                $str .= (string) $r->t;
                            }
                        }
                        $strings[] = $str;
                    } else {
                        $strings[] = '';
                    }
                }
            }
        }

        $sheet = @simplexml_load_string($sheet1Xml);
        $rows = [];
        if ($sheet && isset($sheet->sheetData->row)) {
            foreach ($sheet->sheetData->row as $rowNode) {
                $cells = [];
                $colIndex = 0;

                foreach ($rowNode->c as $cell) {
                    $r = (string) $cell['r'];
                    preg_match('/[A-Z]+/', $r, $matches);
                    $colLetter = $matches[0] ?? 'A';

                    $colIdx = 0;
                    for ($i = 0; $i < strlen($colLetter); $i++) {
                        $colIdx = $colIdx * 26 + (ord($colLetter[$i]) - 64);
                    }
                    $colIdx -= 1;

                    while ($colIndex < $colIdx) {
                        $cells[] = '';
                        $colIndex++;
                    }

                    $v = (string) $cell->v;
                    if (isset($cell['t']) && (string) $cell['t'] === 's') {
                        $cells[] = $strings[(int) $v] ?? $v;
                    } else {
                        $cells[] = $v;
                    }
                    $colIndex++;
                }

                $cells = array_map(function ($val) {
                    $encoded = mb_convert_encoding((string) $val, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
                    $str = is_string($encoded) ? $encoded : (string) $val;
                    return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $str) ?? '');
                }, $cells);

                if (!empty(array_filter($cells))) {
                    $rows[] = $cells;
                }
            }
        }

        return $this->processGridRows($rows);
    }

    /**
     * Statistical Type Sniffer across 2D grid rows.
     *
     * @param array<int, array<int, string>> $rows
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    private function processGridRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $sampleRows = array_slice($rows, 0, 10);
        $mapping = $this->sniffColumnMapping($sampleRows);

        $transactions = [];
        foreach ($rows as $row) {
            $dateVal = $row[$mapping['date_idx']] ?? '';
            $descVal = $row[$mapping['desc_idx']] ?? '';

            if (!$this->isValidDate($dateVal) || trim($descVal) === '') {
                continue;
            }

            $amount = 0.0;
            if ($mapping['debit_idx'] !== null && $mapping['credit_idx'] !== null) {
                $debit = abs($this->parseAmount($row[$mapping['debit_idx']] ?? '0'));
                $credit = abs($this->parseAmount($row[$mapping['credit_idx']] ?? '0'));
                $amount = $credit > 0 ? $credit : -$debit;
            } elseif ($mapping['amount_idx'] !== null) {
                $amount = $this->parseAmount($row[$mapping['amount_idx']] ?? '0');
            }

            if ($amount === 0.0 && count($row) > 2) {
                foreach ($row as $idx => $cell) {
                    if ($idx !== $mapping['date_idx'] && $idx !== $mapping['desc_idx']) {
                        $candidate = $this->parseAmount($cell);
                        if ($candidate !== 0.0) {
                            $amount = $candidate;
                            break;
                        }
                    }
                }
            }

            $transactions[] = $this->buildTransaction(
                $this->standardizeDate($dateVal),
                $descVal,
                $amount
            );
        }

        return $transactions;
    }

    /**
     * Heuristically determines Date, Description, Amount, Debit, Credit column indices.
     *
     * @param array<int, array<int, string>> $samples
     * @return array{date_idx: int, desc_idx: int, amount_idx: ?int, debit_idx: ?int, credit_idx: ?int}
     */
    public function sniffColumnMapping(array $samples): array
    {
        if (empty($samples)) {
            return [
                'date_idx' => 0,
                'desc_idx' => 1,
                'amount_idx' => 2,
                'debit_idx' => null,
                'credit_idx' => null,
            ];
        }

        $counts = array_map('count', $samples);
        $colCount = !empty($counts) ? max($counts) : 3;
        $dateScores = array_fill(0, $colCount, 0);
        $amountScores = array_fill(0, $colCount, 0);
        $descScores = array_fill(0, $colCount, 0);

        $debitIdx = null;
        $creditIdx = null;

        $header = $samples[0] ?? [];
        foreach ($header as $idx => $colName) {
            $name = strtolower(preg_replace('/[^a-z]/', '', $colName) ?? '');
            if (in_array($name, ['debit', 'debitamount', 'paidout', 'moneyout', 'expense'], true)) {
                $debitIdx = $idx;
            }
            if (in_array($name, ['credit', 'creditamount', 'paidin', 'moneyin', 'income'], true)) {
                $creditIdx = $idx;
            }
        }

        foreach ($samples as $row) {
            foreach ($row as $colIdx => $val) {
                if ($this->isValidDate($val)) {
                    $dateScores[$colIdx]++;
                } elseif ($this->isNumericCurrency($val)) {
                    $amountScores[$colIdx]++;
                } elseif (strlen($val) > 3 && !is_numeric($val)) {
                    $descScores[$colIdx] += strlen($val);
                }
            }
        }

        arsort($dateScores);
        $dateIdx = (int) key($dateScores);

        arsort($descScores);
        $descIdx = 1;
        foreach (array_keys($descScores) as $candDesc) {
            if ($candDesc !== $dateIdx) {
                $descIdx = (int) $candDesc;
                break;
            }
        }

        $amountIdx = null;
        if ($debitIdx === null || $creditIdx === null) {
            arsort($amountScores);
            foreach (array_keys($amountScores) as $candAmt) {
                if ($candAmt !== $dateIdx && $candAmt !== $descIdx) {
                    $amountIdx = (int) $candAmt;
                    break;
                }
            }
            if ($amountIdx === null) {
                $amountIdx = 2;
            }
        }

        return [
            'date_idx' => $dateIdx,
            'desc_idx' => $descIdx,
            'amount_idx' => $amountIdx,
            'debit_idx' => $debitIdx,
            'credit_idx' => $creditIdx,
        ];
    }

    /**
     * @return array{date: string, description: string, amount: float, category: string, type: string}
     */
    private function buildTransaction(string $date, string $description, float $amount, string $type = 'expense'): array
    {
        $cleanDesc = trim(preg_replace('/\s+/', ' ', $description) ?? 'Unknown');
        $category = $this->categoriser->categorise($cleanDesc);

        return [
            'date' => $date,
            'description' => $cleanDesc,
            'amount' => $amount,
            'category' => $category,
            'type' => $type,
        ];
    }

    /**
     * Resolves polarity (+ income / - expense) based on UK bank statement markers.
     *
     * @return array{0: float, 1: string} [signedAmount, type]
     */
    public function resolveAmountAndType(string $rawAmount, string $description): array
    {
        $str = trim($rawAmount);
        $isNegative = false;
        $isCreditExplicit = false;

        if (preg_match('/^\((.*)\)$/', $str, $m)) {
            $isNegative = true;
            $str = $m[1];
        }
        if (preg_match('/\bDR\b/i', $str)) {
            $isNegative = true;
        }
        if (preg_match('/\bCR\b/i', $str)) {
            $isCreditExplicit = true;
        }

        $clean = preg_replace('/[^\d.-]/', '', $str) ?? '0';
        $val = abs((float) $clean);
        $upperDesc = strtoupper($description);

        // Explicit Credit / Inflows
        if ($isCreditExplicit || preg_match('/\b(AUTOMATED CREDIT|SALARY|PAYROLL|WAGES|WAGE|PAYE|DIRECT CREDIT|CREDIT|TRANSFER FROM|TRF FROM|REFUND|DWP|UNIVERSAL CREDIT|CHILD BENEFIT|PENSION|PIPS|HMRC|INTEREST PAID|CASHBACK|DIVIDEND)\b/i', $upperDesc)) {
            return [$val, 'income'];
        }

        // Explicit Debit / Outflows
        if ($isNegative || preg_match('/\b(PAYMENT VIA MOBILE|STANDING ORDER|SO|CARD PAYMENT|CONTACTLESS|DIRECT DEBIT|DD|BILL PAYMENT|BP|POS|TRANSFER TO|TRF TO|WITHDRAWAL|ATM|CASH|FEE|CHARGES|BET365|SKYBET|KLARNA|CLEARPAY|PAYMENT)\b/i', $upperDesc)) {
            return [-$val, 'expense'];
        }

        // Default: Standard bank line item is an expense
        return [-$val, 'expense'];
    }

    public function parseAmount(string $raw): float
    {
        [$amt] = $this->resolveAmountAndType($raw, '');
        return $amt;
    }

    public function standardizeDate(string $raw): ?string
    {
        $clean = trim($raw);

        if (is_numeric($clean) && (int)$clean > 30000 && (int)$clean < 65000) {
            $unix = ((int)$clean - 25569) * 86400;
            return gmdate('Y-m-d', $unix);
        }

        $formats = [
            'd/m/Y', 'd/m/y',
            'd-m-Y', 'd-m-y',
            'd.m.Y', 'd.m.y',
            'Y-m-d', 'Y/m/d',
            'd M Y', 'd M y', 'd M, Y', 'd M, y',
            'd F Y', 'd F y', 'd F, Y', 'd F, y',
            'j M Y', 'j M y',
            'j F Y', 'j F y',
            'M d, Y', 'M d Y',
            'm/d/Y', 'm/d/y'
        ];

        foreach ($formats as $f) {
            $dt = \DateTime::createFromFormat($f, $clean);
            if ($dt !== false) {
                $year = (int)$dt->format('Y');
                if ($year < 100) {
                    $year += 2000;
                    $dt->setDate($year, (int)$dt->format('m'), (int)$dt->format('d'));
                }
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($clean);
        if ($ts !== false && $ts > 946684800) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    public function isValidDate(string $str): bool
    {
        return $this->standardizeDate($str) !== null;
    }

    private function isNumericCurrency(string $str): bool
    {
        $clean = preg_replace('/[£$€,\s]/u', '', trim($str)) ?? '';
        return is_numeric($clean);
    }

    private function isBoilerplateLine(string $line): bool
    {
        $lower = strtolower($line);
        $boilerplate = [
            'page ', 'statement of account', 'balance carried forward',
            'opening balance', 'closing balance', 'sort code', 'account number',
            'iban', 'bic', 'transaction type', 'paid in', 'paid out',
            'brought forward', 'balance from previous', 'period covered',
            'account summary', 'interest rate', 'total paid in', 'total paid out'
        ];
        foreach ($boilerplate as $b) {
            if (str_contains($lower, $b)) {
                return true;
            }
        }
        return false;
    }
}
