<?php
/**
 * Minimal .xlsx reader — no external libraries. Reads the first worksheet
 * of a real Excel file (not a renamed CSV) and returns it as an array of
 * rows, each row an array of string cell values, in column order.
 * Handles shared strings, inline strings, and blank/skipped cells.
 */
function read_xlsx_rows(string $filePath): array
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Could not open this file as an Excel workbook.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sst = simplexml_load_string($sharedXml);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    // Rich text runs — concatenate all <r><t> fragments.
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    // Find the first sheet's internal path via workbook.xml + rels (falls
    // back to the conventional sheet1.xml if anything is missing).
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if ($wb !== false && $rels !== false && isset($wb->sheets->sheet[0])) {
            $rId = (string) $wb->sheets->sheet[0]->attributes('r', true)['id'];
            foreach ($rels->Relationship as $rel) {
                if ((string) $rel['Id'] === $rId) {
                    $sheetPath = 'xl/' . ltrim((string) $rel['Target'], '/');
                    break;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('Could not find a worksheet inside this Excel file.');
    }

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Could not parse this Excel worksheet.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $rowXml) {
        $row = [];
        foreach ($rowXml->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colIndex = $ref !== '' ? xlsx_col_to_index($m[1] ?? 'A') : count($row);

            $type = (string) $cell['t'];
            if ($type === 's') {
                $idx = (int) $cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
            } elseif (isset($cell->v)) {
                $value = (string) $cell->v;
            } else {
                $value = '';
            }

            $row[$colIndex] = $value;
        }
        if (empty($row)) continue;
        $maxIndex = max(array_keys($row));
        $ordered = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $ordered[] = $row[$i] ?? '';
        }
        $rows[] = $ordered;
    }

    return $rows;
}

function xlsx_col_to_index(string $col): int
{
    $index = 0;
    foreach (str_split($col) as $char) {
        $index = $index * 26 + (ord($char) - ord('A') + 1);
    }
    return $index - 1;
}
