<?php
/**
 * Plugin Name: Cerrito Events Admin Columns
 * Description: Add custom columns and filters to Events admin list
 * Version: 1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===============================================
// ADD CUSTOM COLUMNS TO EVENTS LIST
// ===============================================

function cerrito_event_columns($columns) {
    // Remove default columns we don't need
    unset($columns['date']);
    
    // Add new columns
    $columns['event_date'] = 'Date';
    $columns['event_time'] = 'Time';
    $columns['event_location'] = 'Location';
    $columns['is_recurring'] = 'Recurring';
    $columns['game_type'] = 'Type';
    
    return $columns;
}
add_filter('manage_event_posts_columns', 'cerrito_event_columns');

// ===============================================
// POPULATE CUSTOM COLUMNS
// ===============================================

function cerrito_event_column_content($column, $post_id) {
    switch ($column) {
        case 'event_date':
            $date = get_field('event_date', $post_id);
            
            if ($date) {
                // Handle different date formats
                if (strlen($date) === 8 && is_numeric($date)) {
                    // Ymd format
                    $year = substr($date, 0, 4);
                    $month = substr($date, 4, 2);
                    $day = substr($date, 6, 2);
                    $date = "$year-$month-$day";
                } elseif (strpos($date, '/') !== false) {
                    // m/d/Y format
                    $date_obj = DateTime::createFromFormat('m/d/Y', $date);
                    if ($date_obj) {
                        $date = $date_obj->format('Y-m-d');
                    }
                }
                
                $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                if ($date_obj) {
                    echo $date_obj->format('M j, Y');
                } else {
                    echo esc_html($date);
                }
            } else {
                echo '—';
            }
            break;
            
        case 'event_time':
            $time = get_field('event_time', $post_id);
            echo $time ? esc_html($time) : '—';
            break;
            
        case 'event_location':
            $location = get_field('event_location', $post_id);
            
            // Handle ACF relationship field
            if (is_array($location) && !empty($location)) {
                $location = $location[0];
            }
            
            if ($location && is_object($location)) {
                echo '<a href="' . get_edit_post_link($location->ID) . '">' . esc_html($location->post_title) . '</a>';
            } else {
                echo '—';
            }
            break;
            
        case 'is_recurring':
            $recurring = get_field('is_recurring', $post_id);
            
            if ($recurring) {
                echo '✓ Yes';
                
                // Show which days
                $when_terms = get_the_terms($post_id, 'when');
                if ($when_terms && !is_wp_error($when_terms)) {
                    $days = array();
                    foreach ($when_terms as $term) {
                        $days[] = $term->name;
                    }
                    echo '<br><small>' . implode(', ', $days) . '</small>';
                }
            } else {
                echo '—';
            }
            break;
            
        case 'game_type':
            $types = get_the_terms($post_id, 'game_type');
            
            if ($types && !is_wp_error($types)) {
                $type_list = array();
                foreach ($types as $type) {
                    $type_list[] = '<a href="' . admin_url('edit.php?post_type=event&game_type=' . $type->slug) . '">' . esc_html($type->name) . '</a>';
                }
                echo implode(', ', $type_list);
            } else {
                echo '—';
            }
            break;
    }
}
add_action('manage_event_posts_custom_column', 'cerrito_event_column_content', 10, 2);

// ===============================================
// MAKE COLUMNS SORTABLE
// ===============================================

function cerrito_event_sortable_columns($columns) {
    $columns['event_date'] = 'event_date';
    $columns['event_time'] = 'event_time';
    $columns['event_location'] = 'event_location';
    $columns['is_recurring'] = 'is_recurring';
    
    return $columns;
}
add_filter('manage_edit-event_sortable_columns', 'cerrito_event_sortable_columns');

// ===============================================
// HANDLE SORTING
// ===============================================

function cerrito_event_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('event_date' === $orderby) {
        $query->set('meta_key', 'event_date');
        $query->set('orderby', 'meta_value');
    }
    
    if ('event_time' === $orderby) {
        $query->set('meta_key', 'event_time');
        $query->set('orderby', 'meta_value');
    }
    
    if ('event_location' === $orderby) {
        $query->set('meta_key', 'event_location');
        $query->set('orderby', 'meta_value');
    }
    
    if ('is_recurring' === $orderby) {
        $query->set('meta_key', 'is_recurring');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'cerrito_event_orderby');

// ===============================================
// ADD FILTERS (DROPDOWNS)
// ===============================================

function cerrito_event_filters() {
    global $typenow;
    
    if ($typenow == 'event') {
        
        // Location filter
        $locations = get_posts(array(
            'post_type' => 'location',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        if ($locations) {
            $current_location = isset($_GET['event_location_filter']) ? $_GET['event_location_filter'] : '';
            
            echo '<select name="event_location_filter">';
            echo '<option value="">All Locations</option>';
            
            foreach ($locations as $location) {
                $selected = ($current_location == $location->ID) ? ' selected="selected"' : '';
                echo '<option value="' . $location->ID . '"' . $selected . '>' . esc_html($location->post_title) . '</option>';
            }
            
            echo '</select>';
        }
        
        // Recurring filter
        $current_recurring = isset($_GET['recurring_filter']) ? $_GET['recurring_filter'] : '';
        
        echo '<select name="recurring_filter">';
        echo '<option value="">All Events</option>';
        echo '<option value="yes"' . ($current_recurring == 'yes' ? ' selected="selected"' : '') . '>Recurring Only</option>';
        echo '<option value="no"' . ($current_recurring == 'no' ? ' selected="selected"' : '') . '>One-Time Only</option>';
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'cerrito_event_filters');

// ===============================================
// APPLY FILTERS TO QUERY
// ===============================================

function cerrito_event_filter_query($query) {
    global $pagenow, $typenow;
    
    if ($pagenow == 'edit.php' && $typenow == 'event' && $query->is_main_query()) {
        
        // Location filter
        if (isset($_GET['event_location_filter']) && !empty($_GET['event_location_filter'])) {
            $location_id = intval($_GET['event_location_filter']);
            
            $query->set('meta_query', array(
                array(
                    'key' => 'event_location',
                    'value' => '"' . $location_id . '"',
                    'compare' => 'LIKE'
                )
            ));
        }
        
        // Recurring filter
        if (isset($_GET['recurring_filter']) && !empty($_GET['recurring_filter'])) {
            if ($_GET['recurring_filter'] == 'yes') {
                $query->set('meta_query', array(
                    array(
                        'key' => 'is_recurring',
                        'value' => '1',
                        'compare' => '='
                    )
                ));
            } elseif ($_GET['recurring_filter'] == 'no') {
                $query->set('meta_query', array(
                    array(
                        'key' => 'is_recurring',
                        'compare' => 'NOT EXISTS'
                    )
                ));
            }
        }
    }
}
add_action('pre_get_posts', 'cerrito_event_filter_query');
