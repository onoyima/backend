<?php
$apiBase = $argv[1] ?? 'https://testexeat.veritas.edu.ng/api';
$arg2 = $argv[2] ?? '';
$arg3 = $argv[3] ?? '';
$token = '';
if ($arg2 && strpos($arg2, '@') !== false) {
    $loginUrl = rtrim($apiBase, '/') . '/login';
    $payload = json_encode(['email' => $arg2, 'password' => $arg3]);
    $ch = curl_init($loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status === 200) {
        $json = json_decode($res, true);
        $token = $json['token'] ?? '';
    }
} else {
    $token = $arg2;
}
$sseUrl = rtrim($apiBase, '/') . '/staff/notifications/stream' . ($token ? ('?token=' . urlencode($token)) : '');
$audioUrl = rtrim($apiBase, '/') . '/notifications/alert-audio';
$prevUnread = null;
$ch = curl_init($sseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($audioUrl, &$prevUnread) {
    foreach (explode("\n", $chunk) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ':') { continue; }
        if (strpos($line, 'data:') === 0) {
            $json = trim(substr($line, 5));
            $payload = json_decode($json, true);
            if (!is_array($payload)) { continue; }
            $count = isset($payload['unread_count']) ? (int)$payload['unread_count'] : null;
            $latest = $payload['latest_id'] ?? null;
            if ($count !== null) {
                echo "Unread={$count} Latest={$latest}\n";
                if ($prevUnread !== null && $count > $prevUnread) {
                    $fn = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('alert_' . time() . '.mp3');
                    $fp = fopen($fn, 'wb');
                    $ah = curl_init($audioUrl);
                    curl_setopt($ah, CURLOPT_FILE, $fp);
                    curl_setopt($ah, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ah, CURLOPT_TIMEOUT, 20);
                    curl_exec($ah);
                    curl_close($ah);
                    fclose($fp);
                    echo "Saved: {$fn}\n";
                }
                $prevUnread = $count;
            }
        }
    }
    return strlen($chunk);
});
curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if ($err) { echo "Stream error: {$err}\n"; }