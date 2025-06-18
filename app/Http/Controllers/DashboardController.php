<?php

namespace App\Http\Controllers;

use App\Models\AfricaTalkingAccount;
use App\Models\Queue;
use App\Models\Text;
use App\Models\TextStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard');
    }


    public function balance()
    {
        try {
            $balance = AfricaTalkingAccount::getBalance();
            return response()->json([
                'success' => true,
                'balance' => $balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getStats()
    {

        return response()->json([
            'success' => true,
            'totalSent' => Queue::where('status', TextStatus::SENT)->count(),
            'totalFailed' => Queue::where('status', TextStatus::FAILED)->count(),
            'totalInQueue' => Queue::where('status', TextStatus::PROCESSING)->count(),
        ]);
    }


    public function getMonthlyStats()
    {
        $months = [];
        $counts = [];

        // Get data for last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('M');
            $year = $date->format('Y');

            $count = Queue::whereYear('created_at', $year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $months[] = $month;
            $counts[] = $count;
        }

        return response()->json([
            'success' => true,
            'months' => $months,
            'counts' => $counts,
        ]);
    }


    public function getRecentSms()
    {
        $messages = Text::with([
            'status:id,text_status_name,color_code',
            'creator:id,name',
        ])
            ->select('texts.*')
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }
}
