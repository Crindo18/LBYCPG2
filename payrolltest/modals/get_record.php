<?php
require_once '../dbconfig.php';
header('Content-Type: application/json');

try {
    if (empty($_GET['id'])) throw new Exception("No ID provided.");
    $stmt = $pdo->prepare("SELECT * FROM payrolldata WHERE ID = ?");
    $stmt->execute([$_GET['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) throw new Exception("Record not found.");
    echo json_encode(['success'=>true,'data'=>$data]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
