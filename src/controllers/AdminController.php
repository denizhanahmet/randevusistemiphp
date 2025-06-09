<?php

class AdminController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function listAppointments() {
        $stmt = $this->db->query("SELECT * FROM appointments ORDER BY appointment_date, appointment_time");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listBlockedDays() {
        $stmt = $this->db->query("SELECT * FROM blocked_days ORDER BY date");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addBlockedDay($date) {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO blocked_days (date) VALUES (:date)");
        return $stmt->execute([':date' => $date]);
    }

    public function removeBlockedDay($date) {
        $stmt = $this->db->prepare("DELETE FROM blocked_days WHERE date = :date");
        return $stmt->execute([':date' => $date]);
    }
    public function updateStatus($id, $status) {
    $allowed = ['pending', 'approved', 'cancelled', 'customer_canceled'];
    if (!in_array($status, $allowed)) {
        return false;
    }
    $stmt = $this->db->prepare("UPDATE appointments SET status = :status WHERE id = :id");
    return $stmt->execute([':status' => $status, ':id' => $id]);
}
}