<?php
/**
 * Shortcode: [cerrito_recurring_schedule]
 *
 * Displays recurring events grouped by day of week.
 *
 * Parameters:
 *   location   string  Location slug or ID (auto-detected on single location pages)
 *   game_type  string  Filter by game type slug or name
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_recurring_schedule_shortcode( array $atts ): string {
    $atts = shortcode_atts( [
        'location'  => '',
        'game_type' => '',
    ], $atts );

    if ( empty( $atts['location'] ) && is_singular( 'location' ) ) {
        global $post;
        $atts['location'] = $post->post_name;
    }

    cerrito_enqueue_styles();
    ob_start();

    $events = get_posts( [
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'meta_query'     => [ [
            'key'     => 'is_recurring',
            'value'   => '1',
            'compare' => '=',
        ] ],
    ] );

    if ( ! empty( $atts['location'] ) ) {
        $location_id = cerrito_resolve_location_id( $atts['location'] );
        if ( $location_id ) $events = cerrito_filter_by_location( $events, $location_id );
    }

    if ( ! empty( $atts['game_type'] ) ) {
        $events = cerrito_filter_by_game_type( $events, $atts['game_type'] );
    }

    echo '<div class="cerrito-recurring-schedule">';

    if ( $events ) {
        $day_order     = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
        $events_by_day = [];
        $coming_soon   = [];

        foreach ( $events as $event ) {
            $when_terms = get_the_terms( $event->ID, 'when' );

            if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                foreach ( $when_terms as $when_term ) {
                    $day        = $when_term->name;
                    $event_type = cerrito_get_event_type_string( $event->ID );

                    $special_theme = get_field( 'special_theme', $event->ID );
                    if ( empty( $special_theme ) ) {
                        $types = get_the_terms( $event->ID, 'game_type' );
                        if ( $types && ! is_wp_error( $types ) ) {
                            $special_theme = cerrito_get_next_themed_date_for_day( $types[0]->term_id, $day );
                        }
                    }

                    $group_key = $event_type . ( $special_theme ? ' - ' . $special_theme : '' );

                    if ( ! isset( $events_by_day[ $day ][ $group_key ] ) ) {
                        $events_by_day[ $day ][ $group_key ] = [
                            'type'   => $event_type,
                            'theme'  => $special_theme,
                            'class'  => cerrito_get_event_class( $event_type ),
                            'events' => [],
                        ];
                    }

                    $events_by_day[ $day ][ $group_key ]['events'][] = $event;
                }
            } else {
                $coming_soon[] = $event;
            }
        }

        // Render by canonical day order
        foreach ( $day_order as $day ) {
            if ( empty( $events_by_day[ $day ] ) ) continue;

            echo '<div class="cerrito-recurring-day">';
            echo '<div class="cerrito-day-header">EVERY ' . esc_html( strtoupper( $day ) ) . '</div>';

            foreach ( $events_by_day[ $day ] as $group ) {
                $game_emoji = cerrito_get_game_emoji( $group['type'] );
                ?>
                <div class="cerrito-recurring-occurrence">
                    <div class="cerrito-occurrence-title">
                        <?php if ( $game_emoji ) : ?>
                            <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
                        <?php endif; ?>
                        <?php echo esc_html( $group['type'] ); ?>
                        <?php if ( $group['theme'] ) : ?>
                            <span class="cerrito-theme-inline"><?php echo esc_html( $group['theme'] ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php foreach ( $group['events'] as $event ) :
                        cerrito_render_location_card( $event, $group['class'] );
                    endforeach; ?>
                </div>
                <?php
            }

            echo '</div>'; // .cerrito-recurring-day
        }

        // Coming Soon section
        if ( ! empty( $coming_soon ) ) {
            echo '<div class="cerrito-coming-soon-section">';
            echo '<div class="cerrito-occurrence-title">Coming Soon</div>';
            foreach ( $coming_soon as $event ) {
                cerrito_render_coming_soon_card( $event );
            }
            echo '</div>';
        }

    } else {
        echo '<p>No recurring events scheduled at this time.</p>';
    }

    echo '</div>'; // .cerrito-recurring-schedule
    return ob_get_clean();
}
add_shortcode( 'cerrito_recurring_schedule', 'cerrito_recurring_schedule_shortcode' );

/**
 * Render a "Coming Soon" card for a recurring event with no day assigned yet.
 */
function cerrito_render_coming_soon_card( WP_Post $event ): void {
    $event_type      = cerrito_get_event_type_string( $event->ID );
    $special_theme   = get_field( 'special_theme', $event->ID );
    $age_restriction = get_field( 'age_restriction', $event->ID );
    $special_notes   = get_field( 'special_notes', $event->ID );
    $location        = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
    $game_emoji      = cerrito_get_game_emoji( $event_type );

    if ( ! $location ) return;

    $location_logo    = cerrito_get_location_logo( $location->ID );
    $location_address = cerrito_get_location_address( $location->ID );
    ?>
    <div class="cerrito-coming-soon-item">
        <div class="cerrito-occurrence-title cerrito-occurrence-title--small">
            <?php if ( $game_emoji ) : ?>
                <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
            <?php endif; ?>
            <?php echo esc_html( $event_type ); ?>
            <?php if ( $special_theme ) : ?>
                <span class="cerrito-theme-inline"><?php echo esc_html( $special_theme ); ?></span>
            <?php endif; ?>
        </div>

        <div class="cerrito-location-card <?php echo esc_attr( cerrito_get_event_class( $event_type ) ); ?>">
            <div class="cerrito-location-logo">
                <?php if ( $location_logo ) : ?>
                    <img src="<?php echo esc_url( $location_logo ); ?>" alt="<?php echo esc_attr( $location->post_title ); ?>">
                <?php endif; ?>
            </div>
            <div class="cerrito-location-details">
                <div class="cerrito-location-name">
                    <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                        <?php echo esc_html( $location->post_title ); ?>
                    </a>
                </div>
                <div class="cerrito-location-time">🕐 TBA</div>
                <?php if ( $location_address ) : ?>
                    <div class="cerrito-location-address">📍 <?php echo esc_html( cerrito_flatten_address( $location_address ) ); ?></div>
                <?php endif; ?>
                <?php if ( $age_restriction ) : ?>
                    <div class="cerrito-location-badges">
                        <span class="cerrito-badge"><?php echo esc_html( $age_restriction ); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ( $special_notes ) : ?>
                    <div class="cerrito-location-notes"><?php echo wp_kses_post( $special_notes ); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
