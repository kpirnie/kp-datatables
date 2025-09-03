<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Database;
use KPT\Logger;
use Exception;
use RuntimeException;
use InvalidArgumentException;

/**
 * DataTables - Advanced Database Table Management System
 *
 * A comprehensive table management system with CRUD operations, search, sorting,
 * pagination, bulk actions, and modal forms using UIKit3. This is the main class
 * that orchestrates all DataTables functionality and provides a fluent interface
 * for configuration.
 *
 * Features:
 * - Full CRUD operations with AJAX support
 * - Advanced search and filtering
 * - Multi-column sorting
 * - Configurable pagination
 * - Bulk actions with custom callbacks
 * - Inline editing capabilities
 * - File upload handling
 * - Responsive design with theme support
 * - Database JOIN support
 * - Extensive customization options
 *
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT\DataTables
 */
class DataTables
{
    /**
     * Database instance for all database operations
     *
     * @var Database|null
     */
    private ?Database $db = null;

    /**
     * Database configuration array
     *
     * @var array
     */
    private array $dbConfig = [];

    /**
     * Name of the primary database table
     *
     * @var string
     */
    private string $tableName = '';

    /**
     * Column configuration array
     *
     * Format: ['column_name' => 'Display Label'] or ['column_name' => ['label' => 'Label', 'type' => 'email']]
     *
     * @var array
     */
    private array $columns = [];

    /**
     * Table schema information loaded from database
     *
     * @var array
     */
    private array $tableSchema = [];

    /**
     * JOIN configuration for complex queries
     *
     * Format: [['type' => 'LEFT', 'table' => 'table_name', 'condition' => 'join_condition']]
     *
     * @var array
     */
    private array $joins = [];

    /**
     * List of columns that can be sorted
     *
     * @var array
     */
    private array $sortableColumns = [];

    /**
     * List of columns that support inline editing
     *
     * @var array
     */
    private array $inlineEditableColumns = [];

    /**
     * Number of records to display per page
     *
     * @var int
     */
    private int $recordsPerPage = 25;

    /**
     * Available page size options for user selection
     *
     * @var array
     */
    private array $pageSizeOptions = [25, 50, 100, 250];

    /**
     * Whether to include "ALL" option in page size selector
     *
     * @var bool
     */
    private bool $includeAllOption = true;

    /**
     * Whether search functionality is enabled
     *
     * @var bool
     */
    private bool $searchEnabled = true;

