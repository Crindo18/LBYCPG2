<?php
// payroll.php
require_once 'dbconfig.php';
$activePage = 'payroll.php';
$importMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payroll_file'])) {
    $tmp = $_FILES['payroll_file']['tmp_name'];
    if (is_uploaded_file($tmp)) {
        if (($handle = fopen($tmp, 'r')) !== false) {
            // optional: read header and map columns (we assume correct order)
            $header = fgetcsv($handle);
            $conn->beginTransaction();
            $insert = $conn->prepare("INSERT INTO attendance (`Date`,`ShiftNumber`,`BusinessUnit`,`Name`,`TimeIn`,`TimeOut`,`Hours`,`Role`,`Remarks`,`Deductions`,`Extra`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $rows = 0;
            try {
                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    // ensure at least required indexes exist
                    $insert->execute([
                        $data[0] ?? null, // Date
                        $data[1] ?? null, // ShiftNumber
                        $data[2] ?? null, // BusinessUnit
                        $data[3] ?? null, // Name
                        $data[4] ?? null, // TimeIn
                        $data[5] ?? null, // TimeOut
                        $data[6] ?? null, // Hours
                        $data[7] ?? null, // Role
                        $data[8] ?? null, // Remarks (enum)
                        $data[9] ?? null, // Deductions
                        $data[10] ?? null // Extra (enum)
                    ]);
                    $rows++;
                }
                $conn->commit();
                $importMessage = "Imported {$rows} rows successfully.";
            } catch (Exception $e) {
                $conn->rollBack();
                $importMessage = "Import failed: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $importMessage = "Unable to open uploaded file.";
        }
    } else {
        $importMessage = "No file uploaded.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Payroll - Employee System</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="content">
  <header class="page-header"><h1>Payroll</h1></header>

  <section class="card">
    <h2>Import Payroll CSV</h2>
    <p>CSV columns expected (order): Date, Shift Number, Business Unit, Name, Time In, Time Out, Hours, Role, Remarks, Deductions, Extra</p>
    <form method="post" enctype="multipart/form-data" onsubmit="window.removeEventListener('beforeunload', beforeUnloadFn);">
      <input type="file" name="payroll_file" accept=".csv" required>
      <button type="submit">Upload</button>
    </form>
    <?php if ($importMessage): ?>
      <p style="margin-top:10px;color:<?= strpos($importMessage,'success')!==false ? 'green' : 'red' ?>"><?= htmlspecialchars($importMessage) ?></p>
    <?php endif; ?>
  </section>

</main>

<script>
function beforeUnloadFn(e){
  e.returnValue = 'Information will not be saved.';
}
window.addEventListener('beforeunload', beforeUnloadFn);
</script>
</body>
</html>
