
# 🧾 Handlebars + TCPDF Integration Plan for Ballot / Survey Form Generation

This plan describes how to use **Handlebars** for **layout definition** and **TCPDF** (`elibyy/tcpdf-laravel`) for **PDF rendering**, specifically for generating **ballots or survey forms** with OMR (Optical Mark Recognition) capabilities.

---

## 🧭 Objective

- Use **Handlebars** to define flexible ballot/survey layouts as JSON
- Use these layouts to drive **precise PDF rendering** with TCPDF
- Ensure output is compatible with downstream OpenCV appreciation in Python

---

## 🧱 1. Project Dependencies

Install:

```bash
composer require elibyy/tcpdf-laravel
composer require xamin/handlebars.php
```

Publish config for TCPDF (optional):

```bash
php artisan vendor:publish --provider="elibyy\tcpdf\TcpdfServiceProvider"
```

---

## 📁 2. Suggested Directory Structure

```
omr-templates/
├── app/
│   ├── Services/
│   │   ├── OMRTemplateGenerator.php
│   │   └── LayoutCompiler.php        <-- NEW: Handlebars-based layout compiler
├── resources/
│   └── templates/
│       └── ballot.hbs               <-- Handlebars layout template
├── storage/
│   └── app/ballots/
├── tests/
│   └── Feature/
│       └── GenerateBallotTest.php
```

---

## ✍️ 3. Sample Handlebars Template (`resources/templates/ballot.hbs`)

```hbs
{
  "identifier": "{{identifier}}",
  "title": "{{title}}",
  "bubbles": [
    {{#each candidates}}
    {
      "x": {{x}},
      "y": {{y}},
      "label": "{{label}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

---

## ⚙️ 4. Create LayoutCompiler Service

```php
namespace App\Services;

use Handlebars\Handlebars;

class LayoutCompiler
{
    public function compile(string $template, array $data): array
    {
        $engine = new Handlebars();
        $templateContents = file_get_contents(resource_path("templates/{$template}.hbs"));

        $json = $engine->render($templateContents, $data);
        return json_decode($json, true);
    }
}
```

---

## 🖨️ 5. Generate PDF via OMRTemplateGenerator

```php
namespace App\Services;

use Elibyy\TCPDF\Facades\TCPDF;

class OMRTemplateGenerator
{
    public function generate(array $layout): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage();

        // Draw Fiducial Markers
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Rect(10, 10, 10, 10, 'F');
        $pdf->Rect(190, 10, 10, 10, 'F');
        $pdf->Rect(10, 277, 10, 10, 'F');
        $pdf->Rect(190, 277, 10, 10, 'F');

        // Draw Barcode
        $pdf->write2DBarcode($layout['identifier'], 'PDF417', 10, 260, 80, 20);

        // Draw Bubbles
        foreach ($layout['bubbles'] as $bubble) {
            $pdf->Circle($bubble['x'], $bubble['y'], 2.5, 0, 360, 'D');
            $pdf->Text($bubble['x'] + 3, $bubble['y'], $bubble['label']);
        }

        $path = storage_path("app/ballots/{$layout['identifier']}.pdf");
        $pdf->Output($path, 'F');

        return $path;
    }
}
```

---

## 🚀 6. Putting It Together

```php
$compiler = new LayoutCompiler();
$templateData = [
    'identifier' => 'BALLOT-2025-001',
    'title' => 'Presidential Survey',
    'candidates' => [
        ['x' => 25, 'y' => 80, 'label' => 'Candidate A'],
        ['x' => 25, 'y' => 90, 'label' => 'Candidate B'],
        ['x' => 25, 'y' => 100, 'label' => 'Candidate C'],
    ]
];

$layout = $compiler->compile('ballot', $templateData);
$pdfPath = (new OMRTemplateGenerator())->generate($layout);
```

---

## 🧪 7. Test Case to Add

```php
it('generates a ballot PDF from Handlebars layout', function () {
    $compiler = new \App\Services\LayoutCompiler();
    $generator = new \App\Services\OMRTemplateGenerator();

    $layout = $compiler->compile('ballot', [
        'identifier' => 'TEST-BALLOT-001',
        'title' => 'Test Ballot',
        'candidates' => [
            ['x' => 30, 'y' => 60, 'label' => 'Yes'],
            ['x' => 30, 'y' => 70, 'label' => 'No'],
        ]
    ]);

    $path = $generator->generate($layout);
    expect(file_exists($path))->toBeTrue();
});
```

---

## ✅ Summary

| Component              | Purpose                               |
|------------------------|----------------------------------------|
| `ballot.hbs`           | Define bubble layout via Handlebars   |
| `LayoutCompiler`       | Compile Handlebars → JSON layout       |
| `OMRTemplateGenerator` | Use layout to render precise PDF       |
| Output                 | Barcode + Fiducials + Bubbles in PDF  |

This method allows flexible, human-readable layout definitions while maintaining pixel-perfect rendering with TCPDF — and is fully compatible with downstream Python OpenCV-based appreciation.
