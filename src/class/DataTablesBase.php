<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Database;

/**
 * DataTablesBase - Base class with shared properties and getters
 *
 * This abstract base class contains all the shared properties and getter methods
 * used by both DataTables and Renderer classes. It provides a centralized location
 * for all configuration data and access methods, preventing code duplication and
 * ensuring consistency across the inheritance hierarchy.
 *
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT\DataTables
 */
abstract class DataTablesBase
{
    /**
     * Database instance for all database operations
     *
     * @var Database|null
     */
    protected ?Database $db = null;

    /**
     * Database configuration array
     *
     * @var array
     */
    protected array $dbConfig = [];

    /**
     * Name of the primary database table
     *
     * @var string
     */
    protected string $tableName = '';

    /**
     * Column configuration array
     *
     * Format: ['column_name' => 'Display Label'] or ['column_name' => ['label' => 'Label', 'type' => 'email']]
     *
     * @var array
     */
    protected array $columns = [];

    /**
     * Table schema information loaded from database
     *
     * @var array
     */
    protected array $tableSchema = [];

    /**
     * JOIN configuration for complex queries
     *
     * Format: [['type' => 'LEFT', 'table' => 'table_name', 'condition' => 'join_condition']]
     *
     * @var array
     */
    protected array $joins = [];

    /**
     * List of columns that can be sorted
     *
     * @var array
     */
    protected array $sortableColumns = [];

    /**
     * List of columns that support inline editing
     *
     * @var array
     */
    protected array $inlineEditableColumns = [];

    /**
     * Number of records to display per page
     *
     * @var int
     */
    protected int $recordsPerPage = 25;

    /**
     * Available page size options for user selection
     *
     * @var array
     */
    protected array $pageSizeOptions = [25, 50, 100, 250];

    /**
     * Whether to include "ALL" option in page size selector
     *
     * @var bool
     */
    protected bool $includeAllOption = true;

    /**
     * Whether search functionality is enabled
     *
     * @var bool
     */
    protected bool $searchEnabled = true;

    /**
     * Configuration for action buttons (edit, delete, custom)
     *
     * @var array
     */
    protected array $actionConfig = [
        'position' => 'end',        // 'start' or 'end'
        'show_edit' => true,
        'show_delete' => true,
        'custom_actions' => []
    ];

    /**
     * Configuration for bulk actions functionality
     *
     * @var array
     */
    protected array $bulkActions = [
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

    /**
     * CSS class configuration for table elements
     *
     * @var array
     */
    protected array $cssClasses = [
        'table' => 'uk-table uk-table-striped uk-table-hover uk-margin-bottom',
        'thead' => '',
        'tbody' => '',
        'tfoot' => '',
        'tr' => '',              // Base class for rows (ID will be appended)
        'columns' => []          // Column-specific classes
    ];

    /**
     * File upload configuration and validation rules
     *
     * @var array
     */
    protected array $fileUploadConfig = [
        'upload_path' => 'uploads/',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'max_file_size' => 10485760    // 10MB in bytes
    ];

    /**
     * Add form configuration
     *
     * @var array
     */
    protected array $addFormConfig = [
        'title' => 'Add New Record',
        'fields' => [],
        'ajax' => true,
        'class' => '',
    ];

    /**
     * Edit form configuration
     *
     * @var array
     */
    protected array $editFormConfig = [
        'title' => 'Edit Record',
        'fields' => [],
        'ajax' => true,
        'class' => '',
    ];

    /**
     * Primary key column name for the table
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    // === GETTER METHODS FOR CONFIGURATION ACCESS ===
    // These methods provide read-only access to configuration for other classes

    /**
     * Get the database instance
     *
     * @return Database The configured database instance
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Get the table name
     *
     * @return string The configured table name
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the table schema information
     *
     * @return array The table schema array
     */
    public function getTableSchema(): array
    {
        return $this->tableSchema;
    }

    /**
     * Get the columns configuration
     *
     * @return array The columns configuration array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the JOIN configurations
     *
     * @return array Array of JOIN configurations
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * Get the sortable columns list
     *
     * @return array Array of sortable column names
     */
    public function getSortableColumns(): array
    {
        return $this->sortableColumns;
    }

    /**
     * Get the inline editable columns list
     *
     * @return array Array of inline editable column names
     */
    public function getInlineEditableColumns(): array
    {
        return $this->inlineEditableColumns;
    }

    /**
     * Get the records per page setting
     *
     * @return int Number of records per page
     */
    public function getRecordsPerPage(): int
    {
        return $this->recordsPerPage;
    }

    /**
     * Get the page size options
     *
     * @return array Array of available page size options
     */
    public function getPageSizeOptions(): array
    {
        return $this->pageSizeOptions;
    }

    /**
     * Get the include all option setting
     *
     * @return bool Whether "ALL" option should be included in page size selector
     */
    public function getIncludeAllOption(): bool
    {
        return $this->includeAllOption;
    }

    /**
     * Get the bulk actions configuration
     *
     * @return array Bulk actions configuration array
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * Check if search is enabled
     *
     * @return bool Whether search functionality is enabled
     */
    public function isSearchEnabled(): bool
    {
        return $this->searchEnabled;
    }

    /**
     * Get the action configuration
     *
     * @return array Action buttons configuration array
     */
    public function getActionConfig(): array
    {
        return $this->actionConfig;
    }

    /**
     * Get the CSS classes configuration
     *
     * @return array CSS classes configuration array
     */
    public function getCssClasses(): array
    {
        return $this->cssClasses;
    }

    /**
     * Get the file upload configuration
     *
     * @return array File upload configuration array
     */
    public function getFileUploadConfig(): array
    {
        return $this->fileUploadConfig;
    }

    /**
     * Get the primary key column name
     *
     * @return string The primary key column name
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the add form configuration
     *
     * @return array Add form configuration array
     */
    public function getAddFormConfig(): array
    {
        return $this->addFormConfig;
    }

    /**
     * Get the edit form configuration
     *
     * @return array Edit form configuration array
     */
    public function getEditFormConfig(): array
    {
        return $this->editFormConfig;
    }
}