    /**
     * Configuration for action buttons (edit, delete, custom)
     *
     * @var array
     */
    private array $actionConfig = [
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

    /**
     * CSS class configuration for table elements
     *
     * @var array
     */
    private array $cssClasses = [
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
    private array $fileUploadConfig = [
        'upload_path' => 'uploads/',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'max_file_size' => 10485760    // 10MB in bytes
    ];

    /**
     * Add form configuration
     *
     * @var array
     */
    private array $addFormConfig = [
        'title' => 'Add New Record',
        'fields' => [],
        'ajax' => true
    ];

    /**
     * Edit form configuration
     *
     * @var array
     */
    private array $editFormConfig = [
        'title' => 'Edit Record', 
        'fields' => [],
        'ajax' => true
    ];

    /**
     * Primary key column name for the table
     *
     * @var string
     */
    private string $primaryKey = 'id';

    /**
     * Constructor - Initialize DataTables with database configuration
     *
     * @param array $dbConfig Database configuration array (optional)
     */
    public function __construct(array $dbConfig = [])
    {
        if (! empty($dbConfig)) {
            $this -> dbConfig = $dbConfig;
            $this -> initializeDatabase();
        }

        Logger::debug("DataTables instance created successfully");
    }

    /**
     * Initialize database connection from configuration
     *
     * @return void
     */
    private function initializeDatabase(): void
    {
        if (!empty($this->dbConfig)) {
            try {
                $this->db = new Database((object)$this->dbConfig);
                Logger::debug("Database connection initialized successfully");
            } catch (Exception $e) {
                Logger::error("Failed to initialize database connection", ['error' => $e->getMessage()]);
                $this->db = null;
            }
        }
    }

    /**
     * Set database configuration and initialize connection
     *
     * @param  array $config Database configuration array
     * @return self Returns self for method chaining
     */
    public function database(array $config): self
    {
        $this->dbConfig = $config;
        $this->initializeDatabase();
        return $this;
    }

    /**
     * Set the primary database table name and auto-detect schema
     *
     * This method specifies which table will be used for all CRUD operations.
     * The table name will be used in all generated SQL queries. Also automatically
     * loads the table schema to generate forms and validate data.
     *
     * @param  string $tableName The name of the database table
     * @return self Returns self for method chaining
     * @throws RuntimeException If database connection is not available
     */
    public function table(string $tableName): self
    {
        $this->tableName = $this->sanitizeInput($tableName);

        // Only load schema if database is available
        if ($this->db) {
            try {
                $this->loadTableSchema();
            } catch (Exception $e) {
                Logger::error("Failed to load table schema", ['table' => $tableName, 'error' => $e->getMessage()]);
                // Continue without schema - basic functionality will still work
            }
        }

        Logger::debug("DataTables table set", ['table' => $tableName]);
        return $this;
    }

    /**
     * Load table schema from database and auto-detect field types
     *
     * @return void
     * @throws RuntimeException If database connection is not available
     */
    private function loadTableSchema(): void
    {
        if (!$this->db) {
            throw new RuntimeException('Database connection required before loading schema');
        }

        try {
            Logger::debug("Loading table schema", ['table' => $this->tableName]);

            // Get table structure using DESCRIBE with the fluent interface
            $schema = $this->db->query("DESCRIBE `{$this->tableName}`")->fetch();

            if (!$schema || empty($schema)) {
                throw new RuntimeException("Table '{$this->tableName}' does not exist or is not accessible");
            }

            Logger::debug("Schema query returned", ['column_count' => count($schema)]);

            $this->tableSchema = [];

            foreach ($schema as $column) {
                $this->tableSchema[$column->Field] = [
                    'type' => $this->parseColumnType($column->Type),
                    'null' => $column->Null === 'YES',
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra
                ];

                // Auto-detect primary key
                if ($column->Key === 'PRI') {
                    $this->primaryKey = $column->Field;
                }
            }

            // If no columns specified, use all non-primary key columns
            if (empty($this->columns)) {
                foreach ($this->tableSchema as $field => $info) {
                    if ($field !== $this->primaryKey) {
                        $this->columns[$field] = $this->generateColumnLabel($field);
                    }
                }
            }

            Logger::debug("Table schema loaded successfully", [
                'columns' => count($this->tableSchema),
                'primary_key' => $this->primaryKey
            ]);
        } catch (Exception $e) {
            Logger::error("Failed to load table schema", [
                'table' => $this->tableName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Parse MySQL column type to appropriate form field type with enhanced detection
     *
     * @param  string $columnType MySQL column type from DESCRIBE
     * @return string HTML form field type
     */
    private function parseColumnType(string $columnType): string
    {
        $type = strtolower($columnType);

        // Handle boolean/checkbox fields first (most specific)
        if (strpos($type, 'tinyint(1)') !== false || strpos($type, 'boolean') !== false || strpos($type, 'bit(1)') !== false) return 'boolean';
        
        // Handle other integer types
        if (strpos($type, 'int') !== false || strpos($type, 'integer') !== false) return 'number';
        
        // Handle decimal/float types
        if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) return 'number';
        
        // Handle date/time types
        if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) return 'datetime-local';
        if (strpos($type, 'date') !== false) return 'date';
        if (strpos($type, 'time') !== false) return 'time';
        
        // Handle text types
        if (strpos($type, 'text') !== false || strpos($type, 'longtext') !== false || strpos($type, 'mediumtext') !== false) return 'textarea';
        
        // Handle enum types
        if (strpos($type, 'enum') !== false) return 'select';
        
        // Handle varchar with common patterns
        if (strpos($type, 'varchar') !== false) {
            // Check for email patterns in column names or types
            if (strpos($type, 'email') !== false) return 'email';
            return 'text';
        }
        
        // Handle char types
        if (strpos($type, 'char') !== false) return 'text';

        return 'text'; // Default fallback
    }

    /**
     * Generate human-readable column label from database field name
     *
     * @param  string $field Database field name
     * @return string Human-readable label
     */
    private function generateColumnLabel(string $field): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }

    /**
     * Configure the columns to display in the table with enhanced field configuration
     *
     * Accepts column configurations where the array key is always the database column name.
     * Values can be simple display labels or detailed configuration arrays with type overrides,
     * form field options, CSS classes, and HTML attributes.
     *
     * Examples:
     * - Simple: ['name' => 'Full Name', 'email' => 'Email Address']
     * - Enhanced: ['active' => ['label' => 'Status', 'type' => 'checkbox', 'class' => 'uk-checkbox']]
     * - With options: ['status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]]
     *
     * @param  array $columns Array of column configurations
     * @return self Returns self for method chaining
     */
    public function columns(array $columns): self
    {
        $this->columns = [];
        foreach ($columns as $column => $config) {
            if (is_string($config)) {
                // Simple: 'column_name' => 'Display Label'
                $this->columns[$column] = $config;
            } else {
                // Enhanced: 'column_name' => ['label' => 'Label', 'type' => 'checkbox', etc.]
                $this->columns[$column] = $config['label'] ?? $this->generateColumnLabel($column);

                // Store enhanced configuration in schema if available
                if (isset($this->tableSchema[$column])) {
                    // Override type if specified
                    if (isset($config['type'])) {
                        $this->tableSchema[$column]['override_type'] = $config['type'];
                    }

                    // Store form field options
                    if (isset($config['options'])) {
                        $this->tableSchema[$column]['form_options'] = $config['options'];
                    }

                    // Store CSS classes for form field
                    if (isset($config['class'])) {
                        $this->tableSchema[$column]['form_class'] = $config['class'];
                    }

                    // Store HTML attributes for form field
                    if (isset($config['attributes'])) {
                        $this->tableSchema[$column]['form_attributes'] = $config['attributes'];
                    }

                    // Store placeholder text
                    if (isset($config['placeholder'])) {
                        $this->tableSchema[$column]['form_placeholder'] = $config['placeholder'];
                    }
                }
            }
        }
        Logger::debug("DataTables columns configured", ['column_count' => count($columns)]);
        return $this;
    }

    /**
     * Add a JOIN clause to the main query
     *
     * Allows for complex queries involving multiple tables. Each JOIN is stored
     * and will be applied to both data retrieval and count queries.
     *
     * @param  string $type      JOIN type (INNER, LEFT, RIGHT, FULL OUTER)
     * @param  string $table     Table name to join with (can include alias)
     * @param  string $condition JOIN condition (e.g., 'a.id = b.foreign_id')
     * @return self Returns self for method chaining
     */
    public function join(string $type, string $table, string $condition): self
    {
        // Store JOIN configuration for later query building
        $this->joins[] = [
            'type' => strtoupper($this->sanitizeInput($type)),    // Normalize JOIN type to uppercase
            'table' => $this->sanitizeInput($table),
            'condition' => $condition // Note: This needs careful validation in practice
        ];

        Logger::debug(
            "DataTables JOIN added",
            [
            'type' => $type,
            'table' => $table,
            'condition' => $condition
            ]
        );

        return $this;
    }

    /**
     * Define which columns can be sorted by users
     *
     * Only columns specified here will have clickable headers with sort indicators.
     * Column names should match the field names used in the database query.
     *
     * @param  array $columns Array of sortable column field names
     * @return self Returns self for method chaining
     */
    public function sortable(array $columns): self
    {
        $this->sortableColumns = array_map([$this, 'sanitizeInput'], $columns);
        Logger::debug("DataTables sortable columns set", ['columns' => $columns]);
        return $this;
    }

    /**
     * Define which columns support inline editing
     *
     * Columns specified here will be double-clickable for inline editing.
     * Only columns that are safe to edit should be included.
     *
     * @param  array $columns Array of inline editable column field names
     * @return self Returns self for method chaining
     */
    public function inlineEditable(array $columns): self
    {
        $this->inlineEditableColumns = array_map([$this, 'sanitizeInput'], $columns);
        Logger::debug("DataTables inline editable columns set", ['columns' => $columns]);
        return $this;
    }

    /**
     * Set the default number of records per page
     *
     * This sets the initial page size when the table loads. Users can still
     * change this using the page size selector if enabled.
     *
     * @param  int $count Number of records to show per page
     * @return self Returns self for method chaining
     */
    public function perPage(int $count): self
    {
        $this->recordsPerPage = max(1, $count);
        Logger::debug("DataTables records per page set", ['count' => $count]);
        return $this;
    }

    /**
     * Configure available page size options
     *
     * Sets the options available in the page size selector dropdown.
     * The includeAll parameter determines if an "All records" option is shown.
     *
     * @param  array $options    Array of page size options (e.g., [10, 25, 50, 100])
     * @param  bool  $includeAll Whether to include an "ALL" records option
     * @return self Returns self for method chaining
     */
    public function pageSizeOptions(array $options, bool $includeAll = true): self
    {
        $this->pageSizeOptions = array_map('intval', $options);
        $this->includeAllOption = $includeAll;

        Logger::debug(
            "DataTables page size options set",
            [
            'options' => $options,
            'include_all' => $includeAll
            ]
        );

        return $this;
    }

    /**
     * Enable or disable search functionality
     *
     * Controls whether the search input and column selector are displayed.
     * When enabled, provides both global and column-specific searching.
     *
     * @param  bool $enabled Whether search functionality should be enabled
     * @return self Returns self for method chaining
     */
    public function search(bool $enabled = true): self
    {
        $this->searchEnabled = $enabled;
        Logger::debug("DataTables search configured", ['enabled' => $enabled]);
        return $this;
    }

    /**
     * Configure bulk actions functionality
     *
     * Enables bulk operations on multiple selected records. Custom actions can
     * be defined with callback functions for complex operations.
     *
     * @param  bool  $enabled Whether to enable bulk actions
     * @param  array $actions Array of custom bulk action configurations
     * @return self Returns self for method chaining
     */
    public function bulkActions(bool $enabled = true, array $actions = []): self
    {
        $this->bulkActions['enabled'] = $enabled;

        // Merge custom actions with default actions
        if (!empty($actions)) {
            $this->bulkActions['actions'] = array_merge($this->bulkActions['actions'], $actions);
        }

        Logger::debug(
            "DataTables bulk actions configured",
            [
            'enabled' => $enabled,
            'actions' => array_keys($this->bulkActions['actions'])
            ]
        );

        return $this;
    }

    /**
     * Configure action buttons and their placement
     *
     * Controls the edit/delete buttons and any custom action buttons.
     * Actions can be positioned at the start or end of each table row.
     *
     * @param  string $position      Position of action column ('start' or 'end')
     * @param  bool   $showEdit      Whether to show the edit button
     * @param  bool   $showDelete    Whether to show the delete button
     * @param  array  $customActions Array of custom action button configurations
     * @return self Returns self for method chaining
     */
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

    /**
     * Set CSS class for the main table element
     *
     * Allows customization of the table's appearance using CSS classes.
     * Default uses UIKit3 table classes for styling.
     *
     * @param  string $class CSS class string for the table element
     * @return self Returns self for method chaining
     */
    public function tableClass(string $class): self
    {
        $this->cssClasses['table'] = $class;
        return $this;
    }

    /**
     * Set base CSS class for table rows
     *
     * This class will be combined with the record ID to create unique row classes.
     * For example, if $class is 'highlight' and record ID is 123, the final
     * class will be 'highlight-123'.
     *
     * @param  string $class Base CSS class for table rows
     * @return self Returns self for method chaining
     */
    public function rowClass(string $class): self
    {
        $this->cssClasses['tr'] = $class;
        return $this;
    }

    /**
     * Set CSS classes for specific columns
     *
     * Allows individual styling of table columns. The array keys should match
     * column names from the columns configuration.
     *
     * @param  array $classes Array of column name => CSS class mappings
     * @return self Returns self for method chaining
     */
    public function columnClasses(array $classes): self
    {
        $this->cssClasses['columns'] = $classes;
        return $this;
    }

    /**
     * Set the primary key column name
     *
     * Specifies which column serves as the unique identifier for records.
     * This is used for edit, delete, and bulk operations.
     *
     * @param  string $column Name of the primary key column
     * @return self Returns self for method chaining
     */
    public function primaryKey(string $column): self
    {
        $this->primaryKey = $this->sanitizeInput($column);
        Logger::debug("DataTables primary key set", ['column' => $column]);
        return $this;
    }

    /**
     * Configure file upload settings
     *
     * Sets up file upload validation including allowed file types, size limits,
     * and upload destination. Used for form fields with type 'file'.
     *
     * @param  string $uploadPath        Directory path where files will be uploaded
     * @param  array  $allowedExtensions Array of allowed file extensions (without dots)
     * @param  int    $maxFileSize       Maximum file size in bytes
     * @return self Returns self for method chaining
     */
    public function fileUpload(string $uploadPath = 'uploads/', array $allowedExtensions = [], int $maxFileSize = 10485760): self
    {
        $this->fileUploadConfig = [
            'upload_path' => rtrim($uploadPath, '/') . '/',    // Ensure trailing slash
            'allowed_extensions' => !empty($allowedExtensions) ? $allowedExtensions : $this->fileUploadConfig['allowed_extensions'],
            'max_file_size' => $maxFileSize
        ];

        Logger::debug("DataTables file upload configured", $this->fileUploadConfig);
        return $this;
    }

    /**
     * Sanitize input to prevent injection attacks
     *
     * @param  string $input Raw input string
     * @return string Sanitized string
     */
    private function sanitizeInput(string $input): string
    {
        // Remove any non-alphanumeric characters except underscore, dash, and dot
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($input));
    }

    /**
     * Render the complete DataTable HTML
     *
     * Generates all HTML, CSS includes, JavaScript includes, and initialization code
     * needed for a fully functional DataTable. This is the main method that produces
     * the final output.
     *
     * @return string Complete HTML output ready for display
     * @throws RuntimeException If required configuration is missing
     */
    public function render(): string
    {
        try {
            // Validate required configuration
            if (empty($this->tableName)) {
                throw new RuntimeException('Table name must be set before rendering');
            }

            if (!$this->db) {
                throw new RuntimeException('Database connection required before rendering');
            }

            // Create renderer and generate HTML
            $renderer = new Renderer($this);
            return $renderer->render();
        } catch (Exception $e) {
            Logger::error("DataTables render failed", ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle incoming AJAX requests
     *
     * Processes all AJAX requests for DataTables operations including data fetching,
     * CRUD operations, bulk actions, and file uploads. This method should be called
     * before any HTML output when AJAX requests are detected. Always handled internally.
     *
     * @return void (method outputs JSON and exits)
     */
    public function handleAjax(): void
    {
        try {
            // Extract and sanitize the action from POST or GET parameters
            $action = $this->sanitizeInput($_POST['action'] ?? $_GET['action'] ?? '');

            if (empty($action)) {
                throw new InvalidArgumentException('No action specified');
            }

            Logger::debug("DataTables handling AJAX request", ['action' => $action]);

            // Delegate to the AJAX handler
            $handler = new AjaxHandler($this);
            $handler->handle($action);
        } catch (Exception $e) {
            // Log the error and return error response
            Logger::error("DataTables AJAX error", ['message' => $e->getMessage()]);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Configure the add form with custom fields
     *
     * @param  string $title Form title
     * @param  array  $fields Field configurations
     * @param  bool   $ajax Whether form should use AJAX submission
     * @return self Returns self for method chaining
     */
    public function addForm(string $title, array $fields, bool $ajax = true): self
    {
        $this->addFormConfig = [
            'title' => $title,
            'fields' => $fields,
            'ajax' => $ajax
        ];
        
        Logger::debug("DataTables add form configured", ['field_count' => count($fields)]);
        return $this;
    }

    /**
     * Configure the edit form with custom fields
     *
     * @param  string $title Form title
     * @param  array  $fields Field configurations
     * @param  bool   $ajax Whether form should use AJAX submission
     * @return self Returns self for method chaining
     */
    public function editForm(string $title, array $fields, bool $ajax = true): self
    {
        $this->editFormConfig = [
            'title' => $title,
            'fields' => $fields,
            'ajax' => $ajax
        ];
        
        Logger::debug("DataTables edit form configured", ['field_count' => count($fields)]);
        return $this;
    }

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
