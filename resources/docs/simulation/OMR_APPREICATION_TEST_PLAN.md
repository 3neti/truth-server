# 🧠 OMR Appreciation Test Plan (Programmatic Simulation)

### **Objective**
To validate the *appreciation* (mark recognition) module programmatically by simulating filled answer sheets through code. This allows end-to-end testing — from template generation to mark detection — without requiring physical scanning.

---

## **1. Overview of Approach**

We will:

1. **Generate** the blank OMR layout from the template (already done by `OMRTemplateGenerationTest`).
2. **Programmatically darken bubbles** corresponding to selected answers (simulate pencil marks).
3. **Feed the generated image** into the appreciation engine (Python OpenCV or equivalent service).
4. **Validate detection results** by asserting expected filled bubbles against detected output.
5. **Produce artifacts** (images and appreciation overlays) for visual inspection.

This yields full pipeline coverage:

```mermaid
flowchart LR
    A[Template JSON / Handlebars] --> B[Generate Blank OMR Sheet]
    B --> C[Programmatically Darken Bubbles]
    C --> D[Run Appreciation Engine - Python]
    D --> E[Return Appreciated Marks JSON]
    E --> F[Assertions: Expected vs Actual]
    F --> G[Artifact Export - overlay and report]
```

---

## **2. Tools & Dependencies**

- **PHP**: For generating the OMR templates via TCPDF / Handlebars.
- **Python (OpenCV)**: For appreciation (`appreciate.py`).
- **Imagick or GD**: To simulate bubble darkening on the generated PDF or image.
- **Test Framework**: PHPUnit or Pest for orchestration.

---

## **3. Steps to Implement**

### **Step 1 – Generate the Base Sheet**
Use your existing generator (e.g., `OMRTemplateGeneration::run()` or the TCPDF renderer) to produce a blank PNG/JPEG version of the questionnaire.

```php
$sheetPath = storage_path('app/tests/artifacts/blank_sheet.png');
OMRTemplateGeneration::run(template: $template, output: $sheetPath);
```

### **Step 2 – Retrieve Bubble Coordinates**
Use the layout metadata (JSON or YAML) to get the bounding boxes of each bubble:

```php
$bubbles = $template->getBubbleCoordinates(); 
// e.g., [ ['q1a' => [x, y, r]], ['q1b' => [x, y, r]], ... ]
```

### **Step 3 – Simulate Answers**
Create a helper that draws filled circles on the blank image at those coordinates:

```php
function fillBubble($imagePath, $bubblesToFill) {
    $image = imagecreatefrompng($imagePath);
    $black = imagecolorallocate($image, 0, 0, 0);

    foreach ($bubblesToFill as $bubble) {
        imagefilledellipse($image, $bubble['x'], $bubble['y'], $bubble['r'], $bubble['r'], $black);
    }

    $output = str_replace('.png', '_filled.png', $imagePath);
    imagepng($image, $output);
    imagedestroy($image);

    return $output;
}
```

Then simulate answers like:

```php
$filledSheet = fillBubble($sheetPath, [$bubbles['q1a'], $bubbles['q2c']]);
```

### **Step 4 – Run the Appreciation Script**
Invoke the Python script from PHP:

```php
$outputJson = shell_exec("python3 appreciate.py {$filledSheet}");
$result = json_decode($outputJson, true);
```

Expected output (from appreciation):

```json
{
  "q1": "A",
  "q2": "C"
}
```

### **Step 5 – Produce Artifacts**
After appreciation, generate visual overlays and reports for inspection:

```php
$overlayPath = str_replace('.png', '_overlay.png', $filledSheet);
$reportPath = str_replace('.png', '_report.json', $filledSheet);

file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT));

if (isset($result['marks'])) {
    $image = imagecreatefrompng($filledSheet);
    $green = imagecolorallocate($image, 0, 255, 0);
    foreach ($result['marks'] as $mark) {
        imageellipse($image, $mark['x'], $mark['y'], $mark['r'], $mark['r'], $green);
    }
    imagepng($image, $overlayPath);
    imagedestroy($image);
}
```

Artifacts created per test:
- ✅ **Blank Sheet:** `tests/artifacts/blank_sheet.png`
- ✅ **Simulated Answers:** `tests/artifacts/blank_sheet_filled.png`
- ✅ **Appreciation Overlay:** `tests/artifacts/blank_sheet_overlay.png`
- ✅ **JSON Report:** `tests/artifacts/blank_sheet_report.json`

These can be viewed manually after each test run.

---

## **4. Test Case Examples**

| Case | Description | Expected Result |
|------|--------------|-----------------|
| **1. Single Answer** | Fill one bubble per question | Correct match |
| **2. Multiple Marks** | Two bubbles filled per question | INVALID or MULTIPLE |
| **3. Faint Mark** | Partially filled bubble | Confidence ≥ 0.8 |
| **4. Off-center Mark** | Bubble slightly offset | Detected within tolerance |
| **5. Noise Tolerance** | Random small dots | Correct detection maintained |

---

## **5. Assertions**

```php
expect($result)->toMatchArray([
    'q1' => 'A',
    'q2' => 'C',
]);
```

For faint marks:

```php
expect($result['confidence']['q1'])->toBeGreaterThan(0.8);
```

---

## **6. Continuous Integration**

1. Add `appreciate.py` to `omr-appreciation/`.
2. Install Python dependencies in CI:
   ```bash
   apt-get install python3-opencv
   ```
3. Run with artifact retention enabled:
   ```bash
   vendor/bin/pest --group=appreciation --display-artifacts
   ```

Artifacts will be saved under `storage/app/tests/artifacts/` for visual validation.

---

## **7. Future Enhancements**

- Add **noise & skew simulation** for realism.
- Log overlay videos (`cv2.VideoWriter`) showing appreciation steps.
- Benchmark appreciation runtime.
- Add **custom Pest expectation**: `expectAppreciationToMatch()`.
