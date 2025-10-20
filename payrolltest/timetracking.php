<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Get all time tracking records, ensuring we only get rows with actual dates.
// This prevents employees without time records from showing up
$stmt = $pdo->query("SELECT * FROM payrolldata WHERE Date IS NOT NULL AND Date > '0000-00-00' ORDER BY ID DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get a list of all unique employees with their business units for the dropdown.
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
      <div class="col-md-4">
        <label class="form-label fw-semibold text-secondary">Search by Name</label>
        <input type="text" id="searchName" class="form-control" placeholder="Enter employee name...">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold text-secondary">Filter by Date</label>
        <input type="date" id="filterDate" class="form-control">
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
    <p class="text-muted mt-4 mb-0 small" id="resultCount"></p>
  </div>

  <div class="card-panel p-3">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Date</th><th>Shift #</th><th>Name</th><th>Business Unit</th><th>Role</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Remarks</th><th>Actions</th>
        </tr>
      </thead>
      <tbody id="recordsTbody">
        <?php if ($records): ?>
          <?php foreach ($records as $row): ?>
            <tr data-id="<?= (int)$row['ID'] ?>">
              <td><?= htmlspecialchars($row['Date']) ?></td>
              <td><?= htmlspecialchars($row['ShiftNumber']) ?></td>
              <td><?= htmlspecialchars($row['Name']) ?></td>
              <td><?= htmlspecialchars($row['BusinessUnit']) ?></td>
              <td><?= htmlspecialchars($row['Role']) ?></td>
              <td><?= htmlspecialchars($row['TimeIn']) ?></td>
              <td><?= htmlspecialchars($row['TimeOut']) ?></td>
              <td><?= htmlspecialchars($row['Hours']) ?></td>
              <td><?= htmlspecialchars($row['Remarks']) ?></td>
              <td>
                <button class="btn btn-sm btn-outline-primary editBtn" data-id="<?= (int)$row['ID'] ?>"><i class="bi bi-pencil-square"></i></button>
                <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= (int)$row['ID'] ?>"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" class="text-center text-muted">No time records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
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
                <div class="col-md-3">
                  <label class="form-label">Time In</label>
                  <input type="time" name="TimeIn[]" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Time Out</label>
                  <input type="time" name="TimeOut[]" class="form-control">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Hours</label>
                  <input type="number" step="0.01" name="Hours[]" class="form-control">
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="add-record-row">Add Another Record</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Delete</h5>
      </div>
      <div class="modal-body">Are you sure you want to delete this record?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
/* Helpers */
const tbody = document.getElementById('recordsTbody');
const recordModalEl = document.getElementById('recordModal');
const recordModal = new bootstrap.Modal(recordModalEl);
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
let currentID = null;

function getRowsArray() { return Array.from(tbody.querySelectorAll('tr')); }

/* Filtering + sorting */
const searchName = document.getElementById('searchName');
const filterDate = document.getElementById('filterDate');
const sortOrder = document.getElementById('sortOrder');
const resetBtn = document.getElementById('resetBtn');
const resultCount = document.getElementById('resultCount');

function filterTable() {
  const nameVal = (searchName.value || '').toLowerCase();
  const dateVal = filterDate.value;
  const order = sortOrder.value;
  let rows = getRowsArray();
  rows.forEach(row => {
    const name = (row.cells[2]?.innerText || '').toLowerCase();
    const date = (row.cells[0]?.innerText || '');
    row.style.display = (!nameVal || name.includes(nameVal)) && (!dateVal || date === dateVal) ? '' : 'none';
  });
  rows.sort((a,b) => {
    const ida = parseInt(a.dataset.id || '0', 10);
    const idb = parseInt(b.dataset.id || '0', 10);
    return order === 'oldest' ? ida - idb : idb - ida;
  });
  rows.forEach(r => tbody.appendChild(r));
  const visible = rows.filter(r => r.style.display !== 'none').length;
  resultCount.textContent = `${visible} record${visible !== 1 ? 's' : ''} found.`;
}

[searchName, filterDate, sortOrder].forEach(el => el.addEventListener('input', filterTable));
resetBtn.addEventListener('click', () => {
  searchName.value = ''; filterDate.value = ''; sortOrder.value = 'recent'; filterTable();
});
filterTable();

/* Modal: Add/Edit/Delete Logic */

// Add another record row
document.getElementById('add-record-row').addEventListener('click', () => {
  const container = document.getElementById('record-fields');
  const template = container.querySelector('.record-row');
  const newRow = template.cloneNode(true);
  
  // Clear all input values
  newRow.querySelectorAll('input, select').forEach(el => el.value = '');
  
  // Attach change event listener to the name select in the new row
  const nameSelect = newRow.querySelector('.name-select');
  const businessUnitField = newRow.querySelector('.business-unit-field');
  nameSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit') || '';
    businessUnitField.value = unit;
  });
  
  // Add a remove button to the new row
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

// Reset modal for adding records
document.getElementById('addBtn').addEventListener('click', () => {
  const form = document.getElementById('recordForm');
  form.reset();
  document.getElementById('recordID').value = '';
  document.querySelector('#recordModal .modal-title').textContent = 'Add Time Record(s)';
  document.getElementById('add-record-row').style.display = 'block';

  const container = document.getElementById('record-fields');
  // Remove all but the first record row
  while (container.children.length > 1) {
    container.removeChild(container.lastChild);
  }
  // Remove any "Remove" buttons from the first row if they exist
  const firstRow = container.querySelector('.record-row');
  const removeBtn = firstRow.querySelector('.btn-danger');
  if (removeBtn) removeBtn.parentElement.remove();
});

// Function to attach event listeners to edit and delete buttons
function attachEventListeners() {
  // Edit buttons
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.removeEventListener('click', handleEdit);
    btn.addEventListener('click', handleEdit);
  });
  
  // Delete buttons
  document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.removeEventListener('click', handleDelete);
    btn.addEventListener('click', handleDelete);
  });
}

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
      
      const container = document.getElementById('record-fields');
      // Remove all but the first record row
      while (container.children.length > 1) {
        container.removeChild(container.lastChild);
      }
      
      const row = container.querySelector('.record-row');
      // Remove any "Remove" buttons from the first row
      const removeBtn = row.querySelector('.btn-danger');
      if (removeBtn) removeBtn.parentElement.remove();
      
      row.querySelector('[name="Name[]"]').value = data.Name ?? '';
      row.querySelector('[name="BusinessUnit[]"]').value = data.BusinessUnit ?? '';
      row.querySelector('[name="Date[]"]').value = data.Date ?? '';
      row.querySelector('[name="ShiftNumber[]"]').value = data.ShiftNumber ?? '';
      row.querySelector('[name="Role[]"]').value = data.Role ?? '';
      row.querySelector('[name="Remarks[]"]').value = data.Remarks ?? '';
      row.querySelector('[name="TimeIn[]"]').value = data.TimeIn ?? '';
      row.querySelector('[name="TimeOut[]"]').value = data.TimeOut ?? '';
      row.querySelector('[name="Hours[]"]').value = data.Hours ?? '';

      document.querySelector('#recordModal .modal-title').textContent = 'Edit Time Record';
      document.getElementById('add-record-row').style.display = 'none';
      recordModal.show();
    }).catch(() => alert('Server error'));
}

function handleDelete(e) {
  currentID = e.target.closest('button').dataset.id;
  deleteModal.show();
}

// Initial attachment of event listeners
attachEventListeners();

// Attach change event listeners to all name selects for auto-populating business unit
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

// Handle Delete Confirmation
document.getElementById('confirmDelete').addEventListener('click', () => {
  fetch('modals/delete_record.php', { 
    method: 'POST', 
    body: new URLSearchParams({ ID: currentID }) 
  })
  .then(r => r.json())
  .then(d => { 
    alert(d.message); 
    if (d.success) location.reload(); 
  })
  .catch(() => alert('Server error'));
});

// Handle Form Submit (Add/Edit)
document.getElementById('recordForm').addEventListener('submit', e => {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  const isEdit = !!data.get('ID');
  const action = isEdit ? 'modals/edit_record.php' : 'modals/add_record.php';

  fetch(action, { method:'POST', body: data })
    .then(r => r.json())
    .then(d => { 
      alert(d.message); 
      if (d.success) location.reload(); 
    })
    .catch(() => alert('Server error'));
});
</script>