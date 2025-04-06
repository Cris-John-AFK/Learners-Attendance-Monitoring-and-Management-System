import axios from 'axios';
// Import the api service from config
import { api } from '../../config/axios';

async assignHomeRoomTeacher() {
    try {
        // Debug current version
        console.log('Running assignHomeRoomTeacher from src/views/pages/Curriculum.vue');

        // Set flag to indicate we attempted submission
        this.teacherSubmitted = true;

        // Validate the selected teacher
        if (!this.selectedTeacher || (typeof this.selectedTeacher === 'object' && !this.selectedTeacher.id) ||
            (typeof this.selectedTeacher !== 'object' && !this.selectedTeacher)) {
            this.$toast.add({
                severity: 'error',
                summary: 'Invalid Selection',
                detail: 'Please select a valid teacher',
                life: 3000
            });
            return;
        }

        // Extract teacher ID - handle both object and direct ID cases
        const teacherId = typeof this.selectedTeacher === 'object'
            ? parseInt(this.selectedTeacher.id)
            : parseInt(this.selectedTeacher);

        if (isNaN(teacherId)) {
            this.$toast.add({
                severity: 'error',
                summary: 'Invalid Teacher ID',
                detail: 'The selected teacher ID is not valid',
                life: 3000
            });
            return;
        }

        console.log('Assigning homeroom teacher with ID:', teacherId);
        console.log('Section:', this.selectedSection);
        console.log('Selected curriculum:', this.selectedCurriculum);
        console.log('Selected grade:', this.selectedGrade);

        try {
            // Call the API to assign the homeroom teacher
            const url = `/api/curriculums/${this.selectedCurriculum.id}/grades/${this.selectedGrade.id}/sections/${this.selectedSection.id}/teacher`;
            console.log('Using homeroom teacher endpoint:', url);

            // The payload only needs the teacher_id since the controller will handle the rest
            const payload = {
                teacher_id: teacherId
            };

            console.log('Sending payload:', payload);
            const response = await api.post(url, payload);
            console.log('Teacher assigned successfully:', response.data);

            // Update UI state
            this.selectedSection.homeroom_teacher_id = teacherId;

            if (this.sections && this.sections.length > 0) {
                const sectionIndex = this.sections.findIndex(s => s.id === this.selectedSection.id);
                if (sectionIndex !== -1) {
                    this.sections[sectionIndex].homeroom_teacher_id = teacherId;
                }
            }

            // Close dialog and show success message
            this.assignHomeRoomTeacherDialog = false;
            this.$toast.add({
                severity: 'success',
                summary: 'Teacher Assigned',
                detail: 'Homeroom teacher has been successfully assigned to the section',
                life: 3000
            });
        } catch (error) {
            console.error('Error in homeroom teacher assignment process:', error);

            // Show detailed error info for debugging
            if (error.response && error.response.data) {
                console.error('Server error details:', error.response.data);

                let errorMessage = 'Failed to assign homeroom teacher';

                if (error.response.data.error) {
                    errorMessage = error.response.data.error;
                    console.error('SQL Error:', errorMessage);
                } else if (error.response.data.errors) {
                    errorMessage = Object.entries(error.response.data.errors)
                        .map(([field, msgs]) => `${field}: ${msgs.join(', ')}`)
                        .join('; ');
                } else if (error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                this.$toast.add({
                    severity: 'error',
                    summary: 'Assignment Failed',
                    detail: errorMessage,
                    life: 5000
                });
            } else {
                this.$toast.add({
                    severity: 'error',
                    summary: 'Assignment Failed',
                    detail: error.message || 'Failed to assign homeroom teacher',
                    life: 5000
                });
            }
        }
    } finally {
        this.teacherSubmitted = false;
    }
}
