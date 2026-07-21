<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$configPath = __DIR__ . '/telegram-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}
require $configPath;

if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID') || TELEGRAM_BOT_TOKEN === '' || TELEGRAM_CHAT_ID === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

function clean_field($value) {
    $value = (string) $value;
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    return mb_substr(trim($value), 0, 200);
}

$name = clean_field($input['name'] ?? '');
$phone = clean_field($input['phone'] ?? '');
$course = clean_field($input['course'] ?? '');
$lang = clean_field($input['lang'] ?? 'ru');

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

$labelName = $lang === 'kk' ? 'Аты-жөні' : 'Имя';
$labelPhone = 'Телефон';
$labelCourse = $lang === 'kk' ? 'Курс' : 'Курс';
$labelLang = $lang === 'kk' ? 'Тіл' : 'Язык';

$text = "\xF0\x9F\x86\x95 Аэроклимат Академия — жаңа өтінім / новая заявка\n\n"
      . $labelName . ": " . $name . "\n"
      . $labelPhone . ": " . $phone . "\n"
      . $labelCourse . ": " . $course . "\n"
      . $labelLang . ": " . strtoupper($lang);

$url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
$payload = http_build_query([
    'chat_id' => TELEGRAM_CHAT_ID,
    'text' => $text,
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'telegram_error', 'detail' => $curlError ?: $response]);
}
