<?php

require __DIR__ . '/../vendor/autoload.php';

use TruthRenderer\Renderer;
use TruthRenderer\DTO\RenderRequest;

$template = file_get_contents(__DIR__ . '/../templates/invoice/basic/template.hbs');
$data = [
    'code' => 'INV-001',
    'date' => '2025-08-31',
    'items' => [
        ['name' => 'Product A', 'qty' => 2, 'price' => 50],
        ['name' => 'Product B', 'qty' => 1, 'price' => 75]
    ],
    'total' => 175
];

$renderer = new Renderer();
$result = $renderer->render(new RenderRequest($template, $data, null, 'pdf'));

file_put_contents(__DIR__ . '/invoice.pdf', $result->content);

echo "Invoice rendered to invoice.pdf\n";
