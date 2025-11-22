<?php
$apiBase = $argv[1] ?? 'https://testexeat.veritas.edu.ng/api';
$arg2 = $argv[2] ?? '';
$arg3 = $argv[3] ?? '';
$audioUrl = rtrim($apiBase, '/') . '/notifications/alert-audio';
$fn = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('alert_' . time() . '.mp3');
$fp = fopen($fn, 'wb');
$ch = curl_init($audioUrl);
if ($arg2) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $arg2]);
} elseif ($arg3) {
    $loginUrl = rtrim($apiBase, '/') . '/login';
    $payload = json_encode(['email' => $arg2, 'password' => $arg3]);
    $lh = curl_init($loginUrl);
    curl_setopt($lh, CURLOPT_POST, true);
    curl_setopt($lh, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($lh, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($lh, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($lh);
    $status = curl_getinfo($lh, CURLINFO_RESPONSE_CODE);
    curl_close($lh);
    if ($status === 200) {
        $json = json_decode($res, true);
        $tok = $json['token'] ?? '';
        if ($tok) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tok]);
        }
    }
}
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
curl_close($ch);
fclose($fp);
if ($status === 200 && is_string($type) && strpos($type, 'audio') !== false && $size > 0) {
    echo "OK {$type} {$size} bytes\n";
    echo "File {$fn}\n";
    exit(0);
}
@unlink($fn);
echo "Failed status={$status} type={$type} size={$size}\n";
exit(1);