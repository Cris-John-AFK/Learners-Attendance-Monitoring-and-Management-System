<script setup>
import { CustomerService } from '@/service/CustomerService';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { onBeforeMount, reactive, ref } from 'vue';

const customers1 = ref(null);
const filters1 = ref(null);
const loading1 = ref(null);
const confirmLoading = ref(false);
const showSuccess = ref(false);
const statuses = reactive(['unqualified', 'qualified', 'new', 'negotiation', 'renewal', 'proposal']);
const attendanceRecords = ref({});
const allPresent = ref(false);

onBeforeMount(() => {
    CustomerService.getCustomersLarge().then((data) => {
        customers1.value = data;
        loading1.value = false;
        customers1.value.forEach((customer) => {
            customer.date = new Date(customer.date);
            attendanceRecords.value[customer.id] = 'present';
        });
    });

    initFilters1();
});

function initFilters1() {
    filters1.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        'id': { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        status: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
    };
}

function markAllPresent() {
    customers1.value.forEach((customer) => {
        attendanceRecords.value[customer.id] = allPresent.value ? 'present' : '';
    });
}

function confirmAttendance() {
    confirmLoading.value = true;
    setTimeout(() => {
        confirmLoading.value = false;
        showSuccess.value = true;
        setTimeout(() => showSuccess.value = false, 2000);
    }, 2000);
}
</script>

<template>
    <div class="card relative">
        <div class="font-semibold text-xl mb-4">Attendance Table</div>
        <div class="flex justify-between mb-2">
            <div class="flex items-center gap-2">
                <Checkbox v-model="allPresent" @change="markAllPresent" />
                <span>Mark All Present</span>
            </div>
            <Button label="Confirm" @click="confirmAttendance" :loading="confirmLoading" />
        </div>
        <DataTable
            :value="customers1"
            :paginator="true"
            :rows="10"
            dataKey="id"
            v-model:filters="filters1"
            filterDisplay="menu"
            :loading="loading1"
            :globalFilterFields="['name', 'id', 'status']"
            showGridlines
        >
            <template #header>
                <div class="flex justify-between">
                    <Button type="button" icon="pi pi-filter-slash" label="Clear" outlined @click="initFilters1" />
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText v-model="filters1['global'].value" placeholder="Keyword Search" />
                    </IconField>
                </div>
            </template>
            <template #empty> No students found. </template>
            <template #loading> Loading students data. Please wait. </template>

            <Column header="Attendance" style="min-width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <RadioButton v-model="attendanceRecords[data.id]" inputId="present" value="present" />
                        <label for="present">Present</label>
                        <RadioButton v-model="attendanceRecords[data.id]" inputId="absent" value="absent" />
                        <label for="absent">Absent</label>
                        <RadioButton v-model="attendanceRecords[data.id]" inputId="late" value="late" />
                        <label for="late">Late</label>
                    </div>
                </template>
            </Column>

            <Column field="name" header="Name" style="min-width: 12rem">
                <template #body="{ data }">
                    {{ data.name }}
                </template>
            </Column>

            <Column header="ID Number" field="id" style="min-width: 12rem">
                <template #body="{ data }">
                    {{ data.id }}
                </template>
            </Column>

            <Column header="Status" field="status" style="min-width: 12rem">
                <template #body="{ data }">
                    <Tag :value="data.status" />
                </template>
            </Column>

            <Column header="Remarks" field="remarks" style="min-width: 12rem">
                <template #body="{ data }">
                    <InputText v-model="data.remarks" placeholder="Enter remarks" />
                </template>
            </Column>
        </DataTable>

        <div v-if="showSuccess" class="absolute inset-0 flex items-center justify-center bg-green-100 bg-opacity-80 text-green-700 font-semibold text-xl">
            Attendance Confirmed!
        </div>
    </div>
</template>
