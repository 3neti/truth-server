<?php

namespace TruthElectionDb\Actions;

use TruthElection\Actions\SubmitBallot;
use Lorisleiva\Actions\ActionRequest;

class CastBallot extends SubmitBallot
{
    public function rules(): array
    {
        return [
            'ballot_code' => 'required|string',
            'votes' => 'required|array|min:1',
            'votes.*.position' => 'required|array',
            'votes.*.position.code' => 'required|string',
            'votes.*.position.name' => 'nullable|string',
            'votes.*.position.level' => 'nullable|string',
            'votes.*.position.count' => 'nullable|integer',
            'votes.*.candidates' => 'required|array',
            'votes.*.candidates.*.code' => 'required|string',
            'votes.*.candidates.*.name' => 'nullable|string',
            'votes.*.candidates.*.alias' => 'nullable|string',
            'votes.*.candidates.*.position' => 'nullable|array',
        ];
    }

    public function asController(ActionRequest $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $result = $this->handle(
            ballotCode: $validated['ballot_code'],
            votes: collect($validated['votes']),
        );

        return response()->json([
            'ok' => true,
            'ballot' => $result,
        ]);
    }
}
