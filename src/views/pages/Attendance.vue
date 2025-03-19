<script setup>
import { onBeforeMount, ref } from 'vue';

// Attendance Sessions
const sessions = ref([
    { id: 'SES001', title: 'Math Class - Section A', date: '2025-03-10' },
    { id: 'SES002', title: 'Science Class - Section B', date: '2025-03-11' },
    { id: 'SES003', title: 'History Class - Section C', date: '2025-03-12' }
]);

// Attendance Table Data
const showAttendance = ref(false);
const selectedSession = ref(null);
const students = ref([
    { id: 'S001', name: 'John Doe', status: 'Present', date: new Date(), remarks: '' },
    { id: 'S002', name: 'Jane Smith', status: 'Absent', date: new Date(), remarks: '' },
    { id: 'S003', name: 'Mike Johnson', status: 'Late', date: new Date(), remarks: '' },
    { id: 'S004', name: 'Emily Davis', status: 'Present', date: new Date(), remarks: '' }
]);

const attendanceRecords = ref({});
const allPresent = ref(false);
const showConfirmModal = ref(false);
const showSuccessModal = ref(false);

onBeforeMount(() => {
    students.value.forEach((student) => {
        attendanceRecords.value[student.id] = 'present';
    });
});

// Functions
function openAttendance(session) {
    selectedSession.value = session;
    showAttendance.value = true;
}

function markAllPresent() {
    students.value.forEach((student) => {
        attendanceRecords.value[student.id] = allPresent.value ? 'present' : '';
    });
}

function confirmAttendance() {
    showConfirmModal.value = true;
}

function finalizeAttendance() {
    showConfirmModal.value = false;
    showSuccessModal.value = true;

    // Reset after confirming
    setTimeout(() => {
        showSuccessModal.value = false;
        showAttendance.value = false;
        selectedSession.value = null;
        window.location.reload();
    }, 2000);
}

function goBack() {
    showAttendance.value = false;
}
</script>

<template>
    <div>
        <!-- Attendance Sessions List -->
        <div v-if="!showAttendance" class="card">
            <h2 class="text-2xl font-bold mb-4">Attendance Sessions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="session in sessions" :key="session.id"
                    class="border p-4 rounded-lg shadow transition-transform transform hover:scale-105 duration-300 ease-in-out">
                    <h3 class="text-lg font-semibold">{{ session.title }}</h3>
                    <p class="text-gray-600">Date: {{ session.date }}</p>
                    <button @click="openAttendance(session)" class="mt-2 bg-blue-500 text-white px-4 py-2 rounded">
                        View Attendance
                    </button>
                </div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div v-if="showAttendance" class="card">
            <button @click="goBack" class="mb-4 bg-gray-500 text-white px-4 py-2 rounded">Back</button>
            <h2 class="text-2xl font-bold mb-4">{{ selectedSession.title }} - Attendance</h2>

            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border p-2">ID</th>
                        <th class="border p-2">Name</th>
                        <th class="border p-2">Status</th>
                        <th class="border p-2">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in students" :key="student.id">
                        <td class="border p-2">{{ student.id }}</td>
                        <td class="border p-2">{{ student.name }}</td>
                        <td class="border p-2">
                            <div class="flex space-x-4">
                                <label class="flex items-center space-x-2">
                                    <input type="radio" v-model="attendanceRecords[student.id]" value="present"
                                        class="hidden peer" />
                                    <span class="w-4 h-4 rounded-full border border-gray-400 peer-checked:bg-green-500"></span>
                                    <span>Present</span>
                                </label>

                                <label class="flex items-center space-x-2">
                                    <input type="radio" v-model="attendanceRecords[student.id]" value="absent"
                                        class="hidden peer" />
                                    <span class="w-4 h-4 rounded-full border border-gray-400 peer-checked:bg-red-500"></span>
                                    <span>Absent</span>
                                </label>

                                <label class="flex items-center space-x-2">
                                    <input type="radio" v-model="attendanceRecords[student.id]" value="late"
                                        class="hidden peer" />
                                    <span class="w-4 h-4 rounded-full border border-gray-400 peer-checked:bg-orange-500"></span>
                                    <span>Late</span>
                                </label>
                            </div>
                        </td>
                        <td class="border p-2">
                            <input v-model="student.remarks" type="text" placeholder="Add remarks"
                                class="border p-1 w-full" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4">
                <label> <input type="checkbox" v-model="allPresent" @change="markAllPresent" /> Mark All as Present </label>
            </div>

            <button @click="confirmAttendance" class="mt-4 bg-green-500 text-white px-4 py-2 rounded">
                Confirm Attendance
            </button>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center">
            <div class="bg-white p-6 rounded-lg shadow-lg w-96">
                <h2 class="text-xl font-bold mb-4">Confirm Attendance</h2>
                <p>Please double-check the attendance records before confirming.</p>
                <ul class="mt-4">
                    <li v-for="student in students" :key="student.id" class="border-b py-2">
                        <strong>{{ student.name }}:</strong>
                        <span :class="{
                            'text-green-500': attendanceRecords[student.id] === 'present',
                            'text-red-500': attendanceRecords[student.id] === 'absent',
                            'text-orange-500': attendanceRecords[student.id] === 'late'
                        }">
                            {{ attendanceRecords[student.id] }}
                        </span>
                        <p v-if="student.remarks" class="text-gray-600">Remarks: {{ student.remarks }}</p>
                    </li>
                </ul>

                <div class="mt-4 flex justify-end space-x-2">
                    <button @click="showConfirmModal = false" class="bg-gray-500 text-white px-4 py-2 rounded">
                        Cancel
                    </button>
                    <button @click="finalizeAttendance" class="bg-green-500 text-white px-4 py-2 rounded">
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- Success Message Modal -->
        <div v-if="showSuccessModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center">
            <div class="bg-white p-6 rounded-lg shadow-lg w-80 text-center">
                <h2 class="text-green-500 text-xl font-bold">✔ Success!</h2>
                <p class="mt-2 text-gray-700">Attendance has been successfully recorded.</p>
            </div>
        </div>
    </div>
</template>
