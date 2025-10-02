<?php
// /public_html/accounting/api/util.php
// Helper functions for escaping and CSV parsing

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function parse_csv_upload(array $file, array $requiredHeaders): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('檔案上傳失敗');
    }
    $tmpPath = $file['tmp_name'];
    if (!is_readable($tmpPath)) {
        throw new RuntimeException('暫存檔無法讀取');
    }
    $handle = fopen($tmpPath, 'r');
    if ($handle === false) {
        throw new RuntimeException('無法開啟檔案');
    }
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        throw new RuntimeException('CSV 無表頭');
    }
    $headers = array_map('trim', $headers);
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers, true)) {
            fclose($handle);
            throw new RuntimeException('缺少欄位：'.$header);
        }
    }
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $row = array_map('trim', $row);
        $assoc = [];
        foreach ($headers as $index => $header) {
            $assoc[$header] = $row[$index] ?? '';
        }
        $rows[] = $assoc;
    }
    fclose($handle);
    return $rows;
}
