# Database Seeding Workflow

## Overview

The simulation system now seeds the database from config files **before** generating ballots. This ensures Laravel's template rendering system has access to all election data and produces ballots with correct layouts, ArUco markers, and QR codes.

## Why Seed the Database?

Laravel's ballot rendering requires:
1. **Templates** in the `templates` table (Handlebars layouts)
2. **Data** in the `template_data` table (candidate information)

The deprecated script worked because it used **pre-seeded database templates** from `TemplateSeeder` and `InstructionalDataSeeder`. 

The new workflow explicitly seeds from custom config files.

## Workflow

```
Config Files → Seed Database → Generate Ballot PDF → Test Scenarios
```

### Step 1: Seed Database (`simulation:seed-from-config`)

```bash
php artisan simulation:seed-from-config \
    --config-dir=resources/docs/simulation/config \
    --fresh
```

**What it does:**
1. Loads `election.json`, `precinct.yaml`, `mapping.yaml`
2. Parses positions and candidates
3. Maps bubble numbers from `mapping.yaml` to candidates
4. Creates/updates database records:
   - `SIM-QUESTIONNAIRE-001` - Full candidate list  
   - `SIM-BALLOT-001` - Ballot/answer sheet

**Database Tables:**
- `templates` - Ballot layout templates (from `TemplateSeeder`)
- `template_data` - Election-specific data (candidates, positions)

### Step 2: Generate Ballot

The `simulation:generate-ballot` command now uses the seeded data:

```bash
php artisan simulation:generate-ballot \
    --output-dir=storage/app/private/simulation/template
```

**Defaults:**
- `--template-variant=answer-sheet`
- `--document-id=SIM-BALLOT-001`  
- `--questionnaire-variant=questionnaire`
- `--questionnaire-id=SIM-QUESTIONNAIRE-001`

These defaults match the document IDs created by `simulation:seed-from-config`.

## Config File → Database Mapping

### election.json
```json
{
  "positions": [
    {"code": "PUNONG_BARANGAY", "name": "...", "count": 1}
  ],
  "candidates": {
    "PUNONG_BARANGAY": [
      {"code": "001", "name": "Juan Dela Cruz", "alias": "Juan"}
    ]
  }
}
```

↓

### template_data.json_data
```json
{
  "positions": [
    {
      "code": "PUNONG_BARANGAY",
      "title": "...",
      "max_selections": 1,
      "candidates": [
        {
          "code": "001",
          "name": "Juan Dela Cruz",
          "party": "Juan",
          "number": 1  // from mapping.yaml
        }
      ]
    }
  ]
}
```

## Document IDs

| Type | Document ID | Purpose |
|------|------------|---------|
| Questionnaire | `SIM-QUESTIONNAIRE-001` | Full candidate list for posting |
| Ballot | `SIM-BALLOT-001` | Answer sheet for voter marking |

These IDs are **fixed** and independent of the config directory, making the commands simple to use.

## Comparison with Deprecated Script

### Old Approach (OMRAppreciationTest.php)
```php
$this->seed(TemplateSeeder::class);
$this->seed(InstructionalDataSeeder::class);

$template = Template::where('layout_variant', $ballotVariant)->first();
$data = TemplateData::where('document_id', $ballotDocId)->first();
```

Used **hard-coded document IDs** from seeders:
- `PH-2025-BALLOT-CURRIMAO-001` (Philippine)
- `BRGY-2025-BALLOT-BOKIAWAN-001` (Barangay)

### New Approach
```bash
# 1. Seed from any config
php artisan simulation:seed-from-config --config-dir=PATH --fresh

# 2. Generate with simple defaults
php artisan simulation:generate-ballot --output-dir=OUTPUT
```

Uses **generic document IDs** (`SIM-*`) that work with any config.

## Benefits

1. **Config Independence**: Any config directory works
2. **No Profile Management**: No need to update `omr-testing.php` config
3. **Explicit Workflow**: Clear separation between seeding and generation
4. **Same Rendering**: Uses Laravel's proven ballot infrastructure
5. **Complete Output**: ArUco markers, QR codes, fiducials all render correctly

## Troubleshooting

### "Template data not found"
**Solution:** Run `simulation:seed-from-config` first

### "Wrong candidate count"
**Check:**
1. `election.json` has correct number of candidates
2. `mapping.yaml` has matching number of bubbles
3. Database was seeded with `--fresh` flag

### "UNIQUE constraint failed"
**Solution:** Add `--fresh` flag to `simulation:seed-from-config`

### "Missing ArUco markers in ballot"
**Check:**
1. `TemplateSeeder` has run (creates ballot templates)
2. Database templates include fiducial rendering logic
3. Not using profile-based document IDs

## Shell Script Integration

The `run-simulation-laravel.sh` script now includes:

```bash
# Step 0: Seed Database
php artisan simulation:seed-from-config \
    --config-dir="$CONFIG_DIR" \
    --fresh

# Step 1: Generate Ballot (uses seeded data)
php artisan simulation:generate-ballot \
    --output-dir="${template_dir}"
```

## Future Enhancements

Possible improvements:
- Support multiple document ID sets (avoid `--fresh` collisions)
- Cache seeded data to avoid re-seeding on every run
- Add validation that config matches ballot layout
- Generate Laravel coordinates AND mapping coordinates

## Summary

**Before:** Hard-coded profiles, database already seeded with specific IDs

**After:** Explicit seeding from config, generic document IDs, works with any config

The database seeding step is now **part of the simulation workflow**, not a prerequisite.
