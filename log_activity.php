<?php
function logActivity($conn, $user_id, $action, $document_id, $description) {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, document_id, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isis", $user_id, $action, $document_id, $description);
    $stmt->execute();
}
