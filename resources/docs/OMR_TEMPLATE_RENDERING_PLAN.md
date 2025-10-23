
# 🗳️ OMR Template Rendering Plan
**Package:** `lbhurtado/omr-templates`  
**Renderer:** `elibyy/tcpdf-laravel`  
**Appreciation Engine:** `Python + OpenCV` (`appreciate.py`)

---

## 🎯 Objective
Replace `DOMPDF` with `elibyy/tcpdf-laravel` to generate **pixel-accurate PDF ballots** with proper fiducial markers, OMR bubble placement, and unique identifiers. The layout must be compatible with `appreciate.py`, a Python + OpenCV-based recognition pipeline.

---

## 🧱 1. Laravel PDF Rendering Setup

### ✅ Remove DOMPDF (if installed)
```bash
composer remove barryvdh/laravel-dompdf
```

### ✅ Install TCPDF (via Laravel wrapper)
```bash
composer require elibyy/tcpdf-laravel
```

### ✅ Optional: Publish config
```bash
php artisan vendor:publish --provider="elibyy\tcpdf\TcpdfServiceProvider"
```

---

## 🧑‍💻 2. Create the OMR Template Generator Service

```php
namespace App\Services;

use Elibyy\TCPDF\Facades\TCPDF;

class OMRTemplateGenerator
{
    public function generate(array $data): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetMargins(0, 0, 0, true);
        $pdf->AddPage();

        // --- Fiducial Markers (Anchor Squares) ---
        $pdf->SetFillColor(0, 0, 0); // Black
        $pdf->Rect(10, 10, 10, 10, 'F');     // Top-left
        $pdf->Rect(190, 10, 10, 10, 'F');    // Top-right
        $pdf->Rect(10, 277, 10, 10, 'F');    // Bottom-left
        $pdf->Rect(190, 277, 10, 10, 'F');   // Bottom-right

        // --- Unique Document Identifier (PDF417 Barcode) ---
        $pdf->write2DBarcode($data['identifier'], 'PDF417', 10, 260, 80, 20);

        // --- OMR Bubbles ---
        foreach ($data['bubbles'] as $bubble) {
            [$x, $y] = [$bubble['x'], $bubble['y']];
            $pdf->Circle($x, $y, 2.5, 0, 360, 'D'); // Hollow circle
        }

        // Save to disk
        $path = storage_path("app/ballots/{$data['identifier']}.pdf");
        $pdf->Output($path, 'F');

        return $path;
    }
}
```

---

## 📐 3. PDF to OpenCV Calibration

To ensure correct mark detection, calibrate between **PDF mm coordinates** and **OpenCV px coordinates**.

### 📏 3.1 DPI Calibration Formula

- Target resolution: **300 DPI**
- 1 inch = 25.4 mm
- So, **1 mm ≈ 11.811 pixels**

### 🧮 Example:
| Marker         | mm (PDF)        | px (OpenCV @ 300dpi) |
|----------------|-----------------|-----------------------|
| Top-left       | (10, 10)        | (118, 118)            |
| Bottom-right   | (190, 277)      | (2244, 3263)          |

---

## 🧪 4. Python Calibration Step (appreciate.py)

### 📍 Detect Fiducial Markers
```python
import cv2
import numpy as np

image = cv2.imread("scanned_ballot.jpg", cv2.IMREAD_GRAYSCALE)
_, thresh = cv2.threshold(image, 127, 255, cv2.THRESH_BINARY_INV)
contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

anchors = []
for cnt in contours:
    approx = cv2.approxPolyDP(cnt, 0.02 * cv2.arcLength(cnt, True), True)
    if len(approx) == 4 and cv2.contourArea(cnt) > 500:  # likely square
        x, y, w, h = cv2.boundingRect(cnt)
        anchors.append((x + w // 2, y + h // 2))  # center of square
```

### 📏 Calibrate Scale and Offset
```python
# Reference anchor positions in pixels (based on rendered mm * 11.811)
expected_anchors = {
    "top_left": (118, 118),
    "top_right": (2480 - 118, 118),
    "bottom_left": (118, 3508 - 118),
    "bottom_right": (2480 - 118, 3508 - 118),
}

# Use anchors to compute affine transform or homography
```

---

## 🟢 5. Bubble Extraction in Python

Use the calibrated layout to locate bubbles:

```python
for bubble in known_bubble_coords_mm:
    x_px = int(bubble["x"] * 11.811)
    y_px = int(bubble["y"] * 11.811)
    roi = image[y_px-10:y_px+10, x_px-10:x_px+10]
    mean_intensity = cv2.mean(roi)[0]
    if mean_intensity < 127:
        print("Marked:", bubble["label"])
```

---

## 🧪 6. Visual Debug Overlay (optional)

For template debugging:
```php
$pdf->SetFont('helvetica', '', 6);
$pdf->Text($x + 3, $y, "{$x},{$y}");
```

---

## 📂 7. Suggested Project Structure

```
omr-templates/
├── app/Services/
│   └── OMRTemplateGenerator.php
├── resources/templates/
│   └── sample_layout.json
├── storage/app/ballots/
│   └── <identifier>.pdf
├── tests/
│   └── Feature/OMRTemplateGenerationTest.php
└── python/
    └── appreciate.py
```

---

## 🧪 8. Test Cases to Implement

- [ ] Anchor square detection (Python)
- [ ] Bubble position extraction (Python)
- [ ] Barcode detection and value check (Python)
- [ ] Visual validation of generated PDF (manual or test overlay)
- [ ] Confidence-based mark classification

---

## ✅ Summary

| Component       | Tool/Library              |
|----------------|---------------------------|
| PDF Rendering  | `elibyy/tcpdf-laravel`    |
| Fiducial Marks | Drawn at mm-level coords  |
| Barcode        | PDF417 (unique ID)        |
| Appreciation   | `Python + OpenCV`         |
| Calibration    | DPI scale = 11.811 px/mm  |

This setup ensures consistent ballot layout generation and high-accuracy mark detection in your `appreciate.py` pipeline.
