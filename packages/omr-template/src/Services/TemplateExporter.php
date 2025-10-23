<?php

namespace LBHurtado\OMRTemplate\Services;

use TCPDF;
use LBHurtado\OMRTemplate\Data\OutputBundle;
use LBHurtado\OMRTemplate\Data\ZoneMapData;

class TemplateExporter
{
    public function export(
        string $html,
        ZoneMapData $zoneMapData,
        ?array $metadata = null
    ): OutputBundle {
        $pdf = $this->generatePdf($html);

        return new OutputBundle(
            html: $html,
            pdf: $pdf,
            zoneMap: $zoneMapData,
            metadata: $metadata,
        );
    }

    protected function generatePdf(string $html): TCPDF
    {
        $pdf = new TCPDF(
            config('omr-template.default_orientation', 'P'),
            'mm',
            config('omr-template.default_layout', 'A4'),
            true,
            'UTF-8',
            false
        );

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Add page and write HTML content
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf;
    }
}
