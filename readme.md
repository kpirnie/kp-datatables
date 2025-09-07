# KPT DataTables

Advanced PHP DataTables library with CRUD operations, search, sorting, pagination, bulk actions, JOIN support, and UIKit3 integration.

## Features

- 🚀 **Full CRUD Operations** - Create, Read, Update, Delete with AJAX support
- 🔗 **Advanced JOIN Support** - Complex database relationships with table aliases
- 🔍 **Advanced Search** - Search all columns or specific columns with qualified column names
- 📊 **Sorting** - Multi-column sorting with visual indicators on joined tables
- 📄 **Pagination** - Configurable page sizes with first/last navigation
- ✅ **Bulk Actions** - Select multiple records for bulk operations with custom callbacks
- ✏️ **Inline Editing** - Double-click to edit fields directly in the table
- 📁 **File Uploads** - Built-in file upload handling with validation
- 🎨 **Themes** - Light and dark UIKit3 themes with toggle
- 📱 **Responsive** - Mobile-friendly design
- 🎛️ **Customizable** - Extensive configuration options
- 🔧 **Chainable API** - Fluent interface for easy configuration

## Requirements

- PHP 8.1 or higher
- PDO extension
- JSON extension

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-datatables
```

## Dependencies

This package depends on:
- [`kevinpirnie/kpt-database`](https://packagist.org/packages/kevinpirnie/kpt-database) - Database wrapper
- [`kevinpirnie/kpt-logger`](https://packagist.org/packages/kevinpirnie/kpt-logger) - Logging functionality

## Quick Start

### 1. Basic Setup

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

// Configure database via constructor
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables($dbConfig);
```

### 2. Include Required Assets

```php
// Include JavaScript files
echo DataTables::getJsIncludes();
```

### 3. Handle AJAX Requests

```php
// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}
```

### 4. Simple Table

```php
// Configure and render table
echo $dataTable
    ->table('users')
    ->columns([
        'id' => 'ID',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'created_at' => 'Created'
    ])
    ->sortable(['name', 'email', 'created_at'])
    ->renderDataTableComponent();
```

## Advanced Usage with JOINs

### JOIN Tables with Aliases

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->table('kptv_stream_other s')  // Main table with alias
    ->primaryKey('s.id')            // Qualified primary key
    ->join('LEFT', 'kptv_stream_providers p', 's.p_id = p.id')  // JOIN with alias
    ->columns([
        's.id' => 'ID',             // Qualified column names
        's_orig_name' => 'Original Name',
        's_stream_uri' => 'Stream URI',
        'p.sp_name' => 'Provider',  // Column from joined table
    ])
    ->columnClasses([
        's.id' => 'uk-min-width',
        's_stream_uri' => 'txt-truncate'
    ])
    ->sortable(['s_orig_name', 'p.sp_name'])  // Sort on joined columns
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true)
    ->actionGroups([
        [
            'export' => [
                'icon' => 'download',
                'title' => 'Export Record',
                'class' => 'btn-export',
                'href' => '/export/{id}'
            ]
        ],
        ['delete']  // Built-in delete action
    ])
    ->renderDataTableComponent();
