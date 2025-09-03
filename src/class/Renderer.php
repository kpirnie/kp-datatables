<?php

declare(strict_types=1);

namespace KPT\DataTables;

/**
 * Renderer - HTML Rendering Engine for DataTables
 *
 * This class is responsible for generating all HTML output for DataTables including
 * the main table structure, form modals, pagination controls, and JavaScript initialization.
 * It transforms the DataTables configuration into a complete, interactive user interface
 * using UIKit3 components and custom styling.
 *
 * The renderer handles:
 * - CSS and JavaScript file inclusion with theme support
 * - Main table HTML with headers, body, and pagination
 * - Modal forms for add/edit operations (auto-generated from schema)
 * - Control panels with search, bulk actions, and settings
 * - JavaScript initialization and configuration
 * - Responsive design elements
 * - Accessibility features
 *
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT\DataTables
 */
class Renderer
{
    /**
     * DataTables instance containing all configuration data
     *
     * @var DataTables
     */
    private DataTables $dataTable;

    /**
     * Constructor - Initialize renderer with DataTables configuration
     *
     * @param DataTables $dataTable The configured DataTables instance
     */
    public function __construct(DataTables $dataTable)
    {
        $this->dataTable = $dataTable;
    }

    /**
     * Generate complete HTML output for the DataTable
     *
     * This is the main entry point that orchestrates the rendering of all components.
     * It combines CSS/JS includes, the main container, modals, and initialization scripts
     * into a complete, functional DataTable interface.
     *
     * @return string Complete HTML output ready for display
     */
    public function render(): string
    {
        // Build complete HTML structure
        $html = $this->renderContainer();      // Main table container
        $html .= $this->renderModals();         // Add/Edit/Delete modals (auto-generated)
        $html .= $this->renderInitScript();     // JavaScript initialization

        return $html;
    }

    /**
     * Render CSS file includes with improved footer positioning
     *
     * Generates the necessary <link> tags for external files.
     * Supports theme switching by detecting current theme from URL parameters
     * or cookies. Files are loaded from the vendor directory structure.
     * Includes additional CSS for proper footer positioning.
     *
     * @param string $theme Theme name ('light' or 'dark')
     * @return string HTML with CSS includes and footer positioning styles
     */
    public static function getCssIncludes(string $theme = 'light'): string
    {
        $html = "<!-- DataTables CSS -->\n";
        $html .= "<link rel=\"stylesheet\" href=\"vendor/kevinpirnie/kpt-datatables/src/assets/css/datatables-{$theme}.css\" />\n";
        
        // Add footer positioning CSS to ensure footer stays at bottom
        $html .= "<style>\n";
        $html .= "body { min-height: 100vh; display: flex; flex-direction: column; }\n";
        $html .= ".datatables-container { flex: 1; }\n";
        $html .= ".datatables-footer { margin-top: auto; }\n";
        $html .= "</style>\n";
        
        return $html;
    }

    /**
     * Render JavaScript file includes
     *
     * Generates the necessary <script> tags for external files.
     * Files are loaded from the vendor directory structure.
     *
     * @return string HTML with JavaScript includes
     */
    public static function getJsIncludes(): string
    {
        $html = "<!-- DataTables JavaScript -->\n";
        $html .= "<script src=\"vendor/kevinpirnie/kpt-datatables/src/assets/js/datatables.js\"></script>\n";
        return $html;
    }

