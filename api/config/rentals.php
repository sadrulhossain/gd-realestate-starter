<?php

return [
    // The optional rental management module (tenants, leases, rent, maintenance). Off: its API answers 404, the
    // seeder removes its menus and permissions and its daily job does nothing. Data is kept.
    'enabled' => (bool) env('RENTALS_ENABLED', true),
];
