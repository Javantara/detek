// ============================================================
// PLN Dashboard - main.js
// ============================================================

// BASE_URL ditetapkan di tiap halaman PHP, bukan di sini
// agar tidak hardcode path

// ─── Sidebar Submenu (persisten via localStorage) ─────────────
function toggleSubmenu(element) {
    element.classList.toggle('open');
    const sub = element.nextElementSibling;
    if (sub && sub.classList.contains('submenu')) {
        sub.classList.toggle('show');
    }
    // Simpan state semua submenu
    _saveSidebarState();
}

function _saveSidebarState() {
    const items = document.querySelectorAll('.menu-item.has-submenu');
    const state = {};
    items.forEach((el, i) => {
        const label = el.querySelector('span')?.textContent?.trim() || ('menu_' + i);
        state[label] = el.classList.contains('open');
    });
    try { localStorage.setItem('sidebar_state', JSON.stringify(state)); } catch(e) {}
}

function _restoreSidebarState() {
    let state = {};
    try { state = JSON.parse(localStorage.getItem('sidebar_state') || '{}'); } catch(e) {}
    const items = document.querySelectorAll('.menu-item.has-submenu');
    items.forEach((el) => {
        const label = el.querySelector('span')?.textContent?.trim();
        // Jika state tersimpan, gunakan itu; jika tidak, biarkan PHP yang tentukan (class open dari server)
        if (label && state.hasOwnProperty(label)) {
            const sub = el.nextElementSibling;
            if (state[label]) {
                el.classList.add('open');
                if (sub && sub.classList.contains('submenu')) sub.classList.add('show');
            } else {
                // Jangan tutup submenu yang diopen oleh PHP (halaman aktif)
                const hasActive = sub && sub.querySelector('.menu-item.active');
                if (!hasActive) {
                    el.classList.remove('open');
                    if (sub && sub.classList.contains('submenu')) sub.classList.remove('show');
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', _restoreSidebarState);

// ─── Modal ───────────────────────────────────────────────────
function showModal(id) { document.getElementById(id)?.classList.add('show'); }
function hideModal(id) { document.getElementById(id)?.classList.remove('show'); }

// ─── Delete Confirmation ─────────────────────────────────────
function confirmDelete(route, id, message) {
    if (!confirm(message || 'Yakin ingin menghapus data ini?')) return;
    const form  = document.createElement('form');
    form.method = 'GET';
    form.action = window.location.pathname;
    [['page', route], ['id', id]].forEach(([n, v]) => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = n; i.value = v;
        form.appendChild(i);
    });
    document.body.appendChild(form);
    form.submit();
}

// ─── Toast ───────────────────────────────────────────────────
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.textContent = message;
    Object.assign(toast.style, {
        position: 'fixed', top: '20px', right: '20px',
        zIndex: '10000', minWidth: '300px', boxShadow: '0 4px 12px rgba(0,0,0,.3)'
    });
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ─── Table Search ─────────────────────────────────────────────
function searchTable(input, tableId) {
    const filter = input.value.toUpperCase();
    const table  = document.getElementById(tableId);
    if (!table) return;
    Array.from(table.querySelectorAll('tbody tr')).forEach(row => {
        const found = Array.from(row.querySelectorAll('td')).some(td =>
            (td.textContent || td.innerText).toUpperCase().includes(filter)
        );
        row.style.display = found ? '' : 'none';
    });
}

// ─── Filter By Role ───────────────────────────────────────────
function filterByRole(role) {
    const table = document.getElementById('userTable');
    if (!table) return;
    Array.from(table.querySelectorAll('tbody tr')).forEach(row => {
        const cell = row.cells[4]?.textContent?.trim().toLowerCase() || '';
        row.style.display = (!role || cell === role) ? '' : 'none';
    });
}

// ─── Toggle Status (Plant / Unit) ────────────────────────────
function toggleStatus(type, id, checkbox) {
    const status = checkbox.checked ? 1 : 0;
    fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '/pln_web/public/') + '?api=toggle-status', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ type, id, status })
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (!data.success) checkbox.checked = !checkbox.checked;
    })
    .catch(() => {
        showToast('Terjadi kesalahan koneksi', 'error');
        checkbox.checked = !checkbox.checked;
    });
}

// ─── Simpan Halaman Terakhir + Bersihkan URL ─────────────────
(function () {
    const STORAGE_KEY = 'pln_last_page';
    const COOKIE_KEY  = 'pln_last_page';

    function getPageFromUrl(url) {
        try {
            const u = new URL(url, window.location.origin);
            return (u.searchParams.get('page') || '').trim();
        } catch (e) {
            return '';
        }
    }

    function isSavablePage(page) {
        return /^[a-zA-Z0-9_.-]+$/.test(page) && !['login', 'logout'].includes(page);
    }

    function saveLastPage(page) {
        if (!isSavablePage(page)) return;
        try { localStorage.setItem(STORAGE_KEY, page); } catch(e) {}
        document.cookie = COOKIE_KEY + '=' + encodeURIComponent(page) + '; path=/; max-age=' + (60 * 60 * 24 * 30) + '; SameSite=Lax';
    }

    // Simpan saat user klik menu/link yang punya ?page=...
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="page="]');
        if (!link) return;
        const page = getPageFromUrl(link.href);
        saveLastPage(page);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        // Kalau halaman dibuka dengan ?page=..., simpan dulu halaman aktifnya.
        const currentPage = getPageFromUrl(window.location.href);
        saveLastPage(currentPage);

        // Setelah PHP berhasil buka halaman, bersihkan URL agar tetap /pln_web/public/
        if (window.history && window.history.replaceState && window.location.search.includes('page=')) {
            window.history.replaceState(null, '', window.location.pathname);
        }
    });
})();
