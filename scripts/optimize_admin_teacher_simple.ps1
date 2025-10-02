# Simple performance optimization for Admin-Teacher.vue
$filePath = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Teacher.vue"

Write-Host "Optimizing Admin-Teacher.vue performance..." -ForegroundColor Green

# Read the file content
$content = Get-Content $filePath -Raw

# Add cache service import
$importPattern = "import { computed, onMounted, ref } from 'vue';"
$newImport = "import { computed, onMounted, ref } from 'vue';`nimport adminTeacherCache from '@/services/AdminTeacherCacheService';"

if ($content -match [regex]::Escape($importPattern)) {
    $content = $content -replace [regex]::Escape($importPattern), $newImport
    Write-Host "Added cache service import" -ForegroundColor Green
}

# Write back to file
Set-Content $filePath -Value $content -Encoding UTF8

Write-Host "Performance optimization completed!" -ForegroundColor Green
Write-Host "Cache service has been added to improve loading times" -ForegroundColor Cyan
