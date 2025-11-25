
<?php
require_once 'dbconfig.php';
require_once 'calculate_rates.php';
include 'sidebar.php';

// Get date range from query params or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get filter parameters from query string
$filterBusinessUnit = $_GET['business_unit'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterShift = $_GET['shift'] ?? '';
$filterRemarks = $_GET['remarks'] ?? '';
$sortOrder = $_GET['sort'] ?? '';

// Calculate payroll data for the selected date range
$payrollData = calculateRates($pdo, $startDate, $endDate);
$employees = $payrollData['employees'] ?? [];

// Filter employees by business unit, role, shift, and remarks if specified
if ($filterBusinessUnit || $filterRole || $filterShift || $filterRemarks) {
    $employees = array_filter($employees, function($emp) use ($filterBusinessUnit, $filterRole, $filterShift, $filterRemarks, $pdo, $startDate, $endDate) {
        // Check business unit match
        if ($filterBusinessUnit && $emp['business_unit'] !== $filterBusinessUnit) {
            return false;
        }
        
        // Check role, shift, and remarks by querying actual records
        if ($filterRole || $filterShift || $filterRemarks) {
            $stmt = $pdo->prepare("
                SELECT * FROM payrolldata 
                WHERE Name = ? AND Date BETWEEN ? AND ?
            ");
            $stmt->execute([$emp['name'], $startDate, $endDate]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $match = false;
            foreach ($records as $record) {
                $roleMatch = !$filterRole || $record['Role'] === $filterRole;
                $shiftMatch = !$filterShift || $record['ShiftNumber'] == $filterShift;
                $remarksMatch = !$filterRemarks || $record['Remarks'] === $filterRemarks;
                
                if ($roleMatch && $shiftMatch && $remarksMatch) {
                    $match = true;
                    break;
                }
            }
            
            return $match;
        }
        
        return true;
    });
}

// Sort employees by net pay if specified
if ($sortOrder === 'highest') {
    usort($employees, function($a, $b) {
        return $b['totals']['net'] <=> $a['totals']['net'];
    });
} elseif ($sortOrder === 'lowest') {
    usort($employees, function($a, $b) {
        return $a['totals']['net'] <=> $b['totals']['net'];
    });
}

// Fetch unique values for filter dropdowns
$businessUnits = $pdo->query("SELECT DISTINCT BusinessUnit FROM payrolldata WHERE BusinessUnit IS NOT NULL ORDER BY BusinessUnit")->fetchAll(PDO::FETCH_COLUMN);
$roles = $pdo->query("SELECT DISTINCT Role FROM payrolldata WHERE Role IS NOT NULL ORDER BY Role")->fetchAll(PDO::FETCH_COLUMN);
$shifts = $pdo->query("SELECT DISTINCT ShiftNumber FROM payrolldata WHERE ShiftNumber IS NOT NULL ORDER BY ShiftNumber")->fetchAll(PDO::FETCH_COLUMN);
$remarksList = ['OnDuty', 'Overtime', 'Late'];

// Calculate grand totals for all employees
$grandTotal = [
    'gross' => array_sum(array_column(array_column($employees, 'totals'), 'gross')),
    'deductions' => array_sum(array_column(array_column($employees, 'totals'), 'total_deductions')),
    'net' => array_sum(array_column(array_column($employees, 'totals'), 'net'))
];
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-cash-stack"></i> Payroll Overview</h2>
        <div>
            <button class="btn btn-danger" id="downloadSelectedPDFs" onclick="downloadSelectedPDFs()" disabled>
                <i class="bi bi-file-pdf"></i> Download Selected PDFs (<span id="selectedCount">0</span>)
            </button>
            <button class="btn btn-primary" onclick="exportToExcel()">
                <i class="bi bi-file-excel"></i> Export Excel
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card-panel mb-3 p-4">
        <form method="GET" id="filterForm" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-sort-down"></i> Sort By</label>
                <select name="sort" id="sort" class="form-select">
                    <option value="">Default</option>
                    <option value="highest" <?= $sortOrder === 'highest' ? 'selected' : '' ?>>Highest Pay First</option>
                    <option value="lowest" <?= $sortOrder === 'lowest' ? 'selected' : '' ?>>Lowest Pay First</option>
                </select>
            </div>
            
            <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                    <i class="bi bi-funnel"></i> Advanced Filters
                </button>
            </div>
            
            <!-- Advanced Filters (Collapsible) -->
            <div class="col-12 collapse <?= ($filterBusinessUnit || $filterRole || $filterShift || $filterRemarks) ? 'show' : '' ?>" id="advancedFilters">
                <div class="card bg-light p-3 mt-2">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Business Unit</label>
                            <select name="business_unit" id="business_unit" class="form-select">
                                <option value="">All Units</option>
                                <?php foreach ($businessUnits as $unit): ?>
                                    <option value="<?= htmlspecialchars($unit) ?>" <?= $filterBusinessUnit === $unit ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($unit) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="role" class="form-select">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role) ?>" <?= $filterRole === $role ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Shift Number</label>
                            <select name="shift" id="shift" class="form-select">
                                <option value="">All Shifts</option>
                                <?php foreach ($shifts as $shift): ?>
                                    <option value="<?= htmlspecialchars($shift) ?>" <?= $filterShift == $shift ? 'selected' : '' ?>>
                                        Shift <?= htmlspecialchars($shift) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Remarks</label>
                            <select name="remarks" id="remarks" class="form-select">
                                <option value="">All Remarks</option>
                                <?php foreach ($remarksList as $remark): ?>
                                    <option value="<?= htmlspecialchars($remark) ?>" <?= $filterRemarks === $remark ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($remark) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <p class="text-muted mt-3 mb-0">
            <strong>Report Period:</strong> <?= date('F d, Y', strtotime($startDate)) ?> to <?= date('F d, Y', strtotime($endDate)) ?>
            <span class="ms-3"><strong>Employee Records Shown:</strong> <?= count($employees) ?></span>
            <?php if ($filterBusinessUnit || $filterRole || $filterShift || $filterRemarks): ?>
                <span class="ms-3 badge bg-info">Filters Applied</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-currency-dollar"></i> Total Gross Pay</h6>
                <h3 class="text-success fw-bold mb-0">₱<?= number_format($grandTotal['gross'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-dash-circle"></i> Total Deductions</h6>
                <h3 class="text-danger fw-bold mb-0">₱<?= number_format($grandTotal['deductions'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-panel text-center p-3 shadow-sm">
                <h6 class="text-muted mb-2"><i class="bi bi-wallet2"></i> Total Net Pay</h6>
                <h3 class="text-primary fw-bold mb-0">₱<?= number_format($grandTotal['net'], 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- Salary Table -->
    <div class="card-panel p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="salaryTable">
                <thead class="table-light">
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll(this)">
                        </th>
                        <th>Employee</th>
                        <th>Business Unit</th>
                        <th class="text-end">Regular Pay</th>
                        <th class="text-end">Overtime</th>
                        <th class="text-end">Night Diff</th>
                        <th class="text-end">Bonuses</th>
                        <th class="text-end">Allowance</th>
                        <th class="text-end">Holiday Pay</th>
                        <th class="text-end">Gross Pay</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Pay</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input employee-checkbox" 
                                   data-name="<?= htmlspecialchars($emp['name']) ?>" 
                                   onchange="updateSelectedCount()">
                        </td>
                        <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['business_unit']) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['regular'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['overtime'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['night'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['bonus'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['allowance'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($emp['totals']['holiday'], 2) ?></td>
                        <td class="text-end fw-bold text-success">₱<?= number_format($emp['totals']['gross'], 2) ?></td>
                        <td class="text-end text-danger">₱<?= number_format($emp['totals']['total_deductions'], 2) ?></td>
                        <td class="text-end fw-bold text-primary">₱<?= number_format($emp['totals']['net'], 2) ?></td>
                        <td class="text-center">
                            <a href="payslip_view.php?name=<?= urlencode($emp['name']) ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" 
                               class="btn btn-sm btn-outline-primary" title="View Detailed Payslip">
                                <i class="bi bi-file-text"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td></td>
                        <td colspan="8" class="text-end">TOTAL:</td>
                        <td class="text-end text-success">₱<?= number_format($grandTotal['gross'], 2) ?></td>
                        <td class="text-end text-danger">₱<?= number_format($grandTotal['deductions'], 2) ?></td>
                        <td class="text-end text-primary">₱<?= number_format($grandTotal['net'], 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-submit form when any filter changes
document.addEventListener('DOMContentLoaded', function() {
    const filterInputs = ['start_date', 'end_date', 'sort', 'business_unit', 'role', 'shift', 'remarks'];
    
    filterInputs.forEach(inputId => {
        const element = document.getElementById(inputId);
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
});

// Reset all filters to default
function resetFilters() {
    window.location.href = window.location.pathname;
}

// Toggle all employee checkboxes
function toggleSelectAll(checkbox) {
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    employeeCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

// Update selected employee count and button state
function updateSelectedCount() {
    const selectedCheckboxes = document.querySelectorAll('.employee-checkbox:checked');
    const count = selectedCheckboxes.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('downloadSelectedPDFs').disabled = count === 0;
    
    // Update select all checkbox state with indeterminate support
    const allCheckboxes = document.querySelectorAll('.employee-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
    selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
}

// Download PDF payslips for selected employees
function downloadSelectedPDFs() {
    const selectedCheckboxes = document.querySelectorAll('.employee-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // Download each PDF with delay to prevent browser blocking
    selectedCheckboxes.forEach((checkbox, index) => {
        setTimeout(() => {
            const employeeName = checkbox.dataset.name;
            const url = `payslip_pdf.php?name=${encodeURIComponent(employeeName)}&start=${startDate}&end=${endDate}`;
            window.open(url, '_blank');
        }, index * 500);
    });
}

// Export salary table to CSV format
function exportToExcel() {
    const table = document.getElementById('salaryTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    // Build CSV from table rows
    for (let row of rows) {
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        for (let col of cols) {
            // Skip checkbox and action columns
            if (col.cellIndex !== 0 && col.cellIndex !== cols.length - 1) {
                csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
            }
        }
        if (csvRow.length > 0) {
            csv.push(csvRow.join(','));
        }
    }
    
    // Download CSV file
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'salary_summary_<?= $startDate ?>_<?= $endDate ?>.csv';
    a.click();
}
</script>

<style>
.form-check-input:indeterminate {
    background-color: #0d6efd;
    border-color: #0d6efd;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10h8'/%3e%3c/svg%3e");
}
</style>