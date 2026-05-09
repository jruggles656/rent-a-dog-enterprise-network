<?php
/**
 * Bark Bot — AI Chat Endpoint
 * IST 4910 Team 6 | Powered by dolphin3:latest on CSUSB Cyberlab GPU
 *
 * Receives POST with JSON {"message": "user text"}.
 * Returns  JSON {"response": "bot text", "source": "ai"|"fallback"}.
 *
 * Uses OpenWebUI's OpenAI-compatible API at ai.cyberlab.csusb.edu
 * with dolphin3:latest model. Falls back to keyword matching if
 * the API is unreachable or times out.
 */

header('Content-Type: application/json');

// ── Configuration ──────────────────────────────────────────
$OPENWEBUI_URL = 'https://ai.cyberlab.csusb.edu/api/chat/completions';
// FIND-012 / A3: Cyberlab API key loaded from admin.env (outside docroot)
require_once __DIR__ . '/../includes/secrets.php';
$API_KEY       = rentadog_secret('CYBERLAB_API_KEY');
$MODEL         = 'dolphin3:latest';
$TIMEOUT       = 60; // seconds — shared GPU can be slow

$SYSTEM_PROMPT = <<<'PROMPT'
You are Bark Bot, a friendly and enthusiastic customer service assistant for "Rent a Dog" — a dog rental e-commerce business in San Bernardino, CA.

BUSINESS INFO:
- We rent dogs by the hour across 3 tiers: Basic ($30/hr), Premium ($40/hr), VIP ($50/hr)
- Our dogs: Mochi (French Bulldog, VIP), Teddy (Pomeranian, VIP), Biscuit (Labradoodle, Premium), Coco (Cavalier King Charles, Premium), Daisy (Golden Retriever, Premium), Zeus (Tibetan Mastiff, Basic), Luna (Beagle, Basic), Kuma (Shiba Inu, Basic)
- Experiences: Dog Cafe ($55, 90 min), Dog Yoga ($45, 60 min), Dog Garden ($30, 120 min)
- Hours: Mon-Sat 9am-7pm
- Phone: (909) 555-WOOF
- Email: hello@rentadog.local
- Address: 123 Paw Street, San Bernardino, CA

RULES:
- Keep responses SHORT (2-4 sentences max). Customers are chatting, not reading essays.
- Be warm and playful. Use dog puns sparingly. One emoji per response max.
- If someone asks about something unrelated to the business, politely redirect.
- Never make up information not listed above.
- If someone seems upset or has a complaint, be empathetic and suggest they email hello@rentadog.local or call.
PROMPT;

// ── Parse Input ────────────────────────────────────────────
$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode([
        'response' => "Woof! I didn't catch that. Try asking about our breeds, experiences, or pricing!",
        'source'   => 'fallback',
    ]);
    exit;
}

// ── Load DB (for logging) ──────────────────────────────────
require_once __DIR__ . '/../includes/db.php';

// ── Try AI Response ────────────────────────────────────────
$ai_response = callOpenWebUI($message, $OPENWEBUI_URL, $API_KEY, $MODEL, $SYSTEM_PROMPT, $TIMEOUT);

if ($ai_response !== null) {
    // Log to database if available
    logChat($message, $ai_response, 'ai');

    echo json_encode([
        'response' => $ai_response,
        'source'   => 'ai',
    ]);
    exit;
}

// ── Fallback: Keyword Matching ─────────────────────────────
$response = keywordFallback($message);

logChat($message, $response, 'fallback');

echo json_encode([
    'response' => $response,
    'source'   => 'fallback',
]);
exit;

// ════════════════════════════════════════════════════════════
//  Functions
// ════════════════════════════════════════════════════════════

