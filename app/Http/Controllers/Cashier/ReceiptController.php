<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\TableSession;
use Illuminate\Contracts\View\View;

class ReceiptController extends Controller
{
    /**
     * Render a printable receipt for a table session.
     */
    public function show(TableSession $tableSession): View
    {
        $tableSession->load([
            'diningTable',
            'orders.items',
            'payments.recordedByUser',
        ]);

        return view('cashier.receipt', [
            'session' => $tableSession,
            'latestPayment' => $tableSession->payments->sortByDesc('id')->first(),
        ]);
    }
}
