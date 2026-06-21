<?php
include '../config.php';
cek_login([1,2,3,4]);
$title = 'Kalender & Reminder';
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT s.id, s.nis, k.nama_kelas 
     FROM siswa s 
     LEFT JOIN kelas k ON s.kelas_id = k.id 
     WHERE s.user_id = ? LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();

// Jika pengguna bukan siswa (mis. admin/guru/kepsek), jangan hentikan halaman
if (!$siswa) {
    $siswa = ['id' => null, 'nis' => null, 'nama_kelas' => null];
}

$currentYear = date('Y');
$currentMonth = date('n');

?>

<style>
.calendar-page {
    display: grid;
    gap: 1.25rem;
}

.calendar-board {
    display: grid;
    grid-template-columns: 2.4fr 1fr;
    gap: 1rem;
}

.calendar-panel,
.calendar-sidebar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 1.2rem;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.calendar-title {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.calendar-title h3 {
    margin: 0;
    font-size: 1.2rem;
}

.calendar-nav button {
    border: 1px solid #d1d5db;
    background: #f8fafc;
    color: #111827;
    border-radius: 12px;
    min-width: 42px;
    padding: 0.7rem 0.85rem;
    cursor: pointer;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.55rem;
}

.calendar-grid .day-name,
.calendar-grid .calendar-cell {
    min-height: 80px;
}

.calendar-grid .day-name {
    text-align: center;
    font-weight: 700;
    color: var(--gray-500);
}

.calendar-cell {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 0.85rem;
    cursor: pointer;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.18s ease, border-color 0.18s ease;
}

.calendar-cell:hover {
    transform: translateY(-1px);
    border-color: var(--primary-500);
}

.calendar-cell.inactive {
    opacity: 0.45;
    cursor: default;
    background: #f3f4f6;
}

.calendar-cell.active { background: #ffffff; }

.calendar-cell.today {
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.18);
}

.calendar-cell .day-number {
    font-weight: 700;
}

.calendar-cell .event-pill {
    margin-top: 0.8rem;
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    border-radius: 999px;
    font-size: 0.8rem;
    padding: 0.35rem 0.75rem;
}

.calendar-cell.sunday .day-number { color: #dc2626; }
.calendar-cell.holiday { background: rgba(254, 226, 226, 0.6); }
.calendar-cell.holiday .day-number { color: #991b1b; }
.event-pill.holiday { background: rgba(254, 226, 226, 0.22); color: #991b1b; }

.calendar-sidebar h4 {
    margin-top: 0;
}

.event-list {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0;
    display: grid;
    gap: 0.85rem;
}

.event-item {
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 0.85rem 1rem;
    background: #f8fafc;
}

.event-item strong {
    display: block;
    margin-bottom: 0.35rem;
}

.event-item p {
    margin: 0;
    color: #374151;
    font-size: 0.93rem;
}

.event-actions {
    margin-top: 0.85rem;
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.event-actions button {
    border: none;
    border-radius: 12px;
    padding: 0.55rem 0.85rem;
    cursor: pointer;
    font-size: 0.9rem;
}

.event-actions .btn-secondary {
    background: #e5e7eb;
    color: #111827;
}

.event-actions .btn-danger {
    background: #fee2e2;
    color: #991b1b;
}

.modal-backdrop,
.modal-panel {
    position: fixed;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
}

.modal-backdrop {
    background: rgba(15, 23, 42, 0.45);
    z-index: 1000;
    display: none;
}

.modal-panel {
    display: none;
    z-index: 1001;
    align-items: center;
    justify-content: center;
    padding: 1.2rem;
}

.modal-content {
    width: min(560px, 100%);
    background: #ffffff;
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.12);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.modal-header h4 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: #374151;
    cursor: pointer;
}

.modal-field {
    margin-bottom: 1rem;
}

.modal-field label {
    display: block;
    margin-bottom: 0.4rem;
    color: #374151;
    font-weight: 600;
}

.modal-field input,
.modal-field textarea {
    width: 100%;
    padding: 0.85rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 14px;
    font-size: 0.95rem;
}

.modal-field textarea { min-height: 120px; resize: vertical; }

.modal-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.modal-actions button {
    border: none;
    border-radius: 14px;
    padding: 0.85rem 1.2rem;
    cursor: pointer;
}

.modal-actions .btn-primary {
    background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
    color: white;
}

.modal-actions .btn-secondary {
    background: #f3f4f6;
    color: #111827;
}

/* Event Visual Indicators (dots) */
.calendar-cell.has-event {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.calendar-cell.holiday {
    background: rgba(254, 226, 226, 0.6);
    border-color: #fecaca;
}
.calendar-cell .event-dots {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    margin-top: 6px;
    min-height: 8px;
}
.calendar-cell .event-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 0 1.5px rgba(255,255,255,0.8);
}
.calendar-cell .event-dot.holiday-dot {
    width: 8px;
    height: 8px;
    background: #ef4444 !important;
}

@media (max-width: 980px) {
    .calendar-board { grid-template-columns: 1fr; }
}

@media (max-width: 680px) {
    .calendar-grid { gap: 0.35rem; }
    .calendar-cell { min-height: 90px; padding: 0.75rem; }
}
</style>

<div class="page-header">
    <h2 class="page-title"><i class="fas fa-calendar-alt"></i> Kalender & Reminder</h2>
    <p class="page-subtitle">
        <?= e($_SESSION['nama']) ?> &mdash; Kelas <?= e($siswa['nama_kelas'] ?? 'Belum ada kelas') ?>
    </p>
</div>

<div class="calendar-page">
    <div class="calendar-board">
        <section class="calendar-panel">
            <div class="calendar-header">
                <div class="calendar-title">
                    <span class="text-muted">Kalender Bulanan</span>
                    <h3 id="calendarMonthLabel"></h3>
                </div>
                <div class="calendar-nav">
                    <button type="button" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="calendar-grid" id="calendarGrid"></div>
            <div style="margin-top:1rem; font-size:0.95rem; color:var(--gray-600);">
                Klik tanggal untuk menambah atau melihat pengingat.
            </div>
        </section>

        <aside class="calendar-sidebar">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                <div>
                    <h4>Reminder Hari Ini</h4>
                    <p style="margin:0.2rem 0 0; color:var(--gray-500); font-size:0.95rem;">Pilih tanggal untuk melihat detail.</p>
                </div>
                <button id="addTodayEvent" class="btn btn-primary" style="font-size:0.9rem;">+ Tambah</button>
            </div>
            <div id="selectedDateLabel" style="font-weight:700; margin-bottom:0.75rem; color:#111827;"></div>
            <ul class="event-list" id="eventList"></ul>
            <div id="noEventsMessage" style="color:var(--gray-500);">Pilih tanggal untuk melihat reminder.</div>
        </aside>
    </div>
</div>

<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal-panel" id="modalPanel">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Tambah Reminder</h4>
            <button type="button" class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
        </div>
        <form id="eventForm">
            <input type="hidden" name="event_id" id="eventId" value="">
            <div class="modal-field">
                <label for="eventDate">Tanggal</label>
                <input type="date" id="eventDate" name="event_date" required>
            </div>
            <div class="modal-field">
                <label for="eventTitle">Judul Reminder</label>
                <input type="text" id="eventTitle" name="title" placeholder="Contoh: Ulangan Matematika" required>
            </div>
            <div class="modal-field">
                <label for="eventDescription">Catatan</label>
                <textarea id="eventDescription" name="description" placeholder="Tambah detail atau instruksi..."></textarea>
            </div>
                <div class="modal-field" id="modalHolidayField" style="display:none;">
                    <label for="eventIsHoliday">Tandai sebagai Hari Libur</label>
                    <input type="checkbox" id="eventIsHoliday" value="1"> Hari Libur
                </div>
                <div class="modal-field" id="modalScopeField" style="display:none;">
                    <label for="eventScope">Scope Event</label>
                    <select id="eventScope">
                        <option value="user">Pribadi</option>
                        <option value="school">Untuk Sekolah</option>
                    </select>
                </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="cancelModal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Reminder</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const userRole = <?= (int)$_SESSION['role_id'] ?>;
    const isAdmin = (userRole === 1 || userRole === 4);
    const canEdit = (userRole !== 3); // Guru, Admin, Kepsek bisa input; Siswa hanya lihat
    const baseUrl = '<?= rtrim($base_url, '/') ?>';
    const today = new Date();
    let currentYear = <?= $currentYear ?>;
    let currentMonth = <?= $currentMonth - 1 ?>; // JS month index 0-11
    let selectedDate = formatDate(today);
    let eventsByDate = {};

    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    const calendarMonthLabel = document.getElementById('calendarMonthLabel');
    const calendarGrid = document.getElementById('calendarGrid');
    const selectedDateLabel = document.getElementById('selectedDateLabel');
    const eventList = document.getElementById('eventList');
    const noEventsMessage = document.getElementById('noEventsMessage');
    const addTodayEvent = document.getElementById('addTodayEvent');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modalPanel = document.getElementById('modalPanel');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const eventForm = document.getElementById('eventForm');
    const eventId = document.getElementById('eventId');
    const eventDateInput = document.getElementById('eventDate');
    const eventTitleInput = document.getElementById('eventTitle');
    const eventDescriptionInput = document.getElementById('eventDescription');

    document.getElementById('prevMonth').addEventListener('click', () => {
        currentMonth -= 1;
        if (currentMonth < 0) { currentMonth = 11; currentYear -= 1; }
        loadCalendar(currentYear, currentMonth);
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        currentMonth += 1;
        if (currentMonth > 11) { currentMonth = 0; currentYear += 1; }
        loadCalendar(currentYear, currentMonth);
    });

    if (canEdit) {
        addTodayEvent.addEventListener('click', () => {
            openModal(selectedDate);
        });
    } else {
        addTodayEvent.style.display = 'none';
    }

    closeModalBtn.addEventListener('click', closeModal);
    cancelModalBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);

    eventForm.addEventListener('submit', saveEvent);

    function loadCalendar(year, month) {
        const monthLabel = `${monthNames[month]} ${year}`;
        calendarMonthLabel.textContent = monthLabel;
        selectedDate = formatDate(new Date(year, month, 1));
        fetch(`${baseUrl}/ajax/calendar_events?action=get&year=${year}&month=${month + 1}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Gagal memuat kalender', 'error');
                    return;
                }
                eventsByDate = groupEventsByDate(data.events);
                renderCalendar(year, month);
                renderSelectedDate();
            })
            .catch(() => {
                showToast('Koneksi gagal. Coba muat ulang halaman.', 'error');
            });
    }

    function groupEventsByDate(events) {
        const grouped = {};
        events.forEach(evt => {
            if (!grouped[evt.event_date]) grouped[evt.event_date] = [];
            grouped[evt.event_date].push(evt);
        });
        return grouped;
    }

    function renderCalendar(year, month) {
        calendarGrid.innerHTML = '';
        const dayNames = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
        dayNames.forEach(name => {
            const cell = document.createElement('div');
            cell.className = 'day-name';
            cell.textContent = name;
            calendarGrid.appendChild(cell);
        });

        const firstDay = new Date(year, month, 1).getDay();
        const offset = (firstDay + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < offset; i++) {
            const dummy = document.createElement('div');
            dummy.className = 'calendar-cell inactive';
            calendarGrid.appendChild(dummy);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = new Date(year, month, day);
            const dateString = formatDate(cellDate);
            const cell = document.createElement('div');
            cell.className = 'calendar-cell active';
            if (dateString === formatDate(new Date())) {
                cell.classList.add('today');
            }
            cell.dataset.date = dateString;
            cell.innerHTML = `<div class="day-number">${day}</div>`;

            const events = eventsByDate[dateString] || [];
            // mark sunday
            if (cellDate.getDay() === 0) {
                cell.classList.add('sunday');
            }
            // If any holiday event on this date, mark holiday
            const hasHoliday = events.some(evt => evt.is_holiday == 1);
            if (hasHoliday) {
                cell.classList.add('holiday');
                const badge = document.createElement('div');
                badge.className = 'event-pill holiday';
                badge.textContent = `Hari Libur`;
                cell.appendChild(badge);
            } else if (events.length > 0) {
                const badge = document.createElement('div');
                badge.className = 'event-pill';
                badge.textContent = `${events.length} reminder`;
                cell.appendChild(badge);
            }

            cell.addEventListener('click', () => {
                selectedDate = dateString;
                renderSelectedDate();
                if (canEdit) openModal(dateString);
            });
            calendarGrid.appendChild(cell);
        }
    }

    function renderSelectedDate() {
        const formattedKey = new Date(selectedDate).toLocaleDateString('id-ID', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        });
        selectedDateLabel.textContent = formattedKey;
        const events = eventsByDate[selectedDate] || [];
        eventList.innerHTML = '';
        if (!events.length) {
            noEventsMessage.style.display = 'block';
            return;
        }
        noEventsMessage.style.display = 'none';
        events.forEach(event => {
            const item = document.createElement('li');
            item.className = 'event-item';
            let actionsHtml = '';
            if (canEdit) {
                actionsHtml = `
                    <div class="event-actions">
                        <button type="button" class="btn-secondary" data-action="edit" data-id="${event.id}">Edit</button>
                        <button type="button" class="btn-danger" data-action="delete" data-id="${event.id}">Hapus</button>
                    </div>`;
            }
            const holidayLabel = event.is_holiday ? '<small style="color:#991b1b; display:block; margin-bottom:6px;">Hari Libur</small>' : '';
            item.innerHTML = `
                ${holidayLabel}
                <strong>${escapeHtml(event.title)}</strong>
                <p>${escapeHtml(event.description || 'Tidak ada catatan tambahan.')}</p>
                ${actionsHtml}
            `;
            eventList.appendChild(item);
        });

        if (canEdit) {
            eventList.querySelectorAll('button[data-action="edit"]').forEach(button => {
                button.addEventListener('click', () => {
                    const eventId = button.dataset.id;
                    const event = (eventsByDate[selectedDate] || []).find(evt => evt.id === Number(eventId));
                    if (event) openModal(selectedDate, event);
                });
            });

            eventList.querySelectorAll('button[data-action="delete"]').forEach(button => {
                button.addEventListener('click', async () => {
                    const eventId = button.dataset.id;
                    if (!await confirmModal('Hapus reminder ini?')) return;
                    deleteEvent(eventId);
                });
            });
        }
    }

    function openModal(date, event = null) {
        modalBackdrop.style.display = 'block';
        modalPanel.style.display = 'flex';
        eventId.value = event?.id || '';
        eventDateInput.value = date;
        eventTitleInput.value = event?.title || '';
        eventDescriptionInput.value = event?.description || '';
        document.getElementById('modalTitle').textContent = event ? 'Edit Reminder' : 'Tambah Reminder';
        // show holiday and scope fields only for editors
        if (canEdit) {
            document.getElementById('modalHolidayField').style.display = 'block';
            document.getElementById('modalScopeField').style.display = 'block';
            document.getElementById('eventIsHoliday').checked = event?.is_holiday ? true : false;
            document.getElementById('eventScope').value = event?.scope || 'user';
        }
    }

    function closeModal() {
        modalBackdrop.style.display = 'none';
        modalPanel.style.display = 'none';
        eventForm.reset();
        eventId.value = '';
    }

    function saveEvent(e) {
        e.preventDefault();
        const formData = new FormData(eventForm);
        formData.append('action', 'save');
        // append holiday and scope when available
        if (canEdit) {
            const isHoliday = document.getElementById('eventIsHoliday').checked ? 1 : 0;
            const scopeVal = document.getElementById('eventScope').value || 'user';
            formData.append('is_holiday', isHoliday);
            formData.append('scope', scopeVal);
        }

        fetch(`${baseUrl}/ajax/calendar_events`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Gagal menyimpan reminder', 'error');
                return;
            }
            showToast(data.message || 'Reminder tersimpan');
            closeModal();
            loadCalendar(currentYear, currentMonth);
        })
        .catch(() => {
            showToast('Gagal menyimpan. Coba lagi.', 'error');
        });
    }

    function deleteEvent(id) {
        const payload = new FormData();
        payload.append('action', 'delete');
        payload.append('event_id', id);

        fetch(`${baseUrl}/ajax/calendar_events`, {
            method: 'POST',
            body: payload
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Gagal menghapus reminder', 'error');
                return;
            }
            showToast(data.message || 'Reminder dihapus');
            loadCalendar(currentYear, currentMonth);
        })
        .catch(() => {
            showToast('Gagal menghapus. Coba lagi.', 'error');
        });
    }

    function formatDate(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"]/g, tag => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[tag]));
    }

    loadCalendar(currentYear, currentMonth);
})();
</script>

<?php include '../includes/footer.php'; ?>
