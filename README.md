# Palefire

Drupal 11 theme (starterkit-generated) built on Bootstrap 5.3 + Dart Sass.

## Build

    npm install        # bootstrap, bootstrap-icons, swup, sass
    npm run build      # scss/style.scss -> css/style.css, copies Bootstrap JS, Swup + icons
    npm run watch      # recompile on save

`node_modules/` is not committed (`.gitignore`); run `npm install` after
cloning. It used to be tracked, and Windows checkouts kept rewriting its
files (the `.bin/sass` symlink, file modes), so they ended up in commits.
Production needs only the build output in the repo: `css/style.css`,
`js/vendor/` and `images/bi/`.

## Structure

- `palefire.info.yml` – regions, global libraries, `core_version_requirement: ^11`
- `palefire.breakpoints.yml` – Bootstrap 5 breakpoints (sm 576 / md 768 / lg 992 / xl 1200 / xxl 1400)
- `scss/` – `_variables.scss` (brand + Bootstrap overrides), `base/`, `layout/`, `components/`
- `components/toc/` – Single Directory Component: "On this page" navigation
- `src/Hook/PalefireHooks.php` – OOP theme hooks (TOC, section anchors, branding)
- `templates/paragraphs/` – section markup for Modular pages (bento head + Visit card: see below)
- `templates/includes/` – inline SVG logo (`logo-lsu-libraries.svg.twig`), utility panels (`utility-panels.html.twig`), building maps
- `scripts/palefire_modular_setup.php` – one-time Paragraphs/field setup (drush php:script) for sites **without** palefire_modular; it exits without changes when palefire_modular is enabled
- `scripts/palefire_visit_setup.php` – Visit card fields and the `light-map` section theme (sites with palefire_modular)
- `scripts/palefire_modular_setup_undo.php` – removes the unused paragraph types/storages a partial `palefire_modular_setup.php` run leaves on a palefire_modular site

## Modular pages

Any paragraph type with `field_section_title` placed in `field_sections` on a
`modular_page` node becomes a `<section>` with an H2, a stable anchor, and a TOC
entry. `field_full_page` turns on full-viewport scroll-snap (md and up).

### Building maps

A bento section can show a building floor map under its search form
(`bento_search.field_bento_map` -> `building_map` paragraph). Pointing at a
room lights it at once; its tooltip opens after a wait
(`--pf-map-tip-delay`, 1.75s, so sweeping the pointer across the map doesn't
flash tooltips), or at once on keyboard focus or tap. It fades in while it
slides in from the left (just a fade under reduced motion). The
"Legend & spaces" list puts Floor 1 first, and the background video dims and
blurs while the map is in view.

Every room you can select (map area, its name, or its legend entry) shows a
pointer cursor. Rooms with a link are links; rooms without one are buttons
(`role="button"` on the drawing, `<button>` in the list) that open the
tooltip, so Enter and Space work and screen readers announce something
actionable. The tooltip text also reaches screen readers through
`aria-describedby`. Clicking a room's name does what clicking the room does,
unless you are selecting the text.

"Search LSU Libraries" and the map heading are plain h3s: they take the
modular page's h3 styles, with no type of their own. They carry `data-toc`,
so the TOC lists them under their section in every mode. The TOC block's
"Include h3 sub-links on these modes" setting (palefire_modular,
`deep_modes`, default `guide`) still decides whether *all* h3s are listed;
on other modes only `h3[data-toc]` are.

Each building is one drawing plus an entry in `maps/buildings.yml` (label,
default heading, Twig include, rooms with floor). The content lives in the
**Building spaces** taxonomy: one term per building (its description is the
default intro) with a child term per room (tooltip text, access, optional
link). Unpublish a room term to draw it as plain floor. Editors add a
**Building map** to the bento and pick the building; nothing else is needed.
**Room overrides (optional)** on the paragraph change one room's tooltip,
access or link on that page only. It stays collapsed to an "Add Room
override" button until used. **You are here (optional)** is for a page about
one room: pick the room and the map marks it with a bobbing down arrow over
its name, a "Here" badge in its legend entry and tooltip, and
`aria-current="location"`. It is ignored when the room is in another
building or unpublished.

```bash
npm run build      # compiles _building-map.scss into css/style.css; commit it
drush php:script themes/custom/palefire/scripts/palefire_building_map_setup.php   # adds field_map_here
drush cr
```

The setup script creates the vocabulary, paragraph types and fields, and
creates any building/room terms missing from Building spaces (it never
overwrites existing terms, so editors own the text). Re-run it after editing
`buildings.yml`. To add a building: add its drawing under
`templates/includes/`, add an entry to `buildings.yml`, and re-run.

