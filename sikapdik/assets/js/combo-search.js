/**
 * Combo Search Box - SIKAPDIK
 * Transforms any <select> with class "combo-search" into a searchable dropdown
 * Usage: <select class="combo-search" ...>
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select.combo-search').forEach(function(select) {
        initComboSearch(select);
    });
});

function initComboSearch(originalSelect) {
    // Get all options
    const options = [];
    originalSelect.querySelectorAll('option').forEach(opt => {
        options.push({ value: opt.value, text: opt.textContent, selected: opt.selected });
    });

    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'combo-search-wrapper relative';
    originalSelect.parentNode.insertBefore(wrapper, originalSelect);
    
    // Hide original select
    originalSelect.style.display = 'none';
    wrapper.appendChild(originalSelect);

    // Create search input
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'combo-search-input w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm';
    input.placeholder = originalSelect.getAttribute('data-placeholder') || 'Ketik untuk mencari...';
    input.autocomplete = 'off';
    
    // Set initial value if there's a selected option
    const selectedOpt = options.find(o => o.selected && o.value);
    if (selectedOpt) {
        input.value = selectedOpt.text;
    }
    
    wrapper.appendChild(input);

    // Create dropdown list
    const dropdown = document.createElement('div');
    dropdown.className = 'combo-search-dropdown absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden';
    wrapper.appendChild(dropdown);

    // Build dropdown items
    function buildList(filter = '') {
        const filterLower = filter.toLowerCase();
        let html = '';
        let count = 0;
        
        options.forEach(opt => {
            if (!opt.value) return; // skip empty option
            if (filter && !opt.text.toLowerCase().includes(filterLower)) return;
            
            const isActive = opt.value === originalSelect.value;
            html += `<div class="combo-search-item px-4 py-2.5 cursor-pointer text-sm hover:bg-blue-50 transition ${isActive ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'}" data-value="${opt.value}">${highlightMatch(opt.text, filter)}</div>`;
            count++;
        });

        if (count === 0) {
            html = '<div class="px-4 py-3 text-sm text-gray-400 text-center">Tidak ditemukan</div>';
        }
        
        dropdown.innerHTML = html;

        // Add click handlers
        dropdown.querySelectorAll('.combo-search-item').forEach(item => {
            item.addEventListener('click', function() {
                selectItem(this.getAttribute('data-value'), this.textContent);
            });
        });
    }

    function highlightMatch(text, filter) {
        if (!filter) return escapeHtml(text);
        const regex = new RegExp('(' + escapeRegex(filter) + ')', 'gi');
        return escapeHtml(text).replace(regex, '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>');
    }

    function escapeHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function selectItem(value, text) {
        originalSelect.value = value;
        input.value = text.replace(/<[^>]*>/g, ''); // strip HTML
        dropdown.classList.add('hidden');
        
        // Trigger change event on original select
        const event = new Event('change', { bubbles: true });
        originalSelect.dispatchEvent(event);
    }

    function showDropdown() {
        buildList(input.value === (selectedOpt ? selectedOpt.text : '') ? '' : input.value);
        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    }

    // Event listeners
    input.addEventListener('focus', function() {
        this.select();
        showDropdown();
    });

    input.addEventListener('input', function() {
        buildList(this.value);
        dropdown.classList.remove('hidden');
        
        // If input is cleared, reset select
        if (!this.value) {
            originalSelect.value = '';
            const event = new Event('change', { bubbles: true });
            originalSelect.dispatchEvent(event);
        }
    });

    input.addEventListener('blur', hideDropdown);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
            this.blur();
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const first = dropdown.querySelector('.combo-search-item');
            if (first) first.classList.add('bg-blue-100');
        }
    });
}
