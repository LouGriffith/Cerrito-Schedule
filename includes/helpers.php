<?php
/**
 * Shared helper functions used across all Cerrito Schedule shortcodes.
 *
 * Functions are prefixed cerrito_ to avoid collisions with other plugins.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Location helpers ──────────────────────────────────────────────────────────

/**
 * Resolve an ACF relationship field (array or single object) to a WP_Post.
 *
 * @param mixed $field_value
 * @return WP_Post|null
 */
function cerrito_resolve_location( $field_value ) {
    if ( is_array( $field_value ) && ! empty( $field_value ) ) {
        $field_value = $field_value[0];
    }
    return ( $field_value instanceof WP_Post ) ? $field_value : null;
}

/**
 * Resolve a location slug or numeric ID string to a post ID integer.
 *
 * @param string|int $location
 * @return int
 */
function cerrito_resolve_location_id( $location ) {
    if ( is_numeric( $location ) ) return (int) $location;
    $post = get_page_by_path( $location, OBJECT, 'location' );
    return $post ? $post->ID : 0;
}

/**
 * Get a location logo URL, trying multiple possible ACF field names.
 *
 * @param int $location_id
 * @return string
 */
function cerrito_get_location_logo( $location_id ) {
    foreach ( [ 'location_logo', 'logo', 'sponsor_logo' ] as $field_name ) {
        $field = get_field( $field_name, $location_id );
        if ( $field ) return cerrito_resolve_image_url( $field );
    }
    return '';
}

/**
 * Get a location address string, trying multiple possible ACF field names.
 *
 * @param int $location_id
 * @return string
 */
function cerrito_get_location_address( $location_id ) {
    foreach ( [ 'location_address', 'address' ] as $field_name ) {
        $value = get_field( $field_name, $location_id );
        if ( $value ) return (string) $value;
    }
    return '';
}

// ── Game type helpers ─────────────────────────────────────────────────────────

/**
 * Look up a game_type term by name or slug.
 *
 * @param string $type_name
 * @return WP_Term|null
 */
function cerrito_get_game_type_term( $type_name ) {
    if ( ! $type_name ) return null;
    $term = get_term_by( 'name', $type_name, 'game_type' );
    if ( ! $term ) {
        $term = get_term_by( 'slug', sanitize_title( $type_name ), 'game_type' );
    }
    return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * Get the emoji for a game type by name.
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_emoji( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? (string) get_field( 'game_emoji', 'game_type_' . $term->term_id ) : '';
}

/**
 * Get the logo URL for a game type by name.
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_logo( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? cerrito_resolve_image_url( get_field( 'game_logo', 'game_type_' . $term->term_id ) ) : '';
}

/**
 * Get the description for a game type by name (native WP taxonomy description).
 *
 * @param string $type_name
 * @return string
 */
function cerrito_get_game_description( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    return $term ? $term->description : '';
}

/**
 * Get the brand color for a game type by name.
 * Returns a hex color string (e.g. '#ff6b6b') or '' if not set.
 *
 * ACF setup required:
 *   Field group : Game Types (taxonomy)
 *   Field label : Game Color
 *   Field name  : game_color   (Color Picker)
 *   Default     : #0066cc
 *
 * @param string $type_name
 * @return string  Hex color or ''
 */
function cerrito_get_game_color( $type_name ) {
    $term = cerrito_get_game_type_term( $type_name );
    if ( ! $term ) return '';
    $color = get_field( 'game_color', 'game_type_' . $term->term_id );
    return ( $color && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $color ) ) ? $color : '';
}

/**
 * Return an inline style string for a game type color, or '' if none set.
 * Pass $property to control which CSS property is set (e.g. 'color', 'border-left-color').
 *
 * @param string $type_name
 * @param string $property   CSS property name
 * @return string  e.g. ' style="color:#e91e63"' or ''
 */
