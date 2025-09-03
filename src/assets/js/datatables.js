/**
 * DataTables JavaScript - External File
 * 
 * Complete JavaScript functionality for DataTables including
 * AJAX operations, table rendering, pagination, search, 
 * bulk actions, and theme management.
 * 
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

class DataTablesJS {
    constructor(config = {})
    {
        // Configuration
        this.tableName = config.tableName || '';
        this.primaryKey = config.primaryKey || 'id';
        this.inlineEditableColumns = config.inlineEditableColumns || [];
        this.perPage = config.perPage || 25;
        this.bulkActionsEnabled = config.bulkActionsEnabled || false;
        this.bulkActions = config.bulkActions || {};
        this.actionConfig = config.actionConfig || {};
        this.columns = config.columns || {};
        this.cssClasses = config.cssClasses || {};
        
        // State
        this.currentPage = 1;
        this.sortColumn = '';
        this.sortDirection = 'ASC';
        this.search = '';
        this.searchColumn = 'all';
        this.deleteId = null;
        this.selectedIds = new Set();
        
        // Initialize
        this.init();
    }

    init()
    {
        this.bindEvents();
        this.loadData();
        this.initTheme();
    }

    // === EVENT BINDING ===
    bindEvents()
    {
        // Search input
        const searchInput = document.getElementById('datatables-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener(
                'input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(
                    () => {
                            this.search = e.target.value;
                            this.currentPage = 1;
                            this.loadData();
                    }, 300
                );
                }
            );
        }

        // Search column selector
        const searchColumn = document.getElementById('datatables-search-column');
        if (searchColumn) {
            searchColumn.addEventListener(
                'change', (e) => {
                    this.searchColumn = e.target.value;
                    this.currentPage = 1;
                    this.loadData();
                }
            );
        }

        // Page size selector
        const pageSizeSelect = document.getElementById('datatables-page-size');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener(
                'change', (e) => {
                    this.perPage = parseInt(e.target.value);
                    this.currentPage = 1;
                    this.loadData();
                }
            );
        }

        // Bulk actions
        if (this.bulkActionsEnabled) {
            const bulkSelect = document.getElementById('datatables-bulk-action');
            if (bulkSelect) {
                bulkSelect.addEventListener(
                    'change', (e) => {
                        const executeBtn = document.getElementById('datatables-bulk-execute');
                        if (executeBtn) {
                            executeBtn.disabled = !e.target.value || this.selectedIds.size === 0;
                        }
                    }
                );
            }
        }

        // Sortable headers
        document.addEventListener(
            'click', (e) => {
            if (e.target.closest('.sortable-header')) {
                const header = e.target.closest('th[data-sort]');
                if (header) {
                    const column = header.getAttribute('data-sort');
                    if (this.sortColumn === column) {
                        this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        this.sortColumn = column;
                        this.sortDirection = 'ASC';
                    }
                    this.currentPage = 1;
                    this.loadData();
                    this.updateSortIcons();
                }
            }
            }
        );
    }

    // === DATA LOADING ===
    loadData()
    {
        const params = new URLSearchParams(
            {
                action: 'fetch_data',
                table: this.tableName,
                page: this.currentPage,
                per_page: this.perPage,
                search: this.search,
                search_column: this.searchColumn,
                sort_column: this.sortColumn,
                sort_direction: this.sortDirection
            }
        );

        fetch('?' + params.toString())
            .then(response => response.json())
            .then(
                data => {
                if (data.success) {
                    this.renderTable(data.data);
                    this.renderPagination(data);
                    this.renderInfo(data);
                } else {
                        console.error('Failed to load data:', data.message);
                        UIkit.notification(data.message || 'Failed to load data', {status: 'danger'});
                }
                }
            )
            .catch(
                error => {
                console.error('Error loading data:', error);
                UIkit.notification('Error loading data', {status: 'danger'});
                }
            );
    }

    // === TABLE RENDERING ===
    renderTable(data)
    {
        const tbody = document.getElementById('datatables-tbody');
        if (!tbody) { return;
        }

        const columnCount = this.getColumnCount();

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columnCount}" class="uk-text-center uk-text-muted">No records found</td></tr>`;
            return;
        }

        let html = '';
        data.forEach(
            row => {
            const rowId = row[this.primaryKey];
            const rowClass = this.getRowClass(rowId);
            html += `<tr${rowClass ? ` class="${rowClass}"` : ''} data-id="${rowId}">`;
            // Bulk selection checkbox
                if (this.bulkActionsEnabled) {
                    html += '<td class="uk-table-shrink">';
                    html += `<label><input type="checkbox" class="uk-checkbox row-checkbox" value="${rowId}" onchange="DataTables.toggleRowSelection(this)"></label>`;
                    html += '</td>';
                }

                // Action column at start
                if (this.actionConfig.position === 'start') {
                    html += '<td class="uk-table-shrink">';
                    html += this.renderActionButtons(rowId);
                    html += '</td>';
                }

                // Regular columns - simplified structure where key=column, value=label
                Object.keys(this.columns).forEach(
                    column => {
                    const columnClass = this.cssClasses?.columns?.[column] || '';
                    const isEditable = this.inlineEditableColumns.includes(column);
                    let cellContent = row[column] || '';
                    if (isEditable) {
                        cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}">${cellContent}</span>`;
                    }
                    html += `<td${columnClass ? ` class="${columnClass}"` : ''}>${cellContent}</td>`;
                    }
                );
            // Action column at end
            if (this.actionConfig.position === 'end') {
                html += '<td class="uk-table-shrink">';
                html += this.renderActionButtons(rowId);
                html += '</td>';
            }

            html += '</tr>';
            }
        );

        tbody.innerHTML = html;
        this.bindTableEvents();
        this.updateBulkActionButtons();
    }

    renderActionButtons(rowId)
    {
        let html = '';
        
        if (this.actionConfig.show_edit) {
            html += '<a href="#" class="uk-icon-link btn-edit uk-margin-small-right" uk-icon="pencil" title="Edit"></a>';
        }
        
        if (this.actionConfig.show_delete) {
            html += '<a href="#" class="uk-icon-link btn-delete uk-margin-small-right" uk-icon="trash" title="Delete"></a>';
        }

        // Custom actions
        if (this.actionConfig.custom_actions) {
            this.actionConfig.custom_actions.forEach(
                action => {
                const icon = action.icon || 'link';
                const title = action.title || '';
                const className = action.class || 'btn-custom';
                html += `<a href="#" class="uk-icon-link ${className} uk-margin-small-right" uk-icon="${icon}" title="${title}"></a>`;
                }
            );
        }

        return html;
    }

    bindTableEvents()
    {
        // Edit buttons
        document.querySelectorAll('.btn-edit').forEach(
            btn => {
            btn.addEventListener(
                    'click', (e) => {
                    e.preventDefault();
                    const id = e.target.closest('tr').getAttribute('data-id');
                    this.showEditModal(id);
                    }
                );
            }
        );

        // Delete buttons
        document.querySelectorAll('.btn-delete').forEach(
            btn => {
            btn.addEventListener(
                    'click', (e) => {
                    e.preventDefault();
                    const id = e.target.closest('tr').getAttribute('data-id');
                    this.showDeleteModal(id);
                    }
                );
            }
        );

        // Inline edit
        document.querySelectorAll('.inline-editable').forEach(
            span => {
            span.addEventListener(
                    'dblclick', (e) => {
                    this.startInlineEdit(e.target);
                    }
                );
            }
        );
    }

    // === PAGINATION ===
    renderPagination(data)
    {
        const pagination = document.getElementById('datatables-pagination');
        if (!pagination) { return;
        }

        if (data.total_pages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let html = '';
        const currentPage = parseInt(data.page);
        const totalPages = parseInt(data.total_pages);

        // Previous button
        html += `<li${currentPage === 1 ? ' class="uk-disabled"' : ''}>`;
        html += `<a href="#"${currentPage === 1 ? '' : ` onclick="DataTables.goToPage(${currentPage - 1})"`}>`;
        html += '<span uk-pagination-previous></span></a></li>';

        // First page
        if (currentPage > 3) {
            html += '<li><a href="#" onclick="DataTables.goToPage(1)">1</a></li>';
            if (currentPage > 4) {
                html += '<li class="uk-disabled"><span>...</span></li>';
            }
        }

        // Page numbers
        const start = Math.max(1, currentPage - 2);
        const end = Math.min(totalPages, currentPage + 2);
        for (let i = start; i <= end; i++) {
            html += `<li${i === currentPage ? ' class="uk-active"' : ''}>`;
            html += `<a href="#"${i === currentPage ? '' : ` onclick="DataTables.goToPage(${i})"`}>${i}</a></li>`;
        }

        // Last page
        if (currentPage < totalPages - 2) {
            if (currentPage < totalPages - 3) {
                html += '<li class="uk-disabled"><span>...</span></li>';
            }
            html += `<li><a href="#" onclick="DataTables.goToPage(${totalPages})">${totalPages}</a></li>`;
        }

        // Next button
        html += `<li${currentPage === totalPages ? ' class="uk-disabled"' : ''}>`;
        html += `<a href="#"${currentPage === totalPages ? '' : ` onclick="DataTables.goToPage(${currentPage + 1})"`}>`;
        html += '<span uk-pagination-next></span></a></li>';

        pagination.innerHTML = html;
    }

    goToPage(page)
    {
        this.currentPage = page;
        this.loadData();
    }

    renderInfo(data)
    {
        const info = document.getElementById('datatables-info');
        if (!info) { return;
        }

        const start = (data.page - 1) * data.per_page + 1;
        const end = Math.min(start + data.per_page - 1, data.total);
        info.textContent = `Showing ${start} to ${end} of ${data.total} records`;
    }

    updateSortIcons()
    {
        document.querySelectorAll('.sort-icon').forEach(
            icon => {
            icon.setAttribute('uk-icon', 'triangle-up');
            }
        );

        const currentSortHeader = document.querySelector(`th[data-sort="${this.sortColumn}"] .sort-icon`);
        if (currentSortHeader) {
            const iconName = this.sortDirection === 'ASC' ? 'triangle-up' : 'triangle-down';
            currentSortHeader.setAttribute('uk-icon', iconName);
        }
    }

    // === BULK ACTIONS ===
    toggleSelectAll(checkbox)
    {
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        rowCheckboxes.forEach(
            cb => {
            cb.checked = checkbox.checked;
            this.toggleRowSelection(cb);
            }
        );
    }

    toggleRowSelection(checkbox)
    {
        const rowId = checkbox.value;
        if (checkbox.checked) {
            this.selectedIds.add(rowId);
        } else {
            this.selectedIds.delete(rowId);
            // Uncheck "select all" if not all rows are selected
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
        }
        this.updateBulkActionButtons();
    }

    updateBulkActionButtons()
    {
        const bulkSelect = document.getElementById('datatables-bulk-action');
        const executeBtn = document.getElementById('datatables-bulk-execute');
        
        if (bulkSelect && executeBtn) {
            const hasSelection = this.selectedIds.size > 0;
            bulkSelect.disabled = !hasSelection;
            executeBtn.disabled = !hasSelection || !bulkSelect.value;
        }
    }

    executeBulkAction()
    {
        const bulkSelect = document.getElementById('datatables-bulk-action');
        if (!bulkSelect || !bulkSelect.value) { return;
        }

        const action = bulkSelect.value;
        const selectedIds = Array.from(this.selectedIds);

        if (selectedIds.length === 0) {
            UIkit.notification('No records selected', {status: 'warning'});
            return;
        }

        // Check if action requires confirmation
        const actionConfig = this.bulkActions[action];
        if (actionConfig && actionConfig.confirm) {
            UIkit.modal.confirm(actionConfig.confirm).then(
                () => {
                this.performBulkAction(action, selectedIds);
                }, () => {
                // User cancelled
                }
            );
        } else {
            this.performBulkAction(action, selectedIds);
        }
    }

    performBulkAction(action, selectedIds)
    {
        const formData = new FormData();
        formData.append('action', 'bulk_action');
        formData.append('bulk_action', action);
        formData.append('selected_ids', JSON.stringify(selectedIds));

        fetch(
            window.location.href, {
                method: 'POST',
                body: formData
            }
        )
        .then(response => response.json())
        .then(
            data => {
            if (data.success) {
                this.selectedIds.clear();
                this.loadData();
                UIkit.notification(data.message || 'Bulk action completed', {status: 'success'});
                
                // Reset bulk action controls
                const bulkSelect = document.getElementById('datatables-bulk-action');
                if (bulkSelect) { bulkSelect.value = '';
                }
                
                const selectAll = document.getElementById('select-all');
                if (selectAll) { selectAll.checked = false;
                }
                
                this.updateBulkActionButtons();
            } else {
                    UIkit.notification(data.message || 'Bulk action failed', {status: 'danger'});
            }
            }
        )
        .catch(
            error => {
            console.error('Error:', error);
            UIkit.notification('An error occurred', {status: 'danger'});
            }
        );
    }

    // === FORM MODALS ===
    showAddModal()
    {
        UIkit.modal('#add-modal').show();
    }

    showEditModal(id)
    {
        this.loadRecordForEdit(id);
        UIkit.modal('#edit-modal').show();
    }

    showDeleteModal(id)
    {
        this.deleteId = id;
        UIkit.modal('#delete-modal').show();
    }

    loadRecordForEdit(id)
    {
        // Find row data and populate form
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) { return; }

        // Set the primary key field
        const pkField = document.getElementById(`edit-${this.primaryKey}`);
        if (pkField) { 
            pkField.value = id; 
            //console.log(`Set primary key field ${this.primaryKey} to ${id}`);
        }

        // Get all table cells from the row
        const cells = row.querySelectorAll('td');
        let cellIndex = 0;
        
        // Skip bulk actions checkbox cell if present
        if (this.bulkActionsEnabled) cellIndex++;
        
        // Skip action column if at start
        if (this.actionConfig.position === 'start') cellIndex++;
        
        // Map cells to columns in order
        Object.keys(this.columns).forEach(column => {
            if (cells[cellIndex]) {
                // Get text content, handling inline editable spans
                const cellElement = cells[cellIndex];
                const editableSpan = cellElement.querySelector('.inline-editable');
                const value = editableSpan ? editableSpan.textContent.trim() : cellElement.textContent.trim();
                
                const formField = document.getElementById(`edit-${column}`);
                if (formField) {
                    if (formField.type === 'checkbox') {
                        formField.checked = value === '1' || value.toLowerCase() === 'true';
                    } else {
                        formField.value = value;
                    }
                    //console.log(`Set field ${column} to ${value}`);
                }
            }
            cellIndex++;
        });
    }

    submitAddForm(event)
    {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'add_record');

        this.submitForm(formData, form, 'add-modal', 'Record added successfully');
        return false;
    }

    submitEditForm(event)
    {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'edit_record');

        this.submitForm(formData, null, 'edit-modal', 'Record updated successfully');
        return false;
    }

    submitForm(formData, form, modalId, successMessage)
    {
        formData.append('table', this.tableName);
        
        fetch(
            window.location.href, {
                method: 'POST',
                body: formData
            }
        )
        .then(response => response.json())
        .then(
            data => {
            if (data.success) {
                UIkit.modal(`#${modalId}`).hide();
                if (form) { form.reset();
                }
                this.loadData();
                UIkit.notification(successMessage, {status: 'success'});
            } else {
                    UIkit.notification(data.message || 'Operation failed', {status: 'danger'});
            }
            }
        )
        .catch(
            error => {
            console.error('Error:', error);
            UIkit.notification('An error occurred', {status: 'danger'});
            }
        );
    }

    confirmDelete()
    {
        if (!this.deleteId) { return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_record');
        formData.append('id', this.deleteId);

        fetch(
            window.location.href, {
                method: 'POST',
                body: formData
            }
        )
        .then(response => response.json())
        .then(
            data => {
            if (data.success) {
                UIkit.modal('#delete-modal').hide();
                this.loadData();
                UIkit.notification('Record deleted successfully', {status: 'success'});
            } else {
                    UIkit.notification(data.message || 'Failed to delete record', {status: 'danger'});
            }
            }
        )
        .catch(
            error => {
            console.error('Error:', error);
            UIkit.notification('An error occurred', {status: 'danger'});
            }
        );

        this.deleteId = null;
    }

    // === INLINE EDITING ===
    startInlineEdit(element)
    {
        const field = element.getAttribute('data-field');
        const id = element.getAttribute('data-id');
        const currentValue = element.textContent;

        if (!this.inlineEditableColumns.includes(field)) { return;
        }

        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentValue;
        input.className = 'uk-input uk-form-small';
        input.style.width = '100px';

        const saveEdit = () => {
            const newValue = input.value;
            if (newValue !== currentValue) {
                this.saveInlineEdit(id, field, newValue, element);
            } else {
                element.textContent = currentValue;
            }
        };

        const cancelEdit = () => {
            element.textContent = currentValue;
        };

        input.addEventListener('blur', saveEdit);
        input.addEventListener(
            'keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveEdit();
            } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
            }
            }
        );

        element.textContent = '';
        element.appendChild(input);
        input.focus();
        input.select();
    }

    saveInlineEdit(id, field, value, element)
    {
        const formData = new FormData();
        formData.append('action', 'inline_edit');
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value);

        fetch(
            window.location.href, {
                method: 'POST',
                body: formData
            }
        )
        .then(response => response.json())
        .then(
            data => {
            if (data.success) {
                element.textContent = value;
                UIkit.notification('Field updated successfully', {status: 'success'});
            } else {
                    element.textContent = element.getAttribute('data-original') || '';
                    UIkit.notification(data.message || 'Failed to update field', {status: 'danger'});
            }
            }
        )
        .catch(
            error => {
            console.error('Error:', error);
            element.textContent = element.getAttribute('data-original') || '';
            UIkit.notification('An error occurred', {status: 'danger'});
            }
        );
    }

    // === THEME MANAGEMENT ===
    initTheme()
    {
        const savedTheme = localStorage.getItem('datatables_theme') || 'light';
        this.setTheme(savedTheme);
    }

    toggleTheme()
    {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }

    setTheme(theme)
    {
        // Update CSS link
        const themeLinks = document.querySelectorAll('link[href*="datatables-"]');
        themeLinks.forEach(
            link => {
            if (link.href.includes('datatables-light.css') || link.href.includes('datatables-dark.css')) {
                const newHref = link.href.replace(/(datatables-)(light|dark)(\.css)/, `$1${theme}$3`);
                link.href = newHref;
            }
            }
        );

        // Save preference
        localStorage.setItem('datatables_theme', theme);
        
        // Set cookie as fallback
        document.cookie = `datatables_theme=${theme}; path=/; max-age=31536000`;
        
        // Update body class for additional styling
        document.body.className = document.body.className
            .replace(/datatables-theme-\w+/, '') + ` datatables-theme-${theme}`;
    }

    getCurrentTheme()
    {
        return localStorage.getItem('datatables_theme') || 'light';
    }

    // === UTILITY METHODS ===
    getColumnCount()
    {
        // Calculate total columns including actions and bulk selection
        let count = Object.keys(this.columns).length || 1;
        count++; // Actions column
        if (this.bulkActionsEnabled) {
            count++; // Bulk selection column
        }
        return count;
    }

    getRowClass(rowId)
    {
        // Use configured row class base or default
        const baseClass = this.cssClasses?.tr || 'datatables-row';
        return baseClass ? `${baseClass}-${rowId}` : '';
    }
}

// Make DataTables available globally
window.DataTablesJS = DataTablesJS;