    /**
     * Render the main DataTables container
     *
     * Creates the primary container div that holds all DataTables components.
     * The container includes a unique class based on the table name for styling
     * and JavaScript targeting.
     *
     * @return string HTML container with all table components
     */
    private function renderContainer(): string
    {
        $tableName = $this->dataTable->getTableName();
        $containerClass = "datatables-container-{$tableName}";

        // Create main container with table-specific class
        $html = "<div class=\"{$containerClass} datatables-container\" data-table=\"{$tableName}\">\n";

        // Build container contents
        $html .= $this->renderControls();       // Top control panel
        $html .= $this->renderTable();          // Main data table
        $html .= $this->renderPagination();     // Bottom pagination

        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render the control panel with actions and filters
     *
     * Creates the top control panel containing add buttons, bulk actions,
     * theme toggle, search functionality, and page size selector.
     * Uses UIKit3 grid system for responsive layout. Add button is always
     * available regardless of inline editing configuration.
     *
     * @return string HTML control panel
     */
    private function renderControls(): string
    {
        $html = "<div class=\"uk-card uk-card-default uk-card-body uk-margin-bottom\">\n";
        $html .= "<div class=\"uk-grid-small uk-child-width-auto\" uk-grid>\n";

        // Add new record button - always available
        $html .= "<div>\n";
        $html .= "<button class=\"uk-button uk-button-primary\" type=\"button\" onclick=\"DataTables.showAddModal()\">\n";
        $html .= "<span uk-icon=\"plus\"></span> Add Record\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        // Bulk actions dropdown and execute button (if enabled)
        $bulkActions = $this->dataTable->getBulkActions();
        if ($bulkActions['enabled']) {
            $html .= $this->renderBulkActions($bulkActions);
        }

        // Theme toggle button for light/dark mode switching
        $html .= "<div>\n";
        $html .= "<button class=\"uk-button uk-button-default\" type=\"button\" onclick=\"DataTables.toggleTheme()\">\n";
        $html .= "<span uk-icon=\"paint-bucket\"></span> Toggle Theme\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        // Search functionality (if enabled)
        if ($this->dataTable->isSearchEnabled()) {
            $html .= $this->renderSearchForm();
        }

        // Records per page selector
        $html .= $this->renderPageSizeSelector();

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render bulk actions dropdown and execute button
     *
     * Creates the bulk actions interface including a dropdown selector
     * for available actions and an execute button. Both elements start
     * disabled and are enabled when records are selected.
     *
     * @param  array $bulkConfig Bulk actions configuration from DataTables
     * @return string HTML bulk actions controls
     */
    private function renderBulkActions(array $bulkConfig): string
    {
        $html = "<div>\n";

        // Bulk action selector dropdown (initially disabled)
        $html .= "<select class=\"uk-select uk-width-auto\" id=\"datatables-bulk-action\" disabled>\n";
        $html .= "<option value=\"\">Bulk Actions</option>\n";

        // Add option for each configured bulk action
        foreach ($bulkConfig['actions'] as $action => $config) {
            $label = $config['label'] ?? ucfirst($action);
            $html .= "<option value=\"{$action}\">{$label}</option>\n";
        }

        $html .= "</select>\n";

        // Execute button (initially disabled)
        $html .= "<button class=\"uk-button uk-button-default uk-margin-small-left\" type=\"button\" " .
                 "id=\"datatables-bulk-execute\" onclick=\"DataTables.executeBulkAction()\" disabled>\n";
        $html .= "<span uk-icon=\"play\"></span> Execute\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render search form with input and column selector
     *
     * Creates the search interface including a text input with search icon
     * and a dropdown to select which column to search. The "All Columns"
     * option enables global searching across all configured columns.
     *
     * @return string HTML search form elements
     */
    private function renderSearchForm(): string
    {
        $columns = $this->dataTable->getColumns();

        // Search input with icon
        $html = "<div>\n";
        $html .= "<div class=\"uk-inline uk-width-medium\">\n";
        $html .= "<span class=\"uk-form-icon\" uk-icon=\"search\"></span>\n";
        $html .= "<input class=\"uk-input\" type=\"text\" placeholder=\"Search...\" id=\"datatables-search\">\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        // Column selector dropdown
        $html .= "<div>\n";
        $html .= "<select class=\"uk-select uk-width-small\" id=\"datatables-search-column\">\n";
        $html .= "<option value=\"all\">All Columns</option>\n";

        // Add option for each configured column (key is column name, value is label)
        foreach ($columns as $column => $label) {
            $html .= "<option value=\"{$column}\">{$label}</option>\n";
        }

        $html .= "</select>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render page size selector dropdown
     *
     * Creates a dropdown allowing users to change how many records are displayed
     * per page. Includes all configured options and optionally an "All records" choice.
     *
     * @return string HTML page size selector
     */
    private function renderPageSizeSelector(): string
    {
        $options = $this->dataTable->getPageSizeOptions();
        $includeAll = $this->dataTable->getIncludeAllOption();
        $current = $this->dataTable->getRecordsPerPage();

        $html = "<div>\n";
        $html .= "<select class=\"uk-select uk-width-auto\" id=\"datatables-page-size\">\n";

        // Add each configured page size option
        foreach ($options as $option) {
            $selected = $option === $current ? ' selected' : '';
            $html .= "<option value=\"{$option}\"{$selected}>{$option} records</option>\n";
        }

        // Add "All records" option if enabled (value of 0 means no limit)
        if ($includeAll) {
            $html .= "<option value=\"0\">All records</option>\n";
        }

        $html .= "</select>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render the main data table structure
     *
     * Creates the complete HTML table including headers, body, and styling.
     * Handles bulk selection checkboxes, sortable headers, action columns,
     * and applies all configured CSS classes.
     *
     * @return string HTML table structure
     */
    private function renderTable(): string
    {
        // Extract configuration for table rendering
        $columns = $this->dataTable->getColumns();
        $sortableColumns = $this->dataTable->getSortableColumns();
        $actionConfig = $this->dataTable->getActionConfig();
        $bulkActions = $this->dataTable->getBulkActions();
        $cssClasses = $this->dataTable->getCssClasses();

        // Get CSS classes with defaults
        $tableClass = $cssClasses['table'] ?? 'uk-table';
        $theadClass = $cssClasses['thead'] ?? '';
        $tbodyClass = $cssClasses['tbody'] ?? '';

        // Start table with scrollable container
        $html = "<div class=\"uk-overflow-auto\">\n";
        $html .= "<table class=\"{$tableClass}\" id=\"datatables-table\">\n";

        // === TABLE HEADER ===
        $html .= "<thead" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
        $html .= "<tr>\n";

        // Bulk selection master checkbox (if bulk actions enabled)
        if ($bulkActions['enabled']) {
            $html .= "<th class=\"uk-table-shrink\">\n";
            $html .= "<label><input type=\"checkbox\" class=\"uk-checkbox\" id=\"select-all\" onchange=\"DataTables.toggleSelectAll(this)\"></label>\n";
            $html .= "</th>\n";
        }

        // Action column at start of row (if configured)
        if ($actionConfig['position'] === 'start') {
            $html .= "<th class=\"uk-table-shrink\">Actions</th>\n";
        }

        // Regular data columns - key is column name, value is display label
        foreach ($columns as $column => $label) {
            // Determine if column is sortable
            $sortable = in_array($column, $sortableColumns);
            $columnClass = $cssClasses['columns'][$column] ?? '';
            $thClass = $columnClass . ($sortable ? ' sortable' : '');

            // Build header cell
            $html .= "<th" . (!empty($thClass) ? " class=\"{$thClass}\"" : "") .
                     ($sortable ? " data-sort=\"{$column}\"" : "") . ">";

            if ($sortable) {
                // Sortable header with click handler and sort indicator
                $html .= "<span class=\"sortable-header\">{$label} <span class=\"sort-icon\" uk-icon=\"triangle-up\"></span></span>";
            } else {
                // Non-sortable header
                $html .= $label;
            }

            $html .= "</th>\n";
        }

        // Action column at end of row (if configured)
        if ($actionConfig['position'] === 'end') {
            $html .= "<th class=\"uk-table-shrink\">Actions</th>\n";
        }

        $html .= "</tr>\n";
        $html .= "</thead>\n";

        // === TABLE BODY ===
        $html .= "<tbody" . (!empty($tbodyClass) ? " class=\"{$tbodyClass}\"" : "") . " id=\"datatables-tbody\">\n";

        // Calculate total columns for loading placeholder
        $totalColumns = count($columns) + 1; // +1 for actions
        if ($bulkActions['enabled']) {
            $totalColumns++; // +1 for bulk selection checkboxes
        }

        // Initial loading placeholder row
        $html .= "<tr><td colspan=\"{$totalColumns}\" class=\"uk-text-center\">Loading...</td></tr>\n";
        $html .= "</tbody>\n";

        $html .= "</table>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render pagination controls and record information with footer styling
     *
     * Creates the bottom section with record count information and pagination
     * controls. The pagination will be populated by JavaScript after data loads.
     * Includes footer class for proper positioning at bottom of screen.
     *
     * @return string HTML pagination section
     */
    private function renderPagination(): string
    {
        $html = "<div class=\"uk-card uk-card-default uk-card-body uk-margin-top datatables-footer\">\n";
        $html .= "<div class=\"uk-flex uk-flex-between uk-flex-middle\">\n";

        // Record count information (updated by JavaScript)
        $html .= "<div class=\"uk-text-meta\" id=\"datatables-info\">\n";
        $html .= "Showing 0 to 0 of 0 records\n";
        $html .= "</div>\n";

        // Pagination controls container (populated by JavaScript)
        $html .= "<div>\n";
        $html .= "<ul class=\"uk-pagination\" id=\"datatables-pagination\">\n";
        $html .= "<li class=\"uk-disabled\"><span uk-pagination-previous></span></li>\n";
        $html .= "<li class=\"uk-disabled\"><span uk-pagination-next></span></li>\n";
        $html .= "</ul>\n";
        $html .= "</div>\n";

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render all modal dialogs for forms (auto-generated from schema)
     *
     * Creates the modal dialogs used for add, edit, and delete operations.
     * Each modal is initially hidden and shown by JavaScript when needed.
     * Forms are automatically generated based on database schema.
     *
     * @return string HTML for all modal dialogs
     */
    private function renderModals(): string
    {
        $html = $this->renderAddModal();        // Add record form modal (auto-generated)
        $html .= $this->renderEditModal();      // Edit record form modal (auto-generated)
        $html .= $this->renderDeleteModal();    // Delete confirmation modal
        return $html;
    }

    /**
     * Render the add record modal form with auto-generated fields
     *
     * Creates a modal dialog containing a form for adding new records.
     * The form fields are automatically generated based on the database schema
     * and include proper validation, styling, and submission handling.
     *
     * @return string HTML add record modal
     */
    private function renderAddModal(): string
    {
        $formFields = $this->dataTable->getFormFields();

        // Modal container
        $html = "<div id=\"add-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">Add New Record</h2>\n";

        // Form with AJAX submission (always AJAX, never in public files)
        $html .= "<form class=\"uk-form-stacked\" id=\"add-form\" onsubmit=\"return DataTables.submitAddForm(event)\">\n";

        // Generate form fields automatically from database schema
        foreach ($formFields as $field => $config) {
            $html .= $this->renderFormField($field, $config, 'add');
        }

        // Modal action buttons
        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-primary uk-margin-small-left\" type=\"submit\">Add Record</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render the edit record modal form with auto-generated fields
     *
     * Creates a modal dialog for editing existing records. Similar to the add modal
     * but includes a hidden field for the record ID and will be pre-populated
     * with existing data by JavaScript. Fields are auto-generated from schema.
     *
     * @return string HTML edit record modal
     */
    private function renderEditModal(): string
    {
        $formFields = $this->dataTable->getFormFields();
        $primaryKey = $this->dataTable->getPrimaryKey();

        // Modal container
        $html = "<div id=\"edit-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">Edit Record</h2>\n";

        // Form with AJAX submission (always AJAX, never in public files)
        $html .= "<form class=\"uk-form-stacked\" id=\"edit-form\" onsubmit=\"return DataTables.submitEditForm(event)\">\n";

        // Hidden field for record ID (populated by JavaScript)
        $html .= "<input type=\"hidden\" name=\"{$primaryKey}\" id=\"edit-{$primaryKey}\">\n";

        // Generate form fields automatically from database schema
        foreach ($formFields as $field => $config) {
            $html .= $this->renderFormField($field, $config, 'edit');
        }

        // Modal action buttons
        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-primary uk-margin-small-left\" type=\"submit\">Update Record</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render the delete confirmation modal
     *
     * Creates a simple confirmation dialog for delete operations. Contains
     * warning text and confirmation/cancel buttons. No form fields are needed
     * since only confirmation is required.
     *
     * @return string HTML delete confirmation modal
     */
    private function renderDeleteModal(): string
    {
        $html = "<div id=\"delete-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">Confirm Delete</h2>\n";
        $html .= "<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n";

        // Confirmation buttons
        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-danger uk-margin-small-left\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
        $html .= "</div>\n";

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render a single form field element based on database schema with enhanced configuration
     *
     * Generates HTML for various form field types including text inputs, selects,
     * textareas, checkboxes, radio buttons, file uploads, and date/time fields.
     * Handles validation attributes, styling classes, and accessibility features.
     * Field types are automatically determined from database schema or overridden.
     *
     * @param  string $field  Field name for form submission
     * @param  array  $config Field configuration array with type, label, validation, etc.
     * @param  string $prefix Field prefix for ID generation ('add' or 'edit')
     * @return string HTML form field element
     */
    private function renderFormField(string $field, array $config, string $prefix = 'add'): string
    {
        // Extract field configuration with defaults
        $type = $config['type'];
        $label = $config['label'];
        $required = $config['required'] ?? false;
        $placeholder = $config['placeholder'] ?? '';
        $options = $config['options'] ?? [];
        $customClass = $config['class'] ?? '';
        $attributes = $config['attributes'] ?? [];
        $value = $config['value'] ?? '';

        // Generate unique IDs for form fields
        $fieldId = "{$prefix}-{$field}";
        $fieldName = $field;

        // Start field container
        $html = "<div class=\"uk-margin\">\n";
        
        // Render field based on type
        switch ($type) {
            case 'checkbox':
                // Checkbox field for boolean values (no separate label div)
                $baseClass = 'uk-checkbox';
                $fieldClass = $customClass ? "{$baseClass} {$customClass}" : $baseClass;
                $attrString = $this->buildAttributeString($attributes);
                
                $html .= "<div class=\"uk-form-controls\">\n";
                $html .= "<label>";
                $html .= "<input type=\"checkbox\" class=\"{$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}";
                if ($value == '1' || $value === true) {
                    $html .= " checked";
                }
                $html .= "> {$label}";
                if ($required) {
                    $html .= " <span class=\"uk-text-danger\">*</span>";
                }
                $html .= "</label>\n";
                $html .= "</div>\n";
                break;

            case 'textarea':
                // Multi-line text input for TEXT columns
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";
                
                $baseClass = 'uk-textarea';
                $fieldClass = $customClass ? "{$baseClass} {$customClass}" : $baseClass;
                $attrString = $this->buildAttributeString($attributes);
                
                $html .= "<textarea class=\"{$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "placeholder=\"{$placeholder}\" {$attrString} " . ($required ? "required" : "") . "></textarea>\n";
                $html .= "</div>\n";
                break;

            case 'select':
                // Dropdown selection for ENUM columns
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";
                
                $baseClass = 'uk-select';
                $fieldClass = $customClass ? "{$baseClass} {$customClass}" : $baseClass;
                $attrString = $this->buildAttributeString($attributes);
                
                $html .= "<select class=\"{$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "{$attrString} " . ($required ? "required" : "") . ">\n";
                
                // Add empty option if field is not required
                if (!$required) {
                    $html .= "<option value=\"\">-- Select --</option>\n";
                }
                
                // Add all options (from enum or custom)
                foreach ($options as $optValue => $optLabel) {
                    $selected = ($value == $optValue) ? ' selected' : '';
                    $html .= "<option value=\"{$optValue}\"{$selected}>{$optLabel}</option>\n";
                }
                $html .= "</select>\n";
                $html .= "</div>\n";
                break;

            case 'file':
                // File upload field
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";
                
                $baseClass = 'uk-input';
                $fieldClass = $customClass ? "{$baseClass} {$customClass}" : $baseClass;
                $attrString = $this->buildAttributeString($attributes);
                
                $html .= "<input type=\"file\" class=\"{$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "{$attrString} " . ($required ? "required" : "") . ">\n";
                $html .= "</div>\n";
                break;

            default:
                // Standard input fields (text, email, number, date, datetime-local, time, etc.)
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";
                
                $baseClass = 'uk-input';
                $fieldClass = $customClass ? "{$baseClass} {$customClass}" : $baseClass;
                $attrString = $this->buildAttributeString($attributes);
                
                $html .= "<input type=\"{$type}\" class=\"{$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "placeholder=\"{$placeholder}\" value=\"{$value}\" {$attrString} " . 
                        ($required ? "required" : "") . ">\n";
                $html .= "</div>\n";
                break;
        }

        // Close field container
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Build HTML attribute string from array
     *
     * @param  array $attributes Associative array of attribute name => value pairs
     * @return string HTML attribute string
     */
    private function buildAttributeString(array $attributes): string
    {
        $attrParts = [];
        foreach ($attributes as $name => $value) {
            $attrParts[] = "{$name}=\"{$value}\"";
        }
        return implode(' ', $attrParts);
    }

    /**
     * Render JavaScript initialization script
     *
     * Generates the JavaScript code that initializes the DataTables instance
     * with all configuration options. This script runs when the DOM is ready
     * and sets up all interactive functionality.
     *
     * @return string JavaScript initialization code
     */
    private function renderInitScript(): string
    {
        // Extract configuration for JavaScript
        $tableName = $this->dataTable->getTableName();
        $primaryKey = $this->dataTable->getPrimaryKey();
        $inlineEditableColumns = json_encode($this->dataTable->getInlineEditableColumns());
        $bulkActions = $this->dataTable->getBulkActions();
        $actionConfig = $this->dataTable->getActionConfig();
        $columns = $this->dataTable->getColumns();

        // Generate initialization script
        $html = "<script>\n";
        $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
        $html .= "    // Initialize DataTables with configuration\n";
        $html .= "    window.DataTables = new DataTablesJS({\n";
        $html .= "        tableName: '{$tableName}',\n";
        $html .= "        primaryKey: '{$primaryKey}',\n";
        $html .= "        inlineEditableColumns: {$inlineEditableColumns},\n";
        $html .= "        perPage: " . $this->dataTable->getRecordsPerPage() . ",\n";
        $html .= "        bulkActionsEnabled: " . ($bulkActions['enabled'] ? 'true' : 'false') . ",\n";
        $html .= "        bulkActions: " . json_encode($bulkActions['actions']) . ",\n";
        $html .= "        actionConfig: " . json_encode($actionConfig) . ",\n";
        $html .= "        columns: " . json_encode($columns) . "\n";
        $html .= "    });\n";
        $html .= "});\n";
        $html .= "</script>\n";

        return $html;
    }
}