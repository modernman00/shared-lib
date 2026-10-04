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
            $noticeMarkers = [
                'statement abbreviations',
                'ways to bank with natwest',
                'important information about compensation arrangements',
                'making a complaint',
                'financial services compensation scheme'
            ];

            foreach ($pages as $page) {
                $text = $page->getText();
                $lower = strtolower($text);

                // Only skip page if it has notice markers AND DOES NOT have transaction table markers
                $hasTable = (bool) preg_match('/(Date\s+Description|BROUGHT\s+FORWARD)/i', $text);
                if (!$hasTable) {
                    $isNotice = false;
                    foreach ($noticeMarkers as $m) {
                        if (str_contains($lower, $m)) {
                            $isNotice = true;
                            break;
                        }
                    }
                    if ($isNotice) {
                        continue;
                    }
                }

                $pageLines = explode("\n", $text);
                $inTable = false;
                foreach ($pageLines as $line) {
                    $cleaned = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $line) ?? '');
                    if ($cleaned === '') {
                        continue;
                    }
                    // Detect start of transaction table on this page
                    if (preg_match('/(Date\s+Description|BROUGHT\s+FORWARD)/i', $cleaned)) {
                        $inTable = true;
                    }
                    // Stop immediately when entering overdraft disclosures, interest notices, or fee schedules
                    if (preg_match('/(Interest\s*\(variable\)|Overdraft\s+Arrangements|arranged\s+overdraft|unarranged\s+overdraft|charging\s+periods?\s+starting)/i', $cleaned)) {
                        $inTable = false;
                        break;
                    }
                    if ($inTable) {
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
    /**
     * Deterministic Multi-Line Transaction State Machine for PDF text streams.
     * Incorporates Line-Start Anchoring, Sparse Date Inheritance, and Balance Delta Reconciliation.
     *
     * @param array<int, string> $lines
     * @return array<int, array{date: string, description: string, amount: float, category: string, type: string}>
     */
    public function extractTransactionsFromPdfLines(array $lines): array
    {
        $transactions = [];
        $activeLedgerDate = null;
        $statementYear = date('Y');
        $previousBalance = null;
        $accumulatedDesc = [];

        // 1. Scan headers for Statement Period / Year anchor
        $startYear = (int) date('Y');
        $endYear = (int) date('Y');
        $headerSlice = implode("\n", array_slice($lines, 0, 40));

        if (preg_match('/Period Covered\s+(?:\w+\s+)?\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})\s+to\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})/i', $headerSlice, $pm)) {
            $startYear = (int) $pm[1];
            $endYear = (int) $pm[2];
        } elseif (preg_match('/\b(\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4}))\s+BROUGHT FORWARD/i', $headerSlice, $bm)) {
            $startYear = (int) $bm[2];
            $endYear = $startYear;
            if (preg_match('/Statement Date\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})/i', $headerSlice, $sm)) {
                $endYear = (int) $sm[1];
            }
        } elseif (preg_match('/Statement Date\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})/i', $headerSlice, $sm)) {
            $endYear = (int) $sm[1];
            $startYear = $endYear;
        } elseif (preg_match('/\b(20\d{2})\b/', $headerSlice, $ym)) {
            $startYear = (int) $ym[1];
            $endYear = $startYear;
        }

        $currentYear = $startYear;
        $lastMonth = null;

        // Line-start date regex (anchored strictly at the start of the line or column)
        $leadingDateRegex = '/^\s*(\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*(?:\s+\d{2,4})?|\d{1,2}[\/\.\-]\d{1,2}(?:[\/\.\-]\d{2,4})?)\b/i';
        $amountPattern = '/(?:\b|[£$€])(-?\(?[£$€]?\s*\d{1,3}(?:,\d{3})*(?:\.\d{2})\)?(?:\s*(?:CR|DR))?)(?=\s|$)/i';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Detect initial opening/brought forward balance lines to seed previousBalance & activeLedgerDate
            if (preg_match('/(BROUGHT\s+FORWARD|PREVIOUS\s+BALANCE|OPENING\s+BALANCE|BALANCE\s+FROM\s+PREVIOUS)/i', $trimmed)) {
                $accumulatedDesc = []; // Reset description accumulator across pages
                if (preg_match($leadingDateRegex, $trimmed, $dateMatches)) {
                    $rawDate = $dateMatches[1];
                    if (preg_match('/\b(20\d{2})\b/', $rawDate, $ym)) {
                        $currentYear = (int) $ym[1];
                    }
                    $parsedDate = $this->standardizeDateWithYear($rawDate, (string) $currentYear);
                    if ($parsedDate !== null) {
                        $activeLedgerDate = $parsedDate;
                        $lastMonth = (int) date('m', (int) strtotime($parsedDate));
                    }
                }
                if (preg_match_all($amountPattern, $trimmed, $balMatches) && !empty($balMatches[1])) {
                    $rawBal = end($balMatches[1]);
                    $previousBalance = $this->parsePureFloat($rawBal);
                }
                continue;
            }

            // Ignore common PDF header/footer clutter
            if ($this->isBoilerplateLine($trimmed)) {
                continue;
            }

            $hasLeadingDate = (bool) preg_match($leadingDateRegex, $trimmed, $dateMatches);
            if ($hasLeadingDate) {
                $rawDate = $dateMatches[1];

                // Check for explicit 4-digit year in rawDate or month calendar roll-over
                if (preg_match('/\b(20\d{2})\b/', $rawDate, $ym)) {
                    $currentYear = (int) $ym[1];
                    if (preg_match('/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $rawDate, $monMatch)) {
                        $lastMonth = (int) date('m', (int) strtotime($monMatch[0] . ' 1 2000'));
                    }
                } elseif (preg_match('/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i', $rawDate, $monMatch)) {
                    $mNum = (int) date('m', (int) strtotime($monMatch[0] . ' 1 2000'));
                    if ($lastMonth !== null && $mNum < $lastMonth && $mNum <= 6 && $lastMonth >= 7) {
                        $currentYear = min($endYear, $currentYear + 1);
                    }
                    $lastMonth = $mNum;
                }

                $parsedDate = $this->standardizeDateWithYear($rawDate, (string) $currentYear);
                if ($parsedDate !== null) {
                    $activeLedgerDate = $parsedDate;
                }
                // Strip the leading date from the line
                $lineWithoutDate = trim(substr($trimmed, strlen($dateMatches[0])));
            } else {
                $lineWithoutDate = $trimmed;
            }

            $hasAmounts = (bool) preg_match_all($amountPattern, $lineWithoutDate, $amountMatches);

            if ($hasAmounts && !empty($amountMatches[1]) && $activeLedgerDate !== null) {
                $matchedAmounts = $amountMatches[1];
                $numAmounts = count($matchedAmounts);

                // Strip matched amounts from current line to isolate remaining description chunk
                $cleanChunk = $lineWithoutDate;
                foreach ($matchedAmounts as $mAmt) {
                    $pos = strrpos($cleanChunk, $mAmt);
                    if ($pos !== false) {
                        $cleanChunk = substr_replace($cleanChunk, '', $pos, strlen($mAmt));
                    }
                }
                $cleanChunk = trim(preg_replace('/[£$€]/', '', $cleanChunk) ?? '');
                if ($cleanChunk !== '') {
                    $accumulatedDesc[] = $cleanChunk;
                }

                $fullDescription = trim(preg_replace('/\s+/', ' ', implode(' ', $accumulatedDesc)) ?? '');
                $accumulatedDesc = []; // Reset accumulator immediately for next transaction

                $extractedTxAmount = null;
                $calculatedType = 'expense';

                if ($numAmounts >= 2) {
                    // Typical UK Statement Row: [Description...] [Paid In / Paid Out Amount] [Balance]
                    $txAmtStr = $matchedAmounts[$numAmounts - 2];
                    $balStr = $matchedAmounts[$numAmounts - 1];

                    $rawTxFloat = $this->parsePureFloat($txAmtStr);
                    $rawBalFloat = $this->parsePureFloat($balStr);

                    if ($previousBalance !== null) {
                        $delta = round($rawBalFloat - $previousBalance, 2);
                        if ($delta > 0.005) {
                            $calculatedType = 'income';
                            $extractedTxAmount = abs($rawTxFloat);
                        } elseif ($delta < -0.005) {
                            $calculatedType = 'expense';
                            $extractedTxAmount = -abs($rawTxFloat);
                        }
                    }

                    $previousBalance = $rawBalFloat;
                    if ($extractedTxAmount === null) {
                        [$extractedTxAmount, $calculatedType] = $this->resolveAmountAndType($txAmtStr, $fullDescription);
                    }
                } else {
                    // Single amount found on the line
                    $singleAmtStr = $matchedAmounts[0];
                    [$extractedTxAmount, $calculatedType] = $this->resolveAmountAndType($singleAmtStr, $fullDescription);
                }

                if ($extractedTxAmount !== null && $fullDescription !== '') {
                    $transactions[] = $this->buildTransaction($activeLedgerDate, $fullDescription, $extractedTxAmount, $calculatedType);
                }
            } else {
                // Line has no amount: accumulate as multi-line description chunk for the upcoming transaction
                if ($lineWithoutDate !== '') {
                    $accumulatedDesc[] = $lineWithoutDate;
                }
            }
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
                $this->standardizeDate($dateVal) ?? date('Y-m-d'),
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
        $colCount = max($counts) > 0 ? max($counts) : 3;
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

    public function parsePureFloat(string $str): float
    {
        $clean = preg_replace('/[£$€,\s]/u', '', trim($str)) ?? '0';
        if (preg_match('/^\((.*)\)$/', $clean, $m)) {
            $clean = '-' . $m[1];
        }
        $clean = preg_replace('/[^\d.-]/', '', $clean) ?? '0';
        return (float) $clean;
    }

    public function standardizeDateWithYear(string $raw, string $defaultYear): ?string
    {
        $clean = trim($raw);

        // If date has NO explicit 4-digit year (e.g. "04 DEC", "04 Dec", "04/12", "4 Dec")
        if (preg_match('/^\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]{3,9}$/i', $clean) || preg_match('/^\d{1,2}[\/\.\-]\d{1,2}$/', $clean)) {
            $withYear = $clean . ' ' . $defaultYear;
            $res = $this->standardizeDate($withYear);
            if ($res !== null) {
                return $res;
            }
        }

        // Direct parse if year is already explicitly present (e.g. "02 DEC 2025", "2026-01-06", "06/01/26")
        return $this->standardizeDate($clean);
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
            'previous balance', 'statement date', 'balance brought forward',
            'account summary', 'interest rate', 'total paid in', 'total paid out',
            'retstmt', 'national westminster bank plc', 'registered in england',
            'prudential regulation authority', 'financial conduct authority',
            'financial services firm reference', 'bishopsgate, london',
            'ear %', 'nar %', 'charging period', 'unarranged overdraft', 'date description',
            'unpaid transaction fees', 'unpaid transaction fee', 'adapt, student, graduate',
            'premier select, premier reward', 'nominal monthly rate', 'nominal annual rate',
            'here\'s a reminder about what you could be charged'
        ];
        foreach ($boilerplate as $b) {
            if (str_contains($lower, $b)) {
                return true;
            }
        }
        return false;
    }
}
