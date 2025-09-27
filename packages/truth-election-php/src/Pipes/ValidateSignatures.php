<?php

namespace TruthElection\Pipes;

use TruthElection\Policies\Signatures\SignaturePolicy;
use TruthElection\Data\FinalizeErContext;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Collection;
use Closure;

final class ValidateSignatures
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $sigs = $ctx->er->signatures ?? [];

        if ($sigs instanceof DataCollection || $sigs instanceof Collection) {
            $sigs = $sigs->toArray();
        }
        app(SignaturePolicy::class)->assertSatisfied((array)$sigs, $ctx->force);

        return $next($ctx);
    }
}
