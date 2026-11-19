<?php

return [
    'demo' => (bool) env('TRAVELOPS_DEMO', false),
    'public_demo' => (bool) env('TRAVELOPS_PUBLIC_DEMO', false),
    'provision_confirm' => env('DEMO_PROVISION_CONFIRM'),
    'provision_passwords' => [
        'customer' => env('DEMO_CUSTOMER_PASSWORD', ''),
        'operator' => env('DEMO_OPERATOR_PASSWORD', ''),
        'merchant' => env('DEMO_MERCHANT_PASSWORD', ''),
        'pos_clerk' => env('DEMO_POS_PASSWORD', ''),
    ],
    'webhook_secret' => env('TRAVELOPS_WEBHOOK_SECRET'),
];
