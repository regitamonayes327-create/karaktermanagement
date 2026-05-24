<?php
/**
 * Simple XLSX Writer - Generate Excel files without external libraries
 * Lightweight Office Open XML Spreadsheet generator
 * 
 * SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
 */

class SimpleXLSXWriter {
    private $sheets = [];
    private $currentSheet = 0;
    private $sharedStrings = [];
    private $sharedStringIndex = [];
    private $styles = [];
    
    // Predefined styles
    const STYLE_NORMAL = 0;
    const STYLE_HEADER = 1;
    const STYLE_SUBHEADER = 2;
    const STYLE_DATA = 3;
    const STYLE_REQUIRED = 4;
    const STYLE_EXAMPLE = 5;
    const STYLE_NOTE = 6;
    const STYLE_TITLE = 7;

    public function __construct() {
        $this->addSheet('Data Siswa');
    }

    public function addSheet($name) {
        $this->sheets[] = [
            'name' => $name,
            'rows' => [],
            'colWidths' => [],
            'merges' => []
        ];
        $this->currentSheet = count($this->sheets) - 1;
    }

    public function setColWidths($widths) {
        $this->sheets[$this->currentSheet]['colWidths'] = $widths;
    }

    public function addMerge($startCol, $startRow, $endCol, $endRow) {
        $this->sheets[$this->currentSheet]['merges'][] = [
            $this->colLetter($startCol) . $startRow,
            $this->colLetter($endCol) . $endRow
        ];
    }

    public function writeRow($data, $styles = []) {
        $row = [];
        foreach ($data as $i => $value) {
            $style = $styles[$i] ?? self::STYLE_NORMAL;
            $row[] = ['value' => $value, 'style' => $style];
        }
        $this->sheets[$this->currentSheet]['rows'][] = $row;
    }

