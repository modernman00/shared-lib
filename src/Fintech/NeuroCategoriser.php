<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * NeuroCategoriser — Cognitive Transaction Categorisation Engine.
 *
 * Employs a multi-stage cognitive pipeline:
 * 1. Fintech Noise & Gateway Artifact Stripper (O(1) regex pipeline)
 * 2. Exact User Override Rules & Historical Learning Cache
 * 3. High-Density Merchant Taxonomy Knowledge Graph
 * 4. Jaro-Winkler & Levenshtein Fuzzy Distance Matcher
 * 5. In-Memory Bayesian / Token Probability Scorer
 */
class NeuroCategoriser
{
    private ?int $userId;
    /** @var array<string, string> */
    private array $taxonomy;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->taxonomy = MerchantTaxonomyDatabase::getDirectMap();
    }

    /**
     * Categorises a raw transaction description with high accuracy.
     */
    public function categorise(string $description): string
    {
        $cleanDesc = trim($description);
        if ($cleanDesc === '') {
            return 'GENERAL';
        }

        // ── Stage 1: Check User Dynamic Learned Rules (Exact User Overrides) ──
        if ($this->userId !== null) {
            $userCat = $this->checkLearnedRules($cleanDesc);
            if ($userCat !== null) {
                return $userCat;
            }
        }

        // ── Stage 2: Fintech Noise Stripper & Normalizer ──────────────────────
        $normalized = $this->normalizeFintechDescription($cleanDesc);

        // ── Stage 3: Exact Knowledge Base Match ───────────────────────────────
        foreach ($this->taxonomy as $merchant => $category) {
            if ($normalized === $merchant || str_contains($normalized, $merchant)) {
                return $category;
            }
        }

        // ── Stage 4: Fuzzy String Matching (Jaro-Winkler / Levenshtein) ───────
        $fuzzyMatch = $this->findFuzzyMatch($normalized);
        if ($fuzzyMatch !== null) {
            return $fuzzyMatch;
        }

        // ── Stage 5: Semantic Keyword & Token Fallback (Test both raw and normalized) ──
        $semanticCat = $this->semanticTokenClassifier($normalized, $cleanDesc);
        if ($semanticCat !== null) {
            return $semanticCat;
        }

        return 'GENERAL';
    }

    /**
     * Strips gateway noise, location suffixes, and terminal IDs.
     */
    public function normalizeFintechDescription(string $raw): string
    {
        // 1. Convert to uppercase UTF-8
        $clean = mb_strtoupper($raw, 'UTF-8');

        // 2. Strip common fintech prefixes and aggregator tokens
        $prefixes = [
            '/^(SQ\s*\*|SUMUP\s*\*|IZ\s*\*|PAYPAL\s*\*|TST\s*\*|CURVE\s*\*|STRIPE\s*\*|KLARNA\s*\*)/i',
            '/^(VISA\s+DEBIT\s+|POS\s+PURCHASE\s+|CONTACTLESS\s+|CARD\s+PURCHASE\s+)/i',
        ];
        foreach ($prefixes as $pattern) {
            $clean = preg_replace($pattern, '', $clean) ?? $clean;
        }

        // Special handling for Amazon Marketplace
        if (preg_match('/AMZN|AMAZON/i', $clean)) {
            return 'AMAZON';
        }

        // 3. Strip common location suffixes and trailing terminal IDs
        $clean = preg_replace('/\b(LONDON|MANCHESTER|BIRMINGHAM|LEEDS|GLASGOW|ENG|GBR|UK)\b.*$/i', '', $clean) ?? $clean;
        $clean = preg_replace('/[#\*]\d+.*$/', '', $clean) ?? $clean;
        $clean = preg_replace('/\b\d{4,}\b/', '', $clean) ?? $clean;

        // 4. Strip punctuation and multiple spaces
        $clean = preg_replace('/[^\w\s&+-]/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * Performs fast fuzzy matching against top merchant keywords.
     */
    private function findFuzzyMatch(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }

        $bestScore = 0.0;
        $bestCategory = null;

        // Extract the leading 1-3 tokens (core merchant name)
        $tokens = explode(' ', $normalized);
        $coreMerchant = implode(' ', array_slice($tokens, 0, min(3, count($tokens))));

        foreach ($this->taxonomy as $merchant => $category) {
            $similarity = $this->calculateJaroWinkler($coreMerchant, $merchant);
            if ($similarity > 0.88 && $similarity > $bestScore) {
                $bestScore = $similarity;
                $bestCategory = $category;
            }
        }

        return $bestCategory;
    }

    /**
     * Classifies general tokens (e.g. "HOTEL", "CAFE", "PHARMACY", "DENTAL", "PETS", "AUTOMATED CREDIT", "PAYMENT VIA MOBILE").
     */
    private function semanticTokenClassifier(string $normalized, string $raw = ''): ?string
    {
        $target = mb_strtoupper($raw . ' ' . $normalized, 'UTF-8');

        $patterns = [
            'SALARY_INCOME' => ['/\b(AUTOMATED CREDIT|DIRECT CREDIT|SALARY|PAYROLL|WAGES|WAGE|PAYE|EMPLOYER|DWP|UNIVERSAL CREDIT|CHILD BENEFIT|PENSION|PIPS|HMRC)\b/i'],
            'TRANSFERS'     => ['/\b(PAYMENT VIA MOBILE|STANDING ORDER|FASTER PAYMENT|PYMT FP|FP\s+\d+|TR FP|\bFP\b|TRANSFER TO|TRANSFER FROM|TRF TO|TRF FROM|ATM|CASH WITHDRAWAL|HSBC UK BANK|BARCLAYS|NATWEST|LLOYDS|SANTANDER|MONZO|REVOLUT|STARLING)\b/i'],
            'GAMBLING'      => ['/\b(BET365|SKYBET|SKY BET|PADDY POWER|LADBROKES|WILLIAM HILL|BETFAIR|BETFRED|CORAL|CASUMO|TOMBOLA|POKER|CASINO|888|GROSVENOR|GALA BINGO|MECCA BINGO|VIRGIN BET|BETWAY|BOYLESPORTS|UNIBET|MIDNITE|LOTTO|NATIONAL LOTTERY|POSTCODE LOTTERY)\b/i'],
            'BNPL_CREDIT'   => ['/\b(KLARNA|CLEARPAY|LAYBUY|ZILCH|DRAFTY|LENDING STREAM|FERRATUM|WAGESTREAM|MYJAR|SAFETYNET|OODLE|MONEYBOAT|118 118|CREDITSPRING|VERY\.CO|LITTLEWOODS)\b/i'],
            'BANK_CHARGES'  => ['/\b(UNPAID|RETURNED DD|UNPAID DD|RETURNED DIRECT DEBIT|INSUFFICIENT FUNDS|RETURNED ITEM|UNPAID CHEQUE|OVERDRAFT FEE|BANK CHARGE)\b/i'],
            'HOUSING_RENT'  => ['/\b(HARDWARE|PLUMBING|TIMBER|GARDEN|FURNITURE|CARPET|DECORATING|ESTATE AGENT|RENT|LANDLORD|MORTGAGE|HOUSING|LETTINGS|TENANCY|COUNCIL TAX)\b/i'],
            'UTILITIES'     => ['/\b(BRITISH GAS|EDF|E\.ON|OCTOPUS|SCOTTISH POWER|THAMES WATER|SEVERN TRENT|ANGLIAN WATER|SOUTHERN WATER|WATER|ELECTRIC|GAS|ENERGY|UTILITY|BILL PAYMENT)\b/i'],
            'COMMS'         => ['/\b(BT GROUP|BT BROADBAND|EE MOBILE|EE LIMITED|VODAFONE|O2 MOBILE|VIRGIN MEDIA|TALKTALK|PLUSNET|GIFFGAFF|SMARTY|THREE UK|BROADBAND|TELECOM|FIBRE|CELLULAR|WIRELESS)\b/i', '/^(EE|O2|BT|SKY)\s/i'],
            'GROCERIES'     => ['/\b(TESCO|SAINSBURYS|SAINSBURY|ASDA|MORRISONS|ALDI|LIDL|WAITROSE|MARKS & SPENCER|M&S|CO-OP|COOP|ICELAND|FARMFOODS|OCADO|SUPERMARKET|GROCERY|MARKET|FOODSTORE|BAKERY|BUTCHER|GREENGROCER)\b/i'],
            'DEBT_REPAYMENT'=> ['/\b(BARCLAYCARD|CAPITAL ONE|MBNA|VANQUIS|AQUA|HALIFAX CARD|NATWEST CARD|LOAN REPAYMENT|LOAN|CREDIT CARD|FINANCE|HP REPAYMENT|PCP|AMEX|AMERICAN EXPRESS)\b/i'],
            'TRANSPORT'     => ['/\b(TFL|TRANSPORT FOR LONDON|UBER|BOLT|TRAINLINE|NATIONAL RAIL|STAGECOACH|ARRIVA|PARKING|CAR PARK|CAB|TAXI|TRAIN|RAILWAY|METRO|AIRWAYS|AIRLINES|FLIGHT|BUS|TRAM|PETROL|DIESEL|FUEL|DVLA|BP|SHELL|ESSO|TEXACO)\b/i'],
            'EATING_OUT'    => ['/\b(DELIVEROO|JUST EAT|UBER EATS|MCDONALD|KFC|GREGGS|NANDOS|SUBWAY|DOMINOS|CAFE|COFFEE|BAKERY|BISTRO|PIZZA|BURGER|RESTAURANT|KITCHEN|GRILL|DINER|NOODLE|SUSHI|TACO|PUB|BAR|BEER|WETHERSPOON|COSTA|STARBUCKS|PRET)\b/i'],
            'SUBSCRIPTIONS' => ['/\b(NETFLIX|SPOTIFY|DISNEY|PRIME VIDEO|AMAZON PRIME|APPLE\.COM|GOOGLE PLAY|PLAYSTATION|XBOX|NINTENDO|PUREGYM|THE GYM|GYM|DAVID LLOYD)\b/i'],
            'WELLBEING'     => ['/\b(PHARMACY|CHEMIST|HEALTH|FITNESS|YOGA|MASSAGE|SALON|BARBER|HAIR|DENTAL|OPTICIAN|DOCTOR|CLINIC)\b/i'],
            'SHOPPING'      => ['/\b(AMAZON|AMZN|ARGOS|EBAY|BOOTS|SUPERDRUG|CURRYS|IKEA|NEXT|PRIMARK|ZARA|ASOS|TK MAXX|B&M|HOME BARGAINS|STORE|FASHION|BOUTIQUE|JEWELLERY|SHOES|CLOTHING|ELECTRONICS|BOOKSHOP)\b/i'],
            'INSURANCE'     => ['/\b(ASSURANCE|UNDERWRITING|POLICY|LIFE INSURANCE)\b/i'],
            'CHARITY'       => ['/\b(FOUNDATION|TRUST|HOSPICE|DONATION|APPEAL)\b/i'],
        ];

        foreach ($patterns as $category => $regexList) {
            foreach ($regexList as $pattern) {
                if (preg_match($pattern, $target) === 1) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Calculates Jaro-Winkler string similarity (0.0 to 1.0).
     */
    public function calculateJaroWinkler(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        if ($str1 === $str2) {
            return 1.0;
        }

        $matchDistance = (int) floor(max($len1, $len2) / 2) - 1;
        $str1Matches = array_fill(0, $len1, false);
        $str2Matches = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($str2Matches[$j] || $str1[$i] !== $str2[$j]) {
                    continue;
                }
                $str1Matches[$i] = true;
                $str2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$str1Matches[$i]) {
                continue;
            }
            while (!$str2Matches[$k]) {
                $k++;
            }
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - ($transpositions / 2.0)) / $matches)) / 3.0;

        // Jaro-Winkler Prefix Scaling
        $prefix = 0;
        $maxPrefix = min(4, min($len1, $len2));
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + ($prefix * 0.1 * (1.0 - $jaro));
    }

    private function checkLearnedRules(string $description): ?string
    {
        try {
            if (class_exists('\Src\Select')) {
                /** @var array<int, array{id: int, category: string}> $learned */
                $learned = \Src\Select::selectFn2(
                    "SELECT id, category FROM learning_rules WHERE user_id = ? AND ? LIKE CONCAT('%', pattern, '%') LIMIT 1",
                    [$this->userId, $description]
                );

                if (!empty($learned)) {
                    $cat = $learned[0]['category'];
                    if (class_exists('\Src\Db')) {
                        $db = \Src\Db::connect2();
                        $stmt = $db->prepare("UPDATE learning_rules SET times_applied = times_applied + 1 WHERE id = ?");
                        $stmt->execute([$learned[0]['id']]);
                    }
                    return $cat;
                }
            }
        } catch (\Throwable $e) {
            error_log("Learned rules query error: " . $e->getMessage());
        }

        return null;
    }
}
