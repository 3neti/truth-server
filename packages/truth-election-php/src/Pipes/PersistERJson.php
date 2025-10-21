<?php

namespace TruthElection\Pipes;

use TruthElection\Data\FinalizeErContext;
use Illuminate\Support\Facades\Storage;
use TruthElection\Data\ERData;
use Closure;

final class PersistERJson
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $disk = config('truth-election.storage.disk', 'local');

        $minifiedER = $ctx->getMinifiedElectionReturn();
        $json = json_encode($minifiedER->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $path = "{$ctx->folder}/er.json";

        Storage::disk($disk)->put($path, $json);

        logger()->info("[PersistERJson] Saved to: {$path}");

        return $next($ctx);
    }
}
