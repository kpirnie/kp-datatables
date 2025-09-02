<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Database;
use KPT\Logger;
use Exception;
use RuntimeException;

class DataTables
{
    private Database $db;
    private string $tableName = '';
    private array $columns = [];
    private array $joins = [];
    private array $sortableColumns = [];
    private array $inlineEditableColumns = [];
    private int $recordsPerPage = 25;
    private array $pageSizeOptions = [25, 50, 100, 250];
    private bool $includeAllOption = true;
    private array $addFormConfig = [
        'title' => 'Add Record',
        'fields' => [],
        'ajax' => true
    ];
    private array $editFormConfig = [
        'title' => 'Edit Record',
        'fields' => [],
        'ajax' => true
    ];
    private bool $searchEnabled = true;
    private array $actionConfig = [
        'position' => 'end',
        'show_edit' => true,
        'show_delete' => true,
        'custom_actions' => []
    ];
    private array $bulkActions = [
        'enabled' => false,
        'actions' => [
            'delete' => [
                'label' => 'Delete Selected',
                'icon' => 'trash',
                'class' => 'uk-button-danger',
                'confirm' => 'Are you sure you want to delete the selected records?'
            ]
        ]
    ];
    private array $cssClasses = [
        'table' => 'uk-table uk-table-striped uk-table-hover',
        'thead' => '',
        'tbody' => '',
        'tfoot' => '',
        'tr' => '',
        'columns' => []
    ];
    private array $fileUploadConfig = [
        'upload_path' => 'uploads/',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'max_file_size' => 10485760
    ];
    private string $primaryKey = 'id';

    public function __construct(Database $database)
    {
        $this->db = $database;
        Logger::debug("DataTables instance created successfully");
    }

    public function table(string $tableName): self
    {
        $this->tableName = $tableName;
        Logger::debug("DataTables table set", ['table' => $tableName]);
        return $this;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;
        Logger::debug("DataTables columns configured", ['column_count' => count($columns)]);
        return $this;
    }

