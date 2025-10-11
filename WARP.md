# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Truth is an Augmented Reality Ballot Appreciation System built with Laravel and Vue.js. It serves as the human-readable interface layer between Computer Vision ballot scanning and the TruthLedger blockchain-style election record store. The system handles everything from ballot reading and vote tallying to generating signed, serialized, and auditable election returns.

## Architecture

### Monorepo Structure

The project is organized as a monorepo where **most of the core technology resides in specialized packages** within the `packages/` directory. The main Laravel application primarily serves as an orchestration layer:

- **truth-election-php**: Core election logic, ballot data structures, and Laravel Actions (no database dependencies)
- **truth-election-db**: Database layer, migrations, and persistence for election data  
- **truth-election-ui**: Vue.js UI components and election-specific interfaces
- **truth-qr-php**: QR code generation utilities for TRUTH envelopes using bacon-qr-code and endroid/qr-code
- **truth-qr-ui**: UI components for QR code functionality and scanning
- **truth-codec-php**: Encoding/decoding utilities for TRUTH data formats
- **truth-renderer-php**: PDF generation and document rendering capabilities

Each package is a self-contained Laravel package with its own:
- Service providers and configuration
- Routes and controllers (where applicable) 
- Tests using Pest PHP
- Composer dependencies managed independently

### Technology Stack

- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Vue 3 + TypeScript with Inertia.js
- **CSS**: Tailwind CSS 4.x
- **Build**: Vite with Laravel Wayfinder
- **Testing**: Pest PHP for backend, no frontend test framework configured
- **Database**: SQLite (default), supports MySQL/PostgreSQL
- **Queue**: Database-backed queues
- **Broadcasting**: Laravel Reverb for real-time features

### Key Architectural Concepts

The system bridges three main layers:
1. **Computer Vision Input** → via `ReadVote` and `FinalizeBallot` actions
2. **Precinct Context + Mappings** → via YAML/JSON config files  
3. **Blockchain-Ready Output** → JSON payloads, PDFs, encoded QR codes

Election data flows through Laravel Actions pattern with these key actions:
- `ReadVote`: Process individual ballot marks
- `FinalizeBallot`: Complete ballot processing
- `CastBallot`: Submit complete ballot data
- `TallyVotes`: Calculate election results
- `WrapupVoting`: Generate final election returns

## Development Commands

### Environment Setup
```bash
# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Install dependencies
composer install
npm install

# Database setup (SQLite by default)
touch database/database.sqlite
php artisan migrate
```

### Running the Application
```bash
# Development mode (runs server, queue, logs, and Vite concurrently)
composer run dev

# Or run components separately
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
npm run dev

# Start Reverb for real-time features
php artisan reverb:start -v

# With SSR support
composer run dev:ssr
```

### Building Assets
```bash
# Development build
npm run dev

# Production build
npm run build

# SSR build
npm run build:ssr
```

### Testing
```bash
# Run all tests
composer run test
# Or: php artisan test

# Run package-specific tests
cd packages/truth-election-php && vendor/bin/pest
cd packages/truth-qr-php && vendor/bin/pest
```

### Code Quality
```bash
# Format code
npm run format
npm run format:check

# Lint JavaScript/TypeScript
npm run lint

# PHP formatting (uses Laravel Pint)
./vendor/bin/pint
```

## Election-Specific Commands

### Setup and Configuration
```bash
# Clear logs and initialize precinct with fresh data
truncate -s0 storage/logs/laravel.log
php artisan election:setup-precinct --fresh

# Publish package stubs
php artisan vendor:publish --tag=truth-election-ui-stubs --force
php artisan vendor:publish --tag=truth-qr-ui-stubs --force
```

### Ballot Processing
```bash
# Read individual votes (Computer Vision integration)
php artisan election:read-vote BAL-000 A1
php artisan election:read-vote BAL-000 B1
php artisan election:read-vote BAL-000 C1
# ... continue with additional vote marks

# Finalize a ballot after all votes read
php artisan election:finalize-ballot BAL-000

# Cast complete ballots from compact format
php artisan election:cast-ballot "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018,GG_016,BC_015,MD_009,WS_007,MA_035,SB_006,FP_038,OS_028,MF_003;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008"

# Bulk ballot processing via stdin (preferred method)
echo "BAL-004|PRESIDENT:SJ_002;VICE-PRESIDENT:TH_001;SENATOR:CB_025,AA_018,SH_030,ATJ_041,RM_024,KR_011,AG_046,CE_023,ZS_014,BC_049,CB_005,PP_039;REPRESENTATIVE-PARTY-LIST:THE_MARTIAN_044" | php artisan election:cast
```

### Vote Tallying and Returns
```bash
# Tally votes (requires OTP for security)
echo "317537" | php artisan election:tally-votes

# Attest election return with BEI signatures
php artisan election:attest-return BEI:uuid-juan:signature123
php artisan election:attest-return BEI:uuid-maria:signature456
echo "BEI:uuid-pedro:signature789" | php artisan election:attest-return

# Record election statistics
php artisan election:record-statistics '{"watchers_count":5,"registered_voters_count":800,"actual_voters_count":700,"ballots_in_box_count":695,"unused_ballots_count":105}'
echo '{"watchers_count":6,"registered_voters_count":801,"actual_voters_count":701,"ballots_in_box_count":696,"unused_ballots_count":106}' | php artisan election:record-statistics

# Generate final election return documents
php artisan election:wrapup-voting
```

## Configuration Files

The system requires three key configuration files:

- **config/election.json**: Master election metadata (precinct codes, positions, etc.)
- **config/precinct.yaml**: Precinct-specific configuration (voting limits, BEIs, etc.)  
- **config/mapping.yaml**: Maps ballot keys (A1, B3) to candidate codes per position

## Broadcasting Setup

For real-time features, configure Laravel Reverb:

```bash
# Install and start Reverb
php artisan install:broadcasting --reverb
php artisan reverb:start -v
```

Update `app/Http/Middleware/HandleInertiaRequests.php`:

```php
use TruthElection\Support\ElectionStoreInterface;

public function share(Request $request): array
{
    return [
        // ... existing shares
        'precinct' => app(ElectionStoreInterface::class)->getPrecinct(),
    ];
}
```

## Output Artifacts

The system generates multiple output formats:
- **ElectionReturnData (JSON)**: Machine-readable, blockchain-storable format
- **PDF**: Printable election returns for human inspection and legal requirements  
- **QR Payloads**: Multiple encoded chunks for offline verification and transport

## Package Development

Since most of the technology lives in packages, you'll frequently work within individual packages:

```bash
# Install package dependencies
cd packages/truth-election-php
composer install

# Run package-specific tests
vendor/bin/pest

# Test specific package functionality
cd packages/truth-qr-php && vendor/bin/pest
cd packages/truth-codec-php && vendor/bin/pest
```

### Package Structure
Each package follows a consistent Laravel package structure:
- **src/**: Source code with PSR-4 autoloading (`TruthElection\`, `TruthQr\`, etc.)
- **tests/**: Pest test files with package-specific test cases
- **config/**: Package configuration files
- **routes/**: Package-specific routes (API and web routes where applicable)
- **composer.json**: Independent dependency management with local package references

### Inter-Package Dependencies
Packages reference each other through local path repositories in their `composer.json`, enabling tight integration while maintaining modularity.
