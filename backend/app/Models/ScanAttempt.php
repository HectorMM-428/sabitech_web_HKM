<?php

namespace App\Models;

use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['staff_id', 'talk_id', 'reservation_id', 'result'])]
class ScanAttempt extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
        ];
    }
}
