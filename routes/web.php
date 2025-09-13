<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-api', function() {
    $payload = [
        'test' => 'This is a much longer JSON payload that should definitely require HTTP/2 DATA frames to be sent properly and trigger the debug output'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.misbar.tech/api/test-200');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return response()->json([
        'status' => $httpCode,
        'response' => $response
    ]);
});