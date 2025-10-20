<?php
require_once 'dbconfig.php';
include 'sidebar.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$uploadMessage = '';
$uploadType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    require __DIR__ . '/vendor/autoload.php';
    
    $file = $_FILES['excel_file']['tmp_name'];
    
    try {
        $spreadsheet = IOFactory::load($file);
        $pdo->beginTransaction();
        
        // Process TimeSheet tab
        $worksheet = $spreadsheet->getSheetByName('TimeSheet');
        if (!$worksheet) {
            $worksheet = $spreadsheet->getActiveSheet();
        }
        
        $rows = $worksheet->toArray(null, true, true, true);
        $header = $rows[1];
        
        // Map columns
        $colMap = [];
        foreach ($header as $col => $txt) {
            $h = strtolower(trim($txt));
            if (strpos($h, 'date') !== false) $colMap['Date'] = $col;
            elseif (strpos($h, 'shift') !== false) $colMap['ShiftNumber'] = $col;
            elseif (strpos($h, 'business') !== false || strpos($h, 'unit') !== false) $colMap['BusinessUnit'] = $col;
            elseif (strpos($h, 'name') !== false) $colMap['Name'] = $col;
            elseif (strpos($h, 'time in') !== false || strpos($h, 'timein') !== false) $colMap['TimeIn'] = $col;
            elseif (strpos($h, 'time out') !== false || strpos($h, 'timeout') !== false) $colMap['TimeOut'] = $col;
            elseif (strpos($h, 'hours') !== false) $colMap['Hours'] = $col;
            elseif (strpos($h, 'role') !== false) $colMap['Role'] = $col;
            elseif (strpos($h, 'remark') !== false) $colMap['Remarks'] = $col;
            elseif (strpos($h, 'deduct') !== false) $colMap['Deductions'] = $col;
            elseif (strpos($h, 'short') !== false || strpos($h, 'extra') !== false || strpos($h, 'bonus') !== false) $colMap['Extra'] = $col;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO payrolldata (Date, ShiftNumber, BusinessUnit, Name, TimeIn, TimeOut, Hours, Role, Remarks, Deductions, Extra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $imported = 0;
        for ($i = 2; $i <= count($rows); $i++) {
            if (!isset($rows[$i])) continue;
            $row = $rows[$i];
            
            $date = isset($colMap['Date']) ? $row[$colMap['Date']] : null;
            $name = isset($colMap['Name']) ? trim($row[$colMap['Name']]) : null;
            
            if (empty($date) || empty($name)) continue;
            
            // Format date
            if (is_numeric($date)) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date)->format('Y-m-d');
            } else {
                $date = date('Y-m-d', strtotime($date));
            }
            
            // Format times
            $timeIn = isset($colMap['TimeIn']) ? $row[$colMap['TimeIn']] : null;
            $timeOut = isset($colMap['TimeOut']) ? $row[$colMap['TimeOut']] : null;
            
            if ($timeIn && is_numeric($timeIn)) {
                $timeIn = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($timeIn)->format('H:i:s');
            } elseif ($timeIn) {
                $timeIn = date('H:i:s', strtotime($timeIn));
            }
            
            if ($timeOut && is_numeric($timeOut)) {
                $timeOut = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($timeOut)->format('H:i:s');
            } elseif ($timeOut) {
                $timeOut = date('H:i:s', strtotime($timeOut));
            }
            
            // Handle deductions - convert positive to negative for storage
            $deductionValue = isset($colMap['Deductions']) ? floatval($row[$colMap['Deductions']]) : 0;
            if ($deductionValue > 0) {
                $deductionValue = -$deductionValue;
            }
            
            $stmt->execute([
                $date,
                isset($colMap['ShiftNumber']) ? $row[$colMap['ShiftNumber']] : null,
                isset($colMap['BusinessUnit']) ? $row[$colMap['BusinessUnit']] : null,
                $name,
                $timeIn,
                $timeOut,
                isset($colMap['Hours']) ? floatval($row[$colMap['Hours']]) : null,
                isset($colMap['Role']) ? $row[$colMap['Role']] : null,
                isset($colMap['Remarks']) ? $row[$colMap['Remarks']] : null,
                $deductionValue,
                isset($colMap['Extra']) ? floatval($row[$colMap['Extra']]) : 0
            ]);
            $imported++;
        }
        
        $pdo->commit();
        $uploadMessage = "Successfully imported {$imported} time records!";
        $uploadType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $uploadMessage = "Import failed: " . $e->getMessage();
        $uploadType = 'danger';
    }
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-excel"></i> Batch Upload</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2"></i> View Statistics
        </a>
    </div>

    <?php if ($uploadMessage): ?>
    <div class="alert alert-<?= $uploadType ?> alert-dismissible fade show" role="alert">
        <strong><?= $uploadType === 'success' ? 'Success!' : 'Error!' ?></strong> <?= htmlspecialchars($uploadMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Upload Form -->
        <div class="col-md-8">
            <div class="card-panel p-4 shadow-sm">
                <h4 class="mb-3"><i class="bi bi-upload"></i> Upload TimeSheet Excel File</h4>
                <p class="text-muted">Upload an Excel file (.xlsx or .xls) containing employee time records.</p>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Excel File</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
                        <small class="text-muted">The file should have a "TimeSheet" tab with proper headers.</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> Expected Columns:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Required:</strong> Date, Name, Business Unit</li>
                            <li><strong>Optional:</strong> Shift No., Time IN, Time OUT, Hours</li>
                            <li><strong>Optional:</strong> Role, Remarks, Deductions, Extra/Bonus/SIL</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-custom btn-lg">
                        <i class="bi bi-cloud-upload"></i> Upload & Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Instructions & Quick Links -->
        <div class="col-md-4">
            <div class="card-panel p-4 shadow-sm">
                <h5 class="mb-3"><i class="bi bi-question-circle"></i> Upload Instructions</h5>
                <ol class="ps-3">
                    <li class="mb-2">Prepare your Excel file with a "TimeSheet" tab</li>
                    <li class="mb-2">Ensure Date and Name columns are filled</li>
                    <li class="mb-2">Deductions should be entered as positive numbers</li>
                    <li class="mb-2">Click "Upload & Import" to process</li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <small><strong>Note:</strong> Deductions will be automatically converted to negative values for proper calculation.</small>
                </div>
            </div>
            
            <div class="card-panel p-3 mt-3 text-center shadow-sm">
                <h6 class="mb-3">Quick Actions</h6>
                <a href="salary_summary.php" class="btn btn-outline-primary w-100 mb-2">
                    <i class="bi bi-calculator"></i> View Salary Summary
                </a>
                <a href="timetracking.php" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-clock-history"></i> View Time Records
                </a>
                <a href="reports.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-graph-up"></i> View Reports
                </a>
            </div>
            
            <div class="card-panel p-3 mt-3 bg-light shadow-sm">
                <h6 class="mb-2"><i class="bi bi-lightbulb"></i> Pro Tip</h6>
                <small class="text-muted">
                    After uploading, visit the Dashboard to see updated statistics and the Salary Summary to verify calculations.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';
});
</script>