Layout: the heading, intro and legend form a 15-18rem column beside the
map while the map still gets about 36rem. The drawing starts level with the
heading (it used to be centred against the column, which left a tall gap
above it as the legend grew) and is sticky (`position: sticky`, just under
the top bar), so it stays in view while the legend and the page scroll.
`building-map.js` adds `.is-unstuck` when the drawing is taller than the
window below the top bar. In a narrower container (under 58rem, a container
query on `.pf-bento__visit`, so the TOC sidebar is taken into account) it
stacks: heading, drawing, then the legend. The "Legend & spaces" toggle always shows: open beside
the map, collapsed under it. Inside a section the bento adds no padding of
its own (the section's `--pf-section-pad-*` already applies).

Icons: the squares behind the galleries are the elevators. They are outline
only (not part of a gallery region) with the elevator arrows drawn straight
on the floor: no tile, fill or border behind them
(`templates/includes/map-icon-elevator.svg.twig`). The restroom tiles keep
their outline but have no fill either. List the icons a drawing
uses under `legend` in `buildings.yml` (currently `elevator`) to explain them
in the legend; this is theme-only, so `npm run build` and `drush cr` are
enough.

Swatches: the legend and tooltip swatches are the map's own hatch, a 5-unit
line every 20 units at the same white and opacity as the rooms.
`building-map.js` sets `--pf-map-hatch-period` to those 20 units in screen
pixels whenever the map resizes, so the swatch lines keep the map's weight
and spacing at any size. The period, line weight and offset are rounded to
whole device pixels (`--pf-map-hatch-line`, `--pf-map-hatch-offset`), so all
four swatch lines render at the same weight.

- **Entrance:** the "Entrance" floor label (`--x: 2700; --y: 2850`) is a
  label group with Font Awesome's `fa-arrow-alt-up` (solid, inlined as path
  data so the SVG needs no icon font) above the word. It shares the label's
  rotate and floor skew; `building-map.js` re-centres it over the word once
  fonts load.
- **Elevator icons:** drawn at `--scale: .68` on the map
  (`.pf-map__icon--elevator`); the legend icon keeps its size.
- **Load sequence:** the outlines trace; as the last lines land the rooms
  fade in one after another (top of the drawing first), each room name a
  beat after its room, then icons and hidden lines. Tune
  `--pf-map-reveal-step` (gap between rooms, 70ms),
  `--pf-map-reveal-label-lag` (room to name, 220ms) and
  `--pf-map-reveal-fade` on `.pf-map`. Reduced motion skips it.

Dark chrome: while a dark section is active, the top bar uses the TOC
sidebar's wash and blur (`--pf-sidebar-bg`, `scss/layout/_chrome-dark.scss`),
so the two match. To make both darker, change `sidebar-bg` in
`$pf-chrome-dark` (`_modular-tokens.scss`).

Upgrading from the per-page "Map space" version: the old `map_space` type
and `building_map.field_map_spaces` are listed but kept. Run the script with
`-- cleanup` to delete them. The bento's map is now rendered by
`palefire_preprocess_paragraph__bento_search()`, so it no longer depends on
the bento's view display.

Hover timing: pointing at a room, its name or its list entry lights it at
once (hatch, gold name, lift); only the tooltip waits `--pf-map-tip-delay`.
Moving from a room onto its name, or back, keeps the wait running; once a
tooltip is open, moving to another room switches it at once. Keyboard focus
and taps still open it straight away. The tooltip slides
`--pf-map-tip-shift` (14px) in from the left over `--pf-map-tip-slide` with
`--pf-map-tip-slide-ease` (no overshoot) and closes with a plain fade.
`--pf-map-tip-ease` is still the "here" arrow's bob.

Room-name shadow: a soft glow in `--pf-map-label-halo` (the map surface
colour) sits behind each room name, clear until hover. It is three stacked
`text-shadow`s at 1x, 2.5x and 5x `--pf-map-label-halo-blur` (12 map units,
so about 3, 7 and 15px at 1000px wide), not the old hard `stroke` +
`paint-order` outline, so it knocks the outlines and hatch back around the
letters without a visible edge. The shadow list never changes; hover only
swaps its colour (`--pf-map-label-glow`, `transparent` at rest), so it
fades. It comes in over `--pf-map-label-halo-in` (60ms), before the name has
moved, so the lifting name stays readable over the lines it passes; on the
way out it waits for the name to land (`--pf-map-out`) and fades over
`--pf-map-label-halo-out`. Softer or tighter: change
`--pf-map-label-halo-blur` only.

