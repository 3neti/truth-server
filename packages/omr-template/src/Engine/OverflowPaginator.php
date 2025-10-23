<?php

namespace LBHurtado\OMRTemplate\Engine;

class OverflowPaginator
{
    protected $pdf;
    protected LayoutContext $context;
    protected OMRDrawer $omrDrawer;

    public function __construct($pdf, LayoutContext $context, OMRDrawer $omrDrawer)
    {
        $this->pdf = $pdf;
        $this->context = $context;
        $this->omrDrawer = $omrDrawer;
    }

    public function checkAndAddPage(float $requiredHeight): bool
    {
        if ($this->context->needsPageBreak($requiredHeight)) {
            $this->addPage();
            return true;
        }
        
        return false;
    }

    public function addPage(): void
    {
        $this->pdf->AddPage();
        $this->context->incrementPage();
        
        // Redraw fiducials and timing marks on the new page
        $this->omrDrawer->drawFiducials();
        $this->omrDrawer->drawTimingMarks();
    }

    public function getContext(): LayoutContext
    {
        return $this->context;
    }
}
