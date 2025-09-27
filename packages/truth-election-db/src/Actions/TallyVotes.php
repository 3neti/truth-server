<?php

namespace TruthElectionDb\Actions;

use TruthElection\Actions\GenerateElectionReturn;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class TallyVotes extends GenerateElectionReturn
{
    public function rules(): array
    {
        return [
            'election_return_code' => ['nullable', 'string'],
        ];
    }

    public function asController(ActionRequest $request): \Illuminate\Http\Response|JsonResponse
    {
        $electionReturnCode = $request->get('election_return_code'); // may be null
        $electionReturn = $this->handle($electionReturnCode);

        return response()->json($electionReturn);
    }
}
