
# 🎯 Fiducial Marker Orientation Plan for `omr-templates` Package

This plan outlines how to implement **fiducial markers with orientation detection** in the `omr-templates` package. The goal is to place **asymmetrical black squares** that allow `appreciate.py` (Python + OpenCV) to infer and correct the orientation of scanned OMR forms (e.g., 0°, 90°, 180°, 270°).

---

## ✅ 1. Objective

- Ensure each ballot/survey form has **four black fiducial markers**
- Position them **asymmetrically** so Python can detect paper orientation
- Include optional layout presets in config

---

## 📁 2. Suggested Directory & File Additions

```
omr-templates/
├── config/
│   └── omr.php              <-- NEW: configuration for marker layout
├── app/Services/
│   └── OMRTemplateGenerator.php
└── resources/templates/
    └── ballot.hbs
```

---

## ⚙️ 3. Configuration File: `config/omr.php`

```php
return [

    'fiducials' => [
        'default' => [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 190, 'y' => 10],
            'bottom_left' => ['x' => 10, 'y' => 277],
            'bottom_right' => ['x' => 190, 'y' => 277],
        ],

        'asymmetrical_right' => [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 180, 'y' => 12],  // intentionally offset
            'bottom_left' => ['x' => 10, 'y' => 277],
            'bottom_right' => ['x' => 180, 'y' => 270],
        ],
    ],

    'marker_size' => 10, // in mm

];
```

---

## 🖨️ 4. Update `OMRTemplateGenerator` to Draw Fiducials from Config

```php
use Illuminate\Support\Facades\Config;

$layout = Config::get('omr.fiducials.default');
$size = Config::get('omr.marker_size', 10);

$pdf->SetFillColor(0, 0, 0); // Black fill

foreach ($layout as $position => $coords) {
    $pdf->Rect($coords['x'], $coords['y'], $size, $size, 'F');
}
```

---

## 🧪 5. Output for Python Detection

Fiducials will appear as:
- Solid black squares (10mm x 10mm)
- Rendered at known coordinates
- OpenCV will detect these as **4 contours**
- You can sort and compare their relative positions to determine orientation

---

## 🧠 6. Orientation Detection in `appreciate.py` (Python)

### Input from config (converted to px at 300dpi):
```python
expected_positions = {
    "top_left": (118, 118),
    "top_right": (2244, 118),
    "bottom_left": (118, 3263),
    "bottom_right": (2244, 3263)
}
```

### Sort detected contours:
```python
# sort by y, then x to get TL, TR, BL, BR
sorted_fiducials = sorted(detected, key=lambda p: (p[1], p[0]))
```

### Determine orientation:
- Compute vector angle from TL → TR
- Use centroid relationships to verify top/bottom
- Rotate image if needed before OMR appreciation

---

## 🧪 7. Test Case Ideas (PHP)

- [ ] Ensure that all 4 fiducials are drawn
- [ ] Check that the positions match config
- [ ] Assert PDF output with expected barcode and anchor points

---

## ✅ Summary

| Component              | Purpose                                |
|------------------------|----------------------------------------|
| `config/omr.php`       | Stores fiducial layouts and sizes      |
| `OMRTemplateGenerator` | Reads config and draws square anchors  |
| `appreciate.py`        | Detects orientation based on anchors   |
| Layout options         | `default`, `asymmetrical_right`, etc. |

This approach supports modular fiducial placement and guarantees accurate paper orientation detection for downstream mark recognition.