Appointment rooms: `building-map.js` gives rooms with `access: appointment`
a broken hatch, the walk-in lines cut into 20-unit dashes and staggered row
to row, so "by request" reads from the texture and not only from a dimmer
grey (the old .13 vs .3 opacity was easy to miss on a dim screen). It
clones the drawing's hatch mask (same period and room cuts) with a dashed
pattern, so drawings need no changes, and adds `.has-broken-hatch` to the
map: appointment opacity becomes .42 (hover .7) and the legend and tooltip
swatches are masked into the same dashes. Without JS, or with
`SETTINGS.appointmentHatch: 'lines'`, rooms keep the plain hatch at the old
opacity.

### Full-page backgrounds and frosted glass

Section background images (`field_section_bg`) and bento background videos
run past their section to fill the page: left under the "On this page"
sidebar (lg and up) and, for the first section, up under the top bar. The top
bar and the sidebar are frosted glass (translucent + `backdrop-filter`), so
the background shows through, blurred. Only the decorative layer moves;
section content and solid section colours stay in the main column.

- Tune the glass in `scss/_variables.scss`: `$pf-glass-blur`,
  `$pf-glass-saturate`, `$pf-header-bg-alpha`. The sidebar wash is
  `sidebar-bg` in `scss/components/_modular-tokens.scss`.
- `js/palefire.js` (`palefireBleedOffset`) measures how far the first
  background has to reach up, so a breadcrumb, title or tabs above the
  sections are covered too. Those stay on top of the image.
- Users with `prefers-reduced-transparency` or `prefers-contrast: more` get
  a solid top bar and sidebar. Browsers without `backdrop-filter` get a
  nearly opaque top bar.

### Modular page head: breadcrumb and title

`page.html.twig` prints a `<header class="pf-modular__head">` at the top of
`<main>` on Modular pages, directly above the first section and over its
background bleed. Styles: `.pf-modular__head` in `scss/layout/_layout.scss`.

- Breadcrumb: the same `breadcrumb` region and System Breadcrumb block as
  every other page (no block layout change). Styled like the TOC
  (`.pf-breadcrumb--modular`): monospace, uppercase and tracked, muted links
  with a gold underline, slanted dividers, `--pf-toc-*` colours so it follows
  light/dark sections. The current page is the last item, in the active
  colour, cut to one line with an ellipsis if it runs out of room; the
  `<h1>` right below repeats it in full. When the bento with a background
  video is the first section, the breadcrumb leaves room on the right for
  the video button. Other pages keep the Bootstrap breadcrumb above the body.
- Title: the node's title as the page's `<h1>`. A placed Page title block is
  emptied and hidden on Modular pages (`PalefireHooks::preprocessBlock()`),
  so there is never a second `<h1>`.
- Snapping: the head has no snap point of its own. The first section's
  `scroll-margin-top` is `--pf-bleed-block-start`, which moves its snap point
  (and its `#anchor`) to the top of the page, so the head and the first
  section snap as one screen. Peek and screen heights on the first section
  subtract the head's height (`--pf-above-sections`), so that screen is still
  one screen tall.
- Alignment: the breadcrumb, the TOC title, the TOC hide/show button and the
  background-video button share one line. The TOC title and the breadcrumb
  both start 2rem down with the same type; `modular-toc.js` measures them at
  the top of the page and nudges the title and the button by any difference
  (`--pf-toc-title-nudge`). `bento-video.js` centres the video button on the
  breadcrumb (`--pf-bento-toggle-top`) when the bento is the first section.

- Title weight: the `<h1>` uses `$pf-display-weight` (600, in
  `scss/_variables.scss`) with -0.02em tracking, a 1.1 line-height and
  grayscale font smoothing. At 800 the system font (Segoe UI Variable on
  Windows) drew close to Black and looked too heavy. The map tooltip's room
  name uses the same variable, and its small caps labels are 600.
- First load: the first section's `scroll-margin-top` is
  `--pf-bleed-block-start` plus `100vh`. Browsers snap on the first layout,
  before `palefireBleedOffset` runs, and the old fallback (header height
  only) put the snap point below the top, so the page loaded part-way
  down. The extra margin past the top is clamped, so the snap point is the
  top of the page with or without JS.
- Wide screens: when the first section is a bento, the head's inline
  padding is `max(--pf-section-pad-inline, (100% - $pf-content-max) / 2)`.
  The bento's search form and map are capped at `$pf-content-max` (1280px)
  and centred, so once the main column is wider than the cap plus padding
  (TOC collapsed on a laptop, or any large monitor) their left edge moved
  in while the breadcrumb and `<h1>` stayed at the padding. Now all three
  share one edge at every width. Other first sections keep the plain
  section padding.

