// Custom JavaScript for Gunayatan Gatepass System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize file upload functionality if script is loaded
    if (typeof initFileUploader === 'function' && $('#fileUploader').length) {
        initFileUploader();
    }
    
    // Initialize signature pad if script is loaded and element exists
    if (typeof initSignaturePad === 'function' && $('#signatureCanvas').length) {
        initSignaturePad();
    }

    // Check for saved theme preference and apply it - wrap in a timeout to ensure DOM is fully loaded
    setTimeout(function() {
        checkThemePreference();
    }, 100);
    
    // Add click event listeners for theme toggle buttons
    document.querySelectorAll('.theme-toggle-btn, .theme-toggle-menu').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            toggleDarkMode();
            return false;
        });
    });
    
    // Dynamic Item Addition for Gatepass Form
    const addItemBtn = document.getElementById('addItemBtn');
    if (addItemBtn) {
        addItemBtn.addEventListener('click', addNewItem);
    }

    // Initialize first item if creating new gatepass
    initializeFirstItemIfNeeded();
    
    // Add event listeners to existing remove buttons
    addRemoveItemListeners();
    
    // Set up autocomplete for existing item inputs
    setupExistingItemAutocomplete();
    
    // Add search functionality
    setupSearch();
    
    // Initialize date pickers
    initializeDatePickers();
    
    // Set up barcode scanner functionality
    setupBarcodeScanner();
    
    // Setup form validation
    setupFormValidation();
    
    // PDF preview functionality
    setupPDFPreview();
    
    // Setup filters for tables
    setupTableFilters();
});

