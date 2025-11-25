<?php
// Batch Upload - Syncs employee data, holidays, and time records from Excel
require_once 'dbconfig.php';
include 'sidebar.php';
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$uploadMessage = '';
$uploadType = '';

// Check required PHP extensions
$missingExtensions = [];
if (!extension_loaded('zip')) $missingExtensions[] = 'php_zip';
if (!extension_loaded('gd') && !function_exists('imagecreate')) $missingExtensions[] = 'php_gd';

// Process Excel upload when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if (!empty($missingExtensions)) {
        $uploadMessage = "Server Error: The following PHP extensions are required but disabled in php.ini: " . implode(', ', $missingExtensions);
        $uploadType = 'danger';
    } else {
        $file = $_FILES['excel_file']['tmp_name'];
        
        try {
            // Load Excel file
            $inputFileType = IOFactory::identify($file);
            $reader = IOFactory::createReader($inputFileType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file);
            
            // Create tables before transaction (DDL causes implicit commit)
            $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                business_unit VARCHAR(100),
                daily_rate DECIMAL(10,2) DEFAULT 520.00,
                sss DECIMAL(10,2) DEFAULT 0,
                phic DECIMAL(10,2) DEFAULT 0,
                hdmf DECIMAL(10,2) DEFAULT 0,
                govt_loan DECIMAL(10,2) DEFAULT 0,
                email VARCHAR(100)
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS holidays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE UNIQUE NOT NULL,
                description VARCHAR(255),
                rate_multiplier DECIMAL(5,2) DEFAULT 1.00
            )");

            $pdo->beginTransaction();

            // Process 'Employees' sheet (cols A-C: holidays, F-M: employee data)
            $empSheet = $spreadsheet->getSheetByName('Employees');
            if ($empSheet) {
                $rows = $empSheet->toArray(null, true, true, true);
                
                // Prepare upsert statements
                $stmtEmp = $pdo->prepare("
                    INSERT INTO employees (name, business_unit, daily_rate, sss, phic, hdmf, govt_loan, email)
                    VALUES (:name, :bu, :rate, :sss, :phic, :hdmf, :loan, :email)
                    ON DUPLICATE KEY UPDATE 
                        business_unit = VALUES(business_unit),
                        daily_rate = VALUES(daily_rate),
                        sss = VALUES(sss),
                        phic = VALUES(phic),
                        hdmf = VALUES(hdmf),
                        govt_loan = VALUES(govt_loan),
                        email = VALUES(email)
                ");

                $stmtHol = $pdo->prepare("
                    INSERT INTO holidays (date, description, rate_multiplier)
                    VALUES (:date, :desc, :rate)
                    ON DUPLICATE KEY UPDATE 
                        description = VALUES(description),
                        rate_multiplier = VALUES(rate_multiplier)
                ");

                // Loop through rows (skip header)
                for ($i = 2; $i <= count($rows); $i++) {
                    $row = $rows[$i];

                    // Parse holiday data from columns A-C
                    $holDateRaw = $row['A'] ?? null;
                    if ($holDateRaw) {
                        try {
                            $holDate = is_numeric($holDateRaw) 
                                ? Date::excelToDateTimeObject($holDateRaw)->format('Y-m-d')
                                : date('Y-m-d', strtotime($holDateRaw));
                            
                            $holRate = floatval($row['C'] ?? 0);
                            if ($holRate > 0) {
                                $stmtHol->execute([
                                    ':date' => $holDate,
                                    ':desc' => $row['B'] ?? '',
                                    ':rate' => $holRate
                                ]);
                            }
                        } catch (Exception $e) {}
                    }

                    // Parse employee data from columns F-M
                    $empName = trim($row['F'] ?? '');
                    if ($empName && $empName !== 'Name' && stripos($empName, 'Employees') === false) {
                        $stmtEmp->execute([
                            ':name' => $empName,
                            ':bu'   => $row['G'] ?? '',
                            ':rate' => floatval($row['H'] ?? 520),
                            ':sss'  => floatval($row['I'] ?? 0),
                            ':phic' => floatval($row['J'] ?? 0),
                            ':hdmf' => floatval($row['K'] ?? 0),
                            ':loan' => floatval($row['L'] ?? 0),
                            ':email'=> $row['M'] ?? ''
                        ]);
                    }
                }
            }

            // Process 'TimeSheet' sheet (dynamically maps columns by header text)
            $tsSheet = $spreadsheet->getSheetByName('TimeSheet');
            if (!$tsSheet) {
                $tsSheet = $spreadsheet->getActiveSheet();
            }
            
            $rows = $tsSheet->toArray(null, true, true, true);
            $header = $rows[1];
            
            $colMap = [];
            foreach ($header as $col => $txt) {
                $h = strtolower(trim($txt ?? ''));
                if (strpos($h, 'date') !== false) $colMap['Date'] = $col;
                elseif (strpos($h, 'shift') !== false) $colMap['ShiftNumber'] = $col;
                elseif (strpos($h, 'business') !== false) $colMap['BusinessUnit'] = $col;
                elseif (strpos($h, 'name') !== false) $colMap['Name'] = $col;
                elseif (strpos($h, 'time in') !== false) $colMap['TimeIn'] = $col;
                elseif (strpos($h, 'time out') !== false) $colMap['TimeOut'] = $col;
                elseif (strpos($h, 'hours') !== false) $colMap['Hours'] = $col;
                elseif (strpos($h, 'role') !== false) $colMap['Role'] = $col;
                elseif (strpos($h, 'remark') !== false) $colMap['Remarks'] = $col;
                elseif (strpos($h, 'deduct') !== false) $colMap['Deductions'] = $col;
                elseif (strpos($h, 'short') !== false || strpos($h, 'extra') !== false || strpos($h, 'bonus') !== false || strpos($h, 'sil') !== false) $colMap['Extra'] = $col;
            }
            
            $stmtTS = $pdo->prepare("
                INSERT INTO payrolldata (Date, ShiftNumber, BusinessUnit, Name, TimeIn, TimeOut, Hours, Role, Remarks, Deductions, Extra)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Import time records
            $importedTS = 0;
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];
                $dateRaw = isset($colMap['Date']) ? $row[$colMap['Date']] : null;
                $name = isset($colMap['Name']) ? trim($row[$colMap['Name']] ?? '') : null;
                
                if (empty($dateRaw) || empty($name)) continue;
                
                // Convert Excel date
                try {
                    $date = is_numeric($dateRaw) 
                        ? Date::excelToDateTimeObject($dateRaw)->format('Y-m-d') 
                        : date('Y-m-d', strtotime($dateRaw));
                } catch (Exception $e) { continue; }
                
                // Parse time values
                $timeIn = null; $timeOut = null;
                if (!empty($row[$colMap['TimeIn'] ?? ''])) {
                    $val = $row[$colMap['TimeIn']];
                    $timeIn = is_numeric($val) ? Date::excelToDateTimeObject($val)->format('H:i:s') : date('H:i:s', strtotime($val));
                }
                if (!empty($row[$colMap['TimeOut'] ?? ''])) {
                    $val = $row[$colMap['TimeOut']];
                    $timeOut = is_numeric($val) ? Date::excelToDateTimeObject($val)->format('H:i:s') : date('H:i:s', strtotime($val));
                }

                // Convert deductions to negative
                $deduction = floatval($row[$colMap['Deductions'] ?? ''] ?? 0);
                if ($deduction > 0) $deduction = -$deduction;

                $stmtTS->execute([
                    $date,
                    $row[$colMap['ShiftNumber'] ?? ''] ?? null,
                    $row[$colMap['BusinessUnit'] ?? ''] ?? null,
                    $name,
                    $timeIn,
                    $timeOut,
                    floatval($row[$colMap['Hours'] ?? ''] ?? 0),
                    $row[$colMap['Role'] ?? ''] ?? null,
                    $row[$colMap['Remarks'] ?? ''] ?? null,
                    $deduction,
                    floatval($row[$colMap['Extra'] ?? ''] ?? 0)
                ]);
                $importedTS++;
            }
            
            // Commit changes
            $pdo->commit();
            $uploadMessage = "<strong>Success!</strong> Database Synced.<br>• TimeSheet Records: $importedTS<br>• Employee Data Updated from 'Employees' tab.<br>• Holiday Calendar Updated.";
            $uploadType = 'success';
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $uploadMessage = "Import Failed: " . $e->getMessage();
            $uploadType = 'danger';
        }
    }
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-excel"></i> Batch Upload (Full Sync)</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2"></i> View Statistics
        </a>
    </div>

    <?php if (!empty($missingExtensions)): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Server Configuration Required</h4>
            <p>The following PHP extensions are disabled. You must enable them in your <code>php.ini</code> file to process Excel files:</p>
            <ul>
                <?php foreach ($missingExtensions as $ext): ?>
                    <li><strong><?= htmlspecialchars($ext) ?></strong> (Remove the <code>;</code> before <code>extension=<?= str_replace('php_', '', $ext) ?></code> in php.ini)</li>
                <?php endforeach; ?>
            </ul>
            <hr>
            <p class="mb-0">After saving php.ini, please <strong>restart your Apache server</strong>.</p>
        </div>
    <?php endif; ?>

    <?php if ($uploadMessage): ?>
    <div class="alert alert-<?= $uploadType ?> alert-dismissible fade show" role="alert">
        <?= $uploadMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card-panel p-4 shadow-sm">
                <h4 class="mb-3"><i class="bi bi-upload"></i> Upload Master Excel File</h4>
                <p class="text-muted">Upload your <strong>Payroll Testing Data</strong> file (.xlsx).</p>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Excel File</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required 
                               <?= !empty($missingExtensions) ? 'disabled' : '' ?>>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> System Updates:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Employees Tab:</strong> Updates Daily Rates, SSS, PHIC, HDMF, Loan.</li>
                            <li><strong>Employees Tab (Cols A-C):</strong> Updates Holiday Calendar.</li>
                            <li><strong>TimeSheet Tab:</strong> Imports daily attendance and overtime.</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-custom btn-lg" <?= !empty($missingExtensions) ? 'disabled' : '' ?>>
                        <i class="bi bi-cloud-upload"></i> Sync Database
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-panel p-4 shadow-sm">
                <h5 class="mb-3">Database Status</h5>
                <p class="small text-muted">
                    This upload will refresh the following tables:<br>
                    - <code>employees</code> (Rates & Deductions)<br>
                    - <code>holidays</code> (Calendar)<br>
                    - <code>payrolldata</code> (Logs)
                </p>
                <div class="d-grid gap-2">
                    <a href="salary_summary.php" class="btn btn-outline-primary">
                        <i class="bi bi-cash-stack"></i> Go to Payroll
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Prevent double-submission
document.getElementById('uploadForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Syncing...';
});
</script>