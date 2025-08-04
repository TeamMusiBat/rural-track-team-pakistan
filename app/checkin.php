<?php
header('Content-Type: application/json');
$host = "srv1268.hstgr.io";
$dbname = "u696686061_smartort";
$username = "u696686061_smartort";
$password = "Atifkhan83##";
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? '';
$stmt = $pdo->prepare("INSERT INTO attendance (user_id, check_in) VALUES (?, NOW())");
$stmt->execute([$user_id]);
echo json_encode(['success'=>true]);
