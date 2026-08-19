<?php

/**
 * The convention centers featured on /event-centers.
 *
 * Config rather than a database table because this is a curated editorial list
 * of five places, not member data. Nobody administers it, nothing writes to it,
 * and a migration and an admin screen for five rows that change once a year
 * would be more machinery than the feature is.
 *
 * `calendar_url` points at each venue's own published calendar. Vaytoven does
 * not host, mirror or scrape those listings — the venue is the authority on its
 * own schedule, and a copied calendar is wrong the moment an event moves. Every
 * URL below was requested and confirmed to return the venue's real events page;
 * `www.lvcc.com` in particular is a Las Vegas cigar retailer and is NOT the
 * convention center, which is why the Las Vegas entry points at the LVCVA
 * destination calendar instead.
 *
 * `search` is what "Explore properties nearby" puts into the property search.
 * It matches on city, which is how listings are located, so the button lands on
 * real results rather than on an empty filtered page. That is the whole reason
 * this section exists rather than being a page of outbound links: someone
 * looking at a convention in Orlando should be one click from Orlando
 * advertisements.
 */
return [

    [
        'slug'         => 'mccormick-place',
        'name'         => 'McCormick Place',
        'city'         => 'Chicago',
        'region'       => 'Illinois',
        'blurb'        => "America's largest convention center, hosting major national and international events throughout the year.",
        'calendar_url' => 'https://www.mccormickplace.com/events/',
        'search'       => ['city' => 'Chicago'],
    ],

    [
        'slug'         => 'orange-county-convention-center',
        'name'         => 'Orange County Convention Center',
        'city'         => 'Orlando',
        'region'       => 'Florida',
        'blurb'        => "One of America's largest convention facilities, with approximately 7 million square feet across its campus.",
        'calendar_url' => 'https://events.occc.net/',
        'search'       => ['city' => 'Orlando'],
    ],

    [
        'slug'         => 'las-vegas-convention-center',
        'name'         => 'Las Vegas Convention Center',
        'city'         => 'Las Vegas',
        'region'       => 'Nevada',
        'blurb'        => 'A major Las Vegas convention destination for large-scale trade shows, conferences and exhibitions.',
        'calendar_url' => 'https://www.vegasmeansbusiness.com/destination-calendar/',
        'search'       => ['city' => 'Las Vegas'],
    ],

    [
        'slug'         => 'georgia-world-congress-center',
        'name'         => 'Georgia World Congress Center',
        'city'         => 'Atlanta',
        'region'       => 'Georgia',
        'blurb'        => "One of the country's largest convention complexes and a major destination for national events.",
        'calendar_url' => 'https://www.gwcc.com/calendar/',
        'search'       => ['city' => 'Atlanta'],
    ],

    [
        'slug'         => 'javits-center',
        'name'         => 'Javits Center',
        'city'         => 'New York',
        'region'       => 'New York',
        'blurb'        => 'Major Manhattan convention destination with a continuously updated calendar of trade shows, conferences, expos and consumer events.',
        'calendar_url' => 'https://www.javitscenter.com/en/events',
        'search'       => ['city' => 'New York'],
    ],

];
