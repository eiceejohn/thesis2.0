<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class XlsxWorkbookReader
{
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open workbook: {$path}");
        }

        $sharedStrings = $this->sharedStrings($zip);
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relationshipMap = [];

        foreach ($relationships->Relationship as $relationship) {
            $relationshipMap[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $sheets = [];
        $position = 1;

        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes('r', true);
            $relationshipId = (string) $attributes['id'];
            $target = 'xl/'.ltrim($relationshipMap[$relationshipId], '/');
            $worksheetXml = $zip->getFromName($target);

            if (! $worksheetXml) {
                continue;
            }

            $sheets[] = [
                'name' => (string) $sheet['name'],
                'position' => $position++,
                'rows' => $this->rows($worksheetXml, $sharedStrings),
            ];
        }

        $zip->close();

        return $sheets;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $strings = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return $strings;
        }

        $shared = simplexml_load_string($xml);

        foreach ($shared->si as $item) {
            $strings[] = $this->nodeText($item);
        }

        return $strings;
    }

    private function rows(string $worksheetXml, array $sharedStrings): array
    {
        $worksheet = simplexml_load_string($worksheetXml);
        $rows = [];

        foreach ($worksheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $value = $this->cellValue($cell, $sharedStrings);

                if ($value !== '') {
                    $cells[$column] = $value;
                }
            }

            if ($cells !== []) {
                $rows[] = [
                    'number' => (int) $row['r'],
                    'cells' => $cells,
                ];
            }
        }

        return $rows;
    }

    private function cellValue($cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return $this->nodeText($cell->is);
        }

        $value = (string) $cell->v;

        if ($type === 's') {
            return trim($sharedStrings[(int) $value] ?? $value);
        }

        return trim($value);
    }

    private function nodeText($node): string
    {
        return trim(html_entity_decode(strip_tags($node->asXML()), ENT_QUOTES | ENT_XML1));
    }
}
