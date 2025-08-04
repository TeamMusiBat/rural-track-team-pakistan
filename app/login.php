<?php
header('Content-Type: application/json');
$host = "srv1268.hstgr.io";
$dbname = "u696686061_smartort";
$username = "u696686061_smartort";
$password = "Atifkhan83##";
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$data = json_decode(file_get_contents('php://input'), true);
$user = $data['username'] ?? '';
$pass = $data['password'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
$stmt->execute([$user]);
$row = $stmt->fetch();
if ($row && password_verify($pass, $row['password'])) {
    echo json_encode(['success'=>true, 'user'=>['id'=>$row['id'],'username'=>$row['username'],'full_name'=>$row['full_name']]]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Invalid credentials']);
}
