<?php

namespace TruthElection\Pipes;

use Illuminate\Support\Facades\{Log, Storage};
use TruthElection\Data\FinalizeErContext;
use TruthRenderer\Actions\RenderDocument;
use Closure;

final class RenderElectionReturnPdf
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $disk = config('truth-election.storage.disk', 'local');

        Log::info('[RenderElectionReturnPdf] Starting PDF render...');

        $result = RenderDocument::run([
            'templateName' => 'core:precinct/er/template',
            'data'         => $ctx->er->toArray(),
            'format'       => 'pdf',
            'paperSize'    => 'A4',
            'orientation'  => 'portrait',
            'filename'     => 'election_return', //TODO: put this in the config
        ], persist: false);

        // Save the rendered PDF to the appropriate folder
        $path = "{$ctx->folder}/election_return.pdf"; //TODO: put this in the config

        Storage::disk($disk)->put($path, $result->content);

        Log::info("[RenderElectionReturnPdf] PDF saved to: {$path}");

        return $next($ctx);
    }
}
