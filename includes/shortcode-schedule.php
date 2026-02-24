<?php
/**
 * Shortcode: [cerrito_schedule]
 *
 * Displays upcoming one-time events grouped by date.
 *
 * Parameters:
 *   days_ahead       int     How many days ahead to show (default 30)
 *   location         string  Location slug or ID (auto-detected on single location pages)
 *   game_type        string  Filter by game type slug or name
 *   show_coming_soon string  'yes'|'no' — reserved for future use
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_schedule_shortcode( array $atts ): string {
    $atts = shortcode_atts( [
        'show_coming_soon' => 'yes',
        'days_ahead'       => '30',
        'location'         => '',
        'game_type'        => '',
    ], $atts );

    // Auto-detect location on single location pages
    if ( empty( $atts['location'] ) && is_singular( 'location' ) ) {
        global $post;
        $atts['location'] = $post->post_name;
    }

    cerrito_enqueue_styles();
    ob_start();

    $today    = wp_date( 'Y-m-d' );
    $end_date = wp_date( 'Y-m-d', strtotime( '+' . (int) $atts['days_ahead'] . ' days' ) );

    $events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_key'       => 'event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [ [
            'key'     => 'event_date',
            'value'   => [ $today, $end_date ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ] ],
    ] );

    if ( ! empty( $atts['location'] ) ) {
        $location_id = cerrito_resolve_location_id( $atts['location'] );
        if ( $location_id ) $events = cerrito_filter_by_location( $events, $location_id );
    }

    if ( ! empty( $atts['game_type'] ) ) {
        $events = cerrito_filter_by_game_type( $events, $atts['game_type'] );
    }

    echo '<div class="cerrito-schedule">';

    if ( $events ) {
        $events_by_date = [];

        foreach ( $events as $event ) {
            $event_date    = cerrito_normalise_date( get_field( 'event_date', $event->ID ) );
            $event_type    = cerrito_get_event_type_string( $event->ID );
            $special_theme = get_field( 'special_theme', $event->ID );

            // Fall back to automatic themed date
            if ( empty( $special_theme ) && $event_date ) {
                $types = get_the_terms( $event->ID, 'game_type' );
                if ( $types && ! is_wp_error( $types ) ) {
                    $auto = cerrito_get_event_theme( $types[0]->term_id, $event_date );
                    if ( $auto ) $special_theme = $auto->name;
                }
            }

            $group_key = $event_type . ( $special_theme ? ' - ' . $special_theme : '' );

            if ( ! isset( $events_by_date[ $event_date ][ $group_key ] ) ) {
                $events_by_date[ $event_date ][ $group_key ] = [
                    'type'   => $event_type,
                    'theme'  => $special_theme,
                    'class'  => cerrito_get_event_class( $event_type ),
                    'events' => [],
                ];
            }

            $events_by_date[ $event_date ][ $group_key ]['events'][] = $event;
        }

        foreach ( $events_by_date as $date => $groups ) {
            $date_obj     = DateTime::createFromFormat( 'Y-m-d', $date );
            $display_date = $date_obj ? strtoupper( $date_obj->format( 'l, M j' ) ) : strtoupper( $date );

            echo '<div class="cerrito-date-section">';
            echo '<div class="cerrito-date-header">' . esc_html( $display_date ) . '</div>';

            foreach ( $groups as $group ) {
                $game_logo  = cerrito_get_game_logo( $group['type'] );
                $game_emoji = cerrito_get_game_emoji( $group['type'] );
                ?>
                <div class="cerrito-event <?php echo esc_attr( $group['class'] ); ?>">
                    <?php if ( $game_logo ) : ?>
                        <div class="cerrito-game-logo">
                            <img src="<?php echo esc_url( $game_logo ); ?>" alt="<?php echo esc_attr( $group['type'] ); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="cerrito-event-type">
                        <?php if ( $game_emoji ) : ?>
                            <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
                        <?php endif; ?>
                        <?php echo esc_html( $group['type'] ); ?>
                        <?php if ( $group['theme'] ) : ?>
                            <span class="cerrito-event-theme"><?php echo esc_html( $group['theme'] ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php foreach ( $group['events'] as $event ) :
                        $event_time = get_field( 'event_time', $event->ID );
                        $location   = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
                        if ( $location ) : ?>
                            <div class="cerrito-event-venue">
                                <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                                    <?php echo esc_html( $location->post_title ); ?>
                                </a>
                                <?php if ( $event_time ) : ?>
                                    → <span class="cerrito-event-time"><?php echo esc_html( $event_time ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif;
                    endforeach; ?>
                </div>
                <?php
            }

            echo '</div>'; // .cerrito-date-section
        }
    } else {
        echo '<p>No upcoming events scheduled at this time. Check back soon!</p>';
    }

    echo '</div>'; // .cerrito-schedule
    return ob_get_clean();
}
add_shortcode( 'cerrito_schedule', 'cerrito_schedule_shortcode' );
