<?php

namespace App\Http\Middleware;

use App\Models\TermsAcceptance;
use App\Services\Legal\LegalDocumentRegistry;
use Closure;
use Illuminate\Http\Request;

class EnsureMobileAccountAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isActive(), 403, __('This account has been deactivated.'));
        $name = $request->route()->getName();
        if (in_array($name, ['mobile.account', 'mobile.password.update', 'mobile.terms.store'], true)) {
            return $next($request);
        }
        $required = collect(app(LegalDocumentRegistry::class)->registrationRequired())->pluck('id');
        $accepted = TermsAcceptance::where('user_id', $request->user()->id)->whereIn('terms_version_id', $required)->count();
        if ($accepted < $required->count()) {
            return response()->json(['message' => __('Please review the current terms.'), 'error' => 'terms_required'], 409);
        }

        return $next($request);
    }
}
