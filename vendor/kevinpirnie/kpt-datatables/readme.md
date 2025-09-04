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
- 🎨 **Custom Templates** - Individual component rendering for custom layouts

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

// Configure database
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

### 2. Simple Table

```php
// Handle AJAX requests
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
    exit;
}

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
    ->render();
```

## Custom Template Components

DataTables extends the Renderer class, allowing you to render individual components for custom layouts:

### Available Component Methods

```php
// Render individual components
echo $dataTable->renderSearchFormComponent();           // Search input and column selector
echo $dataTable->renderBulkActionsComponent();          // Bulk action buttons
echo $dataTable->renderPageSizeSelectorComponent();     // Records per page selector  
echo $dataTable->renderPaginationComponent();           // Pagination controls
echo $dataTable->renderDataTableComponent();            // Just the table without controls
```

### Custom Layout Example

```php
<div class="custom-controls">
    <div class="left-controls">
        <?php echo $dataTable->renderSearchFormComponent(); ?>
        <?php echo $dataTable->renderBulkActionsComponent(); ?>
    </div>
    
    <div class="right-controls">
        <?php echo $dataTable->renderPageSizeSelectorComponent(); ?>
    </div>
</div>

<?php echo $dataTable->renderDataTableComponent(); ?>

<div class="bottom-controls">
    <?php echo $dataTable->renderPaginationComponent(); ?>
</div>
```

## Column Configuration

### Simple Configuration (Column Name => Display Label)

```php
->columns([
    'id' => 'ID',
    'name' => 'Full Name', 
    'email' => 'Email Address',
    'status' => 'Status'
])
```

### Enhanced Configuration (with Type Overrides)

```php
->columns([
    'id' => 'ID',
    'name' => 'Full Name',
    'email' => 'Email Address', 
    'active' => [
        'label' => 'Active Status',
        'type' => 'boolean'  // Displays as icons, edits as select
    ],
    'role_id' => [
        'label' => 'Role',
        'type' => 'select',
        'options' => [
            '1' => 'Admin',
            '2' => 'User'
        ]
    ]
])
```

## Advanced Configuration Example

```php
$dataTable = new DataTables($dbConfig);

$dataTable
    ->table('users u')
    ->primaryKey('user_id') // Default: 'id'
    ->columns([
        'user_id' => 'ID',
        'name' => 'Name',
        'email' => 'Email',
        'role_name' => 'Role',
        'active' => [
            'label' => 'Status', 
            'type' => 'boolean'
        ]
    ])
    
    // JOIN other tables
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    
    // Configure sorting and editing
    ->sortable(['name', 'email', 'created_at'])
    ->inlineEditable(['name', 'email', 'active'])
    
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
                return $db->raw("UPDATE {$table} SET active = 1 WHERE user_id IN (" . 
                              implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
            }
        ]
    ])
    
    // Configure action buttons with groups
    ->actionGroups([
        ['edit', 'delete'],
        [
            'view_details' => [
                'icon' => 'info',
                'title' => 'View Details',
                'class' => 'btn-view-details',
                'onclick' => 'viewDetails(this.closest(\'tr\').dataset.id)'
            ]
        ]
    ])
    
    // Add form configuration
    ->addForm('Add New User', [
        'user_type' => [
            'type' => 'hidden',
            'value' => 'standard'
        ],
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
        'active' => [
            'type' => 'boolean',
            'label' => 'Active Status',
            'default' => '1'
        ]
    ])
    
    // Edit form (similar structure)
    ->editForm('Edit User', [
        // ... same fields as add form
    ])
    
    ->render();
```

## Form Field Types

### Hidden Fields
```php
'tracking_id' => [
    'type' => 'hidden',
    'value' => 'auto_generated_value'
]
```

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

### Boolean Fields (Icons in table, Select in forms)
```php
'active' => [
    'type' => 'boolean',
    'label' => 'Active Status'
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

### Textarea
```php
'description' => [
    'type' => 'textarea',
    'label' => 'Description',
    'placeholder' => 'Enter description...',
    'attributes' => ['rows' => '5']
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

### Checkbox
```php
'newsletter' => [
    'type' => 'checkbox',
    'label' => 'Subscribe to Newsletter',
    'value' => '1'
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
            return $database->raw(
                "UPDATE {$tableName} SET archived = 1 WHERE id IN ({$placeholders})", 
                $selectedIds
            );
        },
        'success_message' => 'Records archived successfully',
        'error_message' => 'Failed to archive records'
    ]
])
```

## Action Groups

Configure action buttons with separators:

```php
->actionGroups([
    ['edit', 'delete'],  // Built-in actions
    [
        'view_details' => [
            'icon' => 'info',
            'title' => 'View Details',
            'class' => 'btn-view-details',
            'onclick' => 'viewDetails(this.closest(\'tr\').dataset.id)'
        ],
        'duplicate' => [
            'icon' => 'copy',
            'title' => 'Duplicate Record',
            'class' => 'btn-duplicate',
            'onclick' => 'duplicateRecord(this.closest(\'tr\').dataset.id)'
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

## File Upload Configuration

```php
->fileUpload(
    'uploads/documents/',           // Upload path
    ['pdf', 'doc', 'docx', 'jpg'],  // Allowed extensions
    10485760                        // Max file size (10MB)
)
```

## CSS Customization

```php
->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
->rowClass('custom-row')  // Creates classes like 'custom-row-123' 
->columnClasses([
    'name' => 'uk-text-bold',
    'email' => 'uk-text-primary',
    'status' => 'uk-text-center'
])
```

## JavaScript Integration

### Include Required Files
```php
<?php echo KPT\DataTables\Renderer::getJsIncludes(); ?>
```

### Custom JavaScript Functions
```javascript
function viewDetails(id) {
    console.log('Viewing details for ID:', id);
    // Your custom logic
}

function duplicateRecord(id) {
    console.log('Duplicating record ID:', id);
    // Your custom logic  
}
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
    'username' => 'username',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables($dbConfig);

// Handle AJAX requests FIRST
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
    exit;
}

// Configure table
$dataTable
    ->table('users')
    ->columns([
        'id' => 'ID',
        'name' => 'Name', 
        'email' => 'Email',
        'active' => [
            'label' => 'Status',
            'type' => 'boolean'
        ]
    ])
    ->sortable(['id', 'name', 'email'])
    ->inlineEditable(['name', 'email', 'active'])
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
        'active' => [
            'type' => 'boolean',
            'label' => 'Active',
            'default' => '1'
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
        'active' => [
            'type' => 'boolean',
            'label' => 'Active'
        ]
    ]);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@latest/dist/css/uikit.min.css">
</head>
<body class="uk-light">
    <div class="uk-container">
        <h1>Users</h1>
        <?php echo $dataTable->render(); ?>
    </div>

    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/uikit@latest/dist/js/uikit-icons.min.js"></script>
    <?php echo KPT\DataTables\Renderer::getJsIncludes(); ?>
</body>
</html>
```

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

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Kevin Pirnie](https://github.com/kpirnie)
- [UIKit3](https://getuikit.com/) for the UI framework

## Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

---

**Made with ❤️ by [Kevin Pirnie](https://kpirnie.com)**