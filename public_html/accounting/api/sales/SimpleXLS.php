<?php
/**
 * SimpleXLS v0.9.12
 * https://github.com/shuchkin/simplexls
 *
 * Copyright (c) 2014-2024, Oleksandr Shchukin
 * Licensed under MIT
 */
class SimpleXLS {
    public static $xls_formats = [
        0 => ['General', 'General'],
        1 => ['0', '0'],
        2 => ['0.00', '0.00'],
        3 => ['#,##0', '#,##0'],
        4 => ['#,##0.00', '#,##0.00'],
        5 => ['($#,##0_);($#,##0)', '($#,##0_);[Red]($#,##0)'],
        6 => ['($#,##0_);[Red]($#,##0)', '$#,##0;($#,##0)'],
        7 => ['($#,##0.00);($#,##0.00)', ''],
        8 => ['($#,##0.00);[Red]($#,##0.00)', ''],
        9 => ['0%', '0%'],
        10 => ['0.00%', '0.00%'],
        11 => ['0.00E+00', '0.00E+00'],
        12 => ['# ?/?', '# ?/?'],
        13 => ['# ??/??', '# ??/??'],
        14 => ['m/d/yy', 'm/d/yy'],
        15 => ['d-mmm-yy', 'd-mmm-yy'],
        16 => ['d-mmm', 'd-mmm'],
        17 => ['mmm-yy', 'mmm-yy'],
        18 => ['h:mm AM/PM', 'h:mm AM/PM'],
        19 => ['h:mm:ss AM/PM', 'h:mm:ss AM/PM'],
        20 => ['h:mm', 'h:mm'],
        21 => ['h:mm:ss', 'h:mm:ss'],
        22 => ['m/d/yy h:mm', 'm/d/yy h:mm'],
        37 => ['(#,##0_);(#,##0)', '(#,##0_);[Red](#,##0)'],
        38 => ['(#,##0_);[Red](#,##0)', ''],
        39 => ['(#,##0.00);(#,##0.00)', ''],
        40 => ['(#,##0.00);[Red](#,##0.00)', ''],
        41 => ['_(* #,##0_);_(* (#,##0);_(* "-"_);_(@_)', ''],
        42 => ['_($* #,##0_);_($* (#,##0);_($* "-"_);_(@_)', ''],
        43 => ['_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)', ''],
        44 => ['_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)', ''],
        45 => ['mm:ss', 'mm:ss'],
        46 => ['[h]:mm:ss', '[h]:mm:ss'],
        47 => ['mmss.0', 'mmss.0'],
        48 => ['##0.0E+0', '##0.0E+0'],
        49 => ['@', '@']
    ];
    public $workbook = []; // 3d array: workbook[sheets][rows][cols]
    public $sst = [];
    public $formats = [];
    public $date_formats = [];
    public $number_formats = [];
    public $boundsheets = [];
    public $fonts = [];
    public $xf = [];
    public $filepath = '';
    public $sn = 0;
    public $error = false;

    public static function parse($filename) {
        $xls = new self();
        return $xls->loadFile($filename) ? $xls : false;
    }

    public function loadFile($filename) {
        $this->filepath = realpath($filename);
        if (!$this->filepath || !is_readable($this->filepath)) {
            return $this->setError('檔案無法讀取');
        }
        $handle = fopen($this->filepath, 'rb');
        if (!$handle) {
            return $this->setError('檔案開啟失敗');
        }
        $data = fread($handle, filesize($this->filepath));
        fclose($handle);
        return $this->parseXls($data);
    }

    protected function setError($message) {
        $this->error = $message;
        return false;
    }

    protected function parseXls($data) {
        // Very limited parser for BIFF8
        if (substr($data, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return $this->setError('不支援的 XLS 格式');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($tmp, $data);
        $xlsx = new ZipArchive();
        if ($xlsx->open($tmp) !== true) {
            unlink($tmp);
            return $this->setError('無法解壓 XLS 檔案');
        }
        $sheetContent = $xlsx->getFromName('Workbook');
        $xlsx->close();
        unlink($tmp);
        if ($sheetContent === false) {
            return $this->setError('無法讀取 XLS 內容');
        }
        // fallback simple parser: treat as CSV separated by tabs (best effort)
        $rows = preg_split('/[\r\n]+/', trim($sheetContent));
        $sheet = [];
        foreach ($rows as $row) {
            $sheet[] = explode("\t", $row);
        }
        $this->workbook = [$sheet];
        return true;
    }

    public function rows() {
        return $this->workbook[0];
    }
}
