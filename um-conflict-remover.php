<?php
/**
 * Plugin Name:     Ultimate Member - Conflict Remover
 * Description:     Extension to Ultimate Member to exclude conflicting scripts and styles from UM frontend and backend pages and UM select2 scripts.
 * Version:         3.1.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.6.10
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Conflict_Remover {

    function __construct( ) {

        if( is_admin()) {

            add_filter( 'um_settings_structure',      array( $this, 'um_settings_structure_conflict_remover' ), 10, 1 );
            add_action( 'admin_enqueue_scripts',      array( $this, 'um_conflict_remover_backend_scripts_and_styles' ), 999, 1 );

        } else {

            add_action( 'wp_print_footer_scripts',    array( $this, 'um_conflict_remover_scripts_and_styles' ), 9 );
            add_action( 'wp_print_scripts',           array( $this, 'um_conflict_remover_scripts_and_styles' ), 9 );
            add_action( 'wp_print_styles',            array( $this, 'um_conflict_remover_scripts_and_styles' ), 9 );
            add_filter( 'um_dequeue_select2_scripts', array( $this, 'um_conflict_remover_dequeue_um_select2' ), 10, 1 );
        }
    }

    public function um_conflict_remover_dequeue_um_select2( $bool ) {

        if ( UM()->options()->get( 'um_conflict_remover_dequeue_um_select2' ) == 1 ) {

            $bool = true;
        }

        return $bool;
    }

    public function um_conflict_remover_backend_scripts_and_styles( $hook ) {

        if ( UM()->options()->get( 'um_conflict_remover_backend_pages' ) == 1 ) {

            if ( in_array( $hook , array( 'toplevel_page_ultimatemember', 'ultimate-member_page_um_options', 'ultimate-member_page_um_roles'  ))
                 || ( $hook == 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'um_form' )) {

                $this->um_remove_conflicting_plugins();
            }
        }
    }

    public function um_conflict_remover_scripts_and_styles() {

        global $post;

        $um_pages = UM()->options()->get( 'um_conflict_remover_um_pages' );
        $remove = false;

        if ( is_array( $um_pages ) ) {

            $REQUEST_URI = $_SERVER['REQUEST_URI'];

            if ( in_array( $REQUEST_URI, $um_pages ) ) {
                $remove = true;

            } else {

                foreach ( $um_pages as $um_page ) {
                    if ( strpos( $REQUEST_URI, $um_page ) !== FALSE ) {
                        $remove = true;
                    }
                }
            }
        }

        if ( isset( $post ) && is_a( $post, 'WP_Post' ) ) {

            $um_posts = array_map( 'intval', explode( ',', UM()->options()->get( 'um_conflict_remover_um_page_ids' )));

            if ( in_array( $post->ID, $um_posts ) ) {
                $remove = true;
            }

            if ( strpos( $post->post_content, '[ultimatemember_' ) !== FALSE ) {
                $remove = true;
            }

            if ( strpos( $post->post_content, '[ultimatemember form_id' ) !== FALSE ) {
                $remove = true;
            }

            if ( strpos( $post->post_content, '[um_' ) !== FALSE ) {
                $remove = true;
            }
        }

        if ( $remove ) {

            $this->um_remove_conflicting_plugins();
        }
    }

    public function um_remove_conflicting_plugins() {

        global $wp_scripts, $wp_styles;

        $um_plugins = UM()->options()->get( 'um_conflict_remover_plugins' );

        if ( ! empty( $um_plugins )) {

            $remove_handles = array(
                        'scripts' => array(),
                        'styles'  => array()
                    );

            foreach ( $wp_scripts->registered as $value ) {
                foreach ( $um_plugins as $um_plugin ) {

                    if ( strpos( $value->src, $um_plugin ) !== FALSE ) {
                        $remove_handles['scripts'][] = $value->handle;
                    }
                }
            }

            foreach ( $wp_styles->registered as $value ) {
                foreach ( $um_plugins as $um_plugin ) {

                    if ( strpos( $value->src, $um_plugin ) !== FALSE ) {
                        $remove_handles['styles'][] = $value->handle;
                    }
                }
            }

            if ( count( $remove_handles['scripts'] ) > 0 ) {
                if ( is_array( $wp_scripts->queue ) ) {

                    foreach ( $wp_scripts->queue as $key => $handle ) {

                        if ( in_array( $handle, $remove_handles['scripts'] ) ) {
                            unset( $wp_scripts->queue[$key] );
                        }
                    }
                }
            }

            if ( count( $remove_handles['styles'] ) > 0 ) {
                if ( is_array( $wp_styles->queue ) ) {

                    foreach ( $wp_styles->queue as $key => $handle ) {

                        if ( in_array( $handle, $remove_handles['styles'] ) ) {
                            unset( $wp_styles->queue[$key] );
                        }
                    }
                }
            }
        }
    }

    public function um_settings_structure_conflict_remover( $settings_structure ) {

        $um_core_pages = UM()->config()->core_pages;
        $wp_pages      = UM()->query()->wp_pages();
        $um_pages      = array();

        foreach ( $um_core_pages as $page_s => $page ) {

            $page_id    = UM()->options()->get_core_page_id( $page_s );
            $wp_page_id = (int)UM()->options()->get( $page_id );
            $page_title = ! empty( $page['title'] ) ? $page['title'] : '?';

            $um_pages['/' . $wp_pages[$wp_page_id] . '/'] = $page_title;
        }

        $plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_list = array();

        foreach ( $active_plugins as $plugin_path ) {

            $folder = explode( '/', $plugin_path );
            if ( in_array( $folder[0], array( 'ultimate-member', 'um-conflict-remover', 'um-conflict-remover-main' ))) continue;

            if ( isset( $plugins[$plugin_path]['Name'] )) {
                $plugin_list['plugins/' . $folder[0] . '/'] = $plugins[$plugin_path]['Name'];
            }
        }

        asort( $plugin_list );

        $settings_structure['access']['sections']['other']['fields'][] = array(
                        'id'      => 'um_conflict_remover_um_pages',
                        'type'    => 'select',
                        'multi'   => true,
                        'options' => $um_pages,
                        'label'   => __( 'Conflict Remover - UM Form Pages with conflict', 'ultimate-member' ),
                        'tooltip' => __( 'Select single or multiple UM Form Pages where you will remove conflicting Plugins', 'ultimate-member' )
                    );

        $settings_structure['access']['sections']['other']['fields'][] = array(
                        'id'            => 'um_conflict_remover_backend_pages',
                        'type'          => 'checkbox',
                        'label'         => __( 'Conflict Remover - UM Backend pages', 'ultimate-member' ),
                        'tooltip'       => __( 'Click to include the UM backend pages in remove conflicting Plugins.', 'ultimate-member' ),
                        );

        $settings_structure['access']['sections']['other']['fields'][] = array(
                        'id'      => 'um_conflict_remover_um_page_ids',
                        'type'    => 'text',
                        'label'   => __( 'Conflict Remover - Page/Post IDs with conflict', 'ultimate-member' ),
                        'tooltip' => __( 'Enter comma separated Page/Post IDs where you will remove conflicting Plugins', 'ultimate-member' )
                    );

        $settings_structure['access']['sections']['other']['fields'][] = array(
                        'id'      => 'um_conflict_remover_plugins',
                        'type'    => 'select',
                        'multi'   => true,
                        'options' => $plugin_list,
                        'label'   => __( 'Conflict Remover - Active Plugins to exclude', 'ultimate-member' ),
                        'tooltip' => __( 'Select single or multiple Plugins for exclusion of their conflicting scripts and styles.', 'ultimate-member' )
                    );

        $settings_structure['access']['sections']['other']['fields'][] = array(
                        'id'            => 'um_conflict_remover_dequeue_um_select2',
                        'type'          => 'checkbox',
                        'label'         => __( 'Conflict Remover - Dequeue UM select2 scripts', 'ultimate-member' ),
                        'tooltip'       => __( 'Click to dequeue UM select2 scripts.', 'ultimate-member' ),
                    );

        return $settings_structure;
    }

}

new UM_Conflict_Remover();
