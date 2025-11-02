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
            <i class="bi bi-person-plus"></i> Add Employee(s)
        </button>
    </div>

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

        <div class="d-flex justify-content-between align-items-center mt-4">
            <p class="text-muted mb-0 small" id="resultCount"><?= count($employees) ?> employees found.</p>
            <button id="deleteSelected" class="btn btn-danger" disabled style="opacity: 0.5; cursor: not-allowed;">
                <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
    </div>

    <div class="card-panel p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <label class="form-label fw-semibold text-secondary me-2">Show</label>
                <select id="recordsPerPage" class="form-select d-inline-block" style="width: auto;">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">All</option>
                </select>
                <span class="text-muted ms-2">entries</span>
            </div>
            <div>
                <span class="text-muted small" id="pageInfo">Showing 0 to 0 of 0 entries</span>
            </div>
        </div>
        
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
        
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="employeeForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee(s)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="ID" id="employeeID">
          <div id="employee-fields">
            <div class="row employee-row mb-3">
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input type="text" name="Name[]" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Business Unit</label>
                <select name="BusinessUnit[]" class="form-select" required>
                    <option value="">Select...</option>
                    <option value="Canteen">Canteen</option>
                    <option value="Service Crew">Service Crew</option>
                    <option value="Main Office">Main Office</option>
                    <option value="Satellite Office">Satellite Office</option>
                </select>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="add-employee-row">Add Another Employee</button>
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
const deleteSelectedBtn = document.getElementById('deleteSelected');
const selectedCountSpan = document.getElementById('selectedCount');
const recordsPerPageSelect = document.getElementById('recordsPerPage');
const pageInfo = document.getElementById('pageInfo');
const pagination = document.getElementById('pagination');

let currentPage = 1;
let filteredRows = [];

// Add another employee row
document.getElementById('add-employee-row').addEventListener('click', () => {
  const employeeFields = document.getElementById('employee-fields');
  const newRow = document.querySelector('.employee-row').cloneNode(true);
  newRow.querySelectorAll('input, select').forEach(el => el.value = '');
  employeeFields.appendChild(newRow);
});

function filterTable() {
    const nameVal = searchName.value.toLowerCase();
    const unitVal = filterUnit.value.toLowerCase();
    const orderVal = sortOrder.value;

    let rows = Array.from(tbody.rows);
    rows.forEach(row => {
        const name = row.cells[1].innerText.toLowerCase();
        const unit = row.cells[2].innerText.toLowerCase();
        const match = (!nameVal || name.includes(nameVal)) && (!unitVal || unit === unitVal);
        row.dataset.visible = match ? 'true' : 'false';
    });

    rows.sort((a, b) => {
        const ida = parseInt(a.dataset.id || '0', 10);
        const idb = parseInt(b.dataset.id || '0', 10);
        return orderVal === 'recent_asc' ? ida - idb : idb - ida;
    });
    rows.forEach(r => tbody.appendChild(r));

    filteredRows = rows.filter(r => r.dataset.visible === 'true');
    const visible = filteredRows.length;
    resultCount.textContent = `${visible} employee${visible!==1?'s':''} found.`;
    
    currentPage = 1;
    displayPage();
}