```

## Complete Configuration Example

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->table('users u')
    ->primaryKey('u.user_id')
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    ->join('LEFT', 'departments d', 'u.dept_id = d.dept_id')
    ->columns([
        'u.user_id' => 'ID',
        'u.name' => 'Name',
        'u.email' => 'Email',
        'r.role_name' => 'Role',
        'd.dept_name' => 'Department',
        'u.status' => [
            'label' => 'Status',
            'type' => 'boolean'
        ]
    ])
    
    // Configure sorting and editing
    ->sortable(['u.name', 'u.email', 'r.role_name', 'd.dept_name'])
    ->inlineEditable(['u.name', 'u.email', 'u.status'])
    
    // Pagination options
    ->perPage(25)
    ->pageSizeOptions([10, 25, 50, 100], true)
    
    // Enable bulk actions with custom callbacks
    ->bulkActions(true, [
        'activate' => [
            'label' => 'Activate Selected',
            'icon' => 'check',
            'class' => 'uk-button-secondary',
            'confirm' => 'Activate selected users?',
            'callback' => function($ids, $db, $table) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                return $db->query("UPDATE users SET status = 'active' WHERE user_id IN ({$placeholders})")
                          ->bind($ids)
                          ->execute();
            },
            'success_message' => 'Users activated successfully',
            'error_message' => 'Failed to activate users'
        ]
    ])
    
    // Configure action button groups
    ->actionGroups([
        [
            'email' => [
                'icon' => 'mail',
                'title' => 'Send Email',
                'class' => 'btn-email',
                'href' => '/email/{id}'
            ],
            'profile' => [
                'icon' => 'user',
                'title' => 'View Profile',
                'href' => '/profile/{id}'
            ]
        ],
        ['edit', 'delete']  // Built-in actions
    ])
    
    // Add form configuration
    ->addForm('Add New User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true,
            'placeholder' => 'Enter full name'
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true,
            'placeholder' => 'user@example.com'
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'avatar' => [
            'type' => 'file',
            'label' => 'Avatar Image'
        ],
        'status' => [
            'type' => 'boolean',
            'label' => 'Active Status',
            'value' => '1'
        ]
    ])
    
    // Edit form (similar to add form)
    ->editForm('Edit User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'status' => [
            'type' => 'boolean',
            'label' => 'Active Status'
        ]
    ])
    
    // CSS customization
    ->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
    ->rowClass('custom-row')
    ->columnClasses([
        'u.name' => 'uk-text-bold',
        'u.email' => 'uk-text-primary',
        'u.status' => 'uk-text-center'
    ])
    
    // File upload configuration
    ->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif'], 5242880) // 5MB limit
    
    ->renderDataTableComponent();
```

## Field Types

### Text Inputs
```php
'field_name' => [
    'type' => 'text', // text, email, url, tel, number, password
    'label' => 'Field Label',
    'required' => true,
    'placeholder' => 'Placeholder text',
    'class' => 'custom-css-class',
    'attributes' => ['maxlength' => '100']
]
```

### Enhanced Column Configuration
```php
->columns([
    'u.active' => [
        'label' => 'Status',
        'type' => 'boolean',
        'class' => 'uk-text-center'
    ],
    'u.category_id' => [
        'label' => 'Category',
        'type' => 'select',
        'options' => [
            '1' => 'Category 1',
            '2' => 'Category 2'
        ]
    ]
])
```

### Boolean/Checkbox Fields
```php
'active' => [
    'type' => 'boolean', // Renders as select in forms, toggle in table
    'label' => 'Active Status'
],
'newsletter' => [
    'type' => 'checkbox',
    'label' => 'Subscribe to Newsletter',
    'value' => '1'
]
```

### Select Dropdown
```php
'category' => [
    'type' => 'select',
    'label' => 'Category',
    'required' => true,
    'options' => [
        '1' => 'Category 1',
        '2' => 'Category 2',
        '3' => 'Category 3'
    ]
]
```

### File Upload
```php
'document' => [
    'type' => 'file',
    'label' => 'Upload Document'
]
```

## Bulk Actions

### Built-in Delete Action
```php
->bulkActions(true) // Enables default delete action
```

### Custom Bulk Actions
```php
->bulkActions(true, [
    'archive' => [
        'label' => 'Archive Selected',
        'icon' => 'archive',
        'class' => 'uk-button-secondary',
        'confirm' => 'Archive selected records?',
        'callback' => function($selectedIds, $database, $tableName) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            return $database->query("UPDATE {$tableName} SET archived = 1 WHERE id IN ({$placeholders})")
                           ->bind($selectedIds)
                           ->execute();
        },
        'success_message' => 'Records archived successfully',
        'error_message' => 'Failed to archive records'
    ]
])
```

## Action Button Groups

### Grouped Actions with Separators
```php
->actionGroups([
    ['edit', 'delete'], // Group 1: built-in actions
    [ // Group 2: custom actions
        'email' => [
            'icon' => 'mail',
            'title' => 'Send Email',
            'class' => 'btn-email',
            'href' => '/email/{id}'
        ],
        'export' => [
            'icon' => 'download',
            'title' => 'Export Data',
            'class' => 'btn-export',
            'href' => '/export/{id}'
        ]
    ]
])
```

## Database Joins

