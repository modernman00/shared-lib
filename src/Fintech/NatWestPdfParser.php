<?php

declare(strict_types=1);

namespace Src\Fintech;

use Smalot\PdfParser\Parser;

/**
 * NatWest UK PDF statement parser (shared across all portfolio apps).
 *
 * High-precision deterministic parser with running-balance reconciliation,
 * year roll-over handling, merchant extraction and ATM/cash preservation.
 * Returns null from parse() for non-NatWest PDFs so callers can fall back to
 * UniversalStatementParser.
 */
final class NatWestPdfParser
{
    /**
     * Diagnostics-shaped result (same keys as UniversalStatementParser::parsePdfWithDiagnostics).
     * Returns null when the PDF is not a NatWest statement.
     *
     * @return array<string, mixed>|null
     */
    public function parseWithDiagnostics(string $filePath): ?array
    {
        $result = $this->parse($filePath);
        if ($result === null) {
            return null;
        }
        $txs = $result['transactions'];
        return [
            'success' => count($txs) > 0,
            'bank' => 'NatWest',
            'is_scanned' => false,
            'transactions' => $txs,
            'total_count' => count($txs),
            'total_inflow' => $result['total_inflow'],
            'total_outflow' => $result['total_outflow'],
            'net_cashflow' => round($result['total_inflow'] - $result['total_outflow'], 2),
            'reconciled' => $result['reconciled'],
            'error' => count($txs) === 0 ? 'No transactions could be extracted from this NatWest statement.' : null,
        ];
    }

