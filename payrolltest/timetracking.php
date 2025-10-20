<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Get all time tracking records (Date not null/empty)
$stmt = $pdo->query("SELECT * FROM payrolldata WHERE COALESCE(Date,'') <> '' ORDER BY ID DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct employee names (robust: include any non-empty Name)
$empStmt = $pdo->query("SELECT DISTINCT Name FROM payrolldata WHERE COALESCE(Name,'') <> '' ORDER BY Name ASC");
$employees = $empStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Time Tracking</h2>
    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#recordModal" id="addBtn">
      <i class="bi bi-plus-circle"></i> Add Time Record
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
          <th>Date</th>
          <th>Shift #</th>
          <th>Name</th>
          <th>Business Unit</th>
          <th>Role</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Hours</th>
          <th>Remarks</th>
          <th>Actions</th>
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

<!-- ADD / EDIT Modal -->
<div class="modal fade" id="recordModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="recordForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Time Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="ID" id="recordID">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" name="Date" id="Date" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Shift Number</label>
              <input type="text" name="ShiftNumber" id="ShiftNumber" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Name</label>
              <select name="Name" id="Name" class="form-select" required>
                <option value="">Select Employee...</option>
                <?php foreach ($employees as $emp): ?>
                  <option value="<?= htmlspecialchars($emp) ?>"><?= htmlspecialchars($emp) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Business Unit</label>
              <select name="BusinessUnit" id="BusinessUnit" class="form-select" required>
                <option value="">Select...</option>
                <option value="Canteen">Canteen</option>
                <option value="Service Crew">Service Crew</option>
                <option value="Main Office">Main Office</option>
                <option value="Satellite Office">Satellite Office</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Role</label>
              <select name="Role" id="Role" class="form-select" required>
                <option value="">Select...</option>
                <option value="Crew">Crew</option>
                <option value="Cashier">Cashier</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Remarks</label>
              <select name="Remarks" id="Remarks" class="form-select">
                <option value="">Select...</option>
                <option value="OnDuty">OnDuty</option>
                <option value="Overtime">Overtime</option>
                <option value="Late">Late</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Time In</label>
              <input type="time" name="TimeIn" id="TimeIn" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Time Out</label>
              <input type="time" name="TimeOut" id="TimeOut" class="form-control">
            </div>

            <div class="col-md-4">
              <label class="form-label">Hours</label>
              <input type="number" step="0.01" name="Hours" id="Hours" class="form-control">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5></div>
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

function getRowsArray() {
  return Array.from(tbody.querySelectorAll('tr'));
}

/* Filtering + sorting (client-side, using data-id for DB ID) */
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

  // show/hide
  rows.forEach(row => {
    const name = (row.cells[2].innerText || '').toLowerCase();
    const date = (row.cells[0].innerText || '');
    const match = (!nameVal || name.includes(nameVal)) && (!dateVal || date === dateVal);
    row.style.display = match ? '' : 'none';
  });

  // sort by database ID (data-id)
  rows.sort((a,b) => {
    const ida = parseInt(a.dataset.id || '0', 10);
    const idb = parseInt(b.dataset.id || '0', 10);
    return order === 'oldest' ? ida - idb : idb - ida;
  });

  // re-append in sorted order (keeps hidden rows hidden)
  rows.forEach(r => tbody.appendChild(r));

  const visible = rows.filter(r => r.style.display !== 'none').length;
  resultCount.textContent = `${visible} record${visible !== 1 ? 's' : ''} found.`;
}

[searchName, filterDate, sortOrder].forEach(el => el.addEventListener('input', filterTable));
resetBtn.addEventListener('click', () => {
  searchName.value = '';
  filterDate.value = '';
  sortOrder.value = 'recent';
  filterTable();
});
filterTable();

/* Modal behavior */
const recordModal = new bootstrap.Modal(document.getElementById('recordModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
let currentID = null;

// Enable name select by default when adding
document.getElementById('addBtn').addEventListener('click', () => {
  document.getElementById('recordForm').reset();
  document.getElementById('recordID').value = '';
  document.getElementById('Name').disabled = false;
  document.querySelector('#recordModal .modal-title').textContent = 'Add Time Record';
  recordModal.show();
});

// Populate edit modal (and lock Name). If the existing Name isn't in the dropdown, add it so it displays.
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', e => {
    const id = e.target.closest('button').dataset.id;
    fetch('modals/get_record.php?id=' + encodeURIComponent(id))
      .then(r => r.json())
      .then(res => {
        if (!res || !res.success) {
          alert(res?.message || 'Could not fetch record');
          return;
        }
        const data = res.data;

        // Fill fields
        for (const key of ['ID','Date','ShiftNumber','BusinessUnit','Role','TimeIn','TimeOut','Hours','Remarks']) {
          if (document.getElementById(key)) document.getElementById(key).value = data[key] ?? '';
        }

        // Handle Name select specially: ensure option exists, set it, then disable
        const nameSelect = document.getElementById('Name');
        if (nameSelect) {
          const currentName = data['Name'] ?? '';
          let optionExists = false;
          for (const opt of nameSelect.options) {
            if (opt.value === currentName) { optionExists = true; break; }
          }
          if (!optionExists && currentName !== '') {
            const opt = document.createElement('option');
            opt.value = currentName;
            opt.text = currentName;
            // put as first selectable option (after placeholder)
            nameSelect.insertBefore(opt, nameSelect.children[1] || null);
          }
          nameSelect.value = currentName;
          nameSelect.disabled = true; // lock name
        }

        document.querySelector('#recordModal .modal-title').textContent = 'Edit Time Record';
        recordModal.show();
      })
      .catch(() => alert('Server error'));
  });
});

// Delete flow
document.querySelectorAll('.deleteBtn').forEach(btn => {
  btn.addEventListener('click', e => {
    currentID = e.target.closest('button').dataset.id;
    deleteModal.show();
  });
});
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

// Submit add/edit via unified handlers (add_record.php / edit_record.php)
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
