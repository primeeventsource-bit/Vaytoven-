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
 *
 * `photo` is a photograph of that building — not of its city, and not a stock
 * convention hall. Each was checked by eye against the venue before it was
 * committed, and four of the five carry the venue's own signage. They come from
 * Wikimedia Commons rather than from the venues' media kits because a media kit
 * grants press use, while these carry a free licence with a named author. CC BY
 * and CC BY-SA require that author to be credited wherever the photo is shown,
 * which is what the credits line at the foot of the page is for: it is a licence
 * term, not decoration, so a new photo needs a new credit alongside it.
 *
 * The files live in public/images/event-centers, cropped to 16:9 at 1200px and
 * 800px for the srcset.
 */
return [

    [
        'slug' => 'mccormick-place',
        'name' => 'McCormick Place',
        'city' => 'Chicago',
        'region' => 'Illinois',
        'blurb' => "America's largest convention center, hosting major national and international events throughout the year.",
        'calendar_url' => 'https://www.mccormickplace.com/events/',
        'search' => ['city' => 'Chicago'],
        'photo' => [
            'file' => 'mccormick-place',
            'alt' => 'The glass frontage of McCormick Place in Chicago, seen from the plaza',
            'by' => 'Patrick Bray, US Army Corps of Engineers',
            'license' => 'public domain',
            'license_url' => null,
            'source' => 'https://commons.wikimedia.org/wiki/File:McCormick_Place_exterior.jpg',
        ],
    ],

    [
        'slug' => 'orange-county-convention-center',
        'name' => 'Orange County Convention Center',
        'city' => 'Orlando',
        'region' => 'Florida',
        'blurb' => "One of America's largest convention facilities, with approximately 7 million square feet across its campus.",
        'calendar_url' => 'https://events.occc.net/',
        'search' => ['city' => 'Orlando'],
        'photo' => [
            'file' => 'orange-county-convention-center',
            'alt' => 'The arched glass roof of the Orange County Convention Center in Orlando, behind palm trees',
            'by' => 'Ebyabe',
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Orlando_FL_Orange_County_Convention_Center01.jpg',
        ],
    ],

    [
        'slug' => 'las-vegas-convention-center',
        'name' => 'Las Vegas Convention Center',
        'city' => 'Las Vegas',
        'region' => 'Nevada',
        'blurb' => 'A major Las Vegas convention destination for large-scale trade shows, conferences and exhibitions.',
        'calendar_url' => 'https://www.vegasmeansbusiness.com/destination-calendar/',
        'search' => ['city' => 'Las Vegas'],
        'photo' => [
            'file' => 'las-vegas-convention-center',
            'alt' => 'The Las Vegas Convention Center entrance, with its name across the facade',
            'by' => 'Michael Gray',
            'license' => 'CC BY-SA 2.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Las_Vegas_Convention_Ctr.jpg',
        ],
    ],

    [
        'slug' => 'georgia-world-congress-center',
        'name' => 'Georgia World Congress Center',
        'city' => 'Atlanta',
        'region' => 'Georgia',
        'blurb' => "One of the country's largest convention complexes and a major destination for national events.",
        'calendar_url' => 'https://www.gwcc.com/calendar/',
        'search' => ['city' => 'Atlanta'],
        'photo' => [
            'file' => 'georgia-world-congress-center',
            'alt' => 'The Georgia World Congress Center in Atlanta, its name along the red entrance canopy',
            'by' => 'Michael Barera',
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Atlanta_August_2016_23_(Georgia_World_Congress_Center).jpg',
        ],
    ],

    [
        'slug' => 'javits-center',
        'name' => 'Javits Center',
        'city' => 'New York',
        'region' => 'New York',
        'blurb' => 'Major Manhattan convention destination with a continuously updated calendar of trade shows, conferences, expos and consumer events.',
        'calendar_url' => 'https://www.javitscenter.com/en/events',
        'search' => ['city' => 'New York'],
        'photo' => [
            'file' => 'javits-center',
            'alt' => 'The glass curtain wall of the Javits Center in Manhattan, with its name on the tower',
            'by' => 'Kidfly182',
            'license' => 'CC BY 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Jacob_Javits_Convention_Center_002.jpg',
        ],
    ],

];
