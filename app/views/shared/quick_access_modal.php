<!-- Modal Edit Quick Access - rendered at body level via JS teleport -->
<div id="qaModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:28px 32px;width:100%;max-width:520px;margin:20px;max-height:90vh;display:flex;flex-direction:column;animation:modalSlideUp .22s ease">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-shrink:0">
            <div>
                <div style="font-size:17px;font-weight:700">Edit Quick Access</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:3px">Pilih maks 8 menu</div>
            </div>
            <button onclick="closeQAModal()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:20px;line-height:1;padding:4px">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Counter -->
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;flex-shrink:0">
            Dipilih: <span id="qaCount" style="color:var(--accent-cyan);font-weight:600">0</span> / 8
        </div>

        <!-- List (scrollable) -->
        <div id="qaMenuList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:0;padding-right:4px"></div>

        <!-- Footer -->
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;flex-shrink:0">
            <button onclick="closeQAModal()" class="btn btn-secondary" style="padding:10px 20px">Batal</button>
            <button onclick="saveQA()" class="btn btn-primary" style="padding:10px 24px">
                <i class="bi bi-floppy" style="margin-right:6px"></i>Simpan
            </button>
        </div>
    </div>
</div>

<style>
@keyframes modalSlideUp { from{transform:translateY(24px);opacity:0} to{transform:translateY(0);opacity:1} }
.qa-item { display:flex;align-items:center;gap:12px;padding:11px 14px;border:1px solid var(--border-color);border-radius:9px;cursor:pointer;transition:all .15s;user-select:none; }
.qa-item:hover { border-color:var(--accent-cyan);background:var(--hover-bg); }
.qa-item.selected { border-color:var(--accent-cyan);background:rgba(0,217,255,0.08); }
.qa-item.selected .qa-check { background:var(--accent-cyan);border-color:var(--accent-cyan); }
.qa-item.selected .qa-check::after { display:block; }
.qa-check { width:18px;height:18px;border-radius:4px;border:2px solid var(--border-color);flex-shrink:0;position:relative;transition:all .15s; }
.qa-check::after { content:'';display:none;position:absolute;left:3px;top:0;width:6px;height:10px;border:2px solid #0a1628;border-top:none;border-left:none;transform:rotate(45deg); }
.qa-name { flex:1;font-size:14px; }
.qa-disabled { opacity:0.4;pointer-events:none; }
#qaMenuList::-webkit-scrollbar { width:4px; }
#qaMenuList::-webkit-scrollbar-thumb { background:var(--accent-cyan);border-radius:2px; }
</style>

<script>
// Teleport modal ke body agar tidak terpengaruh parent CSS
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('qaModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
});
</script>
