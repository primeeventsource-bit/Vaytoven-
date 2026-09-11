<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\AccountActionRequest;
use App\Http\Requests\Api\Mobile\OfferRequest;
use App\Http\Resources\MobileOfferResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\UserResource;
use App\Models\MemberOffer;
use App\Models\Property;
use App\Models\TermsAcceptance;
use App\Models\Wishlist;
use App\Services\Legal\LegalDocumentRegistry;
use App\Services\Mobile\MobileListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function account(Request $request, LegalDocumentRegistry $registry)
    {
        $accepted = TermsAcceptance::where('user_id', $request->user()->id)->pluck('terms_version_id')->all();

        return response()->json([
            'user' => new UserResource($request->user()),
            'required_terms' => collect($registry->registrationRequired())->values()->map(fn ($v) => [
                'accepted' => in_array($v->id, $accepted, true), 'id' => $v->id, 'kind' => $v->kind, 'version' => $v->version_label, 'url' => $v->publicUrl(),
            ]),
        ]);
    }

    public function terms(AccountActionRequest $request, LegalDocumentRegistry $registry)
    {
        $ids = collect($registry->registrationRequired())->pluck('id')->sort()->values()->all();
        $submitted = collect($request->validated('version_ids'))->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($ids !== $submitted) {
            throw ValidationException::withMessages(['version_ids' => __('The terms changed. Please review the current documents.')]);
        }
        DB::transaction(function () use ($ids, $request) {
            foreach ($ids as $id) {
                TermsAcceptance::firstOrCreate(['user_id' => $request->user()->id, 'terms_version_id' => $id], [
                    'accepted_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                ]);
            }
        });

        return response()->json(['message' => __('Terms accepted.')]);
    }

    public function password(AccountActionRequest $request)
    {
        $user = $request->user();
        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('The current password is incorrect.')]);
        }
        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false, 'password_changed_at' => now()])->save();
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        });

        return response()->json(['message' => __('Password updated.')]);
    }

    public function saved(Request $request)
    {
        $ids = Wishlist::where('user_id', $request->user()->id)->pluck('id');

        return PropertyResource::collection(Property::where('status', PropertyStatus::Active)->whereIn('id', DB::table('wishlist_properties')->select('property_id')->whereIn('wishlist_id', $ids))->with(['photos', 'amenities'])->get());
    }

    public function save(AccountActionRequest $request, Property $property, MobileListingService $service)
    {
        $saved = $request->isMethod('put');
        $service->save($request, $property, $saved);

        return response()->json(['saved' => $saved]);
    }

    public function offers(Request $request)
    {
        return MobileOfferResource::collection(MemberOffer::where('direction', 'from_buyer')->where(fn ($q) => $q->where('buyer_user_id', $request->user()->id)->orWhereHas('property', fn ($p) => $p->where('host_id', $request->user()->id)))->with('property')->latest()->paginate(30));
    }

    public function submit(OfferRequest $request, Property $property, MobileListingService $service)
    {
        return (new MobileOfferResource($service->submit($request, $property)))->response()->setStatusCode(201);
    }

    public function respond(AccountActionRequest $request, MemberOffer $offer, MobileListingService $service)
    {
        return new MobileOfferResource($service->respond($request, $offer));
    }
}
