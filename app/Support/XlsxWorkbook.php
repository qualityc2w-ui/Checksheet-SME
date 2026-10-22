<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class XlsxWorkbook
{
    public function create(string $sheetName, array $headings, iterable $rows): string
    {
        $path = tempnam(storage_path('app'), 'qc-export-');
        if ($path === false) {
            throw new RuntimeException('File ekspor Excel tidak dapat dibuat.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('File ekspor Excel tidak dapat dibuka.');
        }

        $sheetRows = array_merge(
            [$headings],
            is_array($rows) ? array_values($rows) : iterator_to_array($rows, false),
        );
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        foreach ($sheetRows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $sheet .= '<row r="'.$excelRow.'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                $text = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
                $sheet .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        foreach ($files as $name => $contents) {
            if (! $zip->addFromString($name, $contents)) {
                $zip->close();
                @unlink($path);
                throw new RuntimeException('File ekspor Excel gagal dibuat.');
            }
        }

        if (! $zip->close()) {
            @unlink($path);
            throw new RuntimeException('File ekspor Excel gagal diselesaikan.');
        }

        return $path;
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}
