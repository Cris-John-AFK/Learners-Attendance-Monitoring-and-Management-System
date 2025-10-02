$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Student.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# Remove responsiveLayout="scroll" to prevent horizontal scrolling
$content = $content -replace 'responsiveLayout="scroll"', 'responsiveLayout="stack"'

# Adjust column widths to fit better without horizontal scroll
$replacements = @{
    # Make Student column more compact
    'header="Student" style="min-width: 200px"' = 'header="Student" style="width: 180px"'
    
    # Make Grade and Section more compact
    'header="Grade" sortable style="width: 120px"' = 'header="Grade" sortable style="width: 80px"'
    'header="Section" sortable style="width: 120px"' = 'header="Section" sortable style="width: 90px"'
    
    # Make LRN more compact
    'header="LRN" sortable style="min-width: 150px"' = 'header="LRN" sortable style="width: 130px"'
    
    # Make QR Code smaller
    'header="QR Code" style="width: 80px"' = 'header="QR Code" style="width: 60px"'
    
    # Make Age smaller
    'header="Age" sortable style="width: 80px"' = 'header="Age" sortable style="width: 50px"'
    
    # Make Gender smaller
    'header="Gender" sortable style="width: 100px"' = 'header="Gender" sortable style="width: 80px"'
    
    # Make Email more compact
    'header="Email" sortable style="min-width: 200px"' = 'header="Email" sortable style="width: 160px"'
    
    # Make Contact smaller
    'header="Contact" style="width: 130px"' = 'header="Contact" style="width: 100px"'
    
    # Make Status smaller
    'header="Status" style="width: 120px"' = 'header="Status" style="width: 100px"'
    
    # Make Reason column wider and ensure it's visible
    'header="Reason" style="width: 200px"' = 'header="Reason" style="width: 150px"'
}

# Apply all replacements
foreach ($old in $replacements.Keys) {
    $new = $replacements[$old]
    $content = $content -replace [regex]::Escape($old), $new
}

# Also fix the Reason column content to show better
$reasonColumnFix = @'
                            <div class="flex align-items-center gap-1">
                                <span class="text-sm text-wrap" style="max-width: 120px; overflow-wrap: break-word;">{{ getStudentReason(slotProps.data) || 'N/A' }}</span>
                                <Button icon="pi pi-search" class="p-button-rounded p-button-text p-button-sm" @click="viewStudentDetails(slotProps.data)" title="View Details" />
                            </div>
'@

$oldReasonContent = @'
                            <div class="flex gap-1">
                                <span class="text-sm">{{ getStudentReason(slotProps.data) || 'N/A' }}</span>
                                <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="viewStudentDetails(slotProps.data)" title="View Details" />
                            </div>
'@

$content = $content -replace [regex]::Escape($oldReasonContent), $reasonColumnFix

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Fixed table layout - removed horizontal scroll and adjusted column widths"
