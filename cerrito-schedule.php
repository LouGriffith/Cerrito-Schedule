<?php
/**
 * Plugin Name: Cerrito Schedule Display
 * Plugin URI: https://cerritoentertainment.com
 * Description: Schedule shortcode for displaying events (works with ACF)
 * Version: 4.5
 * Author: Cerrito Entertainment
 * Author URI: https://cerritoentertainment.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===============================================
// FRONT-END SCHEDULE DISPLAY SHORTCODE
// ===============================================

function cerrito_schedule_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_coming_soon' => 'yes',
        'days_ahead' => '30',
        'location' => '', // Location slug or ID
        'game_type' => '', // Game type slug or name
    ), $atts);
    
    // Auto-detect location from current post if on single location page
    if (empty($atts['location']) && is_singular('location')) {
        global $post;
        $atts['location'] = $post->post_name; // Use the slug
    }
    
    ob_start();
    ?>
    
    <style>
    .cerrito-schedule {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .cerrito-legend {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 30px;
        text-align: center;
        font-size: 14px;
    }
    
    .cerrito-date-section {
        margin-bottom: 40px;
    }
    
    .cerrito-date-header {
        background: #333;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 1.2em;
        font-weight: bold;
    }
    
    .cerrito-event {
        background: white;
        border-left: 4px solid #0066cc;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .cerrito-game-logo {
        margin-bottom: 15px;
    }
    
    .cerrito-game-logo img {
        max-width: 150px;
        height: auto;
    }
    
    .cerrito-event:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .cerrito-event.trivia {
        border-left-color: #ff6b6b;
    }
    
    .cerrito-event.bingo {
        border-left-color: #4ecdc4;
    }
    
    .cerrito-event-type {
        font-weight: bold;
        font-size: 1.1em;
        margin-bottom: 8px;
    }
    
    .cerrito-game-emoji {
        font-size: 1.3em;
        margin-right: 8px;
    }
    
    .cerrito-event-theme {
        color: #e91e63;
        font-style: italic;
        margin-left: 10px;
    }
    
    .cerrito-event-venue {
        font-size: 1.05em;
        margin: 8px 0;
    }
    
    .cerrito-event-venue a {
        color: #0066cc;
        text-decoration: none;
        font-weight: 500;
    }
    
    .cerrito-event-venue a:hover {
        text-decoration: underline;
    }
    
    .cerrito-event-time {
        color: #666;
        margin-left: 5px;
    }
    
    .cerrito-coming-soon {
        margin-top: 60px;
        padding-top: 40px;
        border-top: 3px solid #ddd;
    }
    
    .cerrito-coming-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .cerrito-coming-item {
        text-align: center;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .cerrito-coming-item img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    @media (max-width: 768px) {
        .cerrito-coming-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <div class="cerrito-schedule">
        
        <?php
        // Get current date
        $today = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+' . $atts['days_ahead'] . ' days'));
        
        // Build query args
        $query_args = array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => 'event_date',
                    'value' => array($today, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );
        
        // Query for upcoming events
        $events = get_posts($query_args);
        
        // Filter by location if specified
        if (!empty($atts['location']) && $events) {
            // Get location post by slug or ID
            if (is_numeric($atts['location'])) {
                $location_id = intval($atts['location']);
            } else {
                $location_post = get_page_by_path($atts['location'], OBJECT, 'location');
                $location_id = $location_post ? $location_post->ID : 0;
            }
            
            if ($location_id) {
                $events = array_filter($events, function($event) use ($location_id) {
                    $event_location = get_field('event_location', $event->ID);
                    
                    // Handle ACF relationship field
                    if (is_array($event_location) && !empty($event_location)) {
                        $event_location = $event_location[0];
                    }
                    
                    if (is_object($event_location)) {
                        return $event_location->ID == $location_id;
                    }
                    
                    return false;
                });
            }
        }
        
        // Filter by game type if specified
        if (!empty($atts['game_type']) && $events) {
            $events = array_filter($events, function($event) use ($atts) {
                $types = get_the_terms($event->ID, 'game_type');
                
                if ($types && !is_wp_error($types)) {
                    foreach ($types as $type) {
                        // Match by slug or name
                        if ($type->slug === $atts['game_type'] || 
                            strtolower($type->name) === strtolower($atts['game_type'])) {
                            return true;
                        }
                    }
                }
                
                return false;
            });
        }
        
        if ($events) {
            // Group events by date, then by type
            $events_by_date = array();
            
            foreach ($events as $event) {
                $event_date = get_field('event_date', $event->ID);
                
                // Handle different date formats
                if ($event_date && strlen($event_date) === 8 && is_numeric($event_date)) {
                    // Convert Ymd to Y-m-d
                    $year = substr($event_date, 0, 4);
                    $month = substr($event_date, 4, 2);
                    $day = substr($event_date, 6, 2);
                    $event_date = "$year-$month-$day";
                } elseif ($event_date && strpos($event_date, '/') !== false) {
                    // Convert m/d/Y to Y-m-d
                    $date_obj = DateTime::createFromFormat('m/d/Y', $event_date);
                    if ($date_obj) {
                        $event_date = $date_obj->format('Y-m-d');
                    }
                }
                
                if (!isset($events_by_date[$event_date])) {
                    $events_by_date[$event_date] = array();
                }
                
                // Get event type
                $types = get_the_terms($event->ID, 'game_type');
                $event_type = '';
                if ($types && !is_wp_error($types)) {
                    $type_names = array();
                    foreach ($types as $type) {
                        $type_names[] = $type->name;
                    }
                    $event_type = implode(', ', $type_names);
                }
                
                // Get special theme
                $special_theme = get_field('special_theme', $event->ID);
                
                // Create a key for grouping (type + theme)
                $group_key = $event_type . ($special_theme ? ' - ' . $special_theme : '');
                
                if (!isset($events_by_date[$event_date][$group_key])) {
                    $events_by_date[$event_date][$group_key] = array(
                        'type' => $event_type,
                        'theme' => $special_theme,
                        'class' => '',
                        'events' => array()
                    );
                    
                    // Set class based on type
                    $type_lower = strtolower($event_type);
                    if (strpos($type_lower, 'trivia') !== false) {
                        $events_by_date[$event_date][$group_key]['class'] = 'trivia';
                    } elseif (strpos($type_lower, 'bingo') !== false) {
                        $events_by_date[$event_date][$group_key]['class'] = 'bingo';
                    }
                }
                
                // Add this event to the group
                $events_by_date[$event_date][$group_key]['events'][] = $event;
            }
            
            // Now display grouped events
            foreach ($events_by_date as $date => $groups) {
                // Format date for display
                if ($date) {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                    $display_date = $date_obj ? $date_obj->format('l, M j') : $date;
                } else {
                    $display_date = 'Coming Soon';
                }
                
                echo '<div class="cerrito-date-section">';
                echo '<div class="cerrito-date-header">' . strtoupper($display_date) . '</div>';
                
                // Display each event type group
                foreach ($groups as $group_key => $group) {
                    $event_class = 'cerrito-event ' . $group['class'];
                    
                    // Get the game type term to fetch logo and emoji
                    $game_logo = '';
                    $game_emoji = '';
                    if ($group['type']) {
                        // Try to get term by name first
                        $term = get_term_by('name', $group['type'], 'game_type');
                        
                        // If that fails, try by slug
                        if (!$term) {
                            $term = get_term_by('slug', sanitize_title($group['type']), 'game_type');
                        }
                        
                        if ($term) {
                            // ACF stores taxonomy term fields differently
                            $game_logo_field = get_field('game_logo', 'game_type_' . $term->term_id);
                            $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
                            
                            // Handle different ACF return formats for images
                            if (is_array($game_logo_field)) {
                                // Array format - get URL
                                $game_logo = isset($game_logo_field['url']) ? $game_logo_field['url'] : '';
                            } elseif (is_numeric($game_logo_field)) {
                                // Attachment ID - get URL
                                $game_logo = wp_get_attachment_url($game_logo_field);
                            } elseif (is_string($game_logo_field) && !empty($game_logo_field)) {
                                // Already a URL
                                $game_logo = $game_logo_field;
                            }
                            
                            // DEBUG - remove this after testing
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Game Type: ' . $group['type']);
                                error_log('Term ID: ' . $term->term_id);
                                error_log('Logo Field: ' . print_r($game_logo_field, true));
                                error_log('Final Logo: ' . $game_logo);
                            }
                        }
                    }
                    ?>
                    
                    <div class="<?php echo esc_attr($event_class); ?>">
                        <?php if ($game_logo): ?>
                            <div class="cerrito-game-logo">
                                <img src="<?php echo esc_url($game_logo); ?>" alt="<?php echo esc_attr($group['type']); ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="cerrito-event-type">
                            <?php if ($game_emoji): ?>
                                <span class="cerrito-game-emoji"><?php echo esc_html($game_emoji); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($group['type']); ?>
                            <?php if ($group['theme']): ?>
                                <span class="cerrito-event-theme"><?php echo esc_html($group['theme']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($group['events'] as $event): 
                            $event_time = get_field('event_time', $event->ID);
                            $location = get_field('event_location', $event->ID);
                            
                            // Handle ACF relationship field (returns array or post object)
                            if (is_array($location) && !empty($location)) {
                                $location = $location[0];
                            }
                        ?>
                            <?php if ($location): ?>
                                <div class="cerrito-event-venue">
                                    <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                                        <?php echo esc_html($location->post_title); ?>
                                    </a>
                                    <?php if ($event_time): ?>
                                        → <span class="cerrito-event-time"><?php echo esc_html($event_time); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php
                }
                
                echo '</div>'; // Close date section
            }
            
        } else {
            echo '<p>No upcoming events scheduled at this time. Check back soon!</p>';
        }
        ?>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('cerrito_schedule', 'cerrito_schedule_shortcode');

// ===============================================
// RECURRING SCHEDULE SHORTCODE
// ===============================================

function cerrito_recurring_schedule_shortcode($atts) {
    $atts = shortcode_atts(array(
        'location' => '', // Optional location filter
        'game_type' => '', // Optional game type filter
    ), $atts);
    
    // Auto-detect location from current post if on single location page
    if (empty($atts['location']) && is_singular('location')) {
        global $post;
        $atts['location'] = $post->post_name; // Use the slug
    }
    
    ob_start();
    ?>
    
    <style>
    .cerrito-recurring-schedule {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .cerrito-recurring-day {
        margin-bottom: 50px;
    }
    
    .cerrito-recurring-day-header {
        background: #333;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 1.2em;
        font-weight: bold;
    }
    
    .cerrito-recurring-occurrence {
        margin-bottom: 30px;
    }
    
    .cerrito-recurring-occurrence-title {
        font-weight: bold;
        font-size: 1.2em;
        margin-bottom: 15px;
        padding: 10px 0;
        border-bottom: 2px solid #ddd;
    }
    
    .cerrito-recurring-emoji {
        font-size: 1.3em;
        margin-right: 8px;
    }
    
    .cerrito-recurring-theme {
        color: #e91e63;
        font-style: italic;
        margin-left: 10px;
    }
    
    .cerrito-recurring-location-card {
        background: white;
        border-left: 4px solid #0066cc;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 20px;
        align-items: start;
    }
    
    .cerrito-recurring-location-card.trivia {
        border-left-color: #ff6b6b;
    }
    
    .cerrito-recurring-location-card.bingo {
        border-left-color: #4ecdc4;
    }
    
    .cerrito-location-logo {
        width: 100%;
        max-width: 120px;
    }
    
    .cerrito-location-logo img {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }
    
    .cerrito-location-details {
        flex: 1;
    }
    
    .cerrito-location-name {
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .cerrito-location-name a {
        color: #0066cc;
        text-decoration: none;
    }
    
    .cerrito-location-name a:hover {
        text-decoration: underline;
    }
    
    .cerrito-location-time {
        font-size: 1.05em;
        color: #333;
        margin-bottom: 8px;
    }
    
    .cerrito-location-address {
        color: #666;
        margin-bottom: 8px;
        font-size: 0.95em;
    }
    
    .cerrito-location-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 10px 0;
    }
    
    .cerrito-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
        background: #ff6b6b;
        color: white;
    }
    
    .cerrito-location-notes {
        margin-top: 10px;
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
        font-size: 0.95em;
    }
    
    .cerrito-coming-soon-section {
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #ddd;
    }
    
    .cerrito-coming-soon-title {
        font-weight: bold;
        font-size: 1.1em;
        margin-bottom: 15px;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .cerrito-recurring-location-card {
            grid-template-columns: 1fr;
        }
        
        .cerrito-location-logo {
            max-width: 200px;
            margin: 0 auto;
        }
    }
    </style>
    
    <div class="cerrito-recurring-schedule">
        
        <?php
        // Query for recurring events
        $query_args = array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'is_recurring',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $events = get_posts($query_args);
        
        // Filter by location if specified
        if (!empty($atts['location']) && $events) {
            if (is_numeric($atts['location'])) {
                $location_id = intval($atts['location']);
            } else {
                $location_post = get_page_by_path($atts['location'], OBJECT, 'location');
                $location_id = $location_post ? $location_post->ID : 0;
            }
            
            if ($location_id) {
                $events = array_filter($events, function($event) use ($location_id) {
                    $event_location = get_field('event_location', $event->ID);
                    
                    if (is_array($event_location) && !empty($event_location)) {
                        $event_location = $event_location[0];
                    }
                    
                    if (is_object($event_location)) {
                        return $event_location->ID == $location_id;
                    }
                    
                    return false;
                });
            }
        }
        
        // Filter by game type if specified
        if (!empty($atts['game_type']) && $events) {
            $events = array_filter($events, function($event) use ($atts) {
                $types = get_the_terms($event->ID, 'game_type');
                
                if ($types && !is_wp_error($types)) {
                    foreach ($types as $type) {
                        if ($type->slug === $atts['game_type'] || 
                            strtolower($type->name) === strtolower($atts['game_type'])) {
                            return true;
                        }
                    }
                }
                
                return false;
            });
        }
        
        if ($events) {
            // Group events by "when" taxonomy, then by game type
            $events_by_day = array();
            $coming_soon = array();
            
            // Define day order
            $day_order = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
            
            foreach ($events as $event) {
                // Get the "when" taxonomy
                $when_terms = get_the_terms($event->ID, 'when');
                
                if ($when_terms && !is_wp_error($when_terms)) {
                    foreach ($when_terms as $when_term) {
                        $day = $when_term->name;
                        
                        if (!isset($events_by_day[$day])) {
                            $events_by_day[$day] = array();
                        }
                        
                        // Get event type
                        $types = get_the_terms($event->ID, 'game_type');
                        $event_type = '';
                        $event_class = '';
                        
                        if ($types && !is_wp_error($types)) {
                            $type_names = array();
                            foreach ($types as $type) {
                                $type_names[] = $type->name;
                            }
                            $event_type = implode(', ', $type_names);
                            
                            $type_lower = strtolower($event_type);
                            if (strpos($type_lower, 'trivia') !== false) {
                                $event_class = 'trivia';
                            } elseif (strpos($type_lower, 'bingo') !== false) {
                                $event_class = 'bingo';
                            }
                        }
                        
                        $special_theme = get_field('special_theme', $event->ID);
                        $group_key = $event_type . ($special_theme ? ' - ' . $special_theme : '');
                        
                        if (!isset($events_by_day[$day][$group_key])) {
                            $events_by_day[$day][$group_key] = array(
                                'type' => $event_type,
                                'theme' => $special_theme,
                                'class' => $event_class,
                                'events' => array()
                            );
                        }
                        
                        $events_by_day[$day][$group_key]['events'][] = $event;
                    }
                } else {
                    // No "when" set - add to coming soon
                    $coming_soon[] = $event;
                }
            }
            
            // Display events in day order
            foreach ($day_order as $day) {
                if (!isset($events_by_day[$day])) {
                    continue;
                }
                
                echo '<div class="cerrito-recurring-day">';
                echo '<div class="cerrito-recurring-day-header">EVERY ' . strtoupper($day) . '</div>';
                
                foreach ($events_by_day[$day] as $group) {
                    $event_class = $group['class'];
                    
                    // Get game type emoji
                    $game_emoji = '';
                    if ($group['type']) {
                        $term = get_term_by('name', $group['type'], 'game_type');
                        
                        if (!$term) {
                            $term = get_term_by('slug', sanitize_title($group['type']), 'game_type');
                        }
                        
                        if ($term) {
                            $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
                        }
                    }
                    ?>
                    
                    <div class="cerrito-recurring-occurrence">
                        <div class="cerrito-recurring-occurrence-title">
                            <?php if ($game_emoji): ?>
                                <span class="cerrito-recurring-emoji"><?php echo esc_html($game_emoji); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($group['type']); ?>
                            <?php if ($group['theme']): ?>
                                <span class="cerrito-recurring-theme"><?php echo esc_html($group['theme']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($group['events'] as $event): 
                            $event_time = get_field('event_time', $event->ID);
                            $location = get_field('event_location', $event->ID);
                            $age_restriction = get_field('age_restriction', $event->ID);
                            $special_notes = get_field('special_notes', $event->ID);
                            
                            if (is_array($location) && !empty($location)) {
                                $location = $location[0];
                            }
                            
                            if ($location && is_object($location)):
                                // Get location details - use correct field names
                                $location_logo_field = get_field('location_logo', $location->ID);
                                if (!$location_logo_field) {
                                    $location_logo_field = get_field('logo', $location->ID);
                                }
                                if (!$location_logo_field) {
                                    $location_logo_field = get_field('sponsor_logo', $location->ID);
                                }
                                
                                $location_address = get_field('location_address', $location->ID);
                                if (!$location_address) {
                                    $location_address = get_field('address', $location->ID);
                                }
                                
                                // Handle different ACF image formats for location logo
                                $location_logo = '';
                                if (is_array($location_logo_field)) {
                                    $location_logo = isset($location_logo_field['url']) ? $location_logo_field['url'] : '';
                                } elseif (is_numeric($location_logo_field)) {
                                    $location_logo = wp_get_attachment_url($location_logo_field);
                                } elseif (is_string($location_logo_field) && !empty($location_logo_field)) {
                                    $location_logo = $location_logo_field;
                                }
                        ?>
                            <div class="cerrito-recurring-location-card <?php echo esc_attr($event_class); ?>">
                                <?php if ($location_logo): ?>
                                    <div class="cerrito-location-logo">
                                        <img src="<?php echo esc_url($location_logo); ?>" alt="<?php echo esc_attr($location->post_title); ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="cerrito-location-logo">
                                        <!-- No logo available -->
                                    </div>
                                <?php endif; ?>
                                
                                <div class="cerrito-location-details">
                                    <div class="cerrito-location-name">
                                        <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                                            <?php echo esc_html($location->post_title); ?>
                                        </a>
                                    </div>
                                    
                                    <?php if ($event_time): ?>
                                        <div class="cerrito-location-time">
                                            🕐 <?php echo esc_html($event_time); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($location_address): ?>
                                        <div class="cerrito-location-address">
                                            📍 <?php echo esc_html(str_replace(array("\r\n", "\n", "\r"), " ", $location_address)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($age_restriction): ?>
                                        <div class="cerrito-location-badges">
                                            <span class="cerrito-badge"><?php echo esc_html($age_restriction); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($special_notes): ?>
                                        <div class="cerrito-location-notes">
                                            <?php echo wp_kses_post($special_notes); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; ?>
                        
                    </div>
                    
                    <?php
                }
                
                echo '</div>'; // Close day section
            }
            
            // Coming Soon section
            if (!empty($coming_soon)) {
                echo '<div class="cerrito-coming-soon-section">';
                echo '<div class="cerrito-recurring-occurrence-title">Coming Soon</div>';
                
                foreach ($coming_soon as $event) {
                    // Get event type
                    $types = get_the_terms($event->ID, 'game_type');
                    $event_type = '';
                    $event_class = '';
                    
                    if ($types && !is_wp_error($types)) {
                        $type_names = array();
                        foreach ($types as $type) {
                            $type_names[] = $type->name;
                        }
                        $event_type = implode(', ', $type_names);
                        
                        $type_lower = strtolower($event_type);
                        if (strpos($type_lower, 'trivia') !== false) {
                            $event_class = 'trivia';
                        } elseif (strpos($type_lower, 'bingo') !== false) {
                            $event_class = 'bingo';
                        }
                    }
                    
                    $special_theme = get_field('special_theme', $event->ID);
                    $location = get_field('event_location', $event->ID);
                    $age_restriction = get_field('age_restriction', $event->ID);
                    $special_notes = get_field('special_notes', $event->ID);
                    
                    if (is_array($location) && !empty($location)) {
                        $location = $location[0];
                    }
                    
                    if ($location && is_object($location)):
                        // Use correct field names
                        $location_logo_field = get_field('location_logo', $location->ID);
                        if (!$location_logo_field) {
                            $location_logo_field = get_field('logo', $location->ID);
                        }
                        if (!$location_logo_field) {
                            $location_logo_field = get_field('sponsor_logo', $location->ID);
                        }
                        
                        $location_address = get_field('location_address', $location->ID);
                        if (!$location_address) {
                            $location_address = get_field('address', $location->ID);
                        }
                        
                        // Handle different ACF image formats
                        $location_logo = '';
                        if (is_array($location_logo_field)) {
                            $location_logo = isset($location_logo_field['url']) ? $location_logo_field['url'] : '';
                        } elseif (is_numeric($location_logo_field)) {
                            $location_logo = wp_get_attachment_url($location_logo_field);
                        } elseif (is_string($location_logo_field) && !empty($location_logo_field)) {
                            $location_logo = $location_logo_field;
                        }
                        
                        // Get game emoji
                        $game_emoji = '';
                        if ($event_type) {
                            $term = get_term_by('name', $event_type, 'game_type');
                            if (!$term) {
                                $term = get_term_by('slug', sanitize_title($event_type), 'game_type');
                            }
                            if ($term) {
                                $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
                            }
                        }
                    ?>
                        <div style="margin-bottom: 20px;">
                            <div class="cerrito-recurring-occurrence-title" style="font-size: 1em; padding: 5px 0;">
                                <?php if ($game_emoji): ?>
                                    <span class="cerrito-recurring-emoji"><?php echo esc_html($game_emoji); ?></span>
                                <?php endif; ?>
                                <?php echo esc_html($event_type); ?>
                                <?php if ($special_theme): ?>
                                    <span class="cerrito-recurring-theme"><?php echo esc_html($special_theme); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="cerrito-recurring-location-card <?php echo esc_attr($event_class); ?>">
                                <?php if ($location_logo): ?>
                                    <div class="cerrito-location-logo">
                                        <img src="<?php echo esc_url($location_logo); ?>" alt="<?php echo esc_attr($location->post_title); ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="cerrito-location-logo"></div>
                                <?php endif; ?>
                                
                                <div class="cerrito-location-details">
                                    <div class="cerrito-location-name">
                                        <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                                            <?php echo esc_html($location->post_title); ?>
                                        </a>
                                    </div>
                                    
                                    <div class="cerrito-location-time">
                                        🕐 TBA
                                    </div>
                                    
                                    <?php if ($location_address): ?>
                                        <div class="cerrito-location-address">
                                            📍 <?php echo esc_html(str_replace(array("\r\n", "\n", "\r"), " ", $location_address)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($age_restriction): ?>
                                        <div class="cerrito-location-badges">
                                            <span class="cerrito-badge"><?php echo esc_html($age_restriction); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($special_notes): ?>
                                        <div class="cerrito-location-notes">
                                            <?php echo wp_kses_post($special_notes); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php
                    endif;
                }
                
                echo '</div>';
            }
            
        } else {
            echo '<p>No recurring events scheduled at this time.</p>';
        }
        ?>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('cerrito_recurring_schedule', 'cerrito_recurring_schedule_shortcode');

// ===============================================
// MASTER SCHEDULE SHORTCODE (COMBINES ALL)
// ===============================================

function cerrito_master_schedule_shortcode($atts) {
    $atts = shortcode_atts(array(
        'location' => '', // Optional location filter
        'game_type' => '', // Optional game type filter
        'days_ahead' => '30', // How far ahead to show one-time events
        'show_game_logo' => 'no', // Show game type logo
        'show_game_description' => 'no', // Show game type description
    ), $atts);
    
    // Auto-detect location from current post if on single location page
    if (empty($atts['location']) && is_singular('location')) {
        global $post;
        $atts['location'] = $post->post_name; // Use the slug
    }
    
    ob_start();
    ?>
    
    <style>
    .cerrito-master-schedule {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .cerrito-master-day {
        margin-bottom: 50px;
    }
    
    .cerrito-master-day-header {
        background: #333;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 1.2em;
        font-weight: bold;
    }
    
    .cerrito-master-section {
        margin-bottom: 30px;
    }
    
    .cerrito-master-section-title {
        font-size: 0.9em;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .cerrito-master-occurrence {
        margin-bottom: 20px;
    }
    
    .cerrito-master-occurrence-title {
        font-weight: bold;
        font-size: 1.1em;
        margin-bottom: 15px;
        padding: 5px 0;
    }
    
    .cerrito-master-game-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .cerrito-master-game-logo {
        flex-shrink: 0;
    }
    
    .cerrito-master-game-logo img {
        max-width: 100px;
        height: auto;
    }
    
    .cerrito-master-game-info {
        flex: 1;
    }
    
    .cerrito-master-game-description {
        color: #666;
        font-size: 0.95em;
        font-weight: normal;
        margin-top: 8px;
        line-height: 1.5;
    }
    
    .cerrito-master-emoji {
        font-size: 1.3em;
        margin-right: 8px;
    }
    
    .cerrito-master-theme {
        color: #e91e63;
        font-style: italic;
        margin-left: 10px;
    }
    
    .cerrito-master-date {
        color: #0066cc;
        font-size: 0.9em;
        margin-left: 10px;
    }
    
    .cerrito-master-location-card {
        background: white;
        border-left: 4px solid #0066cc;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 20px;
        align-items: start;
    }
    
    .cerrito-master-location-card.trivia {
        border-left-color: #ff6b6b;
    }
    
    .cerrito-master-location-card.bingo {
        border-left-color: #4ecdc4;
    }
    
    .cerrito-master-location-logo {
        width: 100%;
        max-width: 120px;
    }
    
    .cerrito-master-location-logo img {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }
    
    .cerrito-master-location-details {
        flex: 1;
    }
    
    .cerrito-master-location-name {
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .cerrito-master-location-name a {
        color: #0066cc;
        text-decoration: none;
    }
    
    .cerrito-master-location-name a:hover {
        text-decoration: underline;
    }
    
    .cerrito-master-location-time {
        font-size: 1.05em;
        color: #333;
        margin-bottom: 8px;
    }
    
    .cerrito-master-location-address {
        color: #666;
        margin-bottom: 8px;
        font-size: 0.95em;
    }
    
    .cerrito-master-location-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 10px 0;
    }
    
    .cerrito-master-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
        background: #ff6b6b;
        color: white;
    }
    
    .cerrito-master-location-notes {
        margin-top: 10px;
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
        font-size: 0.95em;
    }
    
    @media (max-width: 768px) {
        .cerrito-master-location-card {
            grid-template-columns: 1fr;
        }
        
        .cerrito-master-location-logo {
            max-width: 200px;
            margin: 0 auto;
        }
    }
    </style>
    
    <div class="cerrito-master-schedule">
        
        <?php
        // Define day order
        $day_order = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        
        // Get current date for upcoming events
        $today = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+' . $atts['days_ahead'] . ' days'));
        
        // Query for all events
        $all_events = get_posts(array(
            'post_type' => 'event',
            'posts_per_page' => -1,
        ));
        
        // Filter by location if specified
        if (!empty($atts['location']) && $all_events) {
            if (is_numeric($atts['location'])) {
                $location_id = intval($atts['location']);
            } else {
                $location_post = get_page_by_path($atts['location'], OBJECT, 'location');
                $location_id = $location_post ? $location_post->ID : 0;
            }
            
            if ($location_id) {
                $all_events = array_filter($all_events, function($event) use ($location_id) {
                    $event_location = get_field('event_location', $event->ID);
                    
                    if (is_array($event_location) && !empty($event_location)) {
                        $event_location = $event_location[0];
                    }
                    
                    if (is_object($event_location)) {
                        return $event_location->ID == $location_id;
                    }
                    
                    return false;
                });
            }
        }
        
        // Filter by game type if specified
        if (!empty($atts['game_type']) && $all_events) {
            $all_events = array_filter($all_events, function($event) use ($atts) {
                $types = get_the_terms($event->ID, 'game_type');
                
                if ($types && !is_wp_error($types)) {
                    foreach ($types as $type) {
                        if ($type->slug === $atts['game_type'] || 
                            strtolower($type->name) === strtolower($atts['game_type'])) {
                            return true;
                        }
                    }
                }
                
                return false;
            });
        }
        
        // Organize events by day of week
        $events_by_day = array();
        
        foreach ($all_events as $event) {
            $is_recurring = get_field('is_recurring', $event->ID);
            $event_date = get_field('event_date', $event->ID);
            
            // Handle date format
            if ($event_date && strlen($event_date) === 8 && is_numeric($event_date)) {
                $year = substr($event_date, 0, 4);
                $month = substr($event_date, 4, 2);
                $day = substr($event_date, 6, 2);
                $event_date = "$year-$month-$day";
            } elseif ($event_date && strpos($event_date, '/') !== false) {
                $date_obj = DateTime::createFromFormat('m/d/Y', $event_date);
                if ($date_obj) {
                    $event_date = $date_obj->format('Y-m-d');
                }
            }
            
            // Determine which day(s) this event belongs to
            $days = array();
            
            if ($is_recurring) {
                // Get "when" taxonomy
                $when_terms = get_the_terms($event->ID, 'when');
                if ($when_terms && !is_wp_error($when_terms)) {
                    foreach ($when_terms as $term) {
                        $days[] = $term->name;
                    }
                }
            } else {
                // One-time event - check if it's in date range
                if ($event_date && $event_date >= $today && $event_date <= $end_date) {
                    $date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
                    if ($date_obj) {
                        $day_name = $date_obj->format('l'); // Monday, Tuesday, etc.
                        $days[] = $day_name;
                    }
                }
            }
            
            // Add event to appropriate days
            foreach ($days as $day) {
                if (!isset($events_by_day[$day])) {
                    $events_by_day[$day] = array('recurring' => array(), 'one_time' => array());
                }
                
                // Get event details for grouping
                $types = get_the_terms($event->ID, 'game_type');
                $event_type = '';
                $event_class = '';
                
                if ($types && !is_wp_error($types)) {
                    $type_names = array();
                    foreach ($types as $type) {
                        $type_names[] = $type->name;
                    }
                    $event_type = implode(', ', $type_names);
                    
                    $type_lower = strtolower($event_type);
                    if (strpos($type_lower, 'trivia') !== false) {
                        $event_class = 'trivia';
                    } elseif (strpos($type_lower, 'bingo') !== false) {
                        $event_class = 'bingo';
                    }
                }
                
                $special_theme = get_field('special_theme', $event->ID);
                $group_key = $event_type . ($special_theme ? ' - ' . $special_theme : '');
                
                $event_data = array(
                    'event' => $event,
                    'type' => $event_type,
                    'theme' => $special_theme,
                    'class' => $event_class,
                    'date' => $event_date,
                    'group_key' => $group_key
                );
                
                if ($is_recurring) {
                    if (!isset($events_by_day[$day]['recurring'][$group_key])) {
                        $events_by_day[$day]['recurring'][$group_key] = array(
                            'type' => $event_type,
                            'theme' => $special_theme,
                            'class' => $event_class,
                            'events' => array()
                        );
                    }
                    $events_by_day[$day]['recurring'][$group_key]['events'][] = $event;
                } else {
                    if (!isset($events_by_day[$day]['one_time'][$group_key])) {
                        $events_by_day[$day]['one_time'][$group_key] = array(
                            'type' => $event_type,
                            'theme' => $special_theme,
                            'class' => $event_class,
                            'dates' => array()
                        );
                    }
                    if (!isset($events_by_day[$day]['one_time'][$group_key]['dates'][$event_date])) {
                        $events_by_day[$day]['one_time'][$group_key]['dates'][$event_date] = array();
                    }
                    $events_by_day[$day]['one_time'][$group_key]['dates'][$event_date][] = $event;
                }
            }
        }
        
        // Display by day
        foreach ($day_order as $day) {
            if (!isset($events_by_day[$day]) || 
                (empty($events_by_day[$day]['recurring']) && empty($events_by_day[$day]['one_time']))) {
                continue;
            }
            
            echo '<div class="cerrito-master-day">';
            echo '<div class="cerrito-master-day-header">' . strtoupper($day) . 'S</div>';
            
            // Show recurring events first
            if (!empty($events_by_day[$day]['recurring'])) {
                echo '<div class="cerrito-master-section">';
                echo '<div class="cerrito-master-section-title">Every ' . $day . '</div>';
                
                foreach ($events_by_day[$day]['recurring'] as $group) {
                    echo cerrito_render_event_group($group, true, $atts['show_game_logo'], $atts['show_game_description']);
                }
                
                echo '</div>';
            }
            
            // Show one-time events
            if (!empty($events_by_day[$day]['one_time'])) {
                echo '<div class="cerrito-master-section">';
                echo '<div class="cerrito-master-section-title">Upcoming</div>';
                
                foreach ($events_by_day[$day]['one_time'] as $group) {
                    // Sort dates
                    ksort($group['dates']);
                    
                    foreach ($group['dates'] as $date => $events) {
                        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                        $formatted_date = $date_obj ? $date_obj->format('M j') : $date;
                        
                        $group_with_date = $group;
                        $group_with_date['events'] = $events;
                        $group_with_date['show_date'] = $formatted_date;
                        
                        echo cerrito_render_event_group($group_with_date, false, $atts['show_game_logo'], $atts['show_game_description']);
                    }
                }
                
                echo '</div>';
            }
            
            echo '</div>';
        }
        ?>
    </div>
    
    <?php
    return ob_get_clean();
}

function cerrito_render_event_group($group, $is_recurring, $show_game_logo = 'no', $show_game_description = 'no') {
    ob_start();
    
    // Get game emoji, logo, and description
    $game_emoji = '';
    $game_logo = '';
    $game_description = '';
    
    if ($group['type']) {
        $term = get_term_by('name', $group['type'], 'game_type');
        if (!$term) {
            $term = get_term_by('slug', sanitize_title($group['type']), 'game_type');
        }
        if ($term) {
            $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
            
            if ($show_game_logo === 'yes') {
                $game_logo_field = get_field('game_logo', 'game_type_' . $term->term_id);
                
                if (is_array($game_logo_field)) {
                    $game_logo = isset($game_logo_field['url']) ? $game_logo_field['url'] : '';
                } elseif (is_numeric($game_logo_field)) {
                    $game_logo = wp_get_attachment_url($game_logo_field);
                } elseif (is_string($game_logo_field) && !empty($game_logo_field)) {
                    $game_logo = $game_logo_field;
                }
            }
            
            if ($show_game_description === 'yes') {
                $game_description = $term->description; // Native WordPress taxonomy description
            }
        }
    }
    ?>
    
    <div class="cerrito-master-occurrence">
        <?php if ($game_logo || $game_description): ?>
            <div class="cerrito-master-game-header">
                <?php if ($game_logo): ?>
                    <div class="cerrito-master-game-logo">
                        <img src="<?php echo esc_url($game_logo); ?>" alt="<?php echo esc_attr($group['type']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="cerrito-master-game-info">
                    <div class="cerrito-master-occurrence-title">
                        <?php if ($game_emoji): ?>
                            <span class="cerrito-master-emoji"><?php echo esc_html($game_emoji); ?></span>
                        <?php endif; ?>
                        <?php echo esc_html($group['type']); ?>
                        <?php if ($group['theme']): ?>
                            <span class="cerrito-master-theme"><?php echo esc_html($group['theme']); ?></span>
                        <?php endif; ?>
                        <?php if (!$is_recurring && isset($group['show_date'])): ?>
                            <span class="cerrito-master-date"><?php echo esc_html($group['show_date']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($game_description): ?>
                        <div class="cerrito-master-game-description">
                            <?php echo esc_html($game_description); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="cerrito-master-occurrence-title">
                <?php if ($game_emoji): ?>
                    <span class="cerrito-master-emoji"><?php echo esc_html($game_emoji); ?></span>
                <?php endif; ?>
                <?php echo esc_html($group['type']); ?>
                <?php if ($group['theme']): ?>
                    <span class="cerrito-master-theme"><?php echo esc_html($group['theme']); ?></span>
                <?php endif; ?>
                <?php if (!$is_recurring && isset($group['show_date'])): ?>
                    <span class="cerrito-master-date"><?php echo esc_html($group['show_date']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($group['events'] as $event): 
            $event_time = get_field('event_time', $event->ID);
            $location = get_field('event_location', $event->ID);
            $age_restriction = get_field('age_restriction', $event->ID);
            $special_notes = get_field('special_notes', $event->ID);
            
            if (is_array($location) && !empty($location)) {
                $location = $location[0];
            }
            
            if ($location && is_object($location)):
                $location_logo_field = get_field('location_logo', $location->ID);
                if (!$location_logo_field) {
                    $location_logo_field = get_field('logo', $location->ID);
                }
                
                $location_address = get_field('location_address', $location->ID);
                if (!$location_address) {
                    $location_address = get_field('address', $location->ID);
                }
                
                $location_logo = '';
                if (is_array($location_logo_field)) {
                    $location_logo = isset($location_logo_field['url']) ? $location_logo_field['url'] : '';
                } elseif (is_numeric($location_logo_field)) {
                    $location_logo = wp_get_attachment_url($location_logo_field);
                } elseif (is_string($location_logo_field) && !empty($location_logo_field)) {
                    $location_logo = $location_logo_field;
                }
        ?>
            <div class="cerrito-master-location-card <?php echo esc_attr($group['class']); ?>">
                <?php if ($location_logo): ?>
                    <div class="cerrito-master-location-logo">
                        <img src="<?php echo esc_url($location_logo); ?>" alt="<?php echo esc_attr($location->post_title); ?>">
                    </div>
                <?php else: ?>
                    <div class="cerrito-master-location-logo"></div>
                <?php endif; ?>
                
                <div class="cerrito-master-location-details">
                    <div class="cerrito-master-location-name">
                        <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                            <?php echo esc_html($location->post_title); ?>
                        </a>
                    </div>
                    
                    <?php if ($event_time): ?>
                        <div class="cerrito-master-location-time">
                            🕐 <?php echo esc_html($event_time); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($location_address): ?>
                        <div class="cerrito-master-location-address">
                            📍 <?php echo esc_html(str_replace(array("\r\n", "\n", "\r"), ' ', $location_address)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($age_restriction): ?>
                        <div class="cerrito-master-location-badges">
                            <span class="cerrito-master-badge"><?php echo esc_html($age_restriction); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($special_notes): ?>
                        <div class="cerrito-master-location-notes">
                            <?php echo wp_kses_post($special_notes); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php 
            endif;
        endforeach; ?>
    </div>
    
    <?php
    return ob_get_clean();
}

add_shortcode('cerrito_master_schedule', 'cerrito_master_schedule_shortcode');

// ===============================================
// TODAY'S EVENTS SHORTCODE
// ===============================================

function cerrito_today_schedule_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_game_logo' => 'yes',
        'show_game_description' => 'no',
        'style' => 'full', // 'full' or 'compact'
    ), $atts);
    
    ob_start();
    
    // Get today's day name
    $today = date('l'); // Monday, Tuesday, etc.
    $today_date = date('Y-m-d');
    
    ?>
    
    <style>
    .cerrito-today-schedule {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* Compact Style */
    .cerrito-today-schedule.compact {
        max-width: 600px;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-header {
        background: transparent;
        color: #333;
        padding: 10px 0;
        text-align: left;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-header h2 {
        font-size: 1.3em;
        font-weight: 600;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-occurrence {
        margin-bottom: 25px;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-occurrence-title {
        font-size: 1.1em;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-location-simple {
        padding-left: 15px;
        margin-bottom: 5px;
        font-size: 1em;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-location-simple a {
        color: #333;
        text-decoration: none;
        font-weight: 500;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-location-simple a:hover {
        color: #0066cc;
    }
    
    .cerrito-today-schedule.compact .cerrito-today-empty {
        text-align: left;
        padding: 20px 0;
    }
    
    /* Full Style */
    
    .cerrito-today-header {
        background: #333;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        text-align: center;
    }
    
    .cerrito-today-header h2 {
        margin: 0;
        font-size: 1.5em;
    }
    
    .cerrito-today-header .day-name {
        font-size: 2em;
        font-weight: bold;
    }
    
    .cerrito-today-header .date {
        font-size: 1em;
        opacity: 0.9;
        margin-top: 5px;
    }
    
    .cerrito-today-occurrence {
        margin-bottom: 30px;
    }
    
    .cerrito-today-game-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .cerrito-today-game-logo {
        flex-shrink: 0;
    }
    
    .cerrito-today-game-logo img {
        max-width: 100px;
        height: auto;
    }
    
    .cerrito-today-game-info {
        flex: 1;
    }
    
    .cerrito-today-occurrence-title {
        font-weight: bold;
        font-size: 1.2em;
        margin-bottom: 8px;
    }
    
    .cerrito-today-emoji {
        font-size: 1.3em;
        margin-right: 8px;
    }
    
    .cerrito-today-theme {
        color: #e91e63;
        font-style: italic;
        margin-left: 10px;
    }
    
    .cerrito-today-game-description {
        color: #666;
        font-size: 0.95em;
        line-height: 1.5;
        margin-top: 5px;
    }
    
    .cerrito-today-location-card {
        background: white;
        border-left: 4px solid #0066cc;
        padding: 20px;
        margin-bottom: 15px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 20px;
        align-items: start;
    }
    
    .cerrito-today-location-card.trivia {
        border-left-color: #ff6b6b;
    }
    
    .cerrito-today-location-card.bingo {
        border-left-color: #4ecdc4;
    }
    
    .cerrito-today-location-logo {
        width: 100%;
        max-width: 120px;
    }
    
    .cerrito-today-location-logo img {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }
    
    .cerrito-today-location-details {
        flex: 1;
    }
    
    .cerrito-today-location-name {
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .cerrito-today-location-name a {
        color: #0066cc;
        text-decoration: none;
    }
    
    .cerrito-today-location-name a:hover {
        text-decoration: underline;
    }
    
    .cerrito-today-location-time {
        font-size: 1.05em;
        color: #333;
        margin-bottom: 8px;
    }
    
    .cerrito-today-location-address {
        color: #666;
        margin-bottom: 8px;
        font-size: 0.95em;
    }
    
    .cerrito-today-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
        background: #ff6b6b;
        color: white;
        margin-top: 10px;
    }
    
    .cerrito-today-location-notes {
        margin-top: 10px;
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
        font-size: 0.95em;
    }
    
    .cerrito-today-empty {
        text-align: center;
        padding: 40px;
        color: #666;
        font-size: 1.1em;
    }
    
    @media (max-width: 768px) {
        .cerrito-today-location-card {
            grid-template-columns: 1fr;
        }
        
        .cerrito-today-location-logo {
            max-width: 200px;
            margin: 0 auto;
        }
    }
    </style>
    
    <div class="cerrito-today-schedule <?php echo esc_attr($atts['style']); ?>">
        
        <div class="cerrito-today-header">
            <?php if ($atts['style'] === 'compact'): ?>
                <h2><?php echo $today . ' ' . date('M j'); ?></h2>
            <?php else: ?>
                <div class="day-name"><?php echo strtoupper($today); ?></div>
                <div class="date"><?php echo date('F j, Y'); ?></div>
            <?php endif; ?>
        </div>
        
        <?php
        // Get all events
        $all_events = get_posts(array(
            'post_type' => 'event',
            'posts_per_page' => -1,
        ));
        
        $today_events = array();
        
        foreach ($all_events as $event) {
            $include_event = false;
            
            // Check if recurring and matches today
            $is_recurring = get_field('is_recurring', $event->ID);
            
            if ($is_recurring) {
                $when_terms = get_the_terms($event->ID, 'when');
                if ($when_terms && !is_wp_error($when_terms)) {
                    foreach ($when_terms as $term) {
                        if ($term->name === $today) {
                            $include_event = true;
                            break;
                        }
                    }
                }
            } else {
                // Check if one-time event happening today
                $event_date = get_field('event_date', $event->ID);
                
                // Handle date formats
                if ($event_date && strlen($event_date) === 8 && is_numeric($event_date)) {
                    $year = substr($event_date, 0, 4);
                    $month = substr($event_date, 4, 2);
                    $day = substr($event_date, 6, 2);
                    $event_date = "$year-$month-$day";
                } elseif ($event_date && strpos($event_date, '/') !== false) {
                    $date_obj = DateTime::createFromFormat('m/d/Y', $event_date);
                    if ($date_obj) {
                        $event_date = $date_obj->format('Y-m-d');
                    }
                }
                
                if ($event_date === $today_date) {
                    $include_event = true;
                }
            }
            
            if ($include_event) {
                // Get event details for grouping
                $types = get_the_terms($event->ID, 'game_type');
                $event_type = '';
                $event_class = '';
                
                if ($types && !is_wp_error($types)) {
                    $type_names = array();
                    foreach ($types as $type) {
                        $type_names[] = $type->name;
                    }
                    $event_type = implode(', ', $type_names);
                    
                    $type_lower = strtolower($event_type);
                    if (strpos($type_lower, 'trivia') !== false) {
                        $event_class = 'trivia';
                    } elseif (strpos($type_lower, 'bingo') !== false) {
                        $event_class = 'bingo';
                    }
                }
                
                $special_theme = get_field('special_theme', $event->ID);
                $group_key = $event_type . ($special_theme ? ' - ' . $special_theme : '');
                
                if (!isset($today_events[$group_key])) {
                    $today_events[$group_key] = array(
                        'type' => $event_type,
                        'theme' => $special_theme,
                        'class' => $event_class,
                        'events' => array()
                    );
                }
                
                $today_events[$group_key]['events'][] = $event;
            }
        }
        
        if (!empty($today_events)) {
            // COMPACT STYLE
            if ($atts['style'] === 'compact') {
                foreach ($today_events as $group) {
                    // Get game emoji
                    $game_emoji = '';
                    if ($group['type']) {
                        $term = get_term_by('name', $group['type'], 'game_type');
                        if (!$term) {
                            $term = get_term_by('slug', sanitize_title($group['type']), 'game_type');
                        }
                        if ($term) {
                            $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
                        }
                    }
                    ?>
                    
                    <div class="cerrito-today-occurrence">
                        <div class="cerrito-today-occurrence-title">
                            <?php if ($game_emoji): ?>
                                <span class="cerrito-today-emoji"><?php echo esc_html($game_emoji); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($group['type']); ?>
                            <?php if ($group['theme']): ?>
                                <span class="cerrito-today-theme"><?php echo esc_html($group['theme']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($group['events'] as $event): 
                            $event_time = get_field('event_time', $event->ID);
                            $location = get_field('event_location', $event->ID);
                            
                            if (is_array($location) && !empty($location)) {
                                $location = $location[0];
                            }
                            
                            if ($location && is_object($location)):
                        ?>
                            <div class="cerrito-today-location-simple">
                                <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                                    <?php echo esc_html($location->post_title); ?>
                                </a>
                                <?php if ($event_time): ?>
                                    → <?php echo esc_html($event_time); ?>
                                <?php endif; ?>
                            </div>
                        <?php 
                            endif;
                        endforeach; ?>
                    </div>
                    
                    <?php
                }
            } else {
                // FULL STYLE (existing code)
                foreach ($today_events as $group) {
                // Get game emoji, logo, and description
                $game_emoji = '';
                $game_logo = '';
                $game_description = '';
                
                if ($group['type']) {
                    $term = get_term_by('name', $group['type'], 'game_type');
                    if (!$term) {
                        $term = get_term_by('slug', sanitize_title($group['type']), 'game_type');
                    }
                    if ($term) {
                        $game_emoji = get_field('game_emoji', 'game_type_' . $term->term_id);
                        
                        if ($atts['show_game_logo'] === 'yes') {
                            $game_logo_field = get_field('game_logo', 'game_type_' . $term->term_id);
                            
                            if (is_array($game_logo_field)) {
                                $game_logo = isset($game_logo_field['url']) ? $game_logo_field['url'] : '';
                            } elseif (is_numeric($game_logo_field)) {
                                $game_logo = wp_get_attachment_url($game_logo_field);
                            } elseif (is_string($game_logo_field) && !empty($game_logo_field)) {
                                $game_logo = $game_logo_field;
                            }
                        }
                        
                        if ($atts['show_game_description'] === 'yes') {
                            $game_description = $term->description;
                        }
                    }
                }
                ?>
                
                <div class="cerrito-today-occurrence">
                    <?php if ($game_logo || $game_description): ?>
                        <div class="cerrito-today-game-header">
                            <?php if ($game_logo): ?>
                                <div class="cerrito-today-game-logo">
                                    <img src="<?php echo esc_url($game_logo); ?>" alt="<?php echo esc_attr($group['type']); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="cerrito-today-game-info">
                                <div class="cerrito-today-occurrence-title">
                                    <?php if ($game_emoji): ?>
                                        <span class="cerrito-today-emoji"><?php echo esc_html($game_emoji); ?></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($group['type']); ?>
                                    <?php if ($group['theme']): ?>
                                        <span class="cerrito-today-theme"><?php echo esc_html($group['theme']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($game_description): ?>
                                    <div class="cerrito-today-game-description">
                                        <?php echo esc_html($game_description); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="cerrito-today-occurrence-title">
                            <?php if ($game_emoji): ?>
                                <span class="cerrito-today-emoji"><?php echo esc_html($game_emoji); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($group['type']); ?>
                            <?php if ($group['theme']): ?>
                                <span class="cerrito-today-theme"><?php echo esc_html($group['theme']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach ($group['events'] as $event): 
                        $event_time = get_field('event_time', $event->ID);
                        $location = get_field('event_location', $event->ID);
                        $age_restriction = get_field('age_restriction', $event->ID);
                        $special_notes = get_field('special_notes', $event->ID);
                        
                        if (is_array($location) && !empty($location)) {
                            $location = $location[0];
                        }
                        
                        if ($location && is_object($location)):
                            $location_logo_field = get_field('location_logo', $location->ID);
                            if (!$location_logo_field) {
                                $location_logo_field = get_field('logo', $location->ID);
                            }
                            
                            $location_address = get_field('location_address', $location->ID);
                            if (!$location_address) {
                                $location_address = get_field('address', $location->ID);
                            }
                            
                            $location_logo = '';
                            if (is_array($location_logo_field)) {
                                $location_logo = isset($location_logo_field['url']) ? $location_logo_field['url'] : '';
                            } elseif (is_numeric($location_logo_field)) {
                                $location_logo = wp_get_attachment_url($location_logo_field);
                            } elseif (is_string($location_logo_field) && !empty($location_logo_field)) {
                                $location_logo = $location_logo_field;
                            }
                    ?>
                        <div class="cerrito-today-location-card <?php echo esc_attr($group['class']); ?>">
                            <?php if ($location_logo): ?>
                                <div class="cerrito-today-location-logo">
                                    <img src="<?php echo esc_url($location_logo); ?>" alt="<?php echo esc_attr($location->post_title); ?>">
                                </div>
                            <?php else: ?>
                                <div class="cerrito-today-location-logo"></div>
                            <?php endif; ?>
                            
                            <div class="cerrito-today-location-details">
                                <div class="cerrito-today-location-name">
                                    <a href="<?php echo esc_url(get_permalink($location->ID)); ?>">
                                        <?php echo esc_html($location->post_title); ?>
                                    </a>
                                </div>
                                
                                <?php if ($event_time): ?>
                                    <div class="cerrito-today-location-time">
                                        🕐 <?php echo esc_html($event_time); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location_address): ?>
                                    <div class="cerrito-today-location-address">
                                        📍 <?php echo esc_html(str_replace(array("\r\n", "\n", "\r"), ' ', $location_address)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($age_restriction): ?>
                                    <span class="cerrito-today-badge"><?php echo esc_html($age_restriction); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($special_notes): ?>
                                    <div class="cerrito-today-location-notes">
                                        <?php echo wp_kses_post($special_notes); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; ?>
                </div>
                
                <?php
                }
            }
        } else {
            ?>
            <div class="cerrito-today-empty">
                <p>No events scheduled for today. Check back tomorrow!</p>
            </div>
            <?php
        }
        ?>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('cerrito_today', 'cerrito_today_schedule_shortcode');

// ===============================================
// THEMED ROUNDS SHORTCODE
// ===============================================

function cerrito_themed_rounds_shortcode($atts) {
    $atts = shortcode_atts(array(
        'days_ahead' => '60',
        'game_type' => '', // Optional filter
    ), $atts);
    
    ob_start();
    
    ?>
    
    <style>
    .cerrito-themed-rounds {
        max-width: 600px;
        margin: 0 auto;
    }
    
    .cerrito-themed-round {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 15px;
        align-items: center;
        margin-bottom: 15px;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    
    .cerrito-themed-round:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .cerrito-themed-date {
        text-align: center;
        padding: 10px;
        border-radius: 6px;
        background: #0066cc;
        color: white;
    }
    
    .cerrito-themed-date.trivia {
        background: #ff6b6b;
    }
    
    .cerrito-themed-date.bingo {
        background: #4ecdc4;
    }
    
    .cerrito-themed-day {
        font-size: 0.85em;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    
    .cerrito-themed-date-num {
        font-size: 1.8em;
        font-weight: bold;
        line-height: 1;
    }
    
    .cerrito-themed-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .cerrito-themed-emoji {
        font-size: 2em;
        line-height: 1;
    }
    
    .cerrito-themed-theme {
        flex: 1;
    }
    
    .cerrito-themed-theme a {
        color: #333;
        text-decoration: none;
        font-size: 1.1em;
        font-weight: 600;
        display: block;
    }
    
    .cerrito-themed-theme a:hover {
        color: #0066cc;
    }
    
    .cerrito-themed-empty {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    
    @media (max-width: 600px) {
        .cerrito-themed-round {
            grid-template-columns: 90px 1fr;
            gap: 10px;
            padding: 12px;
        }
        
        .cerrito-themed-date-num {
            font-size: 1.5em;
        }
        
        .cerrito-themed-emoji {
            font-size: 1.5em;
        }
        
        .cerrito-themed-theme a {
            font-size: 1em;
        }
    }
    </style>
    
    <div class="cerrito-themed-rounds">
        
        <?php
        // Get current date
        $today = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+' . $atts['days_ahead'] . ' days'));
        
        // Query for upcoming events with themes
        $query_args = array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'event_date',
                    'value' => array($today, $end_date),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ),
                array(
                    'key' => 'special_theme',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'special_theme',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );
        
        $events = get_posts($query_args);
        
        // Filter by game type if specified
        if (!empty($atts['game_type']) && $events) {
            $events = array_filter($events, function($event) use ($atts) {
                $types = get_the_terms($event->ID, 'game_type');
                
                if ($types && !is_wp_error($types)) {
                    foreach ($types as $type) {
                        if ($type->slug === $atts['game_type'] || 
                            strtolower($type->name) === strtolower($atts['game_type'])) {
                            return true;
                        }
                    }
                }
                
                return false;
            });
        }
        
        if ($events) {
            foreach ($events as $event) {
                $event_date = get_field('event_date', $event->ID);
                $special_theme = get_field('special_theme', $event->ID);
                
                if (!$special_theme) {
                    continue;
                }
                
                // Handle date formats
                if ($event_date && strlen($event_date) === 8 && is_numeric($event_date)) {
                    $year = substr($event_date, 0, 4);
                    $month = substr($event_date, 4, 2);
                    $day = substr($event_date, 6, 2);
                    $event_date = "$year-$month-$day";
                } elseif ($event_date && strpos($event_date, '/') !== false) {
                    $date_obj = DateTime::createFromFormat('m/d/Y', $event_date);
                    if ($date_obj) {
                        $event_date = $date_obj->format('Y-m-d');
                    }
                }
                
                if (!$event_date) {
                    continue;
                }
                
                $date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
                if (!$date_obj) {
                    continue;
                }
                
                $day_name = strtoupper(substr($date_obj->format('l'), 0, 3)); // WED, THU, etc.
                $date_num = $date_obj->format('n/j'); // 2/11, 3/5, etc.
                
                // Get event type for styling
                $types = get_the_terms($event->ID, 'game_type');
                $event_class = '';
                $game_emoji = '';
                
                if ($types && !is_wp_error($types)) {
                    $type_lower = strtolower($types[0]->name);
                    if (strpos($type_lower, 'trivia') !== false) {
                        $event_class = 'trivia';
                    } elseif (strpos($type_lower, 'bingo') !== false) {
                        $event_class = 'bingo';
                    }
                    
                    // Get emoji
                    $game_emoji = get_field('game_emoji', 'game_type_' . $types[0]->term_id);
                }
                ?>
                
                <div class="cerrito-themed-round">
                    <div class="cerrito-themed-date <?php echo esc_attr($event_class); ?>">
                        <div class="cerrito-themed-day"><?php echo esc_html($day_name); ?></div>
                        <div class="cerrito-themed-date-num"><?php echo esc_html($date_num); ?></div>
                    </div>
                    
                    <div class="cerrito-themed-info">
                        <?php if ($game_emoji): ?>
                            <div class="cerrito-themed-emoji"><?php echo esc_html($game_emoji); ?></div>
                        <?php endif; ?>
                        
                        <div class="cerrito-themed-theme">
                            <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                <?php echo esc_html($special_theme); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php
            }
        } else {
            ?>
            <div class="cerrito-themed-empty">
                <p>No themed rounds scheduled at this time.</p>
            </div>
            <?php
        }
        ?>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('cerrito_themed_rounds', 'cerrito_themed_rounds_shortcode');
