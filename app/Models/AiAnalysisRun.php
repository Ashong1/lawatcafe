<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysisRun extends Model
{
    protected $fillable = ['narrative', 'signal_count'];

    public function findings()
    {
        return $this->hasMany(AiFinding::class, 'run_id');
    }
}
