<?php

return [
    /*
    | Default accounts created by `php artisan db:seed`. Values come from .env; the second argument is the
    | fallback used when a key is missing. Change these passwords before any real deployment.
    */
    'seed' => [
        'admin' => [
            'name' => env('SEED_ADMIN_NAME', 'Store Admin'),
            'email' => env('SEED_ADMIN_EMAIL', 'admin@oneprompt.test'),
            'password' => env('SEED_ADMIN_PASSWORD', 'Admin@12345'),
        ],
        'instructor' => [
            'name' => env('SEED_INSTRUCTOR_NAME', 'Course Instructor'),
            'email' => env('SEED_INSTRUCTOR_EMAIL', 'instructor@oneprompt.test'),
            'password' => env('SEED_INSTRUCTOR_PASSWORD', 'Instructor@12345'),
        ],
        'inventory_clerk' => [
            'name' => env('SEED_INVENTORY_NAME', 'Maria Santos'),
            'email' => env('SEED_INVENTORY_EMAIL', 'inventory@oneprompt.test'),
            'password' => env('SEED_INVENTORY_PASSWORD', 'Inventory@12345'),
        ],
        'sales_personnel' => [
            'name' => env('SEED_SALES_NAME', 'Carlo Reyes'),
            'email' => env('SEED_SALES_EMAIL', 'sales@oneprompt.test'),
            'password' => env('SEED_SALES_PASSWORD', 'Sales@12345'),
        ],
        'user' => [
            'name' => env('SEED_USER_NAME', 'Juan Dela Cruz'),
            'email' => env('SEED_USER_EMAIL', 'customer@oneprompt.test'),
            'password' => env('SEED_USER_PASSWORD', 'Customer@12345'),
        ],
    ],

    'payment_methods' => ['Cash', 'GCash', 'Card', 'Bank Transfer'],

    'ml' => [
        // Leave empty to auto-detect: .venv inside the project, then "python3" (Linux/macOS) or "python" (Windows).
        'python' => env('ML_PYTHON'),
        'timeout' => (int) env('ML_TIMEOUT', 600),
        'min_records' => 1000,
    ],
];
