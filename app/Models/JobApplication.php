<?php

namespace App\Models;

use App\Support\Reference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'job_opening_id', 'first_name', 'last_name', 'email',
        'phone', 'cover_note', 'resume_path', 'status', 'ip',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            $application->reference ??= Reference::generate(
                'J',
                fn (string $code) => static::query()->where('reference', $code)->exists(),
            );
        });
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