function cerrito_game_color_style( $type_name, $property = 'color' ) {
    $color = cerrito_get_game_color( $type_name );
    return $color ? ' style="' . esc_attr( $property . ':' . $color ) . '"' : '';
}
 *
 * @param string $event_type
 * @return string
 */
function cerrito_get_event_class( $event_type ) {
    $lower = strtolower( $event_type );
    if ( strpos( $lower, 'trivia' ) !== false ) return 'trivia';
    if ( strpos( $lower, 'bingo' )  !== false ) return 'bingo';
    return '';
}

/**
 * Return a comma-separated string of game type names for a post.
 *
 * @param int $post_id
 * @return string
 */
function cerrito_get_event_type_string( $post_id ) {
    $types = get_the_terms( $post_id, 'game_type' );
    if ( ! $types || is_wp_error( $types ) ) return '';
    return implode( ', ', wp_list_pluck( $types, 'name' ) );
}

// ── Theme helpers ─────────────────────────────────────────────────────────────

/**
 * Get the active theme for a game type on a specific date.
 *
 * @param int    $term_id
 * @param string $date     Y-m-d
 * @return WP_Term|false
 */
function cerrito_get_event_theme( $term_id, $date = null ) {
    if ( $date === null ) $date = wp_date( 'Y-m-d' );

    $themed_dates = get_term_meta( $term_id, 'themed_dates', true );
    if ( empty( $themed_dates ) || ! is_array( $themed_dates ) ) return false;

    foreach ( $themed_dates as $entry ) {
        if ( ! isset( $entry['date'], $entry['theme_id'] ) ) continue;
        if ( $entry['date'] !== $date ) continue;

        $theme = get_term( $entry['theme_id'], 'game-theme' );
        if ( $theme && ! is_wp_error( $theme ) ) {
            $theme->emoji = get_field( 'theme_emoji', 'game-theme_' . $theme->term_id );
            $theme->image = cerrito_resolve_image_url( get_field( 'theme_image', 'game-theme_' . $theme->term_id ) );
            return $theme;
        }
    }

    return false;
}

/**
 * Find the next upcoming themed date for a game type that falls on a specific
 * day of the week, within $days_ahead days from today.
 *
 * @param int    $term_id
 * @param string $day_name
 * @param int    $days_ahead
 * @return string  e.g. "St. Patrick's Day (Mar 19)" or ''
 */
function cerrito_get_next_themed_date_for_day( $term_id, $day_name, $days_ahead = 60 ) {
    $start      = wp_date( 'Y-m-d' );
    $end        = wp_date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
    $dated_meta = get_term_meta( $term_id, 'themed_dates', true );

    if ( empty( $dated_meta ) || ! is_array( $dated_meta ) ) return '';

    foreach ( $dated_meta as $entry ) {
        if ( ! isset( $entry['date'], $entry['theme_id'] ) ) continue;
        if ( $entry['date'] < $start || $entry['date'] > $end ) continue;

        $d = DateTime::createFromFormat( 'Y-m-d', $entry['date'] );
        if ( $d && $d->format( 'l' ) === $day_name ) {
            $theme = get_term( $entry['theme_id'], 'game-theme' );
            if ( $theme && ! is_wp_error( $theme ) ) {
                return $theme->name . ' (' . $d->format( 'M j' ) . ')';
            }
        }
    }

    return '';
}

// ── Image helpers ─────────────────────────────────────────────────────────────

/**
 * Resolve an ACF image field (array, attachment ID, or URL string) to a URL.
 *
 * @param mixed $field
 * @return string
 */
function cerrito_resolve_image_url( $field ) {
    if ( is_array( $field ) )                    return $field['url'] ?? '';
    if ( is_numeric( $field ) )                  return (string) ( wp_get_attachment_url( (int) $field ) ?: '' );
    if ( is_string( $field ) && $field !== '' )  return $field;
    return '';
}

// ── Date helpers ──────────────────────────────────────────────────────────────

