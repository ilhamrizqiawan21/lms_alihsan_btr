<?php
include '../config.php';
cek_login([3]);
$title = 'Chat Kelas';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT s.kelas_id FROM siswa s WHERE s.user_id = $user_id"));
$kelas_id = $siswa['kelas_id'] ?? 0;

if (!$kelas_id) {
    set_flash('warning', 'Anda belum memiliki kelas. Hubungi administrator.');
    header('Location: dashboard.php');
    exit;
}

$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);

// Ambil daftar kelas_mapel yang tersedia untuk siswa (menggunakan prepared statement)
$stmt = $conn->prepare("
    SELECT km.id, mp.nama_mapel, u.nama_lengkap as nama_guru
    FROM kelas_mapel km
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id
    JOIN users u ON km.guru_id = u.id
    JOIN tahun_ajaran ta ON km.tahun_ajaran_id = ta.id
    WHERE km.kelas_id = ? AND ta.tahun = ? AND km.semester = ?
    ORDER BY mp.urutan
");
$stmt->bind_param("iss", $kelas_id, $tahun_aktif, $semester_aktif);
$stmt->execute();
$kelas_mapel_options = $stmt->get_result();

$selected_km = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;
if ($selected_km) {
    // Validasi bahwa selected_km memang milik kelas siswa
    $valid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM kelas_mapel WHERE id=$selected_km AND kelas_id=$kelas_id"));
    if (!$valid) $selected_km = 0;
}
?>
<style>
.chat-container { height: 500px; overflow-y: auto; background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
.message { margin-bottom: 1rem; display: flex; flex-direction: column; }
.message-sender { font-weight: bold; font-size: 0.8rem; color: #10b981; }
.message-time { font-size: 0.7rem; color: #94a3b8; margin-left: 8px; }
.message-text { background: white; padding: 0.5rem 1rem; border-radius: 18px; max-width: 80%; word-wrap: break-word; }
.message-right { align-items: flex-end; }
.message-right .message-text { background: #10b981; color: white; }
.input-group { display: flex; gap: 8px; }
.input-group textarea { flex: 1; border-radius: 24px; padding: 10px 16px; border: 1px solid #cbd5e1; resize: none; }
.date-divider { text-align: center; margin: 1rem 0; position: relative; }
.date-divider span { background: #e5e7eb; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; color: #4b5563; }
</style>

<div class="page-header"><h2><i class="fas fa-comments"></i> Chat Kelas</h2></div>

<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Pilih Mata Pelajaran / Guru</label>
            <select name="kelas_mapel_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Pilih --</option>
                <?php while($km = mysqli_fetch_assoc($kelas_mapel_options)): ?>
                <option value="<?= $km['id'] ?>" <?= $selected_km==$km['id']?'selected':'' ?>><?= $km['nama_mapel'] ?> (<?= $km['nama_guru'] ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
    </form>
</div>

<?php if($selected_km): ?>
<div class="form-container">
    <div id="chatMessages" class="chat-container"><div style="text-align:center;">Memuat pesan...</div></div>
    <div class="input-group">
        <textarea id="messageInput" rows="2" placeholder="Tulis pesan... (Enter kirim, Shift+Enter baris baru)"></textarea>
        <button id="sendBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim</button>
    </div>
</div>

<script>
const baseUrl = '<?= $base_url ?>';
const kelasMapelId = <?= $selected_km ?>;
const userId = <?= $user_id ?>;
let lastMessageId = 0;
let lastDate = null;

function formatDateLabel(dateStr) {
    const today = new Date();
    today.setHours(0,0,0,0);
    const msgDate = new Date(dateStr);
    msgDate.setHours(0,0,0,0);
    const diffDays = Math.floor((today - msgDate) / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'Hari Ini';
    if (diffDays === 1) return 'Kemarin';
    if (diffDays <= 7) return 'Minggu Lalu';
    return msgDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function loadMessages() {
    fetch(baseUrl + 'ajax/get_messages.php?kelas_mapel_id=' + kelasMapelId + '&last_id=' + lastMessageId)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                console.error('Server error:', data.error);
                return;
            }
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('chatMessages');
                if (container.children.length === 1 && container.children[0].innerText === 'Memuat pesan...') container.innerHTML = '';
                data.messages.forEach(msg => {
                    const msgDateOnly = msg.created_at.split(' ')[0];
                    if (lastDate !== msgDateOnly) {
                        const divider = document.createElement('div');
                        divider.className = 'date-divider';
                        divider.innerHTML = `<span>${formatDateLabel(msg.created_at)}</span>`;
                        container.appendChild(divider);
                        lastDate = msgDateOnly;
                    }
                    const isMe = (msg.user_id == userId);
                    const div = document.createElement('div');
                    div.className = `message ${isMe ? 'message-right' : ''}`;
                    div.innerHTML = `<div><span class="message-sender">${escapeHtml(msg.nama)}</span><span class="message-time">${msg.time}</span></div><div class="message-text">${escapeHtml(msg.message)}</div>`;
                    container.appendChild(div);
                    lastMessageId = msg.id;
                });
                container.scrollTop = container.scrollHeight;
            }
        })
        .catch(err => console.error('Load messages error:', err));
}

function sendMessage() {
    const message = document.getElementById('messageInput').value.trim();
    if (!message) return;
    fetch(baseUrl + 'ajax/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'kelas_mapel_id=' + kelasMapelId + '&message=' + encodeURIComponent(message) + '&csrf_token=' + encodeURIComponent('<?= csrf_token() ?>')
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('messageInput').value = '';
            loadMessages();
        } else {
            alert('Gagal mengirim pesan: ' + data.message);
        }
    })
    .catch(err => console.error('Send message error:', err));
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('messageInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
setInterval(loadMessages, 3000);
loadMessages();
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>