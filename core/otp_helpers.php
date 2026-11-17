<?php

function format_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);

    if (preg_match('/^09\d{9}$/', $phone)) {
        return '63' . substr($phone, 1);
    }

    if (preg_match('/^639\d{9}$/', $phone)) {
        return $phone;
    }

    return false;
}

function send_sms($phone, $otp, $apikey) {

    $phone = format_phone($phone);

    if (!$phone) {
        return ['success' => false, 'error' => 'Invalid phone'];
    }

    if (!$apikey) {
        return ['success' => false, 'error' => 'Missing API key'];
    }

    $data = [
        'apikey' => $apikey,
        'number' => $phone,
        'message' => "eRequest Mo verification code: $otp. Valid for 2 minutes. Do not share this code with anyone.",
        'sendername' => 'SREREQ'
    ];

    $ch = curl_init("https://api.semaphore.co/api/v4/messages");

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['success' => false, 'error' => curl_error($ch)];
    }

    curl_close($ch);

    file_put_contents(__DIR__ . "/sms_debug.log", $response . PHP_EOL, FILE_APPEND);

    $decoded = json_decode($response, true);

    if (!isset($decoded[0]['status'])) {
        return ['success' => false, 'error' => $response];
    }

    return ['success' => true];
}