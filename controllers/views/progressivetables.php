<?php
/**
 * Progressive Table Renderer Component with Output Buffer Flushing
 * 
 * Renders tables progressively, flushing output every 25 records for better UX
 * 
 * @since 8.4
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class ProgressiveTableRenderer {
    
    private array $config;
    private int $flush_interval;
    private bool $progressive_enabled;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->flush_interval = $config['flush_interval'] ?? 25; // Flush every 25 records
        $this->progressive_enabled = $config['progressive_rendering'] ?? true;
    }
    
    /**
     * Render complete table with progressive loading
     */
    public function render(array $data): string {
        $records = $data['records'] ?? [];
        $record_count = count($records);
        
        // If we have fewer records than flush interval, use standard rendering
        if (!$this->progressive_enabled || $record_count <= $this->flush_interval) {
            return $this->renderStandard($data);
        }
        
        // Start progressive rendering
        $this->startProgressiveRender($data);
        $this->renderProgressiveRecords($records, $data);
        $this->endProgressiveRender($data);
        
        return ''; // Content already output progressively
    }
    
    /**
     * Standard rendering for smaller datasets
     */
    private function renderStandard(array $data): string {
        ob_start();
        
        echo '<div class="uk-overflow-auto">';
        echo '<table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">';
        
        $this->renderHeader($data);
        $this->renderBody($data);
        $this->renderFooter($data);
        
        echo '</table>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Start progressive rendering - output initial structure
     */
    private function startProgressiveRender(array $data): void {
        // Ensure we can flush output
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        
        // Start output buffering with a small buffer size for frequent flushing
        ob_start(null, 1024);
        
        // Output initial table structure
        echo '<div class="uk-overflow-auto">';
        echo '<table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">';
        
        $this->renderHeader($data);
        echo '<tbody>';
        
        // Add loading indicator
        echo '<tr id="loading-indicator" style="display: none;">';
        echo '<td colspan="' . $this->getColumnCount() . '" class="uk-text-center">';
        echo '<div uk-spinner="ratio: 0.5"></div> Loading more records...';
        echo '</td>';
        echo '</tr>';
        
        $this->flushOutput();
    }
    
    /**
     * Render records progressively with flushing
     */
    private function renderProgressiveRecords(array $records, array $data): void {
        $record_count = count($records);
        $processed = 0;
        
        foreach ($records as $index => $record) {
            $this->renderRow($record, $data);
            $processed++;
            
            // Flush every N records or on the last record
            if ($processed % $this->flush_interval === 0 || $processed === $record_count) {
                
                // Add a small delay to demonstrate progressive loading (remove in production)
                if ($processed < $record_count) {
                    echo '<script>
                        document.getElementById("loading-indicator").style.display = "table-row";
                        setTimeout(function() {
                            document.getElementById("loading-indicator").style.display = "none";
                        }, 100);
                    </script>';
                }
                
                $this->flushOutput();
                
                // Small delay to prevent overwhelming the browser (adjust as needed)
                if ($processed < $record_count) {
                    usleep(50000); // 50ms delay
                }
            }
        }
    }
    
    /**
     * End progressive rendering - output closing structure
     */
    private function endProgressiveRender(array $data): void {
        echo '</tbody>';
        $this->renderFooter($data);
        echo '</table>';
        echo '</div>';
        
        // Hide loading indicator
        echo '<script>
            document.getElementById("loading-indicator").style.display = "none";
        </script>';
        
        $this->flushOutput();
        
        // End output buffering
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
    
    /**
     * Flush output buffer and send to browser
     */
    private function flushOutput(): void {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
        // Force output on some servers
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
    
    /**
     * Get total column count for colspan calculations
     */
    private function getColumnCount(): int {
        $count = count($this->config['columns'] ?? []);
        if ($this->config['show_checkbox'] ?? true) $count++;
        if ($this->config['show_actions'] ?? true) $count++;
        return $count;
    }
    
    /**
     * Render table header
     */
    private function renderHeader(array $data): void {
        echo '<thead><tr>';
        
        if ($this->config['show_checkbox'] ?? true) {
            echo '<th width="5px">';
            echo '<input type="checkbox" id="select-all" class="uk-checkbox select-all">';
            echo '</th>';
        }
        
        foreach ($this->config['columns'] ?? [] as $column) {
            $this->renderColumnHeader($column, $data);
        }
        
        if ($this->config['show_actions'] ?? true) {
            echo '<th>Actions</th>';
        }
        
        echo '</tr></thead>';
    }
    
    /**
     * Render column header
     */
    private function renderColumnHeader(array $column, array $data): void {
        $sort_column = $data['sort_column'] ?? '';
        $sort_direction = $data['sort_direction'] ?? 'ASC';
        
        $classes = [];
        if ($column['sortable'] ?? false) {
            $classes[] = 'sortable';
        }
        if ($column['responsive'] ?? false) {
            $classes[] = $column['responsive'];
        }
        
        $class_str = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        $data_attr = ($column['sortable'] ?? false) ? ' data-column="' . $column['key'] . '"' : '';
        
        echo '<th' . $class_str . $data_attr . '>';
        echo htmlspecialchars($column['label']);
        
        if (($column['sortable'] ?? false) && $sort_column === $column['key']) {
            $icon = strtolower($sort_direction) === 'asc' ? 'up' : 'down';
            echo '<span class="uk-align-right" uk-icon="icon: chevron-' . $icon . '"></span>';
        }
        
        echo '</th>';
    }
    
    /**
     * Render table body (standard mode)
     */
    private function renderBody(array $data): void {
        echo '<tbody>';
        
        $records = $data['records'] ?? [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $this->renderRow($record, $data);
            }
        } else {
            $this->renderEmptyRow();
        }
        
        echo '</tbody>';
    }
    
    /**
     * Render table row
     */
    private function renderRow(object $record, array $data): void {
        echo '<tr>';
        
        if ($this->config['show_checkbox'] ?? true) {
            echo '<td>';
            echo '<input type="checkbox" name="ids[]" value="' . $record->id . '" class="uk-checkbox record-checkbox">';
            echo '</td>';
        }
        
        foreach ($this->config['columns'] ?? [] as $column) {
            $this->renderCell($record, $column, $data);
        }
        
        if ($this->config['show_actions'] ?? true) {
            $this->renderActionCell($record, $data);
        }
        
        echo '</tr>';
    }
    
    /**
     * Render table cell
     */
    private function renderCell(object $record, array $column, array $data): void {
        $classes = [];
        if ($column['responsive'] ?? false) {
            $classes[] = $column['responsive'];
        }
        if ($column['truncate'] ?? false) {
            $classes[] = 'truncate';
        }
        
        $class_str = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        
        echo '<td' . $class_str . '>';
        
        if (isset($column['renderer']) && is_callable($column['renderer'])) {
            echo $column['renderer']($record, $data);
        } else {
            $value = $record->{$column['key']} ?? '';
            echo htmlspecialchars($value);
        }
        
        echo '</td>';
    }
    
    /**
     * Render action cell
     */
    private function renderActionCell(object $record, array $data): void {
        echo '<td class="action-cell">';
        echo '<div class="uk-button-group">';
        
        foreach ($this->config['actions'] ?? [] as $action) {
            $this->renderAction($record, $action, $data);
        }
        
        echo '</div>';
        echo '</td>';
    }
    
    /**
     * Render action button
     */
    private function renderAction(object $record, array $action, array $data): void {
        $href = $action['href'] ?? '#';
        $icon = $action['icon'] ?? 'info';
        $tooltip = $action['tooltip'] ?? '';
        $class = $action['class'] ?? 'uk-icon-link';
        $attributes = $action['attributes'] ?? '';
        
        // Handle callable values
        if (is_callable($href)) {
            $href = $href($record, $data);
        }
        if (is_callable($attributes)) {
            $attributes = $attributes($record, $data);
        }
        
        // Replace placeholders
        $href = str_replace('{id}', (string)$record->id, $href);
        $tooltip = str_replace('{id}', (string)$record->id, $tooltip);
        
        // Check condition if exists
        if (isset($action['condition']) && is_callable($action['condition'])) {
            if (!$action['condition']($record, $data)) {
                return;
            }
        }
        
        echo '<a href="' . htmlspecialchars($href) . '" ';
        echo 'class="' . htmlspecialchars($class) . '" ';
        echo 'uk-icon="' . htmlspecialchars($icon) . '" ';
        if ($tooltip) {
            echo 'uk-tooltip="' . htmlspecialchars($tooltip) . '" ';
        }
        if ($attributes) {
            echo $attributes . ' ';
        }
        echo '></a>';
    }
    
    /**
     * Render empty row
     */
    private function renderEmptyRow(): void {
        $colspan = $this->getColumnCount();
        
        echo '<tr>';
        echo '<td colspan="' . $colspan . '" class="uk-text-center">';
        echo htmlspecialchars($this->config['empty_message'] ?? 'No records found');
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Render table footer
     */
    private function renderFooter(array $data): void {
        echo '<tfoot>';
        $this->renderHeader($data); // Reuse header for footer
        echo '</tfoot>';
    }
}

/**
 * Enhanced Base Table View with Progressive Rendering
 */
class EnhancedProgressiveTableView {
    
    protected string $title = '';
    protected string $base_url = '';
    protected array $table_config = [];
    protected array $modal_config = [];
    protected ?ProgressiveTableRenderer $table_renderer = null;
    
    public function __construct(string $title, string $base_url, array $config = []) {
        $this->title = $title;
        $this->base_url = $base_url;
        $this->table_config = $config['table'] ?? [];
        $this->modal_config = $config['modals'] ?? [];
        
        // Enable progressive rendering for large datasets
        $this->table_config['progressive_rendering'] = $config['progressive_rendering'] ?? true;
        $this->table_config['flush_interval'] = $config['flush_interval'] ?? 25;
        
        $this->table_renderer = new ProgressiveTableRenderer($this->table_config);
    }
    
    /**
     * Display the complete view
     */
    public function display(array $data = []): void {
        $this->renderHeader($data);
        $this->renderNavigation($data);
        
        // Check if we should use progressive rendering
        $records = $data['records'] ?? [];
        if (count($records) > ($this->table_config['flush_interval'] ?? 25)) {
            // For large datasets, don't buffer the table output
            $this->table_renderer->render($data);
        } else {
            // For small datasets, use normal rendering
            echo $this->table_renderer->render($data);
        }
        
        $this->renderNavigation($data);
        $this->renderModals($data);
        $this->renderFooter();
    }
    
    /**
     * Render page header
     */
    protected function renderHeader(array $data): void {
        // Use KPT library function
        \KPT\KPT::pull_header();
        echo '<div class="uk-container">';
        echo '<h2 class="me uk-heading-divider">' . htmlspecialchars($this->title) . '</h2>';
        
        if (isset($data['error'])) {
            echo '<div class="uk-alert-danger" uk-alert>';
            echo '<a class="uk-alert-close" uk-close></a>';
            echo '<p>' . htmlspecialchars($data['error']) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render navigation
     */
    protected function renderNavigation(array $data): void {
        \KPT\KPT::include_view('common/navigation', [
            'page' => $data['page'] ?? 1,
            'total_pages' => $data['total_pages'] ?? 1,
            'per_page' => $data['per_page'] ?? 25,
            'base_url' => $this->base_url,
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $data['search_term'] ?? '',
            'sort_column' => $data['sort_column'] ?? '',
            'sort_direction' => $data['sort_direction'] ?? '',
        ]);
    }
    
    /**
     * Render modals
     */
    protected function renderModals(array $data): void {
        foreach ($this->modal_config as $modal_type => $config) {
            switch ($modal_type) {
                case 'create':
                    echo ModalRenderer::renderCreateModal($config, $data);
                    break;
                case 'edit':
                    echo ModalRenderer::renderEditModals($config, $data['records'] ?? []);
                    break;
                case 'delete':
                    echo ModalRenderer::renderDeleteModals($config, $data['records'] ?? []);
                    break;
            }
        }
    }
    
    /**
     * Render footer
     */
    protected function renderFooter(): void {
        echo '</div>';
        \KPT\KPT::pull_footer();
    }
}

/**
 * Progressive Rendering Utilities
 * 
 * Helper classes and functions for managing progressive rendering
 * 
 * @since 8.4
 * @package KP Library
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

/**
 * Output Buffer Manager for Progressive Rendering
 */
class ProgressiveOutputManager {
    
    private static bool $initialized = false;
    private static int $flush_count = 0;
    private static float $start_time = 0;
    
    /**
     * Initialize progressive output
     */
    public static function initialize(array $config = []): void {
        if (self::$initialized) {
            return;
        }
        
        self::$start_time = microtime(true);
        
        // Disable gzip for progressive output
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        
        // Set headers for streaming
        header('X-Accel-Buffering: no'); // Disable Nginx buffering
        header('Cache-Control: no-cache'); // Disable caching
        
        // Clear any existing output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Start new output buffer with immediate flushing
        ob_start(null, 1);
        
        self::$initialized = true;
    }
    
    /**
     * Flush output to browser
     */
    public static function flush(): void {
        if (!self::$initialized) {
            self::initialize();
        }
        
        self::$flush_count++;
        
        // Flush PHP output buffer
        if (ob_get_level() > 0) {
            ob_flush();
        }
        
        // Flush system output buffer
        flush();
        
        // FastCGI optimization
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
    
    /**
     * Send progress update to browser
     */
    public static function sendProgress(int $current, int $total, string $message = ''): void {
        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        
        echo sprintf(
            '<script>
                if (typeof updateProgressIndicator === "function") {
                    updateProgressIndicator(%d, %d, %.1f, "%s");
                }
            </script>',
            $current,
            $total,
            $percentage,
            htmlspecialchars($message)
        );
        
        self::flush();
    }
    
    /**
     * Send timing information (for debugging)
     */
    public static function sendTiming(): void {
        $elapsed = microtime(true) - self::$start_time;
        echo sprintf(
            '<script>
                console.log("Progressive render: %d flushes, %.3f seconds");
            </script>',
            self::$flush_count,
            $elapsed
        );
    }
    
    /**
     * Finalize progressive output
     */
    public static function finalize(): void {
        if (!self::$initialized) {
            return;
        }
        
        // Send final timing info
        self::sendTiming();
        
        // Final flush
        self::flush();
        
        // Clean up
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        
        self::$initialized = false;
    }
}

/**
 * Enhanced Progressive Table Renderer with Better UX
 */
class EnhancedProgressiveTableRenderer extends ProgressiveTableRenderer {
    
    private int $rendered_count = 0;
    private float $render_start_time = 0;
    
    /**
     * Enhanced progressive record rendering with UX improvements
     */
    protected function renderProgressiveRecords(array $records, array $data): void {
        $record_count = count($records);
        $this->rendered_count = 0;
        $this->render_start_time = microtime(true);
        
        // Send initial progress
        ProgressiveOutputManager::sendProgress(0, $record_count, 'Starting to load records...');
        
        foreach ($records as $index => $record) {
            $this->renderRow($record, $data);
            $this->rendered_count++;
            
            // Flush every N records
            if ($this->rendered_count % $this->flush_interval === 0 || $this->rendered_count === $record_count) {
                
                // Calculate ETA
                $elapsed = microtime(true) - $this->render_start_time;
                $rate = $this->rendered_count / $elapsed;
                $remaining = $record_count - $this->rendered_count;
                $eta = $remaining > 0 ? round($remaining / $rate) : 0;
                
                // Send progress update
                $message = $this->rendered_count < $record_count 
                    ? "Loaded {$this->rendered_count} of {$record_count} records (ETA: {$eta}s)"
                    : "Completed loading {$record_count} records";
                
                ProgressiveOutputManager::sendProgress(
                    $this->rendered_count, 
                    $record_count, 
                    $message
                );
                
                // Add visual loading indicator between batches
                if ($this->rendered_count < $record_count) {
                    $this->renderProgressRow($this->rendered_count, $record_count);
                    ProgressiveOutputManager::flush();
                    
                    // Brief pause to prevent overwhelming the browser
                    usleep(10000); // 10ms
                    
                    // Remove the progress row
                    echo '<script>
                        const progressRow = document.getElementById("progress-row");
                        if (progressRow) progressRow.remove();
                    </script>';
                }
            }
        }
    }
    
    /**
     * Render a progress indicator row
     */
    private function renderProgressRow(int $current, int $total): void {
        $percentage = round(($current / $total) * 100, 1);
        
        echo '<tr id="progress-row" class="uk-animation-fade">';
        echo '<td colspan="' . $this->getColumnCount() . '" class="uk-text-center uk-padding-small">';
        echo '<div class="uk-margin-small">';
        echo '<div uk-spinner="ratio: 0.5" class="uk-margin-small-right"></div>';
        echo '<span class="uk-text-meta">Loading... ' . $percentage . '% complete</span>';
        echo '</div>';
        echo '<progress class="uk-progress uk-progress-success" value="' . $percentage . '" max="100"></progress>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Start progressive rendering with enhanced UX
     */
    protected function startProgressiveRender(array $data): void {
        ProgressiveOutputManager::initialize();
        
        // Output initial table structure
        echo '<div class="uk-overflow-auto">';
        echo '<div class="progressive-table-container">';
        echo '<table class="uk-table uk-table-hover uk-table-divider uk-table-small uk-margin-remove-top">';
        
        $this->renderHeader($data);
        echo '<tbody>';
        
        ProgressiveOutputManager::flush();
    }
    
    /**
     * End progressive rendering with cleanup
     */
    protected function endProgressiveRender(array $data): void {
        echo '</tbody>';
        $this->renderFooter($data);
        echo '</table>';
        echo '</div>'; // Close progressive-table-container
        echo '</div>';
        
        // Hide any remaining loading indicators
        echo '<script>
            document.querySelectorAll("#progress-row, #loading-indicator").forEach(el => el.remove());
            
            // Trigger completion event
            document.dispatchEvent(new CustomEvent("progressiveRenderComplete", {
                detail: { recordCount: ' . $this->rendered_count . ' }
            }));
        </script>';
        
        ProgressiveOutputManager::finalize();
    }
}

/**
 * Configuration helper for progressive rendering
 */
class ProgressiveRenderingConfig {
    
    /**
     * Get optimal configuration based on dataset size
     */
    public static function getOptimalConfig(int $record_count): array {
        if ($record_count <= 25) {
            return [
                'progressive_rendering' => false,
                'flush_interval' => $record_count
            ];
        } elseif ($record_count <= 100) {
            return [
                'progressive_rendering' => true,
                'flush_interval' => 25
            ];
        } elseif ($record_count <= 500) {
            return [
                'progressive_rendering' => true,
                'flush_interval' => 50
            ];
        } else {
            return [
                'progressive_rendering' => true,
                'flush_interval' => 100
            ];
        }
    }
    
    /**
     * Check if progressive rendering should be enabled
     */
    public static function shouldUseProgressive(int $record_count, array $user_prefs = []): bool {
        // User preference override
        if (isset($user_prefs['disable_progressive'])) {
            return !$user_prefs['disable_progressive'];
        }
        
        // Automatic detection
        return $record_count > 25;
    }
    
    /**
     * Get progressive rendering JavaScript
     */
    public static function getClientSideScript(): string {
        return '
        <script>
            // Progress indicator management
            let progressNotification = null;
            
            function updateProgressIndicator(current, total, percentage, message) {
                if (progressNotification) {
                    progressNotification.close();
                }
                
                if (current < total) {
                    progressNotification = UIkit.notification({
                        message: message + " (" + percentage + "%)",
                        status: "primary",
                        pos: "top-center",
                        timeout: 0
                    });
                } else {
                    UIkit.notification({
                        message: "✓ " + message,
                        status: "success",
                        pos: "top-center",
                        timeout: 2000
                    });
                }
            }
            
            // Listen for completion
            document.addEventListener("progressiveRenderComplete", function(e) {
                console.log("Progressive rendering completed:", e.detail.recordCount, "records");
                
                // Fade in table smoothly
                const container = document.querySelector(".progressive-table-container");
                if (container) {
                    container.style.opacity = "0";
                    container.style.transition = "opacity 0.5s ease-in-out";
                    setTimeout(() => container.style.opacity = "1", 100);
                }
                
                // Re-initialize any table functionality
                if (typeof initializeTableFeatures === "function") {
                    initializeTableFeatures();
                }
            });
        </script>
        ';
    }
    
    /**
     * Get CSS for progressive rendering
     */
    public static function getProgressiveCSS(): string {
        return '
        <style>
            .progressive-table-container {
                opacity: 1;
                transition: opacity 0.3s ease-in-out;
            }
            
            .progressive-table-container.loading {
                opacity: 0.7;
            }
            
            #progress-row td {
                background-color: #f8f9fa !important;
                border-top: 2px solid #007bff !important;
            }
            
            .uk-progress {
                height: 6px;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
            
            .loading-pulse {
                animation: pulse 1.5s infinite;
            }
        </style>
        ';
    }
}

// Usage example in your view files:
/*
// In your streams.php or other table views:

// Determine optimal config
$record_count = count($records ?: []);
$progressive_config = ProgressiveRenderingConfig::getOptimalConfig($record_count);

// Merge with your existing config
$config['progressive_rendering'] = $progressive_config['progressive_rendering'];
$config['flush_interval'] = $progressive_config['flush_interval'];

// Use enhanced renderer
$view = new EnhancedProgressiveTableView($title, $base_url, $config);

// Add client-side support
echo ProgressiveRenderingConfig::getProgressiveCSS();
echo ProgressiveRenderingConfig::getClientSideScript();

// Render the view
$view->display($data);
*/