// Quick Access Manager
// Requires: ALL_MENUS, QA_SLUGS, BASE_URL

let selected = [...QA_SLUGS];

function openQAModal() {
    const modal = document.getElementById('qaModal');
    modal.style.display = 'flex';
    document.getElementById('qaSearch').value = '';
    renderQAList();
    updateCount();
}

function closeQAModal() {
    document.getElementById('qaModal').style.display = 'none';
}

// Close on backdrop click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('qaModal');
    if (e.target === modal) closeQAModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeQAModal();
});

function renderQAList() {
    const list = document.getElementById('qaMenuList');
    list.innerHTML = '';
    const filtered = ALL_MENUS;

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-secondary);font-size:13px">Tidak ada menu ditemukan</div>';
        return;
    }

    filtered.forEach(menu => {
        const isSelected = selected.includes(menu.menu_link);
        const isMaxed    = !isSelected && selected.length >= 8;
        const div = document.createElement('div');
        div.className = 'qa-item' + (isSelected ? ' selected' : '') + (isMaxed ? ' qa-disabled' : '');
        div.innerHTML = `
            <div class="qa-check"></div>
            <span class="qa-name">${menu.menu_name}</span>
        `;
        if (!isMaxed) div.addEventListener('click', () => toggleQA(menu.menu_link));
        list.appendChild(div);
    });
}

function toggleQA(slug) {
    if (selected.includes(slug)) {
        selected = selected.filter(s => s !== slug);
    } else {
        if (selected.length >= 8) return;
        selected.push(slug);
    }
    updateCount();
    renderQAList();
}

function updateCount() {
    document.getElementById('qaCount').textContent = selected.length;
}

function saveQA() {
    const body = new URLSearchParams();
    selected.forEach(s => body.append('slugs[]', s));

    fetch(BASE_URL + '?api=save-quick-access', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
    })
    .then(r => r.json())
    .then(() => { closeQAModal(); rebuildQAGrid(); })
    .catch(() => alert('Gagal menyimpan, coba lagi'));
}

function rebuildQAGrid() {
    const grid = document.getElementById('qaGrid');
    const menuMap = {};
    ALL_MENUS.forEach(m => menuMap[m.menu_link] = m);

    if (selected.length === 0) {
        grid.innerHTML = '<p style="color:var(--text-secondary);font-size:13px">Belum ada quick access. Klik Edit untuk menambah.</p>';
        return;
    }

    grid.innerHTML = selected.map(slug => {
        const m = menuMap[slug];
        if (!m) return '';
        return `<a href="?page=${m.menu_link}"
                   class="btn btn-secondary"
                   style="text-align:center;padding:12px;display:flex;align-items:center;justify-content:center;gap:8px">
                    <i class="bi bi-grid-1x2"></i>
                    ${m.menu_name}
                </a>`;
    }).join('');
}
