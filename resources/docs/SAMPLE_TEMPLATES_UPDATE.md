# Sample Templates Update

## Overview

Updated the default sample template that loads when visiting `/templates/advanced` to provide a better, more realistic example for users.

---

## What Changed

### Default "Simple" Sample Template

**Before**: Basic 2-position ballot (President + one other)
- Only 2 candidates per position
- Minimal data structure
- Generic names

**After**: Realistic 3-position ballot (President + VP + Senator)
- Multiple candidates per position (3 for Pres, 2 for VP, 8 for Senator)
- Demonstrates multi-selection (Senator: vote for 6 of 8)
- Realistic names and party affiliations
- Better field names (`election_name`, `precinct`, `date`)

---

## New Default Sample

### Template (Handlebars)
```handlebars
{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "2-column"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "question": "Vote for {{max_selections}}",
      "maxSelections": {{max_selections}},
      "layout": "2-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{position}}",
          "label": "{{name}}",
          "description": "{{party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

### Data (JSON)
```json
{
  "election_name": "2025 National Elections",
  "precinct": "001-A",
  "date": "2025-05-15",
  "positions": [
    {
      "code": "PRES",
      "title": "President",
      "max_selections": 1,
      "candidates": [
        { "position": 1, "name": "Alice Martinez", "party": "Progressive Party" },
        { "position": 2, "name": "Robert Chen", "party": "Democratic Alliance" },
        { "position": 3, "name": "Maria Santos", "party": "Independent" }
      ]
    },
    {
      "code": "VP",
      "title": "Vice President",
      "max_selections": 1,
      "candidates": [
        { "position": 1, "name": "John Williams", "party": "Progressive Party" },
        { "position": 2, "name": "Sarah Lee", "party": "Democratic Alliance" }
      ]
    },
    {
      "code": "SEN",
      "title": "Senator",
      "max_selections": 6,
      "candidates": [
        { "position": 1, "name": "David Johnson", "party": "Progressive Party" },
        { "position": 2, "name": "Emma Wilson", "party": "Democratic Alliance" },
        { "position": 3, "name": "James Rodriguez", "party": "Independent" },
        { "position": 4, "name": "Lisa Anderson", "party": "Progressive Party" },
        { "position": 5, "name": "Michael Brown", "party": "Democratic Alliance" },
        { "position": 6, "name": "Jennifer Garcia", "party": "Independent" },
        { "position": 7, "name": "Daniel Kim", "party": "Progressive Party" },
        { "position": 8, "name": "Amanda Taylor", "party": "Democratic Alliance" }
      ]
    }
  ]
}
```

---

## Key Improvements

### 1. More Realistic Structure
- **3 positions** instead of 1-2
- **Mixed selection counts** (1, 1, 6) demonstrates variety
- **Party affiliations** show how to include candidate metadata

### 2. Better Field Names
- `election_name` → clearer than `election.title`
- `precinct` → common ballot field
- `date` → standard election metadata
- `max_selections` → clearer than `maxVotes` or `count`

### 3. Demonstrates Key Features
- **Handlebars iteration** with `{{#each}}`
- **Nested data** (positions → candidates)
- **Conditional rendering** with `{{#unless @last}}`
- **Variable interpolation** in multiple places

### 4. Multi-Selection Example
Senator position allows voting for 6 out of 8 candidates:
```json
{
  "code": "SEN",
  "title": "Senator",
  "max_selections": 6,
  "candidates": [ /* 8 candidates */ ]
}
```

This is a common real-world scenario (senate races, party-list, etc.)

---

## Other Sample Templates

### Package Samples (Already Comprehensive)

**1. Philippines Election Sample**
- File: `packages/omr-template/resources/templates/philippines-election-*.hbs/json`
- Content: Full Philippine election ballot
- Positions: President, VP, Senator (50 candidates!), Governor, Mayor, Party-list
- Status: ✅ Already excellent, no changes needed

**2. Barangay Election Sample**
- File: `packages/omr-template/resources/templates/barangay-election-*.hbs/json`
- Content: 2026 Barangay (village) election
- Positions: Punong Barangay (1), Kagawad (8 of 30)
- Status: ✅ Already excellent, no changes needed

**3. Barangay Mapping Sample**
- File: `packages/omr-template/resources/templates/barangay-election-mapping-*.hbs/json`
- Content: Candidate mapping format (numbers only)
- Status: ✅ Already excellent, no changes needed

---

## User Experience

### Before
User visits `/templates/advanced`:
1. Sees very basic template (2 positions, 2 candidates each)
2. Limited understanding of capabilities
3. Must look elsewhere for realistic examples

### After
User visits `/templates/advanced`:
1. Sees realistic ballot (3 positions, multiple candidates)
2. Multi-selection example (Senator: vote for 6)
3. Party affiliations show metadata support
4. Better field names as example
5. Immediate understanding of capabilities

---

## Compatibility

### Backward Compatible
- All existing samples still available
- Philippine and Barangay samples unchanged
- Users can still load old sample format if needed

### Clear Tracking
Added to `loadSimpleSample()`:
```typescript
// Clear current template tracking since this is a sample
currentTemplate.value = null
```

This ensures:
- Sample templates don't trigger portable export
- No confusion about template source
- Clean state for user experimentation

---

## Files Modified

1. **resources/js/pages/Templates/AdvancedEditor.vue**
   - Updated `loadSimpleSample()` function
   - New template structure
   - New data structure
   - Added `currentTemplate.value = null` to clear tracking

---

## Benefits

### For New Users
- **Better first impression** with realistic example
- **Learn by example** with multi-selection scenarios
- **Understand structure** through clear field names
- **See possibilities** immediately

### For Developers
- **Reference implementation** for ballot structure
- **Copy-paste starting point** for new projects
- **Demonstrates best practices** for field naming
- **Shows iteration patterns** with Handlebars

### For Demonstrations
- **Professional appearance** for demos
- **Realistic data** for screenshots
- **Multiple scenarios** in one sample
- **Party metadata** shows extensibility

---

## Next Steps (Optional)

### Additional Sample Templates

**1. Survey Sample**
```javascript
loadSurveySample() {
  // Customer satisfaction survey
  // Likert scales, yes/no, multiple choice
  // Demonstrates different question types
}
```

**2. Exam Sample**
```javascript
loadExamSample() {
  // Multiple choice exam/test
  // Question numbers, answer keys
  // Shows educational use case
}
```

**3. Form Sample**
```javascript
loadFormSample() {
  // Generic form template
  // Text fields, checkboxes, signatures
  // Demonstrates form processing
}
```

### Add to Sample Menu

Update the sample dropdown to include new options:
```vue
<button>Simple Election Ballot</button>
<button>🗳️ Full Election (Philippines)</button>
<button>🏘️ Barangay Election</button>
<button>📋 Survey Template</button>  <!-- NEW -->
<button>📝 Exam Template</button>     <!-- NEW -->
<button>📄 Form Template</button>     <!-- NEW -->
```

---

## Testing

To test the new sample:

1. Visit `/templates/advanced`
2. Click "Load Sample" → "Simple Election Ballot"
3. Verify:
   - Template loads with 3 positions
   - Data shows President (3), VP (2), Senator (8 candidates)
   - Click "Compile & Preview" → Should generate spec
   - Check portable export checkbox → Should be disabled (sample has no template tracking)

---

## Summary

✅ **Default sample template updated!**

**What changed**:
- More realistic election ballot (3 positions, 13 total candidates)
- Multi-selection example (Senator: vote for 6 of 8)
- Party affiliations in candidate data
- Better field names (`election_name`, `precinct`, `date`)
- Clears template tracking (no portable export confusion)

**Why it matters**:
- Better first impression for new users
- Realistic example of system capabilities
- Professional appearance for demos
- Copy-paste starting point for real projects

**Other samples**:
- Philippine election sample: ✅ Already excellent
- Barangay election sample: ✅ Already excellent  
- Barangay mapping sample: ✅ Already excellent

The improved default sample gives users a much better starting point when they first visit the Advanced Editor! 🚀
