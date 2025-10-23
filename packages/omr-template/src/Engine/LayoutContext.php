<?php

namespace LBHurtado\OMRTemplate\Engine;

class LayoutContext
{
    protected float $currentY;
    protected float $marginLeft;
    protected float $marginTop;
    protected float $marginRight;
    protected float $marginBottom;
    protected float $pageWidth;
    protected float $pageHeight;
    protected int $currentPage = 1;
    protected array $layoutConfig;

    public function __construct(array $config)
    {
        $this->marginLeft = $config['margins']['l'] ?? 18;
        $this->marginTop = $config['margins']['t'] ?? 18;
        $this->marginRight = $config['margins']['r'] ?? 18;
        $this->marginBottom = $config['margins']['b'] ?? 18;
        
        // A4 dimensions in mm
        $this->pageWidth = 210;
        $this->pageHeight = 297;
        
        $this->currentY = $this->marginTop;
        $this->layoutConfig = $config;
    }

    public function getCurrentY(): float
    {
        return $this->currentY;
    }

    public function setCurrentY(float $y): void
    {
        $this->currentY = $y;
    }

    public function advanceY(float $delta): void
    {
        $this->currentY += $delta;
    }

    public function getMarginLeft(): float
    {
        return $this->marginLeft;
    }

    public function getMarginTop(): float
    {
        return $this->marginTop;
    }

    public function getMarginRight(): float
    {
        return $this->marginRight;
    }

    public function getMarginBottom(): float
    {
        return $this->marginBottom;
    }

    public function getContentWidth(): float
    {
        return $this->pageWidth - $this->marginLeft - $this->marginRight;
    }

    public function getContentHeight(): float
    {
        return $this->pageHeight - $this->marginTop - $this->marginBottom;
    }

    public function getPageWidth(): float
    {
        return $this->pageWidth;
    }

    public function getPageHeight(): float
    {
        return $this->pageHeight;
    }

    public function getRemainingHeight(): float
    {
        return $this->pageHeight - $this->marginBottom - $this->currentY;
    }

    public function needsPageBreak(float $requiredHeight): bool
    {
        return $this->getRemainingHeight() < $requiredHeight;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function incrementPage(): void
    {
        $this->currentPage++;
        $this->currentY = $this->marginTop;
    }

    public function getLayoutConfig(): array
    {
        return $this->layoutConfig;
    }

    public function getColumnWidth(int $numColumns, float $gutter = 10): float
    {
        $contentWidth = $this->getContentWidth();
        $totalGutter = ($numColumns - 1) * $gutter;
        return ($contentWidth - $totalGutter) / $numColumns;
    }
}