    public function join(string $type, string $table, string $condition): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'condition' => $condition
        ];
        
        Logger::debug("DataTables JOIN added", [
            'type' => $type,
            'table' => $table,
            'condition' => $condition
        ]);
        
        return $this;
    }

    public function sortable(array $columns): self
    {
        $this->sortableColumns = $columns;
        Logger::debug("DataTables sortable columns set", ['columns' => $columns]);
        return $this;
    }

    public function inlineEditable(array $columns): self
    {
        $this->inlineEditableColumns = $columns;
        Logger::debug("DataTables inline editable columns set", ['columns' => $columns]);
        return $this;
    }

    public function perPage(int $count): self
    {
        $this->recordsPerPage = $count;
        Logger::debug("DataTables records per page set", ['count' => $count]);
        return $this;
    }

    public function pageSizeOptions(array $options, bool $includeAll = true): self
    {
        $this->pageSizeOptions = $options;
        $this->includeAllOption = $includeAll;
        
        Logger::debug("DataTables page size options set", [
            'options' => $options,
            'include_all' => $includeAll
        ]);
        
        return $this;
    }

    public function addForm(string $title, array $fields, bool $ajax = true): self
    {
        $this->addFormConfig = [
            'title' => $title,
            'fields' => $fields,
            'ajax' => $ajax
        ];
        
        Logger::debug("DataTables add form configured", ['title' => $title, 'ajax' => $ajax]);
        return $this;
    }

    public function editForm(string $title, array $fields, bool $ajax = true): self
    {
        $this->editFormConfig = [
            'title' => $title,
            'fields' => $fields,
            'ajax' => $ajax
        ];
        
        Logger::debug("DataTables edit form configured", ['title' => $title, 'ajax' => $ajax]);
        return $this;
    }

    public function bulkActions(bool $enabled = true, array $actions = []): self
    {
        $this->bulkActions['enabled'] = $enabled;
        
        if (!empty($actions)) {
            $this->bulkActions['actions'] = array_merge($this->bulkActions['actions'], $actions);
        }
        
        Logger::debug("DataTables bulk actions configured", [
            'enabled' => $enabled,
            'actions' => array_keys($this->bulkActions['actions'])
        ]);
        
        return $this;
    }

    public function search(bool $enabled = true): self
    {
        $this->searchEnabled = $enabled;
        Logger::debug("DataTables search configured", ['enabled' => $enabled]);
        return $this;
    }

    public function noSearch(): self
    {
        return $this->search(false);
    }

    public function actions(string $position = 'end', bool $showEdit = true, bool $showDelete = true, array $customActions = []): self
    {
        $this->actionConfig = [
            'position' => $position,
            'show_edit' => $showEdit,
            'show_delete' => $showDelete,
            'custom_actions' => $customActions
        ];
        
        Logger::debug("DataTables actions configured", $this->actionConfig);
        return $this;
    }

    public function tableClass(string $class): self
    {
        $this->cssClasses['table'] = $class;
        return $this;
    }

    public function rowClass(string $class): self
    {
        $this->cssClasses['tr'] = $class;
        return $this;
    }

    public function columnClasses(array $classes): self
    {
        $this->cssClasses['columns'] = $classes;
        return $this;
    }

    public function primaryKey(string $column): self
    {
        $this->primaryKey = $column;
        Logger::debug("DataTables primary key set", ['column' => $column]);
        return $this;
    }

    public function fileUpload(string $uploadPath = 'uploads/', array $allowedExtensions = [], int $maxFileSize = 10485760): self
    {
        $this->fileUploadConfig = [
            'upload_path' => rtrim($uploadPath, '/') . '/',
            'allowed_extensions' => !empty($allowedExtensions) ? $allowedExtensions : $this->fileUploadConfig['allowed_extensions'],
            'max_file_size' => $maxFileSize
        ];
        
        Logger::debug("DataTables file upload configured", $this->fileUploadConfig);
        return $this;
    }

    public function render(): string
    {
        try {
            if (empty($this->tableName)) {
                throw new RuntimeException('Table name must be set before rendering');
            }

            if (empty($this->columns)) {
                throw new RuntimeException('Columns must be configured before rendering');
            }

            $renderer = new Renderer($this);
            return $renderer->render();

        } catch (Exception $e) {
            Logger::error("DataTables render failed", ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function handleAjax(): void
    {
        try {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';
            Logger::debug("DataTables handling AJAX request", ['action' => $action]);

            $handler = new AjaxHandler($this);
            $handler->handle($action);

        } catch (Exception $e) {
            Logger::error("DataTables AJAX error", ['message' => $e->getMessage()]);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Getter methods
    public function getDatabase(): Database { return $this->db; }
    public function getTableName(): string { return $this->tableName; }
    public function getColumns(): array { return $this->columns; }
    public function getJoins(): array { return $this->joins; }
    public function getSortableColumns(): array { return $this->sortableColumns; }
    public function getInlineEditableColumns(): array { return $this->inlineEditableColumns; }
    public function getRecordsPerPage(): int { return $this->recordsPerPage; }
    public function getPageSizeOptions(): array { return $this->pageSizeOptions; }
    public function getIncludeAllOption(): bool { return $this->includeAllOption; }
    public function getAddFormConfig(): array { return $this->addFormConfig; }
    public function getEditFormConfig(): array { return $this->editFormConfig; }
    public function getBulkActions(): array { return $this->bulkActions; }
    public function isSearchEnabled(): bool { return $this->searchEnabled; }
    public function getActionConfig(): array { return $this->actionConfig; }
    public function getCssClasses(): array { return $this->cssClasses; }
    public function getFileUploadConfig(): array { return $this->fileUploadConfig; }
    public function getPrimaryKey(): string { return $this->primaryKey; }
}