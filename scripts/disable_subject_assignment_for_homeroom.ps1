$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Teacher.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# Add function to check if teacher has homeroom assignment
$homeroomCheckFunction = @'

// Check if teacher has a homeroom assignment
const teacherHasHomeroom = (teacher) => {
    // Check if teacher is assigned as homeroom teacher to any section
    return sections.value.some(section => 
        section.homeroom_teacher_id === teacher.id || 
        section.homeroom_teacher_id === parseInt(teacher.id)
    );
};

// Get teacher's homeroom section details
const getTeacherHomeroomSection = (teacher) => {
    return sections.value.find(section => 
        section.homeroom_teacher_id === teacher.id || 
        section.homeroom_teacher_id === parseInt(teacher.id)
    );
};
'@

# Insert the homeroom check functions after the existing functions
$content = $content -replace "(const openAddSubjectsDialog)", "$homeroomCheckFunction`r`n`r`n`$1"

# Update the "Assign Subject" button to be disabled for homeroom teachers
$oldAssignButton = @'
                                    <Button
                                        icon="pi pi-plus"
                                        class="p-button-rounded p-button-success p-button-sm"
                                        @click="openAddSubjectsDialog(slotProps.data)"
                                        title="Assign Subject to Teacher"
                                    />
'@

$newAssignButton = @'
                                    <Button
                                        icon="pi pi-plus"
                                        class="p-button-rounded p-button-success p-button-sm"
                                        @click="openAddSubjectsDialog(slotProps.data)"
                                        title="Assign Subject to Teacher"
                                        :disabled="teacherHasHomeroom(slotProps.data)"
                                    />
'@

$content = $content -replace [regex]::Escape($oldAssignButton), $newAssignButton

# Add a visual indicator showing homeroom status in the Actions column
$oldActionsColumn = @'
                    <Column header="Actions" style="width: 200px">
                        <template #body="slotProps">
                            <div class="flex gap-2">
                                <div class="flex flex-column gap-1">
                                    <div class="flex gap-1">
                                        <Button
                                            icon="pi pi-home"
                                            class="p-button-rounded p-button-info p-button-sm"
                                            @click="openAssignSectionDialog(slotProps.data)"
                                            title="Assign Homeroom Section"
                                        />
                                        <Button
                                            icon="pi pi-plus"
                                            class="p-button-rounded p-button-success p-button-sm"
                                            @click="openAddSubjectsDialog(slotProps.data)"
                                            title="Assign Subject to Teacher"
                                            :disabled="teacherHasHomeroom(slotProps.data)"
                                        />
                                    </div>
'@

$newActionsColumn = @'
                    <Column header="Actions" style="width: 200px">
                        <template #body="slotProps">
                            <div class="flex gap-2">
                                <div class="flex flex-column gap-1">
                                    <!-- Homeroom Status Indicator -->
                                    <div v-if="teacherHasHomeroom(slotProps.data)" class="mb-1">
                                        <Tag 
                                            :value="`Homeroom: ${getTeacherHomeroomSection(slotProps.data)?.name || 'Unknown'}`" 
                                            severity="info" 
                                            class="text-xs"
                                        />
                                    </div>
                                    <div class="flex gap-1">
                                        <Button
                                            icon="pi pi-home"
                                            class="p-button-rounded p-button-info p-button-sm"
                                            @click="openAssignSectionDialog(slotProps.data)"
                                            title="Assign Homeroom Section"
                                            :disabled="teacherHasHomeroom(slotProps.data)"
                                        />
                                        <Button
                                            icon="pi pi-plus"
                                            class="p-button-rounded p-button-success p-button-sm"
                                            @click="openAddSubjectsDialog(slotProps.data)"
                                            title="Assign Subject to Teacher"
                                            :disabled="teacherHasHomeroom(slotProps.data)"
                                        />
                                    </div>
'@

$content = $content -replace [regex]::Escape($oldActionsColumn), $newActionsColumn

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Disabled subject assignment for homeroom teachers and added homeroom status indicators"
