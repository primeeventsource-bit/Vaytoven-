<?php

namespace App\Observers;

use App\Models\MemberEnquiry;
use App\Models\Property;
use App\Services\Exchange\ExchangeDetectionService;
use Throwable;

/**
 * Fires Vacation Club Exchange Detection automatically on:
 *   - MemberEnquiry::created     — always (every enquiry gets one)
 *   - Property::created          — only when listing_source = 'managed'
 *
 * Wrapped in try/catch with report() so a detection failure can never
 * block the underlying create. The row saves with exchange_detection = null
 * and an admin can re-trigger detection later via the admin UI.
 *
 * Registered in AppServiceProvider::boot() — Laravel 11 also supports the
 * ObservedBy attribute on models, but we wire here so the observer can
 * watch multiple models from one class.
 */
class ExchangeDetectionObserver
{
    public function __construct(private readonly ExchangeDetectionService $detector)
    {
    }

    public function created(MemberEnquiry|Property $model): void
    {
        try {
            if ($model instanceof Property && $model->listing_source !== 'managed') {
                return;
            }

            $detection = $model instanceof MemberEnquiry
                ? $this->detector->detect($model->club, $model->property)
                : $this->detector->detect($model->title, $model->city);

            // forceFill so we set the column even though it isn't $fillable
            // on the model. saveQuietly so we don't recursively fire updated.
            $model->forceFill(['exchange_detection' => $detection])->saveQuietly();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
