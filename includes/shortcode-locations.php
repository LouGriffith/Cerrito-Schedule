<?php
/**
 * Shortcode: [cerrito_locations]
 *
 * Displays all locations as a directory. Under each location every scheduled
 * event is listed (recurring by day-of-week, upcoming one-time events by date).
 * Locations with no events still appear with a "no events" message.
 *
 * Parameters:
 *   game_type      string   Filter events by game type slug or name
 *   show_address   string   'yes'|'no'  — show address line (default yes)
 *   show_specials  string   'yes'|'no'  — show specials/offers text (default yes)
 *   show_logo      string   'yes'|'no'  — show location logo (default yes)
 *   days_ahead     int      How far ahead to look for one-time events (default 60)
 *   orderby        string   'name' (default) | 'menu_order'
 *   empty_message  string   Text shown when a location has no events
 *                           (default "No events currently scheduled.")
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @param array $atts
 * @return string
 */
function cerrito_locations_shortcode( array $atts ) {
    $atts = shortcode_atts( [
        'game_type'     => '',
        'show_address'  => 'yes',
        'show_specials' => 'yes',
        'show_logo'     => 'yes',
        'days_ahead'    => '60',
        'orderby'       => 'name',
        'empty_message' => 'No events currently scheduled.',
    ], $atts );

    cerrito_enqueue_styles();
    ob_start();

    $days_ahead = (int) $atts['days_ahead'];
    $today      = wp_date( 'Y-m-d' );
    $end_date   = wp_date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
    $day_order  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

    // ── Fetch all locations ───────────────────────────────────────────────────
    $location_query_args = [
        'post_type'      => 'location',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ];

    if ( $atts['orderby'] === 'menu_order' ) {
        $location_query_args['orderby'] = 'menu_order';
        $location_query_args['order']   = 'ASC';
    } else {
        // Fetch alphabetically, then re-sort in PHP: letters A–Z first, numbers after
        $location_query_args['orderby'] = 'title';
        $location_query_args['order']   = 'ASC';
    }

    $locations = get_posts( $location_query_args );

    // Re-sort: A–Z first, then 0–9 (WordPress default puts numbers before letters)
    if ( $atts['orderby'] !== 'menu_order' ) {
        usort( $locations, function( $a, $b ) {
            $ta          = $a->post_title;
            $tb          = $b->post_title;
            $a_is_letter = ctype_alpha( mb_substr( $ta, 0, 1 ) );
            $b_is_letter = ctype_alpha( mb_substr( $tb, 0, 1 ) );
            if ( $a_is_letter && ! $b_is_letter ) return -1;
            if ( ! $a_is_letter && $b_is_letter ) return  1;
            return strcasecmp( $ta, $tb );
        } );
    }

    if ( empty( $locations ) ) {
        echo '<div class="cerrito-empty"><p>No locations found.</p></div>';
        return ob_get_clean();
    }

    // ── Fetch all events once, then index by location ID ──────────────────────
    $all_events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );

    if ( ! empty( $atts['game_type'] ) ) {
        $all_events = cerrito_filter_by_game_type( $all_events, $atts['game_type'] );
    }

    // Index events by location ID for fast lookup
    $events_by_location = [];   // [ location_id => [ WP_Post, … ] ]

    foreach ( $all_events as $event ) {
        $loc = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
        if ( ! $loc ) continue;
        $events_by_location[ $loc->ID ][] = $event;
    }

    // ── Render ────────────────────────────────────────────────────────────────
    echo '<div class="cerrito-locations">';

    foreach ( $locations as $location ) {
        $loc_id      = $location->ID;
        $logo        = ( $atts['show_logo'] === 'yes' ) ? cerrito_get_location_logo( $loc_id ) : '';
        $address     = ( $atts['show_address']  === 'yes' ) ? cerrito_get_location_address( $loc_id ) : '';
        $specials    = ( $atts['show_specials'] === 'yes' ) ? get_field( 'specials', $loc_id ) : '';
        $website     = get_field( 'website', $loc_id );
        $loc_events  = $events_by_location[ $loc_id ] ?? [];

        echo '<div class="cerrito-location-entry">';

        // ── Location header ───────────────────────────────────────────────────
        echo '<div class="cerrito-location-entry__header">';

        if ( $logo ) {
            echo '<div class="cerrito-location-entry__logo">';
            echo '<a href="' . esc_url( get_permalink( $loc_id ) ) . '">';
            echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $location->post_title ) . '">';
            echo '</a>';
            echo '</div>';
        }

        echo '<div class="cerrito-location-entry__info">';
        echo '<h3 class="cerrito-location-entry__name">';
        echo '<a href="' . esc_url( get_permalink( $loc_id ) ) . '">' . esc_html( $location->post_title ) . '</a>';
        echo '</h3>';

        if ( $address ) {
            echo '<div class="cerrito-location-entry__address">📍 ' . esc_html( cerrito_flatten_address( $address ) ) . '</div>';
        }

        if ( $website ) {
            $display_url = preg_replace( '#^https?://#', '', rtrim( $website, '/' ) );
            echo '<div class="cerrito-location-entry__website">';
            echo '🌐 <a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $display_url ) . '</a>';
            echo '</div>';
        }

        if ( $specials ) {
            echo '<div class="cerrito-location-entry__specials">' . wp_kses_post( $specials ) . '</div>';
        }

        echo '</div>'; // __info
        echo '</div>'; // __header

        // ── Events for this location ──────────────────────────────────────────
        echo '<div class="cerrito-location-entry__events">';

        if ( empty( $loc_events ) ) {
            echo '<p class="cerrito-location-entry__empty">' . esc_html( $atts['empty_message'] ) . '</p>';
        } else {
            // Split into recurring (grouped by day) and upcoming one-time
            $by_day   = [];   // [ 'Monday' => [ group_key => group ], … ]
            $upcoming = [];   // [ date => [ group_key => group ] ]

            foreach ( $loc_events as $event ) {
                $is_recurring = (bool) get_field( 'is_recurring', $event->ID );
                $event_type   = cerrito_get_event_type_string( $event->ID );
                $event_date   = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
                $theme        = get_field( 'special_theme', $event->ID );

                // Auto-resolve theme
                if ( empty( $theme ) ) {
                    $types = get_the_terms( $event->ID, 'game_type' );
                    if ( $types && ! is_wp_error( $types ) ) {
                        if ( $is_recurring ) {
                            $when_terms = get_the_terms( $event->ID, 'when' );
                            if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                                $theme = cerrito_get_next_themed_date_for_day( $types[0]->term_id, $when_terms[0]->name, $days_ahead );
                            }
                        } elseif ( $event_date ) {
                            $auto = cerrito_get_event_theme( $types[0]->term_id, $event_date );
                            if ( $auto ) $theme = $auto->name;
                        }
                    }
                }

                $group_key = $event_type . ( $theme ? '|' . $theme : '' );

                if ( $is_recurring ) {
                    $when_terms = get_the_terms( $event->ID, 'when' );
                    $days = ( $when_terms && ! is_wp_error( $when_terms ) )
                        ? wp_list_pluck( $when_terms, 'name' )
                        : [ '__none__' ];

                    foreach ( $days as $day ) {
                        if ( ! isset( $by_day[ $day ][ $group_key ] ) ) {
                            $by_day[ $day ][ $group_key ] = [
                                'type'   => $event_type,
                                'theme'  => $theme,
                                'class'  => cerrito_get_event_class( $event_type ),
                                'events' => [],
                            ];
                        }
                        $by_day[ $day ][ $group_key ]['events'][] = $event;
                    }
                } elseif ( $event_date && $event_date >= $today && $event_date <= $end_date ) {
                    if ( ! isset( $upcoming[ $event_date ][ $group_key ] ) ) {
                        $upcoming[ $event_date ][ $group_key ] = [
                            'type'   => $event_type,
                            'theme'  => $theme,
                            'class'  => cerrito_get_event_class( $event_type ),
                            'events' => [],
                        ];
                    }
                    $upcoming[ $event_date ][ $group_key ]['events'][] = $event;
                }
            }

            // Render recurring events
            $has_recurring = false;
            foreach ( $day_order as $day ) {
                if ( empty( $by_day[ $day ] ) ) continue;
                $has_recurring = true;

                echo '<div class="cerrito-location-entry__day-label">Every ' . esc_html( $day ) . '</div>';

                foreach ( $by_day[ $day ] as $group ) {
                    $group['events'] = cerrito_sort_events_by_time( $group['events'] );
                    echo cerrito_render_location_event_row( $group );
                }
            }

            // Recurring events with no day assigned yet
            if ( ! empty( $by_day['__none__'] ) ) {
                echo '<div class="cerrito-location-entry__day-label">Coming Soon</div>';
                foreach ( $by_day['__none__'] as $group ) {
                    $group['events'] = cerrito_sort_events_by_time( $group['events'] );
                    echo cerrito_render_location_event_row( $group, true );
                }
            }

            // Render upcoming one-time events
            if ( ! empty( $upcoming ) ) {
                ksort( $upcoming );
                foreach ( $upcoming as $date => $groups ) {
                    $d     = DateTime::createFromFormat( 'Y-m-d', $date );
                    $label = $d ? $d->format( 'l, M j' ) : $date;

                    echo '<div class="cerrito-location-entry__day-label">' . esc_html( $label ) . '</div>';

                    foreach ( $groups as $group ) {
                        $group['events'] = cerrito_sort_events_by_time( $group['events'] );
                        echo cerrito_render_location_event_row( $group );
                    }
                }
            }

            if ( ! $has_recurring && empty( $upcoming ) && empty( $by_day['__none__'] ) ) {
                // All events for this location fell outside the date window
                echo '<p class="cerrito-location-entry__empty">' . esc_html( $atts['empty_message'] ) . '</p>';
            }
        }

        echo '</div>'; // __events
        echo '</div>'; // .cerrito-location-entry
    }

    echo '</div>'; // .cerrito-locations
    return ob_get_clean();
}
add_shortcode( 'cerrito_locations', 'cerrito_locations_shortcode' );

