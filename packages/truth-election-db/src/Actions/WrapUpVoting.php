<?php

namespace TruthElectionDb\Actions;

use TruthElection\Actions\FinalizeElectionReturn;
use TruthElection\Data\ElectionReturnData;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;

class WrapUpVoting extends FinalizeElectionReturn
{
    use AsAction;

//    public function rules(): array
//    {
//        return [
//            'disk' => ['nullable', 'string'],
//            'payload' => ['nullable', 'string'],
//            'max_chars' => ['nullable', 'integer'],
//            'dir' => ['nullable', 'string'],
//            'force' => ['nullable', 'boolean'],
//        ];
//    }
//
//    public function asController(ActionRequest $request): ElectionReturnData
//    {
//        $validated = $request->validated();
//
//        return $this->handle(
//            disk: $validated['disk'] ?? 'local',
//            payload: $validated['payload'] ?? 'minimal',
//            maxChars: $validated['max_chars'] ?? 1200,
//            dir: $validated['dir'] ?? 'final',
//            force: $validated['force'] ?? false,
//        );
//    }
}