function displayPage() {
    const recordsPerPage = recordsPerPageSelect.value;
    const showAll = recordsPerPage === 'all';
    const perPage = showAll ? filteredRows.length : parseInt(recordsPerPage);
    
    const totalRecords = filteredRows.length;
    const totalPages = showAll ? 1 : Math.ceil(totalRecords / perPage);
    
    if (currentPage > totalPages) currentPage = totalPages || 1;
    
    const startIndex = showAll ? 0 : (currentPage - 1) * perPage;
    const endIndex = showAll ? totalRecords : startIndex + perPage;
    
    // Hide all rows first
    Array.from(tbody.rows).forEach(row => row.style.display = 'none');
    
    // Show only current page rows
    filteredRows.forEach((row, index) => {
        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    // Update page info
    const showing = totalRecords === 0 ? 0 : startIndex + 1;
    const to = Math.min(endIndex, totalRecords);
    pageInfo.textContent = `Showing ${showing} to ${to} of ${totalRecords} entries`;
    
    // Update pagination
    renderPagination(totalPages, showAll);
    
    // Update select all checkbox
    selectAll.checked = false;
    updateDeleteButton();
}

function renderPagination(totalPages, showAll) {
    pagination.innerHTML = '';
    
    if (showAll || totalPages <= 1) return;
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#">Previous</a>`;
    if (currentPage > 1) {
        prevLi.onclick = (e) => { e.preventDefault(); currentPage--; displayPage(); };
    }
    pagination.appendChild(prevLi);
    
    // Page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        const firstLi = document.createElement('li');
        firstLi.className = 'page-item';
        firstLi.innerHTML = `<a class="page-link" href="#">1</a>`;
        firstLi.onclick = (e) => { e.preventDefault(); currentPage = 1; displayPage(); };
        pagination.appendChild(firstLi);
        
        if (startPage > 2) {
            const dots = document.createElement('li');
            dots.className = 'page-item disabled';
            dots.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dots);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        if (i !== currentPage) {
            li.onclick = (e) => { e.preventDefault(); currentPage = i; displayPage(); };
        }
        pagination.appendChild(li);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('li');
            dots.className = 'page-item disabled';
            dots.innerHTML = `<span class="page-link">...</span>`;
            pagination.appendChild(dots);
        }
        
        const lastLi = document.createElement('li');
        lastLi.className = 'page-item';
        lastLi.innerHTML = `<a class="page-link" href="#">${totalPages}</a>`;
        lastLi.onclick = (e) => { e.preventDefault(); currentPage = totalPages; displayPage(); };
        pagination.appendChild(lastLi);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#">Next</a>`;
    if (currentPage < totalPages) {
        nextLi.onclick = (e) => { e.preventDefault(); currentPage++; displayPage(); };
    }
    pagination.appendChild(nextLi);
}

function updateDeleteButton() {
    const visibleChecked = Array.from(document.querySelectorAll('.rowCheck:checked')).filter(
        chk => chk.closest('tr').style.display !== 'none'
    );
    const count = visibleChecked.length;
    selectedCountSpan.textContent = count;
    
    if (count > 0) {
        deleteSelectedBtn.disabled = false;
        deleteSelectedBtn.style.opacity = '1';
        deleteSelectedBtn.style.cursor = 'pointer';
    } else {
        deleteSelectedBtn.disabled = true;
        deleteSelectedBtn.style.opacity = '0.5';
        deleteSelectedBtn.style.cursor = 'not-allowed';
    }
}

recordsPerPageSelect.addEventListener('change', () => {
    currentPage = 1;
    displayPage();
});

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
    document.querySelector('#employeeModal .modal-title').innerText = 'Add Employee(s)';
    const employeeFields = document.getElementById('employee-fields');
    while (employeeFields.children.length > 1) {
      employeeFields.removeChild(employeeFields.lastChild);
    }
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
                    const employeeFields = document.getElementById('employee-fields');
                    while (employeeFields.children.length > 1) {
                      employeeFields.removeChild(employeeFields.lastChild);
                    }
                    employeeFields.querySelector('input[name="Name[]"]').value = d.Name || '';
                    employeeFields.querySelector('select[name="BusinessUnit[]"]').value = d.BusinessUnit || '';

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

// Select all checkboxes (only visible ones on current page)
selectAll.addEventListener('change', () => {
    document.querySelectorAll('.rowCheck').forEach(chk => {
        if (chk.closest('tr').style.display !== 'none') {
            chk.checked = selectAll.checked;
        }
    });
    updateDeleteButton();
});

// Update delete button when individual checkboxes change
tbody.addEventListener('change', (e) => {
    if (e.target.classList.contains('rowCheck')) {
        updateDeleteButton();
    }
});

// Delete selected
deleteSelectedBtn.addEventListener('click', () => {
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
updateDeleteButton();
</script>