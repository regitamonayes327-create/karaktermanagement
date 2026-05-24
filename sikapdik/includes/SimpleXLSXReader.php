<?php
/**
 * Simple XLSX Reader - Read Excel files without external libraries
 * Reads Office Open XML Spreadsheet format
 * 
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

class SimpleXLSXReader {
    private $sharedStrings = [];
    private $sheets = [];
    private $error = '';

    public function getError() {
        return $this->error;
    }

    /**
     * Open and parse an XLSX file
     */
    public function open($filename) {
        if (!file_exists($filename)) {
            $this->error = 'File tidak ditemukan.';
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            $this->error = 'Gagal membuka file Excel. Pastikan file format .xlsx valid.';
            return false;
        }

        // Read shared strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $this->parseSharedStrings($sharedStringsXml);
        }

        // Read first sheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            $zip->close();
            $this->error = 'Tidak dapat membaca worksheet.';
            return false;
        }

        $this->sheets[0] = $this->parseSheet($sheetXml);
        $zip->close();
        return true;
    }

    /**
     * Get all rows from first sheet
     */
    public function getRows() {
        return $this->sheets[0] ?? [];
    }

    /**
     * Get header row (first row)
     */
    public function getHeader() {
        $rows = $this->getRows();
        return $rows[0] ?? [];
    }

    /**
     * Get data rows (excluding header)
     */
    public function getDataRows() {
        $rows = $this->getRows();
        return array_slice($rows, 1);
    }

    private function parseSharedStrings($xml) {
        // Suppress warnings for malformed XML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        libxml_clear_errors();

        $elements = $dom->getElementsByTagName('si');
        foreach ($elements as $el) {
            $text = '';
            // Handle <t> direct text
            $tNodes = $el->getElementsByTagName('t');
            foreach ($tNodes as $t) {
                $text .= $t->nodeValue;
            }
            $this->sharedStrings[] = $text;
        }
    }

    private function parseSheet($xml) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        libxml_clear_errors();

        $rows = [];
        $rowElements = $dom->getElementsByTagName('row');

        foreach ($rowElements as $rowEl) {
            $rowNum = (int) $rowEl->getAttribute('r');
            $rowData = [];
            
            $cells = $rowEl->getElementsByTagName('c');
            foreach ($cells as $cell) {
                $ref = $cell->getAttribute('r');
                $colIndex = $this->colToIndex($ref);
                $type = $cell->getAttribute('t');
                
                $valueNode = $cell->getElementsByTagName('v')->item(0);
                $value = $valueNode ? $valueNode->nodeValue : '';

                // Handle inline string
                $inlineStr = $cell->getElementsByTagName('is');
                if ($inlineStr->length > 0) {
                    $tNodes = $inlineStr->item(0)->getElementsByTagName('t');
                    $value = '';
                    foreach ($tNodes as $t) {
                        $value .= $t->nodeValue;
                    }
                } elseif ($type === 's') {
                    // Shared string
                    $value = $this->sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'b') {
                    // Boolean
                    $value = $value ? 'TRUE' : 'FALSE';
                }

                // Pad array to match column index
                while (count($rowData) < $colIndex) {
                    $rowData[] = '';
                }
                $rowData[$colIndex] = trim($value);
            }
            
            $rows[$rowNum] = $rowData;
        }

        // Sort by row number and re-index
        ksort($rows);
        return array_values($rows);
    }

    private function colToIndex($cellRef) {
        preg_match('/^([A-Z]+)/', $cellRef, $matches);
        $col = $matches[1] ?? 'A';
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - 64);
        }
        return $index - 1;
    }
}
