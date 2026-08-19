<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitiesSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            // Safety
            ['slug' => 'smoke-alarm',           'label' => 'Smoke alarm',                'category' => 'safety'],
            ['slug' => 'co-detector',           'label' => 'Carbon monoxide detector',   'category' => 'safety'],
            ['slug' => 'first-aid-kit',         'label' => 'First aid kit',              'category' => 'safety'],
            ['slug' => 'fire-extinguisher',     'label' => 'Fire extinguisher',          'category' => 'safety'],
            ['slug' => 'security-cameras',      'label' => 'Exterior security cameras',  'category' => 'safety'],
            ['slug' => 'lockbox',               'label' => 'Self check-in lockbox',      'category' => 'safety'],

            // Accessibility
            ['slug' => 'step-free-entrance',    'label' => 'Step-free entrance',         'category' => 'accessibility'],
            ['slug' => 'wide-doorways',         'label' => 'Wide doorways',              'category' => 'accessibility'],
            ['slug' => 'grab-bars',             'label' => 'Grab bars in bathroom',      'category' => 'accessibility'],
            ['slug' => 'roll-in-shower',        'label' => 'Roll-in shower',             'category' => 'accessibility'],
            ['slug' => 'accessible-parking',    'label' => 'Accessible parking spot',    'category' => 'accessibility'],
            ['slug' => 'elevator',              'label' => 'Elevator',                   'category' => 'accessibility'],

            // Outdoor
            ['slug' => 'pool',                  'label' => 'Pool',                       'category' => 'outdoor'],
            ['slug' => 'hot-tub',               'label' => 'Hot tub',                    'category' => 'outdoor'],
            ['slug' => 'patio',                 'label' => 'Patio or balcony',           'category' => 'outdoor'],
            ['slug' => 'bbq-grill',             'label' => 'BBQ grill',                  'category' => 'outdoor'],
            ['slug' => 'outdoor-dining',        'label' => 'Outdoor dining area',        'category' => 'outdoor'],
            ['slug' => 'fire-pit',              'label' => 'Fire pit',                   'category' => 'outdoor'],
            ['slug' => 'beachfront',            'label' => 'Beachfront',                 'category' => 'outdoor'],
            ['slug' => 'lakefront',             'label' => 'Lakefront',                  'category' => 'outdoor'],
            ['slug' => 'mountain-view',         'label' => 'Mountain view',              'category' => 'outdoor'],
            ['slug' => 'garden',                'label' => 'Garden',                     'category' => 'outdoor'],

            // Indoor
            ['slug' => 'wifi',                  'label' => 'Wifi',                       'category' => 'indoor'],
            ['slug' => 'air-conditioning',      'label' => 'Air conditioning',           'category' => 'indoor'],
            ['slug' => 'heating',               'label' => 'Heating',                    'category' => 'indoor'],
            ['slug' => 'kitchen',               'label' => 'Kitchen',                    'category' => 'indoor'],
            ['slug' => 'dishwasher',            'label' => 'Dishwasher',                 'category' => 'indoor'],
            ['slug' => 'microwave',             'label' => 'Microwave',                  'category' => 'indoor'],
            ['slug' => 'coffee-maker',          'label' => 'Coffee maker',               'category' => 'indoor'],
            ['slug' => 'washer',                'label' => 'Washer',                     'category' => 'indoor'],
            ['slug' => 'dryer',                 'label' => 'Dryer',                      'category' => 'indoor'],
            ['slug' => 'iron',                  'label' => 'Iron',                       'category' => 'indoor'],
            ['slug' => 'tv',                    'label' => 'TV',                         'category' => 'indoor'],
            ['slug' => 'streaming-services',    'label' => 'Streaming services',         'category' => 'indoor'],
            ['slug' => 'fireplace',             'label' => 'Indoor fireplace',           'category' => 'indoor'],
            ['slug' => 'hair-dryer',            'label' => 'Hair dryer',                 'category' => 'indoor'],

            // Family
            ['slug' => 'crib',                  'label' => 'Crib',                       'category' => 'family'],
            ['slug' => 'high-chair',            'label' => 'High chair',                 'category' => 'family'],
            ['slug' => 'pack-n-play',           'label' => 'Pack-n-play / travel crib',  'category' => 'family'],
            ['slug' => 'baby-gates',            'label' => 'Baby safety gates',          'category' => 'family'],
            ['slug' => 'baby-bath',             'label' => 'Baby bath',                  'category' => 'family'],
            ['slug' => 'kids-toys',             'label' => 'Children\'s books and toys', 'category' => 'family'],
            ['slug' => 'board-games',           'label' => 'Board games',                'category' => 'family'],
            ['slug' => 'pets-allowed',          'label' => 'Pets allowed',               'category' => 'family'],

            // Workspace
            ['slug' => 'dedicated-workspace',   'label' => 'Dedicated workspace',        'category' => 'workspace'],
            ['slug' => 'fast-wifi',             'label' => 'Fast wifi (100+ Mbps)',      'category' => 'workspace'],
            ['slug' => 'monitor',               'label' => 'External monitor',           'category' => 'workspace'],
            ['slug' => 'standing-desk',         'label' => 'Standing desk',              'category' => 'workspace'],

            // Other
            ['slug' => 'ev-charger',            'label' => 'EV charger',                 'category' => 'other'],
            ['slug' => 'free-parking',          'label' => 'Free parking on premises',   'category' => 'other'],
            ['slug' => 'paid-parking',          'label' => 'Paid parking on premises',   'category' => 'other'],
            ['slug' => 'gym',                   'label' => 'Gym',                        'category' => 'other'],
            ['slug' => 'sauna',                 'label' => 'Sauna',                      'category' => 'other'],
            ['slug' => 'long-term-stays',       'label' => 'Long-term stays allowed',    'category' => 'other'],
            ['slug' => 'self-checkin',          'label' => 'Self check-in',              'category' => 'other'],
    // --- from the listing builder spec -------------------------------
            // Inside the property
            ['slug' => 'washer-dryer',          'label' => 'Washer / dryer',             'category' => 'indoor'],
            ['slug' => 'streaming-services',    'label' => 'Streaming services',         'category' => 'indoor'],
            ['slug' => 'jacuzzi-tub',           'label' => 'Jacuzzi / soaking tub',      'category' => 'indoor'],
            ['slug' => 'refrigerator',          'label' => 'Refrigerator',               'category' => 'indoor'],
            ['slug' => 'microwave',             'label' => 'Microwave',                  'category' => 'indoor'],
            ['slug' => 'oven',                  'label' => 'Oven',                       'category' => 'indoor'],
            ['slug' => 'stove',                 'label' => 'Stove',                      'category' => 'indoor'],
            ['slug' => 'coffee-maker',          'label' => 'Coffee maker',               'category' => 'indoor'],
            ['slug' => 'dining-area',           'label' => 'Dining area',                'category' => 'indoor'],
            ['slug' => 'in-room-safe',          'label' => 'Safe',                       'category' => 'indoor'],
            ['slug' => 'balcony-patio',         'label' => 'Balcony / patio',            'category' => 'outdoor'],

            // Property and resort facilities
            ['slug' => 'heated-pool',           'label' => 'Heated pool',                'category' => 'outdoor'],
            ['slug' => 'fitness-center',        'label' => 'Fitness center',             'category' => 'outdoor'],
            ['slug' => 'spa',                   'label' => 'Spa',                        'category' => 'outdoor'],
            ['slug' => 'restaurant',            'label' => 'Restaurant',                 'category' => 'outdoor'],
            ['slug' => 'bar-lounge',            'label' => 'Bar / lounge',               'category' => 'outdoor'],
            ['slug' => 'beach-access',          'label' => 'Beach access',               'category' => 'outdoor'],
            ['slug' => 'golf',                  'label' => 'Golf',                       'category' => 'outdoor'],
            ['slug' => 'tennis',                'label' => 'Tennis',                     'category' => 'outdoor'],
            ['slug' => 'pickleball',            'label' => 'Pickleball',                 'category' => 'outdoor'],
            ['slug' => 'kids-club',             'label' => "Kids' club",                 'category' => 'family'],
            ['slug' => 'game-room',             'label' => 'Game room',                  'category' => 'family'],
            ['slug' => 'business-center',       'label' => 'Business center',            'category' => 'workspace'],
            ['slug' => 'concierge',             'label' => 'Concierge',                  'category' => 'outdoor'],
            ['slug' => 'shuttle',               'label' => 'Shuttle service',            'category' => 'outdoor'],
            ['slug' => 'bbq-area',              'label' => 'BBQ area',                   'category' => 'outdoor'],
            ['slug' => 'marina',                'label' => 'Marina',                     'category' => 'outdoor'],
            ['slug' => 'water-activities',      'label' => 'Water activities',           'category' => 'outdoor'],
            ['slug' => 'ski-access',            'label' => 'Ski access',                 'category' => 'outdoor'],
            ['slug' => 'front-desk',            'label' => '24-hour front desk',         'category' => 'safety'],
            ['slug' => 'on-site-security',      'label' => 'On-site security',           'category' => 'safety'],
            ['slug' => 'wheelchair-access',     'label' => 'Wheelchair accessible',      'category' => 'accessibility'],
        ];


        foreach ($catalogue as $row) {
            Amenity::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