// Add a new item row to the form
function addNewItem() {
    const itemsContainer = document.getElementById('itemsContainer');
    const itemCount = document.querySelectorAll('.item-entry').length;
    
    // Get units from the global variable if available
    let unitOptionsHtml = '';
    if (window.availableUnits && Array.isArray(window.availableUnits)) {
        // Use predefined units from the database
        window.availableUnits.forEach(function(unit) {
            unitOptionsHtml += `<option value="${unit.name}">${unit.name} (${unit.symbol})</option>`;
        });
    } else {
        // Fallback to default units - all movable/tangible item units (alphabetically ordered)
        unitOptionsHtml = `
            <option value="Bags">Bags (bag)</option>
            <option value="Barrels">Barrels (bbl)</option>
            <option value="Bottles">Bottles (btl)</option>
            <option value="Boxes">Boxes (box)</option>
            <option value="Buckets">Buckets (bucket)</option>
            <option value="Bundles">Bundles (bdl)</option>
            <option value="Cans">Cans (can)</option>
            <option value="Cartons">Cartons (ctn)</option>
            <option value="Cases">Cases (case)</option>
            <option value="Centimeters">Centimeters (cm)</option>
            <option value="Coils">Coils (coil)</option>
            <option value="Containers">Containers (container)</option>
            <option value="Crates">Crates (crate)</option>
            <option value="Cubic Feet">Cubic Feet (ft³)</option>
            <option value="Cubic Meters">Cubic Meters (m³)</option>
            <option value="Cylinders">Cylinders (cyl)</option>
            <option value="Dozens">Dozens (doz)</option>
            <option value="Drums">Drums (drum)</option>
            <option value="Each">Each (ea)</option>
            <option value="Feet">Feet (ft)</option>
            <option value="Gallons">Gallons (gal)</option>
            <option value="Grams">Grams (g)</option>
            <option value="Gross">Gross (gr)</option>
            <option value="Inches">Inches (in)</option>
            <option value="Jars">Jars (jar)</option>
            <option value="Jerricans">Jerricans (jerry)</option>
            <option value="Kilograms">Kilograms (kg)</option>
            <option value="Kits">Kits (kit)</option>
            <option value="Length">Length (length)</option>
            <option value="Liters">Liters (L)</option>
            <option value="Meters">Meters (m)</option>
            <option value="Milligrams">Milligrams (mg)</option>
            <option value="Milliliters">Milliliters (ml)</option>
            <option value="Millimeters">Millimeters (mm)</option>
            <option value="Numbers">Numbers (nos)</option>
            <option value="Ounces">Ounces (oz)</option>
            <option value="Packages">Packages (pkg)</option>
            <option value="Pairs">Pairs (pair)</option>
            <option value="Pallets">Pallets (pallet)</option>
            <option value="Pieces">Pieces (pcs)</option>
            <option value="Pints">Pints (pt)</option>
            <option value="Plates">Plates (plate)</option>
            <option value="Pounds">Pounds (lb)</option>
            <option value="Quarts">Quarts (qt)</option>
            <option value="Reams">Reams (ream)</option>
            <option value="Rolls">Rolls (roll)</option>
            <option value="Sacks">Sacks (sack)</option>
            <option value="Sets">Sets (set)</option>
            <option value="Sheets">Sheets (sht)</option>
            <option value="Tons">Tons (ton)</option>
            <option value="Tubes">Tubes (tube)</option>
            <option value="Units">Units (unit)</option>
            <option value="Yards">Yards (yd)</option>
        `;
    }
    
    const newItemHtml = `
        <div class="item-entry" id="item-${itemCount + 1}">
            <span class="remove-item" onclick="removeItem(this)"><i class="fas fa-times"></i></span>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="item_name_${itemCount + 1}" class="form-label">Item Name</label>
                    <div class="position-relative">
                        <input type="text" class="form-control item-name-input" id="item_name_${itemCount + 1}" name="item_name[]" required autocomplete="off">
                        <div class="autocomplete-suggestions" id="suggestions_${itemCount + 1}"></div>
                    </div>
                    <small class="form-text text-muted">Start typing to see suggestions from previous entries</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="item_quantity_${itemCount + 1}" class="form-label">Quantity</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="item_quantity_${itemCount + 1}" name="item_quantity[]" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="item_unit_${itemCount + 1}" class="form-label">Unit</label>
                    <select class="form-select" id="item_unit_${itemCount + 1}" name="item_unit[]" required>
                        <option value="">Select unit</option>
                        ${unitOptionsHtml}
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    // Add the new item
    itemsContainer.insertAdjacentHTML('beforeend', newItemHtml);
    
    // Set up autocomplete for the new item
    setupItemAutocomplete(`item_name_${itemCount + 1}`, `suggestions_${itemCount + 1}`);
}

// Remove an item from the form
function removeItem(element) {
    // Get the parent item entry
    const itemEntry = element.closest('.item-entry');
    
    // Check if this is the only item
    const itemCount = document.querySelectorAll('.item-entry').length;
    if (itemCount > 1) {
        // Remove the item entry
        itemEntry.remove();
    } else {
        // Alert the user that at least one item is required
        alert('At least one item is required.');
    }
}

// Initialize the first item if needed
function initializeFirstItemIfNeeded() {
    const itemsContainer = document.getElementById('itemsContainer');
    if (itemsContainer && itemsContainer.children.length === 0) {
        addNewItem();
    }
}

// Add event listeners to existing remove buttons
function addRemoveItemListeners() {
    const removeButtons = document.querySelectorAll('.remove-item');
    removeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            removeItem(this);
        });
    });
}

// Setup search functionality
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('table tbody tr');
            
            tableRows.forEach(function(row) {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

// Initialize date pickers
function initializeDatePickers() {
    const dateFields = document.querySelectorAll('.datepicker');
    if (dateFields.length > 0) {
        dateFields.forEach(function(field) {
            new Pikaday({
                field: field,
                format: 'YYYY-MM-DD',
                minDate: new Date()
            });
        });
    }
}

// Setup barcode scanner functionality
function setupBarcodeScanner() {
    const barcodeInput = document.getElementById('barcodeInput');
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcodeValue = this.value.trim();
                if (barcodeValue.length > 0) {
                    // Search for the gatepass
                    window.location.href = `search_result.php?barcode=${encodeURIComponent(barcodeValue)}`;
                }
            }
        });
    }
    
    // Also set up manual search
    const searchGatepassBtn = document.getElementById('searchGatepassBtn');
    if (searchGatepassBtn) {
        searchGatepassBtn.addEventListener('click', function() {
            const barcodeInput = document.getElementById('barcodeInput');
            const barcodeValue = barcodeInput.value.trim();
            if (barcodeValue.length > 0) {
                window.location.href = `search_result.php?barcode=${encodeURIComponent(barcodeValue)}`;
            } else {
                alert('Please enter a gatepass number or scan a barcode');
            }
        });
    }
}

// Setup form validation
function setupFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// Setup PDF preview functionality
function setupPDFPreview() {
    const previewButtons = document.querySelectorAll('.preview-pdf');
    previewButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const gatepassId = this.getAttribute('data-gatepass-id');
            const modal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
            
            // Set the iframe source
            document.getElementById('pdfFrame').src = `generate_pdf.php?id=${gatepassId}&preview=true`;
            
            // Show the modal
            modal.show();
        });
    });
}

// Setup table filters
function setupTableFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    if (filterSelects.length > 0) {
        filterSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
}

// Apply all selected filters to table
function applyFilters() {
    const filterValues = {};
    const filterSelects = document.querySelectorAll('.filter-select');
    
    // Get all filter values
    filterSelects.forEach(function(select) {
        const filterName = select.getAttribute('data-filter');
        const filterValue = select.value;
        if (filterValue !== '') {
            filterValues[filterName] = filterValue.toLowerCase();
        }
    });
    
    // Apply filters to table rows
    const tableRows = document.querySelectorAll('table tbody tr');
    tableRows.forEach(function(row) {
        let showRow = true;
        
        // Check each filter against row data
        for (const [filterName, filterValue] of Object.entries(filterValues)) {
            const cellData = row.querySelector(`[data-${filterName}]`);
            if (cellData) {
                const cellValue = cellData.getAttribute(`data-${filterName}`).toLowerCase();
                if (!cellValue.includes(filterValue)) {
                    showRow = false;
                    break;
                }
            }
        }
        
        // Show or hide row
        row.style.display = showRow ? '' : 'none';
    });
}

// Export table data to various formats
function exportTable(format) {
    const table = document.querySelector('.table-export');
    if (!table) return;
    
    const filename = 'gatepass_report_' + new Date().toISOString().slice(0, 10);
    
    if (format === 'csv') {
        exportToCSV(table, filename);
    } else if (format === 'excel') {
        exportToExcel(table, filename);
    } else if (format === 'pdf') {
        exportToPDF(table, filename);
    }
}

// Export to CSV
function exportToCSV(table, filename) {
    let csv = [];
    let rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Replace any commas in the cell text to avoid CSV issues
            let text = cols[j].innerText.replace(/,/g, ' ');
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    let csvFile = new Blob([csv], {type: 'text/csv'});
    let downloadLink = document.createElement('a');
    
    downloadLink.download = filename + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Export to Excel (basic implementation)
function exportToExcel(table, filename) {
    let html = table.outerHTML;
    html = html.replace(/ /g, '%20');
    
    // Creating a temporary link for the download
    let downloadLink = document.createElement('a');
    document.body.appendChild(downloadLink);
    
    downloadLink.href = 'data:application/vnd.ms-excel,' + html;
    downloadLink.download = filename + '.xls';
    downloadLink.click();
    
    document.body.removeChild(downloadLink);
}

// Export to PDF (requires a server-side implementation)
function exportToPDF(table, filename) {
    // Collect filter values
    const filterValues = {};
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(function(select) {
        const filterName = select.getAttribute('data-filter');
        const filterValue = select.value;
        if (filterValue !== '') {
            filterValues[filterName] = filterValue;
        }
    });
    
    // Create a form to submit the request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_pdf.php';
    form.target = '_blank';
    
    // Add filters as hidden fields
    for (const [key, value] of Object.entries(filterValues)) {
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'filter_' + key;
        hiddenField.value = value;
        form.appendChild(hiddenField);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Check for saved theme preference and apply it
function checkThemePreference() {
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        updateThemeText();
    } else {
        // Ensure we start with light mode if no preference or light mode is saved
        document.body.classList.remove('dark-mode');
        updateThemeText();
    }
    
    console.log("Theme preference checked: " + (savedTheme || 'default'));
}

// Toggle dark mode
function toggleDarkMode() {
    const body = document.body;
    body.classList.toggle('dark-mode');
    
    // Save preference to localStorage
    const isDarkMode = body.classList.contains('dark-mode');
    localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
    
    console.log('Dark mode toggled. Current state: ' + (isDarkMode ? 'dark' : 'light'));
    
    // Update the theme text
    updateThemeText();
    
    // Force repaint in some browsers
    document.body.style.display = 'none';
    document.body.offsetHeight; // Trigger a reflow
    document.body.style.display = '';
    
    return false; // Prevent default action if used in href
}

// Update theme text in the dropdown
function updateThemeText() {
    const themeText = document.getElementById('themeText');
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    // Update text in dropdown
    if (themeText) {
        themeText.textContent = isDarkMode ? 'Light Mode' : 'Dark Mode';
    }
    
    // Update all theme icons
    const themeIcons = [
        document.getElementById('theme-icon'),
        document.getElementById('menu-theme-icon')
    ];
    
    themeIcons.forEach(icon => {
        if (icon) {
            icon.classList.remove('fa-moon', 'fa-sun');
            icon.classList.add(isDarkMode ? 'fa-sun' : 'fa-moon');
        }
    });
    
    console.log('Theme icons updated, dark mode: ' + isDarkMode);
}

// Smart autocomplete functionality for item names
function setupItemAutocomplete(inputId, suggestionsId) {
    const input = document.getElementById(inputId);
    const suggestionsContainer = document.getElementById(suggestionsId);
    
    if (!input || !suggestionsContainer) return;
    
    let currentFocus = -1;
    let debounceTimer = null;
    
    // Add CSS styles dynamically if not already added
    if (!document.getElementById('autocomplete-styles')) {
        const style = document.createElement('style');
        style.id = 'autocomplete-styles';
        style.textContent = `
            .autocomplete-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-top: none;
                border-radius: 0 0 4px 4px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .autocomplete-suggestion {
                padding: 10px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
                transition: background-color 0.2s;
            }
            
            .autocomplete-suggestion:hover,
            .autocomplete-suggestion.highlighted {
                background-color: #f8f9fa;
            }
            
            .autocomplete-suggestion:last-child {
                border-bottom: none;
            }
            
            .autocomplete-suggestion-text {
                font-weight: 500;
                color: #333;
            }
            
            .autocomplete-suggestion-count {
                font-size: 0.85em;
                color: #666;
                margin-left: 8px;
            }
            
            .dark-mode .autocomplete-suggestions {
                background: #2d3748;
                border-color: #4a5568;
            }
            
            .dark-mode .autocomplete-suggestion {
                border-bottom-color: #4a5568;
            }
            
            .dark-mode .autocomplete-suggestion:hover,
            .dark-mode .autocomplete-suggestion.highlighted {
                background-color: #4a5568;
            }
            
            .dark-mode .autocomplete-suggestion-text {
                color: #e2e8f0;
            }
            
            .dark-mode .autocomplete-suggestion-count {
                color: #a0aec0;
            }
        `;
        document.head.appendChild(style);
    }
    
    input.addEventListener('input', function() {
        const term = this.value.trim();
        
        // Clear previous timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        
        // Hide suggestions if input is too short
        if (term.length < 1) {
            hideSuggestions();
            return;
        }
        
        // Debounce the API call
        debounceTimer = setTimeout(() => {
            fetchSuggestions(term, suggestionsContainer);
        }, 300);
    });
    
    input.addEventListener('keydown', function(e) {
        const suggestions = suggestionsContainer.querySelectorAll('.autocomplete-suggestion');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentFocus++;
            if (currentFocus >= suggestions.length) currentFocus = 0;
            highlightSuggestion(suggestions, currentFocus);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentFocus--;
            if (currentFocus < 0) currentFocus = suggestions.length - 1;
            highlightSuggestion(suggestions, currentFocus);
        } else if (e.key === 'Enter') {
            if (currentFocus > -1 && suggestions[currentFocus]) {
                e.preventDefault();
                selectSuggestion(suggestions[currentFocus], input, suggestionsContainer);
            }
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });
    
    input.addEventListener('blur', function() {
        // Delay hiding to allow click on suggestions
        setTimeout(() => {
            hideSuggestions();
        }, 200);
    });
    
    function fetchSuggestions(term, container) {
        fetch(`../api/get_item_suggestions.php?term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    displaySuggestions(data, container);
                } else {
                    hideSuggestions();
                }
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
                hideSuggestions();
            });
    }
    
    function displaySuggestions(suggestions, container) {
        container.innerHTML = '';
        currentFocus = -1;
        
        suggestions.forEach((suggestion, index) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-suggestion';
            div.innerHTML = `
                <span class="autocomplete-suggestion-text">${escapeHtml(suggestion.value)}</span>
                <span class="autocomplete-suggestion-count">(used ${suggestion.usage_count} times)</span>
            `;
            
            div.addEventListener('click', function() {
                selectSuggestion(this, input, container);
            });
            
            container.appendChild(div);
        });
        
        container.style.display = 'block';
    }
    
    function highlightSuggestion(suggestions, index) {
        // Remove existing highlight
        suggestions.forEach(s => s.classList.remove('highlighted'));
        
        // Add highlight to current
        if (suggestions[index]) {
            suggestions[index].classList.add('highlighted');
        }
    }
    
    function selectSuggestion(suggestionElement, inputElement, container) {
        const text = suggestionElement.querySelector('.autocomplete-suggestion-text').textContent;
        inputElement.value = text;
        hideSuggestions();
        inputElement.focus();
    }
    
    function hideSuggestions() {
        suggestionsContainer.style.display = 'none';
        suggestionsContainer.innerHTML = '';
        currentFocus = -1;
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Set up autocomplete for existing item inputs
function setupExistingItemAutocomplete() {
    document.querySelectorAll('.item-name-input').forEach((input, index) => {
        const suggestionsId = `suggestions_${index + 1}`;
        let suggestionsContainer = document.getElementById(suggestionsId);
        
        if (!suggestionsContainer) {
            // Create suggestions container if it doesn't exist
            suggestionsContainer = document.createElement('div');
            suggestionsContainer.id = suggestionsId;
            suggestionsContainer.className = 'autocomplete-suggestions';
            
            // Insert after the input
            const wrapper = input.parentNode;
            if (wrapper && wrapper.classList.contains('position-relative')) {
                wrapper.appendChild(suggestionsContainer);
            } else {
                // Create wrapper if it doesn't exist
                const newWrapper = document.createElement('div');
                newWrapper.className = 'position-relative';
                input.parentNode.insertBefore(newWrapper, input);
                newWrapper.appendChild(input);
                newWrapper.appendChild(suggestionsContainer);
            }
        }
        
        setupItemAutocomplete(input.id, suggestionsId);
    });
}
