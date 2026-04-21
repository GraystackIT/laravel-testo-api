<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Models;

use Illuminate\Database\Eloquent\Model;

class TestoMeasurement extends Model
{
    protected $table = 'testo_measurements';

    protected $fillable = [
        'logger_uuid',
        'measured_at',
        'temperature',
        'humidity',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'temperature' => 'float',
            'humidity'    => 'float',
        ];
    }
}
