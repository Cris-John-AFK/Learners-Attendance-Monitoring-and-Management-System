$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Student.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# CSS to add for better table layout
$newCSS = @'

/* Improved table layout without horizontal scroll */
.modern-datatable {
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.modern-datatable .p-datatable-table {
    table-layout: fixed;
    width: 100%;
}

.modern-datatable .p-datatable-tbody > tr > td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.modern-datatable .p-datatable-thead > tr > th {
    padding: 0.75rem 0.25rem;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Specific column styling */
.modern-datatable .text-wrap {
    white-space: normal;
    word-break: break-word;
    line-height: 1.2;
}

.modern-datatable .p-button-sm {
    padding: 0.25rem;
    font-size: 0.75rem;
}

.modern-datatable .p-tag {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
'@

# Find the existing CSS section and add our new CSS
if ($content -match "(/\* Modern DataTable Styling \*/)") {
    $content = $content -replace "(/\* Modern DataTable Styling \*/[\s\S]*?\.modern-datatable \{[\s\S]*?\})", "$newCSS"
} else {
    # If no existing CSS section, add it at the end of the style section
    $content = $content -replace "(</style>)", "$newCSS`r`n`$1"
}

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Added improved CSS for table layout"