    /**
     * High-precision deterministic parser for NatWest UK PDF statements.
     * Returns null if the PDF is not a NatWest statement so universalEngine handles it.
     *
     * @return array{
     *   transactions: array<int, array{date: string, description: string, merchant: string, amount: float, category: string, type: string, bank_source: string, balance: float}>,
     *   total_inflow: float,
     *   total_outflow: float,
     *   reconciled: bool
     * }|null
     */
    public function parse(string $filePath): ?array
    {
        if (!class_exists(Parser::class) || !is_file($filePath) || filesize($filePath) > 15 * 1024 * 1024) {
            return null;
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = array_slice($pdf->getPages(), 0, 60);
            if (empty($pages)) {
                return null;
            }

            $firstPageText = $pages[0]->getText();
            $isNatWest = (stripos($firstPageText, 'NatWest') !== false || stripos($firstPageText, 'National Westminster Bank') !== false)
                && (bool) preg_match('/Date\s+Description\s+Paid\s+In/i', $firstPageText);
            if (!$isNatWest) {
                return null;
            }

            // Extract statement period & summary totals for mathematical reconciliation
            $startYear = (int) date('Y');
            $endYear = $startYear;
            if (preg_match('/Period\s+Covered\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})\s+to\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})/i', $firstPageText, $pm)) {
                $startYear = (int) $pm[1];
                $endYear = (int) $pm[2];
            } elseif (preg_match('/Statement\s+Date\s+\d{1,2}\s+[A-Za-z]{3,9}\s+(\d{4})/i', $firstPageText, $sm)) {
                $endYear = (int) $sm[1];
                $startYear = $endYear;
            }

            $summaryPrevBal = null;
            $summaryPaidIn = null;
            $summaryWithdrawn = null;
            $summaryNewBal = null;
            if (preg_match('/Previous\s+Balance\s+£?([\d,]+\.\d{2})/i', $firstPageText, $m)) {
                $summaryPrevBal = (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/Paid\s+In\s+£?([\d,]+\.\d{2})/i', $firstPageText, $m)) {
                $summaryPaidIn = (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/Withdrawn\s+£?([\d,]+\.\d{2})/i', $firstPageText, $m)) {
                $summaryWithdrawn = (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/New\s+Balance\s+£?([\d,]+\.\d{2})/i', $firstPageText, $m)) {
                $summaryNewBal = (float) str_replace(',', '', $m[1]);
            }

            $monthMap = [
                'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
                'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
            ];

            $currentYear = $startYear;
            $lastMonth = null;
            $activeLedgerDate = null;
            $runningBalance = $summaryPrevBal;
            $accumulatedDesc = [];
            $transactions = [];
            $neuroCategoriser = new NeuroCategoriser();

            foreach ($pages as $page) {
                $text = $page->getText();
                if (!preg_match('/Date\s+Description\s+Paid\s+In|BROUGHT\s+FORWARD/i', $text)) {
                    continue;
                }

                $lines = explode("\n", $text);
                $inTable = false;

                foreach ($lines as $rawLine) {
                    $trimmed = trim(preg_replace('/[\x00-\x1F\x7F\x{00A0}]+/u', ' ', $rawLine) ?? '');
                    $trimmed = trim(preg_replace('/\s+/', ' ', $trimmed) ?? '');
                    if ($trimmed === '') {
                        continue;
                    }

                    if (preg_match('/^Date\s+Description\s+Paid\s+In/i', $trimmed)) {
                        $inTable = true;
                        $accumulatedDesc = [];
                        continue;
                    }

                    if (!$inTable) {
                        if (preg_match('/BROUGHT\s+FORWARD/i', $trimmed)) {
                            $inTable = true;
                        } else {
                            continue;
                        }
                    }

                    // Stop at page footer or post-table interest/overdraft disclosures
                    if (preg_match('/^(RETSTMT\s*-|National\s+Westminster\s+Bank\s+Plc|CARRIED\s+FORWARD|Interest\s*\(variable\)|Overdraft\s+Arrangements)/i', $trimmed)) {
                        $inTable = false;
                        $accumulatedDesc = [];
                        continue;
                    }

                    // Handle BROUGHT FORWARD line
                    if (preg_match('/^(?:(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})\s+)?BROUGHT\s+FORWARD\s+£?([\d,]+\.\d{2})/iu', $trimmed, $bfm)) {
                        $accumulatedDesc = [];
                        if (!empty($bfm[1]) && !empty($bfm[2]) && !empty($bfm[3])) {
                            $mKey = strtoupper($bfm[2]);
                            if (isset($monthMap[$mKey])) {
                                $currentYear = (int) $bfm[3];
                                $lastMonth = $monthMap[$mKey];
                                $activeLedgerDate = sprintf('%04d-%02d-%02d', $currentYear, $lastMonth, (int) $bfm[1]);
                            }
                        }
                        $runningBalance = (float) str_replace(',', '', $bfm[4]);
                        continue;
                    }

                    // Strict NatWest posting date: ONLY "DD MMM" at the start of a line (never DD/MM/YY from FP refs!)
                    $lineBody = $trimmed;
                    if (preg_match('/^(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\b\s*(.*)$/i', $trimmed, $dm)) {
                        $day = (int) $dm[1];
                        $mNum = $monthMap[strtoupper($dm[2])];
                        if ($lastMonth !== null && $mNum < $lastMonth && $mNum <= 6 && $lastMonth >= 7 && $currentYear < $endYear) {
                            $currentYear++;
                        }
                        $lastMonth = $mNum;
                        $activeLedgerDate = sprintf('%04d-%02d-%02d', $currentYear, $mNum, $day);
                        $lineBody = trim($dm[3]);
                    }

                    // NatWest transaction rows always end with: <txAmount> <rowBalance>
                    if (preg_match('/^(.*?)\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})$/', $lineBody, $am) && $activeLedgerDate !== null) {
                        $descTail = trim($am[1]);
                        if ($descTail !== '') {
                            $accumulatedDesc[] = $descTail;
                        }
                        $fullDescription = trim(preg_replace('/\s+/', ' ', implode(' ', $accumulatedDesc)) ?? '');
                        $accumulatedDesc = [];

                        $txAbs = (float) str_replace(',', '', $am[2]);
                        $rowBalance = (float) str_replace(',', '', $am[3]);

                        $delta = $runningBalance !== null ? round($rowBalance - $runningBalance, 2) : -$txAbs;
                        if ($delta > 0.005) {
                            $type = 'income';
                            $signedAmount = $txAbs;
                        } else {
                            $type = 'expense';
                            $signedAmount = -$txAbs;
                        }
                        $runningBalance = $rowBalance;

                        $merchant = $this->extractMerchant($fullDescription);
                        $category = $this->categorise($fullDescription, $neuroCategoriser);

                        $transactions[] = [
                            'date' => $activeLedgerDate,
                            'description' => $fullDescription,
                            'merchant' => $merchant,
                            'amount' => $signedAmount,
                            'category' => $category,
                            'type' => $type,
                            'bank_source' => 'NatWest',
                            'balance' => $rowBalance,
                        ];
                    } else {
                        if ($lineBody !== '' && $activeLedgerDate !== null) {
                            $accumulatedDesc[] = $lineBody;
                        }
                    }
                }
            }

            $totalInflow = 0.0;
            $totalOutflow = 0.0;
            foreach ($transactions as $t) {
                if ($t['amount'] > 0) {
                    $totalInflow += $t['amount'];
                } else {
                    $totalOutflow += abs($t['amount']);
                }
            }
            $totalInflow = round($totalInflow, 2);
            $totalOutflow = round($totalOutflow, 2);

            $reconciled = count($transactions) > 0;
            if ($summaryPaidIn !== null && abs($totalInflow - $summaryPaidIn) > 0.01) {
                $reconciled = false;
            }
            if ($summaryWithdrawn !== null && abs($totalOutflow - $summaryWithdrawn) > 0.01) {
                $reconciled = false;
            }
            if ($summaryNewBal !== null && $runningBalance !== null && abs($runningBalance - $summaryNewBal) > 0.01) {
                $reconciled = false;
            }

            return [
                'transactions' => $transactions,
                'total_inflow' => $totalInflow,
                'total_outflow' => $totalOutflow,
                'reconciled' => $reconciled,
            ];
        } catch (\Throwable $e) {
            error_log('NatWest PDF parse error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract a clean human-readable merchant/payee name from a NatWest transaction description.
     */
    public function extractMerchant(string $description): string
    {
        $desc = trim($description);

        // 1. Card Transaction <4digits> <DDMMMYY> [C|CD|D]? <MERCHANT> [<TOWN> GB]
        if (preg_match('/^Card\s+Transaction\s+\d{4}\s+\d{2}[A-Z]{3}\d{2}\s+(?:(?:CD|C|D)\s+)?(.+)$/i', $desc, $m)) {
            $body = trim($m[1]);
            $body = preg_replace('/\s+GB$/i', '', $body) ?? $body;
            $body = preg_replace('/^(?:PPOINT_\*|SUMUP\s+\*+|NYX\*|UFS\*)/i', '', $body) ?? $body;
            $body = trim($body);

            $brandMap = [
                '/^TESCO\b/i' => 'Tesco',
                '/^LIDL\b/i' => 'Lidl',
                '/^ALDI\b/i' => 'Aldi',
                '/^ASDA\b/i' => 'Asda',
                '/^WM\s+MORRISONS\b|^MORR\s+EBBW/i' => 'Morrisons',
                '/^SAINSBURY/i' => 'Sainsbury\'s',
                '/^DELIVEROO\b/i' => 'Deliveroo',
                '/^MCDONALDS\b/i' => 'McDonald\'s',
                '/^EBBW\s+VALE\s+FUEL\s+EXPRESS\b/i' => 'Ebbw Vale Fuel Express',
                '/^TREDEGAR\s+FUEL\s+EXPRESS\b/i' => 'Tredegar Fuel Express',
                '/^SHELL\b/i' => 'Shell',
                '/^SPAR\b/i' => 'Spar',
                '/^B&M\b/i' => 'B&M',
                '/^HOME\s+BARGAINS\b/i' => 'Home Bargains',
                '/^TKMAXX/i' => 'TK Maxx',
                '/^JD\s+SPORTS/i' => 'JD Sports',
                '/^HALFORDS\b/i' => 'Halfords',
                '/^VINTED\b/i' => 'Vinted',
                '/^CYMRAEG\s*VENDING/i' => 'Cymraeg Vending',
                '/^NOVUNA\s+PERSONAL\s+FINANC/i' => 'Novuna Personal Finance',
                '/^MONEYBOAT/i' => 'Moneyboat',
                '/^FINIOLOANS\b/i' => 'Finio Loans',
                '/^MR\s+LENDER\b/i' => 'Mr Lender',
                '/^PRA\s+GROUP\b/i' => 'PRA Group',
                '/^WWW\.ZOPA\.COM\b/i' => 'Zopa',
                '/^GOOGLE\s+PLAY\b/i' => 'Google Play',
                '/^GETFISHINGLICENCE\b/i' => 'Get Fishing Licence',
                '/^ARMOURY\s+STORES\b/i' => 'Armoury Stores',
                '/^MAGICMIST\b/i' => 'Magicmist',
                '/^TYRE\s+PLANET\b/i' => 'Tyre Planet',
                '/^G\s+S\s+SUPERMAR/i' => 'G S Supermarket',
                '/^MARMARIS/i' => 'Marmaris Kebab',
                '/^CAFFI\s+CWTSH\b/i' => 'Caffi Cwtsh',
                '/^O\'?NEILLS\b/i' => 'O\'Neills',
                '/^BREWHOUSE\b/i' => 'Brewhouse',
                '/^PRINCE\s+OF\s+WALES\b/i' => 'Prince of Wales',
                '/^EBBW\s+VALE\s+FISH\s+BAR\b/i' => 'Ebbw Vale Fish Bar',
                '/^MEL\s+MORGAN\s+SPORTS\b/i' => 'Mel Morgan Sports',
                '/^MOBILE\s+EXTRA\b/i' => 'Mobile Extra',
                '/^FESTIVAL\s+ANGLING/i' => 'Festival Angling',
                '/^TREDEGAR\s+ANGLING/i' => 'Tredegar Angling Centre',
            ];
            foreach ($brandMap as $pattern => $label) {
                if (preg_match($pattern, $body)) {
                    return $label;
                }
            }
            return ucwords(strtolower($body));
        }

        // 2. Direct Debit <MERCHANT>
        if (preg_match('/^Direct\s+Debit\s+(.+)$/i', $desc, $m)) {
            $body = trim($m[1]);
            if (stripos($body, 'DVLA') === 0) {
                return 'DVLA';
            }
            if (stripos($body, 'SPECIALIST MOTOR') === 0) {
                return 'Specialist Motor Finance';
            }
            if (stripos($body, 'NATWEST INSURANCE') === 0) {
                return 'NatWest Insurance';
            }
            if (stripos($body, 'NWI HOME INSURANCE') === 0) {
                return 'NWI Home Insurance';
            }
            if (stripos($body, 'CLOSE-DARWIN') === 0) {
                return 'Close-Darwin';
            }
            return ucwords(strtolower($body));
        }

        // 2b. Standing Order <RECIPIENT>
        if (preg_match('/^Standing\s+Order\s+(.+)$/i', $desc, $m)) {
            $body = trim(preg_replace('/\s+(?:TR\s+)?FP\s+\d{2}\/\d{2}\/\d{2}.*$/i', '', $m[1]) ?? $m[1]);
            if (stripos($body, 'SHANE BLACKWELL') === 0) {
                return 'Shane Blackwell';
            }
            return ucwords(strtolower($body));
        }

        // 3. Cash Withdrawal <MERCHANT> <DDMMM> [CHARGE ...]
        if (preg_match('/^Cash\s+Withdrawal\s+(.+?)\s+\d{2}[A-Z]{3}\b/i', $desc, $m)) {
            $atm = strtoupper(trim($m[1]));
            return match ($atm) {
                'NOTEMACHINE' => 'NoteMachine ATM',
                'POST OFFICE' => 'Post Office ATM',
                'ROYAL BANK' => 'Royal Bank ATM',
                'BMACH' => 'BMach ATM',
                default => ucwords(strtolower($atm)) . ' ATM',
            };
        }

        // 4. Automated Credit <PAYER> ...
        if (preg_match('/^Automated\s+Credit\s+(.+)$/i', $desc, $m)) {
            $body = trim(preg_replace('/\s+FP\s+\d{2}\/\d{2}\/\d{2}\s+\d{4}\s+[A-Z0-9]+$/i', '', $m[1]) ?? $m[1]);
            if (stripos($body, 'EUROCAPS') !== false) {
                return 'Eurocaps Limited';
            }
            if (stripos($body, 'SHANES BUYING') !== false) {
                return 'Shanes Buying';
            }
            if (stripos($body, 'EVERGREEN FINANCE') !== false || stripos($body, 'MONEYBOAT') !== false) {
                return 'Evergreen Finance (Moneyboat)';
            }
            if (stripos($body, 'AMY SUTER') !== false) {
                return 'Amy Suter';
            }
            if (stripos($body, 'C BLACKWELL') !== false) {
                return 'C Blackwell';
            }
            return ucwords(strtolower($body));
        }

        // 5. OnLine Transaction
        if (preg_match('/^OnLine\s+Transaction\s+(?:From|To)\s+A\/C\s+\d+\s+(.+?)(?:\s+Via\s+Mobile\s+Xfer)?$/i', $desc, $m)) {
            $name = preg_replace('/\bSW\b/i', 'SW', ucwords(strtolower(trim($m[1])))) ?? trim($m[1]);
            return $name . ' (Mobile Transfer)';
        }
        if (preg_match('/^OnLine\s+Transaction\s+(.+)$/i', $desc, $m)) {
            $body = trim($m[1]);
            if (stripos($body, 'CAPITAL ONE') === 0) {
                return 'Capital One';
            }
            if (stripos($body, 'ZOPA') === 0) {
                return 'Zopa';
            }
            if (stripos($body, 'SHANE BLACKWELL') === 0) {
                return 'Shane Blackwell';
            }
            if (stripos($body, 'AMY JONES') === 0) {
                return 'Amy Jones';
            }
            if (stripos($body, 'AMY ') === 0) {
                return 'Amy';
            }
            if (stripos($body, 'DEMI BLACKWELL') === 0) {
                return 'Demi Blackwell';
            }
            $body = preg_replace('/\s+(?:VIA\s+MOBILE|TPP\s+HSBC).*$/i', '', $body) ?? $body;
            return ucwords(strtolower(trim($body)));
        }

        return $desc;
    }

    /**
     * Categorise NatWest transaction with domain-accurate overrides on top of NeuroCategoriser.
     */
    public function categorise(string $description, NeuroCategoriser $neuro): string
    {
        if (preg_match('/\b(NWI HOME INSURANCE|NATWEST INSURANCE|INSURANCE)\b/i', $description)) {
            return 'INSURANCE';
        }
        if (stripos($description, 'Cash Withdrawal') === 0 || preg_match('/\b(ATM|CASH\s+MACHINE|NOTEMACHINE|LINK\s+ATM)\b/i', $description)) {
            return 'CASH';
        }
        return $neuro->categorise($description);
    }
}
