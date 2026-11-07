<?php

declare(strict_types=1);

namespace Src\Media;

use RuntimeException;

/**
 * UniversalPosterStudioController
 *
 * Advanced Technology Group (ATG Pod 3 & Pod 4)
 * Turn-key Sovereign Poster & Flyer Studio Controller + Self-Contained UI Renderer.
 *
 * Powers `/poster-studio`, `/flyer-studio`, and `/api/poster-studio/render` across all 8 apps:
 * 1. PartyPlatform
 * 2. FamilyPlatform
 * 3. ExecMindApp
 * 4. Best Care Professionals (BCP)
 * 5. LoanEasyFinance
 * 6. iAccountApp
 * 7. iDecideApp
 * 8. TenantScore
 *
 * Strictly adheres to British English (en-GB) and Global UI/UX v2.0 Standards.
 */
class UniversalPosterStudioController
{
    /**
     * Get the complete 8-app portfolio preset catalogue across all 6 visual archetypes.
     *
     * @return array<string, array{
     *   app_key: string,
     *   app_name: string,
     *   badge_colour: string,
     *   default_preset: string,
     *   presets: array<string, array<string, mixed>>
     * }>
     */
    public static function getAppCatalog(): array
    {
        return [
            'partyplatform' => [
                'app_key' => 'partyplatform',
                'app_name' => 'PartyPlatform',
                'sector_name' => 'Nightlife & Social Events',
                'studio_title' => 'PartyPlatform Sovereign Nightlife & Event Poster Studio',
                'studio_subtitle' => 'VIP Celebrations, Day Clubs, Festivals & Nightlife Flyers',
                'badge_colour' => '#eab308',
                'default_preset' => 'vip_aarons_birthday',
                'subject_label' => 'Upload Celebrant / Host / DJ Photo',
                'field_labels' => [
                    'badge' => 'Top Presenter / Club Badge',
                    'kicker' => 'Script / Kicker Overlay',
                    'title' => 'Primary 3D / Display Headline',
                    'titleSecondary' => 'Secondary Title (e.g. PARTY)',
                    'watermark' => 'Backdrop Watermark (Sample #5)',
                    'date' => 'Event Date',
                    'time' => 'Doors / Start Time',
                    'venue' => 'Venue / Nightclub Name',
                    'address' => 'Venue Address / Location',
                    'hosts' => 'Hosted By / Organisers',
                    'musicBy' => 'Music Vibes / DJ Lineup',
                    'dressCode' => 'Dress Code / Entry Terms',
                    'accentMetric' => 'Ticket Tier / Admission Pill',
                    'perks' => 'Perks / Highlights (comma-separated)',
                    'rsvpContact' => 'RSVP / Table Booking Contact',
                    'footerStrip' => 'Bottom Admission Strip / Notice',
                ],
                'presets' => [
                    'pool_sunday_party' => [
                        'label' => 'Sunday Pool Party (Sample #1)',
                        'theme' => UniversalPosterSynthesisService::THEME_PARTY_POOL_TROPICAL,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_POOL_TROPICAL_SPLIT,
                        'aspectRatio' => 'feed',
                        'data' => [
                            'tenant' => 'partyplatform',
                            'badge' => 'MOONSTAR APART HOTEL',
                            'kicker' => 'Presents',
                            'title' => "SUNDAY\nPOOL\nPARTY",
                            'titleSecondary' => 'POOL PARTY',
                            'subtitle' => 'Tropical Daytime Vibes',
                            'date' => 'SUNDAY 12TH OCT 2026',
                            'time' => '2:00 PM',
                            'hosts' => 'ESTHER & CYNTHIA',
                            'musicBy' => "DJ NESSA\nSELECTA IZZY",
                            'venue' => 'MOONSTAR APART HOTEL',
                            'address' => 'Kicukiro - Near Catholic Church',
                            'rsvpContact' => '+44 7700 900513',
                            'qrUrl' => 'https://partyplatform.com/e/sunday-pool-party',
                        ],
                    ],
                    'neon_purple_party' => [
                        'label' => 'Tropical Purple Party (Sample #2)',
                        'theme' => UniversalPosterSynthesisService::THEME_PARTY_NEON,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_NEON_SCRIPT_CLASH,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'partyplatform',
                            'badge' => 'LICERIA CLUB',
                            'kicker' => 'TROPICAL',
                            'title' => 'purple',
                            'titleSecondary' => 'PARTY',
                            'subtitle' => 'Neon Botanical Night',
                            'date' => 'SATURDAY 30 AUGUST',
                            'time' => 'DOORS 10PM',
                            'dressCode' => 'ENTRY FREE',
                            'musicBy' => 'DJ FRANCOIS | DJ SEBASTIAN',
                            'venue' => 'LICERIA CLUB LONDON',
                            'address' => 'BOOK YOUR TICKET ON:',
                            'rsvpContact' => 'WWW.PARTYPLATFORM.COM',
                            'qrUrl' => 'https://partyplatform.com/e/tropical-purple-party',
                        ],
                    ],
                    'vip_aarons_birthday' => [
                        'label' => "Aaron's VIP Birthday (Sample #3)",
                        'theme' => UniversalPosterSynthesisService::THEME_PARTY_BLACK_GOLD,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_VIP_BLACK_GOLD,
                        'aspectRatio' => 'portrait',
                        'data' => [
                            'tenant' => 'partyplatform',
                            'badge' => "YOU'RE INVITED TO",
                            'kicker' => "Aaron's",
                            'title' => 'BIRTHDAY',
                            'titleSecondary' => 'PARTY',
                            'subtitle' => 'All Black Luxury Affair',
                            'date' => 'SAT MAY 07',
                            'time' => '9:00 PM',
                            'venue' => 'TAPE LONDON',
                            'address' => '17 HANOVER SQUARE, LONDON W1S 1HU',
                            'dressCode' => 'ALL BLACK AFFAIR • WEAR ALL BLACK',
                            'rsvpContact' => 'MARCH 31, 2026 • 020 7946 0192',
                            'photoMode' => 'ghost_double',
                            'qrUrl' => 'https://partyplatform.com/e/aarons-birthday',
                        ],
                    ],
                    'floral_alisa_birthday' => [
                        'label' => 'Happy Birthday Alisa (Sample #4)',
                        'theme' => UniversalPosterSynthesisService::THEME_PARTY_FLORAL_CHROME,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_FLORAL_CHROME_OVERLAP,
                        'aspectRatio' => 'portrait',
                        'data' => [
                            'tenant' => 'partyplatform',
                            'badge' => 'VIP CELEBRATION',
                            'kicker' => "Happy\nBirthday\nAlisa",
                            'title' => 'BIRTHDAY',
                            'titleSecondary' => 'Party',
                            'date' => '28 NOV',
                            'time' => '09 PM',
                            'musicBy' => 'DJ MAX GOSHA',
                            'perks' => ['LIVE MUSIC', 'DRINKS', 'SHISHA LOUNGE'],
                            'footerStrip' => 'ENTRY £15 • DOORS OPEN 9PM • FREE PARKING • 2 DRINKS FREE',
                            'qrUrl' => 'https://partyplatform.com/e/alisa-birthday',
                        ],
                    ],
                    'kinetic_friday_night' => [
                        'label' => 'Friday Party Night (Sample #5)',
                        'theme' => UniversalPosterSynthesisService::THEME_PARTY_PYRO_CLUB,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_KINETIC_CLUB_TUNNEL,
                        'aspectRatio' => 'feed',
                        'data' => [
                            'tenant' => 'partyplatform',
                            'badge' => 'YOUR CLUB PRESENTS',
                            'kicker' => 'Party',
                            'title' => 'NIGHT',
                            'titleSecondary' => 'NIGHT',
                            'watermarkText' => 'FRIDAY',
                            'date' => '27 AUG',
                            'time' => '12 PM',
                            'perks' => ['MUSIC', 'DANCE', 'VIP TABLES'],
                            'venue' => 'MAYFAIR BOULEVARD, LONDON',
                            'rsvpContact' => '020 7946 0888',
                            'qrUrl' => 'https://partyplatform.com/e/friday-party-night',
                        ],
                    ],
                ],
            ],
            'familyplatform' => [
                'app_key' => 'familyplatform',
                'app_name' => 'FamilyPlatform',
                'sector_name' => 'Family Milestones & Celebrations',
                'studio_title' => 'FamilyPlatform Heirloom Milestone & Jubilee Studio',
                'studio_subtitle' => 'Golden Jubilees, Heritage Reunions & Baby Naming Celebrations',
                'badge_colour' => '#f59e0b',
                'default_preset' => 'family_golden_anniversary',
                'subject_label' => 'Upload Celebrant / Grandparents / Family Photo',
                'field_labels' => [
                    'badge' => 'Family Dynasty / Host Clan',
                    'kicker' => 'Celebration Milestone Kicker',
                    'title' => 'Primary Milestone Headline',
                    'titleSecondary' => 'Gathering Type (e.g. BANQUET)',
                    'watermark' => 'Heritage Monogram Watermark',
                    'date' => 'Celebration Date',
                    'time' => 'Arrival & Reception Time',
                    'venue' => 'Ballroom / Conservatory Venue',
                    'address' => 'Venue Address / Estate',
                    'hosts' => 'Hosts & Family Elders',
                    'musicBy' => 'Musical Ensemble / Choir',
                    'dressCode' => 'Traditional Attire / Dress Code',
                    'accentMetric' => 'Milestone Honorific (e.g. 50 YEARS)',
                    'perks' => 'Celebration Highlights & Feast',
                    'rsvpContact' => 'Family RSVP Portal / Phone',
                    'footerStrip' => 'Generational Welcome Strip',
                ],
                'presets' => [
                    'family_golden_anniversary' => [
                        'label' => '50th Golden Jubilee Gala',
                        'theme' => UniversalPosterSynthesisService::THEME_FAMILY_HEIRLOOM_GOLD,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_VIP_BLACK_GOLD,
                        'aspectRatio' => 'portrait',
                        'backdrop_asset' => 'bg_family_golden_jubilee.jpg',
                        'data' => [
                            'tenant' => 'familyplatform',
                            'badge' => 'THE OLAOGUN DYNASTY PRESENTS',
                            'kicker' => 'Golden 50th',
                            'title' => 'ANNIVERSARY',
                            'titleSecondary' => 'BANQUET',
                            'date' => 'SAT AUG 22',
                            'time' => '4:00 PM',
                            'venue' => 'THE SAVOY BALLROOM',
                            'address' => 'STRAND, LONDON WC2R 0EZ',
                            'dressCode' => 'TRADITIONAL ROYAL GOLD & IVORY',
                            'rsvpContact' => 'FAMILY PORTAL RSVP • +44 7700 900200',
                            'photoMode' => 'ghost_double',
                            'qrUrl' => 'https://familyplatform.com/events/golden-jubilee',
                        ],
                    ],
                    'family_botanical_reunion' => [
                        'label' => 'Botanical Family Reunion',
                        'theme' => UniversalPosterSynthesisService::THEME_FAMILY_BOTANICAL,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_FLORAL_CHROME_OVERLAP,
                        'aspectRatio' => 'portrait',
                        'data' => [
                            'tenant' => 'familyplatform',
                            'badge' => 'ANNUAL HERITAGE GATHERING',
                            'kicker' => "Grand\nFamily\nReunion",
                            'title' => 'HERITAGE',
                            'titleSecondary' => 'Festival',
                            'date' => '15 AUG',
                            'time' => '01 PM',
                            'musicBy' => 'LIVE HIGHLIFE BAND & STORYTELLING',
                            'perks' => ['FAMILY TREE UNVEILING', 'FEAST', 'GAMES'],
                            'footerStrip' => 'ALL GENERATIONS WELCOME • KEW GARDENS PAVILION • FREE PARKING',
                            'qrUrl' => 'https://familyplatform.com/events/heritage-reunion',
                        ],
                    ],
                    'family_summer_picnic' => [
                        'label' => 'Summer Garden Picnic & Naming',
                        'theme' => 'family_pastel_baby',
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_POOL_TROPICAL_SPLIT,
                        'aspectRatio' => 'feed',
                        'data' => [
                            'tenant' => 'familyplatform',
                            'badge' => 'JOYFUL BLESSINGS',
                            'kicker' => 'Welcome',
                            'title' => "BABY\nNAMING\nFIESTA",
                            'date' => 'SUNDAY 14TH JUNE 2026',
                            'hosts' => 'GRANDPARENTS & FAMILY ELDERS',
                            'musicBy' => "ACOUSTIC SOUL\nCHOIR HARMONY",
                            'venue' => 'RICHMOND PARK CONSERVATORY',
                            'address' => 'Richmond, Surrey TW10 5HS',
                            'rsvpContact' => '+44 7700 900321',
                            'qrUrl' => 'https://familyplatform.com/events/naming-fiesta',
                        ],
                    ],
                ],
            ],
            'execmindapp' => [
                'app_key' => 'execmindapp',
                'app_name' => 'ExecMind',
                'sector_name' => 'C-Suite & Boardroom Intelligence',
                'studio_title' => 'ExecMind Boardroom Briefing & C-Suite Summit Studio',
                'studio_subtitle' => 'Chatham House Rule Executive Roundtables & Strategy Summits',
                'badge_colour' => '#38bdf8',
                'default_preset' => 'exec_ceo_roundtable',
                'subject_label' => 'Upload Keynote Speaker / Board Director Photo',
                'field_labels' => [
                    'badge' => 'Executive Leadership Series Badge',
                    'kicker' => 'Boardroom Briefing Kicker',
                    'title' => 'Executive Summit Headline',
                    'titleSecondary' => 'Executive Sub-theme / Focus',
                    'watermark' => 'Corporate Emblem Watermark',
                    'date' => 'Summit Date',
                    'time' => 'Reception & Dinner Time',
                    'venue' => 'Private Suite / Penthouse Venue',
                    'address' => 'Venue Address / Mayfair Location',
                    'hosts' => 'Keynote Speakers & Facilitators',
                    'musicBy' => 'Executive Moderation Lead',
                    'dressCode' => 'Boardroom Attire Protocol',
                    'accentMetric' => 'Governance Credential (e.g. 12 CPD CREDITS)',
                    'perks' => 'Executive Perks (Chatham House, Private Dining)',
                    'rsvpContact' => 'Confidential Boardroom RSVP Hotline',
                    'footerStrip' => 'Chatham House Rule Statutory Notice',
                ],
                'presets' => [
                    'exec_ceo_roundtable' => [
                        'label' => 'Global CEO AI & Strategy Summit',
                        'theme' => UniversalPosterSynthesisService::THEME_EXEC_SAPPHIRE_SUMMIT,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_EXECUTIVE_BOARD_SUMMIT,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'execmindapp',
                            'badge' => 'EXECMIND SOVEREIGN LEADERSHIP SERIES',
                            'kicker' => 'Q4 BOARDROOM BRIEFING',
                            'title' => "GLOBAL CEO\nROUNDTABLE",
                            'subtitle' => 'Autonomous Decision Intelligence & Capital Allocation',
                            'accentMetric' => '12 CPD CREDITS • CHATHAM HOUSE',
                            'date' => 'THURSDAY 19 NOVEMBER 2026',
                            'time' => '6:30 PM CHAMPAGNE RECEPTION',
                            'venue' => 'THE DORCHESTER PENTHOUSE, MAYFAIR',
                            'address' => '53 Park Lane, London W1K 1QA',
                            'hosts' => 'Keynote: Dr. Chen Wei & Olutobi (Deloitte)',
                            'perks' => ['BOARD NETWORKING', 'PRIVATE DINING', 'BESPOKE BRIEFING'],
                            'regulatoryNotice' => 'Strictly held under the Chatham House Rule. Unauthorised recording or broadcasting is prohibited.',
                            'qrUrl' => 'https://execmind.app/summit/ceo-roundtable',
                        ],
                    ],
                ],
            ],
            'bcp' => [
                'app_key' => 'bcp',
                'app_name' => 'Best Care Professionals',
                'sector_name' => 'Healthcare & CQC Regulated Staffing',
                'studio_title' => 'BCP Sovereign Healthcare Broadcast & Shift Studio',
                'studio_subtitle' => 'CQC Reg 18/19 Clinical Shift Broadcast & Carer Recruitment',
                'badge_colour' => '#f43f5e',
                'default_preset' => 'bcp_urgent_itu_shift',
                'subject_label' => 'Upload Senior Carer / Nurse / Clinician Photo',
                'field_labels' => [
                    'badge' => 'Clinical Agency / Healthcare Division',
                    'kicker' => 'Shift Urgency Alert Kicker',
                    'title' => 'Clinical Role / Position Headline',
                    'titleSecondary' => 'Clinical Speciality / Ward',
                    'watermark' => 'Clinical Watermark Code',
                    'date' => 'Shift Date / Rota Schedule',
                    'time' => 'Shift Timings (e.g. 19:00 – 07:00 ROTA)',
                    'venue' => 'Hospital / Care Home Facility',
                    'address' => 'Clinical Cluster / Location',
                    'hosts' => 'Clinical Governance Director / Lead',
                    'musicBy' => 'Matron / Charge Nurse Contact',
                    'dressCode' => 'Uniform & Clinical PPE Protocol',
                    'accentMetric' => 'Hourly Pay Rate (e.g. £38.50 – £44.00 / HR)',
                    'perks' => 'Carer Perks (Instant EWA, Free Uniform, Mileage)',
                    'rsvpContact' => 'Direct Shift Booking Hotline / WhatsApp',
                    'footerStrip' => 'CQC Regulation 18/19 Statutory Notice',
                ],
                'presets' => [
                    'bcp_urgent_itu_shift' => [
                        'label' => 'Urgent ITU/RGN Shift Surge Broadcast',
                        'theme' => UniversalPosterSynthesisService::THEME_BCP_URGENT_SHIFT,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_CLINICAL_SHIFT_SURGE,
                        'aspectRatio' => 'story',
                        'backdrop_asset' => 'bg_healthcare_clinical_surge.jpg',
                        'data' => [
                            'tenant' => 'bcp',
                            'badge' => 'BEST CARE PROFESSIONALS • RAPID CLINICAL DEPLOYMENT',
                            'kicker' => 'URGENT CLINICAL SHIFT SURGE • IMMEDIATE DEPLOYMENT',
                            'title' => "URGENT RGN &\nITU NURSES",
                            'subtitle' => 'Verified CQC Reg 18/19 Compliant Care Staffing',
                            'accentMetric' => '£38.50 – £44.00 / HR',
                            'date' => 'NIGHT & WEEKEND SHIFTS AVAILABLE',
                            'time' => '19:00 – 07:00 ROTA',
                            'venue' => 'ROYAL FREE & ARIA CARE HOMES',
                            'address' => 'Greater London & Surrey Clinical Cluster',
                            'hosts' => 'Clinical Lead: Dr. Alistair Vance (Ex-Bupa / NHS Consultant)',
                            'perks' => ['INSTANT EWA SAME-DAY PAY', 'FREE CLINICAL UNIFORM', 'MILEAGE PAID', 'WEEKLY CPD'],
                            'regulatoryNotice' => 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Reg 18/19 compliant.',
                            'qrUrl' => 'https://bestcareprofessionals.co.uk/shifts/urgent',
                        ],
                    ],
                    'bcp_carer_open_day' => [
                        'label' => 'Clinical Excellence & Carer Recruitment Day',
                        'theme' => UniversalPosterSynthesisService::THEME_BCP_CLINICAL_EXCELLENCE,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_CLINICAL_RECRUITMENT,
                        'aspectRatio' => 'story',
                        'backdrop_asset' => 'bg_healthcare_clinical_surge.jpg',
                        'data' => [
                            'tenant' => 'bcp',
                            'badge' => 'CQC SINGLE ASSESSMENT EXCELLENCE',
                            'kicker' => 'HEALTHCARE CAREERS OPEN DAY',
                            'title' => "SENIOR CARER\nOPEN DAY",
                            'subtitle' => 'Fast-Track DBS, Mandatory Training & Preferred Pool Onboarding',
                            'accentMetric' => 'UP TO £24.50 / HR + EWA PAY',
                            'date' => 'WEDNESDAY 25 NOVEMBER 2026',
                            'time' => '10:00 AM – 4:00 PM',
                            'venue' => 'BCP CLINICAL TRAINING ACADEMY',
                            'address' => 'Canary Wharf, London E14 5AB',
                            'perks' => ['SAME-DAY INTERVIEW', 'CPD ACCREDITED', 'FLEXIBLE SHIFTS'],
                            'regulatoryNotice' => 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Reg 18/19 compliant.',
                            'qrUrl' => 'https://bestcareprofessionals.co.uk/recruitment',
                        ],
                    ],
                ],
            ],
            'loaneasyfinance' => [
                'app_key' => 'loaneasyfinance',
                'app_name' => 'LoanEasyFinance',
                'sector_name' => 'Consumer Lending & Personal Finance',
                'studio_title' => 'LoanEasy Consumer Lending & Personal Finance Studio',
                'studio_subtitle' => 'Personal Loans, Home Mortgages, Car Finance & Everyday Lending (www.loaneasyfinance.com)',
                'badge_colour' => '#dc2626',
                'default_preset' => 'consumer_first_home_keys',
                'subject_label' => 'Upload Consumer / Homeowner / Family Photo',
                'field_labels' => [
                    'badge' => 'Lender Division / Brand',
                    'kicker' => 'Product Tag (e.g. NEW Exclusive Products / Special Rate)',
                    'title' => 'Headline (e.g. Finding the perfect home is hard. Owning it isn’t.)',
                    'titleSecondary' => 'Sub-Headline / Secondary Callout',
                    'watermark' => 'Reference Watermark',
                    'date' => 'Campaign Period / Validity (e.g. August 2026)',
                    'time' => 'Decision Speed (e.g. Instant 10-Min Decision)',
                    'venue' => 'Borrowing Range (e.g. Borrow £1,000 to £50,000)',
                    'address' => 'Terms (e.g. 5% Deposit • 90% & 95% LTV Available)',
                    'hosts' => 'Product Badge (e.g. Fee-Saver / Personal Loan)',
                    'musicBy' => 'Fee Policy (e.g. No Arrangement Fee)',
                    'dressCode' => 'Eligibility (e.g. UK Residents 18+ • Soft Credit Check)',
                    'accentMetric' => 'Representative APR (e.g. From 4.40% or just 8.55%*)',
                    'perks' => 'Core Benefits (No Fees, Fast Payout, Flexible Repayment)',
                    'rsvpContact' => 'Application Portal (e.g. www.loaneasyfinance.com)',
                    'footerStrip' => 'FCA Consumer Risk & APR Warning Notice',
                ],
                'presets' => [
                    'consumer_first_home_keys' => [
                        'label' => 'Retail Mortgage Card — Exclusive Home Products (From 4.40%)',
                        'theme' => UniversalPosterSynthesisService::THEME_LOANEASY_RETAIL_CARD,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_RETAIL_BANKING_FEATURES,
                        'aspectRatio' => 'portrait',
                        // No bundled backdrop: bg_consumer_family_home.jpg is a real Virgin Money advert
                        // (logo, rates, dates). Upload your own lifestyle photo as the backdrop instead.
                        'data' => [
                            'tenant' => 'loaneasyfinance',
                            'kicker' => 'NEW Exclusive Products',
                            'title' => 'LOANEASY FINANCE',
                            'accentMetric' => '4.40%',
                            'hosts' => 'Fee-Saver',
                            'date' => 'August 2026',
                            'venue' => '90% & 95% LTV Available',
                            'address' => '5% Deposit • No Arrangement Fee',
                            // Small print is generated adaptively by the engine from the advertised rate/product.
                            'qrUrl' => 'https://www.loaneasyfinance.com',
                        ],
                    ],
                    'consumer_moving_joy_hsbc' => [
                        'label' => 'Chevron Home Loan Campaign (From 8.55%*)',
                        'theme' => UniversalPosterSynthesisService::THEME_LOANEASY_CONSUMER_CHEVRON,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_CONSUMER_LOAN_CAMPAIGN,
                        'aspectRatio' => 'portrait',
                        // No bundled backdrop: bg_consumer_moving_joy.jpg is a real HSBC advert (logo,
                        // slogan, Indian T&Cs). The headline and subtitle below were HSBC's own copy too.
                        'data' => [
                            'tenant' => 'loaneasyfinance',
                            'kicker' => 'Interest rate starting at',
                            'title' => "Your new home, without the hard part.",
                            'subtitle' => 'Borrowing made simple',
                            'accentMetric' => '8.55%*',
                            'date' => 'Apply online today • Decision in principle online',
                            'venue' => 'Borrow £1,000 to £50,000',
                            'address' => 'No Arrangement Fees • Funds sent once approved',
                            // Small print is generated adaptively by the engine from the advertised rate/product.
                            'qrUrl' => 'https://www.loaneasyfinance.com',
                        ],
                    ],
                    'loaneasy_sme_facility' => [
                        'label' => 'Commercial SME Facility & Growth Capital (£500k)',
                        'theme' => UniversalPosterSynthesisService::THEME_FINTECH_SME_CAPITAL,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_FINTECH_FACILITY,
                        'aspectRatio' => 'story',
                        'backdrop_asset' => 'bg_fintech_sme_emerald.jpg',
                        'data' => [
                            'tenant' => 'loaneasyfinance',
                            'badge' => 'LOANEASY COMMERCIAL UNDERWRITING',
                            'kicker' => 'INSTANT ALGORITHMIC CREDIT DECISIONING',
                            'title' => "SME GROWTH\nCAPITAL",
                            'subtitle' => 'Working Capital, Equipment Finance & Commercial Bridging',
                            'accentMetric' => 'FROM 6.9% REP APR • UP TO £500K',
                            'date' => 'SAME-DAY UNDERWRITING DECISION',
                            'time' => '24-HOUR DISBURSEMENT',
                            'venue' => 'OPEN BANKING INSTANT VERIFICATION',
                            'address' => 'Zero Early Settlement Penalties • UK Registered SMEs',
                            'perks' => ['FCA AUTHORISED', 'NO HIDDEN FEES', 'FLEXIBLE TERMS'],
                            // Small print is generated adaptively (business-finance wording; no regulatory claims).
                            'qrUrl' => 'https://www.loaneasyfinance.com',
                        ],
                    ],
                ],
            ],
            'iaccountapp' => [
                'app_key' => 'iaccountapp',
                'app_name' => 'iAccountApp',
                'sector_name' => 'Corporate Treasury & Statutory Tax',
                'studio_title' => 'iAccount Treasury, Tax & Financial Reporting Studio',
                'studio_subtitle' => 'Making Tax Digital, Corporation Tax & Year-End Clinics',
                'badge_colour' => '#60a5fa',
                'default_preset' => 'iaccount_tax_health_experian',
                'subject_label' => 'Upload Chartered Accountant / Keynote Speaker Photo',
                'field_labels' => [
                    'badge' => 'Treasury / Advisory Division',
                    'kicker' => 'Statutory Notice Kicker',
                    'title' => 'Clinic / Masterclass Headline',
                    'titleSecondary' => 'Module / Subject (e.g. VAT OPTIMISATION)',
                    'watermark' => 'Ledger Reference Watermark',
                    'date' => 'Filing Deadline / Clinic Date',
                    'time' => 'Masterclass Start Time',
                    'venue' => 'Financial Command Centre / Venue',
                    'address' => 'City of London Headquarters / Online',
                    'hosts' => 'Senior Chartered Accountant / Tax Lead',
                    'musicBy' => 'Statutory Audit Partner',
                    'dressCode' => 'Corporate Attendance Protocol',
                    'accentMetric' => 'Tax Health Score (e.g. 980)',
                    'perks' => 'Curriculum (VAT, R&D Tax, Audit Defense)',
                    'rsvpContact' => 'Client Advisory Hotline / Booking Portal',
                    'footerStrip' => 'HMRC Making Tax Digital Statutory Notice',
                ],
                'presets' => [
                    'iaccount_tax_health_experian' => [
                        'label' => 'Score Card — Tax Health Score (980/1000)',
                        'theme' => UniversalPosterSynthesisService::THEME_IACCOUNT_TAX_HEALTH,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_TAX_HEALTH_SCORE,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'iaccountapp',
                            'badge' => 'IACCOUNT TREASURY & TAX SHIELD',
                            'kicker' => 'REAL-TIME STATUTORY READINESS SCORE',
                            'title' => "You got a 980\nTax Health Score",
                            'subtitle' => 'A quick health check of your records, receipts and upcoming tax deadlines',
                            'accentMetric' => '980',
                            'date' => 'UPDATED FOR TAX YEAR 2026/27',
                            'time' => 'DIGITAL RECORDS UP TO DATE',
                            'venue' => '£14,850 ESTIMATED SAVINGS',
                            'address' => 'Zero Corporation Tax Discrepancies • 100% Reconciled',
                            'hosts' => 'Receipts matched',
                            'musicBy' => 'Deadline reminders on',
                            'dressCode' => 'HMRC FRS 102 Standard',
                            'perks' => ['DIGITAL RECORDS', 'DEADLINE ALERTS', 'RECEIPTS MATCHED'],
                            'regulatoryNotice' => 'iAccount helps you keep digital records and estimate tax. Figures are estimates; check with an accountant before filing.',
                            'qrUrl' => 'https://iaccountapp.co.uk/tax-shield',
                        ],
                    ],
                    'iaccount_hmrc_mtd' => [
                        'label' => 'HMRC Making Tax Digital & Year-End Clinic',
                        'theme' => UniversalPosterSynthesisService::THEME_IACCOUNT_TAX_READINESS,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_TAX_TREASURY_CLINIC,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'iaccountapp',
                            'badge' => 'IACCOUNT TREASURY & TAX ADVISORY',
                            'kicker' => 'STATUTORY FILING DEADLINE BRIEFING',
                            'title' => "HMRC TAX &\nAUDIT CLINIC",
                            'subtitle' => 'Automated Double-Entry Reconciliation, VAT & Corporation Tax',
                            'accentMetric' => 'DIGITAL DOUBLE-ENTRY LEDGER',
                            'date' => 'FRIDAY 11 DECEMBER 2026',
                            'time' => '11:00 AM LIVE MASTERCLASS',
                            'venue' => 'IACCOUNT FINANCIAL COMMAND CENTRE',
                            'address' => 'Bishopsgate, City of London EC2N 4AY',
                            'perks' => ['VAT OPTIMISATION', 'R&D TAX CREDITS', 'YEAR-END READY'],
                            'regulatoryNotice' => 'iAccount helps you keep digital records and estimate tax. Figures are estimates; check with an accountant before filing.',
                            'qrUrl' => 'https://iaccountapp.co.uk/tax-clinic',
                        ],
                    ],
                ],
            ],
            'idecideapp' => [
                'app_key' => 'idecideapp',
                'app_name' => 'iDecideApp',
                'sector_name' => 'Civic Democracy & Verified Consensus',
                'studio_title' => 'iDecide Civic Town Hall & Referendum Studio',
                'studio_subtitle' => 'Verified Citizen Participation, Civic Debates & Referendum Ballots',
                'badge_colour' => '#a855f7',
                'default_preset' => 'idecide_town_hall',
                'subject_label' => 'Upload Town Hall Moderator / Civic Speaker Photo',
                'field_labels' => [
                    'badge' => 'Civic Consensus Institution',
                    'kicker' => 'Debate Format Kicker',
                    'title' => 'Referendum Motion / Debate Headline',
                    'titleSecondary' => 'Ballot Division (e.g. LIVE POLL)',
                    'watermark' => 'Civic Ballot Watermark',
                    'date' => 'Assembly / Hearing Date',
                    'time' => 'Live Stream & Voting Time',
                    'venue' => 'Civic Hall / Assembly Forum',
                    'address' => 'Civic Forum Location / Live Stream Link',
                    'hosts' => 'Moderator & Parliamentary Panel',
                    'musicBy' => 'Civic Debate Steward',
                    'dressCode' => 'Citizen Assembly Protocol',
                    'accentMetric' => 'Ballot Integrity (e.g. ZERO-BOT VERIFIED)',
                    'perks' => 'Debate Features (Live Sentiment, Instant Verdict)',
                    'rsvpContact' => 'Public Ballot Registration Portal',
                    'footerStrip' => 'Cryptographic Consensus Audit Notice',
                ],
                'presets' => [
                    'idecide_town_hall' => [
                        'label' => 'National Civic Debate & Referendum Poll',
                        'theme' => UniversalPosterSynthesisService::THEME_IDECIDE_CIVIC_DEBATE,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_CIVIC_REFERENDUM,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'idecideapp',
                            'badge' => 'IDECIDE VERIFIED CIVIC CONSENSUS',
                            'kicker' => 'LIVE PUBLIC POLICY TOWN HALL',
                            'title' => "CIVIC DEBATE\n& LIVE POLL",
                            'subtitle' => 'Cryptographically Verified Citizen Voting & Impact Matrix',
                            'accentMetric' => 'ZERO-BOT VERIFIED BALLOT',
                            'date' => 'TUESDAY 24 NOVEMBER 2026',
                            'time' => '7:00 PM LIVE STREAM',
                            'venue' => 'WESTMINSTER CIVIC FORUM',
                            'address' => 'Great George Street, London SW1P 3AA',
                            'perks' => ['LIVE SENTIMENT', 'EXPERT PANEL', 'INSTANT VERDICT'],
                            'regulatoryNotice' => 'Cryptographically audited ballot integrity powered by iDecide Sovereign Consensus Engine.',
                            'qrUrl' => 'https://idecideapp.co.uk/polls/town-hall',
                        ],
                    ],
                ],
            ],
            'scoretenant' => [
                'app_key' => 'scoretenant',
                'app_name' => 'TenantScore',
                'sector_name' => 'PropTech & Tenant Referencing',
                'studio_title' => 'TenantScore Verified Rent Passport & Property Studio',
                'studio_subtitle' => 'Open Banking Affordability, Verified Tenant Passports & Landlord Showcases',
                'badge_colour' => '#14b8a6',
                'default_preset' => 'tenant_credit_boost_experian',
                'subject_label' => 'Upload Verified Tenant / Landlord / Agent Photo',
                'field_labels' => [
                    'badge' => 'Referencing Standard / Division',
                    'kicker' => 'Showcase / Passport Kicker',
                    'title' => 'Passport / Viewing Headline',
                    'titleSecondary' => 'Property Category',
                    'watermark' => 'Property Reference Watermark',
                    'date' => 'Viewing Date / Open House',
                    'time' => 'Viewing Slots & Times',
                    'venue' => 'Development / Property Showcase',
                    'address' => 'Property Address & Postcode',
                    'hosts' => 'Verified Landlord / Leasing Agent',
                    'musicBy' => 'Affordability Officer',
                    'dressCode' => 'Tenant Onboarding Requirements',
                    'accentMetric' => 'Tenant Credit Score (e.g. 1250)',
                    'perks' => 'Tenant Perks (Zero Deposit, Instant Lease)',
                    'rsvpContact' => 'Viewing Booking Portal / Phone',
                    'footerStrip' => 'Tenant Fees Act 2019 Statutory Notice',
                ],
                'presets' => [
                    'tenant_credit_boost_experian' => [
                        'label' => 'Score Card — Tenant Rent Boost (1250/1250)',
                        'theme' => UniversalPosterSynthesisService::THEME_TENANT_CREDIT_BOOST,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_TENANT_CREDIT_BOOST,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'scoretenant',
                            'badge' => 'TENANTSCORE VERIFIED PASSPORT',
                            'kicker' => 'TENANT RENT REPORTING BOOST',
                            'title' => "You got a 45\npoint Boost!",
                            'subtitle' => 'Your boosted score means access to more and better rental homes with zero deposit',
                            'accentMetric' => '1250',
                            'date' => '36 MONTHS ON-TIME RENT',
                            'time' => 'SCORE UP BY 45 PTS',
                            'venue' => 'LANDLORD PASSPORT VERIFIED',
                            'address' => 'Open Banking Affordability Confirmed • Zero CCJs',
                            'hosts' => 'Verified Tenant Passport',
                            'musicBy' => 'Instant Landlord Reference',
                            'dressCode' => 'FCA Open Banking Compliant',
                            'perks' => ['ZERO DEPOSIT', 'PRE-APPROVED', 'INSTANT LEASE'],
                            'regulatoryNotice' => 'TenantScore UK. Compliant with Tenant Fees Act 2019 & Open Banking credit referencing protocols.',
                            'qrUrl' => 'https://tenantscore.co.uk/passport/verify',
                        ],
                    ],
                    'tenantscore_open_house' => [
                        'label' => 'Verified Tenant Passport & Property Open House',
                        'theme' => UniversalPosterSynthesisService::THEME_TENANT_PROPERTY_SHOWCASE,
                        'archetype' => UniversalPosterSynthesisService::ARCHETYPE_TENANT_RENT_PASSPORT,
                        'aspectRatio' => 'story',
                        'data' => [
                            'tenant' => 'scoretenant',
                            'badge' => 'TENANTSCORE PROPERTY & CREDIT VERIFICATION',
                            'kicker' => 'EXCLUSIVE LANDLORD & TENANT SHOWCASE',
                            'title' => "VERIFIED RENT\nPASSPORT",
                            'subtitle' => 'Instant Open Banking Affordability & Reference Scoring',
                            'accentMetric' => '99.4% RENT GUARANTEE SCORE',
                            'date' => 'SATURDAY 21 NOVEMBER 2026',
                            'time' => '11:00 AM – 3:00 PM VIEWINGS',
                            'venue' => 'KENSINGTON RESIDENCES SHOWCASE',
                            'address' => 'High Street Kensington, London W8 5SE',
                            'perks' => ['OPEN BANKING CHECK', 'ZERO DEPOSIT OPTION', 'INSTANT LEASE'],
                            'regulatoryNotice' => 'TenantScore UK. Compliant with the Tenant Fees Act 2019 & UK GDPR credit referencing standards.',
                            'qrUrl' => 'https://tenantscore.co.uk/passport/verify',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Normalise app identifier to one of the 8 canonical portfolio keys
     */
    public static function normaliseAppKey(string $rawKey): string
    {
        $clean = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $rawKey));
        return match ($clean) {
            'party', 'partyplatform' => 'partyplatform',
            'family', 'familyplatform' => 'familyplatform',
            'execmind', 'execmindapp' => 'execmindapp',
            'bcp', 'bestcare', 'bestcareprofessionals' => 'bcp',
            'loaneasy', 'loaneasyfinance' => 'loaneasyfinance',
            'iaccount', 'iaccountapp' => 'iaccountapp',
            'idecide', 'idecideapp' => 'idecideapp',
            'scoretenant', 'tenantscore' => 'scoretenant',
            default => 'partyplatform',
        };
    }

    /**
     * Render and output the interactive Studio HTML page directly
     *
     * @param array<string, mixed> $overrides
     */
    public function showStudio(string $appKey = '', array $overrides = []): void
    {
        if (!empty($_GET['asset']) && is_string($_GET['asset'])) {
            $this->serveStudioAsset($_GET['asset']);
            return;
        }

        $resolvedApp = $appKey !== ''
            ? $appKey
            : (string) ($_GET['app'] ?? $_ENV['APP_NAME'] ?? 'partyplatform');

        echo $this->renderStudioHtml($resolvedApp, $overrides);
    }

    /**
     * Safely serve bundled photorealistic studio backdrop or cutout assets (.jpg / .png)
     */
    public function serveStudioAsset(string $filename): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $safeName = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp)$/i', $safeName)) {
            if (!headers_sent()) {
                http_response_code(400);
            }
            echo 'Invalid asset identifier';
            return;
        }

        $assetPath = dirname(__DIR__, 2) . '/resources/poster-assets/' . $safeName;
        if (!is_file($assetPath)) {
            $fallback = '/Users/waleolaogun/Sites/shared-lib/resources/poster-assets/' . $safeName;
            if (is_file($fallback)) {
                $assetPath = $fallback;
            }
        }

        if (!is_file($assetPath)) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            echo 'Asset not found';
            exit;
        }

        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($assetPath));
            header('Cache-Control: public, max-age=86400');
            header('Access-Control-Allow-Origin: *');
        }
        readfile($assetPath);
        exit;
    }

    /**
     * Handle API request (`GET|POST /api/poster-studio/render`) for server-side PNG or JSON Data URI synthesis
     *
     * @param array<string, mixed> $input
     */
    public function renderApiPoster(array $input = []): void
    {
        $payload = count($input) > 0 ? $input : array_merge($_GET, $_POST);
        $rawBody = file_get_contents('php://input');
        if (is_string($rawBody) && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        $appKey = self::normaliseAppKey((string) ($payload['app'] ?? $payload['tenant'] ?? 'partyplatform'));
        $catalog = self::getAppCatalog();
        $appConfig = $catalog[$appKey] ?? $catalog['partyplatform'];
        $defaultPresetKey = $appConfig['default_preset'];
        $presetKey = (string) ($payload['preset'] ?? $defaultPresetKey);
        $preset = $appConfig['presets'][$presetKey] ?? reset($appConfig['presets']);

        $theme = (string) ($payload['theme'] ?? $preset['theme'] ?? UniversalPosterSynthesisService::THEME_PARTY_BLACK_GOLD);
        $aspectRatio = (string) ($payload['aspect_ratio'] ?? $payload['aspectRatio'] ?? $preset['aspectRatio'] ?? 'story');

        $presetData = is_array($preset['data'] ?? null) ? $preset['data'] : [];
        $customData = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Allow top-level scalar overrides (title, subtitle, date, time, venue, etc.)
        foreach (['kicker', 'title', 'subtitle', 'date', 'time', 'venue', 'address', 'accent_metric', 'regulatory_notice', 'qr_url'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $customData[$field] = strip_tags($payload[$field]);
            }
        }

        // Sanitise all string fields against XSS/control characters
        $mergedData = array_merge($presetData, $customData);
        foreach ($mergedData as $k => $v) {
            if (is_string($v)) {
                $mergedData[$k] = trim(strip_tags($v));
            }
        }

        $service = UniversalPosterSynthesisService::create([
            'theme' => $theme,
            'aspect_ratio' => $aspectRatio,
            'data' => $mergedData,
        ]);

        $format = strtolower((string) ($payload['format'] ?? 'json'));
        if ($format === 'png' || $format === 'image') {
            if (!headers_sent()) {
                header('Content-Type: image/png');
                header('Cache-Control: public, max-age=3600');
            }
            echo $service->toPngString();
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo (string) json_encode([
            'status' => 'success',
            'app' => $appKey,
            'theme' => $service->getTheme(),
            'archetype' => $service->getArchetype(),
            'width' => $service->getWidth(),
            'height' => $service->getHeight(),
            'data' => $service->getData(),
            'data_uri' => $service->toDataUri(),
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate the complete, self-contained HTML5 ATG Sovereign Poster & Flyer Studio UI
     *
     * @param array<string, mixed> $overrides
     */
    public function renderStudioHtml(string $appKey = 'partyplatform', array $overrides = []): string
    {
        $canonicalApp = self::normaliseAppKey($appKey);
        $catalog = self::getAppCatalog();
        $activeApp = $catalog[$canonicalApp] ?? $catalog['partyplatform'];
        $archetypes = UniversalPosterSynthesisService::getArchetypes();

        $engineJsPath = dirname(__DIR__, 2) . '/helpers/universal-poster-engine.js';
        $engineJsCode = is_file($engineJsPath) ? (string) file_get_contents($engineJsPath) : '';

        // Multi-tenant sovereign isolation: strictly isolate catalog to the active application
        $isolatedCatalog = [
            $canonicalApp => $activeApp,
        ];
        $catalogJson = (string) json_encode($isolatedCatalog, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $archetypesJson = (string) json_encode($archetypes, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $appNameSafe = htmlspecialchars($activeApp['app_name'], ENT_QUOTES, 'UTF-8');
        $canonicalAppSafe = htmlspecialchars($canonicalApp, ENT_QUOTES, 'UTF-8');
        $studioTitleSafe = htmlspecialchars($activeApp['studio_title'] ?? ($activeApp['app_name'] . ' Sovereign Studio'), ENT_QUOTES, 'UTF-8');
        $studioSubtitleSafe = htmlspecialchars($activeApp['studio_subtitle'] ?? 'Free In-House Design Engine', ENT_QUOTES, 'UTF-8');

        $cspNonce = defined('CSP_NONCE') ? (string) CSP_NONCE : (string) ($GLOBALS['__cspNonce'] ?? ($_SESSION['csp_nonce'] ?? ''));
        if ($cspNonce === '') {
            foreach (headers_list() as $hdr) {
                if (stripos($hdr, 'Content-Security-Policy') === 0 && preg_match("/'nonce-([a-zA-Z0-9+\/=_]+)'/", $hdr, $m)) {
                    $cspNonce = $m[1];
                    break;
                }
            }
        }
        $nonceAttr = $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en-GB" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appNameSafe} — ATG Sovereign Poster &amp; Flyer Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&amp;family=Anton&amp;family=Bebas+Neue&amp;family=Cinzel:wght@600;700;800;900&amp;family=Great+Vibes&amp;family=Montserrat:wght@500;600;700;800;900&amp;family=Oswald:wght@500;700&amp;family=Playfair+Display:ital,wght@0,600;0,700;0,900;1,600&amp;family=Sacramento&amp;display=swap" rel="stylesheet">
    <style{$nonceAttr}>
        :root {
            --bg-canvas: #070b14;
            --bg-surface: #0f172a;
            --bg-card: #1e293b;
            --border-subtle: rgba(148, 163, 184, 0.18);
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent-gold: #eab308;
            --accent-gold-hover: #facc15;
            --accent-emerald: #10b981;
            --accent-purple: #a855f7;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #070b14 55%, #020617 100%);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            overflow-x: hidden;
        }
        /* Top Executive Bar */
        .studio-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 24px;
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle);
            position: sticky;
            top: 0;
            z-index: 40;
            flex-wrap: wrap;
        }
        .brand-cluster {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-emblem {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fde047 0%, #ca8a04 100%);
            color: #090d16;
            font-weight: 900;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(234, 179, 8, 0.25);
            flex-shrink: 0;
        }
        .brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        .brand-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        /* App Switcher Bar - hidden for sovereign single-app isolation */
        .app-pill-strip {
            display: none !important;
        }
        .btn-pill {
            min-height: 44px;
            min-width: 44px;
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid var(--border-subtle);
            background: rgba(30, 41, 59, 0.75);
            color: var(--text-secondary);
            font-size: 0.8125rem;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.16s ease;
            text-decoration: none;
        }
        .btn-pill:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
            border-color: rgba(250, 204, 21, 0.45);
        }
        .btn-pill:active {
            transform: scale(0.98);
        }
        .btn-pill:focus-visible {
            outline: 2px solid var(--accent-gold);
            outline-offset: 2px;
        }
        .btn-pill.active {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.22), rgba(161, 98, 7, 0.35));
            border-color: var(--accent-gold);
            color: #fef08a;
            box-shadow: 0 4px 14px rgba(234, 179, 8, 0.2);
        }
        .btn-primary-gold {
            background: linear-gradient(135deg, #facc15 0%, #ca8a04 100%);
            color: #090d16;
            border: none;
            font-weight: 800;
        }
        .btn-primary-gold:hover {
            filter: brightness(1.06);
        }
        .btn-emerald {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            font-weight: 700;
        }
        /* Main Studio Grid */
        .studio-workspace {
            display: grid;
            grid-template-columns: 420px 1fr 380px;
            gap: 24px;
            max-width: 1760px;
            margin: 0 auto;
            padding: 24px;
            align-items: start;
        }
        @media (max-width: 1380px) {
            .studio-workspace {
                grid-template-columns: 380px 1fr;
            }
            .studio-right-panel {
                grid-column: 1 / -1;
            }
        }
        @media (max-width: 920px) {
            .studio-workspace {
                grid-template-columns: 1fr;
                padding: 16px;
            }
        }
        .surface-card {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }
        .section-header {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .badge-caption {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(234, 179, 8, 0.16);
            color: #fde047;
        }
        /* Archetype Selector Cards */
        .archetype-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }
        .archetype-card {
            min-height: 68px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--border-subtle);
            background: rgba(30, 41, 59, 0.65);
            color: var(--text-primary);
            text-align: left;
            cursor: pointer;
            transition: all 0.16s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 4px;
        }
        .archetype-card:hover {
            border-color: rgba(250, 204, 21, 0.5);
            transform: translateY(-1px);
        }
        .archetype-card.active {
            border-color: var(--accent-gold);
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.18), rgba(30, 41, 59, 0.9));
            box-shadow: 0 0 0 1px var(--accent-gold);
        }
        .archetype-card-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #f8fafc;
        }
        .archetype-card-ref {
            font-size: 0.6875rem;
            color: #fde047;
            font-weight: 600;
        }
        /* Form Controls */
        .form-group {
            margin-bottom: 12px;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(2, 6, 23, 0.75);
            color: #ffffff;
            font-size: 0.875rem;
            transition: border-color 0.15s ease;
        }
        .form-textarea {
            min-height: 68px;
            resize: vertical;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.2);
        }
        /* Canvas Stage */
        .canvas-stage-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: radial-gradient(circle at center, rgba(30, 41, 59, 0.7) 0%, rgba(7, 11, 20, 0.95) 100%);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            position: sticky;
            top: 92px;
        }
        .canvas-viewport {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-height: 76vh;
            overflow: hidden;
            border-radius: 14px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.1);
            background: #050507;
        }
        #atgPosterCanvas {
            max-width: 100%;
            max-height: 74vh;
            width: auto;
            height: auto;
            display: block;
        }
        .canvas-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
            justify-content: center;
            width: 100%;
        }
        /* Loading Spinner */
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(9, 13, 22, 0.25);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .is-loading .spinner {
            display: inline-block;
        }
        .is-loading {
            opacity: 0.75;
            pointer-events: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Living Guide Modal */
        .guide-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(8px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .guide-modal-backdrop.open {
            display: flex;
        }
        .guide-modal {
            background: #0f172a;
            border: 1px solid rgba(234, 179, 8, 0.35);
            border-radius: 20px;
            max-width: 960px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.8);
        }
        .quadrant-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        @media (max-width: 768px) {
            .quadrant-grid {
                grid-template-columns: 1fr;
            }
        }
        .quadrant-box {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 16px;
        }
        .quadrant-box h4 {
            color: #fde047;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        .quadrant-box p, .quadrant-box li {
            font-size: 0.825rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .quadrant-box ul {
            padding-left: 18px;
        }
        .status-toast {
            margin-top: 12px;
            font-size: 0.8125rem;
            color: #34d399;
            min-height: 20px;
            text-align: center;
            font-weight: 600;
        }
        /* CSP Zero-Inline Utility Classes */
        .topbar-actions { display: flex; gap: 8px; align-items: center; }
        .mt-16 { margin-top: 16px; }
        .mb-12 { margin-bottom: 12px; }
        .ratio-strip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .file-input-padded { padding: 8px; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; cursor: pointer; min-height: 44px; }
        .checkbox-input { width: 18px; height: 18px; }
        .range-slider { width: 100%; min-height: 44px; }
        .modal-header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px; }
        .modal-title-h2 { font-size: 1.35rem; font-weight: 800; color: #fff; margin-top: 4px; }
    </style>
</head>
<body>

    <!-- Top Sovereign Navigation & Single-App Branding -->
    <header class="studio-topbar" role="banner">
        <div class="brand-cluster">
            <div class="brand-emblem" aria-hidden="true">ATG</div>
            <div>
                <h1 class="brand-title" id="studioMainTitle">{$studioTitleSafe}</h1>
                <p class="brand-subtitle" id="studioSubTitle">{$studioSubtitleSafe} • Free In-House Creative Suite</p>
            </div>
        </div>

        <div class="topbar-actions">
            <button type="button" class="btn-pill" id="btnOpenLivingGuide" title="Open 4-Quadrant How to Use Playbook">
                <span>📖 How to Use Guide</span>
            </button>
        </div>
    </header>

    <!-- 3-Column Studio Workspace -->
    <main class="studio-workspace">

        <!-- Left Column: Archetype, Preset & Aspect Ratio Controls -->
        <section class="surface-card" aria-label="Template & Layout Archetype Controls">
            <div class="section-header">
                <span>1. Select Sector Template</span>
                <span class="badge-caption">Free In-House</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="presetSelect">Curated Event &amp; Sector Presets</label>
                <select id="presetSelect" class="form-select" aria-label="Select curated poster preset"></select>
            </div>

            <div class="section-header mt-16">
                <span>2. Visual Layout Archetype</span>
                <span class="badge-caption">Sector Tailored</span>
            </div>
            <div class="archetype-grid" id="archetypeGrid" role="radiogroup" aria-label="Visual Layout Archetype">
                <!-- Populated by JS -->
            </div>

            <div class="section-header">
                <span>3. Canvas Aspect Ratio</span>
            </div>
            <div class="ratio-strip-container" id="ratioStrip">
                <button type="button" class="btn-pill" data-ratio="story">Story (9:16)</button>
                <button type="button" class="btn-pill" data-ratio="portrait">Portrait (4:5)</button>
                <button type="button" class="btn-pill" data-ratio="feed">Square (1:1)</button>
                <button type="button" class="btn-pill" data-ratio="poster_a4">A4 Print</button>
            </div>

            <div class="section-header">
                <span>4. Hero Portrait &amp; Smart Cutout</span>
                <span class="badge-caption">AI-Free Edge Alpha</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="subjectUploadInput" id="lblSubjectUpload">Upload Subject / Celebrant / Speaker Photo</label>
                <input type="file" id="subjectUploadInput" accept="image/*" class="form-input file-input-padded">
            </div>

            <div class="form-row-2 mb-12">
                <label class="checkbox-label">
                    <input type="checkbox" id="chkAutoCutout" checked class="checkbox-input">
                    <span>Smart Background Cutout</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="chkGhostEcho" checked class="checkbox-input">
                    <span>Ghost Double-Exposure</span>
                </label>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="rngSubjectScale">Subject Zoom (<span id="lblScaleVal">100%</span>)</label>
                    <input type="range" id="rngSubjectScale" min="0.5" max="1.8" step="0.05" value="1.0" class="range-slider">
                </div>
                <div class="form-group">
                    <label class="form-label" for="rngSubjectOffsetY">Vertical Offset</label>
                    <input type="range" id="rngSubjectOffsetY" min="-260" max="260" step="10" value="0" class="range-slider">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="rngSubjectBgFade">Photo Background Transparency (<span id="lblBgFadeVal">0%</span>)</label>
                <input type="range" id="rngSubjectBgFade" min="0" max="100" step="5" value="0" class="range-slider" aria-describedby="hintSubjectBgFade">
                <small id="hintSubjectBgFade" style="display:block;opacity:.7;font-size:12px;margin-top:4px">Fades the walls/room behind the person in your uploaded photo. 100% removes it.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="bgUploadInput">Optional Custom Backdrop Texture</label>
                <input type="file" id="bgUploadInput" accept="image/*" class="form-input file-input-padded">
            </div>
        </section>

        <!-- Centre Column: Live Interactive High-DPI Canvas Preview -->
        <section class="canvas-stage-card" aria-label="Live Poster Canvas Preview">
            <div class="canvas-viewport">
                <canvas id="atgPosterCanvas" width="1080" height="1920"></canvas>
            </div>

            <div class="canvas-toolbar">
                <button type="button" class="btn-pill btn-primary-gold" id="btnDownloadPng">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>⬇ Download High-Res PNG</span>
                </button>
                <button type="button" class="btn-pill btn-emerald" id="btnShareFlyer">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>📲 Share to WhatsApp / Social</span>
                </button>
                <button type="button" class="btn-pill" id="btnServerRenderVerify">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>⚡ Verify Server GD Render</span>
                </button>
                <button type="button" class="btn-pill" id="btnResetPreset">
                    <span>↺ Reset Template</span>
                </button>
            </div>
            <div id="studioStatusToast" class="status-toast" role="status" aria-live="polite"></div>
        </section>

        <!-- Right Column: Live Typography, Billing & Compliance Customisation -->
        <section class="surface-card studio-right-panel" aria-label="Typography & Event Details Customisation">
            <div class="section-header">
                <span id="customiseSectionTitle">5. Customise Details &amp; Copy</span>
                <span class="badge-caption" id="customiseSectorBadge">en-GB Standard</span>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpBadge" id="lblInpBadge">Top Presenter / Badge</label>
                    <input type="text" id="inpBadge" class="form-input" placeholder="e.g. YOU'RE INVITED TO">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpKicker" id="lblInpKicker">Script / Kicker Overlay</label>
                    <input type="text" id="inpKicker" class="form-input" placeholder="e.g. Aaron's / Happy Birthday">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="inpTitle" id="lblInpTitle">Primary 3D / Display Headline</label>
                <textarea id="inpTitle" class="form-textarea" placeholder="e.g. SUNDAY POOL PARTY or BIRTHDAY"></textarea>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpTitleSecondary" id="lblInpTitleSecondary">Secondary Title</label>
                    <input type="text" id="inpTitleSecondary" class="form-input" placeholder="e.g. PARTY / NIGHT">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpWatermark" id="lblInpWatermark">Backdrop Text (Sample #5)</label>
                    <input type="text" id="inpWatermark" class="form-input" placeholder="e.g. FRIDAY">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpDate" id="lblInpDate">Event Date</label>
                    <input type="text" id="inpDate" class="form-input" placeholder="e.g. SAT MAY 07">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpTime" id="lblInpTime">Doors / Start Time</label>
                    <input type="text" id="inpTime" class="form-input" placeholder="e.g. 9:00 PM">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="inpVenue" id="lblInpVenue">Venue Name</label>
                <input type="text" id="inpVenue" class="form-input" placeholder="e.g. TAPE LONDON">
            </div>

            <div class="form-group">
                <label class="form-label" for="inpAddress" id="lblInpAddress">Venue Address / Location</label>
                <input type="text" id="inpAddress" class="form-input" placeholder="e.g. 17 Hanover Square, London W1S 1HU">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpHosts" id="lblInpHosts">Hosted By / Keynote</label>
                    <input type="text" id="inpHosts" class="form-input" placeholder="e.g. ESTHER & CYNTHIA">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpMusicBy" id="lblInpMusicBy">Music Vibes / Lineup</label>
                    <input type="text" id="inpMusicBy" class="form-input" placeholder="e.g. DJ NESSA • SELECTA IZZY">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpDressCode" id="lblInpDressCode">Dress Code / Admission</label>
                    <input type="text" id="inpDressCode" class="form-input" placeholder="e.g. ALL BLACK AFFAIR">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpAccentMetric" id="lblInpAccentMetric">Rate / APR / Metric Pill</label>
                    <input type="text" id="inpAccentMetric" class="form-input" placeholder="e.g. £36.50/HR or 6.9% APR">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="inpPerks" id="lblInpPerks">Perks / Highlights (comma-separated)</label>
                <input type="text" id="inpPerks" class="form-input" placeholder="e.g. LIVE MUSIC, DRINKS, VIP TABLES">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label" for="inpRsvpContact" id="lblInpRsvpContact">RSVP / Contact / Website</label>
                    <input type="text" id="inpRsvpContact" class="form-input" placeholder="e.g. +44 7700 900077">
                </div>
                <div class="form-group">
                    <label class="form-label" for="inpQrUrl" id="lblInpQrUrl">Dynamic QR Target URL</label>
                    <input type="url" id="inpQrUrl" class="form-input" placeholder="https://partyplatform.com/rsvp">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="inpFooterStrip" id="lblInpFooterStrip">Bottom Strip / Statutory Notice</label>
                <input type="text" id="inpFooterStrip" class="form-input" placeholder="e.g. ENTRY £15 • DOORS OPEN 9PM • FREE PARKING">
            </div>
        </section>
    </main>

    <!-- 4-Quadrant Living Playbook Modal (Standard 19 Compliance) -->
    <div class="guide-modal-backdrop" id="livingGuideModal" role="dialog" aria-modal="true" aria-labelledby="guideModalTitle">
        <div class="guide-modal">
            <div class="modal-header-bar">
                <div>
                    <span class="badge-caption">Mandatory 4-Quadrant Living Guide</span>
                    <h2 id="guideModalTitle" class="modal-title-h2">
                        ATG Sovereign Poster &amp; Flyer Studio Playbook
                    </h2>
                </div>
                <button type="button" class="btn-pill" id="btnCloseLivingGuide">✕ Close</button>
            </div>

            <div class="quadrant-grid">
                <div class="quadrant-box">
                    <h4>1. Strategic Value &amp; Commercial ROI</h4>
                    <p>Replaces third-party commercial SaaS design tools with a zero-licence-fee, zero-dependency sovereign design engine tailored specifically for {$appNameSafe}.</p>
                    <ul>
                        <li><strong>Zero External Egress:</strong> Uploaded photos and event assets never leave your browser during compositing.</li>
                        <li><strong>Viral Acquisition:</strong> Every exported poster embeds a scannable QR code and deep-link back to {$appNameSafe}.</li>
                    </ul>
                </div>

                <div class="quadrant-box">
                    <h4>2. Visual Studio Architecture</h4>
                    <ul>
                        <li><strong>Sector-Tailored Archetypes:</strong> High-art composition engines engineered specifically for {$appNameSafe}.</li>
                        <li><strong>3D Procedural Typography:</strong> Specular bevels, drop shadows, metallic foils and glowing neon cursive shaders.</li>
                        <li><strong>Text-Behind-Subject Depth:</strong> Multi-layer canvas compositing positioning subjects between backdrop typography and foreground overlays.</li>
                        <li><strong>High-Resolution Export:</strong> Renders 1080&times;1920 (9:16) and 1080&times;1350 (4:5) graphics directly to PNG.</li>
                    </ul>
                </div>

                <div class="quadrant-box">
                    <h4>3. Step-by-Step Standard Operating Procedure</h4>
                    <ul>
                        <li><strong>Step 1:</strong> Select a curated {$appNameSafe} preset or start from scratch.</li>
                        <li><strong>Step 2:</strong> Switch between available layout archetypes and aspect ratios (9:16 Story, 4:5 Portrait, 1:1 Square, A4 Print).</li>
                        <li><strong>Step 3:</strong> Upload a photo and toggle <em>Smart Background Cutout</em> or <em>Ghost Double-Exposure</em>.</li>
                        <li><strong>Step 4:</strong> Customise the headline, date, venue, and highlights in real time, then click <strong>Download High-Res PNG</strong>.</li>
                    </ul>
                </div>

                <div class="quadrant-box">
                    <h4>4. Pro Tips &amp; Best Practices</h4>
                    <ul>
                        <li><strong>Text-Behind-Subject Depth:</strong> Upload a portrait photo with Smart Cutout enabled—your subject automatically stands in front of giant backdrop lettering.</li>
                        <li><strong>Native Sharing:</strong> Tap <em>Share Flyer</em> on mobile devices to send instantly via WhatsApp or system share sheets.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline Sovereign Engine -->
    <script{$nonceAttr}>
{$engineJsCode}
    </script>

    <!-- Studio Orchestration Script -->
    <script{$nonceAttr}>
    (function() {
        const CATALOG = {$catalogJson};
        const ARCHETYPES = {$archetypesJson};
        let activeAppKey = '{$canonicalAppSafe}';
        let activePresetKey = CATALOG[activeAppKey] ? CATALOG[activeAppKey].default_preset : 'vip_aarons_birthday';

        const canvasEl = document.getElementById('atgPosterCanvas');
        const engine = new AtgUniversalPosterEngine({
            canvas: canvasEl,
            aspectRatio: 'portrait',
            theme: 'party_black_gold'
        });
        window.atgPosterStudioEngine = engine;

        const presetSelectEl = document.getElementById('presetSelect');
        const archetypeGridEl = document.getElementById('archetypeGrid');
        const ratioStripEl = document.getElementById('ratioStrip');
        const toastEl = document.getElementById('studioStatusToast');

        function showToast(msg) {
            if (toastEl) {
                toastEl.textContent = msg;
            }
        }

        function populatePresetsForApp(appKey) {
            const app = CATALOG[appKey] || Object.values(CATALOG)[0];
            if (!app || !app.presets) return;
            presetSelectEl.innerHTML = '';
            Object.entries(app.presets).forEach(([key, preset]) => {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = preset.label;
                if (key === activePresetKey) opt.selected = true;
                presetSelectEl.appendChild(opt);
            });
        }

        function updateSectorUi(appKey) {
            const app = CATALOG[appKey] || CATALOG.partyplatform;
            document.title = (app.studio_title || app.app_name) + ' — ATG Sovereign Studio';
            const mainTitleEl = document.getElementById('studioMainTitle');
            if (mainTitleEl) mainTitleEl.textContent = app.studio_title || (app.app_name + ' Sovereign Studio');
            const subTitleEl = document.getElementById('studioSubTitle');
            if (subTitleEl) subTitleEl.textContent = (app.studio_subtitle || 'Zero-Cost In-House Design Engine') + ' • ' + (app.sector_name || app.app_name);
            
            const lblSubj = document.getElementById('lblSubjectUpload');
            if (lblSubj) lblSubj.textContent = app.subject_label || 'Upload Subject Photo';

            const custTitle = document.getElementById('customiseSectionTitle');
            if (custTitle) custTitle.textContent = '5. Customise ' + (app.sector_name || 'Typography & Details');
            const custBadge = document.getElementById('customiseSectorBadge');
            if (custBadge) custBadge.textContent = app.sector_name || 'en-GB Standard';

            const labels = app.field_labels || {};
            const labelMap = {
                'lblInpBadge': labels.badge || 'Top Presenter / Badge',
                'lblInpKicker': labels.kicker || 'Script / Kicker Overlay',
                'lblInpTitle': labels.title || 'Primary Headline',
                'lblInpTitleSecondary': labels.titleSecondary || 'Secondary Title',
                'lblInpWatermark': labels.watermark || 'Backdrop Text',
                'lblInpDate': labels.date || 'Event Date',
                'lblInpTime': labels.time || 'Doors / Time',
                'lblInpVenue': labels.venue || 'Venue / Facility Name',
                'lblInpAddress': labels.address || 'Address / Location',
                'lblInpHosts': labels.hosts || 'Hosted By / Lead',
                'lblInpMusicBy': labels.musicBy || 'Music / Secondary Lead',
                'lblInpDressCode': labels.dressCode || 'Dress Code / Protocol',
                'lblInpAccentMetric': labels.accentMetric || 'Rate / Metric Pill',
                'lblInpPerks': labels.perks || 'Perks / Highlights',
                'lblInpRsvpContact': labels.rsvpContact || 'RSVP / Contact / Booking',
                'lblInpFooterStrip': labels.footerStrip || 'Bottom Statutory Notice'
            };

            Object.entries(labelMap).forEach(([id, text]) => {
                const el = document.getElementById(id);
                if (el) el.textContent = text;
            });
        }

        function renderArchetypeCards(appKey) {
            archetypeGridEl.innerHTML = '';
            const key = appKey || activeAppKey;
            const isHealthcare = key === 'bcp';
            const isFintech = key === 'loaneasyfinance';
            const isTax = key === 'iaccountapp';
            const isExec = key === 'execmindapp';
            const isCivic = key === 'idecideapp';
            const isTenant = key === 'scoretenant';
            const isFamily = key === 'familyplatform';
            const isParty = key === 'partyplatform';

            const allowedArchs = Object.values(ARCHETYPES).filter((arch) => {
                if (isHealthcare) return ['clinical_shift_surge', 'clinical_recruitment_day', 'sector_authority_grid'].includes(arch.id);
                if (isFintech) return ['retail_banking_features', 'consumer_loan_campaign', 'fintech_facility_offer'].includes(arch.id);
                if (isTax) return ['tax_health_score_app', 'tax_statutory_clinic', 'sector_authority_grid'].includes(arch.id);
                if (isExec) return ['executive_board_summit', 'sector_authority_grid'].includes(arch.id);
                if (isCivic) return ['civic_referendum_poll', 'sector_authority_grid'].includes(arch.id);
                if (isTenant) return ['tenant_credit_boost_mockup', 'tenant_rent_passport', 'sector_authority_grid'].includes(arch.id);
                if (isFamily) return ['vip_black_gold', 'floral_chrome_overlap', 'pool_tropical_split'].includes(arch.id);
                if (isParty) return ['pool_tropical_split', 'neon_script_clash', 'vip_black_gold', 'floral_chrome_overlap', 'kinetic_club_tunnel'].includes(arch.id);
                return true;
            });

            allowedArchs.forEach((arch) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('data-archetype', arch.id);
                btn.className = 'archetype-card' + (engine.archetype === arch.id ? ' active' : '');
                btn.innerHTML = '<div class="archetype-card-ref">' + arch.reference + '</div>' +
                                '<div class="archetype-card-title">' + arch.name + '</div>';
                btn.addEventListener('click', () => {
                    engine.setArchetype(arch.id);
                    renderArchetypeCards(activeAppKey);
                    engine.render();
                    showToast('Switched layout archetype to: ' + arch.name);
                });
                archetypeGridEl.appendChild(btn);
            });
        }

        function syncRatioButtons() {
            ratioStripEl.querySelectorAll('[data-ratio]').forEach((btn) => {
                btn.classList.toggle('active', btn.getAttribute('data-ratio') === engine.aspectRatio);
            });
        }

        function syncInputsFromEngine() {
            const d = engine.data;
            document.getElementById('inpBadge').value = d.badge || '';
            document.getElementById('inpKicker').value = d.kicker || '';
            document.getElementById('inpTitle').value = d.title || '';
            document.getElementById('inpTitleSecondary').value = d.titleSecondary || '';
            document.getElementById('inpWatermark').value = d.watermarkText || '';
            document.getElementById('inpDate').value = d.date || '';
            document.getElementById('inpTime').value = d.time || '';
            document.getElementById('inpVenue').value = d.venue || '';
            document.getElementById('inpAddress').value = d.address || '';
            document.getElementById('inpHosts').value = d.hosts || '';
            document.getElementById('inpMusicBy').value = d.musicBy || '';
            document.getElementById('inpDressCode').value = d.dressCode || '';
            document.getElementById('inpAccentMetric').value = d.accentMetric || '';
            document.getElementById('inpPerks').value = Array.isArray(d.perks) ? d.perks.join(', ') : (d.perks || '');
            document.getElementById('inpRsvpContact').value = d.rsvpContact || '';
            document.getElementById('inpQrUrl').value = d.qrUrl || '';
            document.getElementById('inpFooterStrip').value = d.footerStrip || d.regulatoryNotice || '';
        }

        function pushInputsToEngine() {
            const perksRaw = document.getElementById('inpPerks').value;
            const perksList = perksRaw.split(',').map((s) => s.trim()).filter(Boolean);
            engine.updateData({
                badge: document.getElementById('inpBadge').value,
                kicker: document.getElementById('inpKicker').value,
                title: document.getElementById('inpTitle').value,
                titleSecondary: document.getElementById('inpTitleSecondary').value,
                watermarkText: document.getElementById('inpWatermark').value,
                date: document.getElementById('inpDate').value,
                time: document.getElementById('inpTime').value,
                venue: document.getElementById('inpVenue').value,
                address: document.getElementById('inpAddress').value,
                hosts: document.getElementById('inpHosts').value,
                musicBy: document.getElementById('inpMusicBy').value,
                dressCode: document.getElementById('inpDressCode').value,
                accentMetric: document.getElementById('inpAccentMetric').value,
                perks: perksList,
                rsvpContact: document.getElementById('inpRsvpContact').value,
                qrUrl: document.getElementById('inpQrUrl').value,
                footerStrip: document.getElementById('inpFooterStrip').value,
                photoMode: document.getElementById('chkGhostEcho').checked ? 'ghost_double' : 'cutout_auto',
                subjectScale: parseFloat(document.getElementById('rngSubjectScale').value) || 1.0,
                subjectOffsetY: parseInt(document.getElementById('rngSubjectOffsetY').value, 10) || 0
            });
            engine.render();
        }

        async function loadPreset(appKey, presetKey) {
            const app = CATALOG[appKey] || CATALOG.partyplatform;
            const preset = app.presets[presetKey] || Object.values(app.presets)[0];
            if (!preset) return;

            engine.setAspectRatio(preset.aspectRatio || 'story');
            engine.setTheme(preset.theme, preset.archetype);
            engine.updateData(preset.data || {});

            if (preset.backdrop_asset) {
                await engine.setBackgroundImage('/poster-studio?asset=' + preset.backdrop_asset);
            } else {
                engine.backgroundImage = null;
            }

            if (preset.subject_asset) {
                await engine.setSubjectImage('/poster-studio?asset=' + preset.subject_asset, { autoRemoveBackground: true, tolerance: 44 });
            } else if (!lastSubjectSrc) {
                engine.subjectImage = null;
                engine.processedSubjectCanvas = null;
            }

            updateSectorUi(appKey);
            syncInputsFromEngine();
            renderArchetypeCards(appKey);
            syncRatioButtons();
            engine.render();
            showToast('Loaded preset: ' + preset.label);
        }

        // Event Listeners
        presetSelectEl.addEventListener('change', (e) => {
            activePresetKey = e.target.value;
            loadPreset(activeAppKey, activePresetKey);
        });

        ratioStripEl.querySelectorAll('[data-ratio]').forEach((btn) => {
            btn.addEventListener('click', () => {
                engine.setAspectRatio(btn.getAttribute('data-ratio'));
                syncRatioButtons();
                engine.render();
                showToast('Canvas resized to ' + engine.width + '×' + engine.height + 'px');
            });
        });

        [
            'inpBadge', 'inpKicker', 'inpTitle', 'inpTitleSecondary', 'inpWatermark',
            'inpDate', 'inpTime', 'inpVenue', 'inpAddress', 'inpHosts',
            'inpMusicBy', 'inpDressCode', 'inpAccentMetric', 'inpPerks',
            'inpRsvpContact', 'inpQrUrl', 'inpFooterStrip'
        ].forEach((id) => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', pushInputsToEngine);
            }
        });

        document.getElementById('chkGhostEcho').addEventListener('change', pushInputsToEngine);
        document.getElementById('rngSubjectScale').addEventListener('input', (e) => {
            document.getElementById('lblScaleVal').textContent = Math.round(parseFloat(e.target.value) * 100) + '%';
            pushInputsToEngine();
        });
        document.getElementById('rngSubjectOffsetY').addEventListener('input', pushInputsToEngine);

        // Subject Photo Upload with Smart Background Removal
        let lastSubjectSrc = null;
        async function reloadSubjectPhoto() {
            if (!lastSubjectSrc) return;
            const autoCutout = document.getElementById('chkAutoCutout').checked;
            const bgFade = (parseInt(document.getElementById('rngSubjectBgFade').value, 10) || 0) / 100;
            await engine.setSubjectImage(lastSubjectSrc, { autoRemoveBackground: autoCutout, tolerance: 44, backgroundFade: bgFade });
            engine.render();
            showToast(bgFade > 0 ? 'Photo background ' + Math.round(bgFade * 100) + '% transparent' : (autoCutout ? 'Subject isolated with Smart Background Cutout' : 'Subject photo updated'));
        }

        document.getElementById('chkAutoCutout').addEventListener('change', reloadSubjectPhoto);
        // Photo background transparency: update the label while dragging, reprocess on release.
        document.getElementById('rngSubjectBgFade').addEventListener('input', (e) => {
            document.getElementById('lblBgFadeVal').textContent = e.target.value + '%';
        });
        document.getElementById('rngSubjectBgFade').addEventListener('change', () => {
            if (!lastSubjectSrc) { showToast('Upload a photo first — this fades the background behind the person'); return; }
            reloadSubjectPhoto();
        });

        document.getElementById('subjectUploadInput').addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (ev) => {
                lastSubjectSrc = ev.target.result;
                await reloadSubjectPhoto();
            };
            reader.readAsDataURL(file);
        });

        document.getElementById('bgUploadInput').addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (ev) => {
                await engine.setBackgroundImage(ev.target.result);
                engine.render();
                showToast('Custom backdrop texture applied');
            };
            reader.readAsDataURL(file);
        });

        // Action Buttons with Spinners (UI/UX v2.0 4-State Verification)
        function withButtonSpinner(btn, taskFn) {
            btn.classList.add('is-loading');
            Promise.resolve()
                .then(taskFn)
                .finally(() => {
                    setTimeout(() => btn.classList.remove('is-loading'), 260);
                });
        }

        document.getElementById('btnDownloadPng').addEventListener('click', function() {
            const btn = this;
            withButtonSpinner(btn, () => {
                const dataUrl = engine.toDataURL('image/png', 1.0);
                const link = document.createElement('a');
                link.download = activeAppKey + '-' + engine.archetype + '-' + Date.now() + '.png';
                link.href = dataUrl;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showToast('High-resolution PNG poster downloaded (' + engine.width + '×' + engine.height + 'px)');
            });
        });

        document.getElementById('btnShareFlyer').addEventListener('click', function() {
            const btn = this;
            withButtonSpinner(btn, () => new Promise((resolve) => {
                engine.toBlob(async (blob) => {
                    try {
                        const file = new File([blob], activeAppKey + '-flyer.png', { type: 'image/png' });
                        if (navigator.canShare && navigator.canShare({ files: [file] })) {
                            await navigator.share({
                                title: engine.data.title || 'Event Flyer',
                                text: (engine.data.title || '') + ' — ' + (engine.data.venue || ''),
                                files: [file]
                            });
                            showToast('Shared via native device share sheet');
                        } else {
                            const text = encodeURIComponent((engine.data.title || 'Invitation') + ' at ' + (engine.data.venue || '') + ' — ' + (engine.data.qrUrl || window.location.href));
                            window.open('https://wa.me/?text=' + text, '_blank', 'noopener');
                            showToast('Opened WhatsApp share link');
                        }
                    } catch (err) {
                        showToast('Share cancelled or completed');
                    }
                    resolve();
                });
            }));
        });

        document.getElementById('btnServerRenderVerify').addEventListener('click', function() {
            const btn = this;
            withButtonSpinner(btn, async () => {
                try {
                    const res = await fetch('/api/poster-studio/render', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            app: activeAppKey,
                            theme: engine.theme,
                            aspect_ratio: engine.aspectRatio,
                            data: engine.data
                        })
                    });
                    const json = await res.json();
                    if (json && json.status === 'success') {
                        showToast('Server-side GD Poster synthesised (' + json.width + '×' + json.height + 'px • ' + json.archetype + ')');
                    } else {
                        showToast('Server render response received');
                    }
                } catch (err) {
                    showToast('Client-side canvas active (Offline-ready)');
                }
            });
        });

        document.getElementById('btnResetPreset').addEventListener('click', () => {
            lastSubjectSrc = null;
            engine.subjectImage = null;
            engine.processedSubjectCanvas = null;
            engine.backgroundImage = null;
            loadPreset(activeAppKey, activePresetKey);
        });

        // Living Guide Modal
        const guideModal = document.getElementById('livingGuideModal');
        document.getElementById('btnOpenLivingGuide').addEventListener('click', () => guideModal.classList.add('open'));
        document.getElementById('btnCloseLivingGuide').addEventListener('click', () => guideModal.classList.remove('open'));
        guideModal.addEventListener('click', (e) => {
            if (e.target === guideModal) guideModal.classList.remove('open');
        });

        // Boot Studio (Sovereign Single-App Isolation)
        populatePresetsForApp(activeAppKey);
        loadPreset(activeAppKey, activePresetKey);

        // Preload photorealistic studio backdrops, portrait cutouts & foreground florals
        if (typeof engine.preloadStudioAssets === 'function') {
            engine.preloadStudioAssets('/poster-studio?asset=').then(() => {
                engine.render();
            });
        }
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => {
                engine.render();
            });
        }
    })();
    </script>
</body>
</html>
HTML;
    }
}
