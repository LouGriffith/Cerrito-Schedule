<?php
/**
 * Shortcode: [cerrito_game_type_schedule]
 *
 * Displays each game type as a header with all scheduled locations and times
 * listed beneath it -- both recurring (by day) and upcoming one-time events.
 *
 * Parameters:
 *   game_type   string  Limit to a single game type slug or name (optional)
 *   days_ahead  int     How far ahead to include one-time events (default 60)
 *   show_logo   string  'yes'|'no'  -- show game type logo above name (default 'no')
 *   show_description string 'yes'|'no' -- show game type description (default 'no')
 *   orderby     string  'name' (default) | 'menu_order' -- order game types
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @param array $atts
 * @return string
 */
function cerrito_game_type_schedule_shortcode( array $atts ) {
    $atts = shortcode_atts( [
        'game_type'        => '',
        'days_ahead'       => '60',
        'show_logo'        => 'no',
        'show_description' => 'no',
        'orderby'          => 'name',
    ], $atts );

    cerrito_enqueue_styles();
    ob_start();

    $days_ahead = (int) $atts['days_ahead'];
    $today      = wp_date( 'Y-m-d' );
    $end_date   = wp_date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
    $day_order  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

    // ── Fetch game type terms ─────────────────────────────────────────────────
    $terms_args = [
        'taxonomy'   => 'game_type',
        'hide_empty' => true,
    ];

    if ( ! empty( $atts['game_type'] ) ) {
        // Accept slug or name
        $terms_args['slug'] = sanitize_title( $atts['game_type'] );
    }

    if ( $atts['orderby'] === 'menu_order' ) {
        $terms_args['orderby'] = 'menu_order';
    } else {
        $terms_args['orderby'] = 'name';
        $terms_args['order']   = 'ASC';
    }

    $game_types = get_terms( $terms_args );

    if ( empty( $game_types ) || is_wp_error( $game_types ) ) {
        echo '<div class="cerrito-empty"><p>No game types found.</p></div>';
        return ob_get_clean();
    }

    // ── Fetch all events once ─────────────────────────────────────────────────
    $all_events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );

    // Index by game_type term_id for fast lookup
    // [ term_id => [ WP_Post, ... ] ]
    $events_by_type = [];

    foreach ( $all_events as $event ) {
        $types = get_the_terms( $event->ID, 'game_type' );
        if ( ! $types || is_wp_error( $types ) ) continue;
        foreach ( $types as $type ) {
            $events_by_type[ $type->term_id ][] = $event;
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────
    echo '<div class="cerrito-gt-schedule">';

    foreach ( $game_types as $term ) {
        $emoji       = (string) get_field( 'game_emoji', 'game_type_' . $term->term_id );
        $logo        = ( $atts['show_logo'] === 'yes' )
                       ? cerrito_resolve_image_url( get_field( 'game_logo', 'game_type_' . $term->term_id ) )
                       : '';
        $description = ( $atts['show_description'] === 'yes' ) ? $term->description : '';
        $event_class = cerrito_get_event_class( $term->name );
        $term_events = $events_by_type[ $term->term_id ] ?? [];

        if ( empty( $term_events ) ) continue;

        // Separate into recurring (keyed by day) and upcoming one-time events
        $recurring_by_day = [];  // [ 'Monday' => [ WP_Post, ... ], ... ]
        $upcoming         = [];  // [ 'Y-m-d'  => [ WP_Post, ... ], ... ]

        foreach ( $term_events as $event ) {
            $is_recurring = (bool) get_field( 'is_recurring', $event->ID );

            if ( $is_recurring ) {
                $when_terms = get_the_terms( $event->ID, 'when' );
                if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                    foreach ( $when_terms as $wt ) {
                        $recurring_by_day[ $wt->name ][] = $event;
                    }
                }
            } else {
                $event_date = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
                if ( $event_date && $event_date >= $today && $event_date <= $end_date ) {
                    $upcoming[ $event_date ][] = $event;
                }
            }
        }

        // Skip the block entirely if there's nothing to show
        if ( empty( $recurring_by_day ) && empty( $upcoming ) ) continue;

        // ── Game type header ──────────────────────────────────────────────────
        echo '<div class="cerrito-gt-block ' . esc_attr( $event_class ) . '">';

        echo '<div class="cerrito-gt-header cerrito-gt-header--' . esc_attr( $event_class ) . '">';
        if ( $logo ) {
            echo '<img class="cerrito-gt-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $term->name ) . '">';
        }
        echo '<span class="cerrito-gt-title">';
        if ( $emoji ) {
            echo '<span class="cerrito-game-emoji">' . esc_html( $emoji ) . '</span>';
        }
        echo esc_html( strtoupper( $term->name ) );
        echo '</span>';
        echo '</div>'; // .cerrito-gt-header

        if ( $description ) {
            echo '<p class="cerrito-gt-description">' . esc_html( $description ) . '</p>';
        }

        echo '<div class="cerrito-gt-rows">';

        // ── Recurring rows (canonical day order) ──────────────────────────────
        foreach ( $day_order as $day ) {
            if ( empty( $recurring_by_day[ $day ] ) ) continue;

            echo '<div class="cerrito-gt-day-label">Every ' . esc_html( $day ) . '</div>';

            foreach ( $recurring_by_day[ $day ] as $event ) {
                $location = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
                if ( ! $location ) continue;

                $time          = get_field( 'event_time', $event->ID );
                $special_theme = get_field( 'special_theme', $event->ID );

                echo '<div class="cerrito-gt-row">';
                echo '<a class="cerrito-gt-location-name" href="' . esc_url( get_permalink( $location->ID ) ) . '">';
                echo esc_html( $location->post_title );
                echo '</a>';
                if ( $time ) {
                    echo '<span class="cerrito-gt-time">' . esc_html( $time ) . '</span>';
                }
                if ( $special_theme ) {
                    echo '<span class="cerrito-theme-badge">' . esc_html( $special_theme ) . '</span>';
                }
                echo '</div>'; // .cerrito-gt-row
            }
        }

        // ── Upcoming one-time rows ─────────────────────────────────────────────
        if ( ! empty( $upcoming ) ) {
            ksort( $upcoming );

            foreach ( $upcoming as $date => $events ) {
                $d_obj    = DateTime::createFromFormat( 'Y-m-d', $date );
                $date_lbl = $d_obj ? $d_obj->format( 'M j' ) . ' (' . $d_obj->format( 'l' ) . ')' : $date;

                echo '<div class="cerrito-gt-day-label">' . esc_html( $date_lbl ) . '</div>';

                foreach ( $events as $event ) {
                    $location = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
                    if ( ! $location ) continue;

                    $time          = get_field( 'event_time', $event->ID );
                    $special_theme = get_field( 'special_theme', $event->ID );

                    echo '<div class="cerrito-gt-row">';
                    echo '<a class="cerrito-gt-location-name" href="' . esc_url( get_permalink( $location->ID ) ) . '">';
                    echo esc_html( $location->post_title );
                    echo '</a>';
                    if ( $time ) {
                        echo '<span class="cerrito-gt-time">' . esc_html( $time ) . '</span>';
                    }
                    if ( $special_theme ) {
                        echo '<span class="cerrito-theme-badge">' . esc_html( $special_theme ) . '</span>';
                    }
                    echo '</div>'; // .cerrito-gt-row
                }
            }
        }

        echo '</div>'; // .cerrito-gt-rows
        echo '</div>'; // .cerrito-gt-block
    }

    echo '</div>'; // .cerrito-gt-schedule
    return ob_get_clean();
}
add_shortcode( 'cerrito_game_type_schedule', 'cerrito_game_type_schedule_shortcode' );
