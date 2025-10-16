<?php
// reports.php
require_once 'dbconfig.php';
$activePage = 'reports.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Reports - Employee System</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="content">
  <header class="page-header"><h1>Reports</h1></header>

  <section class="card">
    <p>This is a placeholder for Reports. You can add:</p>
    <ul>
      <li>Date range filters</li>
      <li>Business unit filters</li>
      <li>Export to CSV/PDF</li>
      <li>Charts (weekly overtime, late counts)</li>
    </ul>
  </section>
</main>
</body>
</html>
