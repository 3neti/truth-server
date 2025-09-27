# Truth Renderer (PHP)

A PHP library to **render structured data (JSON/YAML) into HTML, PDF, or Markdown** using Handlebars templates, with optional **JSON Schema validation**.

It’s framework-agnostic at the core, with optional **Laravel integration** (controllers, routes, Vue stubs) for rapid use in applications.

---

## Features

- ✅ Handlebars templating via **zordius/lightncandy**
- ✅ JSON Schema validation via **justinrainbow/json-schema**
- ✅ PDF output via **dompdf/dompdf**
- ✅ Strongly-typed DTOs (`RenderRequest`, `RenderResult`)
- ✅ `RendererInterface` with `render()` and `renderToFile()`
- ✅ Built-in helpers: `upper`, `lower`, `currency`, `date`, `multiply`
- ✅ Pluggable **partials** and **custom helpers**
- ✅ **Template registry** (in-memory + filesystem namespaces)
- ✅ Laravel adapter: service provider, controller, routes, Vue Inertia component stub

---

## Installation

```bash
composer require lbhurtado/truth-renderer-php
```

---

## Quickstart

```php
use TruthRenderer\Renderer;
use TruthRenderer\DTO\RenderRequest;

$renderer = new Renderer();

$template = '<h1>{{upper title}}</h1> Total: {{currency amount}}';
$data = ['title' => 'Invoice #123', 'amount' => 1234.5];

$request = new RenderRequest(
    template: $template,
    data: $data,
    format: 'pdf',       // html | pdf | md
);

$result = $renderer->render($request);
file_put_contents('invoice.pdf', $result->content);
```

---

## Vue Panel Integration

The package ships with a **Vue 3 + Inertia** panel component (`TruthRenderPanel.vue`) that provides a UI for selecting templates, uploading data, and rendering results.

### Step 1: Publish the Vue stub
```bash
php artisan vendor:publish --tag=truth-renderer-vue
```

This will copy the component into:
```
resources/js/Pages/TruthRenderer/components/TruthRenderPanel.vue
```

### Step 2: Add to an Inertia page
Create a page, e.g. `resources/js/Pages/TruthRenderer/Index.vue`:

```vue
<script setup>
import TruthRenderPanel from './components/TruthRenderPanel.vue'
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-bold mb-4">Truth Renderer</h1>
    <TruthRenderPanel />
  </div>
</template>
```

### Step 3: Expose routes in Laravel
Ensure the package’s routes are loaded (done automatically via the ServiceProvider).  
Endpoints available:
- `GET /truth/templates` → returns available template names
- `POST /truth/render` → renders the selected template with data

### Step 4: Use the Panel
- The panel fetches templates from `/truth/templates`.
- User selects a template + format (PDF/HTML/MD).
- User pastes JSON/YAML data.
- On submit, result is displayed inline or streamed (e.g., PDF preview).

---

## Roadmap

- [ ] DOCX adapter (via PHPWord)
- [ ] Rich HTML→MD adapter (League)
- [ ] Template packs (ballots, election returns, receipts, invoices)
- [ ] UI enhancements: schema-aware validation, live preview

---

## License

MIT © 2025
