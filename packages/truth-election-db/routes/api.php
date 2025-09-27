<?php

use TruthElectionDb\Actions\RecordStatistics;
use TruthElectionDb\Actions\SetupElection;
use TruthElectionDb\Actions\WrapUpVoting;
use TruthElectionDb\Actions\AttestReturn;
use TruthElection\Actions\FinalizeBallot;
use TruthElectionDb\Actions\CastBallot;
use TruthElectionDb\Actions\TallyVotes;
use Illuminate\Support\Facades\Route;
use TruthElection\Actions\ReadVote;

Route::post('setup-precinct', SetupElection::class)->name('election.setup');
Route::post('read-vote', ReadVote::class)->name('read.vote');
Route::post('finalize-ballot', FinalizeBallot::class)->name('finalize.ballot');
Route::post('cast-ballot', CastBallot::class)->name('cast.ballot');
Route::post('tally-votes', TallyVotes::class)->name('tally.votes');
Route::post('attest-return', AttestReturn::class)->name('attest.return');
Route::patch('record-statistics', RecordStatistics::class)->name('record.statistics');
Route::post('wrapup-voting', WrapUpVoting::class)->name('wrapup.voting');
