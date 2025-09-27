<?php

namespace TruthElectionDb\Actions;

use TruthElection\Actions\InitializeSystem;
use Illuminate\Http\Request;

class SetupElection extends InitializeSystem
{
    public function asController(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $result = $this->handle(
            electionPath: $request->input('election_path'),
            precinctPath: $request->input('precinct_path'),
            mappingPath: $request->input('mapping_path'),
        );

        return response()->json($result);
    }
}
