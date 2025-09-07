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
class Renderer extends DataTablesBase
{
    /**
     * Constructor - Initialize renderer with DataTables configuration
     *
     * @param DataTables $dataTable The configured DataTables instance
     */
    public function __construct(?DataTables $dataTable = null)
    {
        /*
        if ($dataTable) {
            // Copy properties from DataTables instance
            foreach (get_object_vars($dataTable) as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
        }
        */
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
    protected function render(): string
    {
        // Build complete HTML structure
        $html = $this->renderContainer();      // Main table container
        $html .= $this->renderModals();         // Add/Edit/Delete modals (auto-generated)
        $html .= $this->renderInitScript();     // JavaScript initialization

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
    protected function renderContainer(): string
    {
        $tableName = $this->getTableName();
        $containerClass = "datatables-container-{$tableName}";

        // Create main container with table-specific class
        $html = "<div class=\"{$containerClass} datatables-container\" data-table=\"{$tableName}\">\n";

        // Build Main data table
        $html .= $this->renderTable();

        $html .= "</div>\n";

        // return the rendered htm
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
    protected function renderBulkActions(array $bulkConfig): string
    {
        $html = "<div class=\"uk-grid-small uk-child-width-auto\" uk-grid>\n";

        // Add new record button - always available
        $html .= "      <div>\n";
        $html .= "          <a href=\"#\" class=\"uk-icon-link\" uk-icon=\"plus\" onclick=\"DataTables.showAddModal(event)\" uk-tooltip=\"Add a New Record\"></a>\n";
        $html .= "      </div>\n";

        // Check if we have action groups with bulk-capable actions
        $actionConfig = $this->getActionConfig();
        $hasGroupActions = false;
        $groupActions = [];

        if (isset($actionConfig['groups'])) {
            foreach ($actionConfig['groups'] as $group) {
                if (is_array($group)) {
                    foreach ($group as $actionKey => $actionData) {
                        if ($actionKey === 'delete' || (is_string($actionData) && $actionData === 'delete')) {
                            $hasGroupActions = true;
                            $groupActions['delete'] = [
                                'icon' => 'trash',
                                'label' => 'Delete Selected',
                                'confirm' => 'Are you sure you want to delete the selected records?'
                            ];
                        }
                    }
                }
            }
        }

        // Use group actions if available, otherwise fall back to bulk actions
        $actionsToRender = $hasGroupActions ? $groupActions : $bulkConfig['actions'];

        $actionCount = 0;
        $totalActions = count($actionsToRender);

        foreach ($actionsToRender as $action => $config) {
            $actionCount++;

            $icon = $config['icon'] ?? 'link';
            $label = $config['label'] ?? ucfirst($action);
            $confirm = $config['confirm'] ?? '';

            $html .= "<div>\n";
            $html .= "<a class=\"uk-icon-link datatables-bulk-action-btn\" uk-icon=\"{$icon}\" ";
            $html .= "data-action=\"{$action}\" ";
            $html .= "data-confirm=\"{$confirm}\" ";
            $html .= "onclick=\"DataTables.executeBulkActionDirect('{$action}', event)\" ";
            $html .= "uk-tooltip=\"{$label}\" disabled></a>\n";
            $html .= "</div>\n";

            if ($actionCount < $totalActions) {
                $html .= "<div class=\"uk-text-muted\">|</div>\n";
            }
        }

        $html .= "</div>\n";
        return $html;
    }

    /**
     * Render search form with input, column selector, and reset button
     *
     * Creates the search interface including a text input with search icon,
     * a dropdown to select which column to search, and a reset button to clear search.
     *
     * @return string HTML search form elements
     */
    protected function renderSearchForm(): string
    {
        $columns = $this->getColumns();

        // Search input with icon
        $html = "<div>\n";
        $html .= "<div class=\"uk-inline uk-width-medium\">\n";
        $html .= "<span class=\"uk-form-icon\" uk-icon=\"search\"></span>\n";
        $html .= "<input class=\"uk-input datatables-search\" type=\"text\" placeholder=\"Search...\">\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        // Reset search button
        $html .= "<div>\n";
        $html .= "<button class=\"uk-button uk-button-default refreshbutton\" type=\"button\" ";
        $html .= "onclick=\"DataTables.resetSearch()\" uk-tooltip=\"Reset Search\">\n";
        $html .= "<span uk-icon=\"refresh\"></span>\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render page size selector dropdown or button group
     *
     * Creates a dropdown or button group allowing users to change how many records are displayed
     * per page. Includes all configured options and optionally an "All records" choice.
     *
     * @param bool $asButtonGroup Whether to render as button group instead of select
     * @return string HTML page size selector
     */
    protected function renderPageSizeSelector(bool $asButtonGroup = false): string
    {
        $options = $this->getPageSizeOptions();
        $includeAll = $this->getIncludeAllOption();
        $current = $this->getRecordsPerPage();

        if ($asButtonGroup) {
            $html = "<div>\n";
            $html .= "Per Page: <div class=\"uk-button-group\">\n";

            // Add each configured page size option as button
            foreach ($options as $option) {
                $activeClass = $option === $current ? ' uk-button-primary' : ' uk-button-default';
                $html .= "<a class=\"uk-button uk-button-small{$activeClass} datatables-page-size-btn\" ";
                $html .= "href=\"#\" data-size=\"{$option}\" onclick=\"DataTables.changePageSize({$option}, event)\">{$option}</a>\n";
            }

            // Add "All records" option if enabled
            if ($includeAll) {
                $activeClass = $current === 0 ? ' uk-button-primary' : ' uk-button-default';
                $html .= "<a class=\"uk-button uk-button-small{$activeClass} datatables-page-size-btn\" ";
                $html .= "href=\"#\" data-size=\"0\" onclick=\"DataTables.changePageSize(0, event)\">All</a>\n";
            }

            $html .= "</div>\n";
            $html .= "</div>\n";
        } else {
            $html = "<div>\n";
            $html .= "Per Page: <select class=\"uk-select uk-width-auto datatables-page-size\">\n";

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
        }

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
    protected function renderPagination(): string
    {

        // Pagination controls container (populated by JavaScript)
        $html = "<div>\n";
        $html .= "<div class=\"datatables-info\" id=\"datatables-info\">\n";
        $html .= "Showing 0 to 0 of 0 records\n";
        $html .= "</div>\n";
        $html .= "<ul class=\"uk-pagination datatables-pagination\" id=\"datatables-pagination\">\n";
        $html .= "<li class=\"uk-disabled\"><span uk-pagination-previous></span></li>\n";
        $html .= "<li class=\"uk-disabled\"><span uk-pagination-next></span></li>\n";
        $html .= "</ul>\n";
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
    protected function renderTable(): string
    {
        // Extract configuration for table rendering
        $columns = $this->getColumns();
        $sortableColumns = $this->getSortableColumns();
        $actionConfig = $this->getActionConfig();
        $bulkActions = $this->getBulkActions();
        $cssClasses = $this->getCssClasses();
        $tableSchema = $this->getTableSchema();

        // Get CSS classes with defaults
        $tableClass = $cssClasses['table'] ?? 'uk-table';
        $theadClass = $cssClasses['thead'] ?? '';
        $tbodyClass = $cssClasses['tbody'] ?? '';

        // Start table with scrollable container
        $html = "<div class=\"uk-overflow-auto\">\n";
        $html .= "<table class=\"{$tableClass} datatables-table\" data-columns='" . json_encode($tableSchema) . "'>\n";

        // === TABLE HEADER ===
        $html .= "<thead" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
        $html .= "<tr>\n";

        // Bulk selection master checkbox (if bulk actions enabled)
        if ($bulkActions['enabled']) {
            $html .= "<th class=\"uk-table-shrink\">\n";
            $html .= "<label><input type=\"checkbox\" class=\"uk-checkbox datatables-select-all\" onchange=\"DataTables.toggleSelectAll(this)\"></label>\n";
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
                $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                $html .= "<span class=\"sortable-header\">{$displayLabel} <span class=\"sort-icon\" uk-icon=\"triangle-up\"></span></span>";
            } else {
                // Non-sortable header
                $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                $html .= $displayLabel;
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
        $html .= "<tbody class=\"datatables-tbody" . (!empty($tbodyClass) ? " {$tbodyClass}" : "") . "\" id=\"datatables-tbody\">\n";

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
     * Render all modal dialogs for forms (auto-generated from schema)
     *
     * Creates the modal dialogs used for add, edit, and delete operations.
     * Each modal is initially hidden and shown by JavaScript when needed.
     * Forms are automatically generated based on database schema.
     *
     * @return string HTML for all modal dialogs
     */
    protected function renderModals(): string
    {
        $html = $this->renderAddModal();        // Add record form modal (auto-generated)
        $html .= $this->renderEditModal();      // Edit record form modal (auto-generated)
        $html .= $this->renderDeleteModal();    // Delete confirmation modal
        return $html;
    }

    /**
     * Render the add record modal form with custom fields
     *
     * @return string HTML add record modal
     */
    protected function renderAddModal(): string
    {
        $formConfig = $this->getAddFormConfig();
        $formFields = $formConfig['fields'];
        $title = $formConfig['title'];
        $formClass = $formConfig['class'] ?? '';

        // Modal container
        $html = "<div id=\"add-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">{$title}</h2>\n";

        // Form with AJAX submission
        $html .= "<form class=\"uk-form-stacked {$formClass}\" id=\"add-form\" onsubmit=\"return DataTables.submitAddForm(event)\">\n";

        // Generate form fields from configuration
        foreach ($formFields as $field => $config) {
            $html .= $this->renderFormField($field, $config, 'add');
        }

        // Modal action buttons
        $html .= "<div class=\"uk-width-1-1 uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-primary uk-margin-small-left\" type=\"submit\">Add Record</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render the edit record modal form with custom fields
     *
     * @return string HTML edit record modal
     */
    protected function renderEditModal(): string
    {
        $formConfig = $this->getEditFormConfig();
        $formFields = $formConfig['fields'];
        $title = $formConfig['title'];
        $primaryKey = $this->getPrimaryKey();
        $formClass = $formConfig['class'] ?? '';

        // Modal container
        $html = "<div id=\"edit-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">{$title}</h2>\n";

        // Form with AJAX submission
        $html .= "<form class=\"uk-form-stacked {$formClass}\" id=\"edit-form\" onsubmit=\"return DataTables.submitEditForm(event)\">\n";

        // Hidden field for record ID (populated by JavaScript)
        $html .= "<input type=\"hidden\" name=\"{$primaryKey}\" id=\"edit-{$primaryKey}\">\n";

        // Generate form fields from configuration
        foreach ($formFields as $field => $config) {
            $html .= $this->renderFormField($field, $config, 'edit');
        }

        // Modal action buttons
        $html .= "<div class=\"uk-width-1-1 uk-margin-top uk-text-right\">\n";
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
    protected function renderDeleteModal(): string
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
    protected function renderFormField(string $field, array $config, string $prefix = 'add'): string
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
        $default = $config['default'] ?? '';
        $disabled = $config['disabled'] ?? false;

        // Use default value if no value is set
        if (empty($value) && !empty($default)) {
            $value = $default;
        }

        // Generate unique IDs for form fields
        $fieldId = "{$prefix}-{$field}";
        $fieldName = $field;

        // Start field container
        $html = "<div class=\"{$customClass}\">\n";

        // Render field based on type
        switch ($type) {
            case 'hidden':
                // Hidden field - no label or container div needed
                return "<input type=\"hidden\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"{$value}\">\n";

            case 'boolean':
                // Boolean toggle field rendered as select for forms
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";


                $html .= "<div class=\"uk-form-controls\">\n";
                $attrString = $this->buildAttributeString($attributes);

                $html .= "<select class=\"uk-select\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "{$attrString} " . ($required ? "required" : "") .
                        ($disabled ? " disabled" : "") . ">\n";

                // Boolean options
                $selected0 = ($value == '0' || $value === false) ? ' selected' : '';
                $selected1 = ($value == '1' || $value === true) ? ' selected' : '';

                $html .= "<option value=\"0\"{$selected0}>Inactive</option>\n";
                $html .= "<option value=\"1\"{$selected1}>Active</option>\n";
                $html .= "</select>\n";
                $html .= "</div>\n";
                break;

            case 'checkbox':
                // Checkbox field for boolean values (no separate label div)
                $attrString = $this->buildAttributeString($attributes);

                $html .= "<div class=\"uk-form-controls\">\n";
                $html .= "<label>";
                $html .= "<input type=\"checkbox\" class=\"uk-checkbox\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}";
                if ($value == '1' || $value === true) {
                    $html .= " checked";
                }
                if ($disabled) {
                    $html .= " disabled";
                }
                $html .= "> {$label}";
                if ($required) {
                    $html .= " <span class=\"uk-text-danger\">*</span>";
                }
                $html .= "</label>\n";
                $html .= "</div>\n";
                break;

            case 'radio':
                // Radio button field for multiple choice values
                $html .= "<label class=\"uk-form-label\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";

                $attrString = $this->buildAttributeString($attributes);

                foreach ($options as $optValue => $optLabel) {
                    $checked = ($value == $optValue) ? ' checked' : '';
                    $disabledAttr = $disabled ? ' disabled' : '';

                    $html .= "<label class=\"uk-margin-small-right\">";
                    $html .= "<input type=\"radio\" class=\"uk-radio\" name=\"{$fieldName}\" value=\"{$optValue}\" {$attrString}{$checked}{$disabledAttr}";
                    if ($required) {
                        $html .= " required";
                    }
                    $html .= "> {$optLabel}";
                    $html .= "</label>\n";
                }
                $html .= "</div>\n";
                break;

            case 'textarea':
                // Multi-line text input for TEXT columns
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";

                $attrString = $this->buildAttributeString($attributes);

                $html .= "<textarea class=\"uk-textarea\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "placeholder=\"{$placeholder}\" {$attrString} " . ($required ? "required" : "") .
                        ($disabled ? " disabled" : "") . "></textarea>\n";
                $html .= "</div>\n";
                break;

            case 'select':
                // Dropdown selection for ENUM columns
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";

                $attrString = $this->buildAttributeString($attributes);

                $html .= "<select class=\"uk-select\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "{$attrString} " . ($required ? "required" : "") .
                        ($disabled ? " disabled" : "") . ">\n";

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

                $attrString = $this->buildAttributeString($attributes);

                $html .= "<input type=\"file\" class=\"uk-input\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "{$attrString} " . ($required ? "required" : "") .
                        ($disabled ? " disabled" : "") . ">\n";
                $html .= "</div>\n";
                break;

            default:
                // Standard input fields (text, email, number, date, datetime-local, time, etc.)
                $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" .
                        ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
                $html .= "<div class=\"uk-form-controls\">\n";

                $attrString = $this->buildAttributeString($attributes);

                $html .= "<input type=\"{$type}\" class=\"uk-input\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                        "placeholder=\"{$placeholder}\" value=\"{$value}\" {$attrString} " .
                        ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
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
    protected function buildAttributeString(array $attributes): string
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
    protected function renderInitScript(): string
    {
        // Extract configuration for JavaScript
        $tableName = $this->getTableName();
        $primaryKey = $this->getPrimaryKey();

        // If primary key is qualified, pass just the column name to JavaScript
        if (strpos($primaryKey, '.') !== false) {
            $jsPrimaryKey = explode('.', $primaryKey)[1];
        } else {
            $jsPrimaryKey = $primaryKey;
        }

        $inlineEditableColumns = json_encode($this->getInlineEditableColumns());
        $bulkActions = $this->getBulkActions();
        $actionConfig = $this->getActionConfig();
        $columns = $this->getColumns();

        // Generate initialization script
        $html = "<script>\n";
        $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
        $html .= "    window.DataTables = new DataTablesJS({\n";
        $html .= "        tableName: '{$tableName}',\n";
        $html .= "        primaryKey: '{$jsPrimaryKey}',\n";  // Use unqualified for JS
        $html .= "        inlineEditableColumns: {$inlineEditableColumns},\n";
        $html .= "        perPage: " . $this->getRecordsPerPage() . ",\n";
        $html .= "        bulkActionsEnabled: " . ($bulkActions['enabled'] ? 'true' : 'false') . ",\n";
        $html .= "        bulkActions: " . json_encode($bulkActions['actions']) . ",\n";
        $html .= "        actionConfig: " . json_encode($actionConfig) . ",\n";
        $html .= "        columns: " . json_encode($columns) . ",\n";
        $html .= "        cssClasses: " . json_encode($this->getCssClasses()) . "\n";
        $html .= "    });\n";
        $html .= "});\n";
        $html .= "</script>\n";

        return $html;
    }
}
