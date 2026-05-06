<?php

namespace App\Models;

use App\Enums\HelpAudience;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'audience',
        'category',
        'title',
        'summary',
        'body',
        'search_keywords',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'audience'     => HelpAudience::class,
        'is_published' => 'bool',
        'sort_order'   => 'int',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
