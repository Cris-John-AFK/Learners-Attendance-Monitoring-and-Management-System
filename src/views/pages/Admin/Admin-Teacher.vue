<script setup>
import { ProductService } from '@/router/service/ProductService';
import { onBeforeMount, ref, computed } from 'vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Rating from 'primevue/rating';
import Calendar from 'primevue/calendar';
import FileUpload from 'primevue/fileupload';

const products = ref(null);
const expandedRows = ref([]);
const searchQuery = ref('');
const editDialog = ref(false);
const editingProduct = ref(null);
const createDialog = ref(false);
const newProduct = ref({
    name: '',
    price: 0,
    category: '',
    inventoryStatus: 'INSTOCK',
    rating: 0,
    image: '' // Default image or leave empty
});

// Add new refs for activity dialog
const activityDialog = ref(false);
const selectedProduct = ref(null);
const newOrder = ref({
    id: null,
    customer: '', // For subject name
    date: new Date().toISOString().split('T')[0],
    amount: 0, // For number of students
    status: 'PENDING'
});

function expandAll() {
    expandedRows.value = products.value.reduce((acc, p) => (acc[p.id] = true) && acc, {});
}

function collapseAll() {
    expandedRows.value = null;
}

function formatCurrency(value) {
    return value.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function getStockSeverity(product) {
    switch (product.inventoryStatus) {
        case 'INSTOCK':
            return 'success';
        case 'LOWSTOCK':
            return 'warn';
        case 'OUTOFSTOCK':
            return 'danger';
        default:
            return null;
    }
}

function getOrderSeverity(order) {
    switch (order.status) {
        case 'DELIVERED':
            return 'success';
        case 'CANCELLED':
            return 'danger';
        case 'PENDING':
            return 'warn';
        case 'RETURNED':
            return 'info';
        default:
            return null;
    }
}

function filterProducts(products, query) {
    if (!query || !products) return products;

    return products.filter((product) => product.name.toLowerCase().includes(query.toLowerCase()));
}

const filteredProducts = computed(() => {
    return filterProducts(products.value, searchQuery.value);
});

function openEdit(product) {
    editingProduct.value = { ...product };
    editDialog.value = true;
}

function saveEdit() {
    const index = products.value.findIndex((p) => p.id === editingProduct.value.id);
    if (index !== -1) {
        products.value[index] = { ...editingProduct.value };
    }
    editDialog.value = false;
    editingProduct.value = null;
}

function openCreateForm() {
    newProduct.value = {
        name: '',
        price: 0,
        category: '',
        inventoryStatus: 'INSTOCK',
        rating: 0,
        image: '',
        orders: [] // Initialize empty orders array
    };
    createDialog.value = true;
}

function saveNewProduct() {
    // Add ID (normally this would come from the backend)
    newProduct.value.id = Date.now(); // Temporary ID generation

    // Add to products array
    products.value = [...products.value, { ...newProduct.value }];

    // Close dialog and reset form
    createDialog.value = false;
    newProduct.value = {
        name: '',
        price: 0,
        category: '',
        inventoryStatus: 'INSTOCK',
        rating: 0,
        image: ''
    };
}

// Add activity functions
function openActivityDialog(product) {
    selectedProduct.value = product;
    newOrder.value = {
        id: Date.now(),
        customer: '',
        date: new Date().toISOString().split('T')[0],
        amount: 0,
        status: 'PENDING'
    };
    activityDialog.value = true;
}

function saveNewActivity() {
    if (selectedProduct.value) {
        // Find the product and add new order
        const product = products.value.find((p) => p.id === selectedProduct.value.id);
        if (product) {
            if (!product.orders) product.orders = [];
            product.orders.push({ ...newOrder.value });
        }
    }
    activityDialog.value = false;
}

// Add function for image handling
function onImageUpload(event) {
    const file = event.files[0];
    const reader = new FileReader();

    reader.onload = (e) => {
        newProduct.value.image = file.name; // Save filename
        // Optional: Preview the image
        // imagePreview.value = e.target.result;
    };

    reader.readAsDataURL(file);
}

// Add function for edit image handling
function onEditImageUpload(event) {
    const file = event.files[0];
    const reader = new FileReader();

    reader.onload = (e) => {
        editingProduct.value.image = file.name; // Save filename
    };

    reader.readAsDataURL(file);
}

// Optional: Add ref for image preview if needed
// const imagePreview = ref('');

onBeforeMount(() => {
    ProductService.getProductsWithOrdersSmall().then((data) => (products.value = data));
});
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center">
            <h3>Teacher</h3>
            <Button label="Register" icon="pi pi-plus" @click="openCreateForm" />
        </div>

        <div class="font-semibold text-xl mb-4"></div>
        <DataTable v-model:expandedRows="expandedRows" :value="filteredProducts" dataKey="id" tableStyle="min-width: 60rem">
            <template #header>
                <div class="flex justify-between align-items-center">
                    <span class="p-input-icon-left">
                        <i class="pi pi-search" />
                        <InputText v-model="searchQuery" placeholder="Search Product..." />
                    </span>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button text icon="pi pi-plus" label="Expand All" @click="expandAll" />
                        <Button text icon="pi pi-minus" label="Collapse All" @click="collapseAll" />
                    </div>
                </div>
            </template>

            <Column expander style="width: 5rem" />
            <Column field="name" header="Teacher Name"></Column>
            <Column header="Image">
                <template #body="slotProps">
                    <img :src="`https://primefaces.org/cdn/primevue/images/product/${slotProps.data.image}`" :alt="slotProps.data.image" class="shadow-lg" width="64" />
                </template>
            </Column>

            <Column field="category" header="Category"></Column>
            <Column field="rating" header="No. Subjects">
                <template #body="slotProps">
                    <Rating :modelValue="slotProps.data.rating" readonly />
                </template>
            </Column>
            <Column header="Status">
                <template #body="slotProps">
                    <Tag :value="slotProps.data.inventoryStatus" :severity="getStockSeverity(slotProps.data)" />
                </template>
            </Column>
            <Column header="Activity">
                <template #body="slotProps">
                    <Button icon="pi pi-plus" @click="openActivityDialog(slotProps.data)" />
                </template>
            </Column>
            <Column header="Actions">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" @click="openEdit(slotProps.data)" />
                </template>
            </Column>

            <template #expansion="slotProps">
                <div class="p-4">
                    <h5>Subject for {{ slotProps.data.name }}</h5>
                    <DataTable :value="slotProps.data.orders">
                        <Column field="id" header="Id" sortable></Column>
                        <Column field="customer" header="Subjects" sortable></Column>
                        <Column field="date" header="Date" sortable></Column>
                        <Column field="amount" header="No. Students" sortable>
                            <template #body="slotProps">
                                {{ formatCurrency(slotProps.data.amount) }}
                            </template>
                        </Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.status.toLowerCase()" :severity="getOrderSeverity(slotProps.data)" />
                            </template>
                        </Column>
                        <Column headerStyle="width:4rem">
                            <template #body>
                                <Button icon="pi pi-search" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </DataTable>

        <Dialog v-model:visible="editDialog" modal header="Edit Product" :style="{ width: '450px' }">
            <div class="p-fluid" v-if="editingProduct">
                <div class="field">
                    <label for="name">Name</label>
                    <InputText id="name" v-model="editingProduct.name" />
                </div>
                <div class="field">
                    <label>Current Image</label>
                    <div class="mb-2">
                        <img :src="`https://primefaces.org/cdn/primevue/images/product/${editingProduct.image}`" :alt="editingProduct.image" class="shadow-lg" width="100" />
                    </div>
                    <label>Change Image</label>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" @upload="onEditImageUpload" :auto="true" chooseLabel="Choose New Image" />
                </div>

                <div class="field">
                    <label for="category">Category</label>
                    <InputText id="category" v-model="editingProduct.category" />
                </div>
                <div class="field">
                    <label for="edit-status">Status</label>
                    <Dropdown id="edit-status" v-model="editingProduct.inventoryStatus" :options="['INSTOCK', 'LOWSTOCK', 'OUTOFSTOCK']" placeholder="Select Status" />
                </div>
                <div class="field">
                    <label for="edit-rating">No. Subjects</label>
                    <Rating id="edit-rating" v-model="editingProduct.rating" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="editDialog = false" text />
                <Button label="Save" icon="pi pi-check" @click="saveEdit" autofocus />
            </template>
        </Dialog>

        <Dialog v-model:visible="createDialog" modal header="Add New Product" :style="{ width: '450px' }">
            <div class="p-fluid">
                <div class="field">
                    <label for="new-name">Name</label>
                    <InputText id="new-name" v-model="newProduct.name" />
                </div>
                <div class="field">
                    <label>Image</label>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" @upload="onImageUpload" :auto="true" chooseLabel="Choose Image" />
                </div>

                <div class="field">
                    <label for="new-category">Category</label>
                    <InputText id="new-category" v-model="newProduct.category" />
                </div>
                <div class="field">
                    <label for="new-status">Status</label>
                    <Dropdown id="new-status" v-model="newProduct.inventoryStatus" :options="['INSTOCK', 'LOWSTOCK', 'OUTOFSTOCK']" placeholder="Select Status" />
                </div>
                <div class="field">
                    <label for="new-rating">Rating</label>
                    <Rating id="new-rating" v-model="newProduct.rating" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="createDialog = false" text />
                <Button label="Save" icon="pi pi-check" @click="saveNewProduct" autofocus />
            </template>
        </Dialog>

        <!-- Add Dialog for new activity -->
        <Dialog v-model:visible="activityDialog" modal header="Add New Subject" :style="{ width: '450px' }">
            <div class="p-fluid">
                <div class="field">
                    <label for="subject-name">Subject Name</label>
                    <InputText id="subject-name" v-model="newOrder.customer" />
                </div>
                <div class="field">
                    <label for="subject-date">Date</label>
                    <Calendar id="subject-date" v-model="newOrder.date" dateFormat="yy-mm-dd" />
                </div>
                <div class="field">
                    <label for="students">Number of Students</label>
                    <InputNumber id="students" v-model="newOrder.amount" />
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <Dropdown id="status" v-model="newOrder.status" :options="['PENDING', 'DELIVERED', 'CANCELLED', 'RETURNED']" placeholder="Select Status" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="activityDialog = false" text />
                <Button label="Save" icon="pi pi-check" @click="saveNewActivity" autofocus />
            </template>
        </Dialog>
    </div>
</template>

<style scoped lang="scss">
:deep(.p-datatable-frozen-tbody) {
    font-weight: bold;
}

:deep(.p-datatable-scrollable .p-frozen-column) {
    font-weight: bold;
}
</style>
