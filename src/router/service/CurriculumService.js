export const CurriculumService = {
    getSubjectsData() {
        return [
            {
                name: 'Makabansa',
                applicableLevels: ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3']
            },
            {
                name: 'Language',
                applicableLevels: ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Reading and Literacy',
                applicableLevels: ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3']
            },
            {
                name: 'Mathematics',
                applicableLevels: ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Physical and Natural Environment',
                applicableLevels: ['Kinder 1', 'Kinder 2']
            },
            {
                name: 'Good Manners and Right Conduct (GMRC)',
                applicableLevels: ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Science',
                applicableLevels: ['Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Araling Panlipunan',
                applicableLevels: ['Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Technology and Livelihood Education (TLE)',
                applicableLevels: ['Grade 4', 'Grade 5', 'Grade 6']
            },
            {
                name: 'Music, Arts, Physical Education, and Health (MAPEH)',
                applicableLevels: ['Grade 4', 'Grade 5', 'Grade 6']
            }
        ];
    },

    getSubjectsMini() {
        return Promise.resolve(this.getSubjectsData().slice(0, 5));
    },

    getSubjectsSmall() {
        return Promise.resolve(this.getSubjectsData().slice(0, 10));
    },

    getSubjects() {
        return Promise.resolve(this.getSubjectsData());
    }
};
