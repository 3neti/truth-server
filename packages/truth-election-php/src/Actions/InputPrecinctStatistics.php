<?php

namespace TruthElection\Actions;

use TruthElection\Support\PrecinctContext;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use TruthElection\Data\PrecinctData;
use Illuminate\Http\JsonResponse;

class InputPrecinctStatistics
{
    use AsAction;

    public function __construct(
        protected PrecinctContext $precinctContext
    ) {}

    /**
     * Update statistical fields of the given Precinct using the provided payload.
     *
     * @param  array   $payload
     * @return PrecinctData
     */
    public function handle(array $payload): PrecinctData
    {
        $precinct = $this->precinctContext->getPrecinct();

        if (! $precinct) {
            throw new \RuntimeException("Precinct [{$precinct->code}] not found in memory.");
        }

        $fields = [
            'watchers_count',
            'precincts_count',
            'registered_voters_count',
            'actual_voters_count',
            'ballots_in_box_count',
            'unused_ballots_count',
            'spoiled_ballots_count',
            'void_ballots_count',
            'closed_at',
        ];

        $data = $precinct->toArray();

        foreach ($fields as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key]; // allow nullable ints
            }
        }

        $updated = PrecinctData::from($data);

        $this->precinctContext->updatePrecinct($updated);

        return $updated;
    }

    /**
     * PATCH /precincts/{precinct}/statistics
     *
     * @param  ActionRequest  $request
     * @return JsonResponse
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $updated = $this->handle($validated);

        return response()->json($updated);
    }

    /**
     * Validation rules for updating any subset of fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'watchers_count'             => ['sometimes', 'nullable', 'integer', 'min:0'],
            'precincts_count'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'registered_voters_count'    => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_voters_count'        => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ballots_in_box_count'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unused_ballots_count'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'spoiled_ballots_count'      => ['sometimes', 'nullable', 'integer', 'min:0'],
            'void_ballots_count'         => ['sometimes', 'nullable', 'integer', 'min:0'],
            'closed_at'                  => ['sometimes', 'nullable', 'date'],
        ];
    }
}
