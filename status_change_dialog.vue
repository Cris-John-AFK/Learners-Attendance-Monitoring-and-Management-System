<!-- Status Change Dialog - Add this to Admin-Student.vue after the Delete Confirmation Dialog -->
<Dialog v-model:visible="statusChangeDialog" modal header="Change Student Status" :style="{ width: '500px' }">
    <div class="p-4">
        <div class="mb-4">
            <h4 class="mb-2">Student: {{ selectedStudentForStatus?.name }}</h4>
            <p class="text-sm text-gray-600">Current Status: {{ getStudentStatusDisplay(selectedStudentForStatus) }}</p>
        </div>
        
        <div class="field mb-4">
            <label for="newStatus" class="block text-sm font-medium mb-2">New Status</label>
            <Dropdown 
                id="newStatus"
                v-model="newStudentStatus" 
                :options="[
                    { label: 'Active', value: 'Active' },
                    { label: 'Dropped Out', value: 'Dropped Out' },
                    { label: 'Transferred Out', value: 'Transferred Out' },
                    { label: 'Graduated', value: 'Graduated' },
                    { label: 'Inactive', value: 'Inactive' }
                ]"
                optionLabel="label"
                optionValue="value"
                placeholder="Select new status"
                class="w-full"
            />
        </div>
        
        <div class="field mb-4">
            <label for="statusReason" class="block text-sm font-medium mb-2">Reason</label>
            <Textarea 
                id="statusReason"
                v-model="statusChangeReason" 
                rows="3" 
                placeholder="Enter reason for status change..."
                class="w-full"
            />
        </div>
        
        <div class="flex justify-end space-x-2 mt-4">
            <Button label="Cancel" class="p-button-text" @click="statusChangeDialog = false" />
            <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveStatusChange" />
        </div>
    </div>
</Dialog>
