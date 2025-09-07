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
        this.deleteId = null;
        this.selectedIds = new Set();
        
        // Initialize
        this.init();
    }

    init()
    {
        this.bindEvents();
        this.loadData();
    }

    // === EVENT BINDING ===
    bindEvents()
    {
        // Search input
        document.querySelectorAll('.datatables-search').forEach(searchInput => {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.search = e.target.value;
                    this.currentPage = 1;
                    this.loadData();
                }, 300);
            });
        });

        // Page size selector
        document.querySelectorAll('.datatables-page-size').forEach(pageSizeSelect => {
            pageSizeSelect.addEventListener('change', (e) => {
                this.perPage = parseInt(e.target.value);
                this.currentPage = 1;
                
                // Sync all page size selectors to the same value
                document.querySelectorAll('.datatables-page-size').forEach(select => {
                    select.value = e.target.value;
                });
                
                this.loadData();
            });
        });

        // Bulk actions
        if (this.bulkActionsEnabled) {
            document.querySelectorAll('.datatables-bulk-action').forEach(bulkSelect => {
                bulkSelect.addEventListener('change', (e) => {
                    document.querySelectorAll('.datatables-bulk-execute').forEach(executeBtn => {
                        executeBtn.disabled = !e.target.value || this.selectedIds.size === 0;
                    });
                });
            });
        }

        // Sortable headers
        document.addEventListener('click', (e) => {
            if (e.target.closest('.sortable-header')) {
                console.log('sort clicked');
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
        });
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
        const tbody = document.querySelector('.datatables-tbody');
        if (!tbody) { return; }

        const columnCount = this.getColumnCount();

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columnCount}" class="uk-text-center uk-text-muted">No records found</td></tr>`;
            return;
        }

        // Get table schema for field type information
        const tableElement = document.querySelector('.datatables-table');
        const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};

        let html = '';
        data.forEach(
            row => {

            // Find the ID value regardless of key format
            const rowId = row['s.id'] || row['id'] || row[this.primaryKey] || Object.values(row)[0];
            const rowClass = this.getRowClass(rowId);
            html += `<tr${rowClass ? ` class="${rowClass} row-select"` : ''} data-id="${rowId}">`;

            // Bulk selection checkbox
            if (this.bulkActionsEnabled) {
                html += '<td class="uk-table-shrink row-check">';
                html += `<label><input type="checkbox" class="uk-checkbox row-checkbox" value="${rowId}" onchange="DataTables.toggleRowSelection(this)"></label>`;
                html += '</td>';
            }

            // Action column at start
            if (this.actionConfig.position === 'start') {
                html += '<td class="uk-table-shrink row-action">';
                html += this.renderActionButtons(rowId, row);
                html += '</td>';
            }

            // Regular columns - simplified structure where key=column, value=label
            Object.keys(this.columns).forEach(
                column => {
                const columnClass = this.cssClasses?.columns?.[column] || '';
                const isEditable = this.inlineEditableColumns.includes(column);
                let cellContent = row[column] || '';
                const tdClass = isEditable ? ' cell-edit' : '';
                
                // Get field type from schema
                const fieldType = tableSchema[column]?.override_type || tableSchema[column]?.type || 'text';
                
                // Handle boolean display with icons
                if (fieldType === 'boolean') {
                    const isActive = cellContent == '1' || cellContent === 'true' || cellContent === true;
                    const iconName = isActive ? 'check' : 'close';
                    const iconClass = isActive ? 'uk-text-success' : 'uk-text-danger';
                    
                    // Store the raw value for form population
                    const rawValue = cellContent; // Keep original value
                    
                    if (isEditable) {
                        cellContent = `<span class="inline-editable boolean-toggle" data-field="${column}" data-id="${rowId}" data-type="boolean" data-value="${rawValue}" style="cursor: pointer;">`;
                        cellContent += `<span uk-icon="${iconName}" class="${iconClass}"></span>`;
                        cellContent += '</span>';
                    } else {
                        cellContent = `<span data-value="${rawValue}"><span uk-icon="${iconName}" class="${iconClass}"></span></span>`;
                    }

                // Handle select display with labels
                } else if (fieldType === 'select') {
                    const selectOptions = tableSchema[column]?.form_options || {};
                    const displayLabel = selectOptions[cellContent] || cellContent;
                    
                    if (isEditable) {
                        cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" data-value="${cellContent}" style="cursor: pointer;">${displayLabel}</span>`;
                    } else {
                        cellContent = displayLabel;
                    }
                } else if (isEditable) {

                    // Add inline-editable class and attributes for non-boolean editable fields
                    cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" style="cursor: pointer;">${cellContent}</span>`;
                }
                
                const classNames = [columnClass, tdClass].filter(c => c).join(' ');
                html += `<td${classNames ? ` class="${classNames}"` : ''}>${cellContent}</td>`;
                }
            );

            // Action column at end
            if (this.actionConfig.position === 'end') {
                html += '<td class="uk-table-shrink row-action">';
                html += this.renderActionButtons(rowId, row);
                html += '</td>';
            }

            html += '</tr>';
            }
        );

        tbody.innerHTML = html;
        this.bindTableEvents();
        this.updateBulkActionButtons();
    }
    
    renderActionButtons(rowId, rowData = {})
    {
        let html = '';
        
        // Store row data for callback use
        if (!window.DataTablesRowData) {
            window.DataTablesRowData = {};
        }
        window.DataTablesRowData[rowId] = rowData;
        
        // Helper function to replace all placeholders in a string
        const replacePlaceholders = (str) => {
            if (typeof str !== 'string') return str;
            
            let result = str.replace('{id}', rowId);
            for (const [column, value] of Object.entries(rowData)) {
                const placeholder = '{' + column + '}';
                result = result.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value || '');
            }
            return result;
        };
        
        // Check if we have action groups configured
        if (this.actionConfig.groups && this.actionConfig.groups.length > 0) {
            let groupCount = 0;
            const totalGroups = this.actionConfig.groups.length;
            
            this.actionConfig.groups.forEach(group => {
                groupCount++;
                
                if (Array.isArray(group)) {
                    // Array of built-in actions like ['edit', 'delete']
                    let actionCount = 0;
                    const totalActions = group.length;
                    
                    group.forEach(actionItem => {
                        actionCount++;
                        
                        switch (actionItem) {
                            case 'edit':
                                html += '<a href="#" class="uk-icon-link btn-edit" uk-icon="pencil" title="Edit"></a>';
                                break;
                            case 'delete':
                                html += '<a href="#" class="uk-icon-link btn-delete" uk-icon="trash" title="Delete"></a>';
                                break;
                        }
                        
                        // Add separator within group if not the last action
                        if (actionCount < totalActions) {
                            html += ' ';
                        }
                    });
                } else if (typeof group === 'object') {
                    // Object of custom actions
                    let actionCount = 0;
                    const actionKeys = Object.keys(group);
                    const totalActions = actionKeys.length;
                    
                    actionKeys.forEach(actionKey => {
                        actionCount++;
                        const actionConfig = group[actionKey];
                        
                        if (actionConfig.callback) {
                            // Handle callback action
                            const icon = actionConfig.icon || 'link';
                            const title = actionConfig.title || '';
                            const className = actionConfig.class || 'btn-custom';
                            const confirm = actionConfig.confirm || '';
                            
                            html += '<a href="#" class="uk-icon-link ' + className + '" uk-icon="' + icon + '" title="' + title + '"';
                            html += ' data-action="' + actionKey + '"';
                            html += ' data-id="' + rowId + '"';
                            html += ' data-confirm="' + confirm + '"';
                            html += ' onclick="DataTables.executeActionCallback(\'' + actionKey + '\', ' + rowId + ', event)"';
                            html += '></a>';
                        } else {
                            // Handle regular action
                            const icon = replacePlaceholders(actionConfig.icon || 'link');
                            const title = replacePlaceholders(actionConfig.title || '');
                            const className = replacePlaceholders(actionConfig.class || 'btn-custom');
                            const href = replacePlaceholders(actionConfig.href || '#');
                            const onclick = replacePlaceholders(actionConfig.onclick || '');
                            const attributes = actionConfig.attributes || {};
                            
                            html += '<a href="' + href + '" class="uk-icon-link ' + className + '" uk-icon="' + icon + '" title="' + title + '"';
                            if (onclick) {
                                html += ' onclick="' + onclick + '"';
                            }
                            
                            // Add custom attributes (also replace placeholders)
                            for (const [attrName, attrValue] of Object.entries(attributes)) {
                                const processedValue = replacePlaceholders(String(attrValue));
                                html += ' ' + attrName + '="' + processedValue + '"';
                            }
                            
                            html += '></a>';
                        }
                        
                        // Add separator within group if not the last action
                        if (actionCount < totalActions) {
                            html += ' ';
                        }
                    });
                }
                
                // Add group separator if not the last group
                if (groupCount < totalGroups) {
                    html += ' <span class="uk-text-muted">|</span> ';
                }
            });
        } else {
            // Fallback to original behavior
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
                    // Replace placeholders in all string properties
                    const icon = replacePlaceholders(action.icon || 'link');
                    const title = replacePlaceholders(action.title || '');
                    const className = replacePlaceholders(action.class || 'btn-custom');
                    const href = replacePlaceholders(action.href || '#');
                    const onclick = replacePlaceholders(action.onclick || '');
                    const attributes = action.attributes || {};
                    
                    html += '<a href="' + href + '" class="uk-icon-link ' + className + ' uk-margin-small-right" uk-icon="' + icon + '" title="' + title + '"';
                    if (onclick) {
                        html += ' onclick="' + onclick + '"';
                    }
                    
                    // Add custom attributes (also replace placeholders)
                    for (const [attrName, attrValue] of Object.entries(attributes)) {
                        const processedValue = replacePlaceholders(String(attrValue));
                        html += ' ' + attrName + '="' + processedValue + '"';
                    }
                    
                    html += '></a>';
                    }
                );
            }
        }

        return html;
    }

    // === PAGINATION ===
    renderInfo(data)
    {
        const start = (data.page - 1) * data.per_page + 1;
        const end = Math.min(start + data.per_page - 1, data.total);
        const infoText = `Showing ${start} to ${end} of ${data.total} records`;
        
        document.querySelectorAll('.datatables-info').forEach(info => {
            info.textContent = infoText;
        });
    }

    renderPagination(data)
    {
        if (data.total_pages <= 1) {
            document.querySelectorAll('.datatables-pagination').forEach(pagination => {
                pagination.innerHTML = '';
            });
            return;
        }

        let html = '';
        const currentPage = parseInt(data.page);
        const totalPages = parseInt(data.total_pages);

        // First page button (<<)
        html += `<li${currentPage === 1 ? ' class="uk-disabled"' : ''}>`;
        html += `<a ${currentPage === 1 ? '' : ` onclick="DataTables.goToPage(1)"`} title="First Page">`;
        html += '<span uk-icon="chevron-double-left"></span></a></li>';

        // Previous button (<)
        html += `<li${currentPage === 1 ? ' class="uk-disabled"' : ''}>`;
        html += `<a ${currentPage === 1 ? '' : ` onclick="DataTables.goToPage(${currentPage - 1})"`} title="Previous Page">`;
        html += '<span uk-pagination-previous></span></a></li>';

        // First page number
        if (currentPage > 2) {
            html += '<li><a onclick="DataTables.goToPage(1)">1</a></li>';
            if (currentPage > 3) {
                html += '<li class="uk-disabled"><span>...</span></li>';
            }
        }

        // Page numbers - show only 3 pages around current page
        const start = Math.max(1, currentPage - 1);
        const end = Math.min(totalPages, currentPage + 1);
        for (let i = start; i <= end; i++) {
            html += `<li${i === currentPage ? ' class="uk-active"' : ''}>`;
            html += `<a ${i === currentPage ? '' : ` onclick="DataTables.goToPage(${i})"`}>${i}</a></li>`;
        }

        // Last page number
        if (currentPage < totalPages - 1) {
            if (currentPage < totalPages - 2) {
                html += '<li class="uk-disabled"><span>...</span></li>';
            }
            html += `<li><a onclick="DataTables.goToPage(${totalPages})">${totalPages}</a></li>`;
        }

        // Next button (>)
        html += `<li${currentPage === totalPages ? ' class="uk-disabled"' : ''}>`;
        html += `<a ${currentPage === totalPages ? '' : ` onclick="DataTables.goToPage(${currentPage + 1})"`} title="Next Page">`;
        html += '<span uk-pagination-next></span></a></li>';

        // Last page button (>>)
        html += `<li${currentPage === totalPages ? ' class="uk-disabled"' : ''}>`;
        html += `<a ${currentPage === totalPages ? '' : ` onclick="DataTables.goToPage(${totalPages})"`} title="Last Page">`;
        html += '<span uk-icon="chevron-double-right"></span></a></li>';

        document.querySelectorAll('.datatables-pagination').forEach(pagination => {
            pagination.innerHTML = html;
        });
    }

    goToPage(page)
    {
        this.currentPage = page;
        this.loadData();
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
            const selectAllCheckbox = document.querySelector('.datatables-select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
        }
        this.updateBulkActionButtons();
    }

    updateBulkActionButtons()
    {
        const hasSelection = this.selectedIds.size > 0;
        
        // Update all bulk action buttons
        document.querySelectorAll('.datatables-bulk-action-btn').forEach(btn => {
            btn.disabled = !hasSelection;
        });
    }

    executeBulkActionDirect(action, event)
    {

        if (event) {
            event.preventDefault();
        }
        const selectedIds = Array.from(this.selectedIds);

        if (selectedIds.length === 0) {
            UIkit.notification('No records selected', {status: 'warning'});
            return;
        }

        // Check if action requires confirmation
        const actionButton = document.querySelector(`[data-action="${action}"]`);
        const confirmMessage = actionButton ? actionButton.getAttribute('data-confirm') : '';
        
        if (confirmMessage) {
            UIkit.modal.confirm(confirmMessage).then(
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

    executeActionCallback(action, rowId, event)
    {
        if (event) {
            event.preventDefault();
        }
        
        // Get row data
        const rowData = window.DataTablesRowData ? window.DataTablesRowData[rowId] : {};
        
        // Find the action configuration
        let actionConfig = null;
        if (this.actionConfig.groups) {
            for (const group of this.actionConfig.groups) {
                if (typeof group === 'object' && !Array.isArray(group)) {
                    if (group[action] && group[action].callback) {
                        actionConfig = group[action];
                        break;
                    }
                }
            }
        }
        
        if (!actionConfig) {
            return;
        }
        
        // Check if confirmation is required
        if (actionConfig.confirm) {
            UIkit.modal.confirm(actionConfig.confirm).then(
                () => {
                    this.performActionCallback(action, rowId, rowData, actionConfig);
                }, () => {
                    // User cancelled
                }
            );
        } else {
            this.performActionCallback(action, rowId, rowData, actionConfig);
        }
    }

    performActionCallback(action, rowId, rowData, actionConfig)
    {
        const formData = new FormData();
        formData.append('action', 'action_callback');
        formData.append('action_name', action);
        formData.append('row_id', rowId);
        formData.append('row_data', JSON.stringify(rowData));

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
                this.loadData();
                UIkit.notification(data.message || actionConfig.success_message || 'Action completed', {status: 'success'});
            } else {
                    UIkit.notification(data.message || actionConfig.error_message || 'Action failed', {status: 'danger'});
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

    /**
     * Reset search functionality
     */
    resetSearch()
    {
        // Clear search input
        document.querySelectorAll('.datatables-search').forEach(searchInput => {
            searchInput.value = '';
        });
        
        // Reset search state and reload data
        this.search = '';
        this.currentPage = 1;
        this.loadData();
    }

    executeBulkAction()
    {
        const bulkSelect = document.querySelector('.datatables-bulk-action');
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
                const bulkSelect = document.querySelector('.datatables-bulk-action');
                if (bulkSelect) { bulkSelect.value = '';
                }
                
                const selectAll = document.querySelector('.datatables-select-all');
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
    showAddModal(event)
    {
        if (event) {
            event.preventDefault();
        }
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
        // Fetch complete record data via AJAX instead of parsing table cells
        const params = new URLSearchParams({
            action: 'fetch_record',
            id: id
        });

        fetch('?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Populate all form fields with the fetched data
                    this.populateEditForm(data.data);
                } else {
                    console.error('Failed to fetch record:', data.message);
                    UIkit.notification(data.message || 'Failed to fetch record data', {status: 'danger'});
                }
            })
            .catch(error => {
                console.error('Error fetching record:', error);
                UIkit.notification('Error fetching record data', {status: 'danger'});
            });
    }
    

    populateEditForm(recordData)
    {
        // Set the primary key field
        const pkField = document.getElementById(`edit-${this.primaryKey}`);
        if (pkField) {
            pkField.value = recordData[this.primaryKey] || '';
        }

        // Get all form fields in the edit form
        const editForm = document.getElementById('edit-form');
        if (!editForm) return;

        // Find all form inputs, selects, and textareas
        const formElements = editForm.querySelectorAll('input, select, textarea');
        
        formElements.forEach(element => {
            const fieldName = element.name;
            
            // Skip if no field name or if it's the primary key (already handled)
            if (!fieldName || fieldName === this.primaryKey) return;
            
            // Get value from record data
            const value = recordData[fieldName];
            
            if (value !== undefined && value !== null) {
                if (element.type === 'checkbox') {
                    element.checked = value == '1' || value === 'true' || value === true;
                } else if (element.type === 'radio') {
                    element.checked = element.value === String(value);
                } else {
                    element.value = value;
                }
            } else {
                // Clear field if no value found
                if (element.type === 'checkbox' || element.type === 'radio') {
                    element.checked = false;
                } else {
                    element.value = '';
                }
            }
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

        // Inline edit for regular fields - improved selector
        document.querySelectorAll('td .inline-editable:not(.boolean-toggle)').forEach(
            span => {
                span.addEventListener(
                    'dblclick', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.startInlineEdit(e.target);
                    }
                );
            }
        );

        // Boolean toggle for boolean fields - improved selector
        document.querySelectorAll('td .boolean-toggle').forEach(
            span => {
                span.addEventListener(
                    'click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleBoolean(e.target.closest('.boolean-toggle'));
                    }
                );
            }
        );

        // Clickable rows
        document.querySelectorAll('tr.row-select').forEach(row => {
            row.addEventListener('click', (e) => {
                const clickedTd = e.target.closest('td');
                if (clickedTd && !clickedTd.classList.contains('row-check') && 
                    !clickedTd.classList.contains('row-action') && 
                    !clickedTd.classList.contains('cell-edit')) {
                    
                    const checkbox = row.querySelector('.row-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.toggleRowSelection(checkbox);
                    }
                }
            });
        });

    }   

    startInlineEdit(element)
    {
        const field = element.getAttribute('data-field');
        const id = element.getAttribute('data-id');
        const fieldType = element.getAttribute('data-type') || 'text';
        // Get current value - check for stored data-value first, then fallback to text content
        const currentValue = element.getAttribute('data-value') || element.textContent;

        if (!this.inlineEditableColumns.includes(field)) { return; }

        let inputElement;
        
        // Create appropriate input based on field type
        switch (fieldType) {
            case 'select':
                // Get schema information for options
                const tableElement = document.querySelector('.datatables-table');
                const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};
                const options = tableSchema[field]?.form_options || {};
                
                inputElement = document.createElement('select');
                inputElement.className = 'uk-select uk-width-1-1';
                
                // Add options
                for (const [value, label] of Object.entries(options)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (value === currentValue) {
                        option.selected = true;
                    }
                    inputElement.appendChild(option);
                }
                break;
                
            case 'textarea':
                inputElement = document.createElement('textarea');
                inputElement.className = 'uk-textarea uk-width-1-1';
                inputElement.value = currentValue;
                //inputElement.style.minHeight = '60px';
                break;
                
            case 'number':
                inputElement = document.createElement('input');
                inputElement.type = 'number';
                inputElement.className = 'uk-input uk-width-1-1';
                inputElement.value = currentValue;
                //inputElement.style.width = '100px';
                break;
                
            case 'date':
                inputElement = document.createElement('input');
                inputElement.type = 'date';
                inputElement.className = 'uk-input uk-width-1-1';
                inputElement.value = currentValue;
                //inputElement.style.width = '150px';
                break;
                
            case 'datetime-local':
                inputElement = document.createElement('input');
                inputElement.type = 'datetime-local';
                inputElement.className = 'uk-input uk-width-1-1';
                inputElement.value = currentValue;
                //inputElement.style.width = '200px';
                break;
                
            default: // text, email, etc.
                inputElement = document.createElement('input');
                inputElement.type = fieldType === 'email' ? 'email' : 'text';
                inputElement.className = 'uk-input uk-width-1-1';
                inputElement.value = currentValue;
                //inputElement.style.width = '150px';
        }

        const saveEdit = () => {
            const newValue = inputElement.value;
            if (newValue !== currentValue) {
                this.saveInlineEdit(id, field, newValue, element);
            } else {
                element.textContent = currentValue;
            }
        };

        const cancelEdit = () => {
            element.textContent = currentValue;
        };

        inputElement.addEventListener('blur', saveEdit);
        inputElement.addEventListener(
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
        element.appendChild(inputElement);
        inputElement.focus();
        if (inputElement.select) {
            inputElement.select();
        }
    }

    toggleBoolean(element)
    {
        const field = element.getAttribute('data-field');
        const id = element.getAttribute('data-id');
        
        // Get current value from the icon
        const currentIcon = element.querySelector('[uk-icon]');
        const isCurrentlyActive = currentIcon.getAttribute('uk-icon') === 'check';
        const newValue = isCurrentlyActive ? '0' : '1';
        
        this.saveInlineEdit(id, field, newValue, element);
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
                // Handle boolean fields differently
                if (element.classList.contains('boolean-toggle')) {
                    const isActive = value == '1' || value === 'true' || value === true;
                    const iconName = isActive ? 'check' : 'close';
                    const iconClass = isActive ? 'uk-text-success' : 'uk-text-danger';
                    
                    element.innerHTML = `<span uk-icon="${iconName}" class="${iconClass}"></span>`;
                    element.setAttribute('data-value', value);
                } else if (element.getAttribute('data-type') === 'select') {
                    
                    // Handle select fields - show label but store value
                    const tableElement = document.querySelector('.datatables-table');
                    const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};
                    const field = element.getAttribute('data-field');
                    const selectOptions = tableSchema[field]?.form_options || {};
                    const displayLabel = selectOptions[value] || value;
                    
                    element.setAttribute('data-value', value);
                    element.textContent = displayLabel;
                } else {
                    element.textContent = value;
                }
                
                // Update edit form if it's open and has this field
                const editForm = document.getElementById(`edit-${field}`);
                if (editForm) {
                    if (editForm.type === 'checkbox') {
                        editForm.checked = value === '1' || value === 'true' || value === true;
                    } else {
                        editForm.value = value;
                    }
                }
                
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

    changePageSize(newSize, event)
    {

        if (event) {
            event.preventDefault();
        }

        this.perPage = parseInt(newSize);
        this.currentPage = 1;
        
        // Update button group active states
        document.querySelectorAll('.datatables-page-size-btn').forEach(btn => {
            const btnSize = parseInt(btn.getAttribute('data-size'));
            if (btnSize === this.perPage) {
                btn.classList.remove('uk-button-default');
                btn.classList.add('uk-button-primary');
            } else {
                btn.classList.remove('uk-button-primary');
                btn.classList.add('uk-button-default');
            }
        });
        
        // Also sync select dropdowns if present
        document.querySelectorAll('.datatables-page-size').forEach(select => {
            select.value = newSize;
        });
        
        this.loadData();
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