<?php
if (!function_exists('calculateMinutesDiff')) {
    function calculateMinutesDiff(string $fromDateTime, string $toDateTime): int
    {
        $from = strtotime($fromDateTime);
        $to   = strtotime($toDateTime);

        if ($from === false || $to === false) {
            return 0;
        }

        $diff = (int) floor(($to - $from) / 60);
        return max(0, $diff);
    }
}

if (!function_exists('formatDownTimeFromMinutes')) {
    function formatDownTimeFromMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);

        $days = floor($minutes / 1440);
        $minutes %= 1440;

        $hours = floor($minutes / 60);
        $mins  = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' day';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' hour';
        }
        $parts[] = $mins . ' min';

        return implode(' ', $parts);
    }
}

if (!function_exists('getAmcAmount')) {
    function getAmcAmount(mysqli $conn, string $vendorName, string $serviceType): float
    {
        $vendorName  = trim($vendorName);
        $serviceType = strtoupper(trim($serviceType));

        if ($vendorName === '' || $serviceType === '') {
            return 0.0;
        }

        $stmt = $conn->prepare("
            SELECT amc_amount
            FROM vendor_amc_rates
            WHERE vendor_name = ?
              AND service_type = ?
              AND active_status = 1
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            return 0.0;
        }

        $stmt->bind_param("ss", $vendorName, $serviceType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float)($row['amc_amount'] ?? 0);
    }
}

if (!function_exists('getPenaltyPercent')) {
    function getPenaltyPercent(mysqli $conn, string $vendorName, string $serviceType, int $downMinutes): float
    {
        $vendorName  = trim($vendorName);
        $serviceType = strtoupper(trim($serviceType));
        $downMinutes = max(0, $downMinutes);

        if ($vendorName === '' || $serviceType === '') {
            return 0.0;
        }

        $stmt = $conn->prepare("
            SELECT penalty_percent
            FROM vendor_penalty_rules
            WHERE vendor_name = ?
              AND service_type = ?
              AND ? BETWEEN from_minute AND to_minute
              AND active_status = 1
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            return 0.0;
        }

        $stmt->bind_param("ssi", $vendorName, $serviceType, $downMinutes);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float)($row['penalty_percent'] ?? 0);
    }
}

if (!function_exists('calculatePenaltyAmount')) {
    function calculatePenaltyAmount(mysqli $conn, string $vendorName, string $serviceType, int $downMinutes): array
    {
        $amcAmount = getAmcAmount($conn, $vendorName, $serviceType);
        $penaltyPercent = getPenaltyPercent($conn, $vendorName, $serviceType, $downMinutes);

        $penaltyAmount = 0.0;
        if ($amcAmount > 0 && $penaltyPercent > 0) {
            $penaltyAmount = ($amcAmount * $penaltyPercent) / 100;
        }

        return [
            'amc_amount'      => (float)$amcAmount,
            'penalty_percent' => (float)$penaltyPercent,
            'penalty_amount'  => round((float)$penaltyAmount, 2)
        ];
    }
}