### TOC title

The TOC's visible title is the page's title, not the block's "On this page"
heading (`templates/navigation/palefire-modular-toc.html.twig`,
`PalefireHooks::preprocessPalefireModularToc()`). It is a plain paragraph,
since the `<h1>` already says it, set to match the breadcrumb (two lines at
most). The block's heading setting still names the `<nav>` for screen
readers, and shows as before if there is no page title. No block config
change is needed, and palefire_modular needs no change: the module only
supplies the block and the default template, and this theme template
overrides it.

Keep exactly one `palefire-modular-toc.html.twig` in the theme
(`templates/navigation/`). A stray copy of the module's default template
sat in the theme root and won the theme registry, so the page title, the
Back to top arrow and the attached library never rendered; it has been
deleted. If the title still shows "On this page" after `drush cr`, look
for another copy: `find . -name 'palefire-modular-toc.html.twig'`.

The hide/show button is a 4px-rounded square. Sub-links are cut to one line
with an ellipsis (`white-space: nowrap`), not `-webkit-line-clamp`: a clamped
box clips at its padding edge, so the top of the second line showed in the
bottom padding. The full text is the link's tooltip where it is cut.

Sideways scrolling: `.pf-toc__sublink` is `position: relative`, so the
visually-hidden event detail that `modular-toc.js` adds to calendar
sub-links (`data-toc-detail`) is clipped inside its link. Before, that
absolutely positioned span escaped the link's `overflow: hidden`, sat at
the end of the full label and gave the sidebar a horizontal scrollbar. The
sublink is also capped at `100%` of the rail, and the sidebar sets
`overflow-x: hidden` as a backstop.

### Background-video button

