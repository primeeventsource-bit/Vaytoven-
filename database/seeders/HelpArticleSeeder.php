<?php

namespace Database\Seeders;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * Curated launch library for the Vaytoven help center (FR-11.5).
 *
 * Articles are upserted by slug so this seeder is idempotent — re-running it
 * after copy edits will overwrite without leaving orphan rows. Each article
 * is short on purpose: the support chat agent quotes summaries verbatim, so
 * vagueness here turns into vagueness in customer answers.
 *
 * Coverage targets every audience and the most common policy questions:
 *   - Cancellation (3): flexible / moderate / strict
 *   - Fees & payouts (3): service fee / host payouts / refunds timing
 *   - Booking & travel (3): how to book / modify a booking / what's included
 *   - Hosting (3): become a host / damage cover / listing requirements
 *   - Members (3): program overview / eligible clubs / payouts
 */
class HelpArticleSeeder extends Seeder
{
    /**
     * Articles that must NOT exist any more.
     *
     * Upserting by slug is idempotent for content that stays, but it has no
     * opinion about content that leaves — deleting an entry from articles()
     * below leaves the published row untouched in every environment that has
     * already been seeded. These nine documented the booking product: three
     * cancellation policies, a 3% checkout service fee, card refund timings,
     * how to book, how to modify a booking, what's included in a stay, and
     * $250,000 of damage cover. All of them described a company that takes
     * reservations and holds guests' money.
     *
     * Deleted rather than unpublished so the support assistant cannot quote
     * them either — it searches the table, not the public index.
     */
    private const RETIRED_SLUGS = [
        'cancellation-flexible',
        'cancellation-moderate',
        'cancellation-strict',
        'service-fee',
        'refund-timing',
        'how-to-book',
        'modify-booking',
        'whats-included',
        'damage-cover',
    ];

