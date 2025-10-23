<?php

namespace LBHurtado\OMRTemplate\Tests\Feature;

use LBHurtado\OMRTemplate\Services\LayoutCompiler;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;
use LBHurtado\OMRTemplate\Tests\TestCase;

class HandlebarsLayoutTest extends TestCase
{
    protected LayoutCompiler $compiler;
    protected OMRTemplateGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new LayoutCompiler();
        $this->compiler->setBasePath(__DIR__ . '/../../resources/templates');
        $this->generator = new OMRTemplateGenerator();
    }

    public function test_compiles_handlebars_template_to_layout(): void
    {
        $data = [
            'identifier' => 'TEST-HBS-001',
            'title' => 'Test Ballot',
            'candidates' => [
                ['x' => 30, 'y' => 60, 'label' => 'Yes'],
                ['x' => 30, 'y' => 70, 'label' => 'No'],
            ]
        ];

        $layout = $this->compiler->compile('ballot', $data);

        $this->assertIsArray($layout);
        $this->assertEquals('TEST-HBS-001', $layout['identifier']);
        $this->assertEquals('Test Ballot', $layout['title']);
        $this->assertArrayHasKey('fiducials', $layout);
        $this->assertArrayHasKey('barcode', $layout);
        $this->assertArrayHasKey('bubbles', $layout);
        $this->assertCount(2, $layout['bubbles']);
    }

    public function test_compiled_layout_has_fiducials(): void
    {
        $layout = $this->compiler->compile('ballot', [
            'identifier' => 'TEST-HBS-002',
            'title' => 'Fiducial Test',
            'candidates' => []
        ]);

        $this->assertArrayHasKey('fiducials', $layout);
        $this->assertCount(4, $layout['fiducials']);
        
        // Verify fiducial positions
        $this->assertEquals(10, $layout['fiducials'][0]['x']);
        $this->assertEquals(10, $layout['fiducials'][0]['y']);
    }

    public function test_compiled_layout_has_barcode(): void
    {
        $layout = $this->compiler->compile('ballot', [
            'identifier' => 'TEST-HBS-003',
            'title' => 'Barcode Test',
            'candidates' => []
        ]);

        $this->assertArrayHasKey('barcode', $layout);
        $this->assertEquals('TEST-HBS-003', $layout['barcode']['content']);
        $this->assertEquals('PDF417', $layout['barcode']['type']);
    }

    public function test_generates_pdf_from_handlebars_layout(): void
    {
        $layout = $this->compiler->compile('ballot', [
            'identifier' => 'TEST-HBS-PDF-001',
            'title' => 'PDF Generation Test',
            'candidates' => [
                ['x' => 30, 'y' => 60, 'label' => 'Option A'],
                ['x' => 30, 'y' => 70, 'label' => 'Option B'],
                ['x' => 30, 'y' => 80, 'label' => 'Option C'],
            ]
        ]);

        $path = $this->generator->generateWithConfig($layout);

        $this->assertFileExists($path);
        $this->assertStringContainsString('TEST-HBS-PDF-001.pdf', $path);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }


    public function test_validates_required_fields(): void
    {
        $layout = ['identifier' => 'TEST-001', 'title' => 'Test'];

        $this->assertTrue($this->compiler->validate($layout));
        $this->assertTrue($this->compiler->validate($layout, ['identifier']));
        $this->assertTrue($this->compiler->validate($layout, ['identifier', 'title']));
    }

    public function test_validation_throws_exception_for_missing_fields(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Layout missing required field: identifier');

        $layout = ['title' => 'Test'];
        $this->compiler->validate($layout);
    }


    public function test_full_integration_handlebars_to_pdf(): void
    {
        // Compile Handlebars template
        $layout = $this->compiler->compile('ballot', [
            'identifier' => 'INTEGRATION-TEST-001',
            'title' => 'Full Integration Ballot',
            'candidates' => [
                ['x' => 35, 'y' => 60, 'label' => 'Candidate A'],
                ['x' => 35, 'y' => 75, 'label' => 'Candidate B'],
                ['x' => 35, 'y' => 90, 'label' => 'Candidate C'],
            ]
        ]);

        // Validate the layout
        $this->compiler->validate($layout, ['identifier', 'title', 'fiducials', 'barcode', 'bubbles']);

        // Generate PDF
        $path = $this->generator->generateWithConfig($layout);

        // Verify output
        $this->assertFileExists($path);
        $fileSize = filesize($path);
        $this->assertGreaterThan(5000, $fileSize); // PDF should be at least 5KB

        // Verify layout structure
        $this->assertCount(4, $layout['fiducials']);
        $this->assertCount(3, $layout['bubbles']);
        $this->assertEquals('PDF417', $layout['barcode']['type']);

        // Cleanup
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function test_compile_to_json_string(): void
    {
        $json = $this->compiler->compileToJson('ballot', [
            'identifier' => 'JSON-TEST-001',
            'title' => 'JSON Output Test',
            'candidates' => [
                ['x' => 30, 'y' => 60, 'label' => 'Yes'],
            ]
        ]);

        $this->assertIsString($json);
        $this->assertStringContainsString('JSON-TEST-001', $json);
        $this->assertStringContainsString('JSON Output Test', $json);
        
        // Verify it's valid JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }
}
