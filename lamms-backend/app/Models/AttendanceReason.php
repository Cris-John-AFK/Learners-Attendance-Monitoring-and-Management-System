<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'reason_name',
        'reason_type',
        'category',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get reasons for a specific type (late or excused)
     */
    public static function getByType(string $type)
    {
        return self::where('reason_type', $type)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('reason_name')
            ->get();
    }

    /**
     * Get all active reasons grouped by type
     */
    public static function getAllGroupedByType()
    {
        return self::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('reason_name')
            ->get()
            ->groupBy('reason_type');
    }

    /**
     * Attendance records that use this reason
     */
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'reason_id');
    }
}
