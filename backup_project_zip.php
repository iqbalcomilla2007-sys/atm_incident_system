<?php
date_default_timezone_set('Asia/Dhaka');

/* ---------- HELPER ---------- */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ---------- CONFIG ---------- */
$projectDir = __DIR__;
$backupDir  = __DIR__ . '/backup';

/* ---------- CREATE BACKUP FOLDER ---------- */
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

/* ---------- ZIP FILE NAME ---------- */
$fileName = 'project_backup_' . date('Ymd_His') . '.zip';
$zipFile  = $backupDir . '/' . $fileName;

/* ---------- CREATE ZIP ---------- */
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Could not create ZIP file.');
}

/* ---------- ITERATE FILES ---------- */
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($files as $file) {
    $filePath = $file->getPathname();

    /* backup folder skip */
    if (strpos($filePath, $backupDir) === 0) {
        continue;
    }

    /* relative path */
    $relativePath = substr($filePath, strlen($projectDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

/* ---------- FORCE DOWNLOAD ---------- */
if (file_exists($zipFile)) {

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
    header('Content-Length: ' . filesize($zipFile));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($zipFile);
    exit;
}

/* ---------- FALLBACK ---------- */
echo "Backup created but download failed.<br>";
echo "Path: " . h($zipFile);
?>