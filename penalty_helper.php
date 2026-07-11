<?php
function parsePenaltyMinutes($text) {
    $text = strtolower(trim((string)$text));
    if ($text === '') return 0;

    $days = 0;
    $hours = 0;
    $mins = 0;

    if (preg_match('/(\d+)\s*day/', $text, $m)) $days = (int)$m[1];
    if (preg_match('/(\d+)\s*hour/', $text, $m)) $hours = (int)$m[1];
    if (preg_match('/(\d+)\s*min/', $text, $m)) $mins = (int)$m[1];

    return ($days * 1440) + ($hours * 60) + $mins;
}

function formatPenaltyMinutes($minutes) {
    $minutes = max(0, (int)$minutes);

    $days = intdiv($minutes, 1440);
    $minutes %= 1440;
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;

    $parts = [];
    if ($days > 0) $parts[] = $days . ' day';
    if ($hours > 0) $parts[] = $hours . ' hour';
    $parts[] = $mins . ' min';

    return implode(' ', $parts);
}

function calculatePenaltyData($conn, $incidentRow) {
    $problem = $incidentRow['problem'] ?? '';
    $atmVendor = trim($incidentRow['atm_vendor'] ?? '');
    $upsVendor = trim($incidentRow['ups_vendor'] ?? '');
    $downTimeText = trim($incidentRow['down_time'] ?? '');
    $downTimeMinutes = parsePenaltyMinutes($downTimeText);

    $vendorType = 'ATM';
    $vendorName = '';

    $pmStmt = $conn->prepare("
        SELECT responsible_vendor_type
        FROM problem_master
        WHERE problem_name = ?
        LIMIT 1
    ");
    if ($pmStmt) {
        $pmStmt->bind_param("s", $problem);
        $pmStmt->execute();
        $pmResult = $pmStmt->get_result();
        if ($pmResult && $pmResult->num_rows > 0) {
            $pmRow = $pmResult->fetch_assoc();
            $vendorType = strtoupper(trim($pmRow['responsible_vendor_type'] ?? 'ATM'));
        }
    }

    if ($vendorType === 'UPS') {
        $vendorName = $upsVendor;
    } elseif ($vendorType === 'ATM') {
        $vendorName = $atmVendor;
    } else {
        $vendorName = '';
    }

    if ($vendorName === '' || $vendorType === 'NONE') {
        return [
            'vendor_name' => '',
            'vendor_type' => $vendorType,
            'down_time_minutes' => $downTimeMinutes,
            'penalty_percent' => 0,
            'penalty_amount' => 0,
            'amc_amount' => 0
        ];
    }

    $amcAmount = 0;
    $amcStmt = $conn->prepare("
        SELECT amc_amount
        FROM vendor_amc_rates
        WHERE vendor_name = ?
          AND vendor_type = ?
          AND active_status = 1
        LIMIT 1
    ");
    if ($amcStmt) {
        $amcStmt->bind_param("ss", $vendorName, $vendorType);
        $amcStmt->execute();
        $amcResult = $amcStmt->get_result();
        if ($amcResult && $amcResult->num_rows > 0) {
            $amcRow = $amcResult->fetch_assoc();
            $amcAmount = (float)$amcRow['amc_amount'];
        }
    }

    $penaltyPercent = 0;
    $ruleStmt = $conn->prepare("
        SELECT penalty_percent
        FROM vendor_penalty_rules
        WHERE vendor_type = ?
          AND active_status = 1
          AND ? BETWEEN from_minute AND to_minute
        ORDER BY from_minute DESC
        LIMIT 1
    ");
    if ($ruleStmt) {
        $ruleStmt->bind_param("si", $vendorType, $downTimeMinutes);
        $ruleStmt->execute();
        $ruleResult = $ruleStmt->get_result();
        if ($ruleResult && $ruleResult->num_rows > 0) {
            $ruleRow = $ruleResult->fetch_assoc();
            $penaltyPercent = (float)$ruleRow['penalty_percent'];
        }
    }

    $penaltyAmount = ($amcAmount * $penaltyPercent) / 100;

    return [
        'vendor_name' => $vendorName,
        'vendor_type' => $vendorType,
        'down_time_minutes' => $downTimeMinutes,
        'penalty_percent' => $penaltyPercent,
        'penalty_amount' => $penaltyAmount,
        'amc_amount' => $amcAmount
    ];
}

function upsertFinalPenalty($conn, $incidentRow, $userId) {
    $calc = calculatePenaltyData($conn, $incidentRow);

    if ($calc['vendor_name'] === '' || $calc['vendor_type'] === 'NONE') {
        return true;
    }

    $checkStmt = $conn->prepare("
        SELECT penalty_id
        FROM incident_penalties
        WHERE incident_id = ?
          AND vendor_name = ?
          AND vendor_type = ?
        LIMIT 1
    ");
    if (!$checkStmt) return false;

    $incidentId = (int)$incidentRow['incident_id'];
    $vendorName = $calc['vendor_name'];
    $vendorType = $calc['vendor_type'];

    $checkStmt->bind_param("iss", $incidentId, $vendorName, $vendorType);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $atmId = $incidentRow['atm_id'] ?? '';
    $atmName = $incidentRow['atm_name'] ?? '';
    $incidentName = $incidentRow['problem'] ?? '';
    $originalDownTime = $incidentRow['down_time'] ?? '';
    $originalDownTimeMinutes = (int)$calc['down_time_minutes'];
    $penaltyPercent = (float)$calc['penalty_percent'];
    $penaltyAmount = (float)$calc['penalty_amount'];

    if ($checkResult && $checkResult->num_rows > 0) {
        $row = $checkResult->fetch_assoc();
        $penaltyId = (int)$row['penalty_id'];

        $stmt = $conn->prepare("
            UPDATE incident_penalties
            SET atm_id = ?,
                atm_name = ?,
                incident_name = ?,
                original_down_time = ?,
                original_down_time_minutes = ?,
                penalty_percent = ?,
                penalty_amount = CASE WHEN is_amount_overridden = 1 THEN penalty_amount ELSE ? END,
                updated_by = ?
            WHERE penalty_id = ?
        ");
        if (!$stmt) return false;

        $stmt->bind_param(
            "ssssiddii",
            $atmId,
            $atmName,
            $incidentName,
            $originalDownTime,
            $originalDownTimeMinutes,
            $penaltyPercent,
            $penaltyAmount,
            $userId,
            $penaltyId
        );

        return $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO incident_penalties
            (
                incident_id, atm_id, atm_name, vendor_name, vendor_type,
                incident_name, original_down_time, original_down_time_minutes,
                penalty_percent, penalty_amount, created_by, updated_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) return false;

        $stmt->bind_param(
            "issssssiddii",
            $incidentId,
            $atmId,
            $atmName,
            $vendorName,
            $vendorType,
            $incidentName,
            $originalDownTime,
            $originalDownTimeMinutes,
            $penaltyPercent,
            $penaltyAmount,
            $userId,
            $userId
        );

        return $stmt->execute();
    }
}
?>