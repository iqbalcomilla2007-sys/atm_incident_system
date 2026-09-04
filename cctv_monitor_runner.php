<?php
date_default_timezone_set('Asia/Dhaka');

include 'db.php';

set_time_limit(0);
ignore_user_abort(true);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

$BASE_DIR = __DIR__;
$SNAPSHOT_DIR = $BASE_DIR . DIRECTORY_SEPARATOR . 'cctv_snapshots';

if (!is_dir($SNAPSHOT_DIR)) {
    mkdir($SNAPSHOT_DIR, 0777, true);
}

function portOpen($ip, $port, $timeout = 3) {
    $fp = @fsockopen($ip, (int)$port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

function getSnapshotUrl($brand, $ip, $port, $channel) {
    $brand = strtoupper(trim($brand));

    if ($brand === 'HIKVISION') {
        $hikChannel = ((int)$channel * 100) + 1;
        return "http://{$ip}:{$port}/ISAPI/Streaming/channels/{$hikChannel}/picture";
    }

    if ($brand === 'DAHUA') {
        return "http://{$ip}:{$port}/cgi-bin/snapshot.cgi?channel={$channel}";
    }

    return null;
}

function downloadSnapshot($url, $username, $password, $savePath) {
    if (!$url || !function_exists('curl_init')) {
        return false;
    }

    foreach ([CURLAUTH_DIGEST, CURLAUTH_BASIC] as $authType) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPAUTH => $authType,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => false
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($ch);

        if ($httpCode == 200 && $data && (strpos($contentType, 'image') !== false || strlen($data) > 1000)) {
            file_put_contents($savePath, $data);
            return true;
        }
    }

    return false;
}

function analyzeImage($imagePath) {
    if (!function_exists('imagecreatefromjpeg')) {
        return ['Snapshot Fail', null, null];
    }

    $img = @imagecreatefromjpeg($imagePath);

    if (!$img) {
        $raw = @file_get_contents($imagePath);
        if ($raw) {
            $img = @imagecreatefromstring($raw);
        }
    }

    if (!$img) {
        return ['Snapshot Fail', 0, 0];
    }

    $w = imagesx($img);
    $h = imagesy($img);

    if ($w <= 0 || $h <= 0) {
        imagedestroy($img);
        return ['Snapshot Fail', 0, 0];
    }

    $sampleStepX = max(1, (int)floor($w / 80));
    $sampleStepY = max(1, (int)floor($h / 60));

    $values = [];
    $sum = 0;
    $count = 0;

    for ($y = 0; $y < $h; $y += $sampleStepY) {
        for ($x = 0; $x < $w; $x += $sampleStepX) {
            $rgb = imagecolorat($img, $x, $y);

            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            $gray = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

            $values[] = $gray;
            $sum += $gray;
            $count++;
        }
    }

    imagedestroy($img);

    if ($count <= 0) {
        return ['Snapshot Fail', 0, 0];
    }

    $brightness = $sum / $count;

    $variance = 0;
    foreach ($values as $v) {
        $variance += pow($v - $brightness, 2);
    }

    $contrast = sqrt($variance / $count);

    if ($brightness < 20 && $contrast < 15) {
        return ['Black Screen', round($brightness, 2), round($contrast, 2)];
    }

    if ($brightness < 35 && $contrast < 8) {
        return ['No Signal', round($brightness, 2), round($contrast, 2)];
    }

    return ['Normal', round($brightness, 2), round($contrast, 2)];
}

/* =========================================================
   ACTIVE DEVICE QUERY
   Active / active / ACTIVE / 1 / blank / NULL সব ধরবে
========================================================= */
$res = $conn->query("
    SELECT *
    FROM cctv_monitor_devices
    WHERE status IS NULL
       OR TRIM(status) = ''
       OR UPPER(TRIM(status)) = 'ACTIVE'
       OR TRIM(status) = '1'
    ORDER BY id ASC
");

if (!$res) {
    die("Device query failed: " . $conn->error);
}

$totalInserted = 0;
$totalDevices = 0;
$skippedDevices = 0;

while ($dev = $res->fetch_assoc()) {
    $deviceId = (int)$dev['id'];
    $atmId = trim($dev['atm_id'] ?? '');
    $brand = trim($dev['dvr_brand'] ?? '');

    $ip = trim($dev['dvr_ip'] ?? '');
    if ($ip === '') {
        $ip = trim($dev['ip_address'] ?? '');
    }

    $httpPort = (int)($dev['http_port'] ?: 80);
    $rtspPort = (int)($dev['rtsp_port'] ?: 554);
    $username = trim($dev['username'] ?? '');
    $password = trim($dev['password_text'] ?? '');
    $totalChannel = (int)($dev['total_channel'] ?: 4);

    if ($ip === '' || $atmId === '') {
        $skippedDevices++;
        continue;
    }

    $totalDevices++;

    $httpOk = portOpen($ip, $httpPort);
    $rtspOk = portOpen($ip, $rtspPort);

    $dvrOnline = ($httpOk || $rtspOk) ? 'Online' : 'Offline';
    $httpStatus = $httpOk ? 'Open' : 'Closed';
    $rtspStatus = $rtspOk ? 'Open' : 'Closed';

    for ($ch = 1; $ch <= $totalChannel; $ch++) {
        $now = date('Ymd_His');
        $fileName = $atmId . '_CH' . $ch . '_' . $now . '.jpg';
        $savePath = $SNAPSHOT_DIR . DIRECTORY_SEPARATOR . $fileName;
        $dbPath = 'cctv_snapshots/' . $fileName;

        $cameraStatus = 'Not Checked';
        $brightness = null;
        $contrast = null;

        if ($dvrOnline === 'Online' && $httpOk) {
            $url = getSnapshotUrl($brand, $ip, $httpPort, $ch);
            $ok = downloadSnapshot($url, $username, $password, $savePath);

            if ($ok) {
                [$cameraStatus, $brightness, $contrast] = analyzeImage($savePath);
            } else {
                $cameraStatus = 'Snapshot Fail';
            }
        } else {
            $cameraStatus = 'Snapshot Fail';
        }

        $stmt = $conn->prepare("
            INSERT INTO cctv_monitor_results
            (
                device_id, atm_id, channel_no,
                dvr_online, http_status, rtsp_status,
                camera_status, brightness, contrast_value,
                snapshot_path, hdd_status, backup_status,
                last_checked_at
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if ($stmt) {
            $hddStatus = 'Not Checked';
            $backupStatus = 'Not Checked';

            $stmt->bind_param(
                "isissssddsss",
                $deviceId,
                $atmId,
                $ch,
                $dvrOnline,
                $httpStatus,
                $rtspStatus,
                $cameraStatus,
                $brightness,
                $contrast,
                $dbPath,
                $hddStatus,
                $backupStatus
            );

            if ($stmt->execute()) {
                $totalInserted++;
            }

            $stmt->close();
        }
    }
}

if (isset($_GET['return']) && $_GET['return'] === 'dashboard') {
    header("Location: cctv_monitor_dashboard.php?run=done&devices=" . (int)$totalDevices . "&rows=" . (int)$totalInserted);
    exit;
}

echo "CCTV monitoring completed.<br>";
echo "Devices checked: " . (int)$totalDevices . "<br>";
echo "Skipped devices: " . (int)$skippedDevices . "<br>";
echo "Result rows inserted: " . (int)$totalInserted . "<br>";
echo '<br><a href="cctv_monitor_dashboard.php">Back to CCTV Monitor Dashboard</a>';