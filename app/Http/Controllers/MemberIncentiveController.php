<?php

namespace App\Http\Controllers;

use App\Services\Fulfillment\DiningRewardsIncentive;
use App\Services\Fulfillment\MemberIncentive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The $300 Dining Rewards screen a Managed Listing Program client sees after
 * signing in. Everything recorded here is taken from the authenticated
 * session and the request — the form carries only which button was pressed.
 */
class MemberIncentiveController extends Controller
{
    public function __construct(private readonly MemberIncentive $incentive)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $member = $request->user();

        if (! $this->incentive->isEligible($member)) {
            $request->session()->forget(MemberIncentive::SESSION_PENDING);

            return redirect()->route('dashboard');
        }

        $this->incentive->present($request, $member);

        return view('member-incentive.show', [
            'incentive' => DiningRewardsIncentive::class,
            'state'     => $this->incentive->state($member),
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $member = $request->user();
        abort_unless($this->incentive->isEligible($member), 404);

        $choice = $request->input('choice') === 'view' ? 'view' : 'continue';

        try {
            $this->incentive->acknowledge($request, $member, $choice);
        } catch (RuntimeException) {
            return redirect()->route('member.incentive.show');
        }

        $request->session()->forget([MemberIncentive::SESSION_PENDING, MemberIncentive::SESSION_PENDING.'_first_login']);

        return $choice === 'view'
            ? redirect()->route('member.incentive.reward')
            : redirect()->route('dashboard')->with('success', 'Thank you — your $300 Dining Rewards thank-you is noted on your account.');
    }

    /** Details of the reward and where it stands. Records nothing. */
    public function reward(Request $request): View
    {
        abort_unless($this->incentive->isEligible($request->user()), 404);

        return view('member-incentive.reward', [
            'incentive' => DiningRewardsIncentive::class,
            'state'     => $this->incentive->state($request->user()),
        ]);
    }
}
