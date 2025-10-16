<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// Get all records ordered by latest Date
$stmt = $pdo->query("SELECT * FROM payrolldata ORDER BY Date DESC, ID DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Time Tracking</h2>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addModal" id="addBtn">
            <i class="bi bi-plus-circle"></i> Add Time Record
        </button>
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
            <tbody>
            <?php if ($records): ?>
                <?php foreach ($records as $row): ?>
                    <tr>
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
                            <button class="btn btn-sm btn-outline-primary editBtn" data-id="<?= $row['ID'] ?>"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['ID'] ?>"><i class="bi bi-trash"></i></button>
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
              <input type="text" name="Name" id="Name" class="form-control" required>
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
              <input type="text" name="Role" id="Role" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Remarks</label>
              <select name="Remarks" id="Remarks" class="form-select">
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

<!-- DELETE confirmation -->
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
let currentID = null;
const recordModal = new bootstrap.Modal(document.getElementById('recordModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
let modalOpen = false;

// Refresh warning only if modal open
window.addEventListener('beforeunload', function (e) {
    if (modalOpen) {
        e.preventDefault();
        e.returnValue = "Information will not be saved.";
    }
});

document.getElementById('addBtn').addEventListener('click', () => {
    document.getElementById('recordForm').reset();
    document.querySelector('.modal-title').textContent = 'Add Time Record';
    document.getElementById('recordID').value = '';
    recordModal.show();
    modalOpen = true;
});

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', e => {
        const id = e.target.closest('button').dataset.id;
        fetch('modals/get_record.php?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    for (const [k,v] of Object.entries(d.data)) {
                        if (document.getElementById(k)) document.getElementById(k).value = v;
                    }
                    document.querySelector('.modal-title').textContent = 'Edit Time Record';
                    recordModal.show();
                    modalOpen = true;
                }
            });
    });
});

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
    });
});

document.getElementById('recordForm').addEventListener('submit', e => {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const action = data.get('ID') ? 'modals/edit_time.php' : 'modals/add_time.php';

    fetch(action, { method:'POST', body:data })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            if (d.success) location.reload();
        });
});

document.getElementById('recordModal').addEventListener('hidden.bs.modal', () => modalOpen = false);
</script>
