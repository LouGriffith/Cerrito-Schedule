# Changelog

All notable changes to the Cerrito Schedule System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [6.11.0] - 2026-03-12

### Changed
- Cancellation reason now appears **inline** on the same row as the struck-through venue
  name (e.g. "Ghost River Brewing — Private event this week"), rather than on a second line
- Cancelled events now show in **all** shortcodes including `[cerrito_today]` — previously
  today's widget suppressed them entirely
- Full location cards (`[cerrito_today]` full style, `[cerrito_master_schedule]`,
  `[cerrito_recurring_schedule]`) also render cancelled events struck-through with the
  inline reason next to the venue name
- `cerrito_render_location_card()` now accepts an optional `$check_date` parameter;
  callers pass the relevant date so cancellation can be detected per-render

---

## [6.10.0] - 2026-03-12

### Changed
- Cancelled recurring occurrences now display as struck-through instead of being hidden,
  so customers can see that an event exists but is not running this week
- Added optional `skip_reason` text sub-field to the `skip_dates` ACF repeater —
  add a public-facing message (e.g. "Private event this week") that appears below
  the struck-through venue name in italic red; falls back to "Cancelled this week"
  if the field is left blank
- `[cerrito_today]` continues to suppress cancelled events entirely (no strike-through)
- Added `cerrito_get_skip_reason( $post_id, $date )` helper returning the reason string
  (or `''` if not cancelled); `cerrito_is_skipped_on()` remains as a boolean wrapper
- Added `cerrito_next_date_for_day_from_event( $event )` helper for use inside renderers

### ACF update required
- Add sub-field to the `skip_dates` repeater:
  Label: `Cancellation Reason` · Name: `skip_reason` · Type: Text (optional)

---

## [6.9.0] - 2026-03-11

### Changed
- Compact schedule rows now show venue name on the left and time on the right,
  using `space-between` to fill the full row width
- Arrow separator (`→`) removed from compact rows
- Time no longer has a fixed minimum width; sits flush right

---

## [6.8.0] - 2026-03-11

### Changed
- `[cerrito_locations]` now sorts locations letters-first (A–Z), with number-prefixed
  venue names appearing after all alphabetical entries (e.g. Ale House, Barley's … 1 Stop Bar)

---

## [6.7.0] - 2026-03-11

### Added
- Skip Dates support for recurring events — add an ACF repeater field `skip_dates`
  (sub-field `skip_date`, Date Picker, return format `Y-m-d`) to the Event post type to
  cancel individual occurrences without unpublishing the event
- Cancelled occurrences are suppressed across all shortcodes: `[cerrito_today]`,
  `[cerrito_recurring_schedule]`, `[cerrito_master_schedule]`, and `[cerrito_locations]`
- `cerrito_is_skipped_on( $post_id, $date )` helper in `helpers.php`
- `cerrito_next_date_for_day( $day_name )` helper — returns the next calendar date
  (WordPress timezone) for a given weekday name

### Fixed
- `[cerrito_today]` compact header now displays full date format:
  "Tuesday, March 10, 2026" (was "Tuesday Mar 10")

---

## [6.6.0] - 2026-03-10

### Added
- `[cerrito_locations]` shortcode — location directory listing all venues with their
  scheduled events nested underneath
  - Recurring events grouped by day-of-week with time(s) shown inline
  - Upcoming one-time events grouped by date
  - Locations with no events show a configurable "no events" message rather than being hidden
  - Parameters: `game_type`, `show_logo`, `show_address`, `show_specials`, `days_ahead`,
    `orderby` (name/menu_order), `empty_message`

---

## [6.5.0]

### Added
- `display="compact"` parameter for `[cerrito_master_schedule]` and `[cerrito_recurring_schedule]`
  — replaces full venue cards with tight inline rows: `time → venue name`
- `show_day_filter="yes"` parameter for `[cerrito_master_schedule]`
  — renders pill buttons (All · Mon · Tue …) above the schedule; only days with events appear
- `default_day="Tuesday"` parameter — pre-selects a day on page load
- `show_themed_filter="yes"` parameter for `[cerrito_master_schedule]`
  — adds a 🎭 Themed toggle button; works independently or combined with the day filter
- Both filters are two-dimensional: selecting "Tuesday + Themed" shows only themed Tuesday events;
  days with no matching groups are automatically hidden

### Changed
- Events within each group are now sorted by start time (earliest first) across all shortcodes
- `date()` replaced with `wp_date()` throughout for correct WordPress timezone handling
- All PHP 8-only syntax removed for PHP 7.4 compatibility (union return types, `mixed` hints,
  typed properties replaced with docblock comments)
- Plugin URI updated to `https://github.com/lougriffith/cerrito-schedule`
- GitHub updater repo references lowercased to match actual repository name

### Fixed
- `data-themed` attribute now stamped on event group divs in both full and compact display modes
- Themed filter correctly hides entire day blocks when no themed events remain after filtering

---

## [6.4] - 2026-02-28

### Fixed
- Timezone bug: replaced all `date()` calls with `wp_date()` across 6 files so event dates
  respect the WordPress-configured timezone rather than the server's UTC setting

---

## [6.2] - 2026-02-20

### Added
- `[cerrito_upcoming_themes_list]` shortcode — formatted upcoming themed dates from
  game_type term meta, sorted by date with location/time details

### Changed
- CSS consolidated into single `schedule.css` loaded once via `wp_enqueue_style()`

---

## [4.5] - 2026-02-16

### Added
- `[cerrito_themed_rounds]` shortcode — card-based display of upcoming themed events
- Day abbreviation, M/D date format, game emoji, hover effects, colour coding

## [4.4] - 2026-02-16

### Added
- Automatic location detection on single location pages (`is_singular('location')`)

## [4.3] - 2026-02-16

### Added
- `style="compact"` parameter for `[cerrito_today]`

## [4.2] - 2026-02-16

### Added
- `[cerrito_today]` shortcode — all events (recurring + one-time) for the current day

## [4.1] - 2026-02-16

### Changed
- Game descriptions pulled from native taxonomy description field instead of custom ACF field

## [4.0] - 2026-02-16

### Added
- `[cerrito_master_schedule]` shortcode combining recurring and upcoming events by day of week
- `show_game_logo` and `show_game_description` parameters

## [3.0] - 2026-02-16

### Added
- `[cerrito_recurring_schedule]` shortcode — weekly recurring events with full location cards
- "Coming Soon" section for events without a day assignment

## [2.0] - 2026-02-16

### Added
- `game_type` and `location` filtering parameters across all shortcodes

## [1.0] - 2026-02-16

### Added
- Initial release: `[cerrito_schedule]` shortcode, ACF integration, admin columns plugin
