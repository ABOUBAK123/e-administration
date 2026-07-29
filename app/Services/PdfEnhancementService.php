<?php

namespace App\Services;

use App\Models\Document;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class PdfEnhancementService
{
    public function enhancePdfWithQrAndNumber(
        string $pdfPath,
        ?Document $document = null,
        ?string $documentNumber = null,
        ?string $qrToken = null
    ): string {
        // Handle both Document object and individual parameters
        if ($document) {
            $documentNumber = $documentNumber ?? $document->document_number;
            $qrToken = $qrToken ?? $document->qr_token;
        }

        if (!$documentNumber) {
            return $pdfPath;
        }

        // Ensure QR token exists
        if (!$qrToken) {
            $qrToken = Str::random(40);
            if ($document) {
                $document->update(['qr_token' => $qrToken]);
            }
        }

        // Generate QR code
        $qrPath = $this->generateQrCode($qrToken);

        if (!$qrPath || !file_exists($qrPath)) {
            return $pdfPath;
        }

        try {
            // Load and enhance PDF with FPDI
            $enhancedPath = $this->overlayQrAndNumber($pdfPath, $qrPath, $documentNumber);
            return $enhancedPath;
        } finally {
            // Cleanup temporary QR file
            @unlink($qrPath);
        }
    }

    private function generateQrCode(string $qrToken): string
    {
        $verifyUrl = route('qr.public', ['token' => $qrToken]);

        $qrResult = Builder::create()
            ->writer(new PngWriter())
            ->data($verifyUrl)
            ->size(300)
            ->margin(6)
            ->build();

        $qrTempPath = storage_path('app/tmp/qr_' . $qrToken . '.png');

        if (!is_dir(dirname($qrTempPath))) {
            mkdir(dirname($qrTempPath), 0755, true);
        }

        file_put_contents($qrTempPath, $qrResult->getString());

        return $qrTempPath;
    }

    private function overlayQrAndNumber(string $pdfPath, string $qrImagePath, string $documentNumber): string
    {
        // Use FPDI to import existing PDF and add footer
        $pdf = new Fpdi();

        // Add all pages from original PDF
        $pageCount = $pdf->setSourceFile($pdfPath);

        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            $pdf->AddPage();
            $pdf->useImportedPage($pageNum);

            // Add footer overlay
            $this->addFooter($pdf, $pageNum, $pageCount, $documentNumber, $qrImagePath);
        }

        // Output enhanced PDF
        $output = $pdf->Output('S');
        file_put_contents($pdfPath, $output);

        return $pdfPath;
    }

    private function addFooter($pdf, int $pageNumber, int $pageCount, string $documentNumber, string $qrImagePath): void
    {
        // Get page dimensions
        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();

        // Footer positioning (in mm)
        $footerY = $pageHeight - 15;
        $margin = 10;

        // Colors
        $blue = [36, 83, 214];
        $gray = [153, 153, 153];

        // 1. Draw separator line
        $pdf->SetDrawColor($gray[0], $gray[1], $gray[2]);
        $pdf->Line($margin, $footerY - 2, $pageWidth - $margin, $footerY - 2);

        // 2. Document number (left, blue, bold)
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
        $pdf->SetXY($margin, $footerY);
        $pdf->Cell(40, 5, 'N° : ' . $documentNumber, 0, 0, 'L');

        // 3. Verification text (center, grey)
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $verificationText = 'Authenticité vérifiable par scan du QR code';
        $textWidth = $pdf->GetStringWidth($verificationText);
        $pdf->SetXY(($pageWidth - $textWidth) / 2, $footerY + 2);
        $pdf->Cell($textWidth, 5, $verificationText, 0, 0, 'C');

        // 4. Page number (right, grey)
        $pageText = 'Page ' . $pageNumber . ' / ' . $pageCount;
        $pageTextWidth = $pdf->GetStringWidth($pageText);
        $pdf->SetXY($pageWidth - $margin - $pageTextWidth, $footerY + 2);
        $pdf->Cell($pageTextWidth, 5, $pageText, 0, 0, 'R');

        // 5. QR code image (right side, above footer text)
        if (file_exists($qrImagePath)) {
            // QR size: 35x35mm
            $qrSize = 35;
            $qrX = $pageWidth - $margin - $qrSize;
            $qrY = $footerY - $qrSize - 5;

            $pdf->Image($qrImagePath, $qrX, $qrY, $qrSize, $qrSize);
        }
    }
}
