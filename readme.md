# KPT DataTables

Advanced PHP DataTables library with CRUD operations, search, sorting, pagination, bulk actions, and UIKit3 integration.

## Features

- 🚀 **Full CRUD Operations** - Create, Read, Update, Delete with AJAX support
- 🔍 **Advanced Search** - Search all columns or specific columns
- 📊 **Sorting** - Multi-column sorting with visual indicators
- 📄 **Pagination** - Configurable page sizes with first/last navigation
- ✅ **Bulk Actions** - Select multiple records for bulk operations
- ✏️ **Inline Editing** - Double-click to edit fields directly in the table
- 📁 **File Uploads** - Built-in file upload handling with validation
- 🎨 **Themes** - Light and dark UIKit3 themes with toggle
- 📱 **Responsive** - Mobile-friendly design
- 🔗 **JOINs** - Support for complex database relationships
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

// Option 1: Configure database via constructor
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables($dbConfig);

// Option 2: Configure database via method chaining
$dataTable = new DataTables();
$dataTable->database($dbConfig);
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

## Advanced Usage

### Complete Configuration Example

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->table('users')
    ->primaryKey('user_id') // Default: 'id'
    ->columns([
        'user_id' => 'ID',
        'name' => 'Name',
        'email' => 'Email',
        'role_name' => 'Role',
        'status' => 'Status'
    ])
    
    // JOIN other tables
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    
    // Configure sorting and editing
    ->sortable(['name', 'email', 'created_at'])
    ->inlineEditable(['name', 'email'])
    
    // Pagination options
    ->perPage(25)
    ->pageSizeOptions([10, 25, 50, 100], true) // true includes "ALL" option
    
    // Enable bulk actions
    ->bulkActions(true, [
        'activate' => [
            'label' => 'Activate Selected',
            'icon' => 'check',
            'class' => 'uk-button-secondary',
            'confirm' => 'Activate selected users?',
            'callback' => function($ids, $db, $table) {
                return $db->query("UPDATE {$table} SET status = 'active' WHERE user_id IN (" . 
                              implode(',', array_fill(0, count($ids), '?')) . ")")
                          ->bind($ids)
                          ->execute();
            }
        ]
    ])
    
    // Configure action buttons
    ->actions('end', true, true, [
        [
            'icon' => 'mail',
            'title' => 'Send Email',
            'class' => 'btn-email'
        ]
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
            'type' => 'radio',
            'label' => 'Status',
            'options' => [
                'active' => 'Active',
                'inactive' => 'Inactive'
            ],
            'value' => 'active'
        ]
    ])
    
    // Edit form (similar to add form)
    ->editForm('Edit User', [
        // ... same fields as add form
    ])
    
    // CSS customization
    ->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
    ->rowClass('custom-row')
    ->columnClasses([
        'name' => 'uk-text-bold',
        'email' => 'uk-text-primary',
        'status' => 'uk-text-center'
    ])
    
    // File upload configuration
    ->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif'], 5242880) // 5MB limit
    
    ->renderDataTableComponent();
```

## Enhanced Column Configuration

### Simple Configuration
```php
->columns([
    'name' => 'Full Name',
    'email' => 'Email Address'
])
```

### Enhanced Configuration with Type Overrides
```php
->columns([
    'active' => [
        'label' => 'Status',
        'type' => 'boolean',
        'class' => 'uk-text-center'
    ],
    'category_id' => [
        'label' => 'Category',
        'type' => 'select',
        'options' => [
            '1' => 'Category 1',
            '2' => 'Category 2'
        ]
    ]
])
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

### Textarea
```php
'description' => [
    'type' => 'textarea',
    'label' => 'Description',
    'placeholder' => 'Enter description...',
    'attributes' => ['rows' => '5']
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

### Boolean/Checkbox
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

### Radio Buttons
```php
'status' => [
    'type' => 'radio',
    'label' => 'Status',
    'options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending'
    ],
    'value' => 'active'
]
```

### File Upload
```php
'document' => [
    'type' => 'file',
    'label' => 'Upload Document'
]
```

### Date/Time Fields
```php
'birth_date' => [
    'type' => 'date',
    'label' => 'Birth Date'
],
'appointment' => [
    'type' => 'datetime-local',
    'label' => 'Appointment Date & Time'
],
'meeting_time' => [
    'type' => 'time',
    'label' => 'Meeting Time'
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
            'class' => 'btn-email'
        ],
        'export' => [
            'icon' => 'download',
            'title' => 'Export Data',
            'class' => 'btn-export'
        ]
    ]
])
```

## Database Joins

```php
$dataTable
    ->table('orders o')
    ->join('INNER', 'customers c', 'o.customer_id = c.customer_id')
    ->join('LEFT', 'order_status s', 'o.status_id = s.status_id')
    ->columns([
        'order_id' => 'Order ID',
        'customer_name' => 'Customer',
        'order_date' => 'Date',
        'status_name' => 'Status',
        'total' => 'Total'
    ]);
