<?php

return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
    'server_key' => env('MIDTRANS_SERVER_KEY', ''),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'sandbox_base_url' => env('MIDTRANS_SANDBOX_BASE_URL', 'https://api.sandbox.midtrans.com'),
    'production_base_url' => env('MIDTRANS_PRODUCTION_BASE_URL', 'https://api.midtrans.com'),
    'sandbox_snap_url' => env('MIDTRANS_SANDBOX_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
    'production_snap_url' => env('MIDTRANS_PRODUCTION_SNAP_URL', 'https://app.midtrans.com/snap/v1/transactions'),
];
