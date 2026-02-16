# Installation Guide

## Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher  
- Advanced Custom Fields (Free or Pro version)

## Step 1: Install Required Plugin

Install and activate **Advanced Custom Fields** from the WordPress plugin repository.

## Step 2: Install Cerrito Schedule Plugins

1. Download both plugin files:
   - `cerrito-schedule.php`
   - `cerrito-events-admin.php`

2. Upload to `/wp-content/plugins/` directory

3. Activate both plugins in **Plugins** menu

## Step 3: Configure ACF Field Groups

### Create Custom Post Types (if not already exist)

You'll need these custom post types:
- `event`
- `location`

### Create Taxonomies

You'll need these taxonomies:
- `game_type` - for Event post type
- `when` - for Event post type (days of week)

### Event Fields

Create an ACF Field Group for the Event post type with these fields:

| Field Label | Field Name | Field Type | Notes |
|------------|------------|------------|-------|
| Event Date | event_date | Date Picker | |
| Event Time | event_time | Text | |
| Event Location | event_location | Relationship | Post Type: location |
| Special Theme | special_theme | Text | |
| Theme Image | theme_image | Image | Return: URL |
| Age Restriction | age_restriction | Text | |
| Special Notes | special_notes | WYSIWYG | |
| Is Recurring | is_recurring | True/False | |
| Recurrence Pattern | recurrence_pattern | Text | |

### Location Fields

Create an ACF Field Group for the Location post type:

| Field Label | Field Name | Field Type | Notes |
|------------|------------|------------|-------|
| Location Logo | location_logo | Image | Return: Image URL |
| What Time Is It Played? | time | Text | |
| Address | location_address | Text Area | |
| Specials | specials | WYSIWYG | |
| Website | website | URL | |

### Game Type Term Fields

Create an ACF Field Group for the game_type taxonomy:

| Field Label | Field Name | Field Type | Notes |
|------------|------------|------------|-------|
| Game Emoji | game_emoji | Text | Single emoji character |
| Game Logo | game_logo | Image | Return: Image URL |

**Note:** Use the native taxonomy Description field for game descriptions.

### When Taxonomy Terms

Manually create these terms in the "When" taxonomy:
- Monday
- Tuesday
- Wednesday  
- Thursday
- Friday
- Saturday
- Sunday

## Step 4: Create Initial Content

### 1. Create Game Types

Go to **Events → Game Types** and create:
- Music Bingo (🎶)
- General Knowledge Trivia (🤔)
- Add more as needed

### 2. Create Locations  

Go to **Locations → Add New** and add your venues:
- Upload logos
- Fill in addresses
- Add any specials

### 3. Create Events

Go to **Events → Add New**:

**For recurring events:**
- Set Event Date (optional)
- Set Event Time
- Select Location
- Select Game Type
- Check "Is Recurring"
- Select day(s) in "When" taxonomy

**For one-time events:**
- Set Event Date (required)
- Set Event Time
- Select Location
- Select Game Type
- Don't check "Is Recurring"

## Step 5: Add Shortcodes to Pages

### Homepage
Create or edit your homepage and add:
```
[cerrito_today style="compact"]
```

### Schedule Page
Create a new page called "Schedule" and add:
```
[cerrito_master_schedule show_game_logo="yes"]
```

### Location Template (in Elementor)
On your single location template, add:
```
[cerrito_schedule]
```
This will automatically show events for each location.

### Themed Rounds Page
Create a page for themed events:
```
[cerrito_themed_rounds]
```

## Step 6: Test

1. View your homepage - should see today's events
2. View schedule page - should see all events organized by day
3. View a location page - should see only that location's events
4. Create a test event with a theme - should appear in themed rounds

## Troubleshooting

### Events not showing
- Check that Event Date is set correctly (YYYY-MM-DD format)
- Verify Location is selected
- Ensure Game Type is assigned

### Location logos not displaying
- Confirm Image field return format is "Image URL"
- Check that images are uploaded
- Verify field name is exactly `location_logo`

### Recurring events not appearing
- Check "Is Recurring" box is checked
- Ensure day is selected in "When" taxonomy
- Verify the current day matches selected day

### Admin columns not showing
- Ensure `cerrito-events-admin.php` is activated
- Check that you're viewing the Events post type list

## Next Steps

See [USER-MANUAL.pdf](USER-MANUAL.pdf) for complete usage instructions and shortcode reference.
