<?php

declare(strict_types=1);

namespace Src\WorldEngine;

/**
 * StudioBlueprints — per-app brand kits, explainer storyboards and poster samples
 * for the ATG World Engine studio.
 *
 * Storyboards follow Hook → Problem → Solution → Proof → Call to Action, and every
 * scene can carry tone alternates (funny / dramatic / short / pro) that the in-studio
 * Director swaps in when an admin says "make it funnier", "more dramatic" and so on.
 *
 * Narration performance tags understood by the voice engine:
 *   *word*        emphasis (pitch lift + slower delivery)
 *   … / [pause]   a comic beat
 *   [sfx:name]    fire a sound effect mid-sentence (boing, sadTrombone, rimshot, kaching…)
 *   [voice:id]    switch speaker mid-line (e.g. [voice:mascot] for a sidekick interruption)
 *
 * Copy rules: British English; no guaranteed outcomes for regulated products
 * (lending, tax, care inspections); ExecMind described without AI wording.
 */
final class StudioBlueprints
{
    /** @var array<string, string> */
    private const ALIASES = [
        'party' => 'partyplatform', 'partyplatform' => 'partyplatform',
        'family' => 'familyplatform', 'familyplatform' => 'familyplatform',
        'idecide' => 'idecide', 'idecideapp' => 'idecide',
        'iaccount' => 'iaccountapp', 'iaccountapp' => 'iaccountapp',
        'tenantscore' => 'tenantscore', 'scoretenant' => 'tenantscore',
        'execmind' => 'execmindapp', 'execmindapp' => 'execmindapp',
        'loaneasy' => 'loaneasyfinance', 'loaneasyfinance' => 'loaneasyfinance',
        'bcp' => 'bcp', 'bestcareprofessionals' => 'bcp',
    ];

    public static function normaliseAppKey(string $raw): string
    {
        $k = strtolower(trim($raw));
        return self::ALIASES[$k] ?? 'partyplatform';
    }

    /** @return list<string> */
    public static function appKeys(): array
    {
        return array_values(array_unique(self::ALIASES));
    }

