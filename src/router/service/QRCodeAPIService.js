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
     * Download QR code as SVG with student information
     * @param {number} studentId - Student ID
     * @param {string} studentName - Student name for filename
     */
    async downloadQRCode(studentId, studentName) {
        try {
            const response = await api.get(`/api/qr-codes/image/${studentId}`);
            const svgData = response.data;
            
            // Parse the SVG to get dimensions
            const parser = new DOMParser();
            const svgDoc = parser.parseFromString(svgData, 'image/svg+xml');
            const originalSvg = svgDoc.querySelector('svg');
            
            // Get original dimensions or use defaults
            const qrSize = 300;
            const headerHeight = 100;
            const padding = 30;
            const totalWidth = qrSize + (padding * 2);
            const totalHeight = qrSize + headerHeight + (padding * 2);
            
            // Create new SVG with student info
            const enhancedSvg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="${totalWidth}" height="${totalHeight}" viewBox="0 0 ${totalWidth} ${totalHeight}">
                    <!-- White background -->
                    <rect width="${totalWidth}" height="${totalHeight}" fill="white"/>
                    
                    <!-- Student Name -->
                    <text x="${totalWidth / 2}" y="${padding + 30}" 
                          font-family="Arial, sans-serif" 
                          font-size="24" 
                          font-weight="bold" 
                          fill="#333333" 
                          text-anchor="middle">
                        ${studentName}
                    </text>
                    
                    <!-- Student ID -->
                    <text x="${totalWidth / 2}" y="${padding + 60}" 
                          font-family="Arial, sans-serif" 
                          font-size="18" 
                          fill="#666666" 
                          text-anchor="middle">
                        ID: ${studentId}
                    </text>
                    
                    <!-- Border around QR code -->
                    <rect x="${padding - 1}" y="${headerHeight + padding - 1}" 
                          width="${qrSize + 2}" height="${qrSize + 2}" 
                          fill="none" stroke="#e0e0e0" stroke-width="2"/>
                    
                    <!-- Original QR Code -->
                    <g transform="translate(${padding}, ${headerHeight + padding})">
                        ${originalSvg ? originalSvg.innerHTML : svgData}
                    </g>
                </svg>
            `;
            
            // Create blob and download
            const blob = new Blob([enhancedSvg], { type: 'image/svg+xml' });
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
     * Convert SVG to PNG and download with student information
     * @param {number} studentId - Student ID
     * @param {string} studentName - Student name for filename
     */
    async downloadQRCodeAsPNG(studentId, studentName) {
        try {
            // Get SVG data
            const response = await api.get(`/api/qr-codes/image/${studentId}`);
            const svgData = response.data;
            
            // Create canvas with extra space for student info
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            return new Promise((resolve, reject) => {
                img.onload = function() {
                    // Canvas dimensions: QR code + header space
                    const qrSize = 300;
                    const headerHeight = 100;
                    const padding = 30;
                    const totalWidth = qrSize + (padding * 2);
                    const totalHeight = qrSize + headerHeight + (padding * 2);
                    
                    canvas.width = totalWidth;
                    canvas.height = totalHeight;
                    
                    // White background
                    ctx.fillStyle = 'white';
                    ctx.fillRect(0, 0, totalWidth, totalHeight);
                    
                    // Draw student name (centered, bold)
                    ctx.fillStyle = '#333333';
                    ctx.font = 'bold 24px Arial, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(studentName, totalWidth / 2, padding + 30);
                    
                    // Draw student ID (centered, smaller)
                    ctx.fillStyle = '#666666';
                    ctx.font = '18px Arial, sans-serif';
                    ctx.fillText(`ID: ${studentId}`, totalWidth / 2, padding + 60);
                    
                    // Draw QR code
                    ctx.drawImage(img, padding, headerHeight + padding, qrSize, qrSize);
                    
                    // Optional: Add border around QR code
                    ctx.strokeStyle = '#e0e0e0';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(padding - 1, headerHeight + padding - 1, qrSize + 2, qrSize + 2);
                    
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