```

## AJAX vs Non-AJAX Forms

### AJAX Forms (Default)
```php
->addForm('Add Record', $fields, true) // true = AJAX
->editForm('Edit Record', $fields, true)
```

### Traditional Form Submission
```php
->addForm('Add Record', $fields, false) // false = traditional POST
->editForm('Edit Record', $fields, false)
```

## File Upload Configuration

```php
->fileUpload(
    'uploads/documents/',           // Upload path
    ['pdf', 'doc', 'docx', 'jpg'],  // Allowed extensions
    10485760                        // Max file size (10MB)
)
```

## Search Configuration

### Enable/Disable Search
```php
->search(true)  // Enable search
->search(false) // Disable search
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
    'name' => 'uk-text-bold uk-text-primary',
    'status' => 'uk-text-center',
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
            ->table('users')
            ->columns([
                'id' => 'ID',
                'name' => 'Name',
                'email' => 'Email',
                'status' => [
                    'label' => 'Status',
                    'type' => 'boolean'
                ]
            ])
            ->sortable(['name', 'email'])
            ->inlineEditable(['name', 'email', 'status'])
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

## Auto-Generated Forms

The library automatically generates forms based on your database schema:

- **Text Fields**: VARCHAR, CHAR columns become text inputs
- **Email Fields**: Columns with "email" in the name become email inputs
- **Numbers**: INT, DECIMAL, FLOAT columns become number inputs
- **Booleans**: TINYINT(1) columns become boolean toggles
- **Dates**: DATE, DATETIME, TIMESTAMP columns become date/datetime inputs
- **Text Areas**: TEXT, LONGTEXT columns become textareas
- **Selects**: ENUM columns become select dropdowns

You can override any auto-detected type using the enhanced column configuration.

## Events and Hooks

### JavaScript Events
```javascript
// Table loaded
document.addEventListener('datatables:loaded', function(e) {
    console.log('Table loaded', e.detail);
});

// Record added
document.addEventListener('datatables:record:added', function(e) {
    console.log('Record added', e.detail);
});

// Theme changed
document.addEventListener('datatables:theme:changed', function(e) {
    console.log('Theme changed to', e.detail.theme);
});
```

## API Methods

### Core Configuration
- `table(string $tableName)` - Set the database table
- `database(array $config)` - Configure database connection
- `primaryKey(string $column)` - Set primary key column (default: 'id')
- `columns(array $columns)` - Configure table columns
- `join(string $type, string $table, string $condition)` - Add JOIN clause

### Display Options
- `sortable(array $columns)` - Set sortable columns
- `inlineEditable(array $columns)` - Set inline editable columns
- `search(bool $enabled)` - Enable/disable search
- `perPage(int $count)` - Set records per page
- `pageSizeOptions(array $options, bool $includeAll)` - Set page size options

### Actions and Forms
- `actions(string $position, bool $showEdit, bool $showDelete, array $customActions)` - Configure action buttons
- `actionGroups(array $groups)` - Configure grouped actions with separators
- `bulkActions(bool $enabled, array $actions)` - Configure bulk actions
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

- **Documentation**: [GitHub Wiki](https://github.com/kpirnie/kpt-datatables/wiki)
- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

## Roadmap

- [ ] Export functionality (CSV, Excel, PDF)
- [ ] Advanced filtering options
- [ ] Column visibility toggle
- [ ] Row drag & drop reordering
- [ ] Real-time updates via WebSockets
- [ ] Integration with popular PHP frameworks
- [ ] REST API endpoints
- [ ] Audit trail/change logging

---

**Made with ❤️ by [Kevin Pirnie](https://kpirnie.com)**