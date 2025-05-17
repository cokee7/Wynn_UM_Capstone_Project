<?php
// chatbot_endpoint.php

// Disable error reporting to prevent HTML in JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/chatbot_config.php';         // Defines DB_* and GEMINI_* constants
require_once __DIR__ . '/chatbot_gemini_client.php';  // Provides callGeminiFlash()

// Build base URL for internal API calls
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = "$scheme://{$_SERVER['HTTP_HOST']}" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Load and sanitize fixed user manual
$manualHtml = file_exists(__DIR__ . '/help.html')
    ? file_get_contents(__DIR__ . '/help.html')
    : '';
$manualText = trim(preg_replace('/\s+/', ' ', strip_tags($manualHtml)));

// Read incoming user message
$userMsg = trim($_POST['message'] ?? '');
if ($userMsg === '') {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

// Initialize conversation history once
if (!isset($_SESSION['history']) || $userMsg === '__init__') {
    $systemPrompt = "You are FinSight's AI assistant. Use the following user manual to answer any dashboard questions accurately: $manualText";
    $_SESSION['history'] = [['role'=>'system','content'=>$systemPrompt]];
    if ($userMsg === '__init__') {
        $greet = callGeminiFlash("Greet the user warmly and offer assistance.") ?: "Hello! How can I help you today?";
        $_SESSION['history'][] = ['role'=>'assistant','content'=>$greet];
        echo json_encode(['reply'=>$greet]);
        exit;
    }
}

// Helper to save to conversation history
function saveHistory(string $role, string $content) {
    $_SESSION['history'][] = ['role'=>$role, 'content'=>$content];
}

// Defaults for classification
$action = 'none';
$topic  = '';
$top_n  = 10;
$days   = 7;

// Regex overrides for explicit commands
if (preg_match('/\b(trending topics|top stats|hottest thing|trending stuff)\b/i', $userMsg)) {
    $action = 'trendingTopics';
} elseif (preg_match('/articles?\s+(?:related to|about|on|for)\s+(.+)/iu', $userMsg, $m)) {
    $action = 'getArticles';
    $topic  = trim($m[1]);
} elseif (preg_match('/report\s+(?:for|on|about|of)\s+(.+)/iu', $userMsg, $m)) {
    $action = 'getReport';
    $topic  = trim($m[1]);
} else {
    // Invisible LLM classifier fallback
    $classifierPrompt = <<<P
You are a classifier. Given a user message, output JSON with keys:
- action: "trendingTopics", "getArticles", "getReport", or "none"
- topic (string or null)
- top_n (integer)
- days (integer)
Do not output any additional text.
P;
    $cj = callGeminiFlash("$classifierPrompt\nUser: $userMsg\nAssistant:");
    $obj = json_decode($cj, true);
    if (is_array($obj)) {
        $action = $obj['action'] ?? 'none';
        $topic  = $obj['topic']  ?? '';
        $top_n  = intval($obj['top_n'] ?? 10);
        $days   = intval($obj['days']  ?? 7);
    }
}

// Robust HTTP fetch with JSON validation
function fetchJson(string $url): array {
    $opts = ['http' => ['ignore_errors' => true, 'timeout'=>5]];
    $ctx  = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    if (!is_string($resp) || strlen($resp) === 0) {
        return ['error' => 'Empty response from API'];
    }
    $trim = ltrim($resp);
    if (strpos($trim, '{') !== 0 && strpos($trim, '[') !== 0) {
        return ['error' => 'Invalid JSON response'];
    }
    $json = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'JSON parse error'];
    }
    return $json;
}

// Handle trending topics
if ($action === 'trendingTopics') {
    $data   = fetchJson("$base/get_dashboard_data.php?days=$days&top_n=$top_n");
    $topics = $data['trending_topics'] ?? [];
    $lines  = [];
    foreach ($topics as $i => $t) {
        if ($i >= $top_n) break;
        $lines[] = "- {$t['Topic']}";
    }
    $reply = "Here are the top $top_n trending topics:\n" . implode("\n", $lines);
    saveHistory('user', $userMsg);
    saveHistory('assistant', $reply);
    echo json_encode(['reply'=>$reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle fetching articles
if ($action === 'getArticles') {
    $data  = fetchJson("$base/get_specific_topic.php?topic=" . urlencode($topic) . "&days=$days");
    if (isset($data['error'])) {
        $reply = "Error: {$data['error']}";
    } else {
        $lines = [];
        foreach ($data as $art) {
            $lines[] = "- {$art['Title']} ({$art['Created_Time']})\n  {$art['Link']}";
        }
        $reply = empty($lines)
            ? "No articles found for '$topic'."
            : "Here are recent articles about '$topic':\n" . implode("\n", $lines);
    }
    saveHistory('user', $userMsg);
    saveHistory('assistant', $reply);
    echo json_encode(['reply'=>$reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle fetching report
if ($action === 'getReport') {
    $data = fetchJson("$base/get_report_api.php?topic=" . urlencode($topic));
    if (isset($data['error'])) {
        $reply = "Error: {$data['error']}";
    } else {
        $md = ["**Report for {$data['topic']}**", "_Generated: {$data['generated_time']}_\n"];
        foreach (preg_split('/\r\n|\r|\n/', $data['content'] ?? '') as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $md[] = preg_replace('/\*\*(.*?)\*\*/', '### $1', $p);
        }
        $reply = implode("\n\n", $md);
    }
    saveHistory('user', $userMsg);
    saveHistory('assistant', $reply);
    echo json_encode(['reply'=>$reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback to multi-turn chat
saveHistory('user', $userMsg);
$historyLines = array_map(function($m) {
    return ucfirst($m['role']) . ": {$m['content']}";
}, $_SESSION['history']);
$historyLines[] = 'Assistant:';
$prompt         = implode("\n", $historyLines);
$reply          = callGeminiFlash($prompt) ?: 'Sorry, something went wrong.';
saveHistory('assistant', $reply);

echo json_encode(['reply'=>$reply], JSON_UNESCAPED_UNICODE);
exit;
?>