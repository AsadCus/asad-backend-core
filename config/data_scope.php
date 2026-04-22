<?php

return [
    'enabled' => (bool) env('DATA_SCOPE_ENABLED', true),
    'mode' => env('DATA_SCOPE_MODE', 'country'),
    'scope_sales_ownership' => (bool) env('DATA_SCOPE_SCOPE_SALES_OWNERSHIP', false),
];
