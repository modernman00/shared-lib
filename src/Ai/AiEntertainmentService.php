<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * AI Entertainment & Creative Social Dynamics Engine
 *
 * Implements high-engagement, emotionally resonant social AI features:
 * - AI Best Man & Birthday Speechwriter / Roast Master
 * - AI Household Dispute & Chore Arbitration Referee
 *
 * Employs fast, open-weights reasoning LLMs (e.g. Llama-3.2-3B-Instruct, Qwen-2.5-7B)
 * on Hugging Face serverless tier at £0 marginal cost.
 */
class AiEntertainmentService
{
    private HuggingFaceInferenceGateway $gateway;
    private ResilientAiChat $fallbackChat;

    public function __construct(
        ?HuggingFaceInferenceGateway $gateway = null,
        ?ResilientAiChat $fallbackChat = null
    ) {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
        $this->fallbackChat = $fallbackChat ?? new ResilientAiChat();
    }

    /**
     * Generate a personalized speech, birthday roast, or celebratory toast.
     *
     * @param string $honoureeName Person being celebrated (e.g. "Dave")
     * @param string $occasion Event occasion (e.g. "30th Birthday", "Wedding Best Man")
     * @param array<int, string> $anecdotes Key bullet points, funny memories, or habits
     * @param string $tone 'humorous_roast', 'heartfelt', 'peaky_blinders', or 'concise_card'
     * @param string $model
     * @return array{
     *     success: bool,
     *     title: string,
     *     speech_text: string,
     *     suggested_toast: string,
     *     delivery_tips: array<int, string>,
     *     error?: string
     * }
     */
    public function generateSpeech(
        string $honoureeName,
        string $occasion,
        array $anecdotes = [],
        string $tone = 'humorous_roast',
        string $model = 'meta-llama/Llama-3.2-3B-Instruct'
    ): array {
        $honouree = htmlspecialchars(trim($honoureeName), ENT_QUOTES, 'UTF-8');
        $occ = htmlspecialchars(trim($occasion), ENT_QUOTES, 'UTF-8');

        if ($honouree === '' || $occ === '') {
            return [
                'success' => false,
                'title' => '',
                'speech_text' => '',
                'suggested_toast' => '',
                'delivery_tips' => [],
                'error' => 'Honouree name and occasion are required.',
            ];
        }

        $cleanAnecdotes = [];
        foreach ($anecdotes as $point) {
            if (is_string($point) && trim($point) !== '') {
                $cleanAnecdotes[] = htmlspecialchars(trim($point), ENT_QUOTES, 'UTF-8');
            }
        }

        $toneInstructions = match ($tone) {
            'heartfelt' => 'Tone: Emotional, nostalgic, genuine, deeply appreciative with a touching celebration of friendship or family.',
            'peaky_blinders' => 'Tone: 1920s dramatic, charismatic, theatrical, commanding, with swagger and loyalty.',
            'concise_card' => 'Tone: Brief, punchy, memorable, perfect for a handwritten greeting card.',
            default => 'Tone: Witty, affectionate comedy roast. Tease playfully with good humor, stage directions [pause for laughter], and a warm, loving finish.',
        };

        $systemPrompt = "You are a master speechwriter and toastmaster. Write an extraordinary, unforgettable celebration speech. {$toneInstructions} You must output ONLY a valid JSON object matching this schema: {\"title\": \"Speech Title\", \"speech_text\": \"Full speech content with stage cues\", \"suggested_toast\": \"A final one-line toast for glasses up\", \"delivery_tips\": [\"Tip 1\", \"Tip 2\"]}";

        $userPrompt = "Honouree: {$honouree}\nOccasion: {$occ}\nKey Stories/Anecdotes:\n" . implode("\n- ", $cleanAnecdotes);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $content = null;
        $response = $this->gateway->chatCompletion($messages, $model, [
            'max_tokens' => 1024,
            'temperature' => 0.7,
        ]);

        if ($response !== null && !empty($response['choices'][0]['message']['content'])) {
            $content = (string)$response['choices'][0]['message']['content'];
        } else {
            // Gate 2 Fallback: Multi-tier chat fallback (DeepSeek -> OpenAI -> HF)
            $content = $this->dispatchFallbackChat($messages, [
                'max_tokens' => 1024,
                'temperature' => 0.7,
            ]);
        }

        if ($content === null || trim($content) === '') {
            return [
                'success' => false,
                'title' => '',
                'speech_text' => '',
                'suggested_toast' => '',
                'delivery_tips' => [],
                'error' => 'Speech generation service was unreachable or timed out.',
            ];
        }
        $parsed = $this->extractJson($content);

        if (!is_array($parsed)) {
            // Fallback: Return text directly if JSON wrapping failed
            return [
                'success' => true,
                'title' => "A Toast to {$honouree}",
                'speech_text' => $content,
                'suggested_toast' => "To {$honouree}!",
                'delivery_tips' => ['Speak slowly and smile.'],
            ];
        }

        $title = (string)($parsed['title'] ?? "A Toast to {$honouree}");
        $speechText = (string)($parsed['speech_text'] ?? '');
        $toast = (string)($parsed['suggested_toast'] ?? "To {$honouree}!");
        $tips = [];
        if (isset($parsed['delivery_tips']) && is_array($parsed['delivery_tips'])) {
            foreach ($parsed['delivery_tips'] as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $tips[] = trim($t);
                }
            }
        }

