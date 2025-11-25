
<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Fetch all time tracking records ordered by most recent
$stmt = $pdo->query("SELECT * FROM payrolldata WHERE Date IS NOT NULL AND Date > '0000-00-00' ORDER BY ID DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique employee names with their business units for dropdown
$empStmt = $pdo->query("
    SELECT DISTINCT Name, BusinessUnit
    FROM payrolldata 
    WHERE Name IS NOT NULL AND Name != ''
    GROUP BY Name, BusinessUnit
    ORDER BY Name ASC
");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Time Tracking</h2>
    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#recordModal" id="addBtn">
      <i class="bi bi-plus-circle"></i> Add Time Record(s)
    </button>
  </div>

  <div class="card-panel mb-3 p-4 shadow-sm">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold text-secondary">Search by Name</label>
        <input type="text" id="searchName" class="form-control" placeholder="Enter employee name...">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold text-secondary">From Date</label>
        <input type="date" id="filterDateFrom" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold text-secondary">Until Date</label>
        <input type="date" id="filterDateUntil" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold text-secondary">Sort By</label>
        <select id="sortOrder" class="form-select">
          <option value="recent">Most Recent</option>
          <option value="oldest">Oldest</option>
        </select>
      </div>
      <div class="col-md-2 text-end">
        <button class="btn btn-outline-secondary w-100 rounded-pill shadow-sm" id="resetBtn">
          <i class="bi bi-arrow-counterclockwise"></i> Reset
        </button>
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-4">
      <p class="text-muted mb-0 small" id="resultCount"></p>
      <button id="deleteSelected" class="btn btn-danger" disabled style="opacity: 0.5; cursor: not-allowed;">
        <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
      </button>
    </div>
  </div>

  <div class="card-panel p-3">
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
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th style="width: 40px;"><input type="checkbox" id="selectAll" title="Select All"></th>
            <th style="width: 110px;">Date</th>
            <th style="width: 80px;">Shift #</th>
            <th style="width: 180px;">Name</th>
            <th style="width: 140px;">Business Unit</th>
            <th style="width: 100px;">Role</th>
            <th style="width: 90px;">Time In</th>
            <th style="width: 90px;">Time Out</th>
            <th style="width: 80px;">Hours</th>
            <th style="width: 100px;">Remarks</th>
            <th style="width: 100px;" class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="recordsTbody">
          <?php if ($records): ?>
            <?php foreach ($records as $row): ?>
              <tr data-id="<?= (int)$row['ID'] ?>" data-date="<?= htmlspecialchars($row['Date']) ?>">
                <td><input type="checkbox" class="rowCheck" value="<?= (int)$row['ID'] ?>"></td>
                <td><?= htmlspecialchars($row['Date']) ?></td>
                <td><?= htmlspecialchars($row['ShiftNumber']) ?></td>
                <td><?= htmlspecialchars($row['Name']) ?></td>
                <td><?= htmlspecialchars($row['BusinessUnit']) ?></td>
                <td><?= htmlspecialchars($row['Role']) ?></td>
                <td><?= htmlspecialchars($row['TimeIn']) ?></td>
                <td><?= htmlspecialchars($row['TimeOut']) ?></td>
                <td><?= htmlspecialchars($row['Hours']) ?></td>
                <td><?= htmlspecialchars($row['Remarks']) ?></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-primary editBtn" data-id="<?= (int)$row['ID'] ?>">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="11" class="text-center text-muted">No time records found.</td></tr>
          <?php endif; ?>
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

<div class="modal fade" id="recordModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <form id="recordForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Time Record(s)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="ID" id="recordID">
          <div id="record-fields">
            <div class="record-row border-bottom pb-3 mb-3">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Name</label>
                  <select name="Name[]" class="form-select name-select" required>
                    <option value="">Select Employee...</option>
                    <?php foreach ($employees as $emp): ?>
                      <option value="<?= htmlspecialchars($emp['Name']) ?>" data-unit="<?= htmlspecialchars($emp['BusinessUnit']) ?>">
                        <?= htmlspecialchars($emp['Name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Business Unit</label>
                  <input type="text" name="BusinessUnit[]" class="form-control business-unit-field" readonly style="background-color: #e9ecef;">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Date</label>
                  <input type="date" name="Date[]" class="form-control" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Shift Number</label>
                  <input type="text" name="ShiftNumber[]" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Role</label>
                  <select name="Role[]" class="form-select" required>
                    <option value="">Select...</option>
                    <option>Crew</option>
                    <option>Cashier</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Remarks</label>
                  <select name="Remarks[]" class="form-select">
                    <option value="">Select...</option>
                    <option>OnDuty</option>
                    <option>Overtime</option>
                    <option>Late</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Time In</label>
                  <input type="time" name="TimeIn[]" class="form-control">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Time Out</label>
                  <input type="time" name="TimeOut[]" class="form-control">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Hours</label>
                  <input type="number" step="0.01" name="Hours[]" class="form-control">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Deductions (₱)</label>
                  <input type="number" step="0.01" name="Deductions[]" class="form-control" placeholder="0.00">
                  <small class="text-muted">Enter positive amount</small>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Extra/Bonus (₱)</label>
                  <input type="number" step="0.01" name="Extra[]" class="form-control" placeholder="0.00">
                  <small class="text-muted">SIL, Bonus, etc.</small>
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="add-record-row">
            <i class="bi bi-plus-circle"></i> Add Another Record
          </button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Initialize DOM elements and modal
const tbody = document.getElementById('recordsTbody');
const recordModalEl = document.getElementById('recordModal');
const recordModal = new bootstrap.Modal(recordModalEl);
const selectAll = document.getElementById('selectAll');
const deleteSelectedBtn = document.getElementById('deleteSelected');
const selectedCountSpan = document.getElementById('selectedCount');
const recordsPerPageSelect = document.getElementById('recordsPerPage');
const pageInfo = document.getElementById('pageInfo');
const pagination = document.getElementById('pagination');

// Track current page and filtered records
let currentPage = 1;
let filteredRows = [];

// Helper function to get all table rows as array
function getRowsArray() { return Array.from(tbody.querySelectorAll('tr')); }

// Initialize filter elements
const searchName = document.getElementById('searchName');
const filterDateFrom = document.getElementById('filterDateFrom');
const filterDateUntil = document.getElementById('filterDateUntil');
const sortOrder = document.getElementById('sortOrder');
const resetBtn = document.getElementById('resetBtn');
const resultCount = document.getElementById('resultCount');

// Filter and sort table based on search criteria
function filterTable() {
  const nameVal = (searchName.value || '').toLowerCase();
  const dateFromVal = filterDateFrom.value;
  const dateUntilVal = filterDateUntil.value;
  const order = sortOrder.value;
  let rows = getRowsArray();
  
  // Apply name and date range filters to each row
  rows.forEach(row => {
    const name = (row.cells[3]?.innerText || '').toLowerCase();
    const date = row.dataset.date || '';
    
    let dateMatch = true;
    if (dateFromVal && date < dateFromVal) dateMatch = false;
    if (dateUntilVal && date > dateUntilVal) dateMatch = false;
    
    const match = (!nameVal || name.includes(nameVal)) && dateMatch;
    row.dataset.visible = match ? 'true' : 'false';
  });
  
  // Sort rows by ID based on selected order
  rows.sort((a,b) => {
    const ida = parseInt(a.dataset.id || '0', 10);
    const idb = parseInt(b.dataset.id || '0', 10);
    return order === 'oldest' ? ida - idb : idb - ida;
  });
  rows.forEach(r => tbody.appendChild(r));
  
  // Update filtered results and display count
  filteredRows = rows.filter(r => r.dataset.visible === 'true');
  const visible = filteredRows.length;
  const recordText = visible === 1 ? 'record' : 'records';
  resultCount.textContent = `${visible} ${recordText} found.`;
  
  currentPage = 1;
  displayPage();
}

// Display current page of filtered records with pagination
function displayPage() {
    const recordsPerPage = recordsPerPageSelect.value;
    const showAll = recordsPerPage === 'all';
    const perPage = showAll ? filteredRows.length : parseInt(recordsPerPage);
    
    // Calculate total pages based on filtered records
    const totalRecords = filteredRows.length;
    const totalPages = showAll ? 1 : Math.ceil(totalRecords / perPage);
    
    if (currentPage > totalPages) currentPage = totalPages || 1;
    
    const startIndex = showAll ? 0 : (currentPage - 1) * perPage;
    const endIndex = showAll ? totalRecords : startIndex + perPage;
    
    // Hide all rows then show only current page rows
    Array.from(tbody.rows).forEach(row => row.style.display = 'none');
    
    filteredRows.forEach((row, index) => {
        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    // Update page information text
    const showing = totalRecords === 0 ? 0 : startIndex + 1;
    const to = Math.min(endIndex, totalRecords);
    pageInfo.textContent = `Showing ${showing} to ${to} of ${totalRecords} entries`;
    
    // Render pagination controls and reset checkboxes
    renderPagination(totalPages, showAll);
    
    selectAll.checked = false;
    updateDeleteButton();
}

// Render pagination controls with page numbers
function renderPagination(totalPages, showAll) {
    pagination.innerHTML = '';
    
    if (showAll || totalPages <= 1) return;
    
    // Add previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#">Previous</a>`;
    if (currentPage > 1) {
        prevLi.onclick = (e) => { e.preventDefault(); currentPage--; displayPage(); };
    }
    pagination.appendChild(prevLi);
    
    // Calculate visible page number range
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    // Add first page and ellipsis if needed
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
    
    // Add page number buttons
    for (let i = startPage; i <= endPage; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        if (i !== currentPage) {
            li.onclick = (e) => { e.preventDefault(); currentPage = i; displayPage(); };
        }
        pagination.appendChild(li);
    }
    
    // Add last page and ellipsis if needed
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
    
    // Add next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#">Next</a>`;
    if (currentPage < totalPages) {
        nextLi.onclick = (e) => { e.preventDefault(); currentPage++; displayPage(); };
    }
    pagination.appendChild(nextLi);
}

// Update delete button state based on selected checkboxes
function updateDeleteButton() {
    const visibleChecked = Array.from(document.querySelectorAll('.rowCheck:checked')).filter(
        chk => chk.closest('tr').style.display !== 'none'
    );
    const count = visibleChecked.length;
    selectedCountSpan.textContent = count;
    
    // Enable or disable delete button based on selection count
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

// Reset pagination when records per page changes
recordsPerPageSelect.addEventListener('change', () => {
    currentPage = 1;
    displayPage();
});

// Attach filter event listeners and initialize table
[searchName, filterDateFrom, filterDateUntil, sortOrder].forEach(el => el.addEventListener('input', filterTable));
resetBtn.addEventListener('click', () => {
  searchName.value = ''; filterDateFrom.value = ''; filterDateUntil.value = ''; sortOrder.value = 'recent'; filterTable();
});
filterTable();

// Add another record row to modal form
document.getElementById('add-record-row').addEventListener('click', () => {
  const container = document.getElementById('record-fields');
  const template = container.querySelector('.record-row');
  const newRow = template.cloneNode(true);
  
  // Clear input values in cloned row
  newRow.querySelectorAll('input, select').forEach(el => el.value = '');
  
  // Auto-populate business unit when employee is selected
  const nameSelect = newRow.querySelector('.name-select');
  const businessUnitField = newRow.querySelector('.business-unit-field');
  nameSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit') || '';
    businessUnitField.value = unit;
  });
  
  // Add remove button for the new row
  const removeButtonCol = document.createElement('div');
  removeButtonCol.className = 'col-12 text-end mt-2';
  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'btn btn-danger btn-sm';
  removeBtn.innerHTML = '<i class="bi bi-trash"></i> Remove This Record';
  removeBtn.onclick = () => newRow.remove();
  
  removeButtonCol.appendChild(removeBtn);
  newRow.querySelector('.row').appendChild(removeButtonCol);
  container.appendChild(newRow);
});

// Reset modal when adding new records
document.getElementById('addBtn').addEventListener('click', () => {
  const form = document.getElementById('recordForm');
  form.reset();
  document.getElementById('recordID').value = '';
  document.querySelector('#recordModal .modal-title').textContent = 'Add Time Record(s)';
  document.getElementById('add-record-row').style.display = 'block';

  // Remove extra record rows leaving only first
  const container = document.getElementById('record-fields');
  while (container.children.length > 1) {
    container.removeChild(container.lastChild);
  }
  const firstRow = container.querySelector('.record-row');
  const removeBtn = firstRow.querySelector('.btn-danger');
  if (removeBtn) removeBtn.parentElement.remove();
});

// Attach edit button event listeners
function attachEventListeners() {
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.removeEventListener('click', handleEdit);
    btn.addEventListener('click', handleEdit);
  });
}

// Handle edit button click - fetch and populate record data
function handleEdit(e) {
  const id = e.target.closest('button').dataset.id;
  fetch('modals/get_record.php?id=' + encodeURIComponent(id))
    .then(r => r.json()).then(res => {
      if (!res || !res.success) { 
        alert(res?.message || 'Could not fetch record'); 
        return; 
      }
      const data = res.data;
      document.getElementById('recordID').value = data.ID;
      
      // Reset form to single row
      const container = document.getElementById('record-fields');
      while (container.children.length > 1) {
        container.removeChild(container.lastChild);
      }
      
      const row = container.querySelector('.record-row');
      const removeBtn = row.querySelector('.btn-danger');
      if (removeBtn) removeBtn.parentElement.remove();
      
      // Populate form fields with record data
      row.querySelector('[name="Name[]"]').value = data.Name ?? '';
      row.querySelector('[name="BusinessUnit[]"]').value = data.BusinessUnit ?? '';
      row.querySelector('[name="Date[]"]').value = data.Date ?? '';
      row.querySelector('[name="ShiftNumber[]"]').value = data.ShiftNumber ?? '';
      row.querySelector('[name="Role[]"]').value = data.Role ?? '';
      row.querySelector('[name="Remarks[]"]').value = data.Remarks ?? '';
      row.querySelector('[name="TimeIn[]"]').value = data.TimeIn ?? '';
      row.querySelector('[name="TimeOut[]"]').value = data.TimeOut ?? '';
      row.querySelector('[name="Hours[]"]').value = data.Hours ?? '';
      
      // Convert deductions to positive value for display
      const deductionValue = Math.abs(parseFloat(data.Deductions ?? 0));
      row.querySelector('[name="Deductions[]"]').value = deductionValue > 0 ? deductionValue : '';
      row.querySelector('[name="Extra[]"]').value = data.Extra ?? '';

      document.querySelector('#recordModal .modal-title').textContent = 'Edit Time Record';
      document.getElementById('add-record-row').style.display = 'none';
      recordModal.show();
    }).catch(() => alert('Server error'));
}

attachEventListeners();

// Auto-populate business unit when employee is selected
document.querySelectorAll('.name-select').forEach(select => {
  select.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit') || '';
    const row = this.closest('.record-row');
    const businessUnitField = row.querySelector('.business-unit-field');
    if (businessUnitField) {
      businessUnitField.value = unit;
    }
  });
});

// Select all visible checkboxes on current page
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

// Delete selected records with confirmation
deleteSelectedBtn.addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.rowCheck:checked')).map(c => c.value);
    if (ids.length === 0) return alert('Please select at least one record.');
    if (!confirm(`Delete ${ids.length} selected record${ids.length>1?'s':''}?`)) return;

    // Delete all selected records via API
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

// Handle form submission for add or edit
document.getElementById('recordForm').addEventListener('submit', e => {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  const isEdit = !!data.get('ID');
  const action = isEdit ? 'modals/edit_record.php' : 'modals/add_record.php';

  // Submit form data to appropriate endpoint
  fetch(action, { method:'POST', body: data })
    .then(r => r.json())
    .then(d => { 
      alert(d.message); 
      if (d.success) location.reload(); 
    })
    .catch(() => alert('Server error'));
});

// Initialize delete button state
updateDeleteButton();
</script>