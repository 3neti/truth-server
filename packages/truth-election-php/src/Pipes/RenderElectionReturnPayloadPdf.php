<?php

namespace TruthElection\Pipes;

use Illuminate\Support\Facades\{Log, Storage};
use TruthElection\Data\FinalizeErContext;
use TruthRenderer\Actions\RenderDocument;

/**
 * RenderElectionReturnPayloadPdf
 *
 * This pipe is responsible for rendering the Election Return (ER) payload
 * into a PDF document using the TruthRenderer's RenderDocument action.
 *
 * This class extends GenerateElectionReturnPayload to access the sanitized payload.
 */
class RenderElectionReturnPayloadPdf extends GenerateElectionReturnPayload
{
    /**
     * Render the ER payload into a PDF and save it to storage.
     *
     * @param FinalizeErContext $ctx
     * @return void
     */
    protected function renderPayload(FinalizeErContext $ctx): void
    {
        $payload = $this->getPayload();

        // ✅ Extract expected fields
        $templateName = $payload['templateName'] ?? null;
        $format       = $payload['format'] ?? 'pdf';

        // ⚠️ Ensure the 'data' section is normalized into a pure associative array
        $data = $this->normalizePayload($payload['data'] ?? []);

        if (!is_array($data)) {
            Log::warning('[RenderElectionReturnPayloadPdf] Invalid payload data: not an array', [
                'type' => gettype($data),
            ]);
            return;
        }

        Log::info('[RenderElectionReturnPayloadPdf] Starting PDF render...', [
            'templateName' => $templateName,
            'format'       => $format,
        ]);

        // 🖨️ Run the actual document render
        $result = RenderDocument::run([
            'templateName' => $templateName,
            'data'         => $data,
            'format'       => $format,
            'paperSize'    => 'A4',
            'orientation'  => 'portrait',
            'filename'     => 'election_return_payload',
        ], persist: true);

        // 💾 Save the rendered PDF to the configured disk
        $path = "{$ctx->folder}/election_return_payload.pdf";

        Storage::disk(config('truth-election.storage.disk', 'local'))
            ->put($path, $result->content);

        Log::info("[RenderElectionReturnPayloadPdf] PDF saved successfully.", [
            'path' => $path,
        ]);
    }

    /**
     * Normalize the payload data into a deeply pure associative array.
     *
     * This avoids issues where decoded JSON5 payloads contain stdClass objects
     * which are incompatible with template engines like Handlebars.
     *
     * @param array $data
     * @return array
     */
    protected function normalizePayload(array $data): array
    {
        return json_decode(json_encode($data), true);
    }
}
