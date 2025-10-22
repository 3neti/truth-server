<?php

namespace LBHurtado\OMRTemplate\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
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

    protected function generatePdf(string $html): Dompdf
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('dpi', config('omr-template.dpi', 300));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(config('omr-template.default_layout', 'A4'), 'portrait');
        $dompdf->render();

        return $dompdf;
    }
}
