<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentQRCode extends Model
{
    use HasFactory;

    protected $table = 'student_qr_codes';

    protected $fillable = [
        'student_id',
        'qr_code_data',
        'qr_code_hash',
        'is_active',
        'generated_at',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'generated_at' => 'datetime',
        'last_used_at' => 'datetime'
    ];

    /**
     * Relationship to Student
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Generate a unique QR code for a student
     */
    public static function generateForStudent($studentId)
    {
        // Deactivate any existing QR codes for this student
        self::where('student_id', $studentId)->update(['is_active' => false]);

        // Generate unique QR code data
        $qrData = 'LAMMS_STUDENT_' . $studentId . '_' . time() . '_' . Str::random(8);
        $qrHash = hash('sha256', $qrData);

        // Create new QR code record
        return self::create([
            'student_id' => $studentId,
            'qr_code_data' => $qrData,
            'qr_code_hash' => $qrHash,
            'is_active' => true,
            'generated_at' => now()
        ]);
    }

    /**
     * Find student by QR code data
     */
    public static function findStudentByQRCode($qrCodeData)
    {
        $qrCode = self::where('qr_code_data', $qrCodeData)
                      ->where('is_active', true)
                      ->with('student')
                      ->first();

        if ($qrCode) {
            // Update last used timestamp
            $qrCode->update(['last_used_at' => now()]);
            return $qrCode->student;
        }

        return null;
    }

    /**
     * Get active QR code for student
     */
    public static function getActiveQRForStudent($studentId)
    {
        return self::where('student_id', $studentId)
                   ->where('is_active', true)
                   ->first();
    }
}
