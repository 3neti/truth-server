<?php

namespace TruthElection\Pipes;

use TruthElection\Actions\InputPrecinctStatistics;
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Data\FinalizeErContext;
use Closure;

final class CloseBalloting
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $store = app(ElectionStoreInterface::class);
        $precinct = $store->getPrecinct($ctx->precinct->code);
        if (is_null($precinct->closed_at)) {
            InputPrecinctStatistics::run([
                'closed_at' => now()->toISOString(),
            ]);
        }

        return $next($ctx);
    }
}