    /**
     * Full studio configuration for one app.
     *
     * @return array<string, mixed>
     */
    public static function forApp(string $appKey): array
    {
        $key = self::normaliseAppKey($appKey);
        $all = self::catalogue();
        $cfg = $all[$key];
        $cfg['app'] = $key;
        return $cfg;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function scene(string $id, string $stage, string $shot, string $headline, string $narration, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'stage' => $stage,
            'shot' => $shot,
            'duration' => 6.0,
            'headline' => $headline,
            'sub' => '',
            'narration' => $narration,
            'props' => [],
            'mood' => 'happy',
            'bullets' => [],
        ], $extra);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function catalogue(): array
    {
        return [
            'partyplatform' => [
                'brand' => ['name' => 'PartyPlatform', 'url' => 'partyplatform.com', 'a' => '#ff3d7f', 'b' => '#7b5cff', 'c' => '#ffd23f', 'd' => '#2b2140'],
                'character' => ['skin' => 'umber', 'hair' => 'black', 'hairStyle' => 'afro', 'top' => '#ff3d7f', 'bottom' => '#2b2140', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'Party planning, minus the panic',
                    'style' => 'cartoon', 'aspect' => '16:9',
                    'voice' => ['preset' => 'bestie', 'enabled' => true, 'tune' => ['pitch' => 1, 'rate' => 1, 'energy' => 1.1]],
                    'music' => ['genre' => 'bouncy', 'volume' => 0.5], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('pp1', 'hook', 'hook', 'Planning a party in *47 group chats*?', 'Quick question. [pause] Are you planning a party across *forty-seven* group chats? [sfx:boing]', ['props' => ['chat', 'cake', 'balloon'], 'mood' => 'shocked', 'pose' => 'shrug', 'duration' => 5,
                            'alt' => ['funny' => 'Quick question. Are you planning a party across *forty-seven* group chats… [pause] and a voice note from Auntie that is *nine minutes long*? [sfx:sadTrombone]', 'dramatic' => 'In a world… of unread messages… one host… stood *alone*.', 'short' => 'Planning a party across *forty-seven* group chats?']]),
                        self::scene('pp2', 'problem', 'chaos', 'Who’s coming? Who’s *allergic to nuts*?', 'Who’s coming? Who’s bringing a plus-one? And who, crucially, is *allergic to nuts*? [sfx:alert] Nobody knows. Not even you.', ['props' => ['chat', 'bell', 'doc', 'clock', 'cake'], 'mood' => 'stressed', 'sub' => 'Spreadsheets. Screenshots. Silence.',
                            'alt' => ['funny' => 'Who’s coming? Who’s bringing a plus-one? Who’s bringing a plus-*three*? [sfx:boing] And why has Kevin replied “maybe” to a *birthday*? [sfx:sadTrombone]', 'dramatic' => 'The RSVPs went unanswered. The allergies… went *unrecorded*. [pause] The cake… was a *risk*.']]),
                        self::scene('pp3', 'problem', 'stat', 'The average host sends', 'The average host sends *dozens* of chasing messages before a single balloon is inflated. That’s not a party. That’s a part-time job.', ['props' => ['chat'], 'mood' => 'shocked', 'stat' => ['value' => '48', 'suffix' => ' messages', 'label' => 'before anyone even says yes'],
                            'alt' => ['funny' => 'The average host sends *dozens* of chasing messages before a single balloon is inflated. [sfx:rimshot] At this point your phone wants a pay rise.']]),
                        self::scene('pp4', 'solution', 'reveal', 'One link. *Every answer.*', 'Enter PartyPlatform. [sfx:sparkle] One beautiful invite, one link, and every RSVP, dietary need and plus-one lands in *one* tidy place.', ['props' => ['phone'], 'mood' => 'excited', 'bullets' => ['RSVPs roll in live', 'Allergies flagged', 'Plus-ones sorted', 'Reminders sent for you'], 'sub' => 'Your party, finally organised.',
                            'alt' => ['funny' => 'Enter PartyPlatform. [sfx:sparkle] One invite, one link, and every RSVP lands in one place. [voice:mascot] Even Kevin’s! [voice:bestie] *Especially* Kevin’s.']]),
                        self::scene('pp5', 'solution', 'split', 'Before vs. *after*', 'Before: chaos, crumbs and crying in the car park. After: a guest list that actually adds up. [sfx:ding]', ['props' => ['chat', 'check'], 'bullets' => ['Before', 'After']]),
                        self::scene('pp6', 'proof', 'proof', 'Hosts are *obsessed*', 'Hosts are telling us the same thing, again and again.', ['quote' => 'Best party I’ve ever hosted — and I didn’t cry once.', 'author' => 'A very relieved host, Croydon', 'mood' => 'proud', 'pose' => 'thumbsUp', 'duration' => 5]),
                        self::scene('pp7', 'cta', 'cta', 'Your next party starts here', 'So go on. Create your invite in two minutes, for free. [sfx:tada] Your group chats will thank you.', ['cta' => 'Create your free invite', 'url' => 'partyplatform.com', 'qr' => true, 'mood' => 'excited',
                            'alt' => ['funny' => 'So go on. Create your invite in two minutes, for free. [pause] Your group chats will thank you. [voice:mascot] Kevin won’t. [sfx:rimshot]']]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Birthday bash', 'template' => 'groovy', 'kicker' => 'Esther & Cynthia present', 'headline' => 'Aaron’s *30th* Birthday Bash', 'sub' => 'Afrobeats, amapiano and far too much jollof', 'date' => 'Sat 7 March', 'time' => '9pm till late', 'venue' => 'Tape London, W1', 'details' => ['Live DJ', 'Dress: all black'], 'cta' => 'RSVP now', 'url' => 'partyplatform.com/e/aaron30', 'badge' => 'Free entry', 'props' => ['balloons', 'gift', 'music']],
                    ['label' => 'Club night', 'template' => 'neon', 'kicker' => 'Fridays at', 'headline' => 'Midnight *Glow*', 'sub' => 'Three rooms. One very good night.', 'date' => 'Every Friday', 'time' => '10pm–4am', 'venue' => 'The Vaults, Shoreditch', 'details' => ['Guest DJs', '£10 before 11'], 'url' => 'partyplatform.com/e/glow', 'props' => ['music', 'star']],
                    ['label' => 'Summer day party', 'template' => 'sticker', 'kicker' => 'Bank holiday special', 'headline' => 'Pool Party *Sunday*', 'sub' => 'Sun, sounds and suncream (bring your own)', 'date' => 'Sun 25 May', 'time' => '1pm–8pm', 'venue' => 'Lido Gardens, Brixton', 'url' => 'partyplatform.com/e/pool', 'props' => ['balloon', 'star', 'music', 'gift']],
                ],
            ],
            'familyplatform' => [
                'brand' => ['name' => 'FamilyPlatform', 'url' => 'familyplatform.com', 'a' => '#f97316', 'b' => '#16a34a', 'c' => '#facc15', 'd' => '#3b2a1a'],
                'character' => ['skin' => 'caramel', 'hair' => 'brown', 'hairStyle' => 'bun', 'top' => '#16a34a', 'bottom' => '#3b2a1a', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'Bring the whole family back together',
                    'style' => 'whiteboard', 'aspect' => '16:9',
                    'voice' => ['preset' => 'storyteller', 'enabled' => true], 'music' => ['genre' => 'lofi', 'volume' => 0.45], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('fp1', 'hook', 'talk', 'Who has Grandma’s *jollof recipe*?', 'Be honest. [pause] Does *anyone* in your family actually have Grandma’s recipe written down?', ['props' => ['tree', 'heart'], 'mood' => 'thinking', 'pose' => 'think', 'duration' => 5,
                            'alt' => ['funny' => 'Be honest. Does *anyone* have Grandma’s recipe written down? [pause] Or is it just “a bit of this, a bit of that, and *vibes*”? [sfx:boing]']]),
                        self::scene('fp2', 'problem', 'chaos', 'Photos here. Birthdays there. *Stories nowhere.*', 'Photos are stuck on three old phones. Birthdays live in one cousin’s head. And the family stories? Slowly slipping away.', ['props' => ['phone', 'calendar', 'chat', 'doc'], 'mood' => 'stressed',
                            'alt' => ['funny' => 'Photos are stuck on three old phones, one of which is in a drawer “just in case”. [sfx:rimshot] Birthdays live in Cousin Tunde’s head. And Tunde is *unreachable*.']]),
                        self::scene('fp3', 'solution', 'reveal', 'One home for your *whole family*', 'FamilyPlatform gives your family one private home. [sfx:sparkle] Your family tree, your photos, your events and your stories, all together.', ['props' => ['tree'], 'mood' => 'happy', 'bullets' => ['Family tree', 'Shared photo albums', 'Birthday reminders', 'Private & invite-only']]),
                        self::scene('fp4', 'solution', 'steps', 'Three steps to *closer*', 'Start your tree. Invite your relatives. Then watch the memories roll in.', ['bullets' => ['Start your tree', 'Invite relatives', 'Share memories'], 'props' => ['tree', 'envelope', 'heart']]),
                        self::scene('fp5', 'proof', 'proof', 'Families love it', 'And families keep telling us the same thing.', ['quote' => 'Mum cried when she saw the whole tree. Happy tears, I promise.', 'author' => 'Adaeze, Manchester', 'mood' => 'proud', 'duration' => 5]),
                        self::scene('fp6', 'cta', 'cta', 'Start your family tree today', 'Start your family tree today. It’s free, it’s private, and Grandma will be *thrilled*. [sfx:tada]', ['cta' => 'Start your family tree', 'url' => 'familyplatform.com', 'qr' => true]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Family reunion', 'template' => 'scrapbook', 'kicker' => 'The Okafor family', 'headline' => 'Summer *Reunion* 2027', 'sub' => 'Bring a dish, bring a story, bring your dancing shoes', 'date' => 'Sat 14 August', 'time' => 'From 1pm', 'venue' => 'Victoria Park, Hackney', 'details' => ['Kids’ games', 'Family photo at 4pm'], 'url' => 'familyplatform.com/events/reunion', 'props' => ['tree', 'heart']],
                    ['label' => 'Golden anniversary', 'template' => 'ticket', 'kicker' => 'Celebrating 50 years', 'headline' => 'Mum & Dad’s *Golden* Anniversary', 'sub' => 'Join us for dinner, dancing and embarrassing speeches', 'date' => 'Sat 9 October', 'time' => '6pm', 'venue' => 'The Savoy, Strand', 'url' => 'familyplatform.com/events/golden', 'props' => ['heart', 'crown']],
                    ['label' => 'Naming ceremony', 'template' => 'papercut', 'kicker' => 'You’re invited', 'headline' => 'Welcome, *baby Amara*', 'sub' => 'A naming ceremony and lunch with family and friends', 'date' => 'Sun 3 May', 'time' => '12 noon', 'venue' => 'St Mary’s Hall, Croydon', 'url' => 'familyplatform.com/events/naming', 'props' => ['balloon', 'star']],
                ],
            ],
            'idecide' => [
                'brand' => ['name' => 'iDecide', 'url' => 'idecideapp.co.uk', 'a' => '#6366f1', 'b' => '#06b6d4', 'c' => '#f59e0b', 'd' => '#1e1b4b'],
                'character' => ['skin' => 'peach', 'hair' => 'ginger', 'hairStyle' => 'curly', 'top' => '#6366f1', 'bottom' => '#1e1b4b', 'acc' => 'glasses'],
                'explainers' => [[
                    'title' => 'Stop overthinking. Start deciding.',
                    'style' => 'motion', 'aspect' => '16:9',
                    'voice' => ['preset' => 'cheeky', 'enabled' => true], 'music' => ['genre' => 'chiptune', 'volume' => 0.4], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('id1', 'hook', 'hook', 'Still deciding… *since Tuesday*?', 'Stay or go? Rent or buy? Pizza or… also pizza? [pause] Some decisions take *days*.', ['props' => ['scales', 'dice', 'brain'], 'mood' => 'thinking', 'pose' => 'think', 'duration' => 5,
                            'alt' => ['funny' => 'Stay or go? Rent or buy? Pizza or… also pizza? [sfx:boing] You’ve made a pros-and-cons list so long it has its *own* pros and cons.', 'dramatic' => 'Every choice… a fork in the road. And you… have been standing at it… for *weeks*.']]),
                        self::scene('id2', 'problem', 'chaos', 'Pros, cons, *panic*', 'You list the pros. You list the cons. You ask four friends and get five opinions. [sfx:sadTrombone]', ['props' => ['doc', 'chat', 'clock', 'brain'], 'mood' => 'stressed']),
                        self::scene('id3', 'solution', 'reveal', 'See your options *play out*', 'iDecide runs your options through thousands of what-if scenarios, so you can see the likely outcomes side by side. [sfx:sparkle]', ['props' => ['chart'], 'mood' => 'excited', 'bullets' => ['Weigh what matters', 'Run what-if scenarios', 'See likely outcomes', 'Decide with confidence']]),
                        self::scene('id4', 'solution', 'stat', 'Scenarios tested per decision', 'Not a hunch. Not a coin toss. Thousands of scenarios, in seconds.', ['props' => ['dice'], 'mood' => 'proud', 'pose' => 'pointLeft', 'stat' => ['value' => '10000', 'suffix' => '+', 'label' => 'what-ifs, in seconds']]),
                        self::scene('id5', 'cta', 'cta', 'Make your next big call', 'Got a dilemma? Bring it to iDecide. [sfx:tada] Your future self says thanks.', ['cta' => 'Run my dilemma', 'url' => 'idecideapp.co.uk', 'qr' => true]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Town hall poll', 'template' => 'swiss', 'kicker' => 'Have your say', 'headline' => 'Should the high street go *car-free*?', 'sub' => 'Vote, see the scenarios, and help decide', 'date' => 'Closes 30 June', 'venue' => 'Online & at the library', 'url' => 'idecideapp.co.uk/polls/town', 'props' => ['scales']],
                    ['label' => 'Decision workshop', 'template' => 'bauhaus', 'kicker' => 'Free workshop', 'headline' => 'Decide better, *faster*', 'sub' => 'A hands-on hour on making big life choices', 'date' => 'Thu 12 June', 'time' => '6:30pm', 'venue' => 'The Hive, Leeds', 'url' => 'idecideapp.co.uk/workshop'],
                ],
            ],
            'iaccountapp' => [
                'brand' => ['name' => 'iAccount', 'url' => 'iaccountapp.co.uk', 'a' => '#10b981', 'b' => '#0ea5e9', 'c' => '#facc15', 'd' => '#0f2a3d'],
                'character' => ['skin' => 'honey', 'hair' => 'black', 'hairStyle' => 'short', 'top' => '#0ea5e9', 'bottom' => '#0f2a3d', 'acc' => 'glasses'],
                'explainers' => [[
                    'title' => 'Your books, finally behaving',
                    'style' => 'collage', 'aspect' => '16:9',
                    'voice' => ['preset' => 'posh', 'enabled' => true], 'music' => ['genre' => 'lofi', 'volume' => 0.4], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('ia1', 'hook', 'hook', 'Receipts in a *shoebox*?', 'Tell me, do your business receipts live in a *shoebox*? [pause] A carrier bag? [sfx:boing] The glovebox?', ['props' => ['receipt', 'doc', 'money'], 'mood' => 'shocked', 'pose' => 'shrug', 'duration' => 5,
                            'alt' => ['funny' => 'Tell me, do your receipts live in a *shoebox*? A carrier bag? [pause] Inside a *coat you no longer own*? [sfx:sadTrombone]']]),
                        self::scene('ia2', 'problem', 'chaos', 'Making Tax Digital is *here*', 'Quarterly updates. Digital records. Deadlines that creep up like a polite but persistent butler. [sfx:alert]', ['props' => ['receipt', 'calendar', 'warning', 'doc', 'clock'], 'mood' => 'stressed']),
                        self::scene('ia3', 'solution', 'reveal', 'Books that *balance themselves*', 'iAccount keeps proper double-entry books for you. [sfx:sparkle] Snap a receipt, match your bank, and see where you stand, any day of the year.', ['props' => ['receipt'], 'mood' => 'excited', 'bullets' => ['Snap receipts', 'Bank feeds matched', 'Digital records kept', 'Clear tax estimates']]),
                        self::scene('ia4', 'solution', 'split', 'Year-end: before and *after*', 'Before: a frantic January. After: a cup of tea and a report that adds up. [sfx:ding]', ['props' => ['receipt', 'chart'], 'bullets' => ['Before', 'After']]),
                        self::scene('ia5', 'cta', 'cta', 'Get your books in order', 'Shall we tidy those books? Start with iAccount today. [sfx:kaching]', ['cta' => 'Start my books', 'url' => 'iaccountapp.co.uk', 'qr' => true]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'MTD clinic', 'template' => 'blueprint', 'kicker' => 'Free drop-in clinic', 'headline' => 'Making Tax Digital, *explained*', 'sub' => 'Bring your questions. Leave with a plan.', 'date' => 'Wed 15 April', 'time' => '10am–2pm', 'venue' => 'Enterprise Hub, Bristol', 'details' => ['Sole traders', 'Landlords', 'Small firms'], 'url' => 'iaccountapp.co.uk/tax', 'props' => ['receipt']],
                    ['label' => 'Books health check', 'template' => 'score', 'kicker' => 'Your books health check', 'headline' => 'How *healthy* are your books?', 'sub' => 'A free 5-minute check-up', 'score' => '86', 'scoreMax' => '100', 'details' => ['Receipts matched', 'Bank reconciled', 'Deadlines tracked'], 'url' => 'iaccountapp.co.uk/check'],
                ],
            ],
            'tenantscore' => [
                'brand' => ['name' => 'TenantScore', 'url' => 'tenantscore.co.uk', 'a' => '#14b8a6', 'b' => '#3b82f6', 'c' => '#facc15', 'd' => '#0f2740'],
                'character' => ['skin' => 'espresso', 'hair' => 'black', 'hairStyle' => 'braids', 'top' => '#14b8a6', 'bottom' => '#0f2740', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'Renting, without the runaround',
                    'style' => 'whiteboard', 'aspect' => '16:9',
                    'voice' => ['preset' => 'narrator', 'enabled' => true, 'tune' => ['energy' => 1.2]], 'music' => ['genre' => 'bouncy', 'volume' => 0.4], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('ts1', 'hook', 'hook', 'Paid rent on time for *years*?', 'You’ve paid your rent on time for *years*. [pause] So why does every new flat feel like a job interview?', ['props' => ['house', 'key', 'calendar'], 'mood' => 'thinking', 'pose' => 'shrug', 'duration' => 5]),
                        self::scene('ts2', 'problem', 'chaos', 'Forms. References. *Waiting.*', 'Payslips. References. Forms in triplicate. And the flat you loved? Gone by Thursday. [sfx:sadTrombone]', ['props' => ['doc', 'clipboard', 'clock', 'envelope'], 'mood' => 'stressed',
                            'alt' => ['funny' => 'Payslips. References. A form asking for your *primary school*. [sfx:boing] And the flat you loved? Gone by Thursday. [sfx:sadTrombone]']]),
                        self::scene('ts3', 'solution', 'reveal', 'Your rental *passport*', 'TenantScore turns your rent history into a verified rental passport you can share with any landlord or agent, in one tap. [sfx:sparkle]', ['props' => ['shield'], 'mood' => 'excited', 'bullets' => ['Rent history verified', 'One shareable passport', 'You control access', 'Faster applications']]),
                        self::scene('ts4', 'solution', 'steps', 'How it *works*', 'Connect your rent payments. Get your score. Share your passport.', ['bullets' => ['Connect rent payments', 'Get your score', 'Share your passport'], 'props' => ['card', 'chart', 'key']]),
                        self::scene('ts5', 'cta', 'cta', 'Claim your rental passport', 'Ready to be the tenant every landlord hopes for? Claim your passport today. [sfx:tada]', ['cta' => 'Claim my passport', 'url' => 'tenantscore.co.uk', 'qr' => true]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Rental passport', 'template' => 'score', 'kicker' => 'TenantScore rental passport', 'headline' => 'Your rent history, *finally counted*', 'sub' => 'Share a verified passport with any landlord', 'score' => '920', 'scoreMax' => '1000', 'details' => ['36 months on time', 'Verified', 'You control access'], 'url' => 'tenantscore.co.uk/passport'],
                    ['label' => 'Open house', 'template' => 'editorial', 'kicker' => 'Open house', 'headline' => 'Two-bed garden flat, *Peckham*', 'sub' => 'Passport holders get first viewing', 'date' => 'Sat 21 June', 'time' => '11am–1pm', 'venue' => 'Choumert Road, SE15', 'url' => 'tenantscore.co.uk/viewings', 'props' => ['house', 'key']],
                ],
            ],
            'execmindapp' => [
                'brand' => ['name' => 'ExecMind', 'url' => 'execmind.app', 'a' => '#c9a227', 'b' => '#4f46e5', 'c' => '#f5d76e', 'd' => '#0b1026'],
                'character' => ['skin' => 'porcelain', 'hair' => 'grey', 'hairStyle' => 'short', 'top' => '#1f2a5a', 'bottom' => '#0b1026', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'The board pack that writes itself',
                    'style' => 'motion', 'aspect' => '16:9',
                    'voice' => ['preset' => 'trailer', 'enabled' => true], 'music' => ['genre' => 'cinematic', 'volume' => 0.5], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('em1', 'hook', 'hook', '*Forty* tabs open. One board meeting.', 'Forty tabs open. [pause] Six dashboards. One board meeting… in *ten minutes*.', ['props' => ['laptop', 'clock', 'chart'], 'mood' => 'shocked', 'duration' => 5,
                            'alt' => ['funny' => 'Forty tabs open. Six dashboards. One board meeting in ten minutes. [pause] And the coffee machine is *broken*. [sfx:sadTrombone]']]),
                        self::scene('em2', 'problem', 'chaos', 'Too much data. *Too little clarity.*', 'Reports from finance, sales and operations, all in different formats, all saying slightly different things.', ['props' => ['doc', 'chart', 'chat', 'briefcase'], 'mood' => 'stressed']),
                        self::scene('em3', 'solution', 'reveal', 'One brief. *Every priority.*', 'ExecMind pulls your numbers, risks and actions into one clear executive brief. [sfx:sparkle] Ready before your coffee is.', ['props' => ['laptop'], 'mood' => 'proud', 'bullets' => ['Key numbers in one view', 'Risks flagged early', 'Actions with owners', 'Board-ready summary']]),
                        self::scene('em4', 'proof', 'proof', 'Leaders, *briefed*', 'Leadership teams say it gives them back their Monday mornings.', ['quote' => 'Our board pack went from three days to one morning.', 'author' => 'Operations Director, logistics firm', 'mood' => 'proud', 'pose' => 'thumbsUp', 'duration' => 5]),
                        self::scene('em5', 'cta', 'cta', 'Walk in *prepared*', 'Walk into every meeting prepared. Book your ExecMind walkthrough. [sfx:boom]', ['cta' => 'Book a walkthrough', 'url' => 'execmind.app', 'qr' => true, 'mood' => 'proud', 'pose' => 'present']),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Leadership summit', 'template' => 'cinema', 'kicker' => 'ExecMind presents', 'headline' => 'The *Clarity* Summit', 'sub' => 'One day. Twelve leaders. No slide decks over 10 pages.', 'date' => '18 September', 'time' => '9am', 'venue' => 'The Shard, London', 'details' => ['Keynotes', 'Roundtables', 'Dinner'], 'url' => 'execmind.app/summit/ceo'],
                    ['label' => 'Briefing breakfast', 'template' => 'aurora', 'kicker' => 'Invite only', 'headline' => 'Board-ready by *breakfast*', 'sub' => 'A 45-minute walkthrough for leadership teams', 'date' => 'Tue 7 October', 'time' => '8am', 'venue' => 'The Ned, City of London', 'cta' => 'Reserve a seat', 'url' => 'execmind.app/breakfast'],
                ],
            ],
            'loaneasyfinance' => [
                'brand' => ['name' => 'LoanEasy', 'url' => 'loaneasyfinance.com', 'a' => '#2563eb', 'b' => '#22c55e', 'c' => '#facc15', 'd' => '#0b1f44'],
                'character' => ['skin' => 'umber', 'hair' => 'black', 'hairStyle' => 'ponytail', 'top' => '#2563eb', 'bottom' => '#0b1f44', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'Borrowing, explained like a friend would',
                    'style' => 'cartoon', 'aspect' => '16:9',
                    'voice' => ['preset' => 'scottish', 'enabled' => true], 'music' => ['genre' => 'bouncy', 'volume' => 0.4], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('le1', 'hook', 'hook', 'Loan jargon giving you a *headache*?', 'APR. LTV. Arrangement fees. [pause] Does loan jargon make your eyes glaze over? [sfx:boing]', ['props' => ['doc', 'pound', 'bank'], 'mood' => 'shocked', 'pose' => 'shrug', 'duration' => 5]),
                        self::scene('le2', 'problem', 'chaos', 'Forms, waiting, *wondering*', 'Long forms, longer waits, and no idea whether you’re even likely to be accepted.', ['props' => ['doc', 'clock', 'bank', 'warning'], 'mood' => 'stressed']),
                        self::scene('le3', 'solution', 'reveal', 'Clear numbers. *No surprises.*', 'LoanEasy shows you clear costs up front, in plain English. [sfx:sparkle] Apply online, track every step, and talk to a real person whenever you like.', ['props' => ['phone'], 'mood' => 'happy', 'bullets' => ['Plain-English costs', 'Simple online application', 'Track every step', 'Real people to talk to']]),
                        self::scene('le4', 'solution', 'steps', 'Three simple steps', 'Tell us what you need. See your options. Decide in your own time.', ['bullets' => ['Tell us what you need', 'See your options', 'Decide in your own time'], 'props' => ['clipboard', 'scales', 'check']]),
                        self::scene('le5', 'cta', 'cta', 'See what you could borrow', 'See what you could borrow with LoanEasy. Borrow responsibly; lending is subject to status. [sfx:ding]', ['cta' => 'Check my options', 'url' => 'loaneasyfinance.com', 'qr' => true, 'sub' => 'Lending subject to status. Borrow responsibly.']),
                    ],
                ]],
                'posters' => [
                    ['label' => 'First-time buyers evening', 'template' => 'papercut', 'kicker' => 'Free event', 'headline' => 'First home? *Let’s talk.*', 'sub' => 'Mortgages explained in plain English', 'date' => 'Thu 5 June', 'time' => '7pm', 'venue' => 'Central Library, Birmingham', 'details' => ['Q&A with advisers', 'Tea & biscuits'], 'url' => 'loaneasyfinance.com/events', 'props' => ['house', 'key']],
                    ['label' => 'Plain-English loans', 'template' => 'swiss', 'kicker' => 'LoanEasy', 'headline' => 'Loans, in *plain English*', 'sub' => 'Clear costs up front. Lending subject to status.', 'url' => 'loaneasyfinance.com'],
                ],
            ],
            'bcp' => [
                'brand' => ['name' => 'Best Care Professionals', 'url' => 'bestcareprofessionals.co.uk', 'a' => '#0d9488', 'b' => '#e11d48', 'c' => '#fbbf24', 'd' => '#12304a'],
                'character' => ['skin' => 'caramel', 'hair' => 'black', 'hairStyle' => 'long', 'top' => '#0d9488', 'bottom' => '#12304a', 'acc' => 'none'],
                'explainers' => [[
                    'title' => 'Inspection-ready, every single day',
                    'style' => 'whiteboard', 'aspect' => '16:9',
                    'voice' => ['preset' => 'irish', 'enabled' => true], 'music' => ['genre' => 'lofi', 'volume' => 0.4], 'captions' => true, 'sfx' => true,
                    'scenes' => [
                        self::scene('bc1', 'hook', 'hook', 'Inspection *next week*?', 'An inspection next week. [pause] Are all your staff files actually up to date?', ['props' => ['clipboard', 'calendar', 'medical'], 'mood' => 'shocked', 'duration' => 5]),
                        self::scene('bc2', 'problem', 'chaos', 'DBS. Training. References. *Everywhere.*', 'DBS checks in one folder, training certificates in another, references in someone’s inbox. [sfx:alert]', ['props' => ['doc', 'envelope', 'clipboard', 'warning'], 'mood' => 'stressed']),
                        self::scene('bc3', 'solution', 'reveal', 'Every staff file, *in one place*', 'Best Care Professionals keeps every recruitment check in one organised record, and reminds you before anything expires. [sfx:sparkle]', ['props' => ['clipboard'], 'mood' => 'happy', 'bullets' => ['DBS & right to work', 'Training tracked', 'References logged', 'Expiry reminders']]),
                        self::scene('bc4', 'proof', 'proof', 'Managers can *breathe*', 'Registered managers tell us it changes the week before an inspection completely.', ['quote' => 'For the first time, I wasn’t dreading the inspection.', 'author' => 'Registered manager, home care', 'mood' => 'proud', 'duration' => 5]),
                        self::scene('bc5', 'cta', 'cta', 'Book your free audit', 'See how organised your records could be. Book a free audit today. [sfx:ding]', ['cta' => 'Book a free audit', 'url' => 'bestcareprofessionals.co.uk', 'qr' => true]),
                    ],
                ]],
                'posters' => [
                    ['label' => 'Recruitment day', 'template' => 'chalk', 'kicker' => 'We’re hiring carers', 'headline' => 'Care with *heart*? Join us.', 'sub' => 'Flexible shifts, full training and a team that has your back', 'date' => 'Sat 17 May', 'time' => '10am–3pm', 'venue' => 'Community Centre, Luton', 'details' => ['Paid training', 'Flexible hours'], 'url' => 'bestcareprofessionals.co.uk/recruitment', 'props' => ['heart', 'medical']],
                    ['label' => 'Urgent shifts', 'template' => 'brutal', 'kicker' => 'Shifts available now', 'headline' => 'Weekend shifts — *pick yours*', 'sub' => 'Day and waking-night shifts this weekend', 'date' => 'This weekend', 'venue' => 'North London', 'url' => 'bestcareprofessionals.co.uk/shifts/urgent', 'badge' => 'New'],
                ],
            ],
        ];
    }
}
