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
 * It acts as the main controller for server-side operations.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
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
     * Main AJAX request dispatcher
     *
     * Routes incoming AJAX requests to the appropriate handler method based
     * on the action parameter. This is the main entry point for all AJAX operations.
     *
     * @param string $action The action to perform (fetch_data, add_record, edit_record, etc.)
     * @return void
     * @throws InvalidArgumentException If the action is unknown or invalid
     */
    public function handle(string $action): void
    {
        // Route the request to the appropriate handler method
        switch ($action) {
            case 'fetch_data':
                // Handle data retrieval for table display
                $this->handleFetchData();
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
            default:
                // Unknown action - throw exception
                throw new InvalidArgumentException("Unknown action: {$action}");
        }
    }

    /**
     * Handle data fetching for table display
     *
     * Processes requests for table data including pagination, sorting, and searching.
     * Builds and executes SQL queries based on the request parameters and returns
     * JSON response with data and metadata.
     *
     * @return void (outputs JSON and exits)
     */
    private function handleFetchData(): void
    {
        // Extract and validate request parameters
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? $this->dataTable->getRecordsPerPage());
        $search = $_GET['search'] ?? '';
        $searchColumn = $_GET['search_column'] ?? '';
        $sortColumn = $_GET['sort_column'] ?? '';
        $sortDirection = $_GET['sort_direction'] ?? 'ASC';

        // Build the main SELECT query with all parameters
        $query = $this->buildSelectQuery($search, $searchColumn, $sortColumn, $sortDirection, $page, $perPage);
        
        // Build a separate COUNT query for pagination metadata
        $countQuery = $this->buildCountQuery($search, $searchColumn);

        // Execute both queries
        $data = $this->dataTable->getDatabase()->raw($query['sql'], $query['params']);
        $total = $this->dataTable->getDatabase()->raw($countQuery['sql'], $countQuery['params']);

        // Extract total count from result
        $totalRecords = $total ? $total[0]->total : 0;

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
        exit;
    }

    /**
     * Handle new record creation
     *
     * Processes POST data to create a new record in the database. Handles file uploads
     * if present and validates the data before insertion.
     *
     * @return void (outputs JSON and exits)
     */
    private function handleAddRecord(): void
    {
        // Get POST data and remove the action parameter
        $data = $_POST;
        unset($data['action']);

        // Process any file uploads in the request
        $data = $this->processFileUploads($data);

        // Prepare SQL INSERT statement
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?'); // Create ? placeholders for each field
        
        // Build the INSERT query
        $query = "INSERT INTO {$this->dataTable->getTableName()} (" . 
                 implode(', ', $fields) . 
                 ") VALUES (" . 
                 implode(', ', $placeholders) . 
                 ")";
        
        // Execute the query with the data values
        $result = $this->dataTable->getDatabase()->raw($query, array_values($data));

        // Prepare response
        $success = $result !== false;
        $message = $success ? 'Record added successfully' : 'Failed to add record';
        $insertId = $success ? $this->dataTable->getDatabase()->getLastId() : null;

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'id' => $insertId
        ]);
        exit;
    }

    /**
     * Handle existing record updates
     *
     * Processes POST data to update an existing record. Requires the record ID
     * and validates that it exists before updating.
     *
     * @return void (outputs JSON and exits)
     * @throws InvalidArgumentException If no record ID is provided
     */
    private function handleEditRecord(): void
    {
        // Extract and validate the record ID
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new InvalidArgumentException('Record ID is required');
        }

        // Get POST data and remove action/id parameters
        $data = $_POST;
        unset($data['action'], $data['id']);

        // Process any file uploads in the request
        $data = $this->processFileUploads($data);

        // Prepare SQL UPDATE statement
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?'; // Build SET clause
        
        // Build the UPDATE query
        $query = "UPDATE {$this->dataTable->getTableName()} SET {$setClause} WHERE {$this->dataTable->getPrimaryKey()} = ?";
        
        // Combine data values with the ID for parameters
        $params = array_merge(array_values($data), [$id]);
        
        // Execute the update query
        $result = $this->dataTable->getDatabase()->raw($query, $params);

        // Prepare response
        $success = $result !== false;
        $message = $success ? 'Record updated successfully' : 'Failed to update record';

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Handle single record deletion
     *
     * Deletes a single record from the database based on the provided ID.
     *
     * @return void (outputs JSON and exits)
     * @throws InvalidArgumentException If no record ID is provided
     */
    private function handleDeleteRecord(): void
    {
        // Extract and validate the record ID
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new InvalidArgumentException('Record ID is required');
        }

        // Build and execute DELETE query
        $query = "DELETE FROM {$this->dataTable->getTableName()} WHERE {$this->dataTable->getPrimaryKey()} = ?";
        $result = $this->dataTable->getDatabase()->raw($query, [$id]);

        // Prepare response
        $success = $result !== false;
        $message = $success ? 'Record deleted successfully' : 'Failed to delete record';

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    /**
     * Handle bulk actions on multiple records
     *
     * Processes bulk operations like delete, activate, etc. on multiple selected records.
     * Supports both built-in actions and custom callback functions.
     *
     * @return void (outputs JSON and exits)
     * @throws InvalidArgumentException If bulk action parameters are invalid
     */
    private function handleBulkAction(): void
    {
        // Extract bulk action parameters
        $bulkAction = $_POST['bulk_action'] ?? null;
        $selectedIds = json_decode($_POST['selected_ids'] ?? '[]', true);

        // Validate bulk action parameter
        if (!$bulkAction) {
            throw new InvalidArgumentException('Bulk action is required');
        }

        // Validate selected IDs
        if (empty($selectedIds) || !is_array($selectedIds)) {
            throw new InvalidArgumentException('No records selected');
        }

        // Check if bulk actions are enabled
        $bulkActions = $this->dataTable->getBulkActions();
        if (!$bulkActions['enabled']) {
            throw new InvalidArgumentException('Bulk actions are not enabled');
        }

        // Validate the specific action exists
        if (!isset($bulkActions['actions'][$bulkAction])) {
            throw new InvalidArgumentException("Unknown bulk action: {$bulkAction}");
        }

        // Initialize result variables
        $result = false;
        $message = '';

        // Process the bulk action
        switch ($bulkAction) {
            case 'delete':
                // Handle built-in bulk delete operation
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $query = "DELETE FROM {$this->dataTable->getTableName()} WHERE {$this->dataTable->getPrimaryKey()} IN ({$placeholders})";
                $result = $this->dataTable->getDatabase()->raw($query, $selectedIds);
                $message = $result !== false ? 'Selected records deleted successfully' : 'Failed to delete selected records';
                break;
            
            default:
                // Handle custom bulk actions with callbacks
                $actionConfig = $bulkActions['actions'][$bulkAction];
                
                // Check if a callback function is defined
                if (isset($actionConfig['callback']) && is_callable($actionConfig['callback'])) {
                    // Execute the custom callback with selected IDs, database, and table name
                    $result = call_user_func(
                        $actionConfig['callback'], 
                        $selectedIds, 
                        $this->dataTable->getDatabase(), 
                        $this->dataTable->getTableName()
                    );
                    
                    // Set appropriate success/error messages
                    $message = $result ? 
                        ($actionConfig['success_message'] ?? 'Bulk action completed successfully') : 
                        ($actionConfig['error_message'] ?? 'Bulk action failed');
                }
                break;
        }

        // Calculate affected count (use actual result if numeric, otherwise count of IDs)
        $affectedCount = is_int($result) ? $result : count($selectedIds);

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $message,
            'affected_count' => $affectedCount
        ]);
        exit;
    }

    /**
     * Handle inline field editing
     *
     * Updates a single field value for a specific record. Used for double-click
     * inline editing functionality.
     *
     * @return void (outputs JSON and exits)
     * @throws InvalidArgumentException If required parameters are missing or field is not editable
     */
    private function handleInlineEdit(): void
    {
        // Extract inline edit parameters
        $id = $_POST['id'] ?? null;
        $field = $_POST['field'] ?? null;
        $value = $_POST['value'] ?? null;

        // Validate required parameters
        if (!$id || !$field) {
            throw new InvalidArgumentException('Record ID and field are required');
        }

        // Check if the field is configured as inline editable
        if (!in_array($field, $this->dataTable->getInlineEditableColumns())) {
            throw new InvalidArgumentException('Field is not inline editable');
        }

        // Build and execute UPDATE query for single field
        $query = "UPDATE {$this->dataTable->getTableName()} SET {$field} = ? WHERE {$this->dataTable->getPrimaryKey()} = ?";
        $result = $this->dataTable->getDatabase()->raw($query, [$value, $id]);

        // Prepare response
        $success = $result !== false;
        $message = $success ? 'Field updated successfully' : 'Failed to update field';

        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
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
     * @param array $data Form data to process
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
     * Upload a single file with validation
     *
     * Handles the complete file upload process including validation of file size,
     * extension, directory creation, and file movement.
     *
     * @param array $file File array from $_FILES
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

        // Generate unique filename to prevent conflicts
        $fileName = uniqid() . '_' . $file['name'];
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
     * Build SELECT query with filtering, sorting, and pagination
     *
     * Constructs a complete SELECT query based on the DataTables configuration
     * and request parameters. Handles JOINs, WHERE conditions, ORDER BY, and LIMIT.
     *
     * @param string $search Search term to filter results
     * @param string $searchColumn Specific column to search (or 'all' for global search)
     * @param string $sortColumn Column to sort by
     * @param string $sortDirection Sort direction (ASC or DESC)
     * @param int $page Page number for pagination
     * @param int $perPage Number of records per page (0 for all records)
     * @return array Array with 'sql' query string and 'params' array
     */
    private function buildSelectQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): array
    {
        // Build SELECT field list from column configuration
        $selectFields = [];
        foreach ($this->dataTable->getColumns() as $column => $config) {
            if (is_string($config)) {
                // Simple column name
                $selectFields[] = $config;
            } else {
                // Complex column configuration with field mapping
                $selectFields[] = $config['field'] ?? $column;
            }
        }

        // Start building the SQL query
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$this->dataTable->getTableName()}";
        $params = [];

        // Add JOIN clauses from configuration
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add WHERE clause for search functionality
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                // Search specific column
                $sql .= " WHERE {$searchColumn} LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Global search across all columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $config) {
                    $field = is_string($config) ? $config : ($config['field'] ?? $column);
                    $searchConditions[] = "{$field} LIKE ?";
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
            $sql .= " ORDER BY {$sortColumn} {$direction}";
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
     * @param string $search Search term to filter results
     * @param string $searchColumn Specific column to search (or 'all' for global search)
     * @return array Array with 'sql' query string and 'params' array
     */
    private function buildCountQuery(string $search = '', string $searchColumn = ''): array
    {
        // Build basic COUNT query
        $sql = "SELECT COUNT(*) as total FROM {$this->dataTable->getTableName()}";
        $params = [];

        // Add same JOIN clauses as the main query
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add same WHERE conditions as the main query
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                // Search specific column
                $sql .= " WHERE {$searchColumn} LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Global search across all columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $config) {
                    $field = is_string($config) ? $config : ($config['field'] ?? $column);
                    $searchConditions[] = "{$field} LIKE ?";
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
}