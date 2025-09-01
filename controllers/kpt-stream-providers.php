<?php
/**
 * KPTV Stream Providers CRUD Class
 * 
 * Handles all database operations for the kptv_stream_providers table
 * Manages IPTV stream provider sources and configurations
 * 
 * @since 8.4
 * @package KP Library
 * @author Kevin Pirnie <me@kpirnie.com>
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;

class KPTV_Stream_Providers extends KPTV_Base {
    
    protected string $table_name = 'kptv_stream_providers';
    protected array $searchable_fields = ['sp_name'];
    protected string $default_sort_column = 'sp_priority';

    protected function create(array $data): int|bool {
        $query = "INSERT INTO {$this->table_name} (
            u_id, sp_should_filter, sp_priority, sp_name, sp_type, 
            sp_domain, sp_username, sp_password, sp_stream_type, sp_refresh_period, sp_cnx_limit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $this->current_user_id,
            $data['sp_should_filter'] ?? 1,
            $data['sp_priority'] ?? 99,
            $data['sp_name'] ?? '',
            $data['sp_type'] ?? 0,
            $data['sp_domain'] ?? '',
            $data['sp_username'] ?? null,
            $data['sp_password'] ?? null,
            $data['sp_stream_type'] ?? 0,
            $data['sp_refresh_period'] ?? 3,
            $data['sp_cnx_limit'] ?? 1
        ];
        
        return $this->query($query)->bind($params)->execute();
    }

    protected function update(int $id, array $data): bool {
        $query = "UPDATE {$this->table_name} SET 
            sp_should_filter = ?, sp_priority = ?, sp_name = ?, sp_type = ?, 
            sp_domain = ?, sp_username = ?, sp_password = ?, sp_stream_type = ?, 
            sp_refresh_period = ?, sp_cnx_limit = ?, sp_updated = CURRENT_TIMESTAMP
            WHERE id = ? AND u_id = ?";
        
        $params = [
            $data['sp_should_filter'] ?? 1,
            $data['sp_priority'] ?? 99,
            $data['sp_name'] ?? '',
            $data['sp_type'] ?? 0,
            $data['sp_domain'] ?? '',
            $data['sp_username'] ?? null,
            $data['sp_password'] ?? null,
            $data['sp_stream_type'] ?? 0,
            $data['sp_refresh_period'] ?? 3,
            $data['sp_cnx_limit'] ?? 1,
            $id,
            $this->current_user_id
        ];
        
        return (bool)$this->query($query)->bind($params)->execute();
    }

    protected function delete(int $id): bool {
        // Delete associated records first
        $this->query("DELETE FROM kptv_stream_other WHERE p_id = ? AND u_id = ?")
             ->bind([$id, $this->current_user_id])
             ->execute();
             
        $this->query("DELETE FROM kptv_streams WHERE p_id = ? AND u_id = ?")
             ->bind([$id, $this->current_user_id])
             ->execute();
        
        // Then delete the provider
        return parent::delete($id);
    }

    public function post_action(array $params): void {

        $theid = isset($params['id']) ? (int)$params['id'] : 0;
        

        switch ($params['form_action']) {
            case 'create':
                $this->create($params);
                KPT::message_with_redirect(KPT::get_redirect_url( ), 'success', 'Provider created successfully.');
                break;
                
            case 'update':
                if ($theid > 0) {
                    $this->update($theid, $params);
                    KPT::message_with_redirect(KPT::get_redirect_url( ), 'success', 'Provider updated successfully.');
                }
                break;
                
            case 'delete':
                if ($theid > 0) {
                    $this->delete($theid);
                    KPT::message_with_redirect(KPT::get_redirect_url( ), 'success', 'Provider deleted successfully.');
                }
                break;
                
            case 'delete-multiple':
                if (isset($params['ids']) && is_array($params['ids'])) {
                    foreach ($params['ids'] as $id) {
                        $this->delete($id);
                    }
                    KPT::message_with_redirect(KPT::get_redirect_url( ), 'success', 'Providers deleted successfully.');
                }
                break;
                
            case 'toggle-active':
                $success = $this->toggleActive($theid, 'sp_should_filter');
                $this->handleResponse($success, 'In/Activated successfully', 'Failed to activate');
                break;
            default:
                KPT::message_with_redirect(KPT::get_redirect_url( ), 'danger', 'Invalid action.');
                break;
        }
    }
}