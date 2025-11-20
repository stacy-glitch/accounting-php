<?php
declare(strict_types=1);

/**
 * 讀取最新匯款帳號表，建立「帳號 ➜ 客戶名稱」對應。
 */
function mega_load_remittance_account_map(string $uploadsRoot): array {
  $latestPath = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . '/remittance/latest.json';
  if (!is_file($latestPath)) {
    return [];
  }
  $json = file_get_contents($latestPath);
  if ($json === false) {
    return [];
  }
  $data = json_decode($json, true);
  if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) {
    return [];
  }

  $map = [];
  foreach ($data['records'] as $row) {
    if (!is_array($row)) {
      continue;
    }
    $account = mega_normalize_account_key((string) ($row['account'] ?? ''));
    if ($account === '' || strlen($account) < 4) {
      continue;
    }
    $name = trim((string) ($row['customer_name'] ?? $row['customer'] ?? $row['customer_code'] ?? ''));
    if ($name === '') {
      continue;
    }
    $key = '_' . $account; // 將帳號轉成字串 key，避免純數字被當作 int key
    if (!isset($map[$key])) {
      $map[$key] = $name;
    }
  }
  return $map;
}

/**
 * 將含帳號的備註替換為對應客戶名稱。
 */
function mega_replace_note_with_customer_from_accounts(array $records, array $accountMap): array {
  if (empty($records) || empty($accountMap)) {
    return $records;
  }

  foreach ($records as &$record) {
    if (!is_array($record)) {
      continue;
    }
    $noteRaw = (string) ($record['note'] ?? '');
    $normalizedNote = mega_normalize_account_key($noteRaw);
    if ($normalizedNote === '' || strlen($normalizedNote) < 4) {
      continue;
    }
    $noteKey = '_' . $normalizedNote;
    if (isset($accountMap[$noteKey])) {
      $record['note'] = $accountMap[$noteKey];
      continue;
    }
    foreach ($accountMap as $accountKey => $customerName) {
      $accountKeyStr = (string) $accountKey;
      if ($accountKeyStr === '' || strlen($accountKeyStr) < 5 /* 前綴 + 至少4碼帳號 */) {
        continue;
      }
      // accountKeyStr 帶有 '_' 前綴，去掉再比對
      $plainAccount = substr($accountKeyStr, 1);
      if ($plainAccount !== '' && strpos($normalizedNote, $plainAccount) !== false) {
        $record['note'] = $customerName;
        break;
      }
    }
  }
  unset($record);

  return $records;
}

function mega_normalize_account_key(string $value): string {
  $trimmed = trim($value);
  if ($trimmed === '') {
    return '';
  }
  $normalized = preg_replace('/[^0-9A-Za-z]/', '', $trimmed);
  if (!is_string($normalized)) {
    return '';
  }
  return strtoupper($normalized);
}
