<?php
/**
 * Notifications - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Notifikasi';
$role_css = 'siswa';
include __DIR__ . '/../includes/header.php';

global $pdo;

$student_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            mark_notification_read($notification_id, $student_id);
            $_SESSION['success_message'] = 'Notifikasi ditandai sebagai sudah dibaca';
            redirect('siswa-notifications');
        } elseif ($_POST['action'] === 'mark_all_read') {
            $count = mark_all_notifications_read($student_id);
            $_SESSION['success_message'] = "{$count} notifikasi ditandai sebagai sudah dibaca";
            redirect('siswa-notifications');
        } elseif ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            delete_notification($notification_id, $student_id);
            $_SESSION['success_message'] = 'Notifikasi dihapus';
            redirect('siswa-notifications');
        }
    }
}

// Get filter
$filter_type = $_GET['type'] ?? 'all';
$filter_read = $_GET['read'] ?? 'all';

// Get notifications
$notifications = get_notifications($student_id, 100, false);

// Filter notifications
if ($filter_type !== 'all') {
    $notifications = array_filter($notifications, function($n) use ($filter_type) {
        return $n['type'] === $filter_type;
    });
}

if ($filter_read === 'unread') {
    $notifications = array_filter($notifications, function($n) {
        return $n['is_read'] == 0;
    });
} elseif ($filter_read === 'read') {
    $notifications = array_filter($notifications, function($n) {
        return $n['is_read'] == 1;
    });
}

// Get statistics
$unread_count = get_unread_notification_count($student_id);
$total_count = count(get_notifications($student_id, 1000, false));
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">
            <i class="fas fa-bell"></i> Notifikasi
        </h2>
        <p class="text-muted mb-0">Kelola notifikasi Anda</p>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-primary mb-2">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="mb-1"><?php echo $total_count; ?></h3>
                <p class="text-muted mb-0">Total Notifikasi</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-warning mb-2">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3 class="mb-1"><?php echo $unread_count; ?></h3>
                <p class="text-muted mb-0">Belum Dibaca</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-success mb-2">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="mb-1"><?php echo $total_count - $unread_count; ?></h3>
                <p class="text-muted mb-0">Sudah Dibaca</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <form method="POST" action="" class="mb-0">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipe</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Semua Tipe</option>
                            <option value="ujian" <?php echo $filter_type === 'ujian' ? 'selected' : ''; ?>>Ujian</option>
                            <option value="pr" <?php echo $filter_type === 'pr' ? 'selected' : ''; ?>>PR</option>
                            <option value="tugas" <?php echo $filter_type === 'tugas' ? 'selected' : ''; ?>>Tugas</option>
                            <option value="nilai" <?php echo $filter_type === 'nilai' ? 'selected' : ''; ?>>Nilai</option>
                            <option value="reminder" <?php echo $filter_type === 'reminder' ? 'selected' : ''; ?>>Reminder</option>
                            <option value="system" <?php echo $filter_type === 'system' ? 'selected' : ''; ?>>System</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="read" class="form-select">
                            <option value="all" <?php echo $filter_read === 'all' ? 'selected' : ''; ?>>Semua</option>
                            <option value="unread" <?php echo $filter_read === 'unread' ? 'selected' : ''; ?>>Belum Dibaca</option>
                            <option value="read" <?php echo $filter_read === 'read' ? 'selected' : ''; ?>>Sudah Dibaca</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notifications List -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Daftar Notifikasi
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($notifications)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item <?php echo $notif['is_read'] ? '' : 'list-group-item-primary'; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-1">
                                        <?php if (!$notif['is_read']): ?>
                                        <span class="badge bg-warning me-2">Baru</span>
                                        <?php endif; ?>
                                        <?php echo escape($notif['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo time_ago($notif['created_at']); ?>
                                    </small>
                                </div>
                                <p class="mb-2"><?php echo escape($notif['message']); ?></p>
                                <div class="d-flex gap-2">
                                    <?php if ($notif['link']): ?>
                                    <a href="<?php echo base_url($notif['link']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Buka
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!$notif['is_read']): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Tandai Dibaca
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus notifikasi ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> Tidak ada notifikasi.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

