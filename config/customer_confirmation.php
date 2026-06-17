<?php

return [
    'auto_sync_billing_mutations' => (bool) env('CUSTOMER_CONFIRMATION_AUTO_SYNC_BILLING_MUTATIONS', true),
    'combine_feature_enabled' => (bool) env('CUSTOMER_CONFIRMATION_COMBINE_FEATURE_ENABLED', true),
];
