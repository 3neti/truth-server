<?php

namespace TruthElection\Pipes;

use TruthElection\Data\FinalizeErContext;
use Illuminate\Support\Facades\Storage;
use Closure;

final class PersistElectionReturnJson
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $disk = config('truth-election.storage.disk', 'local');

        $json = json_encode($ctx->er->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $path = "{$ctx->folder}/election_return.json";

        Storage::disk($disk)->put($path, $json);

        logger()->info("[PersistElectionReturnJson] Saved to: {$path}");

        return $next($ctx);
    }
}
