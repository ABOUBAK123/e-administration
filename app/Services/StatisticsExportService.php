<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

/**
 * Export des statistiques (résultat de AdminStatisticsService::build) en Word, Excel, PDF et CSV.
 */
class StatisticsExportService
{
    public const FORMATS = ['docx', 'xlsx', 'pdf', 'csv'];

    private const TITLE = 'Statistiques des documents signés et des courriers';

    private const HEADERS = ['Administration émettrice', 'Entité sous tutelle', 'Code', 'Documents signés', 'Courriers arrivés', 'Courriers départ'];

    /**
     * @param array                 $statistics résultat de AdminStatisticsService::build
     * @param array<string, string> $criteria   critères de la recherche [libellé => valeur]
     * @return array{content: string, mime: string, filename: string}
     */
    public function export(string $format, array $statistics, array $criteria): array
    {
        $rows = $this->rows($statistics);

        [$content, $mime] = match ($format) {
            'csv'  => [$this->csv($rows), 'text/csv; charset=UTF-8'],
            'xlsx' => [$this->xlsx($rows, $criteria), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'docx' => [$this->docx($rows, $criteria), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'pdf'  => [$this->pdf($rows, $criteria), 'application/pdf'],
            default => throw new RuntimeException('Format d\'export inconnu : ' . $format),
        };

        return ['content' => $content, 'mime' => $mime, 'filename' => 'statistiques-' . now()->format('Y-m-d-His') . '.' . $format];
    }

    /**
     * Lignes à plat : une ligne de total par administration, ses entités, puis le total général.
     *
     * @return array<int, array{kind: string, cells: array}>
     */
    private function rows(array $statistics): array
    {
        $numbers = fn (array $r) => [(int) $r['signed'], (int) $r['arrive'], (int) $r['depart']];

        $rows = [];
        foreach ($statistics['administrations'] as $a) {
            $rows[] = ['kind' => 'admin', 'cells' => [(string) $a['name'], 'Total administration', '', ...$numbers($a)]];
            foreach ($a['entities'] as $e) {
                $rows[] = ['kind' => 'entity', 'cells' => [(string) $a['name'], (string) $e['name'], (string) ($e['code'] ?? ''), ...$numbers($e)]];
            }
        }
        $rows[] = ['kind' => 'total', 'cells' => ['TOTAL GÉNÉRAL', '', '', ...$numbers($statistics['totals'])]];

        return $rows;
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF"); // BOM : accents corrects à l'ouverture dans Excel
        fputcsv($stream, self::HEADERS, ';', '"', '');

        foreach ($rows as $row) {
            // Neutralise les textes qu'un tableur interpréterait comme une formule.
            $cells = array_map(
                fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v,
                $row['cells']
            );
            fputcsv($stream, $cells, ';', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function xlsx(array $rows, array $criteria): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Statistiques');
        $text = fn (string $cell, string $value) => $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);

        $text('A1', self::TITLE);
        $sheet->mergeCells('A1:F1')->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $line = 2;
        foreach ($criteria as $label => $value) {
            $text('A' . $line, $label . ' : ' . $value);
            $sheet->mergeCells("A{$line}:F{$line}");
            $line++;
        }

        $headerLine = ++$line;
        foreach (self::HEADERS as $i => $header) {
            $text(chr(65 + $i) . $headerLine, $header);
        }
        $sheet->getStyle("A{$headerLine}:F{$headerLine}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2453D6']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        foreach ($rows as $row) {
            $line++;
            foreach ($row['cells'] as $i => $value) {
                $cell = chr(65 + $i) . $line;
                is_int($value) ? $sheet->setCellValue($cell, $value) : $text($cell, $value);
            }
            if ($row['kind'] !== 'entity') {
                $sheet->getStyle("A{$line}:F{$line}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $row['kind'] === 'total' ? 'D1D5DB' : 'EAF1FF']],
                ]);
            }
        }

        $sheet->getStyle("A{$headerLine}:F{$line}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D{$headerLine}:F{$line}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (['A' => 40, 'B' => 36, 'C' => 14, 'D' => 18, 'E' => 18, 'F' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A' . ($headerLine + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

        $path = tempnam(sys_get_temp_dir(), 'stats');
        try {
            (new Xlsx($book))->save($path);
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function pdf(array $rows, array $criteria): string
    {
        return Pdf::loadView('admin.partials.statistics-pdf', [
            'title' => self::TITLE,
            'headers' => self::HEADERS,
            'rows' => $rows,
            'criteria' => $criteria,
        ])->setPaper('a4', 'landscape')->output();
    }

    /** Document Word (.docx) construit directement au format OOXML. */
    private function docx(array $rows, array $criteria): string
    {
        $xml = fn (string $v) => htmlspecialchars(
            preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '',
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );

        $paragraph = function (string $value, bool $bold = false, int $size = 18, string $align = 'left', string $color = '1F2937') use ($xml): string {
            return '<w:p><w:pPr><w:spacing w:after="40"/><w:jc w:val="' . $align . '"/></w:pPr><w:r><w:rPr>'
                . ($bold ? '<w:b/>' : '') . '<w:color w:val="' . $color . '"/><w:sz w:val="' . $size . '"/></w:rPr>'
                . '<w:t xml:space="preserve">' . $xml($value) . '</w:t></w:r></w:p>';
        };

        $widths = [3800, 3500, 1400, 1700, 1700, 1700]; // en twips (A4 paysage)

        $tableRow = function (array $cells, ?string $fill, bool $bold, bool $header = false) use ($paragraph, $widths): string {
            $out = '<w:tr>' . ($header ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            foreach (array_values($cells) as $i => $value) {
                $out .= '<w:tc><w:tcPr><w:tcW w:w="' . $widths[$i] . '" w:type="dxa"/>'
                    . ($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '')
                    . '</w:tcPr>'
                    . $paragraph((string) $value, $bold, 18, $i >= 3 ? 'right' : 'left', $header ? 'FFFFFF' : '1F2937')
                    . '</w:tc>';
            }
            return $out . '</w:tr>';
        };

        $body = $paragraph(self::TITLE, true, 28);
        foreach ($criteria as $label => $value) {
            $body .= $paragraph($label . ' : ' . $value);
        }
        $body .= $paragraph('');

        $borders = '';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $borders .= '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="BFBFBF"/>';
        }
        $body .= '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/><w:tblBorders>' . $borders . '</w:tblBorders></w:tblPr><w:tblGrid>'
            . implode('', array_map(fn ($w) => '<w:gridCol w:w="' . $w . '"/>', $widths))
            . '</w:tblGrid>' . $tableRow(self::HEADERS, '2453D6', true, true);
        foreach ($rows as $row) {
            $fill = ['admin' => 'EAF1FF', 'total' => 'D1D5DB'][$row['kind']] ?? null;
            $body .= $tableRow($row['cells'], $fill, $row['kind'] !== 'entity');
        }
        $body .= '</w:tbl><w:p/>';

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            . '<w:pgMar w:top="1000" w:right="1000" w:bottom="1000" w:left="1000" w:header="500" w:footer="500" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'stats');
        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer le document Word.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                . '</Relationships>');
            $zip->addFromString('word/document.xml', $document);
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
