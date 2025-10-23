<?php

namespace LBHurtado\OMRTemplate\Tests\Unit;

use LBHurtado\OMRTemplate\Services\FiducialOrientationHelper;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;
use LBHurtado\OMRTemplate\Tests\TestCase;

class FiducialOrientationHelperTest extends TestCase
{
    protected FiducialOrientationHelper $helper;
    protected OMRTemplateGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new FiducialOrientationHelper();
        $this->generator = new OMRTemplateGenerator();
    }

    public function test_converts_mm_to_pixels(): void
    {
        $this->assertEquals(118, $this->helper->mmToPixels(10, 300));
        $this->assertEquals(2244, $this->helper->mmToPixels(190, 300));
    }

    public function test_converts_pixels_to_mm(): void
    {
        $this->assertEquals(10.0, round($this->helper->pixelsToMm(118, 300), 1));
    }

    public function test_gets_fiducials_for_default_layout(): void
    {
        $fiducials = $this->generator->getFiducialsForLayout('default');
        
        $this->assertCount(4, $fiducials);
        $this->assertEquals(10, $fiducials[0]['x']);
        $this->assertEquals(10, $fiducials[0]['y']);
        $this->assertEquals('top_left', $fiducials[0]['position']);
    }

    public function test_gets_fiducials_for_asymmetrical_layout(): void
    {
        $fiducials = $this->generator->getFiducialsForLayout('asymmetrical_right');
        
        $this->assertCount(4, $fiducials);
        $this->assertEquals(180, $fiducials[1]['x']); // top_right offset
        $this->assertEquals(12, $fiducials[1]['y']);
    }

    public function test_sorts_fiducials_by_position(): void
    {
        $detected = [
            ['x' => 190, 'y' => 277],
            ['x' => 10, 'y' => 10],
            ['x' => 190, 'y' => 10],
            ['x' => 10, 'y' => 277],
        ];

        $sorted = $this->helper->sortFiducialsByPosition($detected);

        $this->assertEquals(10, $sorted['top_left']['x']);
        $this->assertEquals(190, $sorted['top_right']['x']);
        $this->assertEquals(10, $sorted['bottom_left']['x']);
        $this->assertEquals(190, $sorted['bottom_right']['x']);
    }

    public function test_determines_orientation_zero_degrees(): void
    {
        $fiducials = [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 190, 'y' => 10],
            'bottom_left' => ['x' => 10, 'y' => 277],
            'bottom_right' => ['x' => 190, 'y' => 277],
        ];

        $orientation = $this->helper->determineOrientation($fiducials);
        $this->assertEquals(0, $orientation);
    }

    public function test_calculates_centroid(): void
    {
        $points = [
            ['x' => 0, 'y' => 0],
            ['x' => 10, 'y' => 0],
            ['x' => 10, 'y' => 10],
            ['x' => 0, 'y' => 10],
        ];

        $centroid = $this->helper->calculateCentroid($points);
        $this->assertEquals(5.0, $centroid['x']);
        $this->assertEquals(5.0, $centroid['y']);
    }

    public function test_detects_asymmetric_pattern(): void
    {
        $symmetricFiducials = [
            ['x' => 10, 'y' => 10],
            ['x' => 190, 'y' => 10],
            ['x' => 10, 'y' => 277],
            ['x' => 190, 'y' => 277],
        ];

        $asymmetricFiducials = [
            ['x' => 10, 'y' => 10],
            ['x' => 180, 'y' => 12],
            ['x' => 10, 'y' => 277],
            ['x' => 180, 'y' => 270],
        ];

        $this->assertFalse($this->helper->isAsymmetricPattern($symmetricFiducials));
        $this->assertTrue($this->helper->isAsymmetricPattern($asymmetricFiducials));
    }

    public function test_generates_calibration_data(): void
    {
        $fiducials = $this->generator->getFiducialsForLayout('default');
        $calibration = $this->helper->generateCalibrationData($fiducials, 300);

        $this->assertEquals(300, $calibration['dpi']);
        $this->assertArrayHasKey('fiducials_mm', $calibration);
        $this->assertArrayHasKey('fiducials_px', $calibration);
    }

    public function test_exports_calibration_json(): void
    {
        $fiducials = $this->generator->getFiducialsForLayout('default');
        $json = $this->helper->exportCalibrationJson($fiducials, 300);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(300, $decoded['dpi']);
    }

    public function test_generates_pdf_with_different_fiducial_layouts(): void
    {
        $layouts = ['default', 'asymmetrical_right', 'asymmetrical_diagonal'];

        foreach ($layouts as $layout) {
            $data = [
                'identifier' => "TEST-ORIENTATION-{$layout}",
                'bubbles' => [],
            ];

            $path = $this->generator->generateWithFiducialLayout($data, $layout);

            $this->assertFileExists($path);

            // Cleanup
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
