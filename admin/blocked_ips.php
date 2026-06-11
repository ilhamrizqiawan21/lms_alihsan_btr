<?php
include '../config.php';
cek_login([1]); // Admin only

// Handle unblock POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_ip'])) {
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $ip = $_POST['unblock_ip'];
        if (unblock_ip($conn, $ip)) {
            set_flash('success', 'IP berhasil di-unblock: ' . e($ip));
        } else {
            set_flash('error', 'Gagal meng-unblock IP: ' . e($ip));
        }
        header('Location: blocked_ips.php');
        exit;
    } else {
        set_flash('error', 'Token CSRF tidak valid.');
    }
}

$blocked = get_blocked_ips($conn);
$title = 'Blocked IPs';
include '../includes/header.php';
?>
<div class="page-header">
    <h2 class="page-title">Blocked IPs</h2>
    <p class="page-subtitle">Daftar IP yang diblokir sementara dan alasan</p>
</div>

<?php show_flash(); ?>

<div class="card">
    <table class="modern-table">
        <thead>
            <tr>
                <th>IP Address</th>
                <th>Blocked Until</th>
                <th>Reason</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($blocked && $blocked->num_rows > 0): while ($row = $blocked->fetch_assoc()): ?>
            <tr>
                <td><?= e($row['ip_address']) ?></td>
                <td><?= e($row['blocked_until']) ?></td>
                <td><?= e($row['reason']) ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="unblock_ip" value="<?= e($row['ip_address']) ?>">
                        <button type="submit" class="btn btn-warning">Unblock</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5">Tidak ada IP yang diblokir.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