/**
 * Normalise an ACF date field to Y-m-d format.
 * Handles: Ymd (20250219), m/d/Y (02/19/2025), and Y-m-d strings.
 *
 * @param string $date
 * @return string
 */
function cerrito_normalise_date( $date ) {
    if ( ! $date ) return '';

    // Ymd format
    if ( strlen( $date ) === 8 && ctype_digit( $date ) ) {
        return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
    }

    // m/d/Y format
    if ( strpos( $date, '/' ) !== false ) {
        $d = DateTime::createFromFormat( 'm/d/Y', $date );
        return $d ? $d->format( 'Y-m-d' ) : $date;
    }

    return $date;
}

// ── String helpers ────────────────────────────────────────────────────────────

/**
 * Replace newlines in an address string with spaces for single-line display.
 *
 * @param string $address
 * @return string
 */
function cerrito_flatten_address( $address ) {
    return str_replace( [ "\r\n", "\n", "\r" ], ' ', $address );
}

// ── Query helpers ─────────────────────────────────────────────────────────────

/**
 * Filter an array of event WP_Posts to only those matching a game type slug or name.
 *
 * @param WP_Post[] $events
 * @param string    $game_type
 * @return WP_Post[]
 */
function cerrito_filter_by_game_type( array $events, $game_type ) {
    return array_filter( $events, function( $event ) use ( $game_type ) {
        $types = get_the_terms( $event->ID, 'game_type' );
        if ( ! $types || is_wp_error( $types ) ) return false;
        foreach ( $types as $type ) {
            if ( $type->slug === $game_type || strtolower( $type->name ) === strtolower( $game_type ) ) {
                return true;
            }
        }
        return false;
    } );
}

/**
 * Filter an array of event WP_Posts to only those belonging to a given location ID.
 *
 * @param WP_Post[] $events
 * @param int       $location_id
 * @return WP_Post[]
 */
function cerrito_filter_by_location( array $events, $location_id ) {
    return array_filter( $events, function( $event ) use ( $location_id ) {
        $loc = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
        return $loc && $loc->ID === $location_id;
    } );
}

// ── Sort helpers ──────────────────────────────────────────────────────────────

/**
 * Convert a free-text event_time string to minutes-since-midnight for sorting.
 * Handles formats like "7:00 PM", "7pm", "19:00", "7:30", "7".
 * Returns 9999 for blank/unparseable values so they sort last.
 *
 * @param string $time_str
 * @return int
 */
function cerrito_time_to_minutes( $time_str ) {
    $time_str = trim( (string) $time_str );
    if ( $time_str === '' ) return 9999;

    // strtotime handles most common human formats
    $ts = strtotime( $time_str );
    if ( $ts === false ) return 9999;

    return (int) date( 'H', $ts ) * 60 + (int) date( 'i', $ts );
}

/**
 * Sort an array of WP_Post event objects by their event_time ACF field,
 * earliest first. Events with no time sort to the end.
 * Returns a new sorted array; does not modify in place.
 *
 * @param WP_Post[] $events
 * @return WP_Post[]
 */
function cerrito_sort_events_by_time( array $events ) {
    usort( $events, function( $a, $b ) {
        $ta = cerrito_time_to_minutes( get_field( 'event_time', $a->ID ) );
        $tb = cerrito_time_to_minutes( get_field( 'event_time', $b->ID ) );
        return $ta - $tb;
    } );
    return $events;
}

// ── Skip-date helpers ─────────────────────────────────────────────────────────

/**
 * Return the cancellation reason for a recurring event on a specific date,
 * or an empty string if the event is not cancelled on that date.
 *
 * Uses the ACF repeater field `skip_dates` with sub-fields:
 *   skip_date   — Date Picker (return format Y-m-d)
 *   skip_reason — Text (optional public-facing reason)
 *
 * ACF setup required:
 *   Field group : Events
 *   Field label : Skip Dates
 *   Field name  : skip_dates          (Repeater)
 *   Sub-fields  : skip_date           (Date Picker — return format Y-m-d)
 *                 skip_reason         (Text — optional)
 *
 * @param int    $post_id  Event post ID
 * @param string $date     Y-m-d date to check
 * @return string  Cancellation reason, or '' if not cancelled
 */
