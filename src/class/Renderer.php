<?php

declare(strict_types=1);

namespace KPT\DataTables;

class Renderer
{
    private DataTables $dataTable;

    public function __construct(DataTables $dataTable)
    {
        $this->dataTable = $dataTable;
    }

    public function render(): string
    {
        $html = $this->renderIncludes();
        $html .= $this->renderContainer();
        $html .= $this->renderModals();
        $html .= $this->renderInitScript();

        return $html;
    }

    private function renderIncludes(): string
    {
        $theme = $_GET['theme'] ?? $_COOKIE['datatables_theme'] ?? 'light';
        
        $html = "<!-- DataTables CSS -->\n";
        $html .= "<link rel=\"stylesheet\" href=\"vendor/kevinpirnie/kp-datatables/assets/css/datatables-{$theme}.css\">\n";
        $html .= "<link rel=\"stylesheet\" href=\"vendor/kevinpirnie/kp-datatables/assets/css/datatables-custom.css\">\n\n";
        
        $html .= "<!-- DataTables JavaScript -->\n";
        $html .= "<script src=\"vendor/kevinpirnie/kp-datatables/assets/js/datatables.js\"></script>\n\n";
        
        return $html;
    }

    private function renderContainer(): string
    {
        $tableName = $this->dataTable->getTableName();
        $containerClass = "datatables-container-{$tableName}";

        $html = "<div class=\"{$containerClass}\" data-table=\"{$tableName}\">\n";
        $html .= $this->renderControls();
        $html .= $this->renderTable();
        $html .= $this->renderPagination();
        $html .= "</div>\n";

        return $html;
    }

