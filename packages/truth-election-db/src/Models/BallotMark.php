<?php

namespace TruthElectionDb\Models;

use TruthElectionDb\Database\Factories\BallotMarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BallotMark extends Model
{
    use HasFactory;

    protected $fillable = ['ballot_code', 'mark_key'];

    public static function newFactory(): BallotMarkFactory
    {
        return BallotMarkFactory::new();
    }
}
