<?php
/**
 * Shortcode: [cerrito_today]
 *
 * Displays all events (recurring + one-time) happening today.
 *
 * Parameters:
 *   style                 string  'full' (default) or 'compact'
 *   show_game_logo        string  'yes'|'no' — full style only
 *   show_game_description string  'yes'|'no' — full style only
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function cerrito_today_schedule_shortcode( array $atts ): string {
    $atts = shortcode_atts( [
        'show_game_logo'        => 'yes',
        'show_game_description' => 'no',
        'style'                 => 'full',
    ], $atts );

    cerrito_enqueue_styles();
    ob_start();

    $today      = wp_date( 'l' );       // e.g. "Wednesday"
    $today_date = wp_date( 'Y-m-d' );

    $all_events   = get_posts( [ 'post_type' => 'event', 'posts_per_page' => -1 ] );
    $today_groups = [];

    foreach ( $all_events as $event ) {
        $is_recurring = (bool) get_field( 'is_recurring', $event->ID );
        $include      = false;

        if ( $is_recurring ) {
            $when_terms = get_the_terms( $event->ID, 'when' );
            if ( $when_terms && ! is_wp_error( $when_terms ) ) {
                foreach ( $when_terms as $t ) {
                    if ( $t->name === $today ) { $include = true; break; }
                }
            }
        } else {
            $include = ( cerrito_normalise_date( get_field( 'event_date', $event->ID ) ) === $today_date );
        }

        if ( ! $include ) continue;

        $event_type    = cerrito_get_event_type_string( $event->ID );
        $special_theme = get_field( 'special_theme', $event->ID );
        $group_key     = $event_type . ( $special_theme ? ' - ' . $special_theme : '' );

        if ( ! isset( $today_groups[ $group_key ] ) ) {
            $today_groups[ $group_key ] = [
                'type'   => $event_type,
                'theme'  => $special_theme,
                'class'  => cerrito_get_event_class( $event_type ),
                'events' => [],
            ];
        }
        $today_groups[ $group_key ]['events'][] = $event;
    }

    $wrapper_class = 'cerrito-today-schedule' . ( $atts['style'] === 'compact' ? ' compact' : '' );
    echo '<div class="' . esc_attr( $wrapper_class ) . '">';

    // ── Header ────────────────────────────────────────────────────────────────
    echo '<div class="cerrito-today-header">';
    if ( $atts['style'] === 'compact' ) {
        echo '<h2>' . esc_html( wp_date( 'l, F j, Y' ) ) . '</h2>';
    } else {
        echo '<div class="cerrito-today-day">'  . esc_html( strtoupper( $today ) ) . '</div>';
        echo '<div class="cerrito-today-date">' . esc_html( wp_date( 'l, F j, Y' ) ) . '</div>';
    }
    echo '</div>';

    // ── Events ────────────────────────────────────────────────────────────────
    if ( ! empty( $today_groups ) ) {
        if ( $atts['style'] === 'compact' ) {
            cerrito_today_render_compact( $today_groups );
        } else {
            cerrito_today_render_full( $today_groups, $atts );
        }
    } else {
        echo '<div class="cerrito-today-empty"><p>No events scheduled for today. Check back tomorrow!</p></div>';
    }

    echo '</div>'; // wrapper
    return ob_get_clean();
}
add_shortcode( 'cerrito_today', 'cerrito_today_schedule_shortcode' );

// ── Style-specific renderers ──────────────────────────────────────────────────

function cerrito_today_render_compact( array $groups ): void {
    foreach ( $groups as $group ) :
        $game_emoji = cerrito_get_game_emoji( $group['type'] );
        ?>
        <div class="cerrito-today-occurrence">
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
                $event_time = get_field( 'event_time', $event->ID );
                $location   = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
                if ( ! $location ) continue;
            ?>
                <div class="cerrito-compact-venue">
                    <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                        <?php echo esc_html( $location->post_title ); ?>
                    </a>
                    <?php if ( $event_time ) : ?>
                        → <?php echo esc_html( $event_time ); ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    endforeach;
}

function cerrito_today_render_full( array $groups, array $atts ): void {
    foreach ( $groups as $group ) :
        $game_emoji       = cerrito_get_game_emoji( $group['type'] );
        $game_logo        = ( $atts['show_game_logo']        === 'yes' ) ? cerrito_get_game_logo( $group['type'] )        : '';
        $game_description = ( $atts['show_game_description'] === 'yes' ) ? cerrito_get_game_description( $group['type'] ) : '';
        ?>
        <div class="cerrito-today-occurrence">
            <?php if ( $game_logo || $game_description ) : ?>
                <div class="cerrito-game-header">
                    <?php if ( $game_logo ) : ?>
                        <div class="cerrito-game-logo">
                            <img src="<?php echo esc_url( $game_logo ); ?>" alt="<?php echo esc_attr( $group['type'] ); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="cerrito-game-info">
                        <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'] ); ?>
                        <?php if ( $game_description ) : ?>
                            <p class="cerrito-game-description"><?php echo esc_html( $game_description ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'] ); ?>
            <?php endif; ?>

            <?php foreach ( $group['events'] as $event ) :
                cerrito_render_location_card( $event, $group['class'] );
            endforeach; ?>
        </div>
        <?php
    endforeach;
}
