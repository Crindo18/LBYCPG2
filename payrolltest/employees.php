<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Fetch employee data alphabetically (no ID shown)
$stmt = $pdo->query("SELECT DISTINCT Name, Role, BusinessUnit, Remarks FROM payrolldata ORDER BY Name ASC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Employees</h2>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-person-plus"></i> Add Employee
        </button>
    </div>

    <!-- Search / Filter Bar -->
    <div class="card-panel mb-3 p-4 shadow-sm">
        <div class="row g-3 align-items-end">
            <!-- Search -->
            <div class="col-md-4">
                <label class="form-label fw-semibold text-secondary">Search by Name</label>
                <input type="text" id="searchName" class="form-control" placeholder="Enter employee name...">
            </div>
            <!-- Business Unit Filter -->
            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary">Business Unit</label>
                <select id="filterUnit" class="form-select">
                    <option value="">All Business Units</option>
                    <option value="Canteen">Canteen</option>
                    <option value="Service Crew">Service Crew</option>
                    <option value="Main Office">Main Office</option>
                    <option value="Satellite Office">Satellite Office</option>
                </select>
            </div>
            <!-- Sort Order -->
            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary">Sort By</label>
                <select id="sortOrder" class="form-select">
                    <option value="asc">Name: A → Z</option>
                    <option value="desc">Name: Z → A</option>
                </select>
            </div>
            <!-- Reset Button -->
            <div class="col-md-2 text-end">
                <button class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" id="resetBtn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
        </div>

        <!-- Employees found -->
        <p class="text-muted mt-4 mb-0 small" id="resultCount"><?= count($employees) ?> employees found.</p>
    </div>

    <!-- Employee Table -->
    <div class="card-panel p-4 shadow-sm">
        <table class="table table-hover align-middle" id="employeeTable">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Business Unit</th>
                    <th>Remarks</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Name']) ?></td>
                        <td><?= htmlspecialchars($row['Role']) ?></td>
                        <td><?= htmlspecialchars($row['BusinessUnit']) ?></td>
                        <td><?= htmlspecialchars($row['Remarks']) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary editBtn" data-name="<?= htmlspecialchars($row['Name']) ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger deleteBtn" data-name="<?= htmlspecialchars($row['Name']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="employeeForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="ID" id="employeeID">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="Name" id="Name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <input type="text" name="Role" id="Role" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Business Unit</label>
            <select name="BusinessUnit" id="BusinessUnit" class="form-select" required>
                <option value="">Select...</option>
                <option value="Canteen">Canteen</option>
                <option value="Service Crew">Service Crew</option>
                <option value="Main Office">Main Office</option>
                <option value="Satellite Office">Satellite Office</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Remarks</label>
            <select name="Remarks" id="Remarks" class="form-select">
                <option value="OnDuty">OnDuty</option>
                <option value="Overtime">Overtime</option>
                <option value="Late">Late</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const searchName = document.getElementById('searchName');
const filterUnit = document.getElementById('filterUnit');
const sortOrder  = document.getElementById('sortOrder');
const resetBtn   = document.getElementById('resetBtn');
const table = document.getElementById('employeeTable').getElementsByTagName('tbody')[0];
const resultCount = document.getElementById('resultCount');

// Live filtering and sorting
function filterTable() {
    const nameVal = searchName.value.toLowerCase();
    const unitVal = filterUnit.value.toLowerCase();
    const order = sortOrder.value;
    let rows = Array.from(table.rows);

    rows.forEach(row => {
        const name = row.cells[0].innerText.toLowerCase();
        const unit = row.cells[2].innerText.toLowerCase();
        const match = (!nameVal || name.includes(nameVal)) && (!unitVal || unit === unitVal);
        row.style.display = match ? '' : 'none';
    });

    rows.sort((a,b) => {
        const nameA = a.cells[0].innerText.toLowerCase();
        const nameB = b.cells[0].innerText.toLowerCase();
        return order === 'asc' ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
    }).forEach(r => table.appendChild(r));

    const visible = rows.filter(r => r.style.display !== 'none').length;
    resultCount.textContent = `${visible} employee${visible!==1?'s':''} found.`;
}

// Reset filters
resetBtn.addEventListener('click', () => {
    searchName.value = '';
    filterUnit.value = '';
    sortOrder.value = 'asc';
    filterTable();
});

[searchName, filterUnit, sortOrder].forEach(el => el.addEventListener('input', filterTable));
</script>
