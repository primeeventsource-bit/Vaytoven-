<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings\FeatureFlagService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;

/**
 * Frontend bootstrap: every is_public setting plus the resolved feature
 * flags the UI cares about. No auth; sensitive/encrypted values are
 * structurally excluded (allPublic() skips them), and the payload is
 * cached server-side + for 5 minutes at the edge.
 */
class PublicSettingsController extends Controller
{
    public function __invoke(SettingsRepository $settings, FeatureFlagService $flags): JsonResponse
    {
        return response()
            ->json([
                'settings' => $settings->allPublic(),
                'features' => $flags->publicStates(),
            ])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
