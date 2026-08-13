<?php

return [
    'certificate_path' => storage_path('app/qz/digital-certificate.txt'),
    'private_key_path' => storage_path('app/qz/private-key.pem'),
    'root_certificate_path' => storage_path('app/qz/root-certificate.crt'),
    'key_days' => (int) env('QZ_CERT_DAYS', 825),
    'organization' => env('QZ_CERT_ORG', 'Cashier System'),
];
