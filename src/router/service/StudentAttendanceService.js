import { PhotoService } from './PhotoService';

export const StudentAttendanceService = {
    getData() {
        const photos = PhotoService.getData(); // Get photo data

        const sharedAttendance = [
            {
                subject: 'Math',
                teacher: 'Mr. Smith',
                logs: [
                    { date: '2024-03-11', timeIn: '08:00 AM', timeOut: '09:00 AM' },
                    { date: '2024-03-12', timeIn: '08:05 AM', timeOut: '09:05 AM' },
                    { date: '2024-03-13', timeIn: '08:02 AM', timeOut: '09:02 AM' },
                    { date: '2024-03-14', timeIn: '08:01 AM', timeOut: '09:01 AM' },
                    { date: '2024-03-15', timeIn: '08:04 AM', timeOut: '09:04 AM' }
                ]
            },
            {
                subject: 'Science',
                teacher: 'Mr. Smith', // Mr. Smith now also handles Science
                logs: [
                    { date: '2024-03-11', timeIn: '09:15 AM', timeOut: '10:15 AM' },
                    { date: '2024-03-12', timeIn: '09:10 AM', timeOut: '10:10 AM' },
                    { date: '2024-03-13', timeIn: '09:12 AM', timeOut: '10:12 AM' },
                    { date: '2024-03-14', timeIn: '09:08 AM', timeOut: '10:08 AM' },
                    { date: '2024-03-15', timeIn: '09:14 AM', timeOut: '10:14 AM' }
                ]
            },
            {
                subject: 'English',
                teacher: 'Ms. Johnson', // Ms. Johnson handles both English and History
                logs: [
                    { date: '2024-03-11', timeIn: '10:30 AM', timeOut: '11:30 AM' },
                    { date: '2024-03-12', timeIn: '10:25 AM', timeOut: '11:25 AM' },
                    { date: '2024-03-13', timeIn: '10:28 AM', timeOut: '11:28 AM' },
                    { date: '2024-03-14', timeIn: '10:29 AM', timeOut: '11:29 AM' },
                    { date: '2024-03-15', timeIn: '10:27 AM', timeOut: '11:27 AM' }
                ]
            },
            {
                subject: 'History',
                teacher: 'Ms. Johnson',
                logs: [
                    { date: '2024-03-11', timeIn: '11:45 AM', timeOut: '12:45 PM' },
                    { date: '2024-03-12', timeIn: '11:40 AM', timeOut: '12:40 PM' },
                    { date: '2024-03-13', timeIn: '11:42 AM', timeOut: '12:42 PM' },
                    { date: '2024-03-14', timeIn: '11:38 AM', timeOut: '12:38 PM' },
                    { date: '2024-03-15', timeIn: '11:44 AM', timeOut: '12:44 PM' }
                ]
            },
            {
                subject: 'Physical Education',
                teacher: 'Coach Wilson',
                logs: [
                    { date: '2024-03-11', timeIn: '01:00 PM', timeOut: '02:00 PM' },
                    { date: '2024-03-12', timeIn: '01:05 PM', timeOut: '02:05 PM' },
                    { date: '2024-03-13', timeIn: '01:02 PM', timeOut: '02:02 PM' },
                    { date: '2024-03-14', timeIn: '01:04 PM', timeOut: '02:04 PM' },
                    { date: '2024-03-15', timeIn: '01:03 PM', timeOut: '02:03 PM' }
                ]
            }
        ];

        return [
            { id: 1001, firstName: 'John', lastName: 'Doe', gender: 'Male', gradeLevel: 3, section: 'A', photo: photos[0]?.itemImageSrc || '', attendance: sharedAttendance },
            { id: 1002, firstName: 'Jane', lastName: 'Smith', gender: 'Female', gradeLevel: 4, section: 'B', photo: photos[1]?.itemImageSrc || '', attendance: sharedAttendance },
            { id: 1003, firstName: 'Michael', lastName: 'Johnson', gender: 'Male', gradeLevel: 5, section: 'C', photo: photos[2]?.itemImageSrc || '', attendance: sharedAttendance },
            { id: 1004, firstName: 'Emily', lastName: 'Williams', gender: 'Female', gradeLevel: 6, section: 'D', photo: photos[3]?.itemImageSrc || '', attendance: sharedAttendance },
            { id: 1005, firstName: 'Chris', lastName: 'Brown', gender: 'Male', gradeLevel: 2, section: 'E', photo: photos[4]?.itemImageSrc || '', attendance: sharedAttendance }
        ];
    },

    getStudentsSmall() {
        return Promise.resolve(this.getData().slice(0, 2));
    },

    getStudentsMedium() {
        return Promise.resolve(this.getData().slice(0, 3));
    },

    getStudentsLarge() {
        return Promise.resolve(this.getData());
    },

    getStudentById(id) {
        return Promise.resolve(this.getData().find((student) => student.id == id));
    }
};