        return [
            'success' => true,
            'title' => $title,
            'speech_text' => $speechText,
            'suggested_toast' => $toast,
            'delivery_tips' => $tips,
        ];
    }

    /**
     * Arbitrate a household dispute or sibling argument with humorous, fair courtroom justice.
     *
     * @param string $partyAName Name of party A
     * @param string $partyAClaim Argument or grievance of party A
     * @param string $partyBName Name of party B
     * @param string $partyBClaim Counter-argument or grievance of party B
     * @param string $context Dispute domain (e.g. 'household_chores', 'weekend_plans', 'tv_remote')
     * @param string $model
     * @return array{
     *     success: bool,
     *     verdict: string,
     *     compromise_action_plan: array<string, string>,
     *     penalty_or_chore: string,
     *     closing_quip: string,
     *     error?: string
     * }
     */
    public function arbitrateDispute(
        string $partyAName,
        string $partyAClaim,
        string $partyBName,
        string $partyBClaim,
        string $context = 'household_chores',
        string $model = 'meta-llama/Llama-3.2-3B-Instruct'
    ): array {
        $nameA = htmlspecialchars(trim($partyAName), ENT_QUOTES, 'UTF-8');
        $claimA = htmlspecialchars(trim($partyAClaim), ENT_QUOTES, 'UTF-8');
        $nameB = htmlspecialchars(trim($partyBName), ENT_QUOTES, 'UTF-8');
        $claimB = htmlspecialchars(trim($partyBClaim), ENT_QUOTES, 'UTF-8');

        if ($nameA === '' || $nameB === '' || $claimA === '' || $claimB === '') {
            return [
                'success' => false,
                'verdict' => '',
                'compromise_action_plan' => [],
                'penalty_or_chore' => '',
                'closing_quip' => '',
                'error' => 'Both parties must provide names and claims for arbitration.',
            ];
        }

        $systemPrompt = "You are 'Judge Whiskers', a delightfully witty, impartial courtroom judge adjudicating family and domestic disputes. Weigh the evidence fairly with good humor. Output ONLY a valid JSON object with this exact schema: {\"verdict\": \"Court finding and ruling\", \"compromise_action_plan\": {\"{$nameA}\": \"Specific task to do\", \"{$nameB}\": \"Specific task to do\"}, \"penalty_or_chore\": \"Playful punishment or chore for the guilty party\", \"closing_quip\": \"A humorous judicial closing remark\"}";

        $userPrompt = "Dispute Domain: {$context}\nClaim from {$nameA}: \"{$claimA}\"\nClaim from {$nameB}: \"{$claimB}\"";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $content = null;
        $response = $this->gateway->chatCompletion($messages, $model, [
            'max_tokens' => 800,
            'temperature' => 0.6,
        ]);

        if ($response !== null && !empty($response['choices'][0]['message']['content'])) {
            $content = (string)$response['choices'][0]['message']['content'];
        } else {
            // Gate 2 Fallback: Multi-tier chat fallback (DeepSeek -> OpenAI -> HF)
            $content = $this->dispatchFallbackChat($messages, [
                'max_tokens' => 800,
                'temperature' => 0.6,
            ]);
        }

        if ($content === null || trim($content) === '') {
            return [
                'success' => false,
                'verdict' => '',
                'compromise_action_plan' => [],
                'penalty_or_chore' => '',
                'closing_quip' => '',
                'error' => 'Court arbitration server was unreachable or timed out.',
            ];
        }
        $parsed = $this->extractJson($content);

        if (!is_array($parsed)) {
            return [
                'success' => true,
                'verdict' => $content,
                'compromise_action_plan' => [
                    $nameA => 'Perform designated chore.',
                    $nameB => 'Assist with designated chore.',
                ],
                'penalty_or_chore' => 'High-five and move on.',
                'closing_quip' => 'Court is adjourned.',
            ];
        }

        $verdict = (string)($parsed['verdict'] ?? 'The court finds mutual responsibility.');
        $plan = [];
        if (isset($parsed['compromise_action_plan']) && is_array($parsed['compromise_action_plan'])) {
            foreach ($parsed['compromise_action_plan'] as $k => $v) {
                $plan[(string)$k] = (string)$v;
            }
        }
        $penalty = (string)($parsed['penalty_or_chore'] ?? 'Both parties share the next task.');
        $quip = (string)($parsed['closing_quip'] ?? 'Case dismissed.');

        return [
            'success' => true,
            'verdict' => $verdict,
            'compromise_action_plan' => $plan,
            'penalty_or_chore' => $penalty,
            'closing_quip' => $quip,
        ];
    }

    /**
     * Generate interactive Dynasty Trivia Clash quiz questions based on family lore, milestones, or cultural nostalgia.
     *
     * @param string $familyName e.g. "The Adeleke Family"
     * @param array<int, string> $familyFacts e.g. ["Grandpa was born in Lagos in 1945", "Uncle Tunde claims he invented Jollof rice"]
     * @param string $category e.g. 'family_folklore', 'childhood_memories', 'who_is_most_likely_to'
     * @param int $questionCount
     * @param string $model
     * @return array{
     *     success: bool,
     *     quiz_title: string,
     *     questions: array<int, array{question: string, options: array<int, string>, correct_index: int, fun_fact: string}>,
     *     error?: string
     * }
     */
    public function generateFamilyTrivia(
        string $familyName,
        array $familyFacts = [],
        string $category = 'family_folklore',
        int $questionCount = 3,
        string $model = 'meta-llama/Llama-3.2-3B-Instruct'
    ): array {
        $name = htmlspecialchars(trim($familyName), ENT_QUOTES, 'UTF-8');
        if ($name === '') {
            $name = 'Our Family';
        }

        $cleanFacts = [];
        foreach ($familyFacts as $fact) {
            if (is_string($fact) && trim($fact) !== '') {
                $cleanFacts[] = htmlspecialchars(trim($fact), ENT_QUOTES, 'UTF-8');
            }
        }

        $count = max(1, min(5, $questionCount));

        $categoryDescription = match ($category) {
            'childhood_memories' => 'Focus on hilarious childhood mishaps, school reports, and growing-up stories.',
            'who_is_most_likely_to' => 'Focus on playful debate questions about which family member would do what.',
            default => 'Focus on family heritage, milestones, traditions, and funny family lore.',
        };

        $systemPrompt = "You are the Game Show Host of 'Dynasty Trivia Clash', an energetic, affectionate family trivia show. Generate {$count} multiple-choice trivia questions based on the provided family details. {$categoryDescription} Each question MUST have exactly 4 choices (index 0, 1, 2, 3), one correct answer index, and a delightful fun fact explanation. You must output ONLY a valid JSON object matching this schema: {\"quiz_title\": \"Dynasty Trivia Title\", \"questions\": [{\"question\": \"Question text?\", \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"], \"correct_index\": 0, \"fun_fact\": \"Explanation\"}]}";

        $factsStr = !empty($cleanFacts) ? implode("\n- ", $cleanFacts) : 'General loving family traditions and milestones.';
        $userPrompt = "Family: {$name}\nCategory: {$category}\nKnown Lore & Milestones:\n- {$factsStr}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $content = null;
        $response = $this->gateway->chatCompletion($messages, $model, [
            'max_tokens' => 1024,
            'temperature' => 0.7,
        ]);

        if ($response !== null && !empty($response['choices'][0]['message']['content'])) {
            $content = (string)$response['choices'][0]['message']['content'];
        } else {
            $content = $this->dispatchFallbackChat($messages, [
                'max_tokens' => 1024,
                'temperature' => 0.7,
            ]);
        }

        if ($content === null || trim($content) === '') {
            return [
                'success' => false,
                'quiz_title' => '',
                'questions' => [],
                'error' => 'Trivia generator was unreachable or timed out.',
            ];
        }

        $parsed = $this->extractJson($content);
        if (!is_array($parsed) || empty($parsed['questions']) || !is_array($parsed['questions'])) {
            return [
                'success' => true,
                'quiz_title' => "{$name} Dynasty Trivia",
                'questions' => [
                    [
                        'question' => "Which year is celebrated as the grand reunion milestone for {$name}?",
                        'options' => ['2015', '2018', '2020', '2024'],
                        'correct_index' => 3,
                        'fun_fact' => 'Family milestones bring us together across generations!',
                    ],
                    [
                        'question' => "Who is officially crowned the Chief Recipe Master of {$name}?",
                        'options' => ['Grandma', 'Uncle Tunde', 'Auntie Simi', 'Mum'],
                        'correct_index' => 0,
                        'fun_fact' => 'Secret family seasonings can never be replicated!',
                    ],
                ],
            ];
        }

        $questions = [];
        foreach ($parsed['questions'] as $q) {
            if (!is_array($q)) {
                continue;
            }
            $qText = htmlspecialchars(trim((string)($q['question'] ?? 'Trivia Question')), ENT_QUOTES, 'UTF-8');
            $rawOptions = is_array($q['options'] ?? null) ? $q['options'] : ['Option A', 'Option B', 'Option C', 'Option D'];
            $options = [];
            foreach ($rawOptions as $opt) {
                $options[] = htmlspecialchars(trim((string)$opt), ENT_QUOTES, 'UTF-8');
            }
            while (count($options) < 4) {
                $options[] = 'Bonus Option';
            }
            $options = array_slice($options, 0, 4);

            $correctIndex = max(0, min(3, (int)($q['correct_index'] ?? 0)));
            $funFact = htmlspecialchars(trim((string)($q['fun_fact'] ?? 'Family trivia fact.')), ENT_QUOTES, 'UTF-8');

            $questions[] = [
                'question' => $qText,
                'options' => $options,
                'correct_index' => $correctIndex,
                'fun_fact' => $funFact,
            ];
        }

        return [
            'success' => true,
            'quiz_title' => htmlspecialchars(trim((string)($parsed['quiz_title'] ?? "{$name} Dynasty Trivia Clash")), ENT_QUOTES, 'UTF-8'),
            'questions' => $questions,
        ];
    }

    /**
     * Dispatch fallback chat with defensive compatibility for chat() and complete().
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string|null
     */
    private function dispatchFallbackChat(array $messages, array $options): ?string
    {
        if (method_exists($this->fallbackChat, 'chat')) {
            return $this->fallbackChat->chat($messages, $options);
        }

        $res = $this->fallbackChat->complete($messages, $options);
        return ($res['success'] && trim($res['content']) !== '') ? $res['content'] : null;
    }

    /**
     * Helper to extract clean JSON object from LLM response.
     *
     * @param string $raw
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $clean = trim($raw);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        } elseif (preg_match('/(\{.*\})/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }
}
