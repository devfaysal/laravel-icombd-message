<?php

return [
    'username' => env('ICOMBD_USERNAME', ''),
    'password' => env('ICOMBD_PASSWORD', ''),

    'sms_sender' => env('ICOMBD_SMS_SENDER', ''),
    'voice_sender' => env('ICOMBD_VOICE_SENDER', ''),

    'base_url' => env('ICOMBD_BASE_URL', 'https://api.icombd.com'),
    'timeout' => env('ICOMBD_TIMEOUT', 30),
    'connect_timeout' => env('ICOMBD_CONNECT_TIMEOUT', 10),
];
