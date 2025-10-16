<?php
require_once 'dbconfig.php';
include 'sidebar.php';

// --- Filter by business unit if provided ---
$unitFilter = '';
$params = [];
if (isset($_GET['unit']) && $_GET['unit'] !== 'all') {
    $unitMap = [
        'canteen' => 'Canteen',
        'service' => 'Service Crew',
        'main' => 'Main Office',
        'satellite' => 'Satellite Office'
    ];
    if (isset($unitMap[$_GET['unit']])) {
        $unitFilter = "WHERE BusinessUnit = ?";
        $params[] = $unitMap[$_GET['unit']];
    }
}

// --- Fetch employee data ---
$stmt = $pdo->prepare("SELECT * FROM payrolldata $unitFilter ORDER BY ID DESC");
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Employees</h2>
        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-person-plus"></i> Add Employee
        </button>
    </div>

    <div class="card-panel">
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Business Unit</th>
                        <th>Remarks</th>
                        <th>Deductions</th>
                        <th>Extra</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" class="text-center text-muted">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $row): ?>
                            <tr>
                                <td><?= $row['ID'] ?></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= htmlspecialchars($row['Role']) ?></td>
                                <td><?= htmlspecialchars($row['BusinessUnit']) ?></td>
                                <td><?= htmlspecialchars($row['Remarks']) ?></td>
                                <td><?= htmlspecialchars($row['Deductions']) ?></td>
                                <td><?= htmlspecialchars($row['Extra']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary editBtn"
                                            data-id="<?= $row['ID'] ?>"
                                            data-name="<?= htmlspecialchars($row['Name']) ?>"
                                            data-role="<?= htmlspecialchars($row['Role']) ?>"
                                            data-unit="<?= htmlspecialchars($row['BusinessUnit']) ?>"
                                            data-remarks="<?= htmlspecialchars($row['Remarks']) ?>"
                                            data-deductions="<?= htmlspecialchars($row['Deductions']) ?>"
                                            data-extra="<?= htmlspecialchars($row['Extra']) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $row['ID'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ✅ Add Employee Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="addForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addModalLabel">Add Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div class="row g-3">
              <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="Name" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Role</label>
                  <input type="text" name="Role" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Business Unit</label>
                  <select name="BusinessUnit" class="form-select" required>
                      <option value="">Select</option>
                      <option>Canteen</option>
                      <option>Service Crew</option>
                      <option>Main Office</option>
                      <option>Satellite Office</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Remarks</label>
                  <select name="Remarks" class="form-select" required>
                      <option value="">Select</option>
                      <option>OnDuty</option>
                      <option>Overtime</option>
                      <option>Late</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Deductions</label>
                  <input type="number" step="0.01" name="Deductions" class="form-control">
              </div>
              <div class="col-md-6">
                  <label class="form-label">Extra</label>
                  <select name="Extra" class="form-select">
                      <option value="">None</option>
                      <option>Short</option>
                      <option>Misload</option>
                      <option>Bonus</option>
                      <option>SIL</option>
                  </select>
              </div>
          </div>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-custom">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Edit Employee Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="editForm" class="modal-content">
      <input type="hidden" name="ID" id="editID">
      <div class="modal-header">
        <h5 class="modal-title">Edit Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="row g-3">
              <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="Name" id="editName" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Role</label>
                  <input type="text" name="Role" id="editRole" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Business Unit</label>
                  <select name="BusinessUnit" id="editUnit" class="form-select" required>
                      <option>Canteen</option>
                      <option>Service Crew</option>
                      <option>Main Office</option>
                      <option>Satellite Office</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Remarks</label>
                  <select name="Remarks" id="editRemarks" class="form-select" required>
                      <option>OnDuty</option>
                      <option>Overtime</option>
                      <option>Late</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label">Deductions</label>
                  <input type="number" step="0.01" name="Deductions" id="editDeductions" class="form-control">
              </div>
              <div class="col-md-6">
                  <label class="form-label">Extra</label>
                  <select name="Extra" id="editExtra" class="form-select">
                      <option value="">None</option>
                      <option>Short</option>
                      <option>Misload</option>
                      <option>Bonus</option>
                      <option>SIL</option>
                  </select>
              </div>
          </div>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-custom">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastContainer = document.querySelector('.toast-container');

    function showToast(msg, type='success') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.role = 'alert';
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
                           <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        toastContainer.appendChild(toast);
        new bootstrap.Toast(toast, { delay: 3000 }).show();
    }

    // Warn on page reload when modal open
    window.addEventListener('beforeunload', function (e) {
        if (document.querySelector('.modal.show')) {
            e.preventDefault();
            e.returnValue = "Information will not be saved.";
            return "Information will not be saved.";
        }
    });

    // Edit button click
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editID').value = btn.dataset.id;
            document.getElementById('editName').value = btn.dataset.name;
            document.getElementById('editRole').value = btn.dataset.role;
            document.getElementById('editUnit').value = btn.dataset.unit;
            document.getElementById('editRemarks').value = btn.dataset.remarks;
            document.getElementById('editDeductions').value = btn.dataset.deductions;
            document.getElementById('editExtra').value = btn.dataset.extra;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });

    // Submit Add
    document.getElementById('addForm').addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const res = await fetch('modals/add_record.php', { method:'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else showToast(data.message || 'Error adding record', 'danger');
    });

    // Submit Edit
    document.getElementById('editForm').addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const res = await fetch('modals/edit_record.php', { method:'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else showToast(data.message || 'Error updating record', 'danger');
    });

    // Delete button
    document.querySelectorAll('.deleteBtn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (confirm("Are you sure you want to delete this employee?")) {
                const id = btn.dataset.id;
                const formData = new FormData();
                formData.append('ID', id);
                const res = await fetch('modals/delete_record.php', { method:'POST', body: formData });
                const data = await res.json();
                if (data.success) location.reload();
                else showToast(data.message || 'Error deleting record', 'danger');
            }
        });
    });
});
</script>