The bento's pause/play control sits in the section's top corner (on the
breadcrumb's line when the bento is the first section)
(`paragraph--bento-search.html.twig`, `_bento-search.scss`). The button is
a sibling of `.pf-bento__media`, not inside it, and is positioned against
`.pf-bento`: since the media layer became `position: fixed` (with negative
insets that reach under the top bar and sidebar), a button inside it was
measured from a box running past the viewport by the section padding, and
`right: 1rem` put it off screen. `bento-video.js` finds the button from the
bento box, sets a negative `--pf-bento-toggle-top` to reach the breadcrumb
line, and watches the bento (not the always-in-view fixed layer) to pause
the clip when it scrolls away. It is styled
like the TOC hide/show button in its floating look: 2.25rem tall, 4px
corners, a 1px `--pf-toc-rail` border, the sidebar's glass background and
`--pf-toc-color` text that turns `--pf-toc-active` on hover. The background
and border don't change on hover. The pause/play glyph cross-fades, a 2px
gold bar along the bottom edge fills as the current clip plays
(`--pf-bento-progress`, set by `bento-video.js`), and the label ("Pause
background video" / "Play background video", monospace, uppercase and
tracked like the breadcrumb) slides out on hover or keyboard focus with
space on both sides. Screen readers always get the label. Reduced motion
drops the transitions; forced colours get a system button.

### Page title (h1) in the bento

When a Modular page's first section is a bento search, the bento prints the
page `<h1>` (the node title) right above its intro text, in the bento head's
left column; `page.html.twig` then leaves its own `<h1>` out of
`.pf-modular__head`, which keeps only the breadcrumb. On every other Modular
page the `<h1>` stays under the breadcrumb. Paragraph templates don't get a
`node` variable, so the title comes from the host node in
`palefire_preprocess_paragraph__bento_search()` (`page_title`), and
`PalefireHooks::titleBentoUuid()` decides which bento (if any) owns it; both
the page and the paragraph use that one check, so there is always exactly
one `<h1>`. Styles: `.pf-modular__title` (`_layout.scss`) plus
`.pf-bento__title` spacing (`_visit.scss`).

### Bento search form

`paragraph--bento-search.html.twig` prints a plain GET form that sends the
search to the library's Bento results page: `https://lib.lsu.edu/bento`
with the words in `search_api_fulltext_bento` (palefire_modular's
`bento.identifier`, the same query Origami's search box sends). Results are
not shown on this page and there is no JavaScript. To change the
destination, edit `bento_action` at the top of the template. The result
tiles palefire_modular builds (`bento.tiles`) are no longer printed.

Styles (`_bento-search.scss`): one 4px-cornered box with a 1px border on
the sidebar's glass, a thin search glyph, the input, and a "Search" button
in the breadcrumb/TOC voice (monospace, uppercase, tracked) behind a
hairline divider. Muted at rest, the active colour on hover, a gold border
and ring while the field has focus. Colours come from the `--pf-toc-*` and
`--pf-section-*` tokens, so it follows light and dark sections; the border
is at least 3:1 on both. On phones the button shows only its arrow
("Search" stays for screen readers).

### Bento head and Visit card (Special Collections, Option B)

A Modular page can give its bento search a page intro above the search
form and a **Visit** strip under the building map, with today's status
("Open now", "Closes soon", "Opens tomorrow 9AM"), this week's hours, an
"Open in Google Maps" link and, if asked for, a mini map. The bento is one
1280px column: the lead panel (title, intro, search), the map, then the
Visit strip (see "Bento layout: lead panel, Visit strip" below).

Fields (all optional, on `node.modular_page`, added by
`scripts/palefire_visit_setup.php`):

| Field | Type | Used for |
| --- | --- | --- |
| `field_intro` | Text (formatted, long) | Intro paragraph above the search |
| `field_hours_location` | List (text): LibCal location ID | Status line and week hours |
| `field_location_name` | Text (plain) | Card heading, e.g. "Hill Memorial Library" |
| `field_location_query` | Text (plain) | Google Maps link and embed (address or place name) |
| `field_location_display` | List: `link` (default) / `embed` | Link only, or link + mini map |

The fields are read by `_palefire_bento_visit()` in `palefire.theme`
(walking up from the bento paragraph to its node) and printed by
`paragraph--bento-search.html.twig`; hide them in the node's view display
so they don't also print below the sections. The page's cache tags are
added to the bento, so edits show after save.

- **Hours** (`js/visit-hours.js`): Origami's `bento.js` LibCal logic without
  jQuery. One JSON-LD request per location to
  `lsu.libcal.com/widget/hours/grid?format=jsonld&lid=…` (JSONP), the same
  rules (30-minute "soon" windows, next open day up to 14 days out), and a
  re-check every minute. If LibCal does not answer, the status line hides
  and the "All library hours" link stays.
- **Map** (`js/visit-map.js`): with `embed`, a "Show map" button stands in
  for the Google Maps iframe until it is clicked, so the page makes no
  request to Google by default. The link works without JavaScript.
- **LibCal locations**: the list offers `241` and `236`, the two IDs
  `bento.js` uses. Relabel them (or add more) at Structure > Content types >
  Modular page > Manage fields > Hours, or edit `$libcal_locations` in the
  Visit setup script before running it (keys are IDs, values are labels).

Stacking: `.pf-bento__media` is `position: fixed` (clipped to the bento,
see below), so it paints over any bento child that isn't positioned. `_visit.scss` gives every other direct
child of `.pf-bento.has-bg-video` `position: relative`; without it the
intro and the "Search LSU Libraries" heading sat under the video overlay.
The "This week" `<details>` drops the starterkit details box, and the
external-link arrow is an inline SVG (the `↗` character drew as an emoji
on Windows).

#### Bento layout: lead panel, Visit strip, video (2026-10-16)

- **Video stays in the bento.** `.pf-bento__media` is `position: fixed`,
  which `overflow` can't clip, so the video showed through every
  transparent section after it (Events). `.pf-bento.has-bg-video` now has a
  `clip-path` the size of the video's reach (section edges plus the top and
  side bleed), which does clip fixed children: the video holds still while
  the bento scrolls past, then scrolls away with it.
- **More colour.** The video's dark scrim is 30% (was 55%). Meant for a
  bento section with the **Dark** theme (Special Collections: switch its
  bento section from "Light, dark at the map" to Dark in the UI). The map still
  dims and blurs the video when it comes into view.
- **Lead panel.** The `<h1>`, intro and search sit on one frosted panel
  (`.pf-bento__lead`), the Visit card's glass (`--pf-sidebar-bg` +
  `--pf-glass-filter`), so the text reads over the brighter video. In dark
  sections the field's muted text uses `--pf-section-muted` (#ababab): over
  a bright frame the chrome grey (#929292) fell just under 4.5:1. Every
  bento gets the panel, with or without an intro.
- **Visit strip.** The Visit card moved from beside the search to under the
  map, as wide as legend + map. From md up it is one row: name and
  directions | hours | mini map; below md it stacks as before. The
  `.pf-bento__head` wrapper and `.has-head` class are gone.
- **TOC hide slides.** The lg sidebar used to fade its links out while the
  column closed. It now slides out whole, like Bootstrap's
  `.offcanvas-start` (`translateX(-100%)`, .3s ease-in-out), with the column
  closing in step; the toggle rides along at its edge.

Production: theme files only. Switch the bento's section theme to Dark if
it isn't yet, then `git pull` and `drush cr`.

#### "Light, dark at the map" section theme

The Visit setup script also adds `light-map` to palefire_modular's
`field_section_theme` list. A section with that theme starts light; when
its bento's building map comes into view, `building-map.js` (the same
check that dims the background video) adds `.is-map-dark` and
`data-bs-theme="dark"` to the section and sends `palefire:mapview`.
`modular-toc.js` listens and switches the header and sidebar to dark,
then back to light when the map leaves. In SCSS,
`.pf-section--theme-light-map.is-map-dark` `@extend`s
`.pf-section--theme-dark` (`_modular.scss`), so every dark-section rule
applies; the colours ease in over .45s (none with reduced motion).
In the light state the video gets a white wash (`rgb(255 255 255 / .74)`)
instead of the dark overlay, so the light tokens' dark text reads on it.
palefire_modular's own list only has `light` and `dark`: pick "Light, dark
at the map" on the bento's section, or the section stays dark throughout.

