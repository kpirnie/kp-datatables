<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Logger;
use Exception;
use InvalidArgumentException;

/**
 * AjaxHandler - Handles AJAX Requests for DataTables
 *
 * This class processes all AJAX requests for DataTables operations including
 * data fetching, CRUD operations, bulk actions, inline editing, and file uploads.
 * It acts as the main controller for server-side operations with enhanced security
 * and input sanitization. Always handled internally, never accessible from public files.
 *
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT\DataTables
 */
class AjaxHandler
{
    /**
     * DataTables instance containing configuration and database access
     *
     * @var DataTables
     */
    private DataTables $dataTable;

    /**
     * Constructor - Initialize the AJAX handler
     *
     * @param DataTables $dataTable The DataTables instance with configuration
     */
    public function __construct(DataTables $dataTable)
    {
        $this->dataTable = $dataTable;
    }

    /**
     * Main AJAX request dispatcher with enhanced security validation
     *
     * Routes incoming AJAX requests to the appropriate handler method based
     * on the action parameter. This is the main entry point for all AJAX operations.
     * Includes whitelist validation for security.
     *
     * @param  string $action The action to perform (fetch_data, add_record, edit_record, etc.)
     * @return void
     * @throws InvalidArgumentException If the action is unknown or invalid
     */
    public function handle(string $action): void
    {
        // Whitelist of allowed actions for security
        $allowedActions = [
            'fetch_data', 'add_record', 'edit_record', 'delete_record',
            'bulk_action', 'inline_edit', 'upload_file', 'fetch_record'
        ];

        if (!in_array($action, $allowedActions)) {
            throw new InvalidArgumentException("Invalid action: {$action}");
        }

        // Route the request to the appropriate handler method
        switch ($action) {
            case 'fetch_data':
                // Handle data retrieval for table display
                $this->handleFetchData();
                break;
            case 'fetch_record':
                // Handle single record fetch for editing
                $this->handleFetchRecord();
                break;
            case 'add_record':
                // Handle new record creation
                $this->handleAddRecord();
                break;
            case 'edit_record':
                // Handle existing record updates
                $this->handleEditRecord();
                break;
            case 'delete_record':
                // Handle single record deletion
                $this->handleDeleteRecord();
                break;
            case 'bulk_action':
                // Handle bulk operations on multiple records
                $this->handleBulkAction();
                break;
            case 'inline_edit':
                // Handle inline field editing
                $this->handleInlineEdit();
                break;
            case 'upload_file':
                // Handle standalone file uploads
                $this->handleFileUpload();
                break;
        }
    }

    /**
     * Handle standalone file upload requests
     *
     * Processes file uploads that are sent separately from form submissions.
     * Validates file type, size, and moves file to configured upload directory.
     *
     * @return void (outputs JSON and exits)
     * @throws InvalidArgumentException If no file is uploaded
     */
    private function handleFileUpload(): void
    {
        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            throw new InvalidArgumentException('No file uploaded');
        }

        // Process the uploaded file
        $file = $_FILES['file'];
        $uploadResult = $this->uploadFile($file);

