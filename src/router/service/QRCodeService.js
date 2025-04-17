/**
 * QRCodeService.js
 * Service for handling QR code operations and mapping QR codes to student IDs
 */

// Maps QR codes to student IDs
const qrCodeToStudentMap = {
    1: '1', // QR code content '1' maps to student ID 101
    2: '2', // QR code content '2' maps to student ID 102
    3: '3', // QR code content '3' maps to student ID 103
    12345: '1', // Existing QR code 12345 maps to student ID 104
    23456: '2', // Existing QR code 23456 maps to student ID 105
    34567: '3' // Existing QR code 34567 maps to student ID 106
};

/**
 * QR Code Service for attendance management
 */
export const QRCodeService = {
    /**
     * Get student ID corresponding to a QR code
     * @param {string} qrCode - Content of the QR code
     * @returns {string|null} - Student ID or null if QR code is invalid
     */
    getStudentIdFromQR(qrCode) {
        const studentId = qrCodeToStudentMap[qrCode];
        return studentId || null;
    },

    /**
     * Get the QR code path for a student ID
     * @param {string} studentId - Student ID
     * @returns {string|null} - Path to the QR code image or null if not found
     */
    getQRPathForStudent(studentId) {
        // Find the QR code for this student ID
        const qrCode = Object.keys(qrCodeToStudentMap).find((key) => qrCodeToStudentMap[key] === studentId);

        if (!qrCode) return null;

        // Check if it's one of the existing QR codes in the public folder
        if (['12345', '23456', '34567'].includes(qrCode)) {
            return `/qr-codes/${qrCode}_qr.png`;
        }

        // For generated QR codes, we would return a URL that generates them dynamically
        return `/api/qr-code/${studentId}`;
    },

    /**
     * Validate a QR code against the available student database
     * @param {string} qrCode - Content of the QR code
     * @param {Array} students - Array of student objects
     * @returns {Object|null} - Matching student object or null if not found
     */
    validateQRCode(qrCode, students) {
        const studentId = this.getStudentIdFromQR(qrCode);
        if (!studentId) return null;

        // Find the student in the database
        return students.find((student) => student.id === studentId) || null;
    },

    /**
     * Get all valid QR codes in the system
     * @returns {Array} - Array of QR code objects with code and studentId
     */
    getAllQRCodes() {
        return Object.entries(qrCodeToStudentMap).map(([qrCode, studentId]) => ({
            qrCode,
            studentId
        }));
    },

    /**
     * Register a new QR code mapping to a student
     * @param {string} qrCode - Content of the QR code
     * @param {string} studentId - Student ID to associate with the QR code
     * @returns {boolean} - Success status
     */
    registerQRCodeMapping(qrCode, studentId) {
        if (!qrCode || !studentId) return false;

        // Add the mapping
        qrCodeToStudentMap[qrCode] = studentId;
        return true;
    }
};

export default QRCodeService;
