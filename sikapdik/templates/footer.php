        </main>
    </div>
</div>

<!-- Overlay for mobile sidebar -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}

// Close profile dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('profileDropdown');
    const menu = document.getElementById('profileMenu');
    if (dropdown && !dropdown.contains(e.target)) {
        menu.classList.add('hidden');
    }
});

// Auto-hide flash messages
setTimeout(() => {
    const flash = document.getElementById('flashMessage');
    if (flash) flash.style.display = 'none';
}, 5000);

// Confirm delete
function confirmDelete(message = 'Yakin ingin menghapus data ini?') {
    return confirm(message);
}
</script>
</body>
</html>
