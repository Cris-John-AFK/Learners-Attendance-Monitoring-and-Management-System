import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL + '/api';

class GuardhouseService {
    /**
     * Verify QR code and get student information
     */
    static async verifyQRCode(qrCode) {
        try {
            const response = await axios.post(`${API_BASE_URL}/guardhouse/verify-qr`, {
                qr_code: qrCode
            });
            return response.data;
        } catch (error) {
            console.error('Error verifying QR code:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Record attendance (check-in or check-out)
     */
    static async recordAttendance(studentId, qrCodeData, recordType, isManual = false, notes = null) {
        try {
            const response = await axios.post(`${API_BASE_URL}/guardhouse/record-attendance`, {
                student_id: studentId,
                qr_code_data: qrCodeData,
                record_type: recordType,
                is_manual: isManual,
                notes: notes
            });
            return response.data;
        } catch (error) {
            console.error('Error recording attendance:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Manual record attendance by student ID
     */
    static async manualRecord(studentId, recordType, notes = null) {
        try {
            const response = await axios.post(`${API_BASE_URL}/guardhouse/manual-record`, {
                student_id: studentId,
                record_type: recordType,
                notes: notes
            });
            return response.data;
        } catch (error) {
            console.error('Error creating manual record:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Get today's attendance records
     */
    static async getTodayRecords(filters = {}) {
        try {
            const params = new URLSearchParams();

            if (filters.date) params.append('date', filters.date);
            if (filters.record_type) params.append('record_type', filters.record_type);
            if (filters.search) params.append('search', filters.search);

            const response = await axios.get(`${API_BASE_URL}/guardhouse/today-records?${params}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching today records:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Get all students for manual entry
     */
    static async getAllStudents() {
        try {
            const response = await axios.get(`${API_BASE_URL}/student-details`);
            return response.data;
        } catch (error) {
            console.error('Error fetching students:', error);
            // Fallback to students endpoint if student-details doesn't exist
            try {
                const fallbackResponse = await axios.get(`${API_BASE_URL}/students`);
                return fallbackResponse.data;
            } catch (fallbackError) {
                console.error('Error fetching students (fallback):', fallbackError);
                throw error.response?.data || { success: false, message: 'Network error' };
            }
        }
    }

    /**
     * Get live feed data for admin dashboard
     */
    static async getLiveFeed() {
        try {
            const response = await axios.get(`${API_BASE_URL}/guardhouse/live-feed`);
            return response.data;
        } catch (error) {
            console.error('Error fetching live feed:', error);
            // Return empty data on error
            return {
                success: true,
                check_ins: [],
                check_outs: []
            };
        }
    }

    /**
     * Toggle scanner status
     */
    static async toggleScanner(enabled) {
        try {
            const response = await axios.post(`${API_BASE_URL}/guardhouse/toggle-scanner`, {
                enabled: enabled
            });
            return response.data;
        } catch (error) {
            console.error('Error toggling scanner:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Archive current session
     */
    static async archiveSession(sessionData) {
        try {
            const response = await axios.post(`${API_BASE_URL}/guardhouse/archive-session`, sessionData);
            return response.data;
        } catch (error) {
            console.error('Error archiving session:', error);
            throw error.response?.data || { success: false, message: 'Network error' };
        }
    }

    /**
     * Get archived sessions
     */
    static async getArchivedSessions(filters = {}) {
        try {
            const params = new URLSearchParams();
            if (filters.date) params.append('date', filters.date);
            if (filters.search) params.append('search', filters.search);
            if (filters.type) params.append('type', filters.type);

            const response = await axios.get(`${API_BASE_URL}/guardhouse/archived-sessions?${params}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching archived sessions:', error);
            // Return empty data on error
            return {
                success: true,
                records: []
            };
        }
    }
}

export default GuardhouseService;
