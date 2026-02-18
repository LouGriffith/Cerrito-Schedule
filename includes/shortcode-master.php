<?php
/**
 * Shortcode: [cerrito_master_schedule]
 *
 * Combines recurring and upcoming one-time events, grouped by day of week.
 *
 * Parameters:
 *   location              string  Location slug or ID (auto-detected on single location pages)
 *   game_type             string  Filter by game type slug or name
 *   days_ahead            int     How far ahead to show one-time events (default 30)
 *   show_game_logo        string  'yes'|'no'
 *   show_game_description string  'yes'|'no'
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_master_schedule_shortcode( array $atts ): string {
    $atts = shortcode_atts( [
        'location'              => '',
        'game_type'             => '',
        'days_ahead'            => '30',
        'show_game_logo'        => 'no',
        'show_game_description' => 'no',
    ], $atts );

    if ( empty( $atts['location'] ) && is_singular( 'location' ) ) {
        global $post;
        $atts['location'] = $post->post_name;
    }

    cerrito_enqueue_styles();
    ob_start();

    $day_order  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
    $today      = date( 'Y-m-d' );
    $end_date   = date( 'Y-m-d', strtotime( '+' . (int) $atts['days_ahead'] . ' days' ) );
    $days_ahead = (int) $atts['days_ahead'];

    $all_events = get_posts( [ 'post_type' => 'event', 'posts_per_page' => -1 ] );

    if ( ! empty( $atts['location'] ) ) {
        $location_id = cerrito_resolve_location_id( $atts['location'] );
        if ( $location_id ) $all_events = cerrito_filter_by_location( $all_events, $location_id );
    }

    if ( ! empty( $atts['game_type'] ) ) {
        $all_events = cerrito_filter_by_game_type( $all_events, $atts['game_type'] );
    }

    $events_by_day = [];

    foreach ( $all_events as $event ) {
        $is_recurring = (bool) get_field( 'is_recurring', $event->ID );
        $event_date   = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
        $days         = [];

        if ( $is_recurring ) {
            $when_terms = get_the_terms( $event->ID, 'when' );
            if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                foreach ( $when_terms as $t ) $days[] = $t->name;
            }
        } elseif ( $event_date && $event_date >= $today && $event_date <= $end_date ) {
            $d = DateTime::createFromFormat( 'Y-m-d', $event_date );
            if ( $d ) $days[] = $d->format( 'l' );
        }

        foreach ( $days as $day ) {
            if ( ! isset( $events_by_day[ $day ] ) ) {
                $events_by_day[ $day ] = [ 'recurring' => [], 'one_time' => [] ];
            }

            $event_type    = cerrito_get_event_type_string( $event->ID );
            $special_theme = get_field( 'special_theme', $event->ID );

            if ( empty( $special_theme ) && $is_recurring ) {
                $types = get_the_terms( $event->ID, 'game_type' );
                if ( $types && ! is_wp_error( $types ) ) {
                    $special_theme = cerrito_get_next_themed_date_for_day( $types[0]->term_id, $day, $days_ahead );
                }
            }

            $group_key = $event_type . ( $special_theme ? ' - ' . $special_theme : '' );

            if ( $is_recurring ) {
                if ( ! isset( $events_by_day[ $day ]['recurring'][ $group_key ] ) ) {
                    $events_by_day[ $day ]['recurring'][ $group_key ] = [
                        'type'   => $event_type,
                        'theme'  => $special_theme,
                        'class'  => cerrito_get_event_class( $event_type ),
                        'events' => [],
                    ];
                }
                $events_by_day[ $day ]['recurring'][ $group_key ]['events'][] = $event;
            } else {
                if ( ! isset( $events_by_day[ $day ]['one_time'][ $group_key ] ) ) {
                    $events_by_day[ $day ]['one_time'][ $group_key ] = [
                        'type'   => $event_type,
                        'theme'  => $special_theme,
                        'class'  => cerrito_get_event_class( $event_type ),
                        'dates'  => [],
                    ];
                }
                $events_by_day[ $day ]['one_time'][ $group_key ]['dates'][ $event_date ][] = $event;
            }
        }
    }

    echo '<div class="cerrito-master-schedule">';

    foreach ( $day_order as $day ) {
        if ( empty( $events_by_day[ $day ]['recurring'] ) && empty( $events_by_day[ $day ]['one_time'] ) ) {
            continue;
        }

        echo '<div class="cerrito-master-day">';
        echo '<div class="cerrito-day-header">' . esc_html( strtoupper( $day ) . 'S' ) . '</div>';

        if ( ! empty( $events_by_day[ $day ]['recurring'] ) ) {
            echo '<div class="cerrito-master-section">';
            echo '<div class="cerrito-section-label">Every ' . esc_html( $day ) . '</div>';
            foreach ( $events_by_day[ $day ]['recurring'] as $group ) {
                echo cerrito_render_event_group( $group, true, $atts['show_game_logo'], $atts['show_game_description'] );
            }
            echo '</div>';
        }

        if ( ! empty( $events_by_day[ $day ]['one_time'] ) ) {
            echo '<div class="cerrito-master-section">';
            echo '<div class="cerrito-section-label">Upcoming</div>';
            foreach ( $events_by_day[ $day ]['one_time'] as $group ) {
                ksort( $group['dates'] );
                foreach ( $group['dates'] as $date => $events ) {
                    $d              = DateTime::createFromFormat( 'Y-m-d', $date );
                    $g              = $group;
                    $g['events']    = $events;
                    $g['show_date'] = $d ? $d->format( 'M j' ) : $date;
                    echo cerrito_render_event_group( $g, false, $atts['show_game_logo'], $atts['show_game_description'] );
                }
            }
            echo '</div>';
        }

        echo '</div>'; // .cerrito-master-day
    }

    echo '</div>'; // .cerrito-master-schedule
    return ob_get_clean();
}
add_shortcode( 'cerrito_master_schedule', 'cerrito_master_schedule_shortcode' );
