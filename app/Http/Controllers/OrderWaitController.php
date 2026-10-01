<?php

namespace App\Http\Controllers;

use App\Services\OrderWaitService;

class OrderWaitController extends Controller
{
    /** Polled by the reminder on the staff and admin screens. */
    public function index(OrderWaitService $orders)
    {
        return response()->json([
            'minutes' => OrderWaitService::reminderMinutes(),
            'orders' => $orders->waiting(),
            'kds_url' => route('kds.index'),
        ]);
    }
}
