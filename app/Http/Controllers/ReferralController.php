<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Referral\ReferralService;
use App\Models\Referral;
use App\Models\ReferralEarning;

class ReferralController extends Controller
{

    public function __construct(private ReferralService $referrals)
    {
    }

    private function getMaster(Request $request){
        $master = $request->attributes->get('current_master');
        if (!$master) {
            return response()->json(['error' => 'master not resolved'], 401);
        }
        return $master;
    }

    public function attach(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $master = $this->getMaster($request);

        $referral = $this->referrals->registerReferral($master, $request->input('code'));

        if (!$referral) {
            return response()->json(['error' => 'invalid code'], 422);
        }

        return response()->json([
            'id' => $referral->id,
            'status' => $referral->status,
        ], 201);
    }

    public function my(Request $request)
    {
        $master = $this->getMaster($request);

        $referrals = Referral::with('referredMaster')
            ->where('referrer_master_id', $master->id)
            ->get()
            ->map(function (Referral $r) {
                $earned = ReferralEarning::where('referral_id', $r->id)->sum('amount');

                return [
                    'master' => $r->referredMaster->name,
                    'attached_at' => $r->created_at->toDateString(),
                    'rewarded' => $r->status === Referral::STATUS_REWARDED,
                    'earned' => (int) $earned,
                ];
            });

        return response()->json($referrals);
    }

    public function earnings(Request $request)
    {
        $master = $this->getMaster($request);

        $base = ReferralEarning::where('referrer_master_id', $master->id);

        return response()->json([
            'total' => (int) (clone $base)->sum('amount'),
            'pending' => (int) (clone $base)->where('status', ReferralEarning::STATUS_PENDING)->sum('amount'),
            'paid' => (int) (clone $base)->where('status', ReferralEarning::STATUS_PAID)->sum('amount'),
            'referrals_rewarded' => Referral::where('referrer_master_id', $master->id)
                ->where('status', Referral::STATUS_REWARDED)
                ->count(),
        ]);
    }

}
