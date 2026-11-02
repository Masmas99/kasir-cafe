<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TableQrCodeController extends Controller
{
    /**
     * Download the SVG QR code for a single table.
     */
    public function download(DiningTable $diningTable): Response
    {
        $svg = (string) QrCode::format('svg')
            ->size(400)
            ->margin(2)
            ->generate($diningTable->orderUrl());

        $filename = 'qr-'.Str::slug($diningTable->name).'.svg';

        return response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Render a printable sheet of QR codes for one or all active tables.
     */
    public function print(Request $request): View
    {
        $tables = DiningTable::query()
            ->when($request->filled('table'), fn ($query) => $query->whereKey($request->integer('table')))
            ->when(! $request->filled('table'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return view('admin.tables-qr-print', ['tables' => $tables]);
    }
}
