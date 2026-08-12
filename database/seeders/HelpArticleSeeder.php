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
    public function run(): void
    {
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
            // ── Cancellation ────────────────────────────────────────────────
            [
                'slug'     => 'cancellation-flexible',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'cancellation',
                'title'    => 'Flexible cancellation policy',
                'summary'  => 'Full refund if you cancel at least 24 hours before check-in. No refund within 24 hours.',
                'body'     => "Bookings on the Flexible policy refund the full nightly rate and cleaning fee when you cancel at least 24 hours before the local check-in time at the property.\n\nWithin the 24-hour window, the booking is non-refundable apart from any taxes that the destination authority requires us to return.\n\nThe Vaytoven service fee is non-refundable in all cases. Refunds clear back to the original payment method within 5–10 business days depending on your bank.",
                'search_keywords' => 'cancel, refund, flexible, 24 hours, change of plans',
            ],
            [
                'slug'     => 'cancellation-moderate',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'cancellation',
                'title'    => 'Moderate cancellation policy',
                'summary'  => 'Full refund if you cancel 5+ days before check-in. 50% between 5 days and 24 hours. No refund within 24 hours.',
                'body'     => "Moderate is the default policy on most managed listings. Cancel at least 5 days before check-in for a full refund of the nightly rate and cleaning fee. Cancel between 5 days and 24 hours of check-in and you receive 50% of the nightly rate back; the cleaning fee is fully refunded.\n\nWithin 24 hours of check-in, the booking is non-refundable apart from required tax returns.\n\nThe Vaytoven service fee is non-refundable in all cases.",
                'search_keywords' => 'cancel, refund, moderate, 50 percent, partial refund',
            ],
            [
                'slug'     => 'cancellation-strict',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'cancellation',
                'title'    => 'Strict cancellation policy',
                'summary'  => '50% refund if you cancel 7+ days before check-in. No refund within 7 days.',
                'body'     => "Strict applies to peak-season inventory and high-value properties. You get 50% of the nightly rate refunded when you cancel at least 7 days before check-in; the cleaning fee is also refunded.\n\nWithin 7 days of check-in, the booking is non-refundable.\n\nThe Vaytoven service fee is non-refundable in all cases. We always show the policy on the booking page before you confirm — if you don't see Strict listed, this rule does not apply to your stay.",
                'search_keywords' => 'cancel, refund, strict, peak season, premium',
            ],

            // ── Fees & payouts ──────────────────────────────────────────────
            [
                'slug'     => 'service-fee',
                'audience' => HelpAudience::All->value,
                'category' => 'fees',
                'title'    => 'Vaytoven service fee',
                'summary'  => 'A flat 3% service fee added at checkout. Funds platform operations, fraud prevention, and 24/7 support.',
                'body'     => "Travelers pay a 3% service fee at checkout, calculated on the subtotal of nightly rates and cleaning fees. The fee is shown clearly on the price breakdown — there are no surprise charges.\n\nThe service fee is non-refundable in all cancellation scenarios. It funds platform operations including payments infrastructure, fraud prevention, dispute handling, and our 24/7 support agent.",
                'search_keywords' => 'fee, service fee, 3 percent, surcharge, hidden fees',
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
                'slug'     => 'refund-timing',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'payouts',
                'title'    => 'When will my refund arrive?',
                'summary'  => 'Once approved, refunds clear back to the original card in 5–10 business days. Bank transfers can take longer.',
                'body'     => "Refunds are issued back to the card or account that was charged. We initiate the refund the moment the cancellation is approved; from there it's the issuing bank's clearing schedule that determines when the funds appear.\n\nVisa and Mastercard refunds typically post within 5–7 business days. Some debit cards and bank transfers take up to 10 business days. If 10 business days have passed and you still don't see the refund, contact support and we'll trace it with the processor.",
                'search_keywords' => 'refund, when will, how long, money back, where is',
            ],

            // ── Booking & travel ────────────────────────────────────────────
            [
                'slug'     => 'how-to-book',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'booking',
                'title'    => 'How to book a stay',
                'summary'  => 'Search, pick dates, confirm guests, pay. The host has 24 hours to accept; charge captures on acceptance.',
                'body'     => "Use the search on the home page or any destination page. Pick your dates and guest count, review the price breakdown, then confirm.\n\nFor instant-book listings, your card is authorised immediately and you receive a confirmation code (format VYT-XXXXXX). For request-to-book listings, the host has 24 hours to accept; we authorise but don't capture your card until they confirm. If they decline or 24 hours elapse, the authorisation is voided and no charge appears on your statement.\n\nYou can see all your bookings under My Trips.",
                'search_keywords' => 'how to book, reservation, confirmation, instant book, request to book',
            ],
            [
                'slug'     => 'modify-booking',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'booking',
                'title'    => 'Changing dates or guest count',
                'summary'  => 'Both sides must agree to a change. We re-quote the price and only charge or refund the difference.',
                'body'     => "Open the booking under My Trips and use Request a change. Pick your new dates or guest count and submit. The host sees the request and can accept or decline within 24 hours.\n\nIf they accept, we re-quote the booking against current availability and rates. We capture the difference if the new total is higher, and refund the difference if it's lower. Service fees adjust pro-rata.\n\nIf either side wants to cancel rather than modify, the active cancellation policy applies — the modification request itself doesn't lock you in.",
                'search_keywords' => 'modify, change dates, change booking, edit reservation, add guest',
            ],
            [
                'slug'     => 'whats-included',
                'audience' => HelpAudience::Traveler->value,
                'category' => 'booking',
                'title'    => "What's included in my stay",
                'summary'  => 'Linens, towels, basic toiletries, and Wi-Fi are standard. Each listing notes anything additional or excluded.',
                'body'     => "Every Vaytoven property is required to provide clean linens, fresh towels, basic bathroom toiletries, hand soap and dish soap, paper goods, and Wi-Fi. Listings note anything beyond that — pool, hot tub, parking, breakfast — under Amenities.\n\nIf something the listing claims is missing or non-functional on arrival, take a photo and message your host through the booking thread within 24 hours. If they don't resolve it, escalate to Vaytoven Support and we'll either get it fixed, partially refund, or relocate you.",
                'search_keywords' => 'amenities, included, linens, wifi, what comes with',
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
                'slug'     => 'damage-cover',
                'audience' => HelpAudience::Host->value,
                'category' => 'hosting',
                'title'    => 'Damage cover for hosts',
                'summary'  => 'Up to $250,000 per stay, baked into every booking. No separate purchase, no deductible on the first claim.',
                'body'     => "Every confirmed stay is covered up to \$250,000 USD for accidental damage caused by guests. The cover is included in the host fee — there's no add-on to buy and no per-booking surcharge.\n\nTo make a claim, document the damage with photos and a description in your host dashboard within 14 days of guest checkout. We adjudicate and pay verified claims within 30 days. The first claim per calendar year has no deductible; subsequent claims have a \$250 deductible.\n\nIntentional damage and theft are handled separately under our Trust & Safety process — contact support immediately rather than filing a damage claim.",
                'search_keywords' => 'damage, insurance, cover, claim, broken, theft',
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
