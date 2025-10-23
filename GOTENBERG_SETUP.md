# PDF Generation for OMR Templates

## TL;DR: You Don't Need Docker!

**The OMR system works out-of-the-box using DomPDF (pure PHP).** No Docker or Gotenberg required!

If PDF generation fails, increase PHP memory limit:
```bash
php -d memory_limit=512M artisan omr:generate-calibration CAL-TEST
```

Or permanently set in `php.ini`:
```ini
memory_limit = 512M
```

---

## Optional: Gotenberg for Advanced Use Cases

The OMR template system can optionally use [Gotenberg](https://gotenberg.dev/) for PDF generation if you need advanced features or higher performance. By default, it uses DomPDF which is pure PHP.

## What is Gotenberg?

Gotenberg is a Docker-based API for converting documents (HTML, Markdown, Office files) to PDF. It provides high-quality PDF generation with precise control over page dimensions, which is critical for OMR template accuracy.

## Installation Options

### Option 1: Docker Desktop (Recommended for macOS)

1. **Install Docker Desktop:**
   - Download from: https://www.docker.com/products/docker-desktop
   - Or install via Homebrew: `brew install --cask docker`
   - Open Docker Desktop from Applications to start it

2. **Start Gotenberg:**
   ```bash
   docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8
   ```

3. **Verify it's running:**
   ```bash
   curl http://localhost:3000/health
   ```
   Should return: `{"status":"up"}`

### Option 2: OrbStack (Lightweight Docker Alternative for macOS)

OrbStack is a faster, lighter alternative to Docker Desktop:

1. **Install OrbStack:**
   ```bash
   brew install orbstack
   ```

2. **Start Gotenberg:**
   ```bash
   docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8
   ```

### Option 3: Colima (Free Docker Alternative)

Colima is a free, open-source container runtime:

1. **Install Colima and Docker CLI:**
   ```bash
   brew install colima docker
   ```

2. **Start Colima:**
   ```bash
   colima start
   ```

3. **Start Gotenberg:**
   ```bash
   docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8
   ```

## Managing Gotenberg

### Check if Gotenberg is Running

```bash
docker ps | grep gotenberg
```

Or test the health endpoint:
```bash
curl http://localhost:3000/health
```

### Start Gotenberg

```bash
docker start gotenberg
```

Or if it doesn't exist:
```bash
docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8
```

### Stop Gotenberg

```bash
docker stop gotenberg
```

### View Gotenberg Logs

```bash
docker logs gotenberg
```

## Automatic Startup

### Add to Your Dev Environment

Add Gotenberg to your `composer.json` dev script:

```json
{
  "scripts": {
    "dev": [
      "Composer\\Config::disableProcessTimeout",
      "@php artisan gotenberg:ensure",
      "concurrently \"npm:dev\" \"npm:dev:*\" --names \"VITE,ARTISAN,QUEUE,LOG,REVERB\" -c \"bgBlue,bgMagenta,bgGreen,bgCyan,bgYellow\""
    ]
  }
}
```

### Create a Startup Script

Create `bin/start-gotenberg.sh`:

```bash
#!/bin/bash

if ! command -v docker &> /dev/null; then
    echo "❌ Docker not found. Please install Docker Desktop, OrbStack, or Colima."
    exit 1
fi

if docker ps | grep -q gotenberg; then
    echo "✅ Gotenberg is already running"
    exit 0
fi

echo "🚀 Starting Gotenberg..."
docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8

sleep 2

if curl -s http://localhost:3000/health | grep -q "up"; then
    echo "✅ Gotenberg is running on http://localhost:3000"
else
    echo "❌ Gotenberg failed to start"
    exit 1
fi
```

Make it executable:
```bash
chmod +x bin/start-gotenberg.sh
```

## Troubleshooting

### Port 3000 Already in Use

If port 3000 is already taken:

```bash
# Find what's using port 3000
lsof -i :3000

# Use a different port
docker run -d --name gotenberg --rm -p 3001:3000 gotenberg/gotenberg:8
```

Then update your `.env`:
```env
GOTENBERG_URL=http://localhost:3001
```

### Docker Not Running

```bash
# For Docker Desktop
open -a Docker

# For Colima
colima start

# For OrbStack
orbstack start
```

### Container Exits Immediately

Check the logs:
```bash
docker logs gotenberg
```

Common issues:
- Port conflict
- Insufficient memory
- Docker daemon not running

## Configuration

The Gotenberg URL is configured in `config/omr-template.php`:

```php
'gotenberg' => [
    'url' => env('GOTENBERG_URL', 'http://localhost:3000'),
],
```

Override in `.env`:
```env
GOTENBERG_URL=http://localhost:3000
```

## Testing

Test PDF generation:
```bash
# Generate a test ballot
php artisan omr:generate ballot-v1 TEST-001 --data=/tmp/test-data.json

# Generate a calibration sheet
php artisan omr:generate-calibration CAL-TEST-001
```

If successful, you'll see:
```
✅ PDF saved to: storage/omr-output/TEST-001.pdf
✅ Zone map saved to: storage/omr-output/TEST-001.json
✅ Metadata saved to: storage/omr-output/TEST-001.meta.json
```

## Alternative: Puppeteer (Without Docker)

If you can't use Docker, you can use Puppeteer instead:

1. **Install Puppeteer:**
   ```bash
   npm install puppeteer
   ```

2. **Update environment:**
   ```env
   PDF_RENDERER=puppeteer
   ```

Note: Puppeteer may produce slightly different PDF dimensions than Gotenberg, which could affect OMR alignment accuracy.

## Quick Start Commands

```bash
# Install Docker Desktop
brew install --cask docker

# Start Docker Desktop
open -a Docker

# Wait for Docker to start, then:
docker run -d --name gotenberg --rm -p 3000:3000 gotenberg/gotenberg:8

# Verify
curl http://localhost:3000/health

# Test
php artisan omr:generate-calibration CAL-TEST
```

## Support

If you continue having issues:
- Check Docker is running: `docker ps`
- Check logs: `docker logs gotenberg`
- Test health: `curl http://localhost:3000/health`
- Ensure no firewall blocks port 3000