    public function run(): void
    {
        HelpArticle::whereIn('slug', self::RETIRED_SLUGS)->delete();

        foreach ($this->articles() as $i => $article) {
            HelpArticle::updateOrCreate(
                ['slug' => $article['slug']],
                $article + ['sort_order' => $i, 'is_published' => true],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            // ── What Vaytoven is ────────────────────────────────────────────
            [
                'slug'     => 'what-vaytoven-is',
                'audience' => HelpAudience::All->value,
                'category' => 'basics',
                'title'    => 'What Vaytoven does, and what it does not',
                'summary'  => 'We advertise vacation properties. We do not take reservations, collect payment for a stay, or hold anyone\'s money.',
                'body'     => "Vaytoven is a software-as-a-service advertising and marketing platform for vacation property owners. Owners pay us to list and promote their property. That is the whole of our role.\n\nWe are not a booking platform, a travel agency, an escrow provider, or a property manager. You cannot reserve a property through Vaytoven, and Vaytoven never charges you for a stay.\n\nWhat you can do is submit an offer on a listing. If the listing member accepts it, you and they arrange the dates, the payment method and the terms directly between yourselves. No rental money passes through us at any point.\n\nThe only money Vaytoven collects is what property owners pay us for advertising, listing and subscription services.",
                'search_keywords' => 'what is vaytoven, booking, reservation, book, how does it work, advertising',
            ],

            // ── Offers ──────────────────────────────────────────────────────
            [
                'slug'     => 'how-offers-work',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'offers',
                'title'    => 'How to submit an offer',
                'summary'  => 'Pick your dates, name your price, submit. The listing member has 24 hours to respond. Nothing is charged.',
                'body'     => "Open any listing and use Submit Offer. Choose your dates and party size, enter the amount you want to offer, and add a message if it helps your case.\n\nSubmitting an offer is not a reservation and does not hold the dates. It does not charge you, and Vaytoven never asks you for card details to make one.\n\nThe listing member sees your offer on their dashboard with the dates, party size and amount. They can accept or decline. Every offer expires 24 hours after it is submitted, so you are never left waiting indefinitely — if nothing happens, it lapses and you are free to look elsewhere.\n\nYou can see every offer you have sent, and its current status, on your dashboard.",
                'search_keywords' => 'offer, submit offer, how to, bid, make an offer, reserve, book',
            ],
            [
                'slug'     => 'offer-accepted',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'offers',
                'title'    => 'My offer was accepted — what happens now?',
                'summary'  => 'You and the listing member arrange the stay and payment directly. Vaytoven is not part of that transaction.',
                'body'     => "An accepted offer means the listing member is willing to proceed on the dates and amount you proposed. It is the start of a conversation between the two of you, not a confirmed reservation held by Vaytoven.\n\nFrom there you agree directly with them: how and when you pay, what deposit if any, what happens if either side needs to cancel, check-in arrangements, and anything else about the stay. Those terms are theirs to set and yours to accept.\n\nVaytoven does not take the payment, hold a deposit, guarantee the stay, or issue refunds — we are not a party to your agreement. Treat the arrangement as you would any direct booking with a property owner, and keep your own record of what was agreed.\n\nIf you cannot reach the listing member, raise a Trip Support request and we will help you make contact.",
                'search_keywords' => 'accepted, offer accepted, next steps, pay, deposit, confirm',
            ],
            [
                'slug'     => 'host-payouts',
                'audience' => HelpAudience::Host->value,
                'category' => 'payouts',
                'title'    => 'How hosts get paid',
                'summary'  => 'Guests pay you directly. Vaytoven advertises your listing and never handles rental money.',
                'body'     => "Guests pay you directly. Vaytoven is an advertising and marketing platform — it does not take reservations, collect rental payments, hold funds in escrow, or pay hosts out.\n\nWhen a traveler submits an offer on your listing you review it on your dashboard and accept or decline. If you accept, you agree the payment method, deposit, timing and cancellation terms with the guest yourself, on whatever terms you choose.\n\nThat means Vaytoven never asks for your bank account, routing number, government ID or tax forms. If anyone claiming to be from Vaytoven asks you for those, do not send them — contact us instead.\n\nThe only money that moves through Vaytoven is what you pay us for advertising, listing and subscription services. That is billed to you and is quoted before you commit.",
                'search_keywords' => 'payout, payment, when paid, how do i get paid, bank, advertising fee',
            ],
            [
                'slug'     => 'who-handles-refunds',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'offers',
                'title'    => 'Cancellations and refunds',
                'summary'  => 'Whoever took your payment handles it. Vaytoven never charged you for the stay, so it cannot refund one.',
                'body'     => "Because you pay the listing member directly, cancellation and refund terms are whatever you agreed with them. Vaytoven never took the money, so there is nothing here for us to refund and no cancellation policy of ours that applies to your stay.\n\nAgree the cancellation terms in writing before you pay anything. What happens if you cancel, what happens if they cancel, and what is refundable are all worth settling up front.\n\nIf you cannot reach the listing member or you believe a listing misrepresented the property, raise a Trip Support request. We keep a record of the offer and the correspondence, and we will help you make contact — but we cannot issue a refund on their behalf.",
                'search_keywords' => 'refund, cancel, cancellation, money back, dispute, where is my refund',
            ],

            // ── Hosting ─────────────────────────────────────────────────────
            [
                'slug'     => 'become-a-host',
                'audience' => HelpAudience::Host->value,
                'category' => 'hosting',
                'title'    => 'Becoming a Vaytoven host',
                'summary'  => 'Submit your property details, agree an advertising package, then our concierge handles photos, copy, and pricing. Live in 7–14 days.',
                'body'     => "Submit your details at /host/onboarding, or use the host enquiry button on the home page. We screen for property type, location, and ownership before onboarding.\n\nOnce approved, we agree your advertising package. Our listing concierge then arranges photography, writes the listing copy, and suggests pricing based on local comps — you set the actual rates. You review and approve before going live.\n\nMost properties go live within 7–14 days. Travelers then submit offers and inquiries on your dates, which you accept or decline from your dashboard; you arrange the stay and payment with the guest directly.\n\nVaytoven charges you for advertising and platform access, and never asks for banking details, government ID or tax forms.",
                'search_keywords' => 'host, list property, become host, host application, listing concierge',
            ],
            [
                'slug'     => 'host-protecting-yourself',
                'audience' => HelpAudience::Host->value,
                'category' => 'hosting',
                'title'    => 'Damage, deposits and protecting yourself',
                'summary'  => 'Vaytoven provides no damage cover or insurance. You set your own deposit and terms with each guest.',
                'body'     => "Vaytoven advertises your listing. It does not insure your property, provide damage cover, hold a security deposit, or adjudicate a claim between you and a guest — we are not a party to the arrangement you make.\n\nThat means the protections are yours to put in place. Most owners agree a security deposit and written terms with the guest before handing over access, and carry short-term-rental insurance of their own. Your existing homeowner's policy usually does not cover paying guests; check with your insurer rather than assuming.\n\nIf your property is in a vacation club or managed resort, the club may have its own guest-damage process. Ask them what applies before you advertise a week.\n\nIf a guest found through Vaytoven behaves in a way other owners should know about, tell us — we act on listings and accounts that abuse the platform even though we are not part of the transaction.",
                'search_keywords' => 'damage, insurance, cover, claim, deposit, broken, theft, protection',
            ],
            [
                'slug'     => 'listing-requirements',
                'audience' => HelpAudience::Host->value,
                'category' => 'hosting',
                'title'    => 'Listing requirements',
                'summary'  => 'Smoke + CO detectors, fire extinguisher, basic first aid, and listing-quality photos. We help with the rest.',
                'body'     => "Every listing must have working smoke detectors and carbon monoxide detectors on each level, a fire extinguisher in or near the kitchen, and a basic first-aid kit. Hosts attest to these annually.\n\nPhotos must be daylight, in-focus, and represent the actual unit. Our concierge can shoot for you in supported metros at no charge to you on your first listing.\n\nLocal short-term rental permits are the host's responsibility. We'll surface the rule for your address during onboarding, but we don't apply for permits on your behalf.",
                'search_keywords' => 'requirements, smoke detector, photos, permit, what do I need',
            ],

            // ── Members program ────────────────────────────────────────────
            [
                'slug'     => 'managed-program-overview',
                'audience' => HelpAudience::Member->value,
                'category' => 'members',
                'title'    => 'How the managed program works',
                'summary'  => 'We advertise your unused weeks. You pay an upfront weekly program cost ($200–$800 + tax) plus a subscription fee; guests pay you directly.',
                'body'     => "The Managed Listing Program is designed for owners of points-based vacation property — Marriott, Hilton, Disney, Wyndham, RCI, Interval — who don't use every week they're entitled to.\n\nA specialist contacts you within one business day of your enquiry. We confirm your program rules, audit which weeks are eligible to advertise, and quote the cost. We then build the listings and advertise them across our network and partner channels.\n\nThe program is priced as an upfront weekly program cost in the range of \$200–\$800 per week (varies by property tier and season) plus applicable taxes, plus a flat program subscription fee. We quote the exact figures for your specific portfolio on the onboarding call before you commit to anything.\n\nThose fees are the only amounts Vaytoven charges, and they are the only money that moves through Vaytoven. When a traveler makes an offer you accept, the guest pays you directly and you agree the terms with them. Vaytoven does not hold funds in escrow, does not take a percentage of what you earn, and does not pay you out.\n\nYou remain responsible for confirming that renting your weeks is permitted under your club's rules.",
                'search_keywords' => 'members, managed program, points, vacation club, marriott, hilton, disney',
            ],
            [
                'slug'     => 'eligible-clubs',
                'audience' => HelpAudience::Member->value,
                'category' => 'members',
                'title'    => 'Which clubs and programs are eligible',
                'summary'  => 'Most major points-based programs work. Fixed-week and right-to-use systems are case by case.',
                'body'     => "We currently onboard members from Marriott Vacation Club, Hilton Grand Vacations, Disney Vacation Club, Wyndham Destinations, RCI Points, and Interval International. If you have points across multiple clubs, mention all of them in your enquiry — we can manage them in one program.\n\nFixed-week and right-to-use systems are reviewed case by case. Some programs explicitly forbid managed rental, in which case we'll tell you up front rather than risk your membership. Compliance with your club's rental rules is non-negotiable on our side.",
                'search_keywords' => 'eligible, clubs, marriott, hilton, disney, wyndham, rci, interval',
            ],
            [
                'slug'     => 'member-payouts',
                'audience' => HelpAudience::Member->value,
                'category' => 'members',
                'title'    => 'How members get paid',
                'summary'  => 'Guests pay you directly. Vaytoven advertises your weeks and charges only for advertising and your subscription.',
                'body'     => "The same way hosts do: the guest pays you directly. Vaytoven advertises your unused weeks — it does not rent them, take the booking, hold funds in escrow, or pay you out.\n\nWhen a traveler submits an offer on one of your weeks, you see it on your member dashboard with the dates, party size, amount offered and when it expires. If you accept, you agree the payment and terms with the guest yourself.\n\nWhat you pay Vaytoven is separate and goes the other way: the upfront weekly program cost (\$200–\$800 per week + applicable tax, varying by property tier and season) and the flat program subscription fee. Both are quoted in writing on your onboarding call before you commit, and they are the only amounts Vaytoven charges — there is no percentage of your rental income.\n\nVaytoven will never ask for your banking details, government ID or tax forms.",
                'search_keywords' => 'when paid, payout, members payout, how do i get paid, advertising fee, subscription',
            ],
        ];
    }
}
