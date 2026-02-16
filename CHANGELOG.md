# Changelog

All notable changes to the Cerrito Schedule System will be documented in this file.

## [4.5] - 2026-02-16

### Added
- New `[cerrito_themed_rounds]` shortcode for displaying upcoming themed events
- Card-based layout with colored date boxes matching design mockup
- Automatic filtering to show only events with special themes
- Mobile responsive themed rounds display

### Features
- Day abbreviation display (WED, THU, FRI)
- Date in M/D format (2/11, 3/5)
- Game emoji integration
- Theme name linked to event page
- Hover effects on cards
- Color coding: red for trivia, teal for bingo, blue for others

## [4.4] - 2026-02-16

### Added
- Automatic location detection on single location pages
- Shortcodes now auto-filter by current location when used on location templates
- No manual location parameter needed in Elementor templates

### Changed
- All shortcodes detect `is_singular('location')` and apply location filter automatically
- Can still manually override with explicit location parameter

## [4.3] - 2026-02-16

### Added
- Compact style option for `[cerrito_today]` shortcode
- `style="compact"` parameter for simpler homepage display

### Changed
- Today's events can now show as simple list or full cards
- Default header format varies by style (compact vs full)

## [4.2] - 2026-02-16

### Added
- New `[cerrito_today]` shortcode showing all events happening today
- Automatic date detection and display
- Support for both recurring and one-time events on current day

### Features
- Large day/date header
- Same location card layout as other schedules
- Empty state message when no events today

## [4.1] - 2026-02-16

### Changed
- Master schedule now uses native WordPress taxonomy description instead of custom ACF field
- Game descriptions pulled from standard taxonomy description field

## [4.0] - 2026-02-16

### Added
- New `[cerrito_master_schedule]` shortcode combining recurring and upcoming events
- Events organized by day of week with separate sections for "Every Monday" vs "Upcoming"
- Game logo display option via `show_game_logo` parameter
- Game description display option via `show_game_description` parameter

### Features
- Comprehensive weekly view
- Shows both recurring weekly events and upcoming one-time events
- Organized by day of week (Mondays, Tuesdays, etc.)

## [3.4] - 2026-02-16

### Changed
- Address display changed from multi-line to single line
- Line breaks in addresses converted to spaces

## [3.3] - 2026-02-16

### Fixed
- Location logo field name corrected to `location_logo`
- Address field name corrected to `location_address`
- Both fields now check correct ACF field names first

## [3.2] - 2026-02-16

### Fixed
- Removed map_link requirement for address display
- Added fallback field name checks for location logo and address
- Improved compatibility with different ACF field naming

## [3.1] - 2026-02-16

### Fixed
- Location logo now handles all ACF image return formats (array, ID, URL)
- Address display properly renders on recurring schedule

## [3.0] - 2026-02-16

### Added
- New `[cerrito_recurring_schedule]` shortcode for weekly recurring events
- Location details display including logo, address, age restrictions, special notes
- "Coming Soon" section for events without day assignment
- Enhanced card layout for recurring events

### Features
- Events grouped by day of week (Every Monday, Every Tuesday, etc.)
- Full location information cards
- Location logo display
- Clickable location names
- Age restriction badges
- Special notes display

## [2.0] - 2026-02-16

### Added
- Game type filtering via `game_type` parameter
- Location filtering via `location` parameter
- Support for filtering by both slug and ID

### Changed
- Enhanced filtering system across all shortcodes

## [1.0] - 2026-02-16

### Added
- Initial release
- `[cerrito_schedule]` shortcode for upcoming events
- Basic event display with date grouping
- Location and time display
- Game type categorization
- ACF integration
- Admin columns and filters plugin
- Event management system

### Features
- Event date and time fields
- Location relationship
- Game type taxonomy
- Special theme support
- Recurring event support
- When (day of week) taxonomy
- Age restriction field
- Special notes field

## Admin Plugin Changelog

### [1.0] - 2026-02-16

#### Added
- Custom columns in Events admin list
  - Date (formatted)
  - Time
  - Location (clickable)
  - Recurring status with days
  - Game Type (clickable filter)
- Filter dropdowns
  - Filter by location
  - Filter by recurring/one-time status
- Sortable columns
  - All custom columns can be sorted
- Enhanced admin interface

## Upcoming Features

### Planned
- Themed dates feature for game types
- Date-based theme assignment
- Theme reusability across events
- Enhanced theme management

### Under Consideration
- Email notifications for upcoming events
- iCal export
- Google Calendar integration
- Social media integration
- Event capacity management
