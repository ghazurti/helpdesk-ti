<?php

return [
    'vclaim' => [
        'base_url' => env('BPJS_VCLAIM_BASE_URL', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/'),
        'cons_id' => env('BPJS_VCLAIM_CONS_ID'),
        'secret_key' => env('BPJS_VCLAIM_SECRET_KEY'),
        'user_key' => env('BPJS_VCLAIM_USER_KEY'),

        // Endpoint referensi ringan untuk uji response time: hanya baca
        // data ICD-10, tidak menyentuh data pasien, aman dipanggil kapan
        // saja tanpa efek samping.
        'test_endpoint' => env('BPJS_VCLAIM_TEST_ENDPOINT', 'Referensi/diagnosa/A00'),

        'timeout' => (int) env('BPJS_VCLAIM_TIMEOUT', 10),
    ],
];