#### Turning it on (production)

```sh
git pull
drush php:script themes/custom/palefire/scripts/palefire_visit_setup.php
drush cr
```

Use `palefire_visit_setup.php`, not `palefire_modular_setup.php`: the
older script builds its own section paragraph types and only belongs on
sites without palefire_modular. Its list fields also failed with
`The configuration property settings.allowed_values.0.label.0 doesn't
exist`, because it handed `FieldStorageConfig::create()` the YAML shape
(`[['value' => …, 'label' => …]]`) instead of `[value => label]`; the
helper now converts it, and the script exits early when palefire_modular
is enabled.

If that older script was already run on a palefire_modular site, clear
what it left (unused types `section_text` and `section_media`, storages
`paragraph.field_show_in_toc` and `paragraph.field_full_page`; anything in
use is kept and reported):

```sh
drush php:script themes/custom/palefire/scripts/palefire_modular_setup_undo.php
```

Then edit the page: fill in the Visit fields, set the bento's section theme
to "Light, dark at the map", and save. Staging gets the config from
production downstream as usual.

### Background video playlists, "This week" and the light-map check

- **No black between clips.** With two or more videos, `js/bento-video.js`
  keeps two `<video>` layers. While one plays, the other has already loaded
  the next clip. About a second before the clip ends, the next one starts on
  top and fades in over it (`--pf-bento-fade` in `_bento-search.scss`,
  `FADE_MS` in the script; keep them equal). The new layer only shows once it
  is really playing, so on a slow connection the last frame of the old clip
  stays up instead of black. One video still uses the native `loop`. The
  pause button, the progress line and the off-screen pause cover both layers.
  With reduced motion the swap is instant.
- **"This week" open on lg.** `js/visit-hours.js` opens the hours list at
  992px and up (the Visit strip's hours column) and closes it below. It only
  changes when the window crosses that width, so visitors can still open or
  close it themselves.
- **Checking the section theme.** If a light-map page is dark from the top,
  the section is not actually saved as "Light, dark at the map". Run, from
  the theme folder on production:

  ```sh
  drush php:script scripts/palefire_section_theme_check.php /special
  ```

  It lists the theme options (light-map must be there; if not, run
  `scripts/palefire_visit_setup.php`) and each section's saved theme on the
  published revision, plus the latest draft if there is an unpublished one.
  It changes nothing.

### Calendar events in "On this page"

`modular-toc.js` lists any element with `data-toc` under its section, even
inside a `data-toc-skip` area, with optional `data-toc-label` (link text),
`data-toc-detail` (extra screen-reader text) and `data-toc-passive` (listed
but not highlighted by scroll position). A component that renders after
the TOC dispatches a bubbling `palefire:tocrefresh` event from inside its
section, and that section's sub-list is rebuilt and opens like a section
change. The LibCal day carousel uses this for its next three timed events.

## Header (Origami look)

Top bar, left to right: logo | Primary menu | Utility menu (icons). Sizes
follow Origami's `.mainMenu` (`$pf-header-bar-h` etc. in
`scss/_variables.scss`):

| | Expanded | Compact (scrolled) |
| --- | --- | --- |
| Bar height | 87px (lg+), 4rem below lg | 49px |
| Logo width | 150px (lg+), 120px, 88px on phones | 120px |
| Primary menu | 13px, bold, uppercase, .15em (~2px) tracking, `#434343`, 30px apart | same |
| Utility icons | 20px in 44px cells, 70px wide from 1600px | 36px tall (70px wide from 1600px) |