    private function colLetter($col) {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26) - 1;
        }
        return $letter;
    }

    private function getSharedStringIndex($str) {
        if (!isset($this->sharedStringIndex[$str])) {
            $this->sharedStringIndex[$str] = count($this->sharedStrings);
            $this->sharedStrings[] = $str;
        }
        return $this->sharedStringIndex[$str];
    }

    private function escapeXml($str) {
        return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function save($filename) {
        $tempDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/_rels', 0755, true);
        mkdir($tempDir . '/xl', 0755, true);
        mkdir($tempDir . '/xl/_rels', 0755, true);
        mkdir($tempDir . '/xl/worksheets', 0755, true);

        // [Content_Types].xml
        file_put_contents($tempDir . '/[Content_Types].xml', $this->buildContentTypes());
        
        // _rels/.rels
        file_put_contents($tempDir . '/_rels/.rels', $this->buildRels());
        
        // xl/_rels/workbook.xml.rels
        file_put_contents($tempDir . '/xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        
        // xl/workbook.xml
        file_put_contents($tempDir . '/xl/workbook.xml', $this->buildWorkbook());
        
        // xl/styles.xml
        file_put_contents($tempDir . '/xl/styles.xml', $this->buildStyles());
        
        // xl/sharedStrings.xml - build sheets first to populate shared strings
        $sheetContents = [];
        foreach ($this->sheets as $i => $sheet) {
            $sheetContents[$i] = $this->buildSheet($sheet);
        }
        
        file_put_contents($tempDir . '/xl/sharedStrings.xml', $this->buildSharedStrings());
        
        // xl/worksheets/sheet{n}.xml
        foreach ($sheetContents as $i => $content) {
            file_put_contents($tempDir . '/xl/worksheets/sheet' . ($i + 1) . '.xml', $content);
        }

        // Create ZIP
        $zip = new ZipArchive();
        $zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addDirToZip($zip, $tempDir, '');
        $zip->close();

        // Cleanup
        $this->removeDir($tempDir);
    }

    public function output($filename) {
        $tempFile = sys_get_temp_dir() . '/' . uniqid() . '.xlsx';
        $this->save($tempFile);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    private function buildContentTypes() {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
' . $sheets . '
</Types>';
    }

    private function buildRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function buildWorkbookRels() {
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $rIdStyles = count($this->sheets) + 1;
        $rIdStrings = count($this->sheets) + 2;
        $rels .= '<Relationship Id="rId' . $rIdStyles . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . $rIdStrings . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function buildWorkbook() {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="' . $this->escapeXml($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>' . $sheets . '</sheets>
</workbook>';
    }

    private function buildStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="8">
    <font><sz val="11"/><name val="Calibri"/><color rgb="FF333333"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF1E3A5F"/></font>
    <font><sz val="10"/><name val="Calibri"/><color rgb="FF333333"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFCC0000"/></font>
    <font><i/><sz val="10"/><name val="Calibri"/><color rgb="FF666666"/></font>
    <font><sz val="9"/><name val="Calibri"/><color rgb="FF888888"/></font>
    <font><b/><sz val="14"/><name val="Calibri"/><color rgb="FF1E3A5F"/></font>
</fonts>
<fills count="7">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1B4F72"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFD6EAF8"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8F9FA"/></patternFill></fill>
</fills>
<borders count="3">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
        <left style="thin"><color rgb="FF9DC3E6"/></left>
        <right style="thin"><color rgb="FF9DC3E6"/></right>
        <top style="thin"><color rgb="FF9DC3E6"/></top>
        <bottom style="thin"><color rgb="FF9DC3E6"/></bottom>
        <diagonal/>
    </border>
    <border>
        <left style="thin"><color rgb="FFDDDDDD"/></left>
        <right style="thin"><color rgb="FFDDDDDD"/></right>
        <top style="thin"><color rgb="FFDDDDDD"/></top>
        <bottom style="thin"><color rgb="FFDDDDDD"/></bottom>
        <diagonal/>
    </border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="8">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="5" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment wrapText="1"/></xf>
    <xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>
</cellXfs>
</styleSheet>';
    }

    private function buildSharedStrings() {
        $count = count($this->sharedStrings);
        $strings = '';
        foreach ($this->sharedStrings as $str) {
            $strings .= '<si><t>' . $this->escapeXml($str) . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">' . $strings . '</sst>';
    }

    private function buildSheet($sheet) {
        $cols = '';
        if (!empty($sheet['colWidths'])) {
            $cols = '<cols>';
            foreach ($sheet['colWidths'] as $i => $width) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $merges = '';
        if (!empty($sheet['merges'])) {
            $merges = '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $m) {
                $merges .= '<mergeCell ref="' . $m[0] . ':' . $m[1] . '"/>';
            }
            $merges .= '</mergeCells>';
        }

        $rows = '';
        foreach ($sheet['rows'] as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $cells = '';
            foreach ($row as $colIdx => $cell) {
                $colRef = $this->colLetter($colIdx) . $rowNum;
                $value = $cell['value'];
                $style = $cell['style'];

                if ($value === '' || $value === null) {
                    $cells .= '<c r="' . $colRef . '" s="' . $style . '"/>';
                } elseif (is_numeric($value) && strlen($value) < 15 && $value[0] !== '0') {
                    $cells .= '<c r="' . $colRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
                } else {
                    $idx = $this->getSharedStringIndex((string)$value);
                    $cells .= '<c r="' . $colRef . '" t="s" s="' . $style . '"><v>' . $idx . '</v></c>';
                }
            }
            $rows .= '<row r="' . $rowNum . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
' . $cols . '
<sheetData>' . $rows . '</sheetData>
' . $merges . '
</worksheet>';
    }

    private function addDirToZip($zip, $dir, $base) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            $zipPath = ($base ? $base . '/' : '') . $file;
            if (is_dir($path)) {
                $this->addDirToZip($zip, $path, $zipPath);
            } else {
                $zip->addFile($path, $zipPath);
            }
        }
    }

    private function removeDir($dir) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
