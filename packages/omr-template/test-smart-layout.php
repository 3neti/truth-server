<?php

require __DIR__ . '/vendor/autoload.php';

// Load sample ballot JSON
$samplePath = __DIR__ . '/resources/samples/sample-ballot.json';
$spec = json_decode(file_get_contents($samplePath), true);

echo "Testing SmartLayoutRenderer\n";
echo "============================\n\n";
echo "Document: " . $spec['document']['title'] . "\n";
echo "ID: " . $spec['document']['unique_id'] . "\n\n";

// Mock config for standalone usage
$config = [
    'page' => [
        'size' => 'A4',
        'orientation' => 'P',
        'margins' => ['l' => 18, 't' => 18, 'r' => 18, 'b' => 18],
        'dpi' => 300,
    ],
    'fonts' => [
        'header' => ['family' => 'helvetica', 'style' => 'B', 'size' => 12],
        'body'   => ['family' => 'helvetica', 'style' => '', 'size' => 10],
        'small'  => ['family' => 'helvetica', 'style' => '', 'size' => 8],
    ],
    'layouts' => [
        '1-col' => ['cols' => 1, 'gutter' => 6, 'row_gap' => 3, 'cell_pad' => 2],
        '2-col' => ['cols' => 2, 'gutter' => 10, 'row_gap' => 3, 'cell_pad' => 2],
        '3-col' => ['cols' => 3, 'gutter' => 10, 'row_gap' => 2, 'cell_pad' => 2],
    ],
    'omr' => [
        'bubble' => [
            'diameter_mm' => 4.0,
            'stroke' => 0.2,
            'fill' => false,
            'label_gap_mm' => 2.0,
        ],
        'fiducials' => [
            'enable' => true,
            'size_mm' => 5.0,
            'positions' => ['tl','tr','bl','br'],
        ],
        'timing_marks' => [
            'enable' => true,
            'edges' => ['left','bottom'],
            'pitch_mm' => 5.0,
            'size_mm'  => 1.5,
        ],
        'quiet_zone_mm' => 6.0,
        'barcode' => [
            'enable' => true,
            'type' => 'PDF417',
            'height_mm' => 10.0,
            'region' => 'footer',
        ],
    ],
    'coords' => [
        'emit_json' => true,
        'path' => __DIR__ . '/storage/coords',
    ],
    'output_path' => __DIR__ . '/storage',
];

try {
    $renderer = new \LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer($config);
    $result = $renderer->render($spec);
    
    echo "✓ Rendering successful!\n\n";
    echo "PDF: " . $result['pdf'] . "\n";
    echo "Coordinates: " . $result['coords'] . "\n";
    echo "Document ID: " . $result['document_id'] . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
