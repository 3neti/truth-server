<?php

namespace TruthRenderer\Contracts;

use TruthRenderer\DTO\RenderRequest;
use TruthRenderer\DTO\RenderResult;

interface RendererInterface
{
    /**
     * Render into the requested output format (html|pdf|md).
     */
    public function render(RenderRequest $request): RenderResult;

    /**
     * Convenience: render and write the result to a file path.
     * Returns the same RenderResult (with its bytes/string) for chaining or logging.
     *
     * @param string $path Target absolute or relative path (directory must exist).
     */
    public function renderToFile(RenderRequest $request, string $path): RenderResult;
}
