<?php
/**
 * Notification Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

/**
 * Create notification
 */
function create_notification($user_id, $type, $title, $message, $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $title, $message, $link]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for user
 */
function get_notifications($user_id, $limit = 50, $unread_only = false) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count
 */
function get_unread_notification_count($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Get unread notification count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 */
function mark_notification_read($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 */
function mark_all_notifications_read($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Mark all notifications read error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Delete notification
 */
function delete_notification($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Delete notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for ujian reminder
 */
function create_ujian_reminder($sesi_id, $hours_before = 24) {
    global $pdo;
    
    try {
        // Get sesi and ujian info
        $stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, u.id_mapel, m.nama_mapel 
                              FROM sesi_ujian s 
                              INNER JOIN ujian u ON s.id_ujian = u.id 
                              LEFT JOIN mapel m ON u.id_mapel = m.id 
                              WHERE s.id = ?");
        $stmt->execute([$sesi_id]);
        $sesi = $stmt->fetch();
        
        if (!$sesi) {
            return false;
        }
        
        // Get all students assigned to this sesi
        $stmt = $pdo->prepare("SELECT DISTINCT id_siswa FROM nilai WHERE id_sesi = ?");
        $stmt->execute([$sesi_id]);
        $students = $stmt->fetchAll();
        
        $waktu_mulai = new DateTime($sesi['waktu_mulai']);
        $reminder_time = clone $waktu_mulai;
        $reminder_time->modify("-{$hours_before} hours");
        $now = new DateTime();
        
        // Only create notification if reminder time hasn't passed
        if ($reminder_time > $now) {
            foreach ($students as $student) {
                $title = "Reminder: Ujian {$sesi['judul_ujian']}";
                $message = "Ujian {$sesi['judul_ujian']} ({$sesi['nama_mapel']}) akan dimulai pada " . format_datetime($sesi['waktu_mulai']);
                $link = base_url('siswa-ujian-list');
                create_notification($student['id_siswa'], 'reminder', $title, $message, $link);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Create ujian reminder error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for nilai keluar
 */
function create_nilai_notification($nilai_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT n.*, u.judul as judul_ujian, m.nama_mapel 
                              FROM nilai n 
                              INNER JOIN ujian u ON n.id_ujian = u.id 
                              LEFT JOIN mapel m ON u.id_mapel = m.id 
                              WHERE n.id = ?");
        $stmt->execute([$nilai_id]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            return false;
        }
        
        $title = "Nilai Ujian: {$nilai['judul_ujian']}";
        $message = "Nilai untuk ujian {$nilai['judul_ujian']} ({$nilai['nama_mapel']}) telah keluar. Nilai Anda: {$nilai['nilai']}";
        $link = base_url('siswa/ujian/hasil.php?id=' . $nilai['id_sesi']);
        
        return create_notification($nilai['id_siswa'], 'nilai', $title, $message, $link);
    } catch (Exception $e) {
        error_log("Create nilai notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Format datetime for notification
 */
function format_datetime($datetime) {
    if (empty($datetime)) {
        return '';
    }
    
    try {
        $dt = new DateTime($datetime);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $datetime;
    }
}

