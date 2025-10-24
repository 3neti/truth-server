<?php

namespace LBHurtado\OMRTemplate\Engine;

use LBHurtado\OMRTemplate\Renderers\MultipleChoiceRenderer;
use LBHurtado\OMRTemplate\Renderers\RatingScaleRenderer;
use LBHurtado\OMRTemplate\Renderers\MatrixRenderer;
use LBHurtado\OMRTemplate\Renderers\FreeTextRenderer;

class SmartLayoutRenderer
{
    protected $pdf;
    protected array $config;
    protected LayoutContext $context;
    protected CoordinatesRegistry $registry;
    protected OMRDrawer $omrDrawer;
    protected OverflowPaginator $paginator;
    protected array $renderers = [];

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('omr-template');
    }

    public function render(array $spec): array
    {
        // Initialize TCPDF
        $this->initializePDF();
        
        // Initialize components
        $this->registry = new CoordinatesRegistry($this->config);
        $this->context = new LayoutContext($this->config['page'] ?? []);
        $this->omrDrawer = new OMRDrawer($this->pdf, $this->registry, $this->config);
        $this->paginator = new OverflowPaginator($this->pdf, $this->context, $this->omrDrawer);
        
        // Initialize renderers
        $this->initializeRenderers();
        
        // Add first page
        $this->pdf->AddPage();
        
        // Draw fiducials and timing marks
        $this->omrDrawer->drawFiducials();
        $this->omrDrawer->drawTimingMarks();
        
        // Render document header
        $this->renderDocumentHeader($spec['document'] ?? []);
        
        // Render sections
        $sections = $spec['sections'] ?? [];
        foreach ($sections as $section) {
            $this->renderSection($section);
        }
        
        // Render footer with barcode
        $this->renderFooter($spec['document'] ?? []);
        
        // Export coordinates
        $documentId = $spec['document']['unique_id'] ?? 'UNKNOWN';
        $coordsPath = $this->registry->export($documentId);
        
        // Save PDF
        $pdfPath = $this->savePDF($documentId);
        
        return [
            'pdf' => $pdfPath,
            'coords' => $coordsPath,
            'document_id' => $documentId,
        ];
    }

    protected function initializePDF(): void
    {
        $pageConfig = $this->config['page'] ?? [];
        
        $this->pdf = new \TCPDF(
            $pageConfig['orientation'] ?? 'P',
            'mm',
            $pageConfig['size'] ?? 'A4',
            true,
            'UTF-8',
            false
        );
        
        $this->pdf->SetCreator('OMR Templates');
        $this->pdf->SetAuthor('lbhurtado/omr-templates');
        
        $margins = $pageConfig['margins'] ?? ['l' => 18, 't' => 18, 'r' => 18, 'b' => 18];
        $this->pdf->SetMargins($margins['l'], $margins['t'], $margins['r']);
        $this->pdf->SetAutoPageBreak(false);
        
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }

    protected function initializeRenderers(): void
    {
        $this->renderers = [
            new MultipleChoiceRenderer($this->omrDrawer, $this->config),
            new RatingScaleRenderer($this->omrDrawer, $this->config),
            new MatrixRenderer($this->omrDrawer, $this->config),
            new FreeTextRenderer($this->omrDrawer, $this->config),
        ];
    }

    protected function renderDocumentHeader(array $document): void
    {
        $title = $document['title'] ?? 'Untitled Document';
        $uniqueId = $document['unique_id'] ?? '';
        
        $fontConfig = $this->config['fonts']['header'] ?? ['family' => 'helvetica', 'style' => 'B', 'size' => 14];
        $this->pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
        
        $this->pdf->SetXY($this->context->getMarginLeft(), $this->context->getCurrentY());
        $this->pdf->Cell(0, 10, $title, 0, 1, 'C');
        
        if (!empty($uniqueId)) {
            $fontConfig = $this->config['fonts']['small'] ?? ['family' => 'helvetica', 'style' => '', 'size' => 8];
            $this->pdf->SetFont($fontConfig['family'], $fontConfig['style'], $fontConfig['size']);
            $this->pdf->Cell(0, 5, 'ID: ' . $uniqueId, 0, 1, 'C');
        }
        
        $this->context->setCurrentY($this->pdf->GetY() + 5);
    }

    protected function renderSection(array $section): void
    {
        $type = $section['type'] ?? 'unknown';
        
        // Handle page break explicitly
        if ($type === 'page_break') {
            $this->paginator->addPage();
            return;
        }
        
        // Find appropriate renderer
        $renderer = null;
        foreach ($this->renderers as $r) {
            if ($r->canRender($type)) {
                $renderer = $r;
                break;
            }
        }
        
        if ($renderer === null) {
            // Skip unknown section types
            return;
        }
        
        // Prepare context
        $contextArray = [
            'currentY' => $this->context->getCurrentY(),
            'marginLeft' => $this->context->getMarginLeft(),
            'marginTop' => $this->context->getMarginTop(),
            'contentWidth' => $this->context->getContentWidth(),
            'contentHeight' => $this->context->getContentHeight(),
        ];
        
        // Note: Automatic page break checking disabled to support manual page_break sections
        // If you need automatic pagination, remove manual page_break sections from your spec
        
        // Render section
        $consumedHeight = $renderer->render($this->pdf, $section, $contextArray);
        
        // Update context
        $this->context->advanceY($consumedHeight);
    }

    protected function renderFooter(array $document): void
    {
        $uniqueId = $document['unique_id'] ?? 'UNKNOWN';
        
        // Position footer at bottom center
        $footerY = $this->context->getPageHeight() - $this->context->getMarginBottom() - 15;
        
        // Draw barcode if enabled
        $barcodeConfig = $this->config['omr']['barcode'] ?? [];
        if ($barcodeConfig['enable'] ?? false) {
            // Center the barcode horizontally
            $barcodeWidth = $barcodeConfig['width_mm'] ?? 50; // Configurable width in mm
            $centerX = ($this->context->getPageWidth() - $barcodeWidth) / 2;
            
            $this->omrDrawer->drawBarcode(
                $uniqueId,
                $centerX,
                $footerY
            );
        }
    }

    protected function savePDF(string $documentId): string
    {
        $outputPath = $this->config['output_path'] ?? storage_path('omr-output');
        
        if (!file_exists($outputPath)) {
            mkdir($outputPath, 0755, true);
        }
        
        $filename = $outputPath . '/' . $documentId . '.pdf';
        $this->pdf->Output($filename, 'F');
        
        return $filename;
    }

    public function getPDF()
    {
        return $this->pdf;
    }

    public function getRegistry(): CoordinatesRegistry
    {
        return $this->registry;
    }
}
