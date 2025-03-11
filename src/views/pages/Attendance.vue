<script setup>
import { CustomerService } from '@/router/service/CustomerService';
import { ProductService } from '@/router/service/ProductService';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { onBeforeMount, onMounted, ref } from 'vue';

// Product List Section
const products = ref(null);
const options = ref(['list', 'grid']);
const layout = ref('list');
const visibleRight = ref(false);
const selectedProduct = ref(null);

// Attendance Table Section
const showAttendance = ref(false);
const customers1 = ref(null);
const filters1 = ref(null);
const loading1 = ref(false);
const confirmLoading = ref(false);
const showSuccess = ref(false);
const attendanceRecords = ref({});
const allPresent = ref(false);

onMounted(() => {
    ProductService.getProductsSmall().then((data) => {
        products.value = data.slice(0, 6);
    });
});

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

// Shared Functions
function getSeverity(product) {
    switch (product.inventoryStatus) {
        case 'INSTOCK':
            return 'success';
        case 'LOWSTOCK':
            return 'warning';
        case 'OUTOFSTOCK':
            return 'danger';
        default:
            return null;
    }
}

function openDrawer(product) {
    selectedProduct.value = product;
    visibleRight.value = true;
}

// Attendance Table Functions
function initFilters1() {
    filters1.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        id: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        status: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] }
    };
}

function openAttendance(product) {
    selectedProduct.value = product;
    showAttendance.value = true;
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
        setTimeout(() => (showSuccess.value = false), 2000);
    }, 2000);
}

function goBack() {
    showAttendance.value = false;
}
</script>

<template>
    <div v-if="!showAttendance">
        <div class="card">
            <div class="font-semibold text-xl mb-4">Class Attendance</div>
            <p>Use this page to manage your class attendance records.</p>
        </div>

        <div class="flex flex-col">
            <div class="card">
                <DataView :value="products" :layout="layout">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <div class="font-semibold text-xl">Attendance Sessions</div>
                            <SelectButton v-model="layout" :options="options" :allowEmpty="false">
                                <template #option="{ option }">
                                    <i :class="[option === 'list' ? 'pi pi-bars' : 'pi pi-table']" />
                                </template>
                            </SelectButton>
                        </div>
                    </template>

                    <template #list="slotProps">
                        <div class="flex flex-col">
                            <div v-for="(item, index) in slotProps.items" :key="index">
                                <div class="flex flex-col sm:flex-row sm:items-center p-6 gap-4" :class="{ 'border-t border-surface': index !== 0 }">
                                    <div class="md:w-40 relative">
                                        <img class="block xl:block mx-auto rounded w-full" :src="`https://primefaces.org/cdn/primevue/images/product/${item.image}`" :alt="item.name" />
                                        <Tag :value="item.inventoryStatus" :severity="getSeverity(item)" class="absolute dark:!bg-surface-900" style="left: 4px; top: 4px"></Tag>
                                    </div>
                                    <div class="flex flex-col md:flex-row justify-between md:items-center flex-1 gap-6">
                                        <div>
                                            <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">{{ item.category }}</span>
                                            <div class="text-lg font-medium mt-2">{{ item.name }}</div>
                                        </div>
                                        <div class="flex flex-col md:items-end gap-8">
                                            <span class="text-xl font-semibold">Students : {{ item.price }}</span>
                                            <div class="flex flex-row-reverse md:flex-row gap-2">
                                                <div class="flex gap-2">
                                                    <Button icon="pi pi-calendar" outlined @click="openDrawer(item)"></Button>
                                                    <Button icon="pi pi-file-edit" label="Take Attendance" :disabled="item.inventoryStatus === 'OUTOFSTOCK'" class="flex-auto md:flex-initial whitespace-nowrap" @click="openAttendance(item)"></Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #grid="slotProps">
                        <div class="grid grid-cols-12 gap-4">
                            <div v-for="(item, index) in slotProps.items" :key="index" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                                <div class="p-6 border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 rounded flex flex-col">
                                    <div class="bg-surface-50 flex justify-center rounded p-4">
                                        <div class="relative mx-auto">
                                            <img class="rounded w-full" :src="`https://primefaces.org/cdn/primevue/images/product/${item.image}`" :alt="item.name" style="max-width: 300px" />
                                            <Tag :value="item.inventoryStatus" :severity="getSeverity(item)" class="absolute dark:!bg-surface-900" style="left: 4px; top: 4px"></Tag>
                                        </div>
                                    </div>
                                    <div class="pt-6">
                                        <div class="flex flex-row justify-between items-start gap-2">
                                            <div>
                                                <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">{{ item.category }}</span>
                                                <div class="text-lg font-medium mt-1">{{ item.name }}</div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-6 mt-6">
                                            <span class="text-2xl font-semibold">Students : {{ item.price }}</span>
                                            <div class="flex gap-2">
                                                <Button icon="pi pi-calendar" outlined @click="openDrawer(item)"></Button>
                                                <Button icon="pi pi-file-edit" label="Take Attendance" :disabled="item.inventoryStatus === 'OUTOFSTOCK'" class="flex-auto md:flex-initial whitespace-nowrap" @click="openAttendance(item)"></Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </DataView>
            </div>
        </div>

        <Drawer v-model:visible="visibleRight" header="Attendance Details" position="right">
            <template v-if="selectedProduct">
                <p><strong>Grade Name:</strong> {{ selectedProduct.name }}</p>
                <p><strong>Section:</strong> {{ selectedProduct.category }}</p>
                <p><strong>Students Number:</strong> {{ selectedProduct.price }}</p>
                <p><strong>Status:</strong> {{ selectedProduct.inventoryStatus }}</p>
                <p><strong>Calendar Year:</strong></p>
            </template>
            <Calendar v-model="icondisplay" showIcon iconDisplay="input" />
            <template #inputicon="{ clickCallback }">
                <InputIcon class="pi pi-clock cursor-pointer" @click="clickCallback" />
            </template>
            <template v-if="selectedProduct">
                <p><strong></strong></p>
                <p><strong>Student List Present:</strong></p>
                <p><strong>Student List Absent:</strong></p>
                <p><strong>Student List Late:</strong></p>
            </template>
        </Drawer>
    </div>

    <!-- Attendance Table Section -->
    <div v-else class="card relative">
        <div class="flex justify-between items-center mb-4">
            <div class="font-semibold text-xl">Attendance Table for {{ selectedProduct?.name }}</div>
            <Button label="Back" icon="pi pi-arrow-left" @click="goBack" />
        </div>

        <div class="flex justify-between mb-2">
            <div class="flex items-center gap-2">
                <Checkbox v-model="allPresent" @change="markAllPresent" />
                <span>Mark All Present</span>
            </div>
            <Button label="Confirm" @click="confirmAttendance" :loading="confirmLoading" />
        </div>

        <DataTable :value="customers1" :paginator="true" :rows="10" dataKey="id" v-model:filters="filters1" filterDisplay="menu" :loading="loading1" :globalFilterFields="['name', 'id', 'status']" showGridlines>
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

            <Column field="name" header="Name" style="min-width: 12rem" />
            <Column header="ID Number" field="id" style="min-width: 12rem" />

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

        <div v-if="showSuccess" class="absolute inset-0 flex items-center justify-center bg-green-100 bg-opacity-80 text-green-700 font-semibold text-xl">Attendance Confirmed!</div>
    </div>
</template>
