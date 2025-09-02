<?php

declare(strict_types=1);

namespace KPT\DataTables;

use KPT\Logger;
use Exception;
use InvalidArgumentException;

class AjaxHandler
{
    private DataTables $dataTable;

    public function __construct(DataTables $dataTable)
    {
        $this->dataTable = $dataTable;
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'fetch_data':
                $this->handleFetchData();
                break;
            case 'add_record':
                $this->handleAddRecord();
                break;
            case 'edit_record':
                $this->handleEditRecord();
                break;
            case 'delete_record':
                $this->handleDeleteRecord();
                break;
            case 'bulk_action':
                $this->handleBulkAction();
                break;
            case 'inline_edit':
                $this->handleInlineEdit();
                break;
            case 'upload_file':
                $this->handleFileUpload();
                break;
            default:
                throw new InvalidArgumentException("Unknown action: {$action}");
        }
    }

    private function handleFetchData(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? $this->dataTable->getRecordsPerPage());
        $search = $_GET['search'] ?? '';
        $searchColumn = $_GET['search_column'] ?? '';
        $sortColumn = $_GET['sort_column'] ?? '';
        $sortDirection = $_GET['sort_direction'] ?? 'ASC';

        // Build query
        $query = $this->buildSelectQuery($search, $searchColumn, $sortColumn, $sortDirection, $page, $perPage);
        $countQuery = $this->buildCountQuery($search, $searchColumn);

        // Execute queries
        $data = $this->dataTable->getDatabase()->raw($query['sql'], $query['params']);
        $total = $this->dataTable->getDatabase()->raw($countQuery['sql'], $countQuery['params']);

        $totalRecords = $total ? $total[0]->total : 0;

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data ?: [],
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage === 0 ? 1 : ceil($totalRecords / $perPage)
        ]);
        exit;
    }

    private function handleAddRecord(): void
    {
        $data = $_POST;
        unset($data['action']);

        // Handle file uploads
        $data = $this->processFileUploads($data);

        // Insert record
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $query = "INSERT INTO {$this->dataTable->getTableName()} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = $this->dataTable->getDatabase()->raw($query, array_values($data));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $result !== false ? 'Record added successfully' : 'Failed to add record',
            'id' => $result !== false ? $this->dataTable->getDatabase()->getLastId() : null
        ]);
        exit;
    }

    private function handleEditRecord(): void
    {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new InvalidArgumentException('Record ID is required');
        }

        $data = $_POST;
        unset($data['action'], $data['id']);

        // Handle file uploads
        $data = $this->processFileUploads($data);

        // Update record
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        
        $query = "UPDATE {$this->dataTable->getTableName()} SET {$setClause} WHERE {$this->dataTable->getPrimaryKey()} = ?";
        $params = array_merge(array_values($data), [$id]);
        
        $result = $this->dataTable->getDatabase()->raw($query, $params);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $result !== false ? 'Record updated successfully' : 'Failed to update record'
        ]);
        exit;
    }

    private function handleDeleteRecord(): void
    {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new InvalidArgumentException('Record ID is required');
        }

        $query = "DELETE FROM {$this->dataTable->getTableName()} WHERE {$this->dataTable->getPrimaryKey()} = ?";
        $result = $this->dataTable->getDatabase()->raw($query, [$id]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $result !== false ? 'Record deleted successfully' : 'Failed to delete record'
        ]);
        exit;
    }

    private function handleBulkAction(): void
    {
        $bulkAction = $_POST['bulk_action'] ?? null;
        $selectedIds = json_decode($_POST['selected_ids'] ?? '[]', true);

        if (!$bulkAction) {
            throw new InvalidArgumentException('Bulk action is required');
        }

        if (empty($selectedIds) || !is_array($selectedIds)) {
            throw new InvalidArgumentException('No records selected');
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
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $query = "DELETE FROM {$this->dataTable->getTableName()} WHERE {$this->dataTable->getPrimaryKey()} IN ({$placeholders})";
                $result = $this->dataTable->getDatabase()->raw($query, $selectedIds);
                $message = $result !== false ? 'Selected records deleted successfully' : 'Failed to delete selected records';
                break;
            
            default:
                // Handle custom bulk actions
                $actionConfig = $bulkActions['actions'][$bulkAction];
                if (isset($actionConfig['callback']) && is_callable($actionConfig['callback'])) {
                    $result = call_user_func($actionConfig['callback'], $selectedIds, $this->dataTable->getDatabase(), $this->dataTable->getTableName());
                    $message = $result ? ($actionConfig['success_message'] ?? 'Bulk action completed successfully') : 
                                      ($actionConfig['error_message'] ?? 'Bulk action failed');
                }
                break;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $message,
            'affected_count' => is_int($result) ? $result : count($selectedIds)
        ]);
        exit;
    }

    private function handleInlineEdit(): void
    {
        $id = $_POST['id'] ?? null;
        $field = $_POST['field'] ?? null;
        $value = $_POST['value'] ?? null;

        if (!$id || !$field) {
            throw new InvalidArgumentException('Record ID and field are required');
        }

        if (!in_array($field, $this->dataTable->getInlineEditableColumns())) {
            throw new InvalidArgumentException('Field is not inline editable');
        }

        $query = "UPDATE {$this->dataTable->getTableName()} SET {$field} = ? WHERE {$this->dataTable->getPrimaryKey()} = ?";
        $result = $this->dataTable->getDatabase()->raw($query, [$value, $id]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result !== false,
            'message' => $result !== false ? 'Field updated successfully' : 'Failed to update field'
        ]);
        exit;
    }

    private function handleFileUpload(): void
    {
        if (!isset($_FILES['file'])) {
            throw new InvalidArgumentException('No file uploaded');
        }

        $file = $_FILES['file'];
        $uploadResult = $this->uploadFile($file);

        header('Content-Type: application/json');
        echo json_encode($uploadResult);
        exit;
    }

    private function processFileUploads(array $data): array
    {
        foreach ($_FILES as $fieldName => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->uploadFile($file);
                if ($uploadResult['success']) {
                    $data[$fieldName] = $uploadResult['file_path'];
                }
            }
        }

        return $data;
    }

    private function uploadFile(array $file): array
    {
        $config = $this->dataTable->getFileUploadConfig();
        
        // Check file size
        if ($file['size'] > $config['max_file_size']) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size'
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $config['allowed_extensions'])) {
            return [
                'success' => false,
                'message' => 'File type not allowed'
            ];
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0755, true);
        }

        // Generate unique filename
        $fileName = uniqid() . '_' . $file['name'];
        $filePath = $config['upload_path'] . $fileName;

        // Move uploaded file
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

    private function buildSelectQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): array
    {
        // Build SELECT clause
        $selectFields = [];
        foreach ($this->dataTable->getColumns() as $column => $config) {
            if (is_string($config)) {
                $selectFields[] = $config;
            } else {
                $selectFields[] = $config['field'] ?? $column;
            }
        }

        $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$this->dataTable->getTableName()}";
        $params = [];

        // Add JOINs
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add WHERE clause for search
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                $sql .= " WHERE {$searchColumn} LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Search all columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $config) {
                    $field = is_string($config) ? $config : ($config['field'] ?? $column);
                    $searchConditions[] = "{$field} LIKE ?";
                    $params[] = "%{$search}%";
                }
                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        // Add ORDER BY clause
        if (!empty($sortColumn) && in_array($sortColumn, $this->dataTable->getSortableColumns())) {
            $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$sortColumn} {$direction}";
        }

        // Add LIMIT clause
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $sql .= " LIMIT {$offset}, {$perPage}";
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function buildCountQuery(string $search = '', string $searchColumn = ''): array
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->dataTable->getTableName()}";
        $params = [];

        // Add JOINs
        foreach ($this->dataTable->getJoins() as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Add WHERE clause for search
        if (!empty($search)) {
            if (!empty($searchColumn) && $searchColumn !== 'all') {
                $sql .= " WHERE {$searchColumn} LIKE ?";
                $params[] = "%{$search}%";
            } else {
                // Search all columns
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $column => $config) {
                    $field = is_string($config) ? $config : ($config['field'] ?? $column);
                    $searchConditions[] = "{$field} LIKE ?";
                    $params[] = "%{$search}%";
                }
                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }
        }

        return ['sql' => $sql, 'params' => $params];
    }
}