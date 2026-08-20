{{--
    The staff training guide.

    Rendered by dompdf, which supports a conservative subset of CSS: no flexbox,
    no grid, no custom fonts without registration. Tables and floats are what
    survive, so the layout below uses them deliberately rather than out of
    habit — a stylesheet written for the browser silently collapses to a single
    column here, and nobody notices until it is printed.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vaytoven Staff Guide</title>
    <style>
        @page { margin: 46px 44px 54px; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2430; line-height: 1.55; }

        h1 { font-size: 26px; margin: 0 0 6px; color: #7b2cbf; }
        h2 {
            font-size: 15px; margin: 26px 0 10px; padding-bottom: 5px;
            border-bottom: 2px solid #d63384; color: #7b2cbf;
            page-break-after: avoid;
        }
        h3 { font-size: 11.5px; margin: 16px 0 5px; color: #1f2430; page-break-after: avoid; }
        p  { margin: 0 0 9px; }

        .lead { font-size: 11.5px; color: #444b5a; }
        .meta { font-size: 9px; color: #767e8f; margin-top: 4px; }

        .cover { border-bottom: 3px solid #d63384; padding-bottom: 16px; margin-bottom: 22px; }

        table { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
        th, td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid #e6e8ee; }
        th { background: #faf7ff; font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #59617a; }
        td { font-size: 10px; }

        .k { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; color: #59617a; }

        .callout {
            background: #fdf2f8; border-left: 3px solid #d63384;
            padding: 9px 12px; margin: 10px 0 14px; font-size: 10px;
        }
        .callout strong { color: #9d174d; }

        .warn {
            background: #fef2f2; border-left: 3px solid #dc2626;
            padding: 9px 12px; margin: 10px 0 14px; font-size: 10px;
        }
        .warn strong { color: #991b1b; }

        ul { margin: 0 0 10px; padding-left: 16px; }
        li { margin-bottom: 4px; }

        .break { page-break-before: always; }
        .tag {
            display: inline-block; background: #f3eaff; color: #7b2cbf;
            padding: 1px 6px; border-radius: 8px; font-size: 8.5px; margin: 0 3px 3px 0;
        }
    </style>
</head>
<body>

<div class="cover">
    <h1>Vaytoven Staff Guide</h1>
    <p class="lead">How the site works, and how to run it.</p>
    <p class="meta">
        Generated {{ et($generatedAt, 'F j, Y \a\t g:ia') }} ·
        Reflects the system as configured on this environment at that moment.
    </p>
</div>

<h2>1. What Vaytoven is — and what it is not</h2>

<p>
    Vaytoven is an <strong>advertising platform for vacation properties</strong>. Owners pay
    Vaytoven to advertise a property. Travelers browse those advertisements and contact the
    owner about weeks they are interested in.
</p>

<div class="warn">
    <strong>Vaytoven is not a booking site.</strong> We do not take reservations, hold dates,
    collect rental money, or pass funds between travelers and owners. There is no checkout for
    a stay and no payout to an owner — not hidden, not disabled, not anywhere. The only money
    Vaytoven ever collects is its own advertising fee, from the owner.
    <br><br>
    This matters in every conversation with a customer. If somebody believes they have booked
    something through Vaytoven, that belief is wrong and correcting it early prevents a dispute
    later. What they have is an <em>offer</em> — a message about a week — which they and the
    owner settle directly between themselves.
</div>

<h3>The three audiences</h3>
<table>
    <tr><th style="width:22%;">Who</th><th>What they do</th></tr>
    <tr><td><strong>Owners / hosts</strong></td><td>Pay for a Member Services package and have their property advertised.</td></tr>
    <tr><td><strong>Travelers</strong></td><td>Browse advertisements, save the ones they like, and submit offers on weeks.</td></tr>
    <tr><td><strong>Staff</strong></td><td>Build and publish listings, answer inquiries, take Member Services payments, and keep the records straight.</td></tr>
</table>

<h2>2. Getting in, and what you can see</h2>

<p>
    Sign in at <span class="k">/login</span>. The admin area is at <span class="k">/admin</span>.
    What you can see there depends entirely on your <strong>role</strong> — if a tab is missing,
    you do not hold the permission next to it in the table below. That is a configuration answer,
    not a fault; ask an administrator rather than working around it.
</p>

<p>
    On first sign-in you will be asked to change your password and to accept the current terms.
    Both are required and neither can be skipped.
</p>

<h3>The admin tabs</h3>
<table>
    <tr>
        <th style="width:19%;">Tab</th>
        <th style="width:19%;">Needs</th>
        <th>What it is for</th>
    </tr>
    @foreach ($navigation as $tab)
        <tr>
            <td><strong>{{ $tab['label'] }}</strong></td>
            <td><span class="k">{{ $tab['permission'] }}</span></td>
            <td>{{ $tab['purpose'] }}</td>
        </tr>
    @endforeach
</table>

<h2 class="break">3. Roles and permissions</h2>

<p>
    A role is a named bundle of permissions. Changing a role changes it for everybody who holds
    it, straight away — there is no review step and no undo, so check who is affected first.
</p>

@if ($roles->isEmpty())
    <div class="warn">
        <strong>No roles are configured on this environment.</strong>
        Permissions have not been seeded here, so this table is empty. Run the RBAC seeder before
        relying on role-based access on this environment.
    </div>
@else
    <table>
        <tr><th style="width:23%;">Role</th><th>Can do</th></tr>
        @foreach ($roles as $role)
            <tr>
                <td>
                    <strong>{{ $role['name'] }}</strong>
                    <br><span class="k">{{ $role['key'] }}</span>
                </td>
                <td>
                    @forelse ($role['permissions'] as $permission)
                        <span class="tag">{{ $permission }}</span>
                    @empty
                        <em>No permissions. This role can sign in and see nothing else.</em>
                    @endforelse
                </td>
            </tr>
        @endforeach
    </table>
@endif

<div class="callout">
    <strong>Owning something is not the same as having permission.</strong>
    Hosts hold <span class="k">properties.edit</span> so they can maintain their own listing.
    That permission alone does not let them touch anybody else's — every listing screen also
    checks that you are either staff or the owner of that particular property.
</div>

<h2>4. Building a listing</h2>

<p>
    Listings live under the <strong>Listings</strong> tab. Open one and you get the builder,
    which is one page with sections down it. Each section saves on its own, so you can do the
    basics now and the photos later without losing anything.
</p>

<h3>The lifecycle</h3>
<table>
    <tr><th style="width:18%;">Stage</th><th>What it means</th></tr>
    @foreach ($listingStages as $stage)
        <tr>
            <td><strong>{{ $stage['label'] }}</strong></td>
            <td>{{ $stage['guidance'] }}</td>
        </tr>
    @endforeach
</table>

<h3>Photos</h3>
<p>
    Drag files onto the drop zone or choose them. Large images are resized and converted for the
    web automatically, and the original you upload is kept untouched. Uploading strips the
    embedded camera data, which routinely includes the property's GPS coordinates — that removal
    is deliberate and must not be worked around.
</p>
<ul>
    <li><strong>Reorder</strong> by dragging, then press <em>Save photo order</em>. Nothing is saved until you do, so a misdrag is undone by not pressing it.</li>
    <li><strong>Cover</strong> is the photo used on search cards. Set it deliberately; otherwise the first one is used.</li>
    <li><strong>Rotate &amp; crop</strong> is under each photo. Rotating clears the crop, because a box drawn on an upright photo covers a different part of it once it is on its side. <em>Reset to original</em> always works — every edit is replayed against the untouched upload, so nothing is ever lost by experimenting.</li>
    <li><strong>Alt text</strong> is what a screen reader announces. Write it.</li>
</ul>

<p>Sections a photo can be filed under:</p>
<p>
    @foreach ($photoSections as $label)
        <span class="tag">{{ $label }}</span>
    @endforeach
</p>

<h3>Availability</h3>
<p>
    Weeks are added to the calendar and each carries a state. A listing cannot go live without at
    least one upcoming available week — an advertisement with nothing on offer wastes the
    traveler's time and the owner's money.
</p>
<table>
    <tr><th style="width:18%;">State</th><th>Meaning</th></tr>
    @foreach ($weekStates as $state)
        <tr><td><strong>{{ $state['label'] }}</strong></td><td>{{ $state['guidance'] }}</td></tr>
    @endforeach
</table>

<div class="callout">
    <strong>Before a listing can be published</strong> it needs a title, a description, a city,
    at least one photo and at least one upcoming available week. The builder tells you exactly
    which of those are missing — you do not have to remember this list.
</div>

<h2 class="break">5. Offers</h2>

<p>
    A traveler submits an offer against a specific advertised week. It is a message and a
    number, not a reservation. When one arrives the week moves to <em>Offer pending</em> and
    stops accepting new offers, so the member is not left comparing several bids for the same
    nights that arrived while they were deciding. It stays advertised.
</p>

<p>
    The member accepts or declines from their own dashboard. Staff can see offers under the
    <strong>Offers</strong> tab. Nobody at Vaytoven agrees terms on a member's behalf.
</p>

<h2>6. Member Services — the money</h2>

<p>
    This is the only place money changes hands. An owner buys a package for a number of weeks;
    the total is <strong>package price × number of weeks</strong> and it is fixed at the moment
    the order is created.
</p>

<table>
    <tr><th style="width:18%;">Package</th><th>Positioning</th></tr>
    @foreach ($packages as $package)
        <tr>
            <td><strong>{{ $package->label() }}</strong></td>
            <td>{{ $package->tagline() }}</td>
        </tr>
    @endforeach
</table>

<div class="warn">
    <strong>The customer pays, on their own device. Always.</strong>
    Staff never enter card details and never submit a payment on somebody's behalf, even if the
    customer asks. Talk them through it on the phone if they need help — that is fine and
    expected — but the card goes in on their side. Every sale is a customer-initiated online
    transaction, and that is what makes it defensible if it is ever disputed.
    <br><br>
    The amount is locked to the order. Nobody — customer or staff — can type a different figure
    into the payment page. If the amount is wrong, the order is wrong; fix the order.
    <br><br>
    Vaytoven never stores a full card number or a security code. Not in notes, not in the inbox,
    not attached to a member's file. If a customer sends one, do not paste it anywhere.
</div>

<h2>7. Contracts and documents</h2>

<p>
    Agreements are sent for signature and the signed copy comes back with a record of when it was
    opened, signed, from which address and on what device. An accepted version is never
    overwritten: what the member saw and agreed to at that moment is what is kept, permanently.
</p>

<p>Documents can be filed against a member or a property under these types:</p>
<p>
    @foreach ($documentTypes as $label)
        <span class="tag">{{ $label }}</span>
    @endforeach
</p>

<p>
    Every upload and every download is recorded with who did it. That is the point of storing
    them here rather than in an inbox.
</p>

<h2 class="break">8. The activity log</h2>

<p>
    <strong>Activity &amp; IP logs</strong> is the record of what happened on the site. It is
    the screen a dispute is answered from, so two rules govern it.
</p>

<div class="warn">
    <strong>The activity log is never editable.</strong> Not by staff, not by administrators, not
    by anybody. Rows cannot be changed or deleted once written — the database itself refuses. If
    something in it looks wrong, that is information, not a thing to correct.
</div>

<div class="callout">
    <strong>Location is approximate.</strong> Every location shown is derived from an IP address.
    It is a rough city-level estimate and must always be described that way — never as where
    somebody was. It is not a street address and it is not evidence of a person's whereabouts.
</div>

<p>
    Members see a deliberately limited view of activity on their own listings: how many people
    looked, roughly where interest came from, and when. They never see a visitor's IP address,
    address, name, email, exact coordinates, device or identity. Do not read those details out to
    a member.
</p>

<h3>What is recorded</h3>
<p>
    Meaningful events only — a page opened, a gallery viewed, an offer submitted, a payment
    approved. Not mouse movement, not keystrokes, not scrolling.
</p>

@foreach ($activityGroups as $group)
    <h3>{{ $group['label'] }}</h3>
    <p>
        @foreach ($group['types'] as $type)
            <span class="tag">{{ $type }}</span>
        @endforeach
    </p>
@endforeach

<div class="callout">
    <strong>Some events can only ever be written by the system.</strong>
    Logins, payments, contract signatures, account creation and advertisement activation are
    recorded by the server that performed them. A web browser cannot report them, by design — if
    it could, anybody could type their own history into the record and it would be worth nothing
    in a dispute.
</div>

<h2>9. Disputes and chargeback evidence</h2>

<p>
    If a customer disputes a Member Services charge, the evidence is assembled for you. Open the
    member under <strong>Users</strong>, go to <em>Documents</em>, and download the
    <strong>evidence certificate</strong>. It gathers, in one signed PDF: when the account was
    created and verified, which terms version they accepted and when, from which address, their
    sign-in history, the order and what it was for, the payment and its result, and the
    advertising that was delivered.
</p>

<p>
    Send that document as it is generated. Do not edit it, retype it, or extract parts of it into
    an email — an assembled record with an unbroken chain is what carries weight, and a reworded
    summary of one does not.
</p>

<h2>10. Things that are easy to get wrong</h2>

<ul>
    <li><strong>Saying "booking".</strong> We advertise. Travelers submit offers. Getting this wrong in writing creates the exact expectation that becomes a dispute.</li>
    <li><strong>Entering a customer's card.</strong> Never, under any circumstances, including when they ask you to.</li>
    <li><strong>Reading IP locations as fact.</strong> They are approximate. Say so every time.</li>
    <li><strong>Sharing visitor details with a member.</strong> Members get counts and rough areas, never identities.</li>
    <li><strong>Publishing a listing with one photo and no availability.</strong> It will be refused, and it should be.</li>
    <li><strong>Deleting a photo to "fix" it.</strong> Rotate, crop and reset are non-destructive. Deleting is not.</li>
    <li><strong>Changing a role to solve one person's access problem.</strong> It changes everybody with that role.</li>
</ul>

<h2>11. Who to ask</h2>

<p>
    <strong>contact@vaytoven.com</strong> &nbsp;·&nbsp; (877) 782-9868
</p>

<p class="meta">
    This guide is generated from the running system. Roles, permissions, listing stages, packages
    and activity types above are read from this environment as it is configured now — if
    something here does not match what you see on screen, the screen is right and this copy is
    out of date. Download a fresh one.
</p>

</body>
</html>
