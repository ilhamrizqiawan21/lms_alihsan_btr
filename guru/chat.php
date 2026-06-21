<?php
include '../config.php';
cek_login([2]);
$title = 'Chat Kelas';
include '../includes/header.php';

$guru_id = $_SESSION['user_id'];
$tahun_aktif = get_tahun_ajaran_aktif($conn);
$semester_aktif = get_semester_aktif($conn);
$kelas_mapel_id = isset($_GET['kelas_mapel_id']) ? (int)$_GET['kelas_mapel_id'] : 0;

$km_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT km.*, k.nama_kelas, mp.nama_mapel FROM kelas_mapel km 
    JOIN kelas k ON km.kelas_id = k.id 
    JOIN mata_pelajaran mp ON km.mapel_id = mp.id 
    WHERE km.id = $kelas_mapel_id AND km.guru_id = $guru_id"));

$kelas_mapel_options = get_kelas_mapel_guru($conn, $guru_id, $tahun_aktif, $semester_aktif);

if (!$kelas_mapel_options || $kelas_mapel_options->num_rows == 0) {
    set_flash('warning', 'Anda belum memiliki penugasan kelas/mapel pada tahun/semester ini.');
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
.date-divider {
    text-align: center;
    margin: 1rem 0;
    position: relative;
}
.date-divider span {
    background: #e5e7eb;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    color: #4b5563;
}
</style>

<div class="page-header"><h2><i class="fas fa-comments"></i> Chat Kelas</h2></div>
<div class="form-container">
    <form method="GET" class="form-row">
        <div class="form-group"><label>Pilih Kelas & Mapel</label>
            <select name="kelas_mapel_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Pilih --</option>
                <?php while($km = mysqli_fetch_assoc($kelas_mapel_options)): ?>
                <option value="<?= $km['id'] ?>" <?= $kelas_mapel_id==$km['id']?'selected':'' ?>><?= $km['nama_kelas'] ?> - <?= $km['nama_mapel'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </form>
</div>
<?php if($kelas_mapel_id && $km_check): ?>
<div class="form-container">
    <div id="chatMessages" class="chat-container"><div style="text-align:center;">Memuat pesan...</div></div>
    <div class="input-group">
        <textarea id="messageInput" rows="2" placeholder="Ketik pesan... (Enter kirim, Shift+Enter baris baru)"></textarea>
        <button id="sendBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim</button>
    </div>
</div>
<script>
const kelasMapelId = <?= $kelas_mapel_id ?>;
const userId = <?= $_SESSION['user_id'] ?>;
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
    fetch('../ajax/get_messages.php?kelas_mapel_id=' + kelasMapelId + '&last_id=' + lastMessageId)
        .then(res => res.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('chatMessages');
                if (container.children.length === 1 && container.children[0].innerText === 'Memuat pesan...') container.innerHTML = '';
                data.messages.forEach(msg => {
                    // Tampilkan pemisah tanggal jika tanggal berubah
                    const msgDate = msg.created_at.split(' ')[0];
                    if (lastDate !== msgDate) {
                        const divider = document.createElement('div');
                        divider.className = 'date-divider';
                        divider.innerHTML = `<span>${formatDateLabel(msg.created_at)}</span>`;
                        container.appendChild(divider);
                        lastDate = msgDate;
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
        });
}

function sendMessage() {
    const message = document.getElementById('messageInput').value.trim();
    if (!message) return;
    fetch('../ajax/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `kelas_mapel_id=${kelasMapelId}&message=${encodeURIComponent(message)}`
    }).then(() => {
        document.getElementById('messageInput').value = '';
        loadMessages();
    });
}

function escapeHtml(str) { return str.replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('messageInput').addEventListener('keypress', function(e) { if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
setInterval(loadMessages, 3000);
loadMessages();
</script>
<?php endif; ?>
<?php include '../includes/footer.php'; 