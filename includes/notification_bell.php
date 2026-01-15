<?php
/**
 * ============================================
 * NOTIFICATION BELL COMPONENT
 * Icon lonceng notifikasi untuk header
 * ============================================
 */

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    return;
}

// Include helper jika belum
if (!function_exists('hitung_notifikasi_belum_dibaca')) {
    require_once __DIR__ . '/notification_helper.php';
}

// Hitung notifikasi belum dibaca
$notif_count = hitung_notifikasi_belum_dibaca($_SESSION['user_id']);

// Ambil 5 notifikasi terbaru untuk dropdown
$notif_list = ambil_notifikasi_terbaru($_SESSION['user_id'], 5);
?>

<style>
/* ============================================
   NOTIFICATION BELL STYLES
   ============================================ */
.notification-bell {
    position: relative;
    display: inline-block;
    cursor: pointer;
    padding: 0.5rem;
    margin: 0 0.5rem;
}

.notification-icon {
    font-size: 1.25rem;
    color: var(--text-primary, #1f2937);
    transition: color 0.3s ease;
}

.notification-bell:hover .notification-icon {
    color: var(--primary, #2563eb);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #ef4444;
    color: white;
    font-size: 0.625rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.25rem;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);
    animation: pulse 2s ease infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Dropdown Notifikasi */
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 380px;
    max-width: 90vw;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    margin-top: 0.5rem;
    z-index: 1000;
    display: none;
    overflow: hidden;
}

.notification-dropdown.active {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.notification-header h3 {
    font-size: 0.9375rem;
    font-weight: 700;
    margin: 0;
}

.mark-all-read {
    font-size: 0.75rem;
    color: white;
    opacity: 0.9;
    cursor: pointer;
    text-decoration: none;
    transition: opacity 0.3s;
}

.mark-all-read:hover {
    opacity: 1;
    text-decoration: underline;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.3s;
    text-decoration: none;
    color: inherit;
    display: block;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item.unread {
    background: #eff6ff;
}

.notification-item-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.notification-item-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.notification-item-content {
    flex: 1;
}

.notification-item-title {
    font-weight: 600;
    font-size: 0.875rem;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.notification-item-text {
    font-size: 0.8125rem;
    color: #6b7280;
    line-height: 1.4;
}

.notification-item-time {
    font-size: 0.6875rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}

.notification-footer {
    padding: 0.75rem 1.25rem;
    text-align: center;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

.notification-footer a {
    color: #2563eb;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    transition: color 0.3s;
}

.notification-footer a:hover {
    color: #1e40af;
}

.notification-empty {
    padding: 3rem 1.25rem;
    text-align: center;
    color: #9ca3af;
}

.notification-empty i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.notification-empty p {
    font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 480px) {
    .notification-dropdown {
        width: 100vw;
        right: -1rem;
        border-radius: 12px 12px 0 0;
    }
}
</style>

<!-- HTML Structure -->
<div class="notification-bell" id="notificationBell">
    <i class="fas fa-bell notification-icon"></i>
    <?php if ($notif_count > 0) { ?>
        <span class="notification-badge"><?php echo $notif_count > 99 ? '99+' : $notif_count; ?></span>
    <?php } ?>
    
    <!-- Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>🔔 Notifikasi (<?php echo $notif_count; ?> baru)</h3>
            <?php if ($notif_count > 0) { ?>
                <a href="#" class="mark-all-read" onclick="markAllAsRead(event)">
                    Tandai Semua Dibaca
                </a>
            <?php } ?>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notif_list)) { ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>Belum ada notifikasi</p>
                </div>
            <?php } else { ?>
                <?php foreach ($notif_list as $notif) { ?>
                    <a href="<?php echo $notif['link'] ?? '#'; ?>" 
                       class="notification-item <?php echo $notif['sudah_dibaca'] ? '' : 'unread'; ?>"
                       onclick="markAsRead(event, <?php echo $notif['notification_id']; ?>)">
                        <div class="notification-item-header">
                            <div class="notification-item-icon">
                                <i class="fas <?php echo get_icon_class($notif['icon']); ?>"></i>
                            </div>
                            <div class="notification-item-content">
                                <div class="notification-item-title">
                                    <?php echo htmlspecialchars($notif['judul']); ?>
                                </div>
                                <div class="notification-item-text">
                                    <?php echo htmlspecialchars($notif['pesan']); ?>
                                </div>
                                <div class="notification-item-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo format_waktu_relatif($notif['dibuat_pada']); ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            <?php } ?>
        </div>
        
        <div class="notification-footer">
            <a href="notifications.php">
                Lihat Semua Notifikasi
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<script>
// ============================================
// NOTIFICATION BELL JAVASCRIPT
// ============================================

// Toggle dropdown saat icon diklik
document.getElementById('notificationBell').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('active');
});

// Tutup dropdown saat klik di luar
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.getElementById('notificationBell');
    
    if (!bell.contains(e.target)) {
        dropdown.classList.remove('active');
    }
});

// Tandai notifikasi sebagai dibaca saat diklik
function markAsRead(event, notificationId) {
    // Kirim AJAX request untuk update database
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update badge count
            updateNotificationCount();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Tandai semua notifikasi sebagai dibaca
function markAllAsRead(event) {
    event.preventDefault();
    event.stopPropagation();
    
    if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) {
        return;
    }
    
    fetch('api/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload halaman untuk update tampilan
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Update jumlah notifikasi belum dibaca
function updateNotificationCount() {
    fetch('api/get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                } else {
                    // Buat badge baru jika belum ada
                    const bell = document.getElementById('notificationBell');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = data.count > 99 ? '99+' : data.count;
                    bell.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => console.error('Error:', error));
}

// Auto-refresh notification count setiap 30 detik
setInterval(updateNotificationCount, 30000);
</script>