/**
 * Render a single event row inside the location directory view.
 * Shows: emoji  game-type  [theme]  —  time(s)
 *
 * @param array $group       Keys: type, theme, class, events
 * @param bool  $coming_soon Replace time with "TBA"
 * @return string
 */
function cerrito_render_location_event_row( array $group, $coming_soon = false ) {
    ob_start();

    $game_emoji   = cerrito_get_game_emoji( $group['type'] );
    $color_style  = cerrito_game_color_style( $group['type'], 'color' );
    $border_style = cerrito_game_color_style( $group['type'], 'border-left-color' );
    ?>
    <div class="cerrito-location-entry__event-row <?php echo esc_attr( $group['class'] ); ?>"<?php echo $border_style; ?>>
        <span class="cerrito-location-entry__event-type"<?php echo $color_style; ?>>
            <?php if ( $game_emoji ) : ?>
                <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
            <?php endif; ?>
            <?php echo esc_html( $group['type'] ); ?>
            <?php if ( $group['theme'] ) : ?>
                <span class="cerrito-theme-badge"><?php echo esc_html( $group['theme'] ); ?></span>
            <?php endif; ?>
        </span>
        <span class="cerrito-location-entry__event-times">
            <?php if ( $coming_soon ) : ?>
                <span class="cerrito-location-entry__time">TBA</span>
            <?php else : ?>
                <?php foreach ( $group['events'] as $event ) :
                    $t = get_field( 'event_time', $event->ID );
                    if ( $t ) : ?>
                        <span class="cerrito-location-entry__time"><?php echo esc_html( $t ); ?></span>
                    <?php endif;
                endforeach; ?>
            <?php endif; ?>
        </span>
    </div>
    <?php
    return ob_get_clean();
}
