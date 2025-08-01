<?php
/**
 * Enhanced Base Table View Component (Clean Version)
 * 
 * Fully modular table view component using separate renderers
 * 
 * @since 8.4
 * @package KPTV Stream Manager
 */

defined('KPT_PATH') || die('Direct Access is not allowed!');

class EnhancedBaseTableView {
    
    protected string $title = '';
    protected string $base_url = '';
    protected array $table_config = [];
    protected array $modal_config = [];
    protected ?TableRenderer $table_renderer = null;
    
    public function __construct(string $title, string $base_url, array $config = []) {
        $this->title = $title;
        $this->base_url = $base_url;
        $this->table_config = $config['table'] ?? [];
        $this->modal_config = $config['modals'] ?? [];
        $this->table_renderer = new TableRenderer($this->table_config);
    }
    
    /**
     * Render the complete view
     */
    public function display(array $data = []): void {
        $this->renderHeader($data);
        $this->renderNavigation($data);
        echo $this->table_renderer->render($data);
        $this->renderNavigation($data);
        $this->renderModals($data);
        $this->renderFooter();
    }
    
    /**
     * Render page header
     */
    protected function renderHeader(array $data): void {
        KPT::pull_header();
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
        KPT::include_view('common/navigation', [
            'page' => $data['page'] ?? 1,
            'total_pages' => $data['total_pages'] ?? 1,
            'per_page' => $data['per_page'] ?? 25,
            'base_url' => $this->base_url,
            'max_visible_links' => 5,
            'show_first_last' => true,
            'search_term' => $data['search_term'] ?? '',
        ]);
    }
    
    /**
     * Render modals using ModalRenderer
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
        KPT::pull_footer();
    }
}