- **Type**: Roboto is the body and menu font; Handjet is the Hours drawer
  clock. Both come from Google Fonts (`html.html.twig`): preconnect, then a
  non-blocking stylesheet, so they don't delay the first paint. The browser
  downloads a font file only when text uses it, so Handjet costs nothing
  until the Hours drawer opens. Until Roboto arrives the page uses
  "Roboto Fallback" (local Arial resized to Roboto's metrics,
  `scss/base/_fonts.scss`), so the swap doesn't move the layout (CLS).
- **Logo**: the inline LSU Libraries wordmark
  (`templates/includes/logo-lsu-libraries.svg.twig`), filled with
  `currentColor`: grey at rest and LSU purple on hover in the light bar;
  white and LSU gold on hover in the dark bar (`--pf-brand-color` /
  `--pf-brand-hover-color`, `_header.scss` and `_chrome-dark.scss`).
  `page.html.twig` prints it itself when the Header region is empty, so no
  block placement is needed. A Site branding block placed in Header still
  wins, and a custom logo uploaded in the theme settings stays an `<img>`.
- **Primary menu**: uppercase, tracked, bold, with a small faded caret that
  turns while open. Dropdowns (lg and up) are a plain sheet under the item,
  left edge on the item's text: uppercase tracked links, generous padding,
  no item background on hover; the hover/focus is a colour change (purple /
  gold on the dark bar) plus an underline.
- **Utility menu**: solid Bootstrap Icons (`*-fill`, copied by
  `build/copy-assets.js`; falls back to the outline icon), colour-only
  hover. Each icon's name shows as a tooltip on hover and keyboard focus
  (the link's own text, so screen readers hear it once; Escape hides it,
  WCAG 1.4.13). Icons with a panel open it instead of following the link.
  Ask Us is Font Awesome's duotone `comments-alt` SVG instead (see below).
