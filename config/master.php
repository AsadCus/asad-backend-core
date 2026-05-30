<?php

return [
    'hide_customer_from_user_management' => (bool) env('MASTER_HIDE_CUSTOMER_FROM_USER_MANAGEMENT', false),
    'show_two_factor_auth' => (bool) env('MASTER_SHOW_TWO_FACTOR_AUTH', true),
];
