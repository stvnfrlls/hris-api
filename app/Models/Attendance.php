<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime:H:i',
            'clock_out' => 'datetime:H:i',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // auto-detect late if clock_in is after 09:00
    public function resolveStatus(): string
    {
        if (!$this->clock_in) return 'absent';

        $lateThreshold = '09:00:00';

        return $this->getRawOriginal('clock_in') > $lateThreshold
            ? 'late'
            : 'present';
    }
}