- **Phones** (Origami never had mobile styles, so this is Palefire's own):
  logo, five icons and the menu button share one row down to about 360px;
  icon cells are 40x44px there. Drawers are full width.

### Ask Us icon: Font Awesome duotone

The Ask Us utility icon is now Font Awesome 5 Pro's duotone `comments-alt`
(`fad fa-comments-alt`), as intended, instead of Bootstrap Icons'
`chat-dots-fill`. It is printed as an inline SVG, like the other utility
icons: no FA stylesheet or webfont, so nothing render-blocking and no late
icon. `fa_map` in `menu--utility.html.twig` maps the icon name to a file
under `/libraries/fontawesome-pro/svgs/`; the name still picks the Ask
panel. If the file is missing, the Bootstrap Icon is used. `.pf-icon svg`
now sets `fill: currentColor` (FA SVGs have no fill of their own), so the
icon follows the hover colours; the duotone layer keeps its 40% opacity.

Font Awesome Pro is licensed, so it is not in this public repo: it lives in
the site's `/libraries/fontawesome-pro` (Drupal root), including `svgs/`.
The `palefire/fontawesome` library pointed at the theme folder
(`libraries/...`, which doesn't exist); its paths now start with `/`. It is
still not attached anywhere.

Production: make sure `/libraries/fontawesome-pro/svgs/duotone/comments-alt.svg`
exists (and on libstage), then `git pull` and `drush cr`. No config.

### Utility panels (offcanvas)

`templates/includes/utility-panels.html.twig`, printed once by
`page.html.twig` outside the header (its `backdrop-filter` would clip fixed
drawers) and outside `#swup` (kept between Swup visits). Each panel is a
Bootstrap 5.3 offcanvas from the left in the dark TOC sidebar's look: the
`$pf-chrome-dark` / `$pf-section-dark` tokens, sidebar glass, monospace,
gold accent and Origami's plain × close button (`_utility-panels.scss`).

| Icon | Panel | Contents |
| --- | --- | --- |
| `search` | Search | The bento search form; focus moves to the box. |
| `clock` | Hours | Today's date, a Handjet clock (ticks on the minute, only while open) and an OPEN / CLOSED row per LibCal location (`hours_locations`). LibCal is asked the first time the panel opens, through `Drupal.palefireHours` (`visit-hours.js`). "See full hours" goes to the Hours menu link's own URL. |
| `chat-dots` | Ask Us | Origami's three LibAnswers widgets as tabs (Live Chat, Email Main Library, Email Special Collections; `ask_tabs`). Each iframe gets its `src` the first time its tab shows, so nothing loads from LibAnswers until the drawer opens. |
| `person-circle` | Accounts | Origami's six account links (`accounts`: label, URL, Bootstrap Icon) and "Account descriptions" (`/accounts`). |
| `gear` | Settings | Reading and display preferences (below). |

- The menu link stays a real `<a href>` (works without JavaScript).
  Choose a panel with a `data-panel` link attribute, or by icon
  (`panel_map` in `menu--utility.html.twig`, outline and `-fill` names).
  To add a panel: markup in `utility-panels.html.twig`, key in `panels`.
- **Dimming**: Bootstrap's backdrop is a fixed .5. While a utility panel is
  open (`<html data-pf-panel>`, `utility-panels.js`) it is
  `$pf-panel-backdrop-light` (.72) on light pages and
  `$pf-panel-backdrop-dark` (.55) on dark ones. The mobile "On this page"
  offcanvas keeps the default.
- Accessibility: Bootstrap's dialog role, focus trap, Escape, backdrop click
  and focus return; Ask Us tabs are a WAI-ARIA tablist (arrow keys) and each
  iframe has a title; the clock is not a live region; reduced transparency /
  more contrast give a solid drawer; forced colours drop the stripes.

### Settings drawer

Each change applies and saves as soon as it's made, in this browser only
(`localStorage` key `pf-a11y`), and a status line tells screen-reader users
"Saved on this device." "Reset to default" clears it. Another open tab
follows along (`storage` event).

| Group | Setting | `<html>` | What it does |
| --- | --- | --- | --- |
| Text size | 100 / 115 / 130 / 150% | `data-pf-text-size` | Root font size, so the whole rem-based layout scales, not just the text |
| Reading | Easy-to-read font | `.pf-a11y-font` | Atkinson Hyperlegible Next (Google Fonts); only downloaded when someone turns it on |
| Reading | More text spacing | `.pf-a11y-spacing` | The WCAG 1.4.12 test values: line 1.5, paragraph 2em, letter .12em, word .16em |
| Reading | Underline all links | `.pf-a11y-underline` | Includes menus, dropdowns and link-buttons |
| Display | High contrast | `.pf-a11y-contrast` | Solid backgrounds, full-strength text, brighter borders, heavier video wash, no stripes |
| Display | Reduce transparency | `.pf-a11y-solid` | No see-through glass on the header, sidebar and drawers |
| Display | Strong focus outline | `.pf-a11y-focus` | 3px gold ring around a 3px black one |
| Motion | Reduce motion | `.pf-a11y-motion` | Stops animations, page transitions (Swup), the map trace and smooth scrolling |
| Motion | Pause background videos | `.pf-a11y-pausevideo` | Bento background videos start paused (and pause at once when switched on) |

- **No flash**: the inline script in `html.html.twig` applies the saved
  copy to `<html>` before the first paint, on every page load.
- Styles: `scss/base/_a11y-settings.scss` (imported last). "High contrast"
  and "Reduce transparency" reuse the OS-preference rules through the
  `pf-solid-chrome` mixin (`scss/base/_mixins.scss`): one set of rules for
  `prefers-reduced-transparency` / `prefers-contrast` and for the classes.
- JavaScript reads the classes too: `bento-video.js`, `building-map.js`
  and `palefire-swup.js` treat `.pf-a11y-motion` like the OS setting, and
  `utility-panels.js` sends `palefire:a11ychange` when a setting changes.
- To add a setting: an item in `settings_groups`
  (`utility-panels.html.twig`), its key in `SWITCHES` (`utility-panels.js`)
  and in the inline script's list (`html.html.twig`), and its CSS.

### Light-map sections: no dark flash on load

A page whose first section is "Light, dark at the map" used to flash dark
for a frame on load, then turn light. `building-map.js`'s first
IntersectionObserver report comes before fonts, the "This week" list
(opened on lg by `visit-hours.js`) and the scroll snap settle, so it could
call the map "in view" and switch the section, header and sidebar dark.
The map-dark switch now waits until the visitor has scrolled (8px), or
applies at once when the page opens already scrolled (an anchor or a
restored position). A page opened at the top always loads light.

### Production

Theme files only (templates, compiled CSS, JS, icons): no config, no
update hooks.

```sh
git pull
drush cr
```

## Page transitions (Swup 4)

`js/palefire-swup.js` swaps `#swup` (breadcrumb + body) and `#pf-primary-menu`
for anonymous visitors, re-running Drupal behaviors and syncing drupalSettings,
meta tags, and body classes. Pages that need new assets or contain inline
scripts fall back to a full load. Opt out per link with `data-no-swup`.
