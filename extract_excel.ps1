$ErrorActionPreference = "Stop"
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

$filePath = "c:\Users\DELL\Desktop\sistema 2\PLANILLAS FIJOS VIEJO FORMATO  2023- copia (1).xls"
$workbook = $excel.Workbooks.Open($filePath)

$reportPath = "c:\Users\DELL\Desktop\sistema 2\excel_report.txt"
"Excel Report" | Out-File $reportPath

foreach ($sheet in $workbook.Sheets) {
    "Sheet Name: $($sheet.Name)" | Out-File -Append $reportPath
    
    # Get used range
    $usedRange = $sheet.UsedRange
    $rows = $usedRange.Rows.Count
    $cols = $usedRange.Columns.Count
    
    "Rows: $rows, Cols: $cols" | Out-File -Append $reportPath
    
    # Export up to 50 rows and 20 cols
    $maxR = [math]::Min($rows, 50)
    $maxC = [math]::Min($cols, 20)
    
    for ($r = 1; $r -le $maxR; $r++) {
        $rowValues = @()
        for ($c = 1; $c -le $maxC; $c++) {
            $cell = $usedRange.Cells.Item($r, $c)
            $val = $cell.Text
            if ($val -eq $null) { $val = "" }
            $rowValues += "'$val'"
        }
        $rowString = $rowValues -join ","
        $rowString | Out-File -Append $reportPath
    }
    "---" | Out-File -Append $reportPath
}

$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
Remove-Variable excel

Write-Host "Extraction complete"
