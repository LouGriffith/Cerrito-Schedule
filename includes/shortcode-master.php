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
 *   display               string  'full' (default) | 'compact'
 *                                 compact = no venue cards; shows time → venue name inline
 *   show_day_filter       string  'yes'|'no' — show clickable day buttons above the schedule
 *   show_themed_filter    string  'yes'|'no' — add a "Themed Events" toggle button
 *   default_day           string  Pre-select a day on load, e.g. "Tuesday" (requires show_day_filter="yes")
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @param array $atts
 * @return string
 */
function cerrito_master_schedule_shortcode( array $atts ) {
    $atts = shortcode_atts( [
        'location'              => '',
        'game_type'             => '',
        'days_ahead'            => '30',
        'show_game_logo'        => 'no',
        'show_game_description' => 'no',
        'display'               => 'full',
        'show_day_filter'       => 'no',
        'show_themed_filter'    => 'no',
        'default_day'           => '',   // e.g. "Tuesday" — pre-selects that day on load
    ], $atts );

    if ( empty( $atts['location'] ) && is_singular( 'location' ) ) {
        global $post;
        $atts['location'] = $post->post_name;
    }

    cerrito_enqueue_styles();
    ob_start();

    $day_order  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
    $today      = wp_date( 'Y-m-d' );
    $end_date   = wp_date( 'Y-m-d', strtotime( '+' . (int) $atts['days_ahead'] . ' days' ) );
    $days_ahead = (int) $atts['days_ahead'];
    $display    = $atts['display'];
    $show_filter        = ( $atts['show_day_filter']    === 'yes' );
    $show_themed_filter = ( $atts['show_themed_filter'] === 'yes' );
    $default_day = trim( $atts['default_day'] );
    // Unique ID per shortcode instance so multiple on same page don't collide
    static $instance = 0;
    $instance++;
    $uid = 'cerrito-ms-' . $instance;

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
                foreach ( $when_terms as $t ) {
                    // Skip if this occurrence is cancelled for the next date it falls on
                    if ( ! cerrito_is_skipped_on( $event->ID, cerrito_next_date_for_day( $t->name ) ) ) {
                        $days[] = $t->name;
                    }
                }
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

    $wrapper_class = 'cerrito-master-schedule' . ( $display === 'compact' ? ' cerrito-master-schedule--compact' : '' );
    echo '<div id="' . esc_attr( $uid ) . '" class="' . esc_attr( $wrapper_class ) . '">';

    // ── Filter bar ────────────────────────────────────────────────────────────
    $any_filter = $show_filter || $show_themed_filter;

    if ( $any_filter ) {
        // Only show day buttons for days that actually have events
        $days_with_events = array_filter( $day_order, function( $day ) use ( $events_by_day ) {
            return ! empty( $events_by_day[ $day ]['recurring'] ) || ! empty( $events_by_day[ $day ]['one_time'] );
        } );

        echo '<div class="cerrito-day-filter" role="group" aria-label="Filter schedule">';

        if ( $show_filter && count( $days_with_events ) > 1 ) {
            echo '<span class="cerrito-day-filter__label">Day:</span>';
            echo '<button class="cerrito-day-filter__btn cerrito-day-filter__btn--all is-active" data-filter="day" data-day="all">All</button>';
            foreach ( $days_with_events as $day ) {
                $abbr = substr( $day, 0, 3 );
                echo '<button class="cerrito-day-filter__btn" data-filter="day" data-day="' . esc_attr( $day ) . '">' . esc_html( $abbr ) . '</button>';
            }
        }

        if ( $show_themed_filter ) {
            if ( $show_filter && count( $days_with_events ) > 1 ) {
                echo '<span class="cerrito-day-filter__sep" aria-hidden="true"></span>';
            }
            echo '<button class="cerrito-day-filter__btn cerrito-day-filter__btn--themed" data-filter="themed" data-themed="off">🎭 Themed</button>';
        }

        echo '</div>';
    }

    foreach ( $day_order as $day ) {
        if ( empty( $events_by_day[ $day ]['recurring'] ) && empty( $events_by_day[ $day ]['one_time'] ) ) {
            continue;
        }

        echo '<div class="cerrito-master-day" data-day="' . esc_attr( $day ) . '">';
        echo '<div class="cerrito-day-header">' . esc_html( strtoupper( $day ) . 'S' ) . '</div>';

        if ( ! empty( $events_by_day[ $day ]['recurring'] ) ) {
            echo '<div class="cerrito-master-section">';
            echo '<div class="cerrito-section-label">Every ' . esc_html( $day ) . '</div>';
            foreach ( $events_by_day[ $day ]['recurring'] as $group ) {
                echo cerrito_render_event_group( $group, true, $atts['show_game_logo'], $atts['show_game_description'], $display );
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
                    echo cerrito_render_event_group( $g, false, $atts['show_game_logo'], $atts['show_game_description'], $display );
                }
            }
            echo '</div>';
        }

        echo '</div>'; // .cerrito-master-day
    }

    echo '</div>'; // .cerrito-master-schedule

    // ── Inline JS — only emitted when any filter is active ───────────────────
    if ( $any_filter ) {
        $default_js = esc_js( $default_day );
        ?>
        <script>
        (function () {
            var wrap = document.getElementById('<?php echo esc_js( $uid ); ?>');
            if ( ! wrap ) return;

            // State
            var activeDay    = '<?php echo $default_js; ?>' || 'all';
            var themedOnly   = false;

            var dayBtns      = wrap.querySelectorAll('[data-filter="day"]');
            var themedBtn    = wrap.querySelector('[data-filter="themed"]');
            var dayDivs      = wrap.querySelectorAll('.cerrito-master-day');

            function applyFilters() {
                dayDivs.forEach( function( dayDiv ) {
                    // Step 1: does this day pass the day filter?
                    var dayMatch = ( activeDay === 'all' || dayDiv.dataset.day === activeDay );

                    if ( ! dayMatch ) {
                        dayDiv.style.display = 'none';
                        return;
                    }

                    // Step 2: if themed filter is on, show/hide individual groups
                    var groups = dayDiv.querySelectorAll('.cerrito-event-group');

                    if ( themedOnly ) {
                        var visibleGroups = 0;
                        groups.forEach( function( g ) {
                            var show = g.dataset.themed === '1';
                            g.style.display = show ? '' : 'none';
                            if ( show ) visibleGroups++;
                        });
                        // Hide the whole day block if no themed groups remain
                        dayDiv.style.display = visibleGroups > 0 ? '' : 'none';
                    } else {
                        groups.forEach( function( g ) { g.style.display = ''; });
                        dayDiv.style.display = '';
                    }
                });
            }

            // Day buttons
            dayBtns.forEach( function( btn ) {
                btn.addEventListener( 'click', function() {
                    activeDay = this.dataset.day;
                    dayBtns.forEach( function( b ) {
                        b.classList.toggle( 'is-active', b.dataset.day === activeDay );
                    });
                    applyFilters();
                });
            });

            // Themed toggle button
            if ( themedBtn ) {
                themedBtn.addEventListener( 'click', function() {
                    themedOnly = ! themedOnly;
                    this.classList.toggle( 'is-active', themedOnly );
                    applyFilters();
                });
            }

            // Apply initial state
            applyFilters();
            if ( activeDay !== 'all' ) {
                dayBtns.forEach( function( b ) {
                    b.classList.toggle( 'is-active', b.dataset.day === activeDay );
                });
            }
        })();
        </script>
        <?php
    }

    return ob_get_clean();
}
add_shortcode( 'cerrito_master_schedule', 'cerrito_master_schedule_shortcode' );
