<?php

namespace LBHurtado\OMRTemplate\Tests\Feature;

use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;
use LBHurtado\OMRTemplate\Tests\TestCase;

class OMRTemplateGenerationTest extends TestCase
{
    protected OMRTemplateGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new OMRTemplateGenerator();
    }

    public function test_generates_pdf_with_basic_data(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-001',
            'bubbles' => [
                ['x' => 30, 'y' => 50],
                ['x' => 30, 'y' => 60],
                ['x' => 30, 'y' => 70],
            ],
        ];

        $path = $this->generator->generate($data);

        $this->assertFileExists($path);
        $this->assertStringContainsString('TEST-BALLOT-001.pdf', $path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_generates_pdf_with_fiducial_markers(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-002',
            'fiducials' => [
                ['x' => 10, 'y' => 10, 'width' => 10, 'height' => 10],
                ['x' => 190, 'y' => 10, 'width' => 10, 'height' => 10],
                ['x' => 10, 'y' => 277, 'width' => 10, 'height' => 10],
                ['x' => 190, 'y' => 277, 'width' => 10, 'height' => 10],
            ],
            'bubbles' => [],
        ];

        $path = $this->generator->generateWithConfig($data);

        $this->assertFileExists($path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_generates_pdf_with_barcode(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-003',
            'barcode' => [
                'content' => 'TEST-BALLOT-003',
                'type' => 'PDF417',
                'x' => 10,
                'y' => 260,
                'width' => 80,
                'height' => 20,
            ],
            'bubbles' => [],
        ];

        $path = $this->generator->generateWithConfig($data);

        $this->assertFileExists($path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_generates_pdf_with_text_elements(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-004',
            'bubbles' => [],
            'text_elements' => [
                [
                    'x' => 25,
                    'y' => 30,
                    'content' => 'Test Question',
                    'font' => 'helvetica',
                    'style' => 'B',
                    'size' => 12,
                ],
            ],
        ];

        $path = $this->generator->generate($data);

        $this->assertFileExists($path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_generates_pdf_from_sample_layout(): void
    {
        $sampleLayoutPath = __DIR__ . '/../../resources/templates/sample_layout.json';
        
        if (!file_exists($sampleLayoutPath)) {
            $this->markTestSkipped('Sample layout file not found');
        }

        $data = json_decode(file_get_contents($sampleLayoutPath), true);

        $path = $this->generator->generateWithConfig($data);

        $this->assertFileExists($path);
        $this->assertStringContainsString($data['identifier'], $path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_generates_pdf_output_as_string(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-005',
            'bubbles' => [
                ['x' => 30, 'y' => 50],
            ],
        ];

        $output = $this->generator->generatePdfOutput($data);

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_generates_pdf_with_custom_configuration(): void
    {
        $data = [
            'identifier' => 'TEST-BALLOT-006',
            'bubbles' => [
                ['x' => 30, 'y' => 50, 'radius' => 3.0],
            ],
        ];

        $config = [
            'orientation' => 'P',
            'format' => 'A4',
            'bubble_line_width' => 0.5,
        ];

        $path = $this->generator->generateWithConfig($data, $config);

        $this->assertFileExists($path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
