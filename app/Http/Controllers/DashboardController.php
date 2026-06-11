<?php

namespace App\Http\Controllers;

use App\Models\AfricaTalkingAccount;
use App\Models\Queue;
use App\Models\Text;
use App\Models\TextStatus;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard');
    }

    public function balance()
    {
        $countries = Auth::user()->countries()->where('is_active', true)->get(['countries.id', 'name', 'code']);

        $balances = [];
        foreach ($countries as $country) {
            try {
                $balances[] = [
                    'country'  => $country->name,
                    'code'     => $country->code,
                    'balance'  => AfricaTalkingAccount::getBalance(strtolower($country->code)),
                    'success'  => true,
                ];
            } catch (\Exception $e) {
                $balances[] = [
                    'country' => $country->name,
                    'code'    => $country->code,
                    'balance' => null,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['success' => true, 'balances' => $balances]);
    }

    public function getStats()
    {
        $countryIds = Auth::user()->countries()->pluck('countries.id');

        $textIds = Text::whereIn('country_id', $countryIds)->pluck('id');

        return response()->json([
            'success'      => true,
            'totalSent'    => Queue::whereIn('text_id', $textIds)->where('status', TextStatus::SENT)->count(),
            'totalFailed'  => Queue::whereIn('text_id', $textIds)->where('status', TextStatus::FAILED)->count(),
            'totalInQueue' => Queue::whereIn('text_id', $textIds)->where('status', TextStatus::PROCESSING)->count(),
        ]);
    }

    public function getMonthlyStats()
    {
        $countryIds = Auth::user()->countries()->pluck('countries.id');
        $textIds    = Text::whereIn('country_id', $countryIds)->pluck('id');

        $months = [];
        $counts = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $counts[] = Queue::whereIn('text_id', $textIds)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $months[] = $date->format('M');
        }

        return response()->json(['success' => true, 'months' => $months, 'counts' => $counts]);
    }

    public function getRecentSms()
    {
        $countryIds = Auth::user()->countries()->pluck('countries.id');

        $messages = Text::with([
            'status:id,text_status_name,color_code',
            'creator:id,name',
        ])
            ->whereIn('country_id', $countryIds)
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();

        return response()->json(['success' => true, 'messages' => $messages]);
    }
}
