<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model with extra fields: maps a PropertyDeveloper to an
 * ExchangeNetwork with a confidence score and optional conditional rules.
 *
 * Conditions JSON shape (all optional):
 *   {"locations": ["Orlando", "Las Vegas"]}   — boosts confidence when
 *                                                the enquiry's property
 *                                                text contains any listed
 *                                                location.
 *   {"contract_types": ["deeded", "right-to-use"]}
 *                                              — currently a hint only;
 *                                                wired-up checks live in
 *                                                ExchangeDetectionService.
 */
class DeveloperExchangeMapping extends Model
{
    protected $table = 'developer_exchange_mappings';

    protected $fillable = [
        'developer_id',
        'exchange_network_id',
        'confidence',
        'priority',
        'conditions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'priority'   => 'integer',
            'conditions' => 'array',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(PropertyDeveloper::class, 'developer_id');
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(ExchangeNetwork::class, 'exchange_network_id');
    }
}
