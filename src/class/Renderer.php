<?php

declare(strict_types=1);

namespace KPT\DataTables;

if (! class_exists('KPT\DataTables\Renderer', false)) {

    /**
     * Renderer - HTML Rendering Engine for DataTables
     *
     * @since   1.0.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package KPT\DataTables
     */
    class Renderer extends DataTablesBase
    {
        public function __construct(?DataTables $dataTable = null)
        {
        }

        protected function render(): string
        {
            $html = $this->renderContainer();
            $html .= $this->renderModals();
            $html .= $this->renderInitScript();
            return $html;
        }

        protected function renderContainer(): string
        {
            $tableName = $this->getTableName();
            $tm = $this->getThemeManager();
            $containerClass = "datatables-container-{$tableName}";
            $themeContainerClass = $tm->getClasses('container');

            $html = "<div class=\"{$containerClass} datatables-container {$themeContainerClass}\" data-table=\"{$tableName}\">\n";
            $html .= $this->renderTable();
            $html .= "</div>\n";

            return $html;
        }

        protected function renderBulkActions(array $bulkConfig): string
        {
            $tm = $this->getThemeManager();
            $gridClass = $tm->getClass('grid.small');

            $html = "<div class=\"{$gridClass}\"" . ($this->theme === 'uikit' ? ' uk-grid' : '') . ">\n";

            $html .= "      <div>\n";
            $html .= "          <a href=\"#\" class=\"" . $tm->getClasses('icon.link') . "\" ";
            $html .= "onclick=\"DataTables.showAddModal(event)\" ";
            $html .= ($this->theme === 'uikit' ? 'uk-icon="plus" uk-tooltip="Add a New Record"' : 'title="Add a New Record"') . ">";
            if ($this->theme !== 'uikit') {
                $html .= $tm->getIcon('plus');
            }
            $html .= "</a>\n";
            $html .= "      </div>\n";

            if (isset($bulkConfig['html'])) {
                if (is_array($bulkConfig['html']) && isset($bulkConfig['html']['location']) && isset($bulkConfig['html']['content'])) {
                    $location = $bulkConfig['html']['location'];
                    $content = $bulkConfig['html']['content'];
                    if ($location === 'before' || $location === 'both') {
                        $html .= "<div>\n{$content}\n</div>\n";
                    }
                } else {
                    $html .= "<div>\n{$bulkConfig['html']}\n</div>\n";
                }
            }

            $actionsToRender = [];

            if ($bulkConfig['enabled'] && !empty($bulkConfig['actions'])) {
                $actionsToRender = $bulkConfig['actions'];
            }

            $actionConfig = $this->getActionConfig();
            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $group) {
                    if (is_array($group)) {
                        foreach ($group as $actionKey => $actionData) {
                            if ($actionKey === 'delete' || (is_string($actionData) && $actionData === 'delete')) {
                                if (!isset($actionsToRender['delete'])) {
                                    $actionsToRender['delete'] = [
                                        'icon' => 'trash',
                                        'label' => 'Delete Selected',
                                        'confirm' => 'Are you sure you want to delete the selected records?'
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($actionsToRender)) {
                $actionCount = 0;
                $replaceDelete = array_key_exists('replacedelete', $actionsToRender);
                if ($replaceDelete) {
                    $actionsToRender = array_filter($actionsToRender, fn($key) => $key !== 'delete', ARRAY_FILTER_USE_KEY);
                }
                $totalActions = count($actionsToRender);
                foreach ($actionsToRender as $action => $config) {
                    // Handle html with location/content structure
                    if (isset($config['html'])) {
                        if (is_array($config['html']) && isset($config['html']['location']) && isset($config['html']['content'])) {
                            $location = $config['html']['location'];
                            $content = $config['html']['content'];
                            if ($location === 'before' || $location === 'both') {
                                $html .= "<div>\n{$content}\n</div>\n";
                            }
                            // For 'after' and 'both', we skip here and handle after the action buttons
                            if ($location !== 'after' && $location !== 'both') {
                                continue;
                            }
                        } else {
                            // Legacy string format
                            $content = $config['html'];
                            $html .= "<div>\n{$content}\n</div>\n";
                            continue;
                        }
                    }

                    $actionCount++;
                    $icon = $config['icon'] ?? 'link';
                    $label = $config['label'] ?? ucfirst($action);
                    $confirm = $config['confirm'] ?? '';

                    $html .= "<div>\n";
                    $html .= "<a class=\"" . $tm->getClasses('icon.link') . " datatables-bulk-action-btn\" ";
                    if ($this->theme === 'uikit') {
                        $html .= "uk-icon=\"{$icon}\" ";
                    }
                    $html .= "data-action=\"{$action}\" data-confirm=\"{$confirm}\" ";
                    $html .= "onclick=\"DataTables.executeBulkActionDirect('{$action}', event)\" ";
                    $html .= ($this->theme === 'uikit' ? "uk-tooltip=\"{$label}\"" : "title=\"{$label}\"") . " disabled>";
                    if ($this->theme !== 'uikit') {
                        $html .= $tm->getIcon($icon);
                    }
                    $html .= "</a>\n</div>\n";

                    // Handle 'after' and 'both' positioned html
                    if (isset($config['html']) && is_array($config['html']) && isset($config['html']['location']) && isset($config['html']['content'])) {
                        $location = $config['html']['location'];
                        $content = $config['html']['content'];
                        if ($location === 'after' || $location === 'both') {
                            $html .= "<div>\n{$content}\n</div>\n";
                        }
                    }
                }
            }

            // Handle bulk config html with 'after' or 'both' location
            if (isset($bulkConfig['html']) && is_array($bulkConfig['html']) && isset($bulkConfig['html']['location']) && isset($bulkConfig['html']['content'])) {
                $location = $bulkConfig['html']['location'];
                $content = $bulkConfig['html']['content'];
                if ($location === 'after' || $location === 'both') {
                    $html .= "<div>\n{$content}\n</div>\n";
                }
            }

            $html .= "</div>\n";
            return $html;
        }

        protected function renderSearchForm(): string
        {
            $tm = $this->getThemeManager();

            $html = "<div>\n";
            $html .= "<div class=\"" . $tm->getClasses('inline') . " " . $tm->getClass('width.medium') . "\">\n";

            if ($this->theme === 'uikit') {
                $html .= "<span class=\"uk-form-icon\" uk-icon=\"search\"></span>\n";
            } elseif ($this->theme === 'bootstrap') {
                $html .= "<span class=\"position-absolute\" style=\"left:12px;top:50%;transform:translateY(-50%);pointer-events:none;\"><i class=\"bi bi-search\"></i></span>\n";
            } else {
                $html .= "<span class=\"" . $tm->getClass('form.icon') . "\">" . $tm->getIcon('search') . "</span>\n";
            }

            $inputClass = $tm->getClasses('input');
            $paddingStyle = ($this->theme === 'bootstrap') ? ' style="padding-left:38px;"' : '';
            $html .= "<input class=\"{$inputClass} datatables-search\" type=\"text\" placeholder=\"Search...\"{$paddingStyle}>\n";
            $html .= "</div>\n</div>\n";

            $html .= "<div>\n";
            $buttonClass = $tm->getClass('button.default');
            $html .= "<button class=\"{$buttonClass} refreshbutton\" type=\"button\" onclick=\"DataTables.resetSearch()\" ";
            $html .= ($this->theme === 'uikit' ? 'uk-tooltip=\"Reset Search\"' : 'title=\"Reset Search\"') . ">\n";

            if ($this->theme === 'uikit') {
                $html .= "<span uk-icon=\"refresh\"></span>\n";
            } elseif ($this->theme === 'bootstrap') {
                $html .= "<i class=\"bi bi-arrow-clockwise\"></i>\n";
            } else {
                $html .= $tm->getIcon('refresh');
            }

            $html .= "</button>\n</div>\n";

            return $html;
        }

        protected function renderPageSizeSelector(bool $asButtonGroup = false): string
        {
            $tm = $this->getThemeManager();
            $options = $this->getPageSizeOptions();
            $includeAll = $this->getIncludeAllOption();
            $current = $this->getRecordsPerPage();

            if ($asButtonGroup) {
                $html = "<div>\nPer Page: <div class=\"" . $tm->getKpDtClass('button-group') . "\">\n";
                foreach ($options as $option) {
                    $activeClass = $option === $current ? $tm->getClass('button.primary') : $tm->getClass('button.default');
                    $activeClass .= ' ' . $tm->getClass('button.small');
                    $html .= "<a class=\"{$activeClass} datatables-page-size-btn\" href=\"#\" data-size=\"{$option}\" onclick=\"DataTables.changePageSize({$option}, event)\">{$option}</a>\n";
                }
                if ($includeAll) {
                    $activeClass = $current === 0 ? $tm->getClass('button.primary') : $tm->getClass('button.default');
                    $activeClass .= ' ' . $tm->getClass('button.small');
                    $html .= "<a class=\"{$activeClass} datatables-page-size-btn\" href=\"#\" data-size=\"0\" onclick=\"DataTables.changePageSize(0, event)\">All</a>\n";
                }
                $html .= "</div>\n</div>\n";
            } else {
                $selectClass = $tm->getClasses('select') . ' ' . $tm->getClass('width.auto');
                $html = "<div>\nPer Page: <select class=\"{$selectClass} datatables-page-size\">\n";
                foreach ($options as $option) {
                    $selected = $option === $current ? ' selected' : '';
                    $html .= "<option value=\"{$option}\"{$selected}>{$option} records</option>\n";
                }
                if ($includeAll) {
                    $html .= "<option value=\"0\">All records</option>\n";
                }
                $html .= "</select>\n</div>\n";
            }

            return $html;
        }

        protected function renderPagination(): string
        {
            $tm = $this->getThemeManager();
            $paginationClass = $tm->getClasses('pagination');

            $html = "<div>\n";
            $html .= "<div class=\"datatables-info\" id=\"datatables-info\">Showing 0 to 0 of 0 records</div>\n";
            $html .= "<ul class=\"{$paginationClass} datatables-pagination\" id=\"datatables-pagination\">\n";

            if ($this->theme === 'bootstrap') {
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&laquo;</span></li>\n";
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&raquo;</span></li>\n";
            } else {
                $disabledClass = $tm->getClass('pagination.disabled');
                $html .= "<li class=\"{$disabledClass}\"><span>" . ($this->theme === 'uikit' ? '<span uk-pagination-previous></span>' : '&laquo;') . "</span></li>\n";
                $html .= "<li class=\"{$disabledClass}\"><span>" . ($this->theme === 'uikit' ? '<span uk-pagination-next></span>' : '&raquo;') . "</span></li>\n";
            }

            $html .= "</ul>\n</div>\n";
            return $html;
        }

        protected function renderTableHeaderRow(): string
        {
            $tm = $this->getThemeManager();
            $columns = $this->getColumns();
            $sortableColumns = $this->getSortableColumns();
            $actionConfig = $this->getActionConfig();
            $bulkActions = $this->getBulkActions();
            $cssClasses = $this->getCssClasses();

            $html = "<tr>\n";

            if ($bulkActions['enabled']) {
                $shrinkClass = $tm->getClass('th.shrink');
                $checkboxClass = $tm->getClasses('checkbox');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . ">\n";
                $html .= "<label><input type=\"checkbox\" class=\"{$checkboxClass} datatables-select-all\" onchange=\"DataTables.toggleSelectAll(this)\"></label>\n";
                $html .= "</th>\n";
            }

            if ($actionConfig['position'] === 'start') {
                $shrinkClass = $tm->getClass('th.shrink');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . ">Actions</th>\n";
            }

            foreach ($columns as $column => $label) {
                $sortable = in_array($column, $sortableColumns);
                if (!$sortable && stripos($column, ' AS ') !== false) {
                    $parts = explode(' AS ', $column);
                    if (count($parts) === 2) {
                        $aliasName = trim($parts[1], '`\'" ');
                        $sortable = in_array($aliasName, $sortableColumns);
                    }
                }
                $columnClass = $cssClasses['columns'][$column] ?? '';
                $thClass = $columnClass . ($sortable ? ' sortable' : '');

                $html .= "<th" . (!empty($thClass) ? " class=\"{$thClass}\"" : "") .
                        ($sortable ? " data-sort=\"" . (stripos($column, ' AS ') !== false ? trim(explode(' AS ', $column)[1], '`\'" ') : $column) . "\"" : "") . ">";

                if ($sortable) {
                    $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                    $sortableHeaderClass = $tm->getKpDtClass('sortable-header');
                    $html .= "<span class=\"sortable-header {$sortableHeaderClass}\">{$displayLabel} ";
                    if ($this->theme === 'uikit') {
                        $html .= "<span class=\"sort-icon\" uk-icon=\"triangle-up\"></span>";
                    } else {
                        $html .= "<span class=\"sort-icon\">" . $tm->getIcon('triangle-up') . "</span>";
                    }
                    $html .= "</span>";
                } else {
                    $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                    $html .= $displayLabel;
                }

                $html .= "</th>\n";
            }

            if ($actionConfig['position'] === 'end') {
                $shrinkClass = $tm->getClass('th.shrink');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . ">Actions</th>\n";
            }

            $html .= "</tr>\n";
            return $html;
        }

        protected function renderTable(): string
        {
            $tm = $this->getThemeManager();
            $columns = $this->getColumns();
            $bulkActions = $this->getBulkActions();
            $cssClasses = $this->getCssClasses();
            $tableSchema = $this->getTableSchema();

            $tableClass = $cssClasses['table'] ?? $tm->getClass('table.full');
            $theadClass = $cssClasses['thead'] ?? '';
            $tbodyClass = $cssClasses['tbody'] ?? '';
            $themeTableClass = $tm->getKpDtClass('table');
            $overflowClass = $tm->getClasses('overflow.auto');

            $html = "<div class=\"{$overflowClass}\">\n";
            $html .= "<table class=\"{$tableClass} {$themeTableClass} datatables-table\" data-columns='" . json_encode($tableSchema) . "'>\n";

            $html .= "<thead" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
            $html .= $this->renderTableHeaderRow();
            $html .= "</thead>\n";

            $html .= "<tbody class=\"datatables-tbody" . (!empty($tbodyClass) ? " {$tbodyClass}" : "") . "\" id=\"datatables-tbody\">\n";

            $totalColumns = count($columns) + 1;
            if ($bulkActions['enabled']) {
                $totalColumns++;
            }

            $centerClass = $tm->getClass('text.center');
            $html .= "<tr><td colspan=\"{$totalColumns}\" class=\"{$centerClass}\">Loading...</td></tr>\n";
            $html .= "</tbody>\n";

            $html .= "<tfoot" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
            $html .= $this->renderTableHeaderRow();
            $html .= "</tfoot>\n";

            $html .= "</table>\n</div>\n";

            return $html;
        }

        protected function renderModals(): string
        {
            $html = $this->renderAddModal();
            $html .= $this->renderEditModal();
            $html .= $this->renderDeleteModal();
            return $html;
        }

        protected function renderAddModal(): string
        {
            $tm = $this->getThemeManager();
            $formConfig = $this->getAddFormConfig();
            $formFields = $formConfig['fields'];
            $title = $formConfig['title'];
            $formClass = $formConfig['class'] ?? '';

            if ($this->theme === 'bootstrap') {
                $html = "<div class=\"modal fade\" id=\"add-modal\" tabindex=\"-1\">\n<div class=\"modal-dialog\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">{$title}</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n";
            } else {
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $html = "<div id=\"add-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">{$title}</h2>\n";
            }

            $formStackedClass = $tm->getClass('form.stacked');
            $html .= "<form class=\"{$formStackedClass} {$formClass}\" id=\"add-form\" onsubmit=\"return DataTables.submitAddForm(event)\">\n";

            foreach ($formFields as $field => $config) {
                $html .= $this->renderFormField($field, $config, 'add');
            }

            $marginTopClass = $tm->getClass('margin.top');
            $textRightClass = $tm->getClass('text.right');
            $buttonDefaultClass = $tm->getClass('button.default');
            $buttonPrimaryClass = $tm->getClass('button.primary');
            $marginSmallLeftClass = $tm->getClass('margin.small.left');

            $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";

            if ($this->theme === 'bootstrap') {
                $html .= "<button class=\"{$buttonDefaultClass}\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
            } else {
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('add-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
            }

            $html .= "<button class=\"{$buttonPrimaryClass} {$marginSmallLeftClass}\" type=\"submit\">Add Record</button>\n";
            $html .= "</div>\n</form>\n";

            if ($this->theme === 'bootstrap') {
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                $html .= "</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        protected function renderEditModal(): string
        {
            $tm = $this->getThemeManager();
            $formConfig = $this->getEditFormConfig();
            $formFields = $formConfig['fields'];
            $title = $formConfig['title'];
            $primaryKey = $this->getPrimaryKey();
            $formClass = $formConfig['class'] ?? '';

            $unqualifiedPK = strpos($primaryKey, '.') !== false ? explode('.', $primaryKey)[1] : $primaryKey;

            if ($this->theme === 'bootstrap') {
                $html = "<div class=\"modal fade\" id=\"edit-modal\" tabindex=\"-1\">\n<div class=\"modal-dialog\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">{$title}</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n";
            } else {
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $html = "<div id=\"edit-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">{$title}</h2>\n";
            }

            $formStackedClass = $tm->getClass('form.stacked');
            $html .= "<form class=\"{$formStackedClass} {$formClass}\" id=\"edit-form\" onsubmit=\"return DataTables.submitEditForm(event)\">\n";
            $html .= "<input type=\"hidden\" name=\"{$unqualifiedPK}\" id=\"edit-{$unqualifiedPK}\">\n";

            foreach ($formFields as $field => $config) {
                $html .= $this->renderFormField($field, $config, 'edit');
            }

            $marginTopClass = $tm->getClass('margin.top');
            $textRightClass = $tm->getClass('text.right');
            $buttonDefaultClass = $tm->getClass('button.default');
            $buttonPrimaryClass = $tm->getClass('button.primary');
            $marginSmallLeftClass = $tm->getClass('margin.small.left');

            $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";

            if ($this->theme === 'bootstrap') {
                $html .= "<button class=\"{$buttonDefaultClass}\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
            } else {
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('edit-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
            }

            $html .= "<button class=\"{$buttonPrimaryClass} {$marginSmallLeftClass}\" type=\"submit\">Update Record</button>\n";
            $html .= "</div>\n</form>\n";

            if ($this->theme === 'bootstrap') {
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                $html .= "</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        protected function renderDeleteModal(): string
        {
            $tm = $this->getThemeManager();

            if ($this->theme === 'bootstrap') {
                $html = "<div class=\"modal fade\" id=\"delete-modal\" tabindex=\"-1\">\n";
                $html .= "<div class=\"modal-dialog modal-dialog-centered\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">Confirm Delete</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n</div>\n";
                $html .= "<div class=\"modal-footer\">\n";
                $html .= "<button class=\"btn btn-secondary\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
                $html .= "<button class=\"btn btn-danger\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $marginTopClass = $tm->getClass('margin.top');
                $textRightClass = $tm->getClass('text.right');
                $buttonDefaultClass = $tm->getClass('button.default');
                $buttonDangerClass = $tm->getClass('button.danger');
                $marginSmallLeftClass = $tm->getClass('margin.small.left');

                $html = "<div id=\"delete-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">Confirm Delete</h2>\n";
                $html .= "<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n";

                $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('delete-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
                $html .= "<button class=\"{$buttonDangerClass} {$marginSmallLeftClass}\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        protected function renderFormField(string $field, array $config, string $prefix = 'add'): string
        {
            $tm = $this->getThemeManager();

            $type = $config['type'];
            $label = ( $config['label'] ) ?? '';
            $required = $config['required'] ?? false;
            $placeholder = $config['placeholder'] ?? '';
            $options = $config['options'] ?? [];
            $customClass = $config['class'] ?? '';
            $attributes = $config['attributes'] ?? [];
            $value = $config['value'] ?? '';
            $default = $config['default'] ?? '';
            $disabled = $config['disabled'] ?? false;

            if (empty($value) && !empty($default)) {
                $value = $default;
            }

            $fieldId = "{$prefix}-{$field}";
            $fieldName = $field;

            $formControlsClass = $tm->getClass('form.controls');
            $formLabelClass = $tm->getClass('form.label');
            $inputClass = $tm->getClasses('input');
            $selectClass = $tm->getClasses('select');
            $textareaClass = $tm->getClasses('textarea');
            $checkboxClass = $tm->getClasses('checkbox');
            $radioClass = $tm->getClasses('radio');
            $dangerClass = $tm->getClass('text.danger');

            if ($type === 'hidden') {
                return "<input type=\"hidden\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"{$value}\">\n";
            }

            $html = "<div class=\"{$formControlsClass} {$customClass}\">\n";
            $attrString = $this->buildAttributeString($attributes);

            switch ($type) {
                case 'boolean':
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    $selected0 = ($value == '0' || $value === false) ? ' selected' : '';
                    $selected1 = ($value == '1' || $value === true) ? ' selected' : '';
                    $html .= "<option value=\"0\"{$selected0}>Inactive</option>\n<option value=\"1\"{$selected1}>Active</option>\n</select>\n";
                    break;

                case 'checkbox':
                    if ($this->theme === 'bootstrap') {
                        $html .= "<div class=\"form-check\">\n";
                        $html .= "<input type=\"checkbox\" class=\"form-check-input\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}" . (($value == '1' || $value === true) ? " checked" : "") . ($disabled ? " disabled" : "") . ">\n";
                        $html .= "<label class=\"form-check-label\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"text-danger\">*</span>" : "") . "</label>\n</div>\n";
                    } else {
                        $html .= "<label><input type=\"checkbox\" class=\"{$checkboxClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}" . (($value == '1' || $value === true) ? " checked" : "") . ($disabled ? " disabled" : "") . "> {$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    }
                    break;

                case 'radio':
                    $html .= "<label class=\"{$formLabelClass}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    foreach ($options as $optValue => $optLabel) {
                        $checked = ($value == $optValue) ? ' checked' : '';
                        $disabledAttr = $disabled ? ' disabled' : '';
                        if ($this->theme === 'bootstrap') {
                            $html .= "<div class=\"form-check\">\n<input type=\"radio\" class=\"form-check-input\" name=\"{$fieldName}\" id=\"{$fieldId}-{$optValue}\" value=\"{$optValue}\" {$attrString}{$checked}{$disabledAttr}" . ($required ? " required" : "") . ">\n";
                            $html .= "<label class=\"form-check-label\" for=\"{$fieldId}-{$optValue}\">{$optLabel}</label>\n</div>\n";
                        } else {
                            $marginClass = $tm->getClass('margin.small.right');
                            $html .= "<label class=\"{$marginClass}\"><input type=\"radio\" class=\"{$radioClass}\" name=\"{$fieldName}\" value=\"{$optValue}\" {$attrString}{$checked}{$disabledAttr}" . ($required ? " required" : "") . "> {$optLabel}</label>\n";
                        }
                    }
                    break;

                case 'textarea':
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<textarea class=\"{$textareaClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . "></textarea>\n";
                    break;

                case 'select':
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    if (!$required) {
                        $html .= "<option value=\"\">-- Select --</option>\n";
                    }
                    foreach ($options as $optValue => $optLabel) {
                        $selected = ($value == $optValue) ? ' selected' : '';
                        $html .= "<option value=\"{$optValue}\"{$selected}>{$optLabel}</option>\n";
                    }
                    $html .= "</select>\n";
                    break;

                case 'file':
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<input type=\"file\" class=\"{$inputClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    break;

                case 'image':
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    if (!empty($value)) {
                        $imageSrc = (strpos($value, 'http') === 0) ? $value : "/uploads/{$value}";
                        $html .= "<div class=\"mb-2\"><img src=\"{$imageSrc}\" alt=\"Current image\" style=\"max-width: 150px; max-height: 150px; object-fit: cover;\" class=\"rounded\"></div>\n";
                    }
                    $html .= "<input type=\"url\" class=\"{$inputClass} mb-2\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"Enter image URL or upload file below\" value=\"{$value}\" {$attrString} " . ($disabled ? " disabled" : "") . ">\n";
                    $html .= "<div class=\"mt-2\">\n<input type=\"file\" class=\"{$inputClass}\" id=\"{$fieldId}-file\" name=\"{$fieldName}-file\" accept=\"image/*\" {$attrString} " . ($disabled ? " disabled" : "") . ">\n";
                    $html .= "<small class=\"" . $tm->getClass('text.muted') . "\">Upload an image file or enter URL above</small>\n</div>\n";
                    break;

                default:
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $inputType = ($type === 'email') ? 'email' : 'text';
                    $html .= "<input type=\"{$inputType}\" class=\"{$inputClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" value=\"{$value}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    break;
            }

            $html .= "</div>\n";
            return $html;
        }

        protected function buildAttributeString(array $attributes): string
        {
            $attrParts = [];
            foreach ($attributes as $name => $value) {
                $attrParts[] = "{$name}=\"{$value}\"";
            }
            return implode(' ', $attrParts);
        }

        protected function renderInitScript(): string
        {
            $tableName = $this->getTableName();
            $primaryKey = $this->getPrimaryKey();
            $jsPrimaryKey = strpos($primaryKey, '.') !== false ? explode('.', $primaryKey)[1] : $primaryKey;
            $inlineEditableColumns = json_encode($this->getInlineEditableColumns());
            $bulkActions = $this->getBulkActions();
            $actionConfig = $this->getActionConfig();
            $columns = $this->getColumns();
            $defaultSortColumn = $this->getDefaultSortColumn();
            $defaultSortDirection = $this->getDefaultSortDirection();

            // Process action config for JavaScript - remove callbacks but KEEP html
            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $groupIndex => $group) {
                    if (is_array($group) && !empty($group)) {
                        foreach ($group as $actionKey => $actionData) {
                            // Remove callbacks (can't be serialized to JSON)
                            if (is_array($actionData) && isset($actionData['callback'])) {
                                unset($actionConfig['groups'][$groupIndex][$actionKey]['callback']);
                            }
                        }
                    }
                }
            }

            $html = "<script>\n";
            $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
            $html .= "    window.DataTables = new DataTablesJS({\n";
            $html .= "        tableName: '{$tableName}',\n";
            $html .= "        primaryKey: '{$jsPrimaryKey}',\n";
            $html .= "        inlineEditableColumns: {$inlineEditableColumns},\n";
            $html .= "        perPage: " . $this->getRecordsPerPage() . ",\n";
            $html .= "        bulkActionsEnabled: " . ($bulkActions['enabled'] ? 'true' : 'false') . ",\n";
            $html .= "        bulkActions: " . json_encode($bulkActions['actions']) . ",\n";
            $html .= "        actionConfig: " . json_encode($actionConfig) . ",\n";
            $html .= "        columns: " . json_encode($columns) . ",\n";
            $html .= "        cssClasses: " . json_encode($this->getCssClasses()) . ",\n";
            $html .= "        defaultSortColumn: '{$defaultSortColumn}',\n";
            $html .= "        defaultSortDirection: '{$defaultSortDirection}',\n";
            $html .= "        theme: '{$this->theme}'\n";
            $html .= "    });\n";
            $html .= "});\n";
            $html .= "</script>\n";

            return $html;
        }
    }

}
