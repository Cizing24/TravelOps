<?php

namespace App\Http\Controllers;

use App\Application\Redeem;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MerchantController
{
    public function index(Request $request): View
    {
        Gate::authorize('redeem');
        /** @var User $actor */
        $actor = $request->user();
        $venues = $actor->venues()->orderBy('venues.name')->get();

        return view('merchant.index', [
            'venues' => $venues,
            'records' => Redemption::whereIn('venue_id', $venues->modelKeys())->with('venue')->latest('id')->paginate(15),
        ]);
    }

    public function store(Request $request, Redeem $redeem): RedirectResponse|JsonResponse
    {
        Gate::authorize('redeem');
        $data = $request->validate(['venue_id' => 'required|integer|min:1', 'code' => ['required', 'string', 'regex:/\A[a-f0-9]{48}\z/']]);
        /** @var User $actor */
        $actor = $request->user();
        $record = $redeem->ticket($actor, (int) $data['venue_id'], $data['code']);
        if ($request->expectsJson()) {
            return response()->json(['trace_id' => $record->trace_id, 'redeemed_at' => $record->redeemed_at], 201);
        }

        return redirect()->route('merchant.index')->with('notice', '已完成核銷。追蹤編號 '.$record->trace_id);
    }
}
