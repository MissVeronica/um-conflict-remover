<?php
/**
 * Plugin Name:     Ultimate Member - Conflict Remover
 * Description:     Extension to Ultimate Member to exclude conflicting scripts and styles from UM pages.
 * Version:         1.0.0 
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;


if( is_admin()) {

    add_filter( 'um_settings_structure',   'um_settings_structure_conflict_remover', 10, 1 );

} else {

    add_action( 'wp_print_footer_scripts', 'um_conflict_remover_scripts_and_styles', 9 );
    add_action( 'wp_print_scripts',        'um_conflict_remover_scripts_and_styles', 9 );
    add_action( 'wp_print_styles',         'um_conflict_remover_scripts_and_styles', 9 );
}

function um_conflict_remover_scripts_and_styles() {

    global $wp_scripts, $wp_styles;
  
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

    if ( $remove ) {

        $um_plugins = UM()->options()->get( 'um_conflict_remover_plugins' );
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

function um_settings_structure_conflict_remover( $settings_structure ) {

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

        $plugin_list['plugins/' . $folder[0] . '/'] = $plugins[$plugin_path]['Name'];
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
                    'id'      => 'um_conflict_remover_plugins',
                    'type'    => 'select',
                    'multi'   => true,
                    'options' => $plugin_list,
                    'label'   => __( 'Conflict Remover - Active Plugins to exclude', 'ultimate-member' ),
                    'tooltip' => __( 'Select single or multiple Plugins for exclusion of their conflicting scripts and styles.', 'ultimate-member' )
                );

    return $settings_structure;
}
