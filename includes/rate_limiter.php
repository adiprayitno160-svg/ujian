<?php
/**
 * Rate Limiter Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Prevent API abuse and fraud
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Check rate limit for user/action
 * @param string $action Action identifier (e.g., 'save_answer', 'security_check')
 * @param int $user_id User ID
 * @param int $max_requests Maximum requests allowed
 * @param int $time_window Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function check_rate_limit($action, $user_id, $max_requests = 60, $time_window = 60) {
    global $pdo;
    
    try {
        // Create rate_limits table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            user_id INT NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_action_user (action, user_id),
            INDEX idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Get or create rate limit record
        $stmt = $pdo->prepare("SELECT * FROM rate_limits 
                              WHERE action = ? AND user_id = ? 
                              AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$action, $user_id, $time_window]);
        $rate_limit = $stmt->fetch();
        
        $now = time();
        
        if ($rate_limit) {
            // Check if limit exceeded
            if ($rate_limit['request_count'] >= $max_requests) {
                $reset_time = strtotime($rate_limit['window_start']) + $time_window;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $reset_time,
                    'message' => 'Rate limit exceeded. Please wait ' . ($reset_time - $now) . ' seconds.'
                ];
            }
            
            // Increment count
            $stmt = $pdo->prepare("UPDATE rate_limits 
                                  SET request_count = request_count + 1,
                                      last_request = NOW()
                                  WHERE id = ?");
            $stmt->execute([$rate_limit['id']]);
            
            $remaining = $max_requests - $rate_limit['request_count'] - 1;
            $reset_time = strtotime($rate_limit['window_start']) + $time_window;
            
            return [
                'allowed' => true,
                'remaining' => max(0, $remaining),
                'reset_time' => $reset_time
            ];
        } else {
            // Create new rate limit record
            $stmt = $pdo->prepare("INSERT INTO rate_limits (action, user_id, request_count, window_start) 
                                  VALUES (?, ?, 1, NOW())
                                  ON DUPLICATE KEY UPDATE request_count = 1, window_start = NOW()");
            $stmt->execute([$action, $user_id]);
            
            return [
                'allowed' => true,
                'remaining' => $max_requests - 1,
                'reset_time' => $now + $time_window
            ];
        }
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        // On error, allow request (fail open for availability)
        return [
            'allowed' => true,
            'remaining' => $max_requests,
            'reset_time' => time() + $time_window
        ];
    }
}

/**
 * Check rate limit for IP address (for anonymous/unauthenticated requests)
 */
function check_ip_rate_limit($action, $ip_address, $max_requests = 30, $time_window = 60) {
    global $pdo;
    
    try {
        // Create ip_rate_limits table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS ip_rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_action_ip (action, ip_address),
            INDEX idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->prepare("SELECT * FROM ip_rate_limits 
                              WHERE action = ? AND ip_address = ? 
                              AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$action, $ip_address, $time_window]);
        $rate_limit = $stmt->fetch();
        
        $now = time();
        
        if ($rate_limit) {
            if ($rate_limit['request_count'] >= $max_requests) {
                $reset_time = strtotime($rate_limit['window_start']) + $time_window;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $reset_time
                ];
            }
            
            $stmt = $pdo->prepare("UPDATE ip_rate_limits 
                                  SET request_count = request_count + 1,
                                      last_request = NOW()
                                  WHERE id = ?");
            $stmt->execute([$rate_limit['id']]);
            
            $remaining = $max_requests - $rate_limit['request_count'] - 1;
            $reset_time = strtotime($rate_limit['window_start']) + $time_window;
            
            return [
                'allowed' => true,
                'remaining' => max(0, $remaining),
                'reset_time' => $reset_time
            ];
        } else {
            $stmt = $pdo->prepare("INSERT INTO ip_rate_limits (action, ip_address, request_count, window_start) 
                                  VALUES (?, ?, 1, NOW())");
            $stmt->execute([$action, $ip_address]);
            
            return [
                'allowed' => true,
                'remaining' => $max_requests - 1,
                'reset_time' => $now + $time_window
            ];
        }
    } catch (PDOException $e) {
        error_log("IP rate limit check error: " . $e->getMessage());
        return [
            'allowed' => true,
            'remaining' => $max_requests,
            'reset_time' => time() + $time_window
        ];
    }
}

/**
 * Clean old rate limit records
 */
function clean_rate_limits($older_than_hours = 24) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM rate_limits 
                              WHERE window_start < DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $stmt->execute([$older_than_hours]);
        
        $stmt = $pdo->prepare("DELETE FROM ip_rate_limits 
                              WHERE window_start < DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $stmt->execute([$older_than_hours]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Clean rate limits error: " . $e->getMessage());
        return false;
    }
}



