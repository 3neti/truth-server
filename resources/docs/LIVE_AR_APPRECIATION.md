# HES Live AR Ballot Appreciation

This file will be updated with full instructions.

## 🚦 Quick Start

```bash
pip install --upgrade opencv-contrib-python numpy
python live_ar_appreciation.py --mode aruco --dict DICT_6X6_250 --size 2480x3508 --ids 101,102,103,104 --demo-grid --show-warp
python live_ar_appreciation.py --bubbles bubbles.json --size 2480x3508 --ids 101,102,103,104 --threshold 0.30
```


## 🧩 Bubbles JSON Example

```json
[
  { "x": 400, "y": 800, "radius": 18, "label": "SEN_01" },
  { "x": 400, "y": 950, "radius": 18, "label": "SEN_02" },
  { "x": 400, "y": 1100, "radius": 18, "label": "SEN_03" },
  { "x": 800, "y": 800, "radius": 18, "label": "SEN_04" },
  { "x": 800, "y": 950, "radius": 18, "label": "SEN_05" },
  { "x": 800, "y": 1100, "radius": 18, "label": "SEN_06" }
]
```


## 🧪 CLI Reference

```
python live_ar_appreciation.py [options]
--camera N --mode aruco --dict DICT_6X6_250 --size 2480x3508 --ids 101,102,103,104 --bubbles bubbles.json --radius 16 --threshold 0.30 --demo-grid --show-warp --no-fps
```


## Notes
- Corner IDs order is TL,TR,BR,BL.
- Threshold: filled if ROI mean < 255 * (1 - threshold).

