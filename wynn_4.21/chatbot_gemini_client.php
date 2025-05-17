<?php
require_once __DIR__ . '/chatbot_config.php';

/**
 * Call Gemini 2.0 Flash generateContent endpoint.
 *
 * @param string $prompt The full prompt text.
 * @return string|null   The model's reply, or null on failure.
 */
function callGeminiFlash(string $prompt): ?string {
    if (empty(GEMINI_API_KEY)) {
        error_log("⚠️ Missing GEMINI_API_KEY");
        return null;
    }

    $payload = [
        'contents' => [[
            'parts' => [['text' => $prompt]]
        ]]
    ];

    $url = sprintf(
        "%s?key=%s",
        GEMINI_ENDPOINT,
        urlencode(GEMINI_API_KEY)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $resp     = curl_exec($ch);
    $errNo    = curl_errno($ch);
    $errMsg   = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Gemini HTTP status: {$httpCode}");
    if ($errNo) {
        error_log("Curl error ({$errNo}): {$errMsg}");
        return null;
    }

    error_log("Gemini raw response: " . substr($resp, 0, 500));

    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Unexpected Gemini response structure: " . print_r($data, true));
        return null;
    }

    return $data['candidates'][0]['content']['parts'][0]['text'];
}