    private function renderControls(): string
    {
        $html = "<div class=\"uk-card uk-card-default uk-card-body uk-margin-bottom\">\n";
        $html .= "<div class=\"uk-grid-small uk-child-width-auto\" uk-grid>\n";

        // Add button
        if ($this->dataTable->getActionConfig()['show_edit']) {
            $html .= "<div>\n";
            $html .= "<button class=\"uk-button uk-button-primary\" type=\"button\" onclick=\"DataTables.showAddModal()\">\n";
            $html .= "<span uk-icon=\"plus\"></span> Add Record\n";
            $html .= "</button>\n";
            $html .= "</div>\n";
        }

        // Bulk actions
        $bulkActions = $this->dataTable->getBulkActions();
        if ($bulkActions['enabled']) {
            $html .= $this->renderBulkActions($bulkActions);
        }

        // Theme toggle
        $html .= "<div>\n";
        $html .= "<button class=\"uk-button uk-button-default\" type=\"button\" onclick=\"DataTables.toggleTheme()\">\n";
        $html .= "<span uk-icon=\"paint-bucket\"></span> Toggle Theme\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        // Search form
        if ($this->dataTable->isSearchEnabled()) {
            $html .= $this->renderSearchForm();
        }

        // Records per page selector
        $html .= $this->renderPageSizeSelector();

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderBulkActions(array $bulkConfig): string
    {
        $html = "<div>\n";
        $html .= "<select class=\"uk-select uk-width-auto\" id=\"datatables-bulk-action\" disabled>\n";
        $html .= "<option value=\"\">Bulk Actions</option>\n";

        foreach ($bulkConfig['actions'] as $action => $config) {
            $label = $config['label'] ?? ucfirst($action);
            $html .= "<option value=\"{$action}\">{$label}</option>\n";
        }

        $html .= "</select>\n";
        $html .= "<button class=\"uk-button uk-button-default uk-margin-small-left\" type=\"button\" " .
                 "id=\"datatables-bulk-execute\" onclick=\"DataTables.executeBulkAction()\" disabled>\n";
        $html .= "<span uk-icon=\"play\"></span> Execute\n";
        $html .= "</button>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderSearchForm(): string
    {
        $columns = $this->dataTable->getColumns();

        $html = "<div>\n";
        $html .= "<div class=\"uk-inline uk-width-medium\">\n";
        $html .= "<span class=\"uk-form-icon\" uk-icon=\"search\"></span>\n";
        $html .= "<input class=\"uk-input\" type=\"text\" placeholder=\"Search...\" id=\"datatables-search\">\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        $html .= "<div>\n";
        $html .= "<select class=\"uk-select uk-width-small\" id=\"datatables-search-column\">\n";
        $html .= "<option value=\"all\">All Columns</option>\n";

        foreach ($columns as $column => $config) {
            $label = is_string($config) ? $column : ($config['label'] ?? $column);
            $field = is_string($config) ? $config : ($config['field'] ?? $column);
            $html .= "<option value=\"{$field}\">{$label}</option>\n";
        }

        $html .= "</select>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderPageSizeSelector(): string
    {
        $options = $this->dataTable->getPageSizeOptions();
        $includeAll = $this->dataTable->getIncludeAllOption();
        $current = $this->dataTable->getRecordsPerPage();

        $html = "<div>\n";
        $html .= "<select class=\"uk-select uk-width-auto\" id=\"datatables-page-size\">\n";

        foreach ($options as $option) {
            $selected = $option === $current ? ' selected' : '';
            $html .= "<option value=\"{$option}\"{$selected}>{$option} records</option>\n";
        }

        if ($includeAll) {
            $html .= "<option value=\"0\">All records</option>\n";
        }

        $html .= "</select>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderTable(): string
    {
        $columns = $this->dataTable->getColumns();
        $sortableColumns = $this->dataTable->getSortableColumns();
        $actionConfig = $this->dataTable->getActionConfig();
        $bulkActions = $this->dataTable->getBulkActions();
        $cssClasses = $this->dataTable->getCssClasses();

        $tableClass = $cssClasses['table'] ?? 'uk-table uk-table-striped uk-table-hover';
        $theadClass = $cssClasses['thead'] ?? '';
        $tbodyClass = $cssClasses['tbody'] ?? '';

        $html = "<div class=\"uk-overflow-auto\">\n";
        $html .= "<table class=\"{$tableClass}\" id=\"datatables-table\">\n";

        // Table header
        $html .= "<thead" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
        $html .= "<tr>\n";

        // Bulk selection checkbox
        if ($bulkActions['enabled']) {
            $html .= "<th class=\"uk-table-shrink\">\n";
            $html .= "<label><input type=\"checkbox\" class=\"uk-checkbox\" id=\"select-all\" onchange=\"DataTables.toggleSelectAll(this)\"></label>\n";
            $html .= "</th>\n";
        }

        // Action column at start
        if ($actionConfig['position'] === 'start') {
            $html .= "<th class=\"uk-table-shrink\">Actions</th>\n";
        }

        // Regular columns
        foreach ($columns as $column => $config) {
            $label = is_string($config) ? $column : ($config['label'] ?? $column);
            $field = is_string($config) ? $config : ($config['field'] ?? $column);
            $columnClass = $cssClasses['columns'][$column] ?? '';
            
            $sortable = in_array($field, $sortableColumns);
            $thClass = $columnClass . ($sortable ? ' sortable' : '');
            
            $html .= "<th" . (!empty($thClass) ? " class=\"{$thClass}\"" : "") . 
                     ($sortable ? " data-sort=\"{$field}\"" : "") . ">";
            
            if ($sortable) {
                $html .= "<span class=\"sortable-header\">{$label} <span class=\"sort-icon\" uk-icon=\"triangle-up\"></span></span>";
            } else {
                $html .= $label;
            }
            
            $html .= "</th>\n";
        }

        // Action column at end
        if ($actionConfig['position'] === 'end') {
            $html .= "<th class=\"uk-table-shrink\">Actions</th>\n";
        }

        $html .= "</tr>\n";
        $html .= "</thead>\n";

        // Table body
        $html .= "<tbody" . (!empty($tbodyClass) ? " class=\"{$tbodyClass}\"" : "") . " id=\"datatables-tbody\">\n";
        $totalColumns = count($columns) + 1; // +1 for actions
        if ($bulkActions['enabled']) {
            $totalColumns++; // +1 for checkboxes
        }
        $html .= "<tr><td colspan=\"{$totalColumns}\" class=\"uk-text-center\">Loading...</td></tr>\n";
        $html .= "</tbody>\n";

        $html .= "</table>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderPagination(): string
    {
        $html = "<div class=\"uk-card uk-card-default uk-card-body uk-margin-top\">\n";
        $html .= "<div class=\"uk-flex uk-flex-between uk-flex-middle\">\n";

        // Records info
        $html .= "<div class=\"uk-text-meta\" id=\"datatables-info\">\n";
        $html .= "Showing 0 to 0 of 0 records\n";
        $html .= "</div>\n";

        // Pagination controls
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

    private function renderModals(): string
    {
        $html = $this->renderAddModal();
        $html .= $this->renderEditModal();
        $html .= $this->renderDeleteModal();
        return $html;
    }

    private function renderAddModal(): string
    {
        $config = $this->dataTable->getAddFormConfig();
        $title = $config['title'];

        $html = "<div id=\"add-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">{$title}</h2>\n";

        $html .= "<form class=\"uk-form-stacked\" id=\"add-form\"" . 
                 ($config['ajax'] ? " onsubmit=\"return DataTables.submitAddForm(event)\"" : "") . ">\n";

        // Render form fields
        if (!empty($config['fields'])) {
            foreach ($config['fields'] as $field => $fieldConfig) {
                $html .= $this->renderFormField($field, $fieldConfig);
            }
        }

        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-primary uk-margin-small-left\" type=\"submit\">Add Record</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderEditModal(): string
    {
        $config = $this->dataTable->getEditFormConfig();
        $title = $config['title'];
        $primaryKey = $this->dataTable->getPrimaryKey();

        $html = "<div id=\"edit-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">{$title}</h2>\n";

        $html .= "<form class=\"uk-form-stacked\" id=\"edit-form\"" . 
                 ($config['ajax'] ? " onsubmit=\"return DataTables.submitEditForm(event)\"" : "") . ">\n";

        $html .= "<input type=\"hidden\" name=\"{$primaryKey}\" id=\"edit-{$primaryKey}\">\n";

        // Render form fields
        if (!empty($config['fields'])) {
            foreach ($config['fields'] as $field => $fieldConfig) {
                $html .= $this->renderFormField($field, $fieldConfig, 'edit');
            }
        }

        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-primary uk-margin-small-left\" type=\"submit\">Update Record</button>\n";
        $html .= "</div>\n";

        $html .= "</form>\n";
        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderDeleteModal(): string
    {
        $html = "<div id=\"delete-modal\" uk-modal>\n";
        $html .= "<div class=\"uk-modal-dialog uk-modal-body\">\n";
        $html .= "<h2 class=\"uk-modal-title\">Confirm Delete</h2>\n";
        $html .= "<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n";

        $html .= "<div class=\"uk-margin-top uk-text-right\">\n";
        $html .= "<button class=\"uk-button uk-button-default uk-modal-close\" type=\"button\">Cancel</button>\n";
        $html .= "<button class=\"uk-button uk-button-danger uk-margin-small-left\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
        $html .= "</div>\n";

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function renderFormField(string $field, array $config, string $prefix = 'add'): string
    {
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? $field;
        $required = $config['required'] ?? false;
        $value = $config['value'] ?? '';
        $placeholder = $config['placeholder'] ?? '';
        $options = $config['options'] ?? [];
        $fieldClass = $config['class'] ?? '';
        $attributes = $config['attributes'] ?? [];

        $fieldId = "{$prefix}-{$field}";
        $fieldName = $field;

        $html = "<div class=\"uk-margin\">\n";
        $html .= "<label class=\"uk-form-label\" for=\"{$fieldId}\">{$label}" . 
                 ($required ? " <span class=\"uk-text-danger\">*</span>" : "") . "</label>\n";
        $html .= "<div class=\"uk-form-controls\">\n";

        $baseClass = "uk-input {$fieldClass}";
        $attrs = $this->buildAttributes($attributes);

        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'number':
            case 'password':
                $html .= "<input type=\"{$type}\" class=\"{$baseClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         "value=\"{$value}\" placeholder=\"{$placeholder}\" " .
                         ($required ? "required " : "") . $attrs . ">\n";
                break;

            case 'textarea':
                $html .= "<textarea class=\"uk-textarea {$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         "placeholder=\"{$placeholder}\" " . ($required ? "required " : "") . $attrs . ">{$value}</textarea>\n";
                break;

            case 'select':
                $html .= "<select class=\"uk-select {$fieldClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         ($required ? "required " : "") . $attrs . ">\n";
                if (!$required) {
                    $html .= "<option value=\"\">-- Select --</option>\n";
                }
                foreach ($options as $optValue => $optLabel) {
                    $selected = $optValue == $value ? ' selected' : '';
                    $html .= "<option value=\"{$optValue}\"{$selected}>{$optLabel}</option>\n";
                }
                $html .= "</select>\n";
                break;

            case 'checkbox':
                $checked = $value ? ' checked' : '';
                $html .= "<label><input type=\"checkbox\" class=\"uk-checkbox {$fieldClass}\" " .
                         "id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\"{$checked} {$attrs}> {$label}</label>\n";
                break;

            case 'radio':
                foreach ($options as $optValue => $optLabel) {
                    $checked = $optValue == $value ? ' checked' : '';
                    $html .= "<label class=\"uk-margin-small-right\"><input type=\"radio\" class=\"uk-radio {$fieldClass}\" " .
                             "name=\"{$fieldName}\" value=\"{$optValue}\"{$checked} {$attrs}> {$optLabel}</label>\n";
                }
                break;

            case 'file':
                $allowedExts = $this->dataTable->getFileUploadConfig()['allowed_extensions'];
                $accept = !empty($allowedExts) ? ' accept=".' . implode(',.' , $allowedExts) . '"' : '';
                $html .= "<div uk-form-custom=\"target: true\">\n";
                $html .= "<input type=\"file\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$accept} {$attrs}>\n";
                $html .= "<input class=\"uk-input {$fieldClass}\" type=\"text\" placeholder=\"Select file...\" disabled>\n";
                $html .= "</div>\n";
                break;

            case 'date':
                $html .= "<input type=\"date\" class=\"{$baseClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         "value=\"{$value}\" " . ($required ? "required " : "") . $attrs . ">\n";
                break;

            case 'datetime':
                $html .= "<input type=\"datetime-local\" class=\"{$baseClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         "value=\"{$value}\" " . ($required ? "required " : "") . $attrs . ">\n";
                break;

            case 'time':
                $html .= "<input type=\"time\" class=\"{$baseClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" " .
                         "value=\"{$value}\" " . ($required ? "required " : "") . $attrs . ">\n";
                break;

            case 'hidden':
                $html = "<input type=\"hidden\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"{$value}\" {$attrs}>\n";
                return $html; // No wrapping div for hidden fields
        }

        $html .= "</div>\n";
        $html .= "</div>\n";

        return $html;
    }

    private function buildAttributes(array $attributes): string
    {
        $attrs = [];
        foreach ($attributes as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attrs[] = $name;
                }
            } else {
                $attrs[] = "{$name}=\"{$value}\"";
            }
        }

        return implode(' ', $attrs);
    }

    private function renderInitScript(): string
    {
        $tableName = $this->dataTable->getTableName();
        $primaryKey = $this->dataTable->getPrimaryKey();
        $inlineEditableColumns = json_encode($this->dataTable->getInlineEditableColumns());
        $bulkActions = $this->dataTable->getBulkActions();
        $actionConfig = $this->dataTable->getActionConfig();
        $columns = $this->dataTable->getColumns();

        $html = "<script>\n";
        $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
        $html .= "    // Initialize DataTables\n";
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