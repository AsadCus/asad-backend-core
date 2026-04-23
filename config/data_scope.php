<?php

return [
    'enabled' => (bool) env('DATA_SCOPE_ENABLED', true),
    'mode' => env('DATA_SCOPE_MODE', 'country'),
    'sales_ownership' => (bool) env('DATA_SCOPE_SALES_OWNERSHIP', false),
];
