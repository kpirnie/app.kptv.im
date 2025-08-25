<?php
/**
 * Provider View Configuration
 * 
 * @since 8.4
 * @package KP Library
 */

defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;

// make sure it doesnt already exist
if( ! class_exists( 'ProvidersViewConfig' ) ) {

    /**
     * Provider View Configuration
     * 
     * @since 8.4
     * @package KP Library
     */
    class ProvidersViewConfig {
        
        public static function getConfig(): array {
            return [
                'table' => [
                    'show_checkbox' => true,
                    'show_actions' => true,
                    'empty_message' => 'No providers found',
                    'columns' => [
                        [
                            'key' => 'export',
                            'label' => 'Export',
                            'sortable' => false,
                            'renderer' => function($record) {
                                $user_for_link = KPT::encrypt($record->u_id);
                                $prov_for_link = KPT::encrypt($record->id);
                                return '<div class="uk-button-group">' .
                                    '<a href="' . KPT_URI . 'playlist/' . $user_for_link . '/' . $prov_for_link . '/live" class="uk-icon-link copy-link uk-margin-small-right" uk-icon="tv" uk-tooltip="Copy This Providers Live Streams Playlist"></a>' .
                                    '<a href="' . KPT_URI . 'playlist/' . $user_for_link . '/' . $prov_for_link . '/series" class="uk-icon-link copy-link uk-margin-small-horizontal" uk-icon="album" uk-tooltip="Copy This Providers Series Streams Playlist"></a>' .
                                    '</div>';
                            }
                        ],
                        [
                            'key' => 'sp_priority',
                            'label' => 'Priority',
                            'sortable' => true,
                            'responsive' => 'uk-visible@s'
                        ],
                        [
                            'key' => 'sp_name',
                            'label' => 'Name',
                            'sortable' => true
                        ],
                        [
                            'key' => 'sp_cnx_limit',
                            'label' => 'Connections',
                            'sortable' => true
                        ],
                        /*[
                            'key' => 'sp_type',
                            'label' => 'Type',
                            'sortable' => true,
                            'responsive' => 'uk-visible@m',
                            'renderer' => function($record) {
                                return $record->sp_type == 0 ? 'XC API' : 'M3U';
                            }
                        ],
                        [
                            'key' => 'sp_stream_type',
                            'label' => 'Stream',
                            'sortable' => true,
                            'responsive' => 'uk-visible@m',
                            'renderer' => function($record) {
                                return $record->sp_stream_type == 0 ? 'MPEGTS' : 'HLS';
                            }
                        ],*/
                        [
                            'key' => 'sp_should_filter',
                            'label' => 'Filter',
                            'sortable' => true,
                            'renderer' => function($record) {
                                return '<span class="active-toggle" data-id="' . $record->id . '">' .
                                    '<i uk-icon="' . ($record->sp_should_filter ? 'check' : 'close') . '" class="me"></i>' .
                                    '</span>';
                            }
                        ]
                    ],
                    'actions' => [
                        [
                            'href' => '#edit-modal-{id}',
                            'icon' => 'pencil',
                            'tooltip' => 'Edit this Provider',
                            'attributes' => 'uk-toggle'
                        ],
                        [
                            'href' => '#delete-modal-{id}',
                            'icon' => 'trash',
                            'tooltip' => 'Delete this Provider',
                            'attributes' => 'uk-toggle'
                        ]
                    ]
                ],
                'modals' => [
                    'create' => [
                        'title' => 'Add New Provider',
                        'fields' => [
                            [
                                'name' => 'sp_name',
                                'label' => 'Name',
                                'type' => 'text',
                                'group' => 'basic',
                                'required' => true
                            ],
                            [
                                'name' => 'sp_cnx_limit',
                                'label' => 'Connections',
                                'type' => 'number',
                                'group' => 'basic',
                                'required' => true,
                                'min' => 1,
                                'max' => 99,
                                'default' => 1
                            ],
                            [
                                'name' => 'sp_type',
                                'label' => 'Provider Type',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [0 => 'XC API', 1 => 'M3U']
                            ],
                            [
                                'name' => 'sp_domain',
                                'label' => 'Domain',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 'sp_username',
                                'label' => 'Username',
                                'type' => 'text',
                                'group' => 'credentials'
                            ],
                            [
                                'name' => 'sp_password',
                                'label' => 'Password',
                                'type' => 'text',
                                'group' => 'credentials'
                            ],
                            [
                                'name' => 'sp_stream_type',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'group' => 'settings',
                                'options' => [0 => 'MPEGTS', 1 => 'HLS']
                            ],
                            [
                                'name' => 'sp_should_filter',
                                'label' => 'Filter Content',
                                'type' => 'select',
                                'group' => 'settings',
                                'options' => [1 => 'Yes', 0 => 'No'],
                                'default' => 1
                            ],
                            [
                                'name' => 'sp_priority',
                                'label' => 'Priority',
                                'type' => 'number',
                                'group' => 'advanced',
                                'input_type' => 'number',
                                'min' => 0,
                                'max' => 99,
                                'default' => 99
                            ],
                            [
                                'name' => 'sp_refresh_period',
                                'label' => 'Refresh Period (days)',
                                'type' => 'number',
                                'group' => 'advanced',
                                'input_type' => 'number',
                                'default' => 1
                            ]
                        ]
                    ],
                    'edit' => [
                        'title' => 'Edit Provider',
                        'fields' => [
                            [
                                'name' => 'sp_name',
                                'label' => 'Name',
                                'type' => 'text',
                                'group' => 'basic',
                                'required' => true
                            ],
                            [
                                'name' => 'sp_cnx_limit',
                                'label' => 'Connections',
                                'type' => 'number',
                                'group' => 'basic',
                                'required' => true,
                                'min' => 1,
                                'max' => 99,
                            ],
                            [
                                'name' => 'sp_type',
                                'label' => 'Provider Type',
                                'type' => 'select',
                                'group' => 'basic',
                                'options' => [0 => 'XC API', 1 => 'M3U']
                            ],
                            [
                                'name' => 'sp_domain',
                                'label' => 'Domain',
                                'type' => 'text',
                                'required' => true
                            ],
                            [
                                'name' => 'sp_username',
                                'label' => 'Username',
                                'type' => 'text',
                                'group' => 'credentials'
                            ],
                            [
                                'name' => 'sp_password',
                                'label' => 'Password',
                                'type' => 'text',
                                'group' => 'credentials'
                            ],
                            [
                                'name' => 'sp_stream_type',
                                'label' => 'Stream Type',
                                'type' => 'select',
                                'group' => 'settings',
                                'options' => [0 => 'MPEGTS', 1 => 'HLS']
                            ],
                            [
                                'name' => 'sp_should_filter',
                                'label' => 'Filter Content',
                                'type' => 'select',
                                'group' => 'settings',
                                'options' => [1 => 'Yes', 0 => 'No']
                            ],
                            [
                                'name' => 'sp_priority',
                                'label' => 'Priority',
                                'type' => 'number',
                                'group' => 'advanced',
                                'input_type' => 'number',
                                'min' => 0,
                                'max' => 99
                            ],
                            [
                                'name' => 'sp_refresh_period',
                                'label' => 'Refresh Period (days)',
                                'type' => 'number',
                                'group' => 'advanced',
                                'input_type' => 'number'
                            ]
                        ]
                    ],
                    'delete' => [
                        'title' => 'Delete Provider',
                        'message' => 'Are you sure you want to delete this provider?'
                    ]
                ]
            ];
        }
    }

}