        // Send JSON response with upload result
        header('Content-Type: application/json');
        echo json_encode($uploadResult);
        exit;
    }

    /**
     * Process file uploads in form data
     *
     * Scans $_FILES for uploaded files and processes them, updating the form data
     * with the file paths. Used during add/edit record operations.
     *
     * @param  array $data Form data to process
     * @return array Updated form data with file paths
     */
    private function processFileUploads(array $data): array
    {
        // Loop through all uploaded files
        foreach ($_FILES as $fieldName => $file) {
            // Only process files that were uploaded successfully
            if ($file['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->uploadFile($file);

                // Add file path to form data if upload was successful
                if ($uploadResult['success']) {
                    $data[$fieldName] = $uploadResult['file_path'];
                }
            }
        }

        return $data;
    }

    /**
     * Upload a single file with enhanced validation
     *
     * Handles the complete file upload process including validation of file size,
     * extension, directory creation, and file movement with security checks.
     *
     * @param  array $file File array from $_FILES
     * @return array Upload result with success status, file path, and message
     */
    private function uploadFile(array $file): array
    {
        // Get upload configuration
        $config = $this->dataTable->getFileUploadConfig();

        // Validate file size
        if ($file['size'] > $config['max_file_size']) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size'
            ];
        }

        // Extract and validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $config['allowed_extensions'])) {
            return [
                'success' => false,
                'message' => 'File type not allowed'
            ];
        }

        // Ensure upload directory exists
        if (!is_dir($config['upload_path'])) {
            // Create directory with appropriate permissions
            mkdir($config['upload_path'], 0755, true);
        }

        // Generate unique filename to prevent conflicts and directory traversal
        $fileName = uniqid() . '_' . basename($file['name']);
        $filePath = $config['upload_path'] . $fileName;

        // Attempt to move uploaded file to final destination
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'message' => 'File uploaded successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to move uploaded file'
            ];
        }
    }

    /**
     * Sanitize and validate form data array
     *
     * @param  array $data Raw form data
     * @return array Sanitized form data
     */
    private function sanitizeFormData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if ($key !== 'action') { // Skip action parameter
                $sanitized[$this->sanitizeInput($key)] = $this->sanitizeValue($value);
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize individual value based on type
     *
     * @param  mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeValue($value)
    {
        if (is_string($value)) {
            return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }
        return $value;
    }

    /**
     * Validate and sanitize search input
     *
     * @param  string $input Raw search input
     * @return string Sanitized search input
     */
    private function sanitizeSearchInput(string $input): string
    {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Sanitize column name input
     *
     * @param  string $column Raw column name
     * @return string Sanitized column name
     */
    private function sanitizeColumnName(string $column): string
    {
        return preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
    }

    /**
     * Sanitize sort direction input
     *
     * @param  string $direction Raw sort direction
     * @return string Valid sort direction (ASC or DESC)
     */
    private function sanitizeSortDirection(string $direction): string
    {
        return strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Sanitize general input string
     *
     * @param  string $input Raw input
     * @return string Sanitized input
     */
    private function sanitizeInput(string $input): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($input));
    }

    /**
     * Validate integer input with bounds
     *
     * @param  mixed $input Input to validate
     * @param  int   $min   Minimum allowed value
     * @param  int   $max   Maximum allowed value
     * @return int   Validated integer
     */
    private function validateInteger($input, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            return $min;
        }
        return $value;
    }

    /**
     * Validate array of IDs
     *
     * @param  string $jsonIds JSON string of IDs
     * @return array  Validated array of integer IDs
     */
    private function validateIdArray(string $jsonIds): array
    {
        $ids = json_decode($jsonIds, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });
    }

    /**
     * Validate field value against database schema
     *
     * @param  string $fieldName Field name
     * @param  mixed  $value     Value to validate
     * @param  array  $fieldInfo Schema information for field
     * @return mixed  Validated value
     */
    private function validateFieldValue(string $fieldName, $value, array $fieldInfo)
    {
        // Handle NULL values
        if ($value === null || $value === '') {
            if (!$fieldInfo['null']) {
                throw new InvalidArgumentException("Field {$fieldName} cannot be null");
            }
            return null;
        }

        // Type-specific validation based on detected field type
        $fieldType = $fieldInfo['type'];

        switch ($fieldType) {
            case 'number':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Field {$fieldName} must be numeric");
                }
                return is_float($value) ? (float)$value : (int)$value;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException("Field {$fieldName} must be a valid email");
                }
                return $value;

            case 'date':
                if (!$this->isValidDate($value, 'Y-m-d')) {
                    throw new InvalidArgumentException("Field {$fieldName} must be a valid date (Y-m-d)");
                }
                return $value;

            case 'datetime-local':
                if (!$this->isValidDate($value, 'Y-m-d\TH:i')) {
                    throw new InvalidArgumentException("Field {$fieldName} must be a valid datetime");
                }
                return $value;

            case 'checkbox':
                return $value ? 1 : 0;

            default:
                return $this->sanitizeValue($value);
        }
    }

    /**
     * Validate date format
     *
     * @param  string $date   Date string
     * @param  string $format Expected format
     * @return bool   True if valid date
     */
    private function isValidDate(string $date, string $format): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Build SELECT query with filtering, sorting, and pagination
     *
     * Constructs a complete SELECT query based on the DataTables configuration
     * and request parameters. Handles JOINs, WHERE conditions, ORDER BY, and LIMIT.
     * All inputs are sanitized and validated.
     *
     * @param  string $search        Search term to filter results
     * @param  string $searchColumn  Specific column to search (or 'all' for global search)
     * @param  string $sortColumn    Column to sort by
     * @param  string $sortDirection Sort direction (ASC or DESC)
     * @param  int    $page          Page number for pagination
     * @param  int    $perPage       Number of records per page (0 for all records)
     * @return array Array with 'sql' query string and 'params' array
     */
    private function buildSelectQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): array
    {
        // Build SELECT field list from column configuration
        $selectFields = [];
        $columns = $this->dataTable->getColumns();

        // If no columns configured, get all columns from schema
        if (empty($columns)) {
            $schema = $this->dataTable->getTableSchema();
            if (!empty($schema)) {
                foreach ($schema as $columnName => $info) {
                    $selectFields[] = "`{$columnName}`";
                }
            } else {
                // Last resort - select all
                $selectFields[] = "*";
            }
        } else {
            foreach ($columns as $column => $label) {
                $selectFields[] = "`{$column}`";
            }
        }

        // Start building the SQL query
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$this->dataTable->getTableName()}`";
        $params = [];

        // Add JOIN clauses from configuration
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add WHERE clause for search functionality
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                // Search specific column
                $sql .= " WHERE `{$searchColumn}` LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Global search across all columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $label) {
                    $searchConditions[] = "`{$column}` LIKE ?";
                    $params[] = "%{$search}%";
                }

                // Only add WHERE clause if we have searchable columns
                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        // Add ORDER BY clause for sorting
        if (!empty($sortColumn) && in_array($sortColumn, $this->dataTable->getSortableColumns())) {
            // Validate and normalize sort direction
            $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$sortColumn}` {$direction}";
        }

        // Add LIMIT clause for pagination
        if ($perPage > 0) {
            // Calculate offset for pagination
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT {$offset}, {$perPage}";
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Build COUNT query for pagination metadata
     *
     * Constructs a COUNT query to determine total number of records that match
     * the current search/filter criteria. Used for pagination calculations.
     *
     * @param  string $search       Search term to filter results
     * @param  string $searchColumn Specific column to search (or 'all' for global search)
     * @return array Array with 'sql' query string and 'params' array
     */
    private function buildCountQuery(string $search = '', string $searchColumn = ''): array
    {
        // Build basic COUNT query
        $sql = "SELECT COUNT(*) as total FROM `{$this->dataTable->getTableName()}`";
        $params = [];

        // Add same JOIN clauses as the main query
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add same WHERE conditions as the main query
        if (!empty($search)) {
            $columns = $this->dataTable->getColumns();

            // Use schema if no columns configured
            if (empty($columns)) {
                $schema = $this->dataTable->getTableSchema();
                $columns = array_keys($schema);
            } else {
                $columns = array_keys($columns);
            }

            if (!empty($searchColumn) && $searchColumn !== 'all') {
                // Search specific column
                $sql .= " WHERE `{$searchColumn}` LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Global search across all columns
                $searchConditions = [];
                foreach ($columns as $column) {
                    $searchConditions[] = "`{$column}` LIKE ?";
                    $params[] = "%{$search}%";
                }

                // Only add WHERE clause if we have searchable columns
                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Handle data fetching for table display with enhanced input sanitization
     *
     * Processes requests for table data including pagination, sorting, and searching.
     * Builds and executes SQL queries based on the request parameters and returns
     * JSON response with data and metadata. All inputs are sanitized and validated.
     *
     * @return void (outputs JSON and exits)
     */
    private function handleFetchData(): void
    {
        // Extract and validate pagination parameters with bounds checking
        $page = $this->validateInteger($_GET['page'] ?? 1, 1);
        $perPage = $this->validateInteger($_GET['per_page'] ?? $this->dataTable->getRecordsPerPage(), 0, 1000);

        // Sanitize search inputs with proper escaping
        $search = $this->sanitizeSearchInput($_GET['search'] ?? '');
        $searchColumn = $this->sanitizeColumnName($_GET['search_column'] ?? '');

        // Sanitize and validate sort inputs
        $sortColumn = $this->sanitizeColumnName($_GET['sort_column'] ?? '');
        $sortDirection = $this->sanitizeSortDirection($_GET['sort_direction'] ?? 'ASC');

        // Validate sort column exists in configuration
        if (!empty($sortColumn)) {
            $validColumns = array_keys($this->dataTable->getColumns());
            if (!in_array($sortColumn, $validColumns)) {
                $sortColumn = ''; // Reset invalid column
            }
        }

        // Execute data query using fluent interface
        $data = $this->executeDataQuery($search, $searchColumn, $sortColumn, $sortDirection, $page, $perPage);

        // Execute count query using fluent interface
        $total = $this->executeCountQuery($search, $searchColumn);

        // Extract total count from result
        $totalRecords = $total ? $total->total : 0;

        // Calculate total pages (handle division by zero for "all" records)
        $totalPages = $perPage === 0 ? 1 : ceil($totalRecords / $perPage);

        // Send JSON response with data and metadata
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data ?: [], // Ensure array even if no data
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]);

        // Make sure we exit so nothing else gets outputted
        exit;
    }

    /**
     * Execute data query with filtering, sorting, and pagination using fluent interface
     * FIXED to properly handle JOINs and qualified column names
     */
    private function executeDataQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): mixed
    {
        // Build SELECT fields list from configuration
        $selectFields = $this->getSelectFields();

        // Use full table name with alias for SELECT queries
        $tableName = $this->dataTable->getTableName();
        if (strpos($tableName, ' ') !== false) {
            // Has alias, use as-is
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$tableName}";
        } else {
            // No alias, add backticks
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$tableName}`";
        }

        // Add JOIN clauses from DataTables configuration - NO SANITIZATION
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Initialize parameters array for prepared statement
        $params = [];

        // Add WHERE clause for search functionality
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                // Search specific column only
                if (strpos($searchColumn, '.') !== false) {
                    $sql .= " WHERE {$searchColumn} LIKE ?";
                } else {
                    $sql .= " WHERE `{$searchColumn}` LIKE ?";
                }
                $params[] = "%{$search}%";
            } else {
                // Global search across all configured columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $label) {
                    if (strpos($column, '.') !== false) {
                        // Already qualified
                        $searchConditions[] = "{$column} LIKE ?";
                    } else {
                        // Unqualified, add backticks
                        $searchConditions[] = "`{$column}` LIKE ?";
                    }
                    $params[] = "%{$search}%";
                }

                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        // Add ORDER BY clause for sorting
        if (!empty($sortColumn) && in_array($sortColumn, $this->dataTable->getSortableColumns())) {
            $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

            if (strpos($sortColumn, '.') !== false) {
                $sql .= " ORDER BY {$sortColumn} {$direction}";
            } else {
                $sql .= " ORDER BY `{$sortColumn}` {$direction}";
            }
        }

        // Add LIMIT clause for pagination
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT {$offset}, {$perPage}";
        }

        // Execute query using Database fluent interface
        $query = $this->dataTable->getDatabase()->query($sql);

        if (!empty($params)) {
            $query->bind($params);
        }

        return $query->fetch();
    }

    /**
     * Execute count query for pagination metadata using fluent interface
     * FIXED to properly handle JOINs
     */
    private function executeCountQuery(string $search = '', string $searchColumn = ''): mixed
    {
        // Use full table name with alias for COUNT queries
        $tableName = $this->dataTable->getTableName();
        if (strpos($tableName, ' ') !== false) {
            // Has alias, use as-is
            $sql = "SELECT COUNT(*) as total FROM {$tableName}";
        } else {
            // No alias, add backticks
            $sql = "SELECT COUNT(*) as total FROM `{$tableName}`";
        }

        // Add same JOIN clauses as the main data query
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        $params = [];

        // Add WHERE clause for search (same logic as data query)
        if (!empty($search)) {
            $columns = $this->dataTable->getColumns();
            if (empty($columns)) {
                $schema = $this->dataTable->getTableSchema();
                $columns = array_keys($schema);
            } else {
                $columns = array_keys($columns);
            }

            if (!empty($searchColumn) && $searchColumn !== 'all') {
                if (strpos($searchColumn, '.') !== false) {
                    $sql .= " WHERE {$searchColumn} LIKE ?";
                } else {
                    $sql .= " WHERE `{$searchColumn}` LIKE ?";
                }
                $params[] = "%{$search}%";
            } else {
                $searchConditions = [];
                foreach ($columns as $column) {
                    if (strpos($column, '.') !== false) {
                        $searchConditions[] = "{$column} LIKE ?";
                    } else {
                        $searchConditions[] = "`{$column}` LIKE ?";
                    }
                    $params[] = "%{$search}%";
                }

                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        $query = $this->dataTable->getDatabase()->query($sql);

        if (!empty($params)) {
            $query->bind($params);
        }

        return $query->single()->fetch();
    }

    /**
     * Generate SELECT field list from DataTables configuration
     * FIXED to handle qualified column names with proper aliases
     */
    private function getSelectFields(): array
    {
        $selectFields = [];
        $columns = $this->dataTable->getColumns();

        if (empty($columns)) {
            $selectFields[] = "*";
        } else {
            foreach ($columns as $column => $label) {
                // Use the qualified column name AS the same qualified name
                // This preserves the exact key structure
                $selectFields[] = "{$column} AS `{$column}`";
            }
        }

        return $selectFields;
    }

    /**
     * Handle new record creation with schema validation
     * FIXED to use base table name for INSERT
     */
    private function handleAddRecord(): void
    {
        $data = $this->sanitizeFormData($_POST);
        $schema = $this->dataTable->getTableSchema();

        $validatedData = [];
        foreach ($data as $field => $value) {
            if (isset($schema[$field]) && $field !== $this->dataTable->getPrimaryKey()) {
                $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
            }
        }

        $validatedData = $this->processFileUploads($validatedData);

        if (empty($validatedData)) {
            throw new InvalidArgumentException('No valid data to insert');
        }

        $fields = array_keys($validatedData);
        $placeholders = array_fill(0, count($fields), '?');

        // Use BASE table name for INSERT (no alias)
        $query = "INSERT INTO `{$this->dataTable->getBaseTableName()}` (`" .
                implode('`, `', $fields) .
                "`) VALUES (" .
                implode(', ', $placeholders) .
                ")";

        $result = $this->dataTable->getDatabase()
                            ->query($query)
                            ->bind(array_values($validatedData))
                            ->execute();

        $success = $result !== false;
        $message = $success ? 'Record added successfully' : 'Failed to add record';
        $insertId = $success ? $this->dataTable->getDatabase()->getLastId() : null;

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'id' => $insertId
        ]);
        exit;
    }

    /**
     * Handle existing record updates with enhanced validation
     * FIXED to use base table name for UPDATE
     */
    private function handleEditRecord(): void
    {
        $unqualifiedPK = $this->getUnqualifiedPrimaryKey(); // Use unqualified PK
        $id = $this->validateInteger($_POST[$unqualifiedPK] ?? null);
        if (!$id) {
            throw new InvalidArgumentException('Valid record ID is required');
        }

        $data = $this->sanitizeFormData($_POST);
        unset($data[$unqualifiedPK]);

        $schema = $this->dataTable->getTableSchema();
        $validatedData = [];

        foreach ($data as $field => $value) {
            if (isset($schema[$field]) && $field !== $unqualifiedPK) {
                $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
            }
        }

        $validatedData = $this->processFileUploads($validatedData);

        if (empty($validatedData)) {
            throw new InvalidArgumentException('No valid data to update');
        }

        $fields = array_keys($validatedData);
        $setClause = implode(' = ?, ', array_map(function ($f) {
            return "`{$f}`";
        }, $fields)) . ' = ?';

        // Use BASE table name for UPDATE (no alias)
        $query = "UPDATE `{$this->dataTable->getBaseTableName()}` SET {$setClause} WHERE `{$unqualifiedPK}` = ?";

        $params = array_merge(array_values($validatedData), [$id]);

        $result = $this->dataTable->getDatabase()
                            ->query($query)
                            ->bind($params)
                            ->execute();

        $success = $result !== false;
        $message = $success ? 'Record updated successfully' : 'Failed to update record';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Handle single record deletion with ID validation
     * FIXED to use base table name for DELETE
     */
    private function handleDeleteRecord(): void
    {
        $id = $this->validateInteger($_POST['id'] ?? null);
        if (!$id) {
            throw new InvalidArgumentException('Valid record ID is required');
        }

        // Use BASE table name for DELETE
        $unqualifiedPK = $this->getUnqualifiedPrimaryKey(); // Use unqualified PK
        $query = "DELETE FROM `{$this->dataTable->getBaseTableName()}` WHERE `{$unqualifiedPK}` = ?";
        $result = $this->dataTable->getDatabase()
                                ->query($query)
                                ->bind([$id])
                                ->execute();

        $success = $result !== false && $result > 0;
        $message = $success ? 'Record deleted successfully' : ($result === 0 ? 'Record not found' : 'Failed to delete record');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'affected_rows' => $result
        ]);
        exit;
    }

    /**
     * Handle single record fetch for editing
     * FIXED to use base table name for SELECT
     */
    private function handleFetchRecord(): void
    {
        $id = $this->validateInteger($_GET['id'] ?? $_POST['id'] ?? null);
        if (!$id) {
            throw new InvalidArgumentException('Valid record ID is required');
        }

        // Use BASE table name for single record fetch (no alias needed)
        $unqualifiedPK = $this->getUnqualifiedPrimaryKey(); // Use unqualified PK
        $query = "SELECT * FROM `{$this->dataTable->getBaseTableName()}` WHERE `{$unqualifiedPK}` = ?";
        $result = $this->dataTable->getDatabase()
                                ->query($query)
                                ->bind([$id])
                                ->single()
                                ->fetch();

        $success = $result !== false;
        $message = $success ? 'Record fetched successfully' : 'Record not found';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $result ?: null
        ]);
        exit;
    }

    /**
     * Handle bulk actions on multiple records with enhanced security
     * FIXED to use base table name for bulk operations
     */
    private function handleBulkAction(): void
    {
        $bulkAction = $this->sanitizeInput($_POST['bulk_action'] ?? '');
        $selectedIds = $this->validateIdArray($_POST['selected_ids'] ?? '[]');

        if (empty($bulkAction) || empty($selectedIds)) {
            throw new InvalidArgumentException('Valid bulk action and selected IDs are required');
        }

        $bulkActions = $this->dataTable->getBulkActions();
        if (!$bulkActions['enabled']) {
            throw new InvalidArgumentException('Bulk actions are not enabled');
        }

        if (!isset($bulkActions['actions'][$bulkAction])) {
            throw new InvalidArgumentException("Unknown bulk action: {$bulkAction}");
        }

        $result = false;
        $message = '';

        switch ($bulkAction) {
            case 'delete':
                // Use BASE table name for bulk DELETE (no alias)
                $unqualifiedPK = $this->getUnqualifiedPrimaryKey(); // Use unqualified PK
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $query = "DELETE FROM `{$this->dataTable->getBaseTableName()}` WHERE `{$unqualifiedPK}` IN ({$placeholders})";
                $result = $this->dataTable->getDatabase()
                                        ->query($query)
                                        ->bind($selectedIds)
                                        ->execute();
                $message = $result !== false ? 'Selected records deleted successfully' : 'Failed to delete selected records';
                break;

            default:
                $actionConfig = $bulkActions['actions'][$bulkAction];
                if (isset($actionConfig['callback']) && is_callable($actionConfig['callback'])) {
                    $result = call_user_func(
                        $actionConfig['callback'],
                        $selectedIds,
                        $this->dataTable->getDatabase(),
                        $this->dataTable->getBaseTableName()  // Pass base table name
                    );
                    $message = $result ?
                        ($actionConfig['success_message'] ?? 'Bulk action completed successfully') :
                        ($actionConfig['error_message'] ?? 'Bulk action failed');
                }
                break;
        }

        $affectedCount = is_int($result) ? $result : count($selectedIds);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $message,
            'affected_count' => $affectedCount
        ]);
        exit;
    }

    /**
     * Handle inline field editing with enhanced validation
     * FIXED to use base table name for UPDATE
     */
    private function handleInlineEdit(): void
    {
        $id = $this->validateInteger($_POST['id'] ?? null);
        $field = trim($_POST['field'] ?? '');
        $value = $_POST['value'] ?? null;

        if (!$id || !$field) {
            throw new InvalidArgumentException('Record ID and field are required');
        }

        // Extract just the column name for validation (remove table prefix if present)
        $columnName = strpos($field, '.') !== false ? explode('.', $field)[1] : $field;

        $inlineEditableColumns = $this->dataTable->getInlineEditableColumns();

        if (!in_array($field, $inlineEditableColumns) && !in_array($columnName, $inlineEditableColumns)) {
            throw new InvalidArgumentException("Field '{$field}' is not inline editable. Configured fields: " . implode(', ', $inlineEditableColumns));
        }

        $schema = $this->dataTable->getTableSchema();
        if (isset($schema[$columnName])) {
            $value = $this->validateFieldValue($columnName, $value, $schema[$columnName]);
        }

        // Use BASE table name for UPDATE (no alias)
        $unqualifiedPK = $this->getUnqualifiedPrimaryKey(); // Use unqualified PK
        $query = "UPDATE `{$this->dataTable->getBaseTableName()}` SET `{$columnName}` = ? WHERE `{$unqualifiedPK}` = ?";
        $result = $this->dataTable->getDatabase()
                                ->query($query)
                                ->bind([$value, $id])
                                ->execute();

        $success = $result !== false;
        $message = $success ? 'Field updated successfully' : 'Failed to update field';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Get unqualified primary key column name for base table operations
     */
    private function getUnqualifiedPrimaryKey(): string
    {
        $primaryKey = $this->dataTable->getPrimaryKey();
        // If qualified (s.id), extract just the column name (id)
        if (strpos($primaryKey, '.') !== false) {
            return explode('.', $primaryKey)[1];
        }
        return $primaryKey;
    }
}
