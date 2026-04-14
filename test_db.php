<?php
require 'config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    echo "<pre>";
    print_r($users);
    echo "</pre>";
} catch (Exception $e) {
    echo 'Query failed: ' . $e->getMessage();
}
