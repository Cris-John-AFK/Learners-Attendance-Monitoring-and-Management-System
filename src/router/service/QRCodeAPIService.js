import api from '@/config/axios';

/**
 * QR Code API Service for backend integration
 */
export const QRCodeAPIService = {
    /**
     * Generate QR code for a student
     * @param {number} studentId - Student ID
     * @returns {Promise} - API response with QR code data
     */
    async generateQRCode(studentId) {
        try {
            const response = await api.post(`/api/qr-codes/generate/${studentId}`);
            return response.data;
        } catch (error) {
            console.error('Error generating QR code:', error);
            throw error;
        }
    },

    /**
     * Get QR code image URL for a student
     * @param {number} studentId - Student ID
     * @returns {string} - QR code image URL
     */
    getQRCodeImageURL(studentId) {
        return `${api.defaults.baseURL}/api/qr-codes/image/${studentId}`;
    },

    /**
     * Validate QR code and get student information
     * @param {string} qrCodeData - QR code content
     * @returns {Promise} - API response with student data
     */
    async validateQRCode(qrCodeData) {
        try {
            const response = await api.post('/api/qr-codes/validate', {
                qr_code_data: qrCodeData
            });
            return response.data;
        } catch (error) {
            console.error('Error validating QR code:', error);
            throw error;
        }
    },

    /**
     * Get all QR codes in the system
     * @returns {Promise} - API response with all QR codes
     */
    async getAllQRCodes() {
        try {
            const response = await api.get('/api/qr-codes');
            return response.data;
        } catch (error) {
            console.error('Error fetching QR codes:', error);
            throw error;
        }
    },

    /**
     * Get QR code for specific student
     * @param {number} studentId - Student ID
     * @returns {Promise} - API response with student QR code data
     */
    async getStudentQRCode(studentId) {
        try {
            const response = await api.get(`/api/qr-codes/student/${studentId}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching student QR code:', error);
            throw error;
        }
    },

    /**
     * Download QR code as image
     * @param {number} studentId - Student ID
     * @param {string} studentName - Student name for filename
     */
    async downloadQRCode(studentId, studentName) {
        try {
            const response = await api.get(`/api/qr-codes/image/${studentId}`, {
                responseType: 'blob'
            });
            
            // Create blob URL and download
            const blob = new Blob([response.data], { type: 'image/svg+xml' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${studentName.replace(/\s+/g, '_')}_QR_Code.svg`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Error downloading QR code:', error);
            throw error;
        }
    },

    /**
     * Convert SVG to PNG and download
     * @param {number} studentId - Student ID
     * @param {string} studentName - Student name for filename
     */
    async downloadQRCodeAsPNG(studentId, studentName) {
        try {
            // Get SVG data
            const response = await api.get(`/api/qr-codes/image/${studentId}`);
            const svgData = response.data;
            
            // Create canvas and convert SVG to PNG
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            return new Promise((resolve, reject) => {
                img.onload = function() {
                    canvas.width = 300;
                    canvas.height = 300;
                    ctx.fillStyle = 'white';
                    ctx.fillRect(0, 0, 300, 300);
                    ctx.drawImage(img, 0, 0, 300, 300);
                    
                    canvas.toBlob((blob) => {
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `${studentName.replace(/\s+/g, '_')}_QR_Code.png`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(url);
                        resolve();
                    }, 'image/png');
                };
                
                img.onerror = reject;
                img.src = 'data:image/svg+xml;base64,' + btoa(svgData);
            });
        } catch (error) {
            console.error('Error downloading QR code as PNG:', error);
            throw error;
        }
    }
};

export default QRCodeAPIService;