### Multiple JOINs with Complex Relationships
```php
$dataTable
    ->table('orders o')
    ->join('INNER', 'customers c', 'o.customer_id = c.customer_id')
    ->join('LEFT', 'order_status s', 'o.status_id = s.status_id')
    ->join('LEFT', 'shipping_addresses sa', 'o.order_id = sa.order_id')
    ->columns([
        'o.order_id' => 'Order ID',
        'c.customer_name' => 'Customer',
        'o.order_date' => 'Date',
        's.status_name' => 'Status',
        'o.total' => 'Total',
        'sa.city' => 'Ship To City'
    ]);
```

## WHERE Conditions

### Filter Records with Custom Conditions
```php
->where([
    'AND' => [
        [
            'field' => 'status',
            'comparison' => '=',
            'value' => 'active'
        ],
        [
            'field' => 'created_at',
            'comparison' => '>=',
            'value' => '2024-01-01'
        ]
    ]
])
```

### Multiple Condition Groups
```php
->where([
    'AND' => [
        [
            'field' => 'department_id',
            'comparison' => 'IN',
            'value' => [1, 2, 3]
        ]
    ],
    'OR' => [
        [
            'field' => 'priority',
            'comparison' => '=',
            'value' => 'high'
        ],
        [
            'field' => 'urgent',
            'comparison' => '=',
            'value' => 1
        ]
    ]
])
```

### Supported Comparison Operators
- `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`
- `LIKE`, `NOT LIKE`
- `IN`, `NOT IN` (with array values)
- `REGEXP`

---

## Action Groups with Callbacks

Add this section within the existing "Action Button Groups" section, after the "Grouped Actions with Separators" example:

### Actions with Callbacks
```php
->actionGroups([
    [
        'activate' => [
            'icon' => 'check',
            'title' => 'Activate User',
            'class' => 'btn-activate',
            'confirm' => 'Activate this user?',
            'callback' => function($rowId, $rowData, $database, $tableName) {
                // Custom logic with full row data access
                $email = $rowData['email'] ?? '';
                $name = $rowData['name'] ?? '';
                
                // Update database
                $result = $database->query("UPDATE {$tableName} SET status = 'active', activated_at = NOW() WHERE id = ?")
                                  ->bind([$rowId])
                                  ->execute();
                
                // Send notification email (example)
                if ($result) {
                    mail($email, 'Account Activated', "Hello {$name}, your account has been activated.");
                }
                
                return $result !== false;
            },
            'success_message' => 'User activated and notified',
            'error_message' => 'Failed to activate user'
        ],
        'export' => [
            'icon' => 'download',
            'title' => 'Export Data',
            'class' => 'btn-export',
            'callback' => function($rowId, $rowData, $database, $tableName) {
                // Generate export file
                $filename = "user_{$rowId}_" . date('Y-m-d') . ".csv";
                $filepath = "exports/{$filename}";
                
                // Create CSV content
                $csv = fopen($filepath, 'w');
                fputcsv($csv, array_keys($rowData));
                fputcsv($csv, array_values($rowData));
                fclose($csv);
                
                return file_exists($filepath);
            },
            'success_message' => 'Data exported successfully',
            'error_message' => 'Export failed'
        ]
    ],
    ['edit', 'delete'] // Built-in actions
])
```

### Callback Function Parameters
Action callbacks receive four parameters:
- `$rowId` - The ID of the clicked row
- `$rowData` - Complete row data as associative array
- `$database` - Database instance for queries
- `$tableName` - Base table name for operations

The callback should return `true` for success or `false` for failure.

---

## API Methods Update

In the existing "API Methods" section under "Core Configuration", add this line after the `join()` method:

```markdown
- `where(array $conditions)` - Add WHERE conditions to filter records
```

## File Upload Configuration

```php
->fileUpload(
    'uploads/documents/',           // Upload path
    ['pdf', 'doc', 'docx', 'jpg'],  // Allowed extensions
    10485760                        // Max file size (10MB)
)
```

## CSS Customization

### Table Classes
```php
->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
```

### Row Classes with ID Suffix
```php
->rowClass('highlight') // Creates classes like "highlight-123" for row with ID 123
```

### Column-Specific Classes
```php
->columnClasses([
    'u.name' => 'uk-text-bold uk-text-primary',
    'u.status' => 'uk-text-center',
    'actions' => 'uk-text-nowrap'
])
```

## Complete Working Example

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

// Database configuration
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

// Create DataTables instance
$dataTable = new DataTables($dbConfig);

