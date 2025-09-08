<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Database;
use KPT\Logger;
use Exception;
use RuntimeException;
use InvalidArgumentException;

// Check if class already exists before declaring it
if (! class_exists('KPT\DataTables\DataTables', false)) {

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
    class DataTables extends Renderer
    {
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
            if (strpos($type, 'tinyint(1)') !== false || strpos($type, 'boolean') !== false || strpos($type, 'bit(1)') !== false) {
                return 'boolean';
            }

            // Handle other integer types
            if (strpos($type, 'int') !== false || strpos($type, 'integer') !== false) {
                return 'number';
            }

            // Handle decimal/float types
            if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
                return 'number';
            }

            // Handle date/time types
            if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                return 'datetime-local';
            }
            if (strpos($type, 'date') !== false) {
                return 'date';
            }
            if (strpos($type, 'time') !== false) {
                return 'time';
            }

            // Handle text types
            if (strpos($type, 'text') !== false || strpos($type, 'longtext') !== false || strpos($type, 'mediumtext') !== false) {
                return 'textarea';
            }

            // Handle enum types
            if (strpos($type, 'enum') !== false) {
                return 'select';
            }

            // Handle varchar with common patterns
            if (strpos($type, 'varchar') !== false) {
                // Check for email patterns in column names or types
                if (strpos($type, 'email') !== false) {
                    return 'email';
                }
                return 'text';
            }

            // Handle char types
            if (strpos($type, 'char') !== false) {
                return 'text';
            }

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
            // Check if there's an alias (space followed by alias)
            if (preg_match('/^([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)$/', trim($tableName), $matches)) {
                // Table has alias: store both base table name and full name with alias
                $this->baseTableName = $matches[1];  // Base table name for operations
                $this->tableName = trim($tableName);  // Full name with alias for SELECT queries
            } else {
                // No alias: sanitize and store
                $this->baseTableName = $this->sanitizeInput($tableName);
                $this->tableName = $this->baseTableName;
            }

            // Only load schema if database is available
            if ($this->db) {
                try {
                    // Use base table name for schema loading
                    $originalTableName = $this->tableName;
                    $this->tableName = $this->baseTableName;  // Temporarily set to base name
                    $this->loadTableSchema();
                    $this->tableName = $originalTableName;  // Restore full name with alias
                } catch (Exception $e) {
                    Logger::error("Failed to load table schema", ['table' => $tableName, 'error' => $e->getMessage()]);
                    // Continue without schema - basic functionality will still work
                }
            }

            Logger::debug("DataTables table set", ['table' => $tableName]);
            return $this;
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
            $this->joins[] = [
                'type' => strtoupper(trim($type)),    // Just normalize case and trim
                'table' => trim($table),              // DON'T sanitize - preserve spaces for aliases
                'condition' => trim($condition)       // DON'T sanitize - preserve column references
            ];

            Logger::debug("DataTables JOIN added", [
                'type' => $type,
                'table' => $table,
                'condition' => $condition
            ]);

            return $this;
        }

        /**
         * Add WHERE conditions to filter records
         *
         * @param  array $conditions Array of WHERE conditions
         * @return self Returns self for method chaining
         */
        public function where(array $conditions): self
        {
            $this->whereConditions = $conditions;
            Logger::debug("DataTables WHERE conditions set", ['conditions' => $conditions]);
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
         * Configure action groups with separators and custom actions
         *
         * @param  array $groups Array of action groups, each group is an array of actions
         * @return self Returns self for method chaining
         */
        public function actionGroups(array $groups): self
        {
            $this->actionConfig['groups'] = $groups;

            Logger::debug("DataTables action groups configured", ['group_count' => count($groups)]);
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
         * Render the complete DataTable HTML
         *
         * Generates all HTML, CSS includes, JavaScript includes, and initialization code
         * needed for a fully functional DataTable. This is the main method that produces
         * the final output.
         *
         * @return string Complete HTML output ready for display
         * @throws RuntimeException If required configuration is missing
         */
        public function renderDataTableComponent(): string
        {

            try {
                // Validate required configuration
                if (empty($this->tableName)) {
                    throw new RuntimeException('Table name must be set before rendering');
                }

                if (!$this->db) {
                    throw new RuntimeException('Database connection required before rendering');
                }

                // Generate HTML directly since we now extend Renderer
                $html = $this->renderContainer();      // Main table container
                $html .= $this->renderModals();         // Add/Edit/Delete modals (auto-generated)
                $html .= $this->renderInitScript();     // JavaScript initialization

                return $html;
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
        public function addForm(string $title, array $fields, bool $ajax = true, string $class = ''): self
        {
            $this->addFormConfig = [
                'title' => $title,
                'fields' => $fields,
                'ajax' => $ajax,
                'class' => $class
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
        public function editForm(string $title, array $fields, bool $ajax = true, string $class = ''): self
        {
            $this->editFormConfig = [
                'title' => $title,
                'fields' => $fields,
                'ajax' => $ajax,
                'class' => $class
            ];

            Logger::debug("DataTables edit form configured", ['field_count' => count($fields)]);
            return $this;
        }

        /**
         * Set default sort column and direction
         *
         * @param  string $column    Column name to sort by default
         * @param  string $direction Sort direction ('ASC' or 'DESC')
         * @return self Returns self for method chaining
         */
        public function defaultSort(string $column, string $direction = 'ASC'): self
        {
            $this->defaultSortColumn = $this->sanitizeInput($column);
            $this->defaultSortDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

            Logger::debug("DataTables default sort set", [
                'column' => $column,
                'direction' => $this->defaultSortDirection
            ]);

            return $this;
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
            $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/datatables.js\"></script>\n";
            return $html;
        }

        /**
         * Render bulk actions component
         *
         * @return string HTML bulk actions controls
         */
        public function renderBulkActionsComponent(): string
        {
            return $this->renderBulkActions($this->getBulkActions());
        }

        /**
         * Render search form component
         *
         * @return string HTML search form elements
         */
        public function renderSearchFormComponent(): string
        {
            return $this->renderSearchForm();
        }

        /**
         * Render page size selector component
         *
         * @return string HTML page size selector
         */
        public function renderPageSizeSelectorComponent(bool $asButtonGroup = false): string
        {
            return $this->renderPageSizeSelector($asButtonGroup);
        }

        /**
         * Render pagination component
         *
         * @return string HTML pagination section
         */
        public function renderPaginationComponent(): string
        {
            return $this->renderPagination();
        }
    }

}