function callOpenWebUI($message, $url, $apiKey, $model, $systemPrompt, $timeout) {
    $payload = json_encode([
        'model'    => $model,
        'messages' => [
            ['role' => 'system',  'content' => $systemPrompt],
            ['role' => 'user',    'content' => $message],
        ],
        'temperature' => 0.7,
        'max_tokens'  => 200,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Cyberlab uses self-signed cert
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200 || !$result) {
        error_log("[BarkBot] API error: HTTP {$httpCode}, curl: {$error}");
        return null;
    }

    $data = json_decode($result, true);
    $content = $data['choices'][0]['message']['content'] ?? null;

    if (!$content) {
        error_log('[BarkBot] Empty response from API: ' . $result);
        return null;
    }

    return trim($content);
}

function logChat($userMessage, $botResponse, $source) {
    global $pdo, $db_available;

    if (!$db_available || !$pdo) return;

    try {
        $pdo->prepare("
            INSERT INTO activity_log (user_type, action, details)
            VALUES ('customer', 'barkbot_chat', :details)
        ")->execute([
            ':details' => json_encode([
                'user_message' => $userMessage,
                'bot_response' => $botResponse,
                'source'       => $source,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    } catch (PDOException $e) {
        error_log('[BarkBot] Log failed: ' . $e->getMessage());
    }
}

function keywordFallback($message) {
    $msg = strtolower($message);

    if (preg_match('/basic|budget|cheap|affordable/', $msg)) {
        return "Our Basic tier ($30/hr) features Zeus the Tibetan Mastiff, Luna the Beagle, and Kuma the Shiba Inu. Great pups at a great price!";
    } elseif (preg_match('/premium|popular|mid/', $msg)) {
        return "Premium tier ($40/hr) is our most popular! Meet Biscuit the Labradoodle, Coco the Cavalier King Charles, and Daisy the Golden Retriever.";
    } elseif (preg_match('/vip|special|rare|luxury|exclusive/', $msg)) {
        return "VIP tier ($50/hr) is pure luxury! Mochi the French Bulldog and Teddy the Pomeranian are absolute showstoppers.";
    } elseif (preg_match('/cafe|coffee/', $msg)) {
        return "The Dog Cafe is one of our most popular experiences! $55 gets you 90 minutes with artisan coffee, pastries, and a furry companion.";
    } elseif (preg_match('/yoga|zen|stretch/', $msg)) {
        return "Dog Yoga is amazing for stress relief! $45 for a 60-minute guided session with a calm dog partner. Yoga mat and herbal tea included!";
    } elseif (preg_match('/garden|outdoor|play|fetch|park/', $msg)) {
        return "The Dog Garden is pure joy! $30 for 2 hours in our private fenced garden with toys, fetch equipment, and water stations.";
    } elseif (preg_match('/price|cost|how much|rate/', $msg)) {
        return "Quick pricing: Basic $30/hr, Premium $40/hr, VIP $50/hr. Experiences: Dog Cafe $55, Dog Yoga $45, Dog Garden $30. Check our Breeds page for details!";
    } elseif (preg_match('/hello|hi|hey|howdy/', $msg)) {
        return "Woof! I'm Bark Bot, your personal pup advisor. I can help you find the perfect dog or experience. What sounds good?";
    } elseif (preg_match('/breed|dog|pup|tier/', $msg)) {
        return "We have 8 amazing dogs across 3 tiers! Basic ($30/hr), Premium ($40/hr), and VIP ($50/hr). Which tier interests you?";
    } elseif (preg_match('/experience|activity/', $msg)) {
        return "We offer three experiences: Dog Cafe ($55, 90 min), Dog Yoga ($45, 60 min), and Dog Garden ($30, 120 min). Which one sounds fun?";
    } elseif (preg_match('/hour|time|open|schedule/', $msg)) {
        return "We're open Monday through Saturday, 9am to 7pm. Drop by anytime or browse our website to book!";
    } else {
        return "Woof! I can help with breed info, pricing, and our experiences (Cafe, Yoga, Garden). What would you like to know?";
    }
}