// Handle AJAX requests first
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>DataTables Example</title>
    <!-- UIKit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/css/uikit.min.css" />
    <!-- UIKit JS -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.16.14/dist/js/uikit-icons.min.js"></script>
    <?php echo DataTables::getJsIncludes(); ?>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <?php
        echo $dataTable
            ->table('users u')
            ->primaryKey('u.id')
            ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
            ->columns([
                'u.id' => 'ID',
                'u.name' => 'Name',
                'u.email' => 'Email',
                'r.role_name' => 'Role',
                'u.status' => [
                    'label' => 'Status',
                    'type' => 'boolean'
                ]
            ])
            ->sortable(['u.name', 'u.email', 'r.role_name'])
            ->inlineEditable(['u.name', 'u.email', 'u.status'])
            ->bulkActions(true)
            ->addForm('Add User', [
                'name' => [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true
                ],
                'role_id' => [
                    'type' => 'select',
                    'label' => 'Role',
                    'options' => [
                        '1' => 'Admin',
                        '2' => 'User'
                    ]
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Active',
                    'value' => '1'
                ]
            ])
            ->editForm('Edit User', [
                'name' => [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true
                ],
                'role_id' => [
                    'type' => 'select',
                    'label' => 'Role',
                    'options' => [
                        '1' => 'Admin',
                        '2' => 'User'
                    ]
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Active'
                ]
            ])
            ->renderDataTableComponent();
        ?>
    </div>
</body>
</html>
```

## API Methods

### Core Configuration
- `table(string $tableName)` - Set the database table (supports aliases)
- `primaryKey(string $column)` - Set primary key column (supports qualified names)
- `database(array $config)` - Configure database connection
- `columns(array $columns)` - Configure table columns (supports qualified names)
- `join(string $type, string $table, string $condition)` - Add JOIN clause with alias support

### Display Options
- `sortable(array $columns)` - Set sortable columns (supports qualified names)
- `inlineEditable(array $columns)` - Set inline editable columns
- `search(bool $enabled)` - Enable/disable search
- `perPage(int $count)` - Set records per page
- `pageSizeOptions(array $options, bool $includeAll)` - Set page size options

### Actions and Forms
- `actions(string $position, bool $showEdit, bool $showDelete, array $customActions)` - Configure action buttons
- `actionGroups(array $groups)` - Configure grouped actions with separators
- `bulkActions(bool $enabled, array $actions)` - Configure bulk actions with callbacks
- `addForm(string $title, array $fields, bool $ajax)` - Configure add form
- `editForm(string $title, array $fields, bool $ajax)` - Configure edit form

### Styling
- `tableClass(string $class)` - Set table CSS class
- `rowClass(string $class)` - Set row CSS class base
- `columnClasses(array $classes)` - Set column-specific CSS classes

### File Handling
- `fileUpload(string $path, array $extensions, int $maxSize)` - Configure file uploads

### Rendering
- `renderDataTableComponent()` - Generate complete HTML output
- `handleAjax()` - Handle AJAX requests

### Static Methods
- `DataTables::getJsIncludes()` - Get JavaScript include tags

## Key Features for Complex Applications

### Qualified Column Names
- Support for fully qualified column names like `'users.id'`, `'roles.name'`
- Automatic handling of table aliases in SELECT, WHERE, and ORDER BY clauses
- Proper separation of qualified names for display vs base table operations

### Advanced JOIN Support
- Multiple JOIN types (INNER, LEFT, RIGHT, FULL OUTER)
- Table aliases preserved in queries
- Complex relationship mapping between tables

### Flexible Primary Keys
- Support for qualified primary keys (`'users.user_id'`)
- Automatic extraction of base column names for operations
- Proper handling in bulk actions and CRUD operations

## Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan

# Run code style check
composer cs-check
```

## Security

If you discover any security-related issues, please email security@kpirnie.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Kevin Pirnie](https://github.com/kpirnie)
- [UIKit3](https://getuikit.com/) for the UI framework
- All contributors

## Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

## Roadmap

- [ ] Export functionality (CSV, Excel, PDF)
- [ ] Integration with popular PHP frameworks
- [ ] REST API endpoints
- [ ] Audit trail/change logging

---

**Made with ❤️ by [Kevin Pirnie](https://kpirnie.com)**