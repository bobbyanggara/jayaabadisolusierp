<?php

namespace App\Support;

/**
 * Builds a styled, dependency-free multi-sheet XLSX workbook using OOXML.
 *
 * Sheet definition keys:
 *  - name      (string)  worksheet tab name
 *  - heading   (string)  appended to the workbook title in row 1
 *  - headers   (array)   column headers
 *  - rows      (array)   data rows; a cell may be a scalar or [type, value] to override the column type
 *  - types     (array)   per-column type: text | center | int | money | date | pct
 *  - widths    (array)   per-column width
 *  - totals    (array)   column indexes that get a SUBTOTAL row (respects Excel filters)
 *  - tab       (string)  ARGB tab color
 *  - show_count (bool)   show "Total: N baris" in row 3 (default true)
 */
class JaspestFinanceXlsx
{
    private const STYLE_TITLE = 1;
    private const STYLE_SUBTITLE = 2;
    private const STYLE_HEADER = 3;
    private const STYLE_TOTAL_LABEL = 16;
    private const STYLE_TOTAL_MONEY = 17;
    private const STYLE_TOTAL_INT = 18;

    /** @var array<string, array{0:int,1:int}> [normal row style, zebra row style] */
    private const BODY_STYLES = [
        'text' => [4, 5],
        'money' => [6, 7],
        'int' => [8, 9],
        'date' => [10, 11],
        'center' => [12, 13],
        'pct' => [14, 15],
    ];

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
        $number = static function (float $value): string {
            $text = number_format($value, 4, '.', '');
            $text = rtrim(rtrim($text, '0'), '.');
            return in_array($text, ['', '-', '-0'], true) ? '0' : $text;
        };
        $dateSerial = static function (mixed $value): ?int {
            $value = substr(trim((string) ($value ?? '')), 0, 10);
            if ($value === '') {
                return null;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
            if ($date === false) {
                return null;
            }
            return intdiv($date->getTimestamp(), 86400) + 25569;
        };
        $textCell = static function (string $reference, mixed $value, int $style) use ($xml): string {
            return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';
        };

        $sheetFiles = [];
        $sheetMeta = [];
        foreach (array_values($sheets) as $sheetIndex => $sheetData) {
            $headers = array_values($sheetData['headers'] ?? []);
            $rows = array_values($sheetData['rows'] ?? []);
            if (count($headers) < 2) {
                continue;
            }

            $columnCount = count($headers);
            $lastColumn = $columnName($columnCount);
            $types = array_values($sheetData['types'] ?? []);
            $widths = array_values($sheetData['widths'] ?? []);
            $totalColumns = array_map('intval', $sheetData['totals'] ?? []);
            $sheetTitle = $title.(! empty($sheetData['heading']) ? '  —  '.$sheetData['heading'] : '');
            $showCount = $sheetData['show_count'] ?? true;
            $countText = $showCount ? ' | Total: '.count($rows).' baris' : '';

            $sheetRows = [];
            $sheetRows[] = '<row r="1" ht="32" customHeight="1">'.$textCell('A1', $sheetTitle, self::STYLE_TITLE).'</row>';
            $sheetRows[] = '<row r="2" ht="22" customHeight="1">'.$textCell('A2', $subtitle, self::STYLE_SUBTITLE).'</row>';
            $sheetRows[] = '<row r="3" ht="30" customHeight="1">'.$textCell('A3', $filterText.$countText, self::STYLE_SUBTITLE).'</row>';

            $headerCells = '';
            foreach ($headers as $index => $header) {
                $headerCells .= $textCell($columnName($index + 1).'4', $header, self::STYLE_HEADER);
            }
            $sheetRows[] = '<row r="4" ht="32" customHeight="1">'.$headerCells.'</row>';

            $sums = [];
            if ($rows === []) {
                $cells = $textCell('A5', 'Tidak ada data untuk filter ini.', 4);
                for ($colIndex = 1; $colIndex < $columnCount; $colIndex++) {
                    $cells .= '<c r="'.$columnName($colIndex + 1).'5" s="4"/>';
                }
                $sheetRows[] = '<row r="5">'.$cells.'</row>';
                $lastDataRow = 5;
            } else {
                foreach ($rows as $rowIndex => $values) {
                    $excelRow = $rowIndex + 5;
                    $values = array_values($values);
                    $zebra = $rowIndex % 2 === 0 ? 0 : 1;
                    $cells = '';
                    for ($colIndex = 0; $colIndex < $columnCount; $colIndex++) {
                        $value = $values[$colIndex] ?? '';
                        $type = (string) ($types[$colIndex] ?? 'text');
                        if (is_array($value)) {
                            $type = (string) ($value[0] ?? 'text');
                            $value = $value[1] ?? '';
                        }
                        if (! isset(self::BODY_STYLES[$type])) {
                            $type = 'text';
                        }
                        $style = self::BODY_STYLES[$type][$zebra];
                        $reference = $columnName($colIndex + 1).$excelRow;

                        if (in_array($type, ['money', 'int', 'pct'], true)) {
                            if ($value === '' || $value === null || ! is_numeric($value)) {
                                $cells .= '<c r="'.$reference.'" s="'.$style.'"/>';
                            } else {
                                $cells .= '<c r="'.$reference.'" s="'.$style.'"><v>'.$number((float) $value).'</v></c>';
                                $sums[$colIndex] = ($sums[$colIndex] ?? 0.0) + (float) $value;
                            }
                        } elseif ($type === 'date') {
                            $serial = $dateSerial($value);
                            $cells .= $serial === null
                                ? '<c r="'.$reference.'" s="'.$style.'"/>'
                                : '<c r="'.$reference.'" s="'.$style.'"><v>'.$serial.'</v></c>';
                        } else {
                            $cells .= $textCell($reference, $value, $style);
                        }
                    }
                    $sheetRows[] = '<row r="'.$excelRow.'">'.$cells.'</row>';
                }
                $lastDataRow = count($rows) + 4;
            }

            $lastRow = $lastDataRow;
            if ($rows !== [] && $totalColumns !== []) {
                $totalRow = $lastDataRow + 1;
                $lastRow = $totalRow;
                $cells = '';
                for ($colIndex = 0; $colIndex < $columnCount; $colIndex++) {
                    $reference = $columnName($colIndex + 1).$totalRow;
                    if ($colIndex === 0) {
                        $cells .= $textCell($reference, 'TOTAL', self::STYLE_TOTAL_LABEL);
                    } elseif (in_array($colIndex, $totalColumns, true)) {
                        $letter = $columnName($colIndex + 1);
                        $style = ($types[$colIndex] ?? 'money') === 'int' ? self::STYLE_TOTAL_INT : self::STYLE_TOTAL_MONEY;
                        $cells .= '<c r="'.$reference.'" s="'.$style.'"><f>SUBTOTAL(109,'.$letter.'5:'.$letter.$lastDataRow.')</f><v>'.$number((float) ($sums[$colIndex] ?? 0)).'</v></c>';
                    } else {
                        $cells .= '<c r="'.$reference.'" s="'.self::STYLE_TOTAL_LABEL.'"/>';
                    }
                }
                $sheetRows[] = '<row r="'.$totalRow.'" ht="22" customHeight="1">'.$cells.'</row>';
            }

            $cols = '';
            for ($i = 0; $i < $columnCount; $i++) {
                $width = max(8, min(70, (float) ($widths[$i] ?? 20)));
                $col = $i + 1;
                $cols .= '<col min="'.$col.'" max="'.$col.'" width="'.$width.'" customWidth="1"/>';
            }

            $tab = (string) ($sheetData['tab'] ?? 'FF16865F');
            $selected = $sheetIndex === 0 ? ' tabSelected="1"' : '';
            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheetPr><tabColor rgb="'.$xml($tab).'"/><pageSetUpPr fitToPage="1"/></sheetPr>'
                .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
                .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"'.$selected.'><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A5" sqref="A5"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="16"/><cols>'.$cols.'</cols><sheetData>'.implode('', $sheetRows).'</sheetData>'
                .'<autoFilter ref="A4:'.$lastColumn.$lastDataRow.'"/>'
                .'<mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
                .'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.25" footer="0.25"/>'
                .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
                .'<headerFooter><oddFooter>&amp;LJASPEST&amp;CHalaman &amp;P dari &amp;N&amp;RDicetak &amp;D</oddFooter></headerFooter>'
                .'</worksheet>';

            $sheetNumber = count($sheetMeta) + 1;
            $sheetFiles['xl/worksheets/sheet'.$sheetNumber.'.xml'] = $sheetXml;
            $sheetMeta[] = [
                'name' => substr((string) ($sheetData['name'] ?? 'Sheet '.$sheetNumber), 0, 31),
                'number' => $sheetNumber,
                'last_column' => $lastColumn,
                'last_data_row' => $lastDataRow,
            ];
        }

        if ($sheetMeta === []) {
            abort(500, 'Struktur lembar kerja Excel tidak valid.');
        }

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;Rp&quot; #,##0;[Red]-&quot;Rp&quot; #,##0"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="10"/><color rgb="FF1E293B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF102548"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF102548"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF16865F"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE3F3EC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD5DDE6"/></left><right style="thin"><color rgb="FFD5DDE6"/></right><top style="thin"><color rgb="FFD5DDE6"/></top><bottom style="thin"><color rgb="FFD5DDE6"/></bottom><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD5DDE6"/></left><right style="thin"><color rgb="FFD5DDE6"/></right><top style="medium"><color rgb="FF16865F"/></top><bottom style="thin"><color rgb="FFD5DDE6"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="19">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="10" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="10" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="164" fontId="4" fillId="5" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="3" fontId="4" fillId="5" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" indent="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

        $contentOverrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $workbookSheets = '';
        $workbookRels = '';
        $definedNames = '';
        foreach ($sheetMeta as $index => $meta) {
            $id = $index + 1;
            $safeName = $xml($meta['name']);
            $workbookSheets .= '<sheet name="'.$safeName.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $workbookRels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$meta['number'].'.xml"/>';
            $contentOverrides .= '<Override PartName="/xl/worksheets/sheet'.$meta['number'].'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $quoted = "'".$safeName."'";
            $definedNames .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$index.'" hidden="1">'.$quoted.'!$A$4:$'.$meta['last_column'].'$'.$meta['last_data_row'].'</definedName>';
            $definedNames .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$index.'">'.$quoted.'!$4:$4</definedName>';
        }
        $styleRelId = count($sheetMeta) + 1;
        $workbookRels .= '<Relationship Id="rId'.$styleRelId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $created = gmdate('Y-m-d\TH:i:s\Z');
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$contentOverrides.'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$workbookSheets.'</sheets><definedNames>'.$definedNames.'</definedNames><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$workbookRels.'</Relationships>',
            'xl/styles.xml' => $styles,
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$xml($title).'</dc:title><dc:subject>Laporan Kas dan Bank</dc:subject><dc:creator>JASPEST</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created></cp:coreProperties>',
        ];
        foreach ($sheetFiles as $path => $contents) {
            $files[$path] = $contents;
        }

        return self::zip($files);
    }

    private static function zip(array $files): string
    {
        $localData = '';
        $centralDirectory = '';
        $offset = 0;
        foreach ($files as $name => $contents) {
            $compressed = gzdeflate((string) $contents, 6);
            if ($compressed === false) {
                abort(500, 'Gagal mengompres bagian file Excel.');
            }
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
