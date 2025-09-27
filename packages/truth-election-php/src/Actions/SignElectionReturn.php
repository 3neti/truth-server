<?php

namespace TruthElection\Actions;

use TruthElection\Support\ElectionReturnContext;
use Lorisleiva\Actions\Concerns\AsAction;
use TruthElection\Data\SignPayloadData;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class SignElectionReturn
{
    use AsAction;

    public function __construct(
        protected ElectionReturnContext $electionReturnContext,
    ) {}

    /**
     * Handles the signing of an election return by a specific inspector.
     *
     * @param  SignPayloadData  $payload
     * @param  string  $electionReturnCode
     * @return array{
     *     message: string,
     *     id: string,
     *     name: string,
     *     role: string,
     *     signed_at: string
     * }
     */
    public function handle(SignPayloadData $payload, ?string $electionReturnCode = null): array
    {
        $original = $this->electionReturnContext->getElectionReturn()
            ?? abort(404, "Election return [$electionReturnCode] not found.");

        $inspector = $this->electionReturnContext->findInspector($payload->id)
            ?? abort(404, "Inspector with ID [{$payload->id}] not found.");

        $updated = $original->withInspectorSignature($payload, $inspector);

        $this->electionReturnContext->replaceElectionReturn($updated);

        return [
            'message'   => 'Signature saved successfully.',
            'id'        => $payload->id,
            'name'      => $inspector->name,
            'role'      => $inspector->role->value,
            'signed_at' => now()->toIso8601String(),
            'er'        => $updated,
        ];
    }

    /**
     * Validation rules for the controller.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['payload' => ['required', 'string']];
    }

    /**
     * Controller entrypoint for signing the election return.
     *
     * @param  ActionRequest  $request
     * @param  string  $electionReturnCode
     * @return JsonResponse
     */
    public function asController(ActionRequest $request, ?string $code = null): JsonResponse
    {
        $data = SignPayloadData::fromQrString($request->input('payload'));

        $result = $this->handle($data, $code);

        return response()->json($result);
    }
}
