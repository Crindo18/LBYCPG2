<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Fetch employee data grouped by Name; include LatestID for sorting
$stmt = $pdo->query("
    SELECT Name, BusinessUnit, MAX(ID) AS LatestID
    FROM payrolldata
    GROUP BY Name, BusinessUnit
    ORDER BY MAX(ID) DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Employees</h2>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#employeeModal" id="addBtn">
            <i class="bi bi-person-plus"></i> Add Employee
        </button>
    </div>

    <!-- Filter / Search / Sort -->
    <div class="card-panel mb-3 p-4 shadow-sm">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold text-secondary">Search by Name</label>
                <input type="text" id="searchName" class="form-control" placeholder="Enter employee name...">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary">Business Unit</label>
                <select id="filterUnit" class="form-select">
                    <option value="">All Units</option>
                    <option value="Canteen">Canteen</option>
                    <option value="Service Crew">Service Crew</option>
                    <option value="Main Office">Main Office</option>
                    <option value="Satellite Office">Satellite Office</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold text-secondary">Sort By</label>
                <select id="sortOrder" class="form-select">
                    <option value="recent_desc">Most Recent</option>
                    <option value="recent_asc">Oldest</option>
                </select>
            </div>

            <div class="col-md-2 text-end">
                <button class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" id="resetBtn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
            </div>
        </div>

        <p class="text-muted mt-4 mb-0 small" id="resultCount"><?= count($employees) ?> employees found.</p>
    </div>

    <!-- Employee Table -->
    <div class="card-panel p-4 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="employeeTable">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Name</th>
                        <th>Business Unit</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $row): ?>
                        <tr data-id="<?= $row['LatestID'] ?>">
                            <td><input type="checkbox" class="rowCheck" value="<?= $row['LatestID'] ?>"></td>
                            <td><?= htmlspecialchars($row['Name']) ?></td>
                            <td><?= htmlspecialchars($row['BusinessUnit']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary editBtn" data-id="<?= $row['LatestID'] ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button id="deleteSelected" class="btn btn-danger mt-3">
            <i class="bi bi-trash"></i> Delete Selected
        </button>
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
            <label class="form-label">Business Unit</label>
            <select name="BusinessUnit" id="BusinessUnit" class="form-select" required>
                <option value="">Select...</option>
                <option value="Canteen">Canteen</option>
                <option value="Service Crew">Service Crew</option>
                <option value="Main Office">Main Office</option>
                <option value="Satellite Office">Satellite Office</option>
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
const tbody = document.querySelector('#employeeTable tbody');
const resultCount = document.getElementById('resultCount');
const employeeForm = document.getElementById('employeeForm');
const selectAll = document.getElementById('selectAll');

function filterTable() {
    const nameVal = searchName.value.toLowerCase();
    const unitVal = filterUnit.value.toLowerCase();
    const orderVal = sortOrder.value;

    let rows = Array.from(tbody.rows);
    rows.forEach(row => {
        const name = row.cells[1].innerText.toLowerCase();
        const unit = row.cells[2].innerText.toLowerCase();
        const match = (!nameVal || name.includes(nameVal)) && (!unitVal || unit === unitVal);
        row.style.display = match ? '' : 'none';
    });

    rows.sort((a, b) => {
        const ida = parseInt(a.dataset.id || '0', 10);
        const idb = parseInt(b.dataset.id || '0', 10);
        return orderVal === 'recent_asc' ? ida - idb : idb - ida;
    });
    rows.forEach(r => tbody.appendChild(r));

    const visible = rows.filter(r => r.style.display !== 'none').length;
    resultCount.textContent = `${visible} employee${visible!==1?'s':''} found.`;
}

resetBtn.addEventListener('click', () => {
    searchName.value = '';
    filterUnit.value = '';
    sortOrder.value = 'recent_desc';
    filterTable();
});

// Add Employee
document.getElementById('addBtn').addEventListener('click', () => {
    employeeForm.reset();
    document.getElementById('employeeID').value = '';
    document.querySelector('#employeeModal .modal-title').innerText = 'Add Employee';
});

// Edit Employee
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        fetch(`modals/get_record.php?id=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    const d = data.data;
                    document.getElementById('employeeID').value = d.ID || '';
                    document.getElementById('Name').value = d.Name || '';
                    document.getElementById('BusinessUnit').value = d.BusinessUnit || '';
                    document.querySelector('#employeeModal .modal-title').innerText = 'Edit Employee';
                    new bootstrap.Modal(document.getElementById('employeeModal')).show();
                } else alert(data.message || 'Record not found');
            })
            .catch(() => alert('Server error'));
    });
});

// Save (Add/Edit)
employeeForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(employeeForm);
    const action = formData.get('ID') ? 'modals/edit_record.php' : 'modals/add_record.php';
    fetch(action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            alert(res.message);
            if (res.success) location.reload();
        })
        .catch(() => alert('Server error'));
});

// Select all checkboxes
selectAll.addEventListener('change', () => {
    document.querySelectorAll('.rowCheck').forEach(chk => chk.checked = selectAll.checked);
});

// Delete selected
document.getElementById('deleteSelected').addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.rowCheck:checked')).map(c => c.value);
    if (ids.length === 0) return alert('Please select at least one employee.');
    if (!confirm(`Delete ${ids.length} selected employee${ids.length>1?'s':''}?`)) return;

    Promise.all(ids.map(id =>
        fetch('modals/delete_record.php', {
            method: 'POST',
            body: new URLSearchParams({ ID: id })
        }).then(r => r.json())
    ))
    .then(results => {
        alert('Selected records deleted successfully.');
        location.reload();
    })
    .catch(() => alert('Server error'));
});

[searchName, filterUnit, sortOrder].forEach(el => el.addEventListener('input', filterTable));
filterTable();
</script>