function cerrito_get_skip_reason( $post_id, $date ) {
    $rows = get_field( 'skip_dates', $post_id );
    if ( empty( $rows ) || ! is_array( $rows ) ) return '';
    foreach ( $rows as $row ) {
        $skip = cerrito_normalise_date( (string) ( $row['skip_date'] ?? '' ) );
        if ( $skip === $date ) {
            return (string) ( $row['skip_reason'] ?? '' );
        }
    }
    return '';
}

/**
 * Check whether a recurring event has been marked as skipped on a specific date.
 * Convenience wrapper around cerrito_get_skip_reason().
 *
 * @param int    $post_id
 * @param string $date     Y-m-d
 * @return bool
 */
function cerrito_is_skipped_on( $post_id, $date ) {
    return cerrito_get_skip_reason( $post_id, $date ) !== '';
}

/**
 * Given a day name (e.g. "Tuesday"), return the Y-m-d of the next calendar
 * occurrence of that weekday on or after today (WordPress timezone).
 *
 * Used by recurring-schedule shortcodes to know which concrete date an event
 * "would" fall on so skip_dates can be checked.
 *
 * @param string $day_name  Full English weekday name, e.g. "Monday"
 * @return string           Y-m-d
 */
function cerrito_next_date_for_day( $day_name ) {
    $today    = (int) wp_date( 'N' ); // 1 (Mon) … 7 (Sun)
    $day_map  = [ 'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7 ];
    $target   = $day_map[ $day_name ] ?? 1;
    $diff     = ( $target - $today + 7 ) % 7;
    return wp_date( 'Y-m-d', strtotime( "+{$diff} days" ) );
}

/**
 * Get the next occurrence date for a recurring event based on its first `when` term.
 * Returns '' if no when term is found.
 *
 * @param WP_Post $event
 * @return string Y-m-d or ''
 */
function cerrito_next_date_for_day_from_event( $event ) {
    $when_terms = get_the_terms( $event->ID, 'when' );
    if ( ! $when_terms || is_wp_error( $when_terms ) ) return '';
    return cerrito_next_date_for_day( $when_terms[0]->name );
}

// ── Render helpers ────────────────────────────────────────────────────────────

/**
 * Render a single event group card (used by master schedule and recurring schedule).
 *
 * @param array  $group              Keys: type, theme, class, events, show_date (optional)
 * @param bool   $is_recurring       Whether this group represents a recurring event
 * @param string $show_game_logo     'yes'|'no'
 * @param string $show_game_description 'yes'|'no'
 * @param string $display            'full'|'compact'
 * @return string
 */
function cerrito_render_event_group( array $group, $is_recurring, $show_game_logo = 'no', $show_game_description = 'no', $display = 'full' ) {
    $group['events'] = cerrito_sort_events_by_time( $group['events'] );

    if ( $display === 'compact' ) {
        return cerrito_render_event_group_compact( $group, $is_recurring );
    }

    ob_start();

    $game_emoji       = cerrito_get_game_emoji( $group['type'] );
    $game_logo        = ( $show_game_logo        === 'yes' ) ? cerrito_get_game_logo( $group['type'] )        : '';
    $game_description = ( $show_game_description === 'yes' ) ? cerrito_get_game_description( $group['type'] ) : '';

    // Theme display fields
    $theme_emoji = '';
    if ( $group['theme'] ) {
        $theme_name = preg_replace( '/\s*\([^)]*\)$/', '', $group['theme'] ); // strip "(Mar 19)"
        $theme_term = get_term_by( 'name', $theme_name, 'game-theme' )
                   ?: get_term_by( 'slug', sanitize_title( $theme_name ), 'game-theme' );
        if ( $theme_term && ! is_wp_error( $theme_term ) ) {
            $theme_emoji = (string) get_field( 'theme_emoji', 'game-theme_' . $theme_term->term_id );
        }
    }

    $show_date = ( ! $is_recurring && isset( $group['show_date'] ) ) ? $group['show_date'] : '';
    $themed_attr = ! empty( $group['theme'] ) ? ' data-themed="1"' : '';
    ?>

    <div class="cerrito-event-group"<?php echo $themed_attr; ?>>

        <?php if ( $game_logo || $game_description ) : ?>
            <div class="cerrito-game-header">
                <?php if ( $game_logo ) : ?>
                    <div class="cerrito-game-logo">
                        <img src="<?php echo esc_url( $game_logo ); ?>" alt="<?php echo esc_attr( $group['type'] ); ?>">
                    </div>
                <?php endif; ?>
                <div class="cerrito-game-info">
                    <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'], $show_date ); ?>
                    <?php if ( $game_description ) : ?>
                        <p class="cerrito-game-description"><?php echo esc_html( $game_description ); ?></p>
                    <?php endif; ?>
                    <?php if ( $group['theme'] && $theme_emoji ) : ?>
                        <div class="cerrito-theme-display">
                            <span class="cerrito-theme-emoji"><?php echo esc_html( $theme_emoji ); ?></span>
                            <span class="cerrito-theme-name"><?php echo esc_html( $group['theme'] ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <?php cerrito_render_occurrence_title( $game_emoji, $group['type'], $group['theme'], $show_date ); ?>
            <?php if ( $group['theme'] && $theme_emoji ) : ?>
                <div class="cerrito-theme-display">
                    <span class="cerrito-theme-emoji"><?php echo esc_html( $theme_emoji ); ?></span>
                    <span class="cerrito-theme-name"><?php echo esc_html( $group['theme'] ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ( $group['events'] as $event ) :
            $check_date = $is_recurring
                ? cerrito_next_date_for_day_from_event( $event )
                : ( cerrito_normalise_date( get_field( 'event_date', $event->ID ) ) ?: '' );
            cerrito_render_location_card( $event, $group['class'], $check_date );
        endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}

/**
 * Render a compact (no-card) event group — just type label + inline venue rows.
 *
 * @param array $group
 * @param bool  $is_recurring
 * @return string
 */
function cerrito_render_event_group_compact( array $group, $is_recurring ) {
    $group['events'] = cerrito_sort_events_by_time( $group['events'] );
    ob_start();

    $game_emoji  = cerrito_get_game_emoji( $group['type'] );
    $show_date   = ( ! $is_recurring && isset( $group['show_date'] ) ) ? $group['show_date'] : '';
    $themed_attr = ! empty( $group['theme'] ) ? ' data-themed="1"' : '';
    $color_style = cerrito_game_color_style( $group['type'], 'color' );
    ?>
    <div class="cerrito-event-group cerrito-event-group--compact"<?php echo $themed_attr; ?>>

        <div class="cerrito-compact-type <?php echo esc_attr( $group['class'] ); ?>"<?php echo $color_style; ?>>
            <?php if ( $game_emoji ) : ?>
                <span class="cerrito-game-emoji"><?php echo esc_html( $game_emoji ); ?></span>
            <?php endif; ?>
            <?php echo esc_html( $group['type'] ); ?>
            <?php if ( $group['theme'] ) : ?>
                <span class="cerrito-theme-badge"><?php echo esc_html( $group['theme'] ); ?></span>
            <?php endif; ?>
            <?php if ( $show_date ) : ?>
                <span class="cerrito-date-badge">(<?php echo esc_html( $show_date ); ?>)</span>
            <?php endif; ?>
        </div>

        <?php foreach ( $group['events'] as $event ) :
            $event_time      = get_field( 'event_time', $event->ID );
            $location        = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
            if ( ! $location ) continue;
            $cancel_date     = $is_recurring ? cerrito_next_date_for_day_from_event( $event ) : '';
            $cancel_reason   = $cancel_date ? cerrito_get_skip_reason( $event->ID, $cancel_date ) : '';
            $is_cancelled    = $cancel_reason !== '';
        ?>
            <div class="cerrito-compact-row<?php echo $is_cancelled ? ' cerrito-compact-row--cancelled' : ''; ?>">
                <span class="cerrito-compact-venue">
                    <a href="<?php echo esc_url( get_permalink( $location->ID ) ); ?>">
                        <?php echo esc_html( $location->post_title ); ?>
                    </a><?php if ( $is_cancelled ) : ?><span class="cerrito-compact-cancel-reason"> — <?php echo esc_html( $cancel_reason ?: 'Cancelled this week' ); ?></span><?php endif; ?>
                </span>
                <?php if ( $event_time ) : ?>
                    <span class="cerrito-compact-time"><?php echo esc_html( $event_time ); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the occurrence title line (emoji + type + theme badge + date badge).
 *
 * @param string $emoji
 * @param string $type
 * @param string $theme
 * @param string $date
 */
function cerrito_render_occurrence_title( $emoji, $type, $theme, $date = '' ) {
    $color_style = cerrito_game_color_style( $type, 'color' );
    ?>
    <div class="cerrito-occurrence-title">
        <?php if ( $emoji ) : ?>
            <span class="cerrito-game-emoji"><?php echo esc_html( $emoji ); ?></span>
        <?php endif; ?>
        <span class="cerrito-occurrence-type"<?php echo $color_style; ?>><?php echo esc_html( $type ); ?></span>
        <?php if ( $theme ) : ?>
            <span class="cerrito-theme-badge">Theme Rounds</span>
        <?php endif; ?>
        <?php if ( $date ) : ?>
            <span class="cerrito-date-badge"><?php echo esc_html( $date ); ?></span>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render a location card for a single event.
 *
 * @param WP_Post $event
 * @param string  $event_class
 * @param string  $check_date   Optional Y-m-d — if provided and event is cancelled on
 *                              this date, the card renders struck-through with the reason
 */
function cerrito_render_location_card( $event, $event_class, $check_date = '' ) {
    $event_time      = get_field( 'event_time',      $event->ID );
    $location        = cerrito_resolve_location( get_field( 'event_location', $event->ID ) );
    $age_restriction = get_field( 'age_restriction', $event->ID );
    $special_notes   = get_field( 'special_notes',   $event->ID );

    if ( ! $location ) return;

    $cancel_reason = $check_date ? cerrito_get_skip_reason( $event->ID, $check_date ) : '';
    $is_cancelled  = $cancel_reason !== '';
    $event_type    = cerrito_get_event_type_string( $event->ID );
    $border_style  = cerrito_game_color_style( $event_type, 'border-left-color' );
    $card_class    = trim( 'cerrito-location-card ' . $event_class . ( $is_cancelled ? ' cerrito-location-card--cancelled' : '' ) );

    $location_logo    = cerrito_get_location_logo( $location->ID );
    $location_address = cerrito_get_location_address( $location->ID );
    ?>
    <div class="<?php echo esc_attr( $card_class ); ?>"<?php echo $border_style; ?>>
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
                <?php if ( $is_cancelled ) : ?>
                    <span class="cerrito-cancel-reason"><?php echo esc_html( $cancel_reason ?: 'Cancelled this week' ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $event_time ) : ?>
                <div class="cerrito-location-time">🕐 <?php echo esc_html( $event_time ); ?></div>
            <?php endif; ?>
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
    <?php
}

/**
 * Enqueue the schedule stylesheet.
 */
function cerrito_enqueue_styles() {
    if ( ! wp_style_is( 'cerrito-schedule', 'enqueued' ) ) {
        wp_enqueue_style( 'cerrito-schedule' );
    }
}
