import { PhotoService } from '@/router/service/PhotoService';

export const AttendanceService = {
    getData() {
        const photos = PhotoService.getData(); // Get photo data
        return [
            {
                id: 1001,
                name: 'Maria Clara Santos',
                date: '2025-03-19',
                timeIn: '07:20 AM',
                timeOut: '04:25 PM',
                gender: 'Female',
                photo: photos[0]?.itemImageSrc
            },
            {
                id: 1002,
                name: 'Jose Andres Reyes',
                date: '2025-03-19',
                timeIn: '07:10 AM',
                timeOut: '04:35 PM',
                gender: 'Male',
                photo: photos[1]?.itemImageSrc
            },
            {
                id: 1003,
                name: 'Rizalina Bautista',
                date: '2025-03-19',
                timeIn: '07:25 AM',
                timeOut: '04:40 PM',
                gender: 'Female',
                photo: photos[2]?.itemImageSrc
            },
            {
                id: 1004,
                name: 'Emilio Aguinaldo Cruz',
                date: '2025-03-19',
                timeIn: '07:05 AM',
                timeOut: '04:20 PM',
                gender: 'Male',
                photo: photos[3]?.itemImageSrc
            },
            {
                id: 1005,
                name: 'Gabriela Silang Rivera',
                date: '2025-03-19',
                timeIn: '07:18 AM',
                timeOut: '04:45 PM',
                gender: 'Female',
                photo: photos[4]?.itemImageSrc
            },
            {
                id: 1006,
                name: 'Diego Silang Mendoza',
                date: '2025-03-19',
                timeIn: '07:22 AM',
                timeOut: '04:38 PM',
                gender: 'Male',
                photo: photos[5]?.itemImageSrc
            },
            {
                id: 1007,
                name: 'Melchora Aquino Pascual',
                date: '2025-03-19',
                timeIn: '07:08 AM',
                timeOut: '04:28 PM',
                gender: 'Female',
                photo: photos[6]?.itemImageSrc
            },
            {
                id: 1008,
                name: 'Andres Bonifacio Torres',
                date: '2025-03-19',
                timeIn: '07:12 AM',
                timeOut: '04:50 PM',
                gender: 'Male',
                photo: photos[7]?.itemImageSrc
            },
            {
                id: 1009,
                name: 'Antonio Luna Gomez',
                date: '2025-03-19',
                timeIn: '07:30 AM',
                timeOut: '04:15 PM',
                gender: 'Male',
                photo: photos[8]?.itemImageSrc
            },
            {
                id: 1010,
                name: 'Juan Dela Cruz',
                date: '2025-03-19',
                timeIn: '07:15 AM',
                timeOut: '04:30 PM',
                gender: 'Male',
                photo: photos[9]?.itemImageSrc
            }
        ];
    }
};
