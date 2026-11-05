<?php

namespace App\Support;

/** Builds a styled, dependency-free multi-sheet XLSX workbook using OOXML. */
class JaspestFinanceXlsx
{
    public static function build(array $sheets, string $title, string $subtitle, string $filterText): string
    {
        if (! function_exists('gzdeflate')) {
            abort(500, 'Ekstensi PHP zlib belum aktif. Aktifkan zlib untuk membuat file Excel.');
        }
        if ($sheets === []) {
            abort(500, 'Tidak ada lembar kerja yang dapat diekspor.');
        }

        $xml = static function (mixed $value): string {
            $value = (string) ($value ?? '');
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
            return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        $columnName = static function (int $number): string {
            $name = '';
            while ($number > 0) {
                $remainder = ($number - 1) % 26;
                $name = chr(65 + $remainder).$name;
                $number = intdiv($number - 1, 26);
            }
            return $name;
        };

        $sheetFiles = [];
        $sheetMeta = [];
        foreach (array_values($sheets) as $sheetIndex => $sheetData) {
            $headers = array_values($sheetData['headers'] ?? []);
            $rows = array_values($sheetData['rows'] ?? []);
            if ($headers === []) {
                continue;
            }

            $columnCount = count($headers);
            $lastColumn = $columnName($columnCount);
            $lastRow = count($rows) + 4;
            $numericColumns = array_map('intval', $sheetData['numeric_columns'] ?? []);
            $widths = array_values($sheetData['widths'] ?? []);
            $cell = static function (string $reference, mixed $value, int $style = 4) use ($xml): string {
                return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';
            };

            $sheetRows = [];
            $sheetRows[] = '<row r="1" ht="32" customHeight="1">'.$cell('A1', $title, 1).'</row>';
            $sheetRows[] = '<row r="2" ht="22" customHeight="1">'.$cell('A2', $subtitle, 2).'</row>';
            $sheetRows[] = '<row r="3" ht="30" customHeight="1">'.$cell('A3', $filterText.' | Total: '.count($rows).' baris', 2).'</row>';

            $headerCells = '';
            foreach ($headers as $index => $header) {
                $headerCells .= $cell($columnName($index + 1).'4', $header, 3);
            }
            $sheetRows[] = '<row r="4" ht="32" customHeight="1">'.$headerCells.'</row>';

            foreach ($rows as $rowIndex => $values) {
                $excelRow = $rowIndex + 5;
                $values = array_values($values);
                $style = $rowIndex % 2 === 0 ? 4 : 5;
                $cells = '';
                for ($colIndex = 0; $colIndex < $columnCount; $colIndex++) {
                    $value = $values[$colIndex] ?? '';
                    $reference = $columnName($colIndex + 1).$excelRow;
                    if (in_array($colIndex, $numericColumns, true) && $value !== '' && is_numeric($value)) {
                        $numericStyle = is_int($value) ? 7 : 6;
                        $cells .= '<c r="'.$reference.'" s="'.$numericStyle.'"><v>'.(float) $value.'</v></c>';
                    } else {
                        $cells .= $cell($reference, $value, $style);
                    }
                }
                $sheetRows[] = '<row r="'.$excelRow.'" ht="34" customHeight="1">'.$cells.'</row>';
            }

            $cols = '';
            for ($i = 0; $i < $columnCount; $i++) {
                $width = max(10, min(60, (float) ($widths[$i] ?? 20)));
                $col = $i + 1;
                $cols .= '<col min="'.$col.'" max="'.$col.'" width="'.$width.'" customWidth="1"/>';
            }

            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$cols.'</cols><sheetData>'.implode('', $sheetRows).'</sheetData>'
                .'<autoFilter ref="A4:'.$lastColumn.$lastRow.'"/><mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
                .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
                .'</worksheet>';

            $sheetNumber = $sheetIndex + 1;
            $sheetFiles['xl/worksheets/sheet'.$sheetNumber.'.xml'] = $sheetXml;
            $sheetMeta[] = [
                'name' => substr((string) ($sheetData['name'] ?? 'Sheet '.$sheetNumber), 0, 31),
                'number' => $sheetNumber,
                'last_column' => $lastColumn,
                'last_row' => $lastRow,
            ];
        }

        if ($sheetMeta === []) {
            abort(500, 'Struktur lembar kerja Excel tidak valid.');
        }

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00;[Red]-#,##0.00"/></numFmts>'
            .'<fonts count="4"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF102548"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF16865F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="right" vertical="top"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="right" vertical="top"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $contentOverrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $workbookSheets = '';
        $workbookRels = '';
        foreach ($sheetMeta as $index => $meta) {
            $id = $index + 1;
            $safeName = $xml($meta['name']);
            $workbookSheets .= '<sheet name="'.$safeName.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $workbookRels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$meta['number'].'.xml"/>';
            $contentOverrides .= '<Override PartName="/xl/worksheets/sheet'.$meta['number'].'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $styleRelId = count($sheetMeta) + 1;
        $workbookRels .= '<Relationship Id="rId'.$styleRelId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$contentOverrides.'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$workbookSheets.'</sheets><calcPr calcId="191029"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$workbookRels.'</Relationships>',
            'xl/styles.xml' => $styles,
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:creator>JASPEST</dc:creator><dc:title>'.$xml($title).'</dc:title><dc:subject>Laporan Keuangan dan Akuntansi</dc:subject></cp:coreProperties>',
        ];
        foreach ($sheetFiles as $path => $contents) $files[$path] = $contents;

        return self::zip($files);
    }

    private static function zip(array $files): string
    {
        $localData = '';
        $centralDirectory = '';
        $offset = 0;
        foreach ($files as $name => $contents) {
            $compressed = gzdeflate((string) $contents, 6);
            if ($compressed === false) abort(500, 'Gagal mengompres bagian file Excel.');
            $crc = crc32((string) $contents);
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen((string) $contents);
            $nameLength = strlen((string) $name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLength, 0);
            $localRecord = $localHeader.$name.$compressed;
            $localData .= $localRecord;
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($localRecord);
        }
        $entryCount = count($files);
        return $localData.$centralDirectory.pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($centralDirectory), strlen($localData), 0);
    }
}
