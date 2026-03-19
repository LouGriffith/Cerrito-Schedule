<?php
/**
 * Admin Columns and Filters for Events
 * Loaded by cerrito-schedule.php -- not a standalone plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_event_columns( $columns ) {
    unset( $columns['date'] );
    $columns['event_date']     = 'Date';
    $columns['event_time']     = 'Time';
    $columns['event_location'] = 'Location';
    $columns['is_recurring']   = 'Recurring';
    $columns['game_type']      = 'Type';
    return $columns;
}
add_filter( 'manage_event_posts_columns', 'cerrito_event_columns' );

function cerrito_event_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'event_date':
            $date = get_field( 'event_date', $post_id );
            if ( $date ) {
                if ( strlen( $date ) === 8 && is_numeric( $date ) ) {
                    $date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
                } elseif ( strpos( $date, '/' ) !== false ) {
                    $d = DateTime::createFromFormat( 'm/d/Y', $date );
                    if ( $d ) $date = $d->format( 'Y-m-d' );
                }
                $d = DateTime::createFromFormat( 'Y-m-d', $date );
                echo $d ? esc_html( $d->format( 'M j, Y' ) ) : esc_html( $date );
            } else {
                echo '--';
            }
            break;

        case 'event_time':
            $time = get_field( 'event_time', $post_id );
            echo $time ? esc_html( $time ) : '--';
            break;

        case 'event_location':
            $location = get_field( 'event_location', $post_id );
            if ( is_array( $location ) && ! empty( $location ) ) $location = $location[0];
            if ( $location && is_object( $location ) ) {
                echo '<a href="' . get_edit_post_link( $location->ID ) . '">' . esc_html( $location->post_title ) . '</a>';
            } else {
                echo '--';
            }
            break;

        case 'is_recurring':
            $recurring = get_field( 'is_recurring', $post_id );
            if ( $recurring ) {
                echo 'Yes';
                $when_terms = get_the_terms( $post_id, 'when' );
                if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                    $days = wp_list_pluck( $when_terms, 'name' );
                    echo '<br><small>' . esc_html( implode( ', ', $days ) ) . '</small>';
                }
            } else {
                echo '--';
            }
            break;

        case 'game_type':
            $types = get_the_terms( $post_id, 'game_type' );
            if ( $types && ! is_wp_error( $types ) ) {
                $links = [];
                foreach ( $types as $type ) {
                    $links[] = '<a href="' . admin_url( 'edit.php?post_type=event&game_type=' . $type->slug ) . '">' . esc_html( $type->name ) . '</a>';
                }
                echo implode( ', ', $links );
            } else {
                echo '--';
            }
            break;
    }
}
add_action( 'manage_event_posts_custom_column', 'cerrito_event_column_content', 10, 2 );

function cerrito_event_sortable_columns( $columns ) {
    $columns['event_date']     = 'event_date';
    $columns['event_time']     = 'event_time';
    $columns['event_location'] = 'event_location';
    $columns['is_recurring']   = 'is_recurring';
    return $columns;
}
add_filter( 'manage_edit-event_sortable_columns', 'cerrito_event_sortable_columns' );

function cerrito_event_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    $orderby = $query->get( 'orderby' );
    $meta_keys = [ 'event_date', 'event_time', 'event_location', 'is_recurring' ];
    if ( in_array( $orderby, $meta_keys, true ) ) {
        $query->set( 'meta_key', $orderby );
        $query->set( 'orderby', 'meta_value' );
    }
}
add_action( 'pre_get_posts', 'cerrito_event_orderby' );

function cerrito_event_filters() {
    global $typenow;
    if ( $typenow !== 'event' ) return;

    $locations = get_posts( [ 'post_type' => 'location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
    if ( $locations ) {
        $current = isset( $_GET['event_location_filter'] ) ? (int) $_GET['event_location_filter'] : '';
        echo '<select name="event_location_filter">';
        echo '<option value="">All Locations</option>';
        foreach ( $locations as $loc ) {
            $sel = selected( $current, $loc->ID, false );
            echo '<option value="' . esc_attr( $loc->ID ) . '"' . $sel . '>' . esc_html( $loc->post_title ) . '</option>';
        }
        echo '</select>';
    }

    $current_recurring = isset( $_GET['recurring_filter'] ) ? sanitize_text_field( $_GET['recurring_filter'] ) : '';
    echo '<select name="recurring_filter">';
    echo '<option value="">All Events</option>';
    echo '<option value="yes"' . selected( $current_recurring, 'yes', false ) . '>Recurring Only</option>';
    echo '<option value="no"'  . selected( $current_recurring, 'no',  false ) . '>One-Time Only</option>';
    echo '</select>';
}
add_action( 'restrict_manage_posts', 'cerrito_event_filters' );

function cerrito_event_filter_query( $query ) {
    global $pagenow, $typenow;
    if ( $pagenow !== 'edit.php' || $typenow !== 'event' || ! $query->is_main_query() ) return;

    $meta_query = [];

    if ( ! empty( $_GET['event_location_filter'] ) ) {
        $meta_query[] = [
            'key'     => 'event_location',
            'value'   => '"' . intval( $_GET['event_location_filter'] ) . '"',
            'compare' => 'LIKE',
        ];
    }

    if ( ! empty( $_GET['recurring_filter'] ) ) {
        if ( $_GET['recurring_filter'] === 'yes' ) {
            $meta_query[] = [ 'key' => 'is_recurring', 'value' => '1', 'compare' => '=' ];
        } elseif ( $_GET['recurring_filter'] === 'no' ) {
            $meta_query[] = [ 'key' => 'is_recurring', 'compare' => 'NOT EXISTS' ];
        }
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'cerrito_event_filter_query' );
