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
- `scripts/palefire_guide_setup.php` – Guide pages content model: Resource content type, guide paragraph types, guide fields on Modular page (sites with palefire_modular)
- `scripts/palefire_tile_showcase_setup.php` – Tile showcase paragraph type (How-to guides, Collections, Specializations), its image styles and responsive image styles (sites with palefire_modular)
- `scripts/palefire_guide_demo.php` – builds the Systematic Reviews LibGuide as an unpublished guide page, as a demo
- `scripts/palefire_guide_special_collections.php` – builds the "How to Use Special Collections" guide as an unpublished guide page
- `scripts/palefire_guide_wysiwyg.php` – moves every guide page's text to the WYSIWYG text format

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

The TOC's visible title is **"Table of contents"** on every page
(`templates/navigation/palefire-modular-toc.html.twig`; 2026-10-16, was the
page's title, which the `<h1>` already says). It is a plain paragraph in the
label style that lines up with the breadcrumb, not a heading, because the
sidebar comes before the `<h1>`; it names the `<nav>` (`aria-labelledby`).
The block's own heading setting is no longer shown. No block config change
is needed, and palefire_modular needs no change: the module only supplies
the block and the default template, and this theme template overrides it.

Below lg the floating TOC button is a 48px square with the list icon (the
lg hidden-TOC icon) instead of the words "Table of contents"; the words stay
for screen readers (visually hidden) and as the tooltip.

Keep exactly one `palefire-modular-toc.html.twig` in the theme
(`templates/navigation/`). A stray copy of the module's default template
sat in the theme root and won the theme registry, so the page title, the
Back to top arrow and the attached library never rendered; it has been
deleted. If the title still shows "On this page" after `drush cr`, look
for another copy: `find . -name 'palefire-modular-toc.html.twig'`.

The gold bar covers only the active link (2026-10-16): the current h3
sub-link while you are reading under one, otherwise the section link. It
used to stretch over the section link and its whole open sub-list, which
on guide pages with many h3s made a bar taller than the screen. It slides
from the section link to each h3 as you scroll and keeps pace with a
sub-list that is still opening (`placeIndicator()` in `js/modular-toc.js`).
`aria-current` is unchanged, so this is visual only.

The hide/show button is a 4px-rounded square. Sub-links (h3s) wrap to two
lines and are clamped after that (2026-10-16; they used to be one line with
an ellipsis). `modular-toc.js` puts the label in its own
`<span class="pf-toc__sublabel">`, and the clamp (`-webkit-line-clamp: 2`)
goes on that span, not the link: a clamped box clips at its padding edge, so
clamping the padded link let the top of the next line show in its bottom
padding. The link keeps its padding for the tap target, and the
visually-hidden `data-toc-detail` text stays outside the clamp, so screen
readers still get the full label and detail. The full text is the link's
tooltip where it is cut. To change the line count, edit
`.pf-toc__sublabel` in `scss/components/_toc.scss`.

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

**Light bento sections over video** (Light, or "Light, dark at the map"
before the map; follow-up, same day). The 74% white wash made the video a
grey-white haze. Now:

- The wash is 40%, so the video's colour shows, plus a band behind the top
  bar and breadcrumb line that takes it to 85% there and fades out above
  the lead panel (the breadcrumb has no glass of its own).
- On pages with a bento video, the light glass (TOC sidebar, lead panel,
  Visit strip: `--pf-sidebar-bg`) is 80% instead of 50%, and the muted grey
  (`--pf-toc-color`) is #595959 instead of #686868. Worst case, a black frame
  under the glass, the TOC, breadcrumb and search field text stay above 4.5:1.
- Dark sections, the map's dark overlay and the Settings drawer's solid /
  high-contrast switches are unchanged (the override is
  `:root:where(:has(...))`, so those rules still win).

**Lighter still** (later follow-up, /v2/mapsandevents): the video was still
too white and the chrome too opaque.

- The white band behind the top bar and breadcrumb is gone, and the wash is
  20% (was 40%).
- Light glass on bento-video pages (`--pf-sidebar-bg`: TOC sidebar, lead
  panel, Visit strip, breadcrumb) is 62% (was 80%), with the muted grey at
  #404040 (was #595959): worst case about 4.8:1.
- Header: 45% glass (was 60%) with black nav, utility icons and logo (was
  #434343): worst case about 6.4:1. The strip under the compact header
  matches. Reduce transparency / High contrast still make it solid.
- Breadcrumb: without the band it sat straight on the video, so on a light
  hero it gets its own strip of the sidebar glass (`_bento-swiss.scss`),
  its text still on the column edge.

#### Map-only bento (2026-10-16)

A bento can drop its lead panel (title, intro, search) and open on the
building map. Add the field once in the UI (config is never exported from
this repo):

1. Structure > Paragraph types > **Bento search** > Manage fields > Create a
   new field: **Boolean**, label "Hide search panel (map only)", machine
   name **`field_bento_hide_lead`**. Default unchecked.
2. Manage form display: show it (a single on/off checkbox). Leave it out of
   Manage display: the bento template prints only what it needs.
3. Edit the page, tick the box on its bento, save. `drush cr` once after
   deploying this change.

What the theme does (`palefire_preprocess_paragraph__bento_search()` sets
`map_only`; the template adds `.pf-bento--map-only`):

* The lead panel isn't printed. If the bento is the first section, the page
  `<h1>` is still printed, visually hidden, so the page keeps its heading.
* Only with a map: a bento without one ignores the box, so it can't render
  empty.
* The map starts on its dark backdrop (the in-view overlay and blur), is
  never held back on light sections, and `building-map.js` arms the
  "Light, dark at the map" switch at once instead of after the first scroll.
* The Visit strip is unchanged: it comes from the page's Visit fields, so
  leave those empty for the map alone.
* Without the field the box simply doesn't exist and every bento stays as
  it is.

#### Hero: Swiss layer, TOC default, map replay (2026-10-16)

- **Swiss layer** (`scss/components/_bento-swiss.scss`, after
  `_visit.scss`): the bento hero in the events carousel's language. Square
  lead panel, Visit strip and mini map; display title (up to 5.5rem, medium,
  -0.035em); a hairline over the search; the search heading, Visit labels,
  "This week" and the Visit links as small tracked monospace labels; links
  with an accent underline; larger map heading. Accent: LSU purple on light
  sections (it is also the field's focus ring there: gold on white was
  1.4:1), gold on dark.
- **Map on light sections:** the map is drawn for a dark backdrop, which it
  gets once it is in view. Until then it showed under the lead panel as
  white on a pale wash. It is now held back (opacity) until in view;
  `building-map.js` adds `.has-map-view` to the bento, so without JS the
  map always shows.
- **TOC hidden by default on hero pages.** `PalefireHooks::preprocessHtml()`
  puts `data-pf-toc-default="hidden"` on `<html>` when the first section is
  a bento; `modular-toc-collapse.js` (in `<head>`) starts the TOC hidden
  from it, before the first paint. A saved choice wins (now stored as `1`
  hidden / `0` shown; no key means the page default). Guide pages keep their
  own saved choice (`palefire.tocCollapsed.guide`, 2026-10-16), so a TOC
  hidden on a hero page no longer hides it on every guide; guides open with
  it shown. Swup visits apply the new page's saved choice or default
  (`data-pf-mode`, synced by `palefire-swup.js`), without the slide
  (`html.pf-toc-instant`). It never opens by itself on scroll: that would
  reflow the content under the reader and count as layout shift.
- **Map draw:** the trace takes 1.8s (was 3.2s), rooms follow faster
  (45ms steps, 0.45s fades). It redraws every time it comes into view: it
  resets once fully off screen (not before, so it never vanishes while
  partly visible). Follow-up: the first version waited 5s off screen
  before resetting; dropped, redrawing each visit is fine.
  `SETTINGS.duration`, `stagger` and `reveal*` in `building-map.js`; the
  reveal timings are mirrored in `_building-map.scss`.
- **No snap point at the map.** Tested in Chromium with wheel input: with
  the section as one snap area the browser never stops at the map, and
  with the blocks as snap points instead, every wheel step snaps back to
  the top (the map is a screen away), so the page traps mouse users.

Production: `git pull`, then `drush cr` (registers the new
`preprocess_html` hook).

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

### Fix: guide pages crashed after the request-paths change (2026-10-20)

`palefire_preprocess_paragraph__guide_text()` passed an expression
(`... ?? []`) to `Element::children()`, which takes its argument by
reference, so every guide page with a text block failed with "could not be
passed by reference". It now returns early when the block has no body and
passes the variable itself.

File: `palefire.theme`.

### Special Collections guide: request paths as subway lines (2026-10-20)

The tree at the top of the Special Collections guide was hard to scan, so
it's now a diagram: two "subway lines", See it in person and Get a digital
copy, each four stops with a Font Awesome Pro duotone icon, a short label
and a note (Request Item, then box number, book a visit, read it at Hill;
or duplication form, invoice, copies sent). Four across from 34rem of
block width, a vertical line below that (phones). As a line scrolls into
view its stops fade up in turn and the line draws between them
(scroll-driven CSS, no script; skipped under reduced motion and in
browsers without `animation-timeline`). Each line is an `<ol>` named by
its heading; icons are `aria-hidden`.

To place it, a guide text block has a paragraph that is only
`[request-paths]`. `palefire_preprocess_paragraph__guide_text()` takes the
marker out and the block renders `includes/guide-request-paths.html.twig`
after its text. The labels live in that include, not in the editor. The
nested-list tree style stays for other guides.

The page on production (unpublished) needs the change too: edit the "Two
ways to use an item" block, replace the bulleted tree with a paragraph
that says `[request-paths]`, and save. Or delete it and re-run the script.

Files: `palefire.theme`, `templates/paragraphs/paragraph--guide-text.html.twig`,
`templates/includes/guide-request-paths.html.twig` (new),
`scss/components/_guide.scss`, `css/style.css`,
`scripts/palefire_guide_special_collections.php`.

### Special Collections guide: decision tree first (2026-10-20)

"Requesting and visiting" is now the first section of the How to Use
Special Collections guide, and it opens with a decision tree: find the
item in the catalog and choose "Request Item", then either see it in the
Reading Room (request, book an appointment, visit) or order a digital copy
(the Duplication Order Form, an emailed invoice, copies sent to you). Each
option then has its own numbered steps. The duplication steps come from
lib.lsu.edu/visitsc. "Start here" became "What Special Collections is",
after it, and Ask Us now says copies are ordered through "Request Item"
too.

The tree is a plain nested bulleted list, so editors can change it in
WYSIWYG: in guide text, any bulleted list with a list inside it is drawn
as a tree, in a framed box with hairline branches (`_guide.scss`). No
existing guide has nested lists, so nothing else changes.

The script creates a new page; it doesn't update one. To rebuild the
unpublished page made earlier, delete it (node/132607), then re-run the
script.

Files: `scripts/palefire_guide_special_collections.php`,
`scss/components/_guide.scss`, `css/style.css`.

### Hero account card links to Aeon sign-in (2026-10-20)

The landing hero's "Your account" card (`.pf-hero-account`) was a
placeholder with no link. It now links to the Special Collections sign-in,
`https://specialcollections.lib.lsu.edu/RemoteAuth/aeon.dll?`, and its icon
is the Font Awesome Pro duotone sign-in (`fad fa-sign-in`). Like the
theme's other FA icons, it's inlined from the server's
`libraries/fontawesome-pro/svgs/duotone/sign-in.svg` rather than an `<i>`
tag, since no page loads the FA CSS or webfont. The icon stays
`aria-hidden`; the card's text names the link.

File: `templates/includes/landing.html.twig` (and a comment in
`scss/components/_landing-features.scss`; no CSS rebuild needed).

### Hero bottom row becomes a plinth under the tiles (2026-10-20)

The row under the hero tiles (Next event | Latest blog post | Your
account) looked too small next to them, mostly because every field was cut
to one line. Where the cards sit three across (a 50rem panel and up), the
row now sits flush under the tiles as one framed strip: no gap, the tiles'
bottom edge is its top line, and each card is a cell with the same soft
hairline between, under its tile. The whole cell is the link and tints
faintly on hover and focus. On every width, dates and titles now wrap to two
lines before they're clamped, and the thumbnails are square so they fill
the taller card. The two-across and stacked layouts are otherwise unchanged.

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: blog card and sign-in share the row under the tiles (2026-10-20)

- **Blog post** is a card again: the tiles' soft hairline around it, with
  padding, a smaller picture (96–152px) and the title one size down
  (15–17px, regular weight). From a 36rem panel it takes the first two
  thirds of the row, ending where the second tile does. Hover brings the
  frame up to the muted tone.
- **Sign-in** moved out of the title row into the last third of that
  row, at its top right (level with the card's top, nearest the tiles).
  Where that third is narrow (36–60rem of panel, e.g. 1024 and 768px) it
  reads "Sign in", with the full "Sign in to request materials" kept as
  the link's name for screen readers. On phones the card and button stack,
  the button full width.
- **Title row** is back to the title with the hours beside it (the
  `.pf-landing__head-text` wrapper is gone).

Files: `templates/includes/landing.html.twig`,
`scss/components/_landing-features.scss`, `css/style.css`.

### Hero: Next event title in the label type (2026-10-20)

The event title in the Next event line now uses the same small tracked
caps as the day and time, so the line reads as one caption. It stays in
the full-strength text colour (the time is muted), so it still reads as
the link, and keeps its underline and arrow on hover. The phones' held
height follows (three label lines).

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: Next event as a line under the intro (2026-10-20)

The TOC sidebar's Next event tile is gone. Next event is now one line
right under the page description: "Next event" in the search heading's
gold label, the day and time in the muted label type, then the event
title as the link, with an arrow that nudges on hover. `hero-event.js`
fills it in (`.pf-landing__next`); its height is held while the feed
loads and the words fade in, so nothing below moves. On phones it's three
fixed lines (label, time, title), each cut with an ellipsis, so the held
height always matches. It's hidden on pages without the events carousel
and removed when nothing is coming up. The tiles give up the line's 24px
(198px tall at 1440x900, was 230).

Files: `templates/includes/landing.html.twig`, `js/hero-event.js`,
`scss/components/_landing-features.scss`,
`scss/components/_landing-hero.scss`, `css/style.css`.

### Hero: blog post as a caption, Next event as a tile (2026-10-20)

- **Blog post.** No longer a framed strip flush under the tiles: it sits a
  little below them, unframed, like a gallery label. A small colour picture
  (16:9, 104–176px wide), then the label (From the blog · date · category)
  and the title, light, in two lines at most. The excerpt is gone. Hover or
  focus eases the picture in a little and underlines the title. It no
  longer follows the first column when tiles open, and the tiles keep
  their full frame. The tiles get the height it gave up (230px at
  1440x900, was 202).
- **Next event in the TOC** is now a small tile like the hero's: the
  picture fills it (4:3) under a scrim, with the day and time, title and
  place in white at its foot. Hover or focus dims it under a veil and eases
  the picture in. It also no longer flashes an empty "Next event" label
  while the feed loads: its `hidden` rule was less specific than the rule
  that shows the card on pages with the events carousel.

Files: `templates/includes/landing.html.twig`,
`scss/components/_landing-features.scss`,
`scss/components/_landing-hero.scss`, `css/style.css`.

### Hero: one accordion, the blog post under it, Next event in the TOC (2026-10-20)

Back to a single tile accordion. The second accordion is gone:

- **Blog post row.** The latest post now takes the whole row under the
  tiles, inside the same frame with no line between them. Its picture sits
  under the first tile, at the same width, and the label (From the blog ·
  date · category), title and a line of the post sit across the other two.
  The whole row is one link. The picture rests in greyscale and comes back
  to colour on hover or focus. Under exactly three tiles it follows the
  first column as tiles open, so its edge stays under the first tile's. The
  tiles take whatever height the panel has left, and the row only what its
  text needs. Phones stack the picture (16:9) over the text. Placeholder
  (no blog category yet): hatched, not a link.
- **Next event in the TOC.** `hero-event.js` now moves the Next event card
  into the TOC sidebar above "Back to top", where the sidebar had empty
  space, and shows it once filled in, so nothing moves. It's removed
  without a TOC, an events carousel or an upcoming event. The second
  event ("Coming up") is dropped; the Events section further down the
  page lists them all.
- **Sign-in** stays in the title row.

Removed: the event tiles and `modifier` option in
`landing-exhibits.html.twig` (back to its earlier version), the tile
renderer in `hero-event.js`, and the second band's and column-linking CSS.

Files: `templates/includes/landing.html.twig`,
`templates/includes/landing-exhibits.html.twig`, `js/hero-event.js`,
`scss/components/_landing-features.scss`,
`scss/components/_landing-hero.scss`, `css/style.css`.

### Hero: no line between the two accordions (2026-10-20)

The two bands now meet with no line between them, one frame around both,
so they read as one grid. The first band drops its bottom border. The
second band's `border-top: 0` had never applied (the general `.pf-exhibits`
border rule came later at the same specificity), so there were two
hairlines there; it's now scoped to `.pf-landing__features`.

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: the two accordions open by column (2026-10-20)

Each band used to widen its own tiles, so the lines between the columns
broke apart across the two bands on hover. Under exactly three tiles they
now act as one grid of three columns: a tile in use, in either band,
widens its whole column in both (2.4 shares to the others' 1), so the
column lines stay aligned while it opens. With one event, it spans columns 1
and 2 and grows with whichever of them opens; the post stays in column 3.
Nothing changes height. Other landings, and heroes with other tile
counts, keep the single-row accordion.

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: events and blog band darker (2026-10-20)

The second band's pictures still read too bright at rest. They now rest
in greyscale at half brightness (was 80%), and an opened tile comes back
to colour at 75% brightness under the veil instead of full, so a poster's
own lettering (Hill-O-Ween's, for one) stays behind the tile's title. The
band's height is unchanged: its label, title and date already fill most
of it, with the link prompt taking the rest when a tile opens.

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: the events and blog band steps back (2026-10-20)

With two accordions of full-colour tiles at the same height, nothing led:
the events and post competed with the exhibitions, and LibCal's mixed
images (posters, photos) clashed with the chosen exhibition photos. The
second band is now 3 shares of the height to the first's 5 (at least
8–9.5rem against 11–15rem), its titles are one size down on one line, and
opening a tile shows only its link prompt. Its pictures rest in greyscale,
a little darker, and come back to full colour as a tile opens (switched,
not faded, with reduced motion). On narrow panels (phones, no hover)
they stay in colour.

Files: `scss/components/_landing-features.scss`, `css/style.css`.

### Hero: sign-in button in the title row (2026-10-20)

The "Sign in to request materials" button moved from under the tiles to
the far end of the title row, so it shows on the first screen and adds no
height to the panel. The title and hours line are now grouped
(`.pf-landing__head-text`) and wrap first: when the three don't fit in one
row (the contents panel open at 1440px), the hours drop under the title
and the button stays beside them, level with the title. Below lg it sits
under the hours line. With the button gone from the panel's foot, both
accordions fit on a 1440x900 first screen.

Files: `templates/includes/landing.html.twig`,
`scss/components/_landing-features.scss`, `css/style.css`.

### Hero: two events in the second accordion, flush bands (2026-10-20)

The second accordion only ever showed one event, because `hero-event.js`
picked just the next one. It now fills up to two event tiles ("Next event",
"Coming up"), each event once and soonest first, so with the blog post
the row is three tiles in the same thirds as the tiles above. A tile with
no event for it is removed, and the 2/3 rule below still applies when
only one event is coming up. The two accordions now touch, sharing one
frame line, which saves the gap between them; only the sign-in button
keeps its space above.

Files: `templates/includes/landing.html.twig`,
`templates/includes/landing-exhibits.html.twig`, `js/hero-event.js`,
`scss/components/_landing-features.scss`, `css/style.css`.

### Hero: a second tile accordion for the event and blog post (2026-10-20)

The card row under the hero tiles (above) never sat right, so Next event
and the latest blog post are now a second accordion of the same tiles,
stacked under the first. When exactly two of them sit under exactly three
tiles, the first takes two thirds and its edge lines up with the line
between the second and third tiles above; otherwise they share the row
equally, and with no event the post fills it. The event tile is filled in
by `hero-event.js` (picture, title link, day and time; category and place
show when it opens). The blog tile uses the hero tiles' image style
(`tile_showcase_landscape_lg`) instead of the small `medium` one.

In both accordions an opened tile's picture now dims under a black veil, so
its text reads more easily. From md the two bands split the panel's height
equally (at least 11–15rem each), and a tile's extra line can no longer
change a band's height, so nothing moves on hover. On short windows the
panel runs a little past the fold. The sign-in button now sits under both
accordions, aligned right (full width on phones).

Files: `templates/includes/landing.html.twig`,
`templates/includes/landing-exhibits.html.twig`, `js/hero-event.js`,
`palefire.theme`, `scss/components/_landing-features.scss`,
`css/style.css`. Run `drush cr` (template and theme function changes).

### Hero bottom row: lighter caption, sign-in button (2026-10-20)

The plinth (above) still felt as heavy as the tiles. The row is now a
light caption under them: no frame, small square thumbnails, the text
leading. Next event and Latest blog post stack, one row each with a
hairline between, and the account card is now an outlined "Sign in to
request materials" button beside them, as tall as both, in the Search
button's type and colour. With exactly three tiles above (a 36rem panel
and up), the cards take the first two thirds and end where the second tile
does, and the button sits under the third; with any other number of tiles
it's halves. With no event, the post is the only card and the button
shrinks to its height. On phones everything stacks, the button full width
at the end. Stacking the cards beside the button adds no extra row for it.

Files: `templates/includes/landing.html.twig`,
`scss/components/_landing-features.scss`, `css/style.css`.

### Guides use the WYSIWYG text format (2026-10-20)

Guide text was saved as Full HTML (`full_html`, else `basic_html`). The
three guide scripts now save it in the WYSIWYG format instead: the format whose machine name is `wysiwyg` or whose label
is "WYSIWYG". They stop with an error if there isn't one, rather than fall
back to Full HTML; `-- format=<machine name>` still picks another.

Guide pages built before this keep their old format until you run the new
`scripts/palefire_guide_wysiwyg.php` (see Guide pages > Move existing
guides to WYSIWYG). It changes only the format, not the text, and saves
each guide as a new revision.

Files: `scripts/palefire_guide_demo.php`,
`scripts/palefire_guide_research_help.php`,
`scripts/palefire_guide_special_collections.php`,
`scripts/palefire_guide_wysiwyg.php` (new).

### "How to Use Special Collections" guide (2026-10-20)

A new guide page for people who have never used an archive, built the same
way as the Systematic Reviews guide: run
`scripts/palefire_guide_special_collections.php` once and it creates an
unpublished Modular page in guide mode. It explains what Special
Collections is, how a collection is described (catalog record, finding
aid, box, folder, item), what a finding aid is and how to read one, the
paths through Manuscripts, University Archives and Oral History, how to
request and visit, and the do's and don'ts of asking Special Collections a
question through Ask Us. See "How to Use Special Collections" under Guide
pages.

File: `scripts/palefire_guide_special_collections.php` (new). No theme code
or config changes.

### Hero tiles use the page's Image Cover (2026-10-20)

The landing tiles (Hero tiles on the Landing hero and Landing department)
took their picture only from the linked page's landing Background poster,
so a Modular page's **Image Cover** (`field_image_cover`, Media: Image)
never showed. Tiles now use the Image Cover first, and the poster when a
page has no cover. Same landscape image style
(`tile_showcase_landscape_lg`), decorative and lazy. The Media-to-image
lookup the poster used is now `_palefire_media_image_item()`, shared by
both.

File: `palefire.theme`.

### TOC on landing-only pages; TOC open by default (2026-10-20)

- **Landing-only pages get the TOC:** palefire_modular's TOC block lists
  titled sections and renders nothing without one. A page whose only
  section is its landing (Oral History, a Landing department page) had no
  TOC, so the hero's Home and Map links (`heroTocIds()`) never showed.
  `PalefireHooks::preprocessPage()` now builds `landing_toc` with the same
  theme hook (`palefire_modular_toc`), and `page.html.twig` prints it only
  when the block is empty. `preprocessPalefireModularToc()` adds Home and
  Map to it as usual. Not on Form pages, where the block hides the TOC.
- **Open by default (lg and up):** landing pages started with the TOC
  hidden so the hero ran full width. Every page now starts with it shown.
  A visitor's own Hide/Show choice is still remembered
  (`palefire.tocCollapsed.landing` in localStorage).
- Checked on /v2/special with the TOC open: the hero fits the first screen
  at 1440x900 and 1280x800. At 1333x767 it runs 17px past the fold, and at
  1024x768 98px (the Next event | blog | account row wraps to two rows in
  the narrower column). The lead/map snap then waits for the panel's foot.

Files: `src/Hook/PalefireHooks.php`, `templates/layout/page.html.twig`,
`js/modular-toc-collapse.js`.

### New scroll section: Landing department (2026-10-20)

A copy of the Bento search landing (background video, lead card with the
page title, intro and "Search LSU Libraries", building map, Visit strip,
sub-header bar) with the Landing hero's tile accordion under the search.

- **Setup:** `scripts/palefire_landing_department_setup.php` creates the
  paragraph type `landing_department`. It copies every `bento_search` field
  with its settings, widget, display and form field groups, adds Tiles
  (`field_hero_tiles`, up to three Hero tiles, as on the hero), and allows
  the type wherever Bento search is allowed. Run it after
  `palefire_landing_hero_tiles_setup.php`, then `drush cr`. Safe to re-run.
- **Markup:** `paragraph--landing-department.html.twig` includes
  `includes/landing.html.twig` with `department`. The accordion moved into
  `includes/landing-exhibits.html.twig`, shared with the hero (its markup is
  unchanged). With no tiles, nothing shows (no placeholders).
- **Styles:** shared with the Bento landing. Only `.pf-landing__tiles` is
  new: the `pf-features` container the accordion's queries read. From md
  the band fills the card's free height under the search (at least 7rem),
  so the map still starts at the fold on 1440x900 and 768x1024. On short
  laptops (1333x767, 1024x768), with Oral History's intro, the card runs
  past the fold and the lead/map snap waits for its foot.
- `PalefireHooks::LANDING_BUNDLES` includes the new type, so it gets the
  background video, the page `<h1>`, the TOC's Home/Map links, the
  breadcrumb bar, and its poster on other pages' tiles.
- Checked on the live Oral History page with the bar and tiles simulated
  and the new CSS: hover opens a tile (635px against 265), no horizontal
  scroll at 320–1440, tiles stack 4:3 on phones.

Files: `scripts/palefire_landing_department_setup.php`,
`templates/paragraphs/paragraph--landing-department.html.twig`,
`templates/includes/landing.html.twig`,
`templates/includes/landing-exhibits.html.twig`, `palefire.theme`,
`src/Hook/PalefireHooks.php`, `scss/components/_landing-features.scss`,
`css/style.css`.

### Section snap works on tall sections; Oral History gets the landing bar (2026-10-20)

- **Snap:** Events, Specializations and Collections on /v2/special had
  `field_section_snap` on, but the page never pulled to them. Each was
  taller than the window under the header (1015, 827 and 835px against
  812px at 1440x900). The browser counts a snap area that tall as lined up
  anywhere inside it, so it stopped mid-section or at a section's foot.
  With proximity snapping (`html.pf-snap-proximity`), the snap point is
  now a 1px `::after` marker at each section's top. Measured on the live
  page with the new CSS: a scroll that ends near a section's top settles
  on it, and the middle of a long section stays free. Mandatory pages keep
  the section itself, so tall content is never skipped. The first
  section's merged snap margin carries over (`scroll-margin-top: inherit`).
- Snapping is now md and up only (`$pf-snap-breakpoint`). Phones scroll
  freely.
- **Oral History bar:** `PalefireHooks::crumbInLanding()` only accepted
  the Landing hero, so the older Bento search landing kept the breadcrumb
  in the page head. It now accepts both. The bar's measures move from
  `.pf-landing--hero` to `.pf-landing`. On the Bento landing the gap under
  the bar is the section's top padding, which the bar replaces, and the
  lead's min-height subtracts the bar. The map starts at exactly the same
  place as before at 768, 1024 and 1440 (checked with the bar markup
  simulated on the live page). The map itself is unchanged.

Files: `scss/components/_modular.scss`, `_landing-features.scss`,
`_landing-swiss.scss`, `src/Hook/PalefireHooks.php`, `css/style.css`.

### Landing hero: tiles fill tall screens, gold Search, tiles from Modular pages, several blog categories (2026-10-20)

- **Tiles fill the panel:** the tile band had a 32rem cap, so on very
  tall windows it stopped short and left a gap over the bottom row (728px
  at 3000×1875). The cap is gone; the band takes the row's height. Gap
  now 20px, the same as on laptops (1333×833 and 1024×768 unchanged).
- **Search button:** its text and arrow are in the title's colour, the
  same as the TOC's current item (`var(--pf-g-title, var(--pf-toc-active))`,
  242/220/155 on the hero), so the action stands out.
- **Tiles from Modular pages:** a new Hero tile paragraph (one per tile:
  a Modular page, an optional label, an optional dates line) and the
  Landing hero's Tiles field (up to three). Each tile shows the page's
  title, its intro as the line that opens, the background poster of the
  page's first landing as the image (cropped to
  `tile_showcase_landscape_lg`, lazy, size set), and links to the page
  ("Explore"). With no tiles the placeholders stay.
  `_palefire_hero_tiles()`, `_palefire_landing_poster()`.
- **Several blog categories:** "Latest from the blog" now takes any number
  of Article Categories (`field_landing_blog_category`, checkboxes) and
  shows the newest published article in any of them; its category line is
  the article's first matching category.

Setup (production, once): `drush php:script
themes/custom/palefire/scripts/palefire_landing_hero_tiles_setup.php`, then
`drush cr`. It creates the Hero tile type and its fields, adds Tiles and
Blog categories to the Landing hero (the vocabulary is read from
Article's own `field_article_category`), and opens an existing
single-value Blog category field up to any number. Safe to re-run.

Checked on /v2/special in headless Edge with the local CSS: the tile
heights and button colour above. Not checked: the PHP and Twig (no PHP
here, and the fields don't exist until the script runs), so look at the
hero after the script with a tile or two and a category set.

Files: `_landing-features.scss`, `_landing-swiss.scss`, `palefire.theme`,
`templates/includes/landing.html.twig`,
`scripts/palefire_landing_hero_tiles_setup.php`.

### Landing hero: the map holds perfectly still through the reveal (2026-10-20)

The curtain reveal moved the map with a scroll-driven transform, which
followed the scroll a frame late whenever the page's layout shifted. The
header's height hold (`palefire.js`) shifts it on every frame while the
header turns compact. Per-frame samples on /v2/special (1333×833 at
1.5×, local CSS/JS): with the browser's smooth scrolling, the map heading
drifted 124.6–129.6px, up to 4.4px, while the header shrank (scroll
150–720). In 40px steps, like a scrollbar drag, it wobbled about ±0.6px.
The landing's own glide stayed within 0.05px.

The map is now held with `position: sticky`, which the browser places in
step with the scroll on every frame:

- **Hold:** `.pf-map` is pulled up out of its wrapper by
  `--pf-map-reveal-hold` (200vh, negative margin), and a spacer of the
  same height follows it (`::after`). The wrapper (`#map`) keeps its size
  and place, so the TOC, the snap and `#map` links are unchanged
  (`display: flow-root` keeps the margin from collapsing through it).
  Sticky at the stop (49px + 5rem), the map waits from the top of the page
  and is let go exactly when the wrapper reaches the stop, where the snap
  lands. From there it scrolls with the page.
- **Fade:** unchanged. The heading, summary and drawing still fade in on
  the wrapper's view timeline. Opacity can't show a frame's lag.
- **JS:** `building-map.js` now tells that the reveal is on from
  `--pf-map-reveal: on`, not the old animation's name (the light-section
  hold-back stays off during the reveal). Its `shift()`, the focus and
  #hash fixes and `modular-toc.js` all measure the map against the
  wrapper, so they work as before.
- **Unchanged:** the support gate (`animation-timeline: view()`, md up, no
  reduced motion or Settings switch) and the fallback.

Measured per frame on /v2/special, headless Edge, local CSS/JS (lib.lsu.edu
requests with the debug header). The map heading stayed at exactly 129px
from scroll 0 to the stop, in the landing glide (wheel), browser smooth
scroll and 40px steps, at 1333×833 (1.5×), 1024×768, 1440×900, 768×1024 and
1920×1080. The stop lands with the wrapper at 129 at every width. The
wrapper's height, page height and the Events section's place are identical
to before (1333: 1522 / 4535 / 2508px), and the fade's opacity matches.
With reduced motion the map is static and scrolls with the page, as
before.

Files: `_landing-hero.scss`, `js/building-map.js`.

### Landing hero: more room under the Visit panel (2026-10-20)

The hero section's bottom padding under the Visit panel was 2rem (32px)
and looked cramped against Events. It is now `clamp(3rem, 6vw, 5rem)`:
48px on phones, about 61px at 1024, and the sections' usual 80px from
about 1333px wide. It is the section's `--pf-section-pad-block`, so the
background video's clip (`_landing.scss`) still reaches the section's
foot. The top stays 0, and the lead's full-screen height doesn't read
this variable, so nothing above the panel moves.

Checked in headless Edge: the padding (and the video's clip) measure 48 /
61.44 / 80px at 320 / 1024 / 1440, with no horizontal scroll.

File: `_landing-features.scss`.

### Landing bar stays through Home and Map; lead and Visit edge to edge on phones (2026-10-20)

The sub-header bar (breadcrumb and video button) used to scroll away with
the lead, leaving only the video button. It now stays the same the whole
time you're on the landing.

- **Sticky:** `.pf-landing__bar` sticks under the header (`top:
  var(--pf-header-h)`, so it follows the compact header) for the whole
  landing, lead and map, and leaves with it. It sits at z-index 3, above
  the map's heading and event card (2). The docked video button (5) stays
  in its row.
- **Same glass throughout (md up):** while the lead is under the bar, the
  bar keeps only the 70% tint (moved from the lead's `background-image`
  to the bar) over the lead's glass, as before, so the double-blur flicker
  stays fixed. Once the lead's foot passes the bar's bottom edge, a
  scroll-driven switch on the lead's view timeline (`--pf-lead`,
  `timeline-scope` on the landing, no JS) gives the bar its own colour
  and blur under the same tint. Browsers without `timeline-scope` keep the
  bar's own glass with the lead starting under it, and the lead is
  shortened by the bar's height so it still ends at the fold. Filled
  `forwards`, not `both`: filled backwards, the blur started as
  `blur(0px)` rather than `none`, and Chrome drew the lead's glass under
  the bar twice.
- **Not hidden under the bar (WCAG 2.4.11):** inside the landing,
  focusable items get `scroll-margin-top` of the bar plus 1rem, TOC
  targets (h3s) the bar plus 2rem, and the map drawing (a tapped room
  brings it into view) the header, the bar and 1rem. The map's own 5rem
  stop already clears the bar.
- **Edge to edge below md:** the lead panel and the Visit strip pull out
  by the section padding, drop their side borders and pad their content
  to the page gutter (1.25rem), in line with the map heading. In a flush
  section (no padding) nothing changes.

Checked in headless Edge on a local test page with the compiled CSS. The
bar holds at the header's foot from scroll 0 to the end of the landing at
320, 768, 1024 and 1440, then scrolls away. It switches to its own glass
between scroll 764 and 766 at 1440×900, as the lead's foot crosses its
136/134px edge. At rest the bar's colour matches the committed CSS
exactly (248, 227, 227 over the test stripes). At 320 the lead and Visit
strip run 0–305px (the full width less the scrollbar), with no horizontal
scroll.

Files: `_landing-features.scss`, `_landing-hero.scss` (the reveal's own
bar z-index is gone).

### Landing hero: a true curtain reveal for the map (2026-10-19)

The map now stays perfectly still, where the snap will stop it, while the
lead slides up off it. Before, it crept up at 6.5% of the scroll and then
eased into full speed.

- **Pinned:** through the timeline the map moves up against the scroll
  one for one (`pf-map-reveal`: pulled up by the whole distance the
  wrapper travels, `100vh` less the top inset, linear), so on screen it
  doesn't move. The 82% ease-out segment is gone.
- **Exact end:** the timeline's top inset is now the compact header
  (49px, the same at every width from md up) plus the map's 5rem stop, so
  the timeline ends exactly where the snap stops the map. From there it
  scrolls with the page again, from the place it was already in.
- **Unchanged:** the fade-in of the heading, summary and drawing (first
  half, under the lead's glass), the wrapper staying put for the TOC, the
  snap and `#map` links, and the JS reading the map's real place.

Measured on /v2/special at 1333×798 (pane visible, CSS injected), scroll
→ map top on screen: 120 → 129, 300 → 129, 480 → 129, 660 → 129,
720 → 129 (the lead's edge moving 679 → 79), then 780 → 91 and 840 → 31
with the page. The snap stops at 742. Below about 120px of scroll the map
is still faded out, so its first few px of movement don't show.

File: `_landing-hero.scss`.

### Landing tiles open at a calmer pace (2026-10-19)

The tiles' hover/focus accordion used a quick-start curve
(`cubic-bezier(.2, .7, .2, 1)`) over .5–.6s, so most of its movement
happened in the first 150ms. Now it uses the site's pace:

- **Curve:** a soft ease-in-out, `--pf-exhibit-ease: cubic-bezier(.45, 0,
  .2, 1)`. The tile widens over .9s, the text space opens over .8s, and
  the description fades in over .6s once the space has opened. Closing,
  it fades out (.25s), then the space closes (.7s).
- **Real height:** where height can animate to `auto` (`interpolate-size`:
  Chrome, Edge), the space opens to the description's own height, not
  toward a 6rem `max-height`, which ended the visible motion early. Other
  browsers keep the `max-height`.
- **No re-wrap:** the description is laid out at the open tile's width
  from the start (`100cqi × 2.4 / 4.4` less the padding, with three
  tiles). Laid out at the narrow width, it re-wrapped as the tile widened,
  so the space overshot and snapped back (46 → 51 → 30px).

Measured on /v2/special at 1333×798 (focus on a tile, CSS injected): the
tile widens 396 → 463 → 570 → 613 → 629 → 635px at 0.1/0.25/0.4/0.55/
0.7/0.85s, and the text space opens 0 → 2 → 15 → 25 → 28 → 30px with no
overshoot. Opacity .19 at 0.85s, .71 at 1s, 1 by 1.5s. The description's
width matches the open tile's text width exactly (595px). Reduced motion
still turns the transitions off.

File: `_landing-features.scss`.

### Lead → map: one calm glide instead of a flick and a crawl (2026-10-19)

I measured the scroll from the lead to the map frame by frame in the
Browser pane (1333×798). Frame times were fine: no long tasks, about 10 of
180 frames over 12ms. The problem was the motion curve. The snap used the
browser's smooth scroll, which is front-loaded: 620 of the 742px in about
280ms (up to 86px a frame), then a crawl of over a second for the last
120px. The lead flew off and the scroll-driven reveal played as a flash
and then a slow settle, the "two steps".

`landing-snap.js` now runs its own glide: an ease-in-out cubic over about
a second (600–1100ms by distance; 990ms here). The lead lifts off gently,
is fastest half-way and settles gently, so the reveal reads as one motion.
Each frame scrolls instantly (so the CSS smooth scroll doesn't stack on
top), and CSS scroll snapping is off for the glide. Without that, the
browser snapped the rest of the way at once after an instant scroll near
the map's snap point, a jump of about 280px mid-glide. Reduced motion stays
instant. The glide ends the step itself (`scrollend` would fire after
each of its frames), and the fallback timer still covers a hidden tab.

Measured with the same glide run in the page: 0 → 742px in about 1s, no
jumps, at most 37px per frame (86 before), the map's drawn top easing
from 308 to 129, a steady 60fps or better, and no frame over 25ms.

File: `js/landing-snap.js` (the glide itself moved to `palefire.js`, see
the next entry).

### TOC jumps use the same eased glide (2026-10-19)

The glide is now one shared helper, `Drupal.palefireGlide(to, done,
{ interruptible })` in `palefire.js` (the theme's global script). It's
used by:

- **The landing snap (`landing-snap.js`):** as before, not interruptible,
  since it swallows a gesture's trailing wheel events on purpose.
- **Every TOC jump (`modular-toc.js`):** sections, sub-links, the first
  link (Home) and "Back to top", instead of the browser's front-loaded
  smooth scroll. These glides are interruptible: a wheel, touch or key
  press stops them where they are. The TOC's tracking holds until the
  glide ends, then focus moves to the section's heading. Before, the lock
  ended on `scrollend`, which would now fire after the glide's first
  frame.

The helper is instant with reduced motion, turns CSS scroll snapping off
for the glide and back on after (including when interrupted), and takes
600–1100ms by distance. `landing-snap` and `modular-toc` now depend on
`palefire/global`, so it's always there. Without it the TOC falls back to
the browser's smooth scroll.

Measured in the pane at 1333×798 with the glide run in the page:

| Jump | Time | Largest step | Heading under the header |
|---|---|---|---|
| Top → Map | 989ms | 35px | 80px |
| Top → Events | 1109ms | 63px | 80px |
| Top → Collections | 1101ms | 75px | 80px |
| Mid-page → Map | 1108ms | 51px | 80px |
| Mid-page → top | 1118ms | 67px | y 0 |

No jumps. A wheel event stopped a glide at once, with snapping restored.
The browser then settled on the map's nearby snap point, as it does
after any scroll. `node --check` passes for all three scripts.

Files: `js/palefire.js`, `js/landing-snap.js`, `js/modular-toc.js`,
`palefire.libraries.yml`.

### TOC jumps land every section the same way (2026-10-19)

On /v2/special the TOC landed each section differently: the map heading
53px under the header, "Events" 80px, and "Collections" 24px *under* the
header, out of sight. Three causes:

- **Collections overshoot:** the Collections tile cards had
  `scroll-snap-align: start`, there so they snap sideways in their strip,
  but it applies to both axes. So every card was also a vertical snap point
  for the page, and a jump to Collections was snapped on past its title to
  the first card. Now `none start` (sideways only). File:
  `_tile-showcase.scss`.
- **The map's stop:** it was `clamp(1.5rem, 4vw, 3.5rem)` under the
  header. Now 5rem, the same place every section title lands (the
  sections' top padding from md). The reveal's end moves with it
  (`--pf-map-reveal-inset`), so the map is still by the time the snap or a
  jump arrives. File: `_landing-hero.scss`.
- **The header shrinking on the way:** `scroll-padding-top` is the
  header's height at the moment of the click. From the top of the page
  that's the full header, which compacts on the way down, so a section that
  isn't a snap point (nothing re-aligns it) stopped lower by the
  difference. `modular-toc.js` now works the target out itself. Past the
  point where the header compacts (`.pf-header-sentinel`), it aims under
  the compact header. It still corrects for the map's reveal shift, and the
  CSS smooth scroll and each target's `scroll-margin` still apply.
- **Event sub-links:** these now land on their day (date, weather and
  image card) rather than on the event's row, which sits at the foot of the
  image card.

Measured on /v2/special at 1333×798 with the changes injected, smooth
scroll on, from the top and from mid-page:

| Target | From the top | From mid-page |
|---|---|---|
| #map, #events, #collections (heading) | 80 / 80 / 80 | 80 / 80 / 80 |
| A Collections tile (sub-link) | 31 | 31 |
| An event (its day) | 31 | 31 |

All values are px under the header. Sub-links keep their 2rem margin. Not
checked: phone widths, and #home (it goes to the top).

Files: `_tile-showcase.scss`, `_landing-hero.scss`, `js/modular-toc.js`.

### Collections tiles: no flicker on hover (2026-10-19)

Hovering a tile showcase card zooms its image (`scale(1.04)`) and turns
its label gold. As the zoom began, Chrome moved the image onto a
compositing layer of its own, and moved it back once the zoom had ended,
repainting the label and title over it each time. The text's
anti-aliasing flipped at both ends of every hover, which read as a
flicker. The image now keeps its own layer (`will-change: transform`,
only when motion is allowed), so nothing is repainted mid-hover. The
label's colour change is unchanged.

Checked on /v2/special: the rule applies to the three tile images (the
fourth tile has no image). The flicker itself can't be seen in the
Browser pane, so please confirm on the site.

File: `_tile-showcase.scss`.

### Section heading motion: not on guides (2026-10-19)

The H2 slide-in (`js/section-reveal.js`) now runs only on the Modular
content type's other modes. On guides (`<html data-pf-mode="guide">`, set
server-side), the script leaves headings alone, with no `.pf-reveal` and
nothing hidden, so they keep their own colour change (`_guide.scss`) and
nothing else. The guide-specific transition rule added for the slide is
gone, and the shared `.pf-reveal` transition drops the colour part it
carried for guides. The Events carousel reveal is unchanged.

`node --check` passes, and the compiled CSS has no guide `.pf-reveal` rule
left.

Files: `js/section-reveal.js`, `_modular.scss`.

### Section headings slide in; the Events carousel reveals in sequence (2026-10-19)

A new `js/section-reveal.js` (library `palefire/section-reveal`, attached
by the Modular page's sections template) works like the Collections tiles
(`tile-reveal.js`). Things animate in as they scroll into view and hide
again, at once, when they've left it, so they replay on the way back.

- **H2s:** every section title and the hero's map heading slide in from
  the left (1.5rem, `translate`) as they fade in: .6s fade, .7s slide. The
  map heading already fades with the map's reveal, so it only slides.
  Guides keep their heading colour change in the same transition. A
  heading that takes focus (a TOC jump) shows at once.
- **Events carousel:** once a fifth of it is in view, its parts fade in and
  rise 1rem into place in sequence. The order is the day strip, then for
  each day its label, date, weather, hours and image card, 70ms apart, with
  each day starting 140ms after the one before. It resets when the carousel
  has left the window. Only the parts move, never the carousel the script
  watches or the slides Bootstrap moves.
- **Off for:** reduced motion, the Settings drawer's switch, and no
  IntersectionObserver. Without JS nothing is hidden (the hidden states
  need classes only the script adds). Focus inside the carousel shows it
  at once.

Checked on /v2/special at 1333×798 with the CSS and the script's logic run
in the page: the headings hidden at the top and revealed in view. The
carousel at 150ms: day 1's label .15, the rest 0. At 400ms: day 1
.79/.65/.48/.29/.08, day 2 .48/.29/0/0. All shown by 1.2s.

The map reveal's two-step look (the TOC's sub-list for the map heading
opening as the reveal ends) is left as it is, as agreed.

Files: `js/section-reveal.js` (new), `palefire.libraries.yml`,
`field--node--field-scroll-section--modular-page.html.twig`,
`_modular.scss`, `_events-carousel.scss`.

### Smoother reveal end; calmer tile text; one H2 size; event image cards (2026-10-19)

- **Reveal end:** the map moved at 15% of the scroll the whole way, then
  jumped to full speed where the reveal ended, a visible jolt as it
  settled. Now it's held a little more (6.5%) through 82% of the reveal,
  then a quadratic ease-out (its starting slope matched) brings its speed
  smoothly up to the page's own (`@keyframes pf-map-reveal`). Measured in
  the pane at 1333×798: 0.07 of scroll speed, then 0.17 → 0.32 → 0.47 →
  0.62 → 0.76 → 0.91 → 1.00 over the last 120px.
- **Tile description:** when a tile opens, the space opens first (the
  title and labels rise), then the line fades in where it sits, with no
  slide. Closing, it fades out quickly before the space closes.
- **One H2 size (`$pf-h2-size`):** `clamp(1.5rem, 1rem + 1.2vw,
  2.125rem)`, which is 24px on phones, 32px at 1333px and 34px at most. That
  sits a step under the page title (42.7px at 1333px). It now applies to:
  - every section title (`.pf-section__title`: Events, Collections…).
    Events loses its 60px display size.
  - the hero's map heading. It was an h3 at 52px, bigger than the h1, and
    is now an h2 at this size (`map.heading_tag`, from `#pf_hero` set by
    the hero's preprocess, with its own render-cache key). Elsewhere the
    map heading stays h3. `data-toc` keeps it in the TOC either way.
  - Guides keep their own scale.
- **Event dates:** the day's date (h3, "Wednesday, October 21") goes from
  32px to `clamp(1.25rem, 1rem + .5vw, 1.5rem)` (22.7px at 1333px), a step
  under the h2.
- **Event image cards:** a day with a featured image now reads like the
  landing's tiles. The image is the card, and the day's events sit over
  its foot in white, on a gradient under the list (clear at its top, 62%
  black at the first line, 80% at the foot), so white text keeps 4.5:1 over
  any image. The image and the list share one grid cell, so a long list
  grows the image rather than spilling. With one event, the whole card is
  its link: the focus ring goes round the card, the title underlines and
  the image eases in a little on hover (none with reduced motion). With
  several events, each row is its own link. Lines between rows are the
  hairline in white. Days without an image keep the ruled list.
  CSS only (`_events-carousel.scss`): the carousel markup is the LibCal
  module's.

Checked on /v2/special with the CSS injected: map heading 32px, Collections
32px, Events 32px (with the old 60px rule overridden), dates 22.7px. A
click anywhere on an event's image hits its link. Not checked: the h2 tag
(PHP and Twig changes run after deploy), phones, and multi-event days (the
live days have one event each).

Files: `palefire.theme`, `paragraph--building-map.html.twig`,
`_variables.scss`, `_modular.scss`, `_landing-swiss.scss`,
`_landing-hero.scss`, `_landing-features.scss`, `_events-carousel.scss`.

### Landing hero: the lead slides off a near-static map (2026-10-19)

Scrolling from the lead to the map, the map now barely moves (15% of the
scroll) while the lead slides up off it, as if the map had been waiting
underneath. It's a CSS scroll-driven animation (`view-timeline`), so it runs
on the compositor with no scroll listeners. It's on from md up where
`animation-timeline: view()` is supported (Chrome, Edge, Safari 26). In
Firefox, with reduced motion, or with the Settings drawer's Reduce motion
switch, the map scrolls as before.

**CSS (`_landing-hero.scss`)**
- **Timeline:** the map wrapper (`#map`) entering the window, with the top
  inset by about the compact header plus the lead's top padding. Only
  `.pf-map` inside the wrapper moves, so the wrapper itself keeps its
  place, and the TOC, the snap and `#map` links measure it as before.
- **Waiting under the lead:** before the timeline starts, the map is pulled
  up by 85% of the distance it's about to travel, so it waits under the
  lead. Its heading, summary and drawing fade in over the first half
  (`pf-map-reveal-fade`), still under the lead's glass, so the top of the
  page shows nothing of it. By the time the lead uncovers them they're
  fully shown.
- **Stacking:** the lead (z 1) and the bar over it (z 2) stack above the
  map so it can pass under them.

**JS: keeping everything on the map's real place**
- **Draw-on (`building-map.js`):** watches the wrapper and works out the
  drawing's share from its place in the page (less the reveal's shift),
  so the line drawing starts when the drawing is really a quarter in view.
  The dimmed backdrop also watches the wrapper.
- **Hold-back:** the light-section hold-back (`.has-map-view`) is skipped
  while the reveal runs, since its own fade does that job.
- **Keyboard focus:** if focus moves into the map mid-reveal, the page
  scrolls to the control's real place. Otherwise the browser would aim at
  where the map is drawn, and the control could end up off screen. A page
  opened at a #hash inside the map is corrected the same way after load.
- **TOC (`modular-toc.js`):** a TOC jump to a target inside the map (its
  heading) aims at its real place, with the same scroll-padding and
  margin.

Measured on /v2/special at 1333×798 with the CSS injected, Browser pane
visible:

| Scroll | Map drawn at | Shift | Heading opacity | Lead's bottom |
|---|---|---|---|---|
| 0 | 285 | −586 | 0 | 799 |
| 200 | 194 | −477 | .37 | 599 |
| 400 | 164 | −307 | .95 | 399 |
| 600 | 134 | −137 | 1 | 199 |
| 750 | 111 | −10 | 1 | 49 |
| 800 | 71 | 0 | 1 | gone |

The map moves 75px over 500px of scroll. The shift reaches 0 at about
762, just before the snap stops at about 769, so the map is still when it
arrives. `node --check` passes for both scripts. Not checked: the new JS
on a page (the live site still has the old scripts), the frame rate (the
pane can't measure it reliably), and other widths.

Files: `_landing-hero.scss`, `js/building-map.js`, `js/modular-toc.js`.

### TOC: the first link does what "Back to top" does (2026-10-19)

The TOC's first link (Home on the hero) and "Back to top" now go through
one path in `modular-toc.js`:

- **Destination:** the page's very top (`window.scrollTo({ top: 0 })`,
  with the CSS smooth scroll, instant with reduced motion). Home used to
  stop at the lead's `scroll-margin`. Back to top was a native `#top`
  anchor that added `#top` to the history.
- **Address:** the address loses its #hash (`replaceState`, so no history
  entry).
- **Highlight:** the first TOC entry is marked active right away, the
  scroll lock holds the highlight still, and the phone drawer closes.
- **Fallback:** modified clicks and no JS keep the plain anchors.

Checked on /v2/special with the same steps run in the page: from the map
(y 702), both land at y 0 with no hash and no new history entry.
`node --check` passes.

File: `js/modular-toc.js`.

### TOC clicks no longer add browser history entries (2026-10-19)

Each TOC link was a plain anchor, so every click added an entry, and Back
stepped through the jumps (#map, #events, #home…) before leaving the page.
`modular-toc.js` now handles a plain left click itself:

- **Hash:** the address still gets the section's #hash, for sharing or
  reloading, but through `history.replaceState()`, so there's no new
  entry. Back leaves the page.
- **Scroll:** `scrollIntoView({ block: 'start' })` keeps what the anchor
  did. It uses the CSS smooth scroll (instant with reduced motion) and each
  section's `scroll-margin`. It runs after the phone drawer starts
  closing, as the anchor's jump did.
- **Unchanged:** the active highlight, the scroll lock and moving focus to
  the section's heading. Without JS, and for modified clicks (Ctrl/Cmd,
  Shift, Alt, middle click), the links behave as plain anchors.

Checked on /v2/special with the same steps run in the page, for #home,
#map, the map's sub-link and #events: each lands at the same scroll
position as the plain anchor (1, 702, 711, 2389), the address shows the
hash, and history length doesn't change. `node --check` passes.

File: `js/modular-toc.js`.

### Landing hero: corner buttons as close to the screen's edge as to the header (2026-10-19)

The floating TOC button (left, TOC hidden) and the video pause button
(right) sat .375rem (6px) under the header but 1rem (16px) from the
screen's edges. Both now sit .375rem from the edge too, square in their
corners. A new `--pf-bar-icon-edge` carries the buttons' inset, and the
breadcrumb keeps `--pf-bar-edge` (1rem):

- With the TOC hidden, the breadcrumb starts 1rem past the TOC button, so
  it moves 10px left with the button.
- With the TOC open, the breadcrumb is still 1rem in from the column, as
  before.
- The TOC button's new position only applies over the hero's bar
  (`:has(.pf-landing__bar)`). Other pages keep 1rem.

Measured on /v2/special at 1333 with the change injected: TOC button 6px
from the header and 6px from the left edge, video button 6px from the
header and 6px from the right edge. The breadcrumb is 16px past the TOC
button (TOC hidden), and 16px from the column (TOC open).

Files: `_landing-features.scss`, `_modular.scss`.

### Landing hero: bottom row lines up with the tiles (2026-10-19)

The three cards under the tiles (Next event | Latest blog post | Your
account) now start where the tiles above them start. The tiles are equal
thirds with nothing between them, but the cards' grid had a column gap,
so the second and third cards started 13–27px to the right of their
tiles. With three columns (panel 50rem and up), the gap is now 0, and the
space between cards is padding at the end of the first two, the same
amount the gap was (`clamp(1.5rem, 3vw, 2.5rem)`).

Measured on /v2/special with the change injected (tile left → card left):

| Window | TOC hidden | TOC open |
|---|---|---|
| 1333 | 81/469/857 → 80/468/857 | 381/669/957 → 380/668/957 |
| 1920 | 317/743/1169 → 316/743/1169 | 467/893/1319 → 466/893/1319 |
| 1024 | 62/359/657 → 61/359/657 | two columns of cards, unchanged |

The 1px differences are the tile frame. With the panel under 50rem the
cards drop to two columns, which can't line up with three tiles, so that
case is unchanged.

File: `_landing-features.scss`.

### Map screen lines up with the lead: same gutter, same top padding (2026-10-19)

On the landing hero (md and up), the map's heading, summary and Visit
panel now start where the lead's title and text do, and a snap to the map
stops its heading the lead's top padding under the header. The text still
sits deep in the drawing's corner, and the drawing alone may spill into
the gutters.

- **Gutter:** the map wrapper (`.pf-landing__visit`) now spans the
  content column with the lead's own `--pf-hero-inset` padding. Its
  percentages are of the same width as the lead's, so the two always
  match. The drawing spills back out by
  `max(0, min(gutter − section padding, (1280px − 100cqi) / 2))`, which
  keeps it the size it was.
- **Top:** the snap's `scroll-margin-top` is now the lead's top padding,
  `clamp(1.5rem, 4vw, 3.5rem)`. The heading moved from its offset down in
  the drawing to the top. The drawing and the summary move up with it
  (`--pf-map-lift`: the row gap plus 3cqi, less 1rem), so the summary is
  still 1rem under the heading and 3cqi into the drawing's corner.
- **Visit panel:** it's in the wrapper, so it now spans the lead's text
  column too, the same width as the search and the tiles.

Measured on /v2/special with the change injected:

| Window | TOC | Lead title / map heading, left | Drawing width, before → after |
|---|---|---|---|
| 1333 | hidden / open | 80 / 80, 380 / 380 | 1205 → 1205, 905 → 905 |
| 1440 | hidden / open | 86 / 86, 386 / 386 | 1280 → 1280, 1012 → 1012 |
| 1920 | hidden / open | 316 / 316, 466 / 466 | 1280 → 1280 |
| 1024 | hidden / open | 61 / 61, 361 / 361 | 896 → 896, 596 → 596 |
| 768 | | 46 / 46 | 640 → 668 (the gutter is under the section padding here) |

Snapped at 1333, the heading's top is 53px under the compact header (it
was 127px). Nothing scrolls sideways at any width.

File: `_landing-hero.scss`.

### Map: quieter room hatching on desktop (2026-10-19)

From lg (992px), where the drawing is large and its long hatch lines read
heavy, every room hatch is about a quarter quieter. The gap between
resting and selected stays, so the selected room still stands out (its
name also turns gold).

| Hatch | Resting | Selected (hover, focus, tap) |
|---|---|---|
| Walk-in | .3 → **.22** | .5 → **.4** |
| Appointment (broken hatch, as on Hill) | .42 → **.32** | .7 → **.55** |
| Appointment (plain hatch) | .13 → .1 | .3 → .24 |

The legend swatches on the "Walk in" / "By appointment" headings use the
same variables, so they match. Below lg, nothing changes. Checked on
/v2/special with the change injected: rooms and swatches read
.22/.4 and .32/.55 at 1333px, and the old values at 800px.

File: `_building-map.scss`.

### Map: drawn once; no fade out and back in (2026-10-19)

- **Drawn once:** the draw-on trace ran every time the map came back into
  view, because it reset whenever the map left the screen. Now it runs the
  first time a quarter of the map is in view, then the observer
  disconnects, and the map stays drawn (rooms and names included) for the
  rest of the visit. `reset()` is gone.
- **No fade out/in:** on light sections (/v2/special is "light" under
  Twilight), the whole map, legend and Visit panel faded to opacity 0
  whenever the map left the window, and back in when it returned (0.6s
  each way). That hold-back only exists so white map lines don't peek out
  under the lead before you get there. `building-map.js` now adds
  `.has-map-seen` the first time the map is in view, and the hold-back
  rule skips it, so the map fades in once and then stays.
- **Unchanged:** the video's darker wash while the map is in view, on
  sections that have one (it's how the white lines stay readable). On
  /v2/special under Twilight the wash is the same both ways, so nothing
  fades there. Also unchanged: the "light, dark at the map" theme switch.

Checked: `node --check js/building-map.js` passes, and the compiled CSS
has the hold-back rule only with `:not(.has-map-seen)`. Not checked: the
script itself on a page. The live site still has the old one, and the
pane freezes the timeline.

Files: `js/building-map.js`, `_landing-swiss.scss`.

### Legend lines are hairlines; Visit strip's name across, columns aligned (2026-10-19)

- **Hairlines:** the legend's lines (above and below the toggle, above each
  group, under each room) used the drawing's dashed-line grey
  (`--pf-map-hidden-ink`, #5c5c5c, opaque). They now use the site's soft
  hairline (`$pf-hairline`, the panel's muted tone at 20%) through a new
  `--pf-map-rule`, the same as the hours rows in the Visit strip. The
  drawing keeps its grey. Rule from here on: lines and borders are this
  hairline unless a control needs 3:1.
- **Visit strip layout:** the name ("Visit / Hill Memorial Library") runs
  across the top. Under it, the columns start on one line: Instagram |
  hours | mini map from 1200px, and hours | map with Instagram under the
  hours from 768px. The map column stretches, with its mini map growing
  (16:10 at least), so the columns also end on one line. No new rules or
  borders. Applies to every Visit strip.
- **The row fade-in:** the previous patch (`c1b257f`) wasn't on the server
  yet when this was checked. /v2/special had no `.pf-seq` rows and no fade
  rule, while the patch before it (hatches on the headings) was live. It
  arrives with `git pull && drush cr`.

Measured on /v2/special with the change injected:

| Window | Name | Column tops | Column bottoms |
|---|---|---|---|
| 1333 | full width | 2643 / 2643 / 2643 | 2918 / 2918 / 2918 |
| 1024 | full width | hours 2466, map 2466 | 2899 (Instagram) / 2899 (map) |
| 320 | stacked | | no sideways scroll |

Files: `_building-map.scss`, `_visit.scss`.

### Legend & spaces opens like the guides' accordions (2026-10-19)

Same motion as `.pf-accordion` in the guides (the mobile menu's timing,
`$pf-menu-fade` .35s, `$pf-menu-stagger` .06s):

- **Glide:** in Chrome and Edge the body grows open (`::details-content`
  with `interpolate-size: allow-keywords`). Other browsers open it at
  once. Closing is instant. It clips with a 4px clip margin
  (`overflow: clip`) so the rooms' focus rings aren't cut off.
- **Sequenced fade:** the rows (legend line, each heading, each room)
  fade in one after another with a slight drop (`pf-accordion-in`).
  `includes/map-key.html.twig` numbers them (`.pf-seq`, `--pf-seq`) and
  caps the number at 5, so the 6th row and the rest come in together. No
  JS.
- **Reduced motion:** the OS setting and the Settings drawer's switch turn
  off the glide and the fade.

Checked on /v2/special with the change injected. Delays are 0, .06 … .3s,
and the 6th through 11th rows all start at .3s. The glide's target
resolves to the body's full height. The browser pane froze the clock, so
I didn't see the motion itself.

Files: `includes/map-key.html.twig`, `_building-map.scss`.

### Map legend in the Visit panel; hatches on the group headings (2026-10-19)

- **One panel:** on the landing hero, the map's legend and space list now
  sit across the top of the Visit strip, under the drawing, on its glass
  and inside its frame, instead of straight on the video. A rule separates
  them from the Visit content. It's one element with one blur, so no seam
  between two glass panels. How: `palefire_preprocess_paragraph__landing_hero()`
  hands the Visit data to the map paragraph (`#pf_visit` → `map.visit`,
  with its own render-cache key and the page's cache tags), the map's
  aside embeds the strip with the key in it, and `visit_in_map` stops the
  landing from printing it again. Hero only: on other landings the legend
  is a narrow column beside the drawing.
- **Description after the legend:** the building's note now follows
  "Legend & spaces" everywhere the map appears.
- **Hatches once per group:** the walk-in and appointment hatches are on
  the "Walk in" / "By appointment" headings, not on every room. The legend
  line keeps only the drawing's other marks (Elevator). The item-hover
  swatch rules went with them.
- **Templates:** the strip is now `includes/visit-strip.html.twig` (an
  embeddable key block) and the key is `includes/map-key.html.twig`, used
  by both landings and the map.

Checked by moving the elements on /v2/special: one panel at 1333, with the
key across the top at 768 and stacked at 320, and no sideways scroll.

Files: `palefire.theme`, `landing.html.twig`,
`paragraph--building-map.html.twig`, `includes/visit-strip.html.twig`
(new), `includes/map-key.html.twig` (new), `_visit.scss`,
`_building-map.scss`.

### Landing hero: one glass for bar and panel, one rule under the tiles, open/closed line (2026-10-19)

- **Flickering band, second fix:** drawing the bar after the panel wasn't
  enough. The bar and the panel each blurred the playing video on their
  own, and Chrome now and then skips one surface's blur for a frame or a
  repaint. Where the two met, that showed as a flickering band. From md,
  the panel now starts under the bar (pulled up by `--pf-landing-bar-h`,
  with the same amount added to its top padding), so there's one blurred
  surface. The bar has no glass of its own, just a tint
  (`color-mix(--pf-sidebar-bg 70%)`) over the panel's top 48px, which comes
  out close to its old shade. The video button's sticky dock is now only
  as wide as the button instead of a full-width layer over the bar.
  Edge case: a breadcrumb long enough to wrap would make the bar taller
  than the tint.
- **Rule under the tiles:** with the cards side by side (container 36rem
  up), one line runs across the whole row instead of three with gaps.
  When stacked, each card keeps its own line.
- **Open now / Closed now:** a line with the title, in the breadcrumb's
  voice: status dot, state, today's hours and the building, using the
  Visit strip's LibCal location (the page's Hours location field) and
  `visit-hours.js` (no new JS or requests). From lg it sits beside the
  title on its baseline, so the panel still fits the first screen. Below
  lg, and beside a long title, it goes under it. "Hours" shows dimmed
  while loading, and the line hides if LibCal fails. The dot only repeats
  the word, so it's `aria-hidden`.
  **Content note:** /v2/special's Hours location is 241, which LibCal
  calls "LSU Library" (7AM–12AM today). Hill Memorial Library is **236**
  (9AM–5PM), the location the concept page uses. Set the field to 236
  in the production UI to correct both this line and the Visit strip.

Measured on /v2/special with the change injected, panel bottom (window
height in brackets):

| Window | TOC hidden | TOC open |
|---|---|---|
| 1333×798 | 799 (798) | 799 (798) |
| 1440×900 | 901 (900) | 901 (900) |
| 1024×768 | 769 (768) | 864 (768): the narrow column wraps the hours and intro |

At 768 and 320 nothing scrolls sideways. At 320 the hours line wraps
under the title.

Files: `landing.html.twig`, `_landing-features.scss`.

### Landing bar: no flickering band under it (2026-10-19)

A dark band, about 20px tall, sometimes showed along the top of the lead
panel under the breadcrumb bar. It came and went as the cursor moved.
Both are glass (`backdrop-filter`), and the bar was drawn first. The
lead's blur therefore had the bar's dark fill behind it, and Chrome let
that bleed in along the shared edge whenever it repainted. The bar is now
drawn after the lead (`z-index: 1`), so the bar is never part of the
lead's backdrop. The TOC (2) and the video button (5) stay above it. The
TOC's glass was already drawn after the lead, which is why its edge never
showed the band.

File: `_landing-features.scss`.

### Hero search hairline, map tooltip on top, header under the admin toolbar (2026-10-19)

- **Search box:** the field's border and the divider before the Search
  button use the same 20% hairline as the rule above it and the tiles. The
  field is still easy to find from its glass fill, icon, placeholder,
  labelled button and heading. Hover brings its border up to the muted
  tone, and focus turns it gold.
- **Map tooltip:** the summary set into the Hill drawing (z-index 2) was
  drawn over the tooltip. While a tooltip is open, the drawing's stage
  rises to z-index 3. It drops back only after the tooltip fades out, and
  the text stays selectable the rest of the time.
- **Header vs. Gin:** when logged in, Drupal's admin toolbar (core Toolbar
  or Navigation, styled by Gin) is at z-index 501–503. Bootstrap's
  `sticky-top` (1020) put the header over its trays. With the toolbar on
  the page, the header is now at 500 and the glass under it at 499. The
  open menu drawer still rises to the offcanvas layer.

Files: `_landing-features.scss`, `_building-map.scss`, `_header.scss`.

### Landing hero: intro at 80% on desktop; even top and bottom margin (2026-10-19)

- **Intro:** from lg, the page intro under the title is 80% of the
  content column's width. Its lines end short of the margin instead of
  running to it. It's full width on smaller screens.
- **Bottom padding = top:** both are `clamp(1.5rem, 4vw, 3.5rem)`, 53px
  at 1333px, so the panel has an even margin around its content. The tile
  row gives up the height.

Measured on /v2/special with the change injected, in Twilight:

| Window | TOC | Top / bottom | Intro | Tiles | Panel ends |
|---|---|---|---|---|---|
| 1333×798 | hidden | 53 / 54 | 932 of 1165px | 162px | 799 |
| 1333×798 | open | 53 / 54 | 692 of 865px | 154px | 799 |
| 1333×767 | hidden | 53 / 54 | 932 of 1165px | 131px | 768 |
| 1333×767 | open | 53 / 54 | 692 of 865px | 140px | 785 |

At 1333×798 the panel ends at the window's bottom in both TOC states. The
last case runs 18px past the fold: the narrower column wraps the intro
to four lines, and the tiles are already near the least their text needs.
The map snap still works there.

File: `_landing-features.scss`.

### Landing hero: no blinking second bar under the header (2026-10-19)

The header leaves a glass strip behind when it turns compact near the top
of the page (`.pf-page::before`, `_header.scss`). It covers the gap the
taller header leaves, so the page doesn't show through.

- **The problem:** on the Landing hero it sat right between the compact
  header and the sub-header bar, in the same glass. It showed as a second
  bar that blinked on and off as the header compacted and grew back.
  Measured with the header compact: strip 50–88px, bar from 88px.
- **The fix:** the sub-header bar covers that space itself, so the strip
  is off on pages that have the bar (`.pf-page:has(.pf-landing__bar)`).
  Other pages keep it.

Checked on /v2/special with the change injected and the header forced
compact: nothing is drawn between the header and the bar. The real
compact switch doesn't fire in the hidden preview, so the blink itself
wasn't watched live.

File: `_landing-features.scss`.

### Landing hero: less space above the title; bar items at the screen's edges; no bar border (2026-10-19)

- **Space above the title:** `clamp(1.5rem, 4vw, 3.5rem)`, 53px at
  1333px. That's about halfway between the panel's own padding (24px)
  and the side gutter (80px, tried in the last change, too much).
- **Breadcrumb and video button near the screen's edges:** both sit 1rem
  from the content column's edges (`--pf-bar-edge`), like the floating TOC
  button, instead of on the content column, which leaves the space above
  the title clear.
  - With the TOC hidden (lg and up), the breadcrumb steps past the TOC's
    floating button (1rem + 2.25rem + 1rem), in step with the column
    (.3s, none under reduced motion).
  - The docked button keeps 1rem from the right edge, sticky as before.
- **No border under the bar:** it ran into the TOC sidebar's edge. The bar
  is now 3rem (`--pf-landing-bar-h`), so the docked button's offsets
  follow.

Measured on /v2/special at 1333×798 with the change injected, in
Twilight:
- **TOC open:** breadcrumb 16px from the column's edge, button 16px from
  the right.
- **TOC hidden:** breadcrumb at 68px, clear of the TOC button (which ends
  at 52px).
- **Both:** the button is inside the bar's row, the space above the title
  is 53px, and the panel ends at the window's bottom.

File: `_landing-features.scss`.

### Landing hero: gutter above the title, tiles fill the panel, one-line cards (2026-10-19)

- **Space above the title = the side gutter:** the panel's top padding is
  `--pf-hero-gutter`, `clamp(2rem, 6vw, 6rem)`, 80px at 1333px, the same
  as the sides there. On wide windows the sides grow further as the column
  centres; the top stays the gutter.
- **Tiles fill the panel:** the features grid is `1fr auto`. The tiles'
  row takes whatever height is left, so the bottom row sits at the
  panel's foot.
  - The band is at least `clamp(8rem, 17svh, 13rem)` (it holds its shape
    where the panel isn't full screen) and at most 32rem on very tall
    windows.
- **Bottom row, one line per field:** the date or label and the title end
  in an ellipsis, and the line under is clamped to one, so the three cards
  stay an even row.
  - They go three across from 50rem of panel (was 60rem), so the TOC being
    open at 1333px no longer drops the account card to a second row.
  - The thumbnail can shrink to 5.5rem in that narrow case (88×63). With
    the TOC hidden it's 111×79 at 1333px.

Measured on /v2/special with the change injected, in Twilight:

| Window | TOC | Top / side gap | Tile band | Panel ends |
|---|---|---|---|---|
| 1333×798 | open | 80 / 80 | 179px | 799 |
| 1333×798 | hidden | 80 / 80 | 187px | 799 |
| 1333×767 | open | 80 / 80 | 148px | 768 |
| 1333×767 | hidden | 80 / 80 | 156px | 768 |

In every case the panel ends at the window's bottom; the cards are 80–96px
tall.

File: `_landing-features.scss`.

### Landing hero: three opening tiles, account card, sticky video button, smooth TOC (2026-10-19)

**TOC hide/show without the video showing through** (`_modular.scss`):
- The sidebar used to slide with a `transform`, which runs on the
  compositor, smoothly. The column's width is page layout on the main
  thread. On this heavy page the panel's edge lagged behind the sidebar's,
  and the bare background video showed in the gap.
- The sidebar now sits at the end of its column (`justify-self: end`), so
  as the column narrows it moves off the page edge in the same layout as
  the content. The two edges can't part, and the RTL transform rule is
  gone.
- Checked by stepping the animation on /v2/special: the sidebar's right
  edge equals the panel's left edge at every step, closing and opening.

**Content column and sub-header bar** (`_landing-features.scss`):
- **Gutter:** the column keeps at least `clamp(2rem, 6vw, 6rem)` from the
  screen's edge (80px at 1333px), up to 1280px wide and centred. The bar's
  breadcrumb and the panel's content share it, which also keeps the
  breadcrumb clear of the floating TOC button.
- **Sticky video button:** the button moved into a zero-height sticky
  `.pf-landing__dock` laid over the bar's row (after the breadcrumb in the
  source, so after it in the tab order).
  - It sits on the column's right edge and, once the page scrolls, stays
    .375rem under the header, compact or not, like the TOC's button.
  - It leaves when the landing does, so it's there over the map, where the
    video shows most.
  - The bar keeps room for it, so a long breadcrumb wraps first (320px).

**Search:**
- More space around the search: 1rem to 1.5rem above the rule, under it,
  and under the field.
- The rule above it is now the same soft hairline as the lines above the
  bottom row.
- The field's border is softer, at 70% of the panel's muted tone. It can't
  match the hairline (20%, about 1.5:1): a text field's boundary needs 3:1
  (WCAG 1.4.11), and 70% is the least that keeps 3:1 on every reading
  swatch over a mid-grey or white/black video frame.

**Three tiles, opening on hover** (`landing.html.twig`):
- The tiles are Exhibition | LSU History | a new guide tile, "How to use
  Special Collections" (placeholder). They sit flush in one hairline
  frame, with no borders between.
- With a pointer, the tile under it, or the one with keyboard focus
  inside, grows to about 2.4 times the others' width (.6s). More of its
  picture shows, and its extra line and link prompt fade in.
  - Measured at 1333px: the open tile is 635px and the others 265px.
- Each tile has a `more` line, plus `url` and `cta` (empty for now). With
  a url, the title is the tile's link, stretched over it, so the
  slideshow's pause button stays its own control.
- In a closed tile the label and date lines stay on one line (ellipsis)
  and the title takes two at most, so the text always fits the tile.
- Below 36rem of panel the tiles stack at 4:3, with everything shown.
- Reduced motion switches without the animation.

**Bottom row: Next event | Latest blog post | Special Collections account:**
- The account card is a placeholder ("Sign in to your account",
  `account_url` empty for now) with Font Awesome Pro's user icon.
- The row is three across from 60rem of panel, two from 36rem, one below
  (container queries, so the TOC being open counts too).
- Thumbnails are 7×5 landscape, `clamp(6.5rem, 9.5cqi, 9rem)` wide (111×79
  at 1333px, up to 144×103).
- Titles take two lines and the line under them one, so the three cards
  stay one even row.

**Fit at 1333×767:** the tiles' band is `clamp(9rem, 19svh, 13rem)` and
the panel's padding is a little tighter. The content ends at 743px and the
panel at the window's bottom.

Checked on /v2/special by replacing these components' live rules with the
new ones and the markup as the template now renders it, at 320, 768 and
1333px, in Twilight. No sideways scroll anywhere. Not checked: the real
template (the PHP side is unchanged).

Files: `landing.html.twig`, `_landing-features.scss` and `_modular.scss`.

### Landing hero lead is full screen (2026-10-19)

From md up the hero's lead panel fills the screen: across the whole
content column, straight under the sub-header bar, down to the bottom of
the window. The map starts at the fold.

- **Width:** no side margins, no 1280px cap. The panel's content stays
  in a centred 1280px column (`--pf-hero-inset`), with the section's
  padding as the minimum on narrower screens.
- **Height:** `100svh` less the header and the bar (`--pf-landing-bar-h`,
  3rem plus the hairline), replacing the "fit the first screen" height.
- **Look:** the same glass, with only a hairline at its foot. No side or
  top border on the screen's edges.
- **Bar:** no gap under it now. Its breadcrumb and button line up with
  the panel's content column (they had lined up with the panel's old
  edges).
- **Phones (under md):** unchanged.

Measured on /v2/special with the new CSS injected:

| Window | Panel | Bottom | Content |
|---|---|---|---|
| 1333×767, TOC hidden | 0–1325 wide | 768 (the window plus the hairline) | breadcrumb on the title's edge, button on the search field's right edge |
| 1920×1000, TOC hidden | full width | to the bottom | 1280px column |
| 1920×1000, TOC open | 300–1912 | to the bottom | 1280px column |
| 768×1024 | full width | to the bottom | 640px column |

Nothing scrolls sideways at any of these sizes.

File: `_landing-features.scss`.

### Sub-header bar lines up with the lead panel's edges (2026-10-18)

The bar's breadcrumb and video button now line up with the lead panel's
outer edges. Before, they lined up with its text, a panel's padding further
in. The bar's side padding is the section's plus the panel's centring
margin, without the panel's own padding.

Measured on /v2/special: breadcrumb left = panel left and button right =
panel right (0px off) at 320, 1024, 1333 and 1920px wide. Nothing scrolls
sideways at 320px.

File: `_landing-features.scss`.

### Landing hero: sub-header bar; the video never moves; map heading by its summary (2026-10-18)

- **Sub-header bar:** the breadcrumb and the video button left the lead
  panel for a bar of their own, across the whole content column, right
  under the header.
  - It uses the header and TOC's glass and blur, with a hairline under it.
  - Its content lines up with the panel's: breadcrumb text 390px, title
    391px; the button's right edge is on the panel's padding.
  - `modular-toc.js` now finds this breadcrumb too, so the TOC title and
    its hide/show button sit on the bar's line (measured at 1333×767).
  - Panel spacing: 1rem under the bar, and the exhibition band is
    `clamp(9rem, 18svh, 12rem)`. At 1333×767 the panel ends at 756 of
    767px.
- **Background video fills the screen and never changes:** the fixed
  video layer is now the whole viewport (`inset: 0`).
  - It used to reach out from the section's edges by the top and side
    bleed variables. Those animate with the TOC column and change when the
    header turns compact on scroll, so the video was resized and
    re-cropped on every frame: the jagged TOC animation.
  - The landing's clip now only limits its foot (the section's bottom
    edge), so the video still ends where the next section starts.
  - Measured: the layer stays `0, 0, 1325 × 767` through a whole TOC
    toggle and while scrolling.
  - The column itself still animates. The page's content reflows as it
    does, which is far lighter without the video.
- **Map heading close to its summary:**
  - Corner layout (Hill, map 64rem wide or more): the heading is drawn
    lower, by the row-gap plus 3cqi less 1rem, so it sits 1rem above the
    summary. The summary and the drawing don't move.
  - Narrower: the summary is pulled up to 1rem under the heading instead
    of the map's 40px row-gap.
  - Measured: 16px in both.

Checked on /v2/special at 1333×767 with the new CSS injected and the
markup moved as the template now renders it, in Twilight.

Files: `landing.html.twig`, `_landing-features.scss`, `_landing.scss`,
`_landing-hero.scss` and `modular-toc.js`.

### Landing hero lead: flush under the header, shorter exhibitions, 5×7 thumbnails (2026-10-18)

- **Panel flush under the header:** no gap above the hero's lead panel.
  The section keeps 2rem below it.
- **Breadcrumb on the TOC title's line:** from lg (beside the TOC) the
  panel has the sidebar's 2rem top padding, and the breadcrumb row moves
  up by half of what the 2.25rem button adds to the 1.125rem text line.
  Measured at 1333×767: breadcrumb text 121px down, TOC title 120px.
- **Exhibitions:** a shorter band, `clamp(9rem, 20svh, 12rem)`. It was
  stretching to about 17rem. On a tall window the spare height goes
  between the exhibitions and the event/post row
  (`align-content: space-between`), so the row stays at the panel's foot.
- **Next event | Latest blog post:**
  - The "Next event" and "Latest from the blog" labels are visually
    hidden but kept for screen readers.
  - The thumbnails are 5×7 portrait, 5rem × 7rem (80 × 112px), cropped to
    fill. So is the hatched space held while the event feed loads.
  - The post excerpt is back to two lines beside the taller thumbnail.
- **Fit:** the lead ends at 753 of 767px at 1333×767.

Checked on /v2/special with the new CSS injected, in Twilight.

File: `_landing-features.scss`.

### Twilight: one gold, the pale gold (2026-10-18)

LSU gold (#fdd023) next to the pale gold headings (#f2dc9b) clashed, so
Twilight now uses the pale gold for everything.

- **Accent:** `#f2dc9b`. That covers links, the TOC bar and Back to top,
  the search field's focus border, the video button's progress bar, the
  events carousel, badges and the Settings switch.
  - Contrast: 11.3:1 on the page, 9.1:1 on the lead glass over a white
    frame, 15.5:1 for black text on it.
- **Link hover:** a lighter cream gold, `#fbf1d6` (13.6:1). It was the
  bright gold `#ffe27a`.
- **Hard-coded gold that reached Twilight:** on dark swatches these now
  read the swatch's accent, through `--pf-ui-gold` and
  `--pf-focus-color`:
  - the keyboard focus ring, which was `#ffd700` on every dark page,
    including the dark header and dark sections;
  - the exhibition tiles' gold rule and pause button focus;
  - the collection tiles' hover label and focus outline.
  - Every dark swatch's accent is 6:1 or more on its page, so focus
    stays well over 3:1. Light swatches keep their colours.
- **Unchanged:** the Settings drawer's "Focus" switch (a heavier gold
  ring, on purpose) and the Visit strip's yellow "opening soon" dot (a
  status colour).

Checked on /v2/special with the change injected: the accent, the TOC
accent, the landing accent and the focus colour, on the page and inside
the dark header, are all `#f2dc9b`.

Files: `_reading-themes.scss`, `_landing-features.scss` and
`_tile-showcase.scss`.

### Twilight goes charcoal; header matches the TOC; lead and Visit glass like the Settings drawer (2026-10-18)

**Twilight purples:** the 291° plum read reddish. The page is now
`#272429`, hue 276° at 6.5% saturation, which reads as charcoal. The
other purples share that hue at the same low saturation and keep their
lightness.

| Token | Plum (was) | Now |
|---|---|---|
| bg | #2e2330 | #272429 |
| surface | #352a37 | #2e2b30 |
| accent-soft | #372a39 | #2f2c31 |
| rule | #45384a | #3f3a42 |
| scroll | #9a8ca0 | #97929a |
| muted | #bcaebf | #b7b3ba |
| panel-muted | #c3b6c6 | #bfbbc1 |
| hover | #dcc3e2 | #d4c9dc |
| fg | #efe9f0 | #edebee |
| heading | #faf6fa | #f8f7f9 |

- The chrome works out to #242126.
- Pale gold titles and the LSU gold accent are unchanged.
- Contrast: text 12.9:1, muted 7.4:1, gold headings 11.3:1, hover
  9.6:1, scrollbar 5.0:1 (5.2:1 on the rail).

**Header = TOC** (every swatch): the header takes the TOC sidebar's glass
(`--pf-sidebar-bg`), and so does the strip under a header growing back.
- That is 90%, or 75% (light) / 80% (dark) over a landing video.
- It was the header's own 60% (light) / 78% (dark).
- The blur was already the same. Measured on /v2/special: header and
  sidebar are both `rgba(36, 33, 38, .8)` in Twilight.

**Lead panel and Visit strip** (every swatch): the Settings drawer's
glass and blur.
- **Light swatches:** exactly the drawer's, the page colour at 65%.
- **Dark swatches:** the drawer's colour (the page darkened 20%), at 55%
  on the hero and 75% on other landings. The drawer itself is 30%, but
  it has the dimmed backdrop behind it; these panels only have the
  video's wash.
- 55% / 75% is the least that keeps every dark swatch's panel text and
  labels at 4.5:1 over a white video frame. That was worked out per
  swatch under the hero's 80% wash and the plain landing's 60%. At the
  drawer's 30%, Dusk, Solarized, Cocoa, Forest, Midnight and Material
  labels would fall to 3.5–4.2:1.
- Light swatches stay 5.2:1 and up at 65%.
- Reduce transparency and the solid switch make them solid, like the TOC
  and header. `$pf-reading-lead-glass-dark*` in `_reading-themes.scss`.

Checked on /v2/special at 1333×767 with the new CSS injected, in Twilight
and in Cream.

File: `_reading-themes.scss`.

### Twilight: white text, pale gold headings, a softer plum (2026-10-18)

Twilight (the default reading colours) now reads more like Material.

| Token | Was | Now | On the page |
|---|---|---|---|
| Body text (`fg`) | #b2a6c1 grey lavender, 7.0:1 | #efe9f0 near-white | 12.6:1 |
| Headings (`heading`) | #e1d8ec | #faf6fa | 14.0:1 |
| Muted | #9e92ad, 5.5:1 | #bcaebf | 7.1:1 |
| Panel muted | #aca0ba | #c3b6c6 | — |
| Title (new) | — | #f2dc9b pale gold | 11.1:1 |
| Page (`bg`) | #251d30 | #2e2330 | — |
| Surface | #2b2238 | #352a37 | — |
| Rule | #392f47 | #45384a | — |
| Accent soft | #2d223b | #372a39 | — |
| Scrollbar | #8b7d9e | #9a8ca0 | 4.7:1, rail 4.9:1 |
| Text hover | #c19df1 | #dcc3e2 soft plum lilac | 9.3:1 |

- **Title:** Material's bright green headings, done in pale gold. Every
  heading in the content and the current TOC link use it (the `title`
  key, as Material's green does).
- **Purple:** softer and more plum. Same darkness, hue 291° at 16%
  saturation (was LSU purple's 266° at 25%).
- **Unchanged:** the LSU gold accent (#fdd023, 10.2:1) and the link
  hover gold.
- **Not added:** Material's yellow bold and purple italics have no
  Twilight equivalent, so bold and italic stay the text colour.
- **Over a background video, worst case (a white frame):**
  - panel headings 11.4:1, labels 6.3:1, gold headings 9.0:1;
  - the Visit strip's labels 6.0:1 (4.67:1 before).
- **High contrast:** the variant is generated from these values as
  before.

Checked on /v2/special at 1333×767 with the new tokens injected and
Twilight picked in Settings. With transitions off (the hidden preview
pane freezes them), every element took the new colours.

Files: `_reading-themes.scss` and `_visit.scss` (comment only).

### Landing hero: breadcrumb and video button inside the lead; map heading lower, summary smaller (2026-10-18)

- **Top row in the lead panel:** on a Landing hero with its lead panel,
  the breadcrumb and the background video button now share a row at the
  top of the panel, instead of floating over the video above it.
  - The breadcrumb is on the left, lined up with the title. The button is
    at the far end, on the panel's padding.
  - The breadcrumb is built by the same service as the System Breadcrumb
    block, so it has the same trail and template.
  - `PalefireHooks::crumbInLanding()` decides when this applies, and
    `page.html.twig` then leaves out the head's breadcrumb. The head was
    otherwise empty (the `<h1>` is in the lead), so it is skipped
    altogether.
  - Inside the panel the breadcrumb has no glass strip of its own.
  - `landing-video.js` only positions the button when the head has a
    breadcrumb, so here it stays in the row.
  - The panel starts 68px higher, and still ends on screen at 1333×767
    (735 of 767).
- **Map heading lower:** the map now stops 2.5–4.5rem under the header,
  not 1.5–2.5rem. At 1333 that is about 67px, which was 40.
- **Map summary smaller:**
  - in the drawing's corner: 17–22px, line height 1.4 (it was up to 32px);
  - elsewhere: 18px.
  - Your Hill history text runs 6 lines at 1333 and ends at 16.4cqi,
    clear of the upper Gallery at 19cqi. A hit test found no room shapes
    under it.
- **Phones (320px):** the breadcrumb wraps beside the button, and nothing
  scrolls sideways.

Checked on /v2/special with the new CSS and markup injected. The PHP is
unlinted (no PHP on this machine).

Files: `PalefireHooks.php`, `palefire.theme`, `page.html.twig`,
`landing.html.twig`, `_landing-features.scss`, `_landing-swiss.scss`,
`_landing-hero.scss` and `_building-map.scss`. Production: `git pull`,
then `drush cr`.

### Building map: Intro is the summary, the building's description sits above the legend (2026-10-18)

The map's two texts now have fixed roles. Before, the building's
description was the summary when it existed, which pushed the Intro down
to the note.

- **Summary:** the map paragraph's **Intro** (`field_map_intro`), under
  the heading. On the Hill hero, at desktop size, it is set into the
  drawing's empty corner, 0–50% across. Keep it to about four lines there:
  much longer and it reaches the upper Gallery. The note's row in the
  corner is gone.
- **Note:** the **building's description** (Taxonomy → Building spaces →
  Hill Memorial Library), right above "Legend & spaces", for who can walk
  in and what needs an appointment.
  - It sits outside the legend's `<details>`, so it still shows while the
    legend is collapsed. On the hero the legend starts collapsed, so a
    note inside it would have been hidden.
  - Text is 15px, up to 60ch wide.
- Either text is optional.

**For editors:** put the Hill Memorial history summary in the map's Intro.
Move the access text ("Exhibitions, the reading room and quiet study are
open to everyone…") to the Hill Memorial Library term's description.
Until then, the access text stays as the summary in the corner.

Checked on /v2/special at 1333×767 with test text injected:
- **Summary:** covers 0–50% across and 4–18% down, with no room shape
  under it (hit test).
- **Note:** shows above the collapsed legend toggle, 16px clear of it.
- **Width:** nothing scrolls sideways.

Files: `palefire.theme`, `paragraph--building-map.html.twig`,
`_building-map.scss` and `_landing-hero.scss`.

### Landing hero: new lead order, search, map typography, TOC Home and Map (2026-10-18)

**Lead** (`landing.html.twig`, `_landing-features.scss`), top to bottom:
1. The title.
2. The intro, full width (no longer beside the title).
3. A **Search Special Collections** form. It is a placeholder: there is
   no Special Collections search target yet, so it searches Bento like the
   old landing form. Change `landing_action` / `landing_name` in the hero
   block when there is one. On the hero it is not listed in the TOC.
4. The two exhibition tiles. They take whatever height is left (144px
   minimum).
5. **Next event | Latest blog post**, side by side from md, each with its
   label above it, left-aligned (the label column is gone). With no event,
   the post takes the first column.

**Fit:** the lead ends at 743px of a 767px window at 1333×767, 740 of 768
at 1024×768, and 828 of 900 at 1440×900.

**Map summary** (`palefire.theme`, `paragraph--building-map.html.twig`):
- The building's own description (its Building spaces term) never showed:
  the paragraph's intro replaced it.
- Both now show, in a block under the heading (`.pf-map__type`):
  - **summary:** the building's description;
  - **note:** the paragraph's intro, the "open to everyone / by
    appointment" text.
- With only one of them, that one is the summary.
- The intro used to sit in the aside above the legend. It doesn't any
  more.
- In every layout the block has its own row under the heading, added only
  when there is text.

**Typography in the drawing** (`_landing-hero.scss`): Hill Memorial, in the
hero, with the map 64rem wide or more and no event card in the corner.
- The text is set into the drawing's empty top-left corner, on the grid
  sketched for it:
  - **row 1:** the summary at display size, 0–50% across;
  - **row 2:** the note, 0–30% across.
- Placed in cqi, measured from the drawing. Seminar Space starts 65%
  across, and Oral History about 31cqi down.
- Checked at 1333×767 with a test summary injected: the summary covers
  4–20% of the drawing's height, the note 23–35%. A hit test over both
  boxes found no room shapes under the text.
- Narrower, the block stays under the heading.

**TOC "Home" and "Map"** (`PalefireHooks::heroTocIds()`):
- The landing that opens the page has no section title, so
  palefire_modular left it out of the TOC, along with the map inside it.
- The TOC preprocess now puts two links at the top:
  - **Home** → `#home`, the lead panel;
  - **Map** → `#map`, the map's wrapper.
- The landing prints the same ids. A landing with its own section title
  gets no Home, and a section already anchored `home` or `map` keeps that
  anchor.
- `modular-toc.js`:
  - When targets are nested, the innermost one under the activation line
    wins, so Map takes over from the section around it.
  - The section change reports the `.pf-section` that holds the target,
    and its real position on the page, so the dark/light chrome still
    follows the section's theme.

**To do in the UI:** if the Hill Memorial Library term (Taxonomy →
Building spaces) has no description, add one. That is the summary.
Without it, the access note becomes the summary.

Not checked:
- the PHP, which is unlinted (no PHP on this machine);
- the TOC changes at run time (the live TOC script was already running).
  The new links were checked in the DOM only.

Production: `git pull`, then `drush cr`.

### Landing hero lead fits a laptop screen (2026-10-18)

On a 1.5× laptop display (1333×767 CSS px) the new lead was 660px tall
and ran 129px past the fold, so the map snap stopped short. It now ends
at 702px.

- **Title:** 32px to 48px (it was up to 64px). This applies to every
  landing's title.
- **Space above the panel:** 2rem under the breadcrumb instead of 5rem,
  on this hero only. The section's `--pf-section-pad-block` is 2rem, so
  the space under the Visit strip is 2rem too.
- **Title and intro:** the columns are 2 : 3, so the intro wraps to four
  lines. The intro is 16px.
- **Blog and event rows:** 56px thumbnails, 18px titles, tighter spacing.
  - The post excerpt is one line from lg, two below.
  - Its clamp never applied before: a `display: block` rule outranked
    it. Fixed.
- **Exhibition tiles:** 144px minimum (was 176), and they still stretch
  to fill any spare height. The text is a little tighter, and the titles
  are 20px to 24px.
- **Panel padding:** tighter on lg and up.
- **Placeholder blog title:** no longer underlined like a link.

Measured on /v2/special with the new CSS injected:

| Window | Lead ends at | Fold |
|---|---|---|
| 1280×720 | 700 | 720 |
| 1333×767 | 702 | 767 |
| 1024×768 | 707 | 768 |
| 1440×900 | 828 | 900 |
| 1920×960 | 888 | 960 |
| 768×1024 | 978 | 1024 |

On taller windows, the exhibition tiles take the extra height. On phones
the lead stacks and scrolls (no snap below md), and nothing scrolls
sideways at 320px.

Files: `_landing-features.scss` and `_landing-swiss.scss`.

### Landing hero lead: blog post, two exhibitions, next event; smaller title (2026-10-18)

The hero's lead panel now holds three rows under the title. Built in
`landing.html.twig`, styled in `_landing-features.scss` (new).

- **Title:** 36px on phones to 64px from about 1440px (was up to 88px).
  This applies to every landing's title.
- **lg and up:** the title and the page intro sit side by side, and the
  rows run across both columns below them. Stacked, the intro alone took
  about 200px and pushed the lead to 821px in a 900px window; side by side
  the lead ends at the fold.
- **Row 1, latest blog post:** the newest published Article in the
  paragraph's **Blog category**, newest by Article Date. It shows the
  image (core `medium` style, decorative), the date and category, the
  title and two lines of About Text. If there is no About Text, it uses
  the body's summary or text.
  - It uses the Next event card's parts, as a ledger row: the label in
    its own column from md up.
  - It refreshes when articles change (`node_list:article`).
  - It is a placeholder row until the field below exists and is filled
    in. Code: `_palefire_hero_post()` and `_palefire_field_image()` in
    `palefire.theme`.
- **Row 2, two exhibitions:** these are **placeholders** for now, as data
  at the top of the Twig block (label, title, ISO start and end, the
  shown dates or `permanent`, and image URLs), ready for a real source.
  - Each tile shows its image edge to edge, with the text on a dark scrim
    that keeps white text at 4.5:1 even over a white image. Hatched tones
    stand in for images.
  - The permanent LSU History tile reads "On view · Permanent" after a
    gold rule, instead of dates.
  - A tile with several images crossfades through them, one every 7s
    (`js/exhibit-rotate.js`, library `palefire/exhibit-rotate`). It has a
    pause/play button (WCAG 2.2.2) and runs only while on screen and the
    tab is visible. It holds still on hover or focus, and is off under
    reduced motion.
  - Phones: the tiles stack at 4:3.
- **Row 3:** Next event, as before.
- **Snap** (`landing-snap.js`): on a window short enough that the lead
  runs past the fold, the page now scrolls freely until the lead's end is
  on screen. Before, a step down would have skipped the rest of it.

**To do in the UI (config is by hand):** add the Blog category field.
1. Structure → Paragraph types → **Landing hero** → Manage fields →
   Create a new field.
2. Reference → **Taxonomy term**. Label "Blog category", machine name
   `landing_blog_category` (Drupal adds `field_`). Allowed number: 1.
   Reference type: the **Article Category** vocabulary.
3. Edit the Special Collections page, pick the category in the Landing
   hero, then save.

Checked on /v2/special with the new CSS and markup injected (the blog row
is the placeholder, since the field doesn't exist yet):
- **1440×900:** the lead is 661px and ends at 897px, at the fold.
- **768×1024:** the lead ends at 978px, and the exhibition tiles stretch
  to fill.
- **320px:** everything stacks, and nothing scrolls sideways (a 4px
  overflow of the 4:3 tiles was fixed).
- **Crossfade and pause:** checked with a short-interval stand-in for
  `exhibit-rotate.js`.

Not checked: the real blog query (it needs the field), `exhibit-rotate.js`
itself, and the PHP lint (no PHP on this machine). Production: `git pull`,
then `drush cr`.

### Landing snap: no more stall on the way to the map (2026-10-18)

The glide from the lead to the map stuck for about .2s, about 130px
down, then set off again.

- **Cause:** the header turns compact at that point and shrinks from 87
  to 49px over about .2s. That moves the map's stop point.
  `landing-snap.js` followed the moving point by calling the smooth
  scroll again on every frame of the header's transition (12 calls,
  recorded on /v2/special). Each call restarts the glide from rest, so
  the page stood still until the header settled.
- **Fix:** the glide now aims under the compact header from the start, so
  it is one smooth scroll with no retargeting.
  - `palefire.js` exposes the compact height it already measures on a
    hidden copy of the header, as `Drupal.palefireCompactHeaderHeight()`.
  - If the page moved under the glide (a late font or image), there is
    one correction after it ends, never during it.
  - The fallback timer for browsers without `scrollend` (now 1.5s) never
    corrects, so it can't restart a glide that is still running.
- **Checked** on /v2/special, with the new code injected over the live
  script, at 1440×900: down is one call to 811 (was 12), with no frame
  where the page stands still. It ends with the map heading at the same
  spot, under the compact header. Up is one call to 0.
- **Not confirmed:** one recording on the way up showed a single 130ms
  frame gap, which didn't come back in later runs. The browser pane was
  hidden, which throttles frames, so frame timing there isn't reliable.

Files: `js/landing-snap.js` and `js/palefire.js`.

### Visit strip rework; TOC hide/show button is back; landing head lines up (2026-10-18)

**Visit strip** (`landing.html.twig`, `_visit.scss`):
- **Open in Google Maps** sits under the mini map (`.pf-visit__place`),
  not in the first column.
- **Instagram placeholder** fills the first column under the name: an
  empty frame in the mini map's hatch, with Font Awesome Pro's Instagram
  icon (inlined from the site's copy, like the map's hint icon) and an
  INSTAGRAM label. It is `aria-hidden` until a real post embed replaces
  it. On phones it comes last.
  - md: name, hours and Instagram | map and link.
  - 1200px and up: name and Instagram | hours | map and link.
- **Frosted glass:** in dark contexts (dark sections, the map's dark turn,
  dark reading colours) the strip's glass is 70% of the sidebar's
  (Twilight .8 → .56), so the video shows through. Even over a white
  frame, the muted text stays 4.67:1.
  - Light glass stays at .75: any less and dark text over the map's dark
    overlay drops under 4.5:1.
  - "Reduce transparency" and the Settings drawer's solid switches keep
    it solid.

**TOC hide/show button** (`page.html.twig`,
`js/modular-toc-collapse.js`, `_modular.scss`): restored from `5865c10`
for every Modular page but guides (the TOC is required there, so CSS hides
the button). It replaces the landing pages' scroll-driven collapse (the
`pf-toc-in` code is gone from `modular-toc.js` and `_landing-hero.scss`).
- **Default:** landing pages start hidden, so the hero runs full width.
  Other pages start shown.
- **Saved choice:** kept in `localStorage` per page type
  (`palefire.tocCollapsed.landing` for landing pages,
  `palefire.tocCollapsed` for the rest).
- **Load:** the library loads in `<head>`, so the state is set before
  the first paint: no flash and no layout shift. Swup visits apply the
  new page's state without the slide.
- **Look:** open, it is a square chevron at the top right of the sidebar.
  Closed, it is a glass list button on the breadcrumb's line, 1rem from
  the page edge.
- **Accessibility:** the button's name switches between "Hide table of
  contents" and "Show table of contents", with `aria-expanded` set to
  match. It has a gold focus ring.
- **Motion:** with reduced motion it doesn't slide.

**Landing head** (`_landing-swiss.scss`, `landing-video.js`): the
breadcrumb's glass strip and the video button reached .75rem past the
content edge. Their outer edges are now on the lead panel's edges, with
the TOC open or closed.

Checked on /v2/special with the new rules and markup injected:
- Breadcrumb and video button edges match the panel at 1440 (76 / 1356,
  and 360 / 1372 with the TOC open), 1024 and 768.
- The toggle opens, closes and saves its choice.
- The Visit grid is correct at 1440, 1024, 768 and 320, and nothing
  scrolls sideways at any width.

Not checked: a non-landing, non-guide page (the default there is shown)
and a guide (no button). Production: `git pull`, then `drush cr` (new
library and template changes).

### Events carousel: images fill their frame (2026-10-18)

The featured image's frame is 3:2, but the image kept its own height. A
wider photo (the first event, 640×392) left a 34px strip of the frame's
grey under it. A taller one (the Hill-O-Ween poster) ran past the frame's
foot and was cut there. Images now fill the frame (`object-fit: cover`),
cropped from the top so posters keep their headline. Checked on
/v2/special at 1440px: both images are exactly 420px tall in their 420px
frames.

File: `_events-carousel.scss`.

### Landing: lead fills the first screen, the page snaps to the map (2026-10-18)

From md (768px) up, a landing with a lead panel and a building map
(`.pf-landing--lead-screen`) opens on the lead alone. The map starts
exactly at the fold, and one scroll step takes the page to it.

- **Lead height:** the screen minus the header, breadcrumb, section
  padding and the gap to the map (`--pf-landing-gap`, the map's top
  margin, now set here). It is a `min-height`, so long text still grows
  the panel. The title stays at the top and the last block (Next event,
  search) moves to the bottom, like a poster.
- **Snap:** down from the lead (wheel, PageDown, Space, ArrowDown) glides
  to the map, which stops 1.5–2.5rem under the header. Up from the map's
  top glides back to the top of the page. Scrolling inside the map stays
  free. `js/landing-snap.js` (library `palefire/landing-snap`), attached
  only by this landing.
- **Why JS:** CSS snapping alone still traps the page. Tested again in
  Chromium: with mandatory snapping, every wheel step goes back to the top.
  With proximity, a step stops 85px down and never reaches the map. The
  map keeps a CSS snap point, which catches scrollbar drags.
- The header turns compact on the way down, which moves the snap point by
  38px, so the script follows the new point instead of stopping short.
  Wheel events during the glide, including trackpad inertia, are
  swallowed, so one gesture moves one step.
- **Left to the browser:** touch, below md, a lead taller than the window,
  and scrolling inside drawers, menus or form fields. The glide is
  instant under reduced motion (the OS setting or Settings → Reduce
  motion).

Tested on /v2/special (the new CSS and JS injected) at 1440×900, 1024×768
and 768×1024, where the map starts at the fold. 320px is unchanged, and
nothing scrolls sideways at any width.

Files: `_landing-swiss.scss`, `landing.html.twig`, `js/landing-snap.js`
and `palefire.libraries.yml`. Production: `git pull`, then `drush cr` (new
library).

### Tile showcase: cards animate in on scroll, as on the concept page (2026-10-18)

The Tile showcase cards now animate in the way the Origami concept page's
cards do. The concept uses ScrollReveal in Origami's `custom.js`; the
values here are copied from it:

| Layout | Motion |
| --- | --- |
| Staggered row | odd cards rise from 50px below, even cards drop from 50px above; 100ms after entering, 50ms apart |
| Grid | cards rise from 30px below, 50ms apart |

- **Timing:** all cards fade from opacity 0 over 500ms, ease-out.
- **Replays:** as on the concept (`reset: true`), a card that scrolls out of
  view hides again and replays when it comes back. In the swipeable row on
  phones, cards reveal as they're swiped in.

**How:** `js/tile-reveal.js` (library `palefire/tile-reveal`, attached by
the paragraph template) instead of ScrollReveal. It sets up one
IntersectionObserver per card list, and cards that enter together are
staggered in source order. The `<li>` is watched while the card inside it
(`.pf-tile`) moves, so a card at the screen edge can't move itself out of
view and flicker. Transforms don't count toward layout shift, so CLS is
unaffected.

**Accessibility:**

- With the OS "reduce motion" setting or Settings > Reduce motion, nothing
  is hidden and nothing moves. Turning Reduce motion on mid-visit shows any
  card the script had hidden.
- Without JavaScript the cards are simply shown: the hidden state needs
  `.pf-tiles--reveal`, which only the script adds.
- A card that receives keyboard focus is shown at once.

Files: `js/tile-reveal.js`, `palefire.libraries.yml`,
`templates/paragraphs/paragraph--tile-showcase.html.twig`,
`scss/components/_tile-showcase.scss` and `css/style.css`.

### Landing hero: "Next event" follows the carousel's calendars and libraries (2026-10-18)

The "Next event" card (`hero-event.js`) read the carousel's feed with no
query. So it always showed the default calendar for every library, even
when the page's events carousel was set to, say, Events and Hill Memorial
Library only. It now sends the same `calendar` and `campus` the carousel
sends, read from the first carousel's `data-calendar` and `data-campus`.
The card is therefore always an event the carousel also lists.

- **The pick:** the first timed event that hasn't ended. All-day items are
  skipped with the carousel's own all-day test (start at or before 0.01h,
  end at or after 23.9h), so a segment that starts a few seconds after
  midnight no longer counts as timed.
- **"Show only event days"** needs nothing extra: the card already skipped
  all-day items.
- **Requirements:** the carousel's Calendars and Libraries options
  (palefire_modular `field_carousel_calendars` and
  `field_carousel_campuses`, libcal_gantt 2026-10-18).

Files: `js/hero-event.js`.

### Tile showcase: Tiles as image cards (2026-10-18)

The How-to guides, Collections and Specializations sections from the
Origami concept (`/concept/special`) as one paragraph type,
`tile_showcase`. The concept was hand-coded HTML, with each card's image
set in CSS per class, and it only worked on xxl screens. Here every card is
a referenced Tile node, and the layout follows the section's own width.

**Fields** (besides the shared section fields: title, anchor, snap,
height, theme, hide from TOC, background):

| Field | Type | Purpose |
| --- | --- | --- |
| `field_tile_layout` | List: `stagger`, `grid` | Staggered row (How-to guides, Specializations) or grid (Collections) |
| `field_tiles` | Entity reference → Tile nodes, unlimited | The cards, in this order |

**Each card, from the Tile:**

- the title, as an `<h3>` (the section title is the `<h2>`);
- the Tile Cover (`field_tile_cover`), cropped from the centre through the
  `tile_showcase_portrait` or `tile_showcase_landscape` responsive image
  style;
- the Category terms (`field_category`, up to 3), joined with " + " as the
  label pill, e.g. "Dept + Medium";
- a link to the Tile node's own URL.

Tiles the visitor can't see (unpublished, no access) are left out. The
image's alt is empty because the card's title already names the link.

**Layouts.** Both use container queries on the card list, so they respond
to the space beside the "On this page" sidebar, not the window:

- **Staggered row:**
  - Narrower than 60rem: a swipeable row (scroll-snap) that bleeds to the
    section's edges, with the next card peeking in. Tab moves through the
    cards and scrolls each one into view.
  - 60rem and wider: one row on a repeating five-step offset, close to the
    concept's hand-placed one, so any number of cards keeps the rhythm.
- **Grid:** 1 column, then 2 from 32rem, then 3 from 60rem, edge to edge as
  in the concept.

**"On this page":** each card is listed under its section (as in the
concept). The `<li>` is the TOC target (`data-toc`), not the `<h3>`, so
modular-toc.js never puts `tabindex` on a heading inside the link.

**Accessibility:**

- A scrim that is at least 85% black under the text keeps white titles
  above 4.5:1 on any image, even a white one.
- The label pill is white on black and turns gold on hover and focus.
- Focus is a 3px gold ring with a black halo inside it, so it shows on
  light and dark images alike.
- The title comes first in the source, so the link reads
  "Oral History, Dept + Medium". CSS shows the label above the title.
- The list keeps `role="list"` for Safari.
- Motion (the image zoom on hover) only runs under
  `prefers-reduced-motion: no-preference`.
- Forced-colours mode drops the scrim and draws a system border.

**Images and Lighthouse:** each card loads one `srcset` image with a
`sizes` attribute, lazily and asynchronously. The image styles convert to
WebP when the server's image toolkit supports it (the script says when it
doesn't). If Responsive Image or the style is missing, the card falls back
to `<style>_md`, then to the original file.

#### Turning it on (production)

```sh
git pull
drush php:script themes/custom/palefire/scripts/palefire_tile_showcase_setup.php
drush cr
```

Then edit the page, add three **Tile showcase** sections, and fill each one:

| Section title | Layout | Tiles |
| --- | --- | --- |
| How-to guides | Staggered row | View an item, Browse finding aids, Visitor parking, Donate materials |
| Collections | Grid | the six collection Tiles |
| Specializations | Staggered row | University Archives, Oral History, Rare books, State Documents, Digital Services |

For the concept's look, set each section's theme to Dark and add the
concept's background image. Make sure each Tile has a Tile Cover and its
Category terms set. Staging gets the config from production downstream as
usual.

Files: `palefire.theme` (`palefire_preprocess_paragraph__tile_showcase()`,
`_palefire_tile_cover()`), `templates/paragraphs/paragraph--tile-showcase.html.twig`,
`scss/components/_tile-showcase.scss`, `scss/style.scss`, `css/style.css`
and `scripts/palefire_tile_showcase_setup.php`.

### Soft hairlines (the guides' look) replace the Swiss layer's solid lines (2026-10-18)

The landing's Swiss layer drew its lines at the full 3:1 line colour, so
the Visit strip's hours read as solid rules. They now use the soft
hairline the guides use. One shared token, `$pf-hairline`
(`_variables.scss`), is 20% of the section's line colour, the strength
the hero already used, so it follows light and dark sections and every
reading colour.

- **Now soft:**
  - the Visit strip's frame, hours rows and mini-map edge (a new
    `--pf-visit-rule`; the status dot and map stripes keep the line);
  - the lead panel's border and the rule over the search.
- **Unchanged:**
  - the search field keeps its 3:1 border, and so does anything whose
    border identifies a control (WCAG 1.4.11);
  - the events carousel already draws its rules in the reading colour's
    soft rule tone, so it is left as it is.
- **The hero:** its own 20% override is gone, because the default is now
  the same.
- **High contrast:** Settings > High contrast sets
  `--pf-hairline-strength` to 100% on every page, so these lines go back
  to full strength.

Files: `_variables.scss`, `_base.scss`, `_a11y-settings.scss`,
`_visit.scss`, `_landing-swiss.scss` and `_landing-hero.scss`.

### Breadcrumb caps centred; Search pairs with Menu on phones (2026-10-18)

- **Breadcrumb:** the caps sat 1px above the centre of the glass strip.
  The 2px under the "Home" link for its gold underline pushed the line
  up, and the current page copied it. The link now takes that room back
  with a negative margin, so the underline hangs into the strip's
  padding, and the current page's 2px is gone. Measured: the caps' centre
  is the strip's centre.
- **Search on phones and tablets (below lg):** the bare, thin magnifier
  read as unfinished next to the two-line "Menu" button. It is now drawn
  in the menu lines' 2px weight (CSS, replacing the icon-font glyph), with
  the menu's spacing and colours. From 25rem (400px) it carries the word
  SEARCH in the button's type. Narrower, there is no room beside the
  logo, so the word stays visually hidden; it is the link's name either
  way. Desktop keeps its icon and tooltip.

Files: `_layout.scss` and `_header.scss`.

### Building map on phones: marks grow with the map; entrance arrow; hint icon (2026-10-18)

- **Grow with the map:** the room numbers, restroom and elevator icons,
  and FLOOR 2 / ENTRANCE used to stay a fixed size on phones. Now they grow
  with the drawing from 320 to 575px, part fixed and part in proportion:
  - numbers 20 → 31px;
  - restroom tile 18 → 28px;
  - elevator 15 → 23px;
  - type 9 → 11.5px.

  Growing in full proportion would have left a jump at 576px, where the
  desktop sizes take over; this leaves a small step.
- **Entrance arrow:** ENTRANCE used to be enlarged from the start of the
  word, which slid the arrow off the doorway and onto the wall lines. It
  now grows around the arrow's tip, so the arrow stays in the doorway and
  the word sits clear below the slab.
- **FLOOR 2:** it now sits under the railing, tracked a little tighter
  (.16em), so it clears the Floor 2 Gallery's number and the Quiet Study
  and McIlhenny Room corners.
- **Hint icon:** Font Awesome Pro's bullseye-pointer (solid) instead of a
  "1" in a circle, which read as one of the room numbers. It is inlined
  from the site's `/libraries/fontawesome-pro` copy with `source()`, like
  the utility menu's icons, so no FA CSS or font loads.

Files: `_building-map.scss` and `paragraph--building-map.html.twig`.

### "Bento" renamed to "landing" in the theme (2026-10-18)

"Bento" was a prototype name. The landing section's classes, variables,
files and JS now say "landing". The compiled CSS is the same as before
apart from the names. Bento search, with the search form, is retired;
its paragraph type still renders, through the same markup.

**Renamed:**
- **Classes:** `.pf-bento*` → `.pf-landing*`, `--pf-bento-*` →
  `--pf-landing-*`, `%pf-bento-label` → `%pf-landing-label`,
  `data-pf-bento` / `data-bento-video*` → `data-pf-landing` /
  `data-landing-video*`.
- **Files:**
  - `js/bento-video.js` → `js/landing-video.js` (library
    `palefire/landing-video`, behavior `palefireLandingVideo`);
  - `_bento-search.scss` → `_landing.scss`;
  - `_bento-swiss.scss` → `_landing-swiss.scss`.
- **Markup:** it now lives in `templates/includes/landing.html.twig`.
  Both `paragraph--landing-hero.html.twig` (with `hero`) and
  `paragraph--bento-search.html.twig` include it.
- **Template variables:** `bento_map`, `bento_intro` and
  `modular_title_in_bento` become `landing_map`, `landing_intro` and
  `modular_title_in_landing`.
- **PHP:**
  - `_palefire_bento_visit()` → `_palefire_landing_visit()`;
  - `PalefireHooks::BENTO_BUNDLES` → `LANDING_BUNDLES`;
  - `titleBentoUuid()` → `titleLandingUuid()`.

**Unchanged on purpose:**
- **Drupal machine names** (config is edited in the UI only):
  - the `bento_search` paragraph type;
  - `field_bento_map` and `field_bento_hide_lead`;
  - the `palefire_preprocess_paragraph__bento_search()` hook and its
    template filename.
- **The real search:** `https://lib.lsu.edu/bento` and
  `search_api_fulltext_bento`.
- **Elsewhere:** Origami file names in comments, `scripts/`, this
  changelog, and the uncompiled prototype in `components/_modular.scss`.

The palefire_modular module still passes the search settings as `bento`.
The bento_search preprocess copies them into `landing`.

### Landing hero: breadcrumb strip and video button line up (2026-10-18)

When the hero has a video, the breadcrumb sits on a glass strip with the
video's pause button on the same line. They didn't line up:
- **Height:** the strip was 32px and the button 36px, so neither the
  tops nor the bottoms matched. The strip is now the button's height
  (2.25rem), and the button is centred on the whole strip rather than on
  its first item, without rounding.
- **Sides:** the strip reaches .75rem past the content edge on the left,
  and the button now does the same on the right, at every width.
  `bento-video.js` measures this from the breadcrumb's own gutter, which
  differs from the section's on wide screens. This also works in RTL.
- **Text:** "Special Collections" sat 1px below "Home". The link keeps
  2px under it for the gold underline, and the current page now keeps the
  same.

Files: `_bento-swiss.scss`, `_bento-search.scss`, `_layout.scss` and
`bento-video.js`.

### Building map on phones: icons, FLOOR 2 and ENTRANCE stay readable (2026-10-18)

On phones only the room names hide now, since the numbers stand in for
them. Before, "Floor 2" and "Entrance" hid with them, and the restroom and
elevator icons shrank with the drawing to about 9px. These now keep a
fixed size, the same way the numbers do:
- type of about 10px;
- a 20px restroom tile;
- a 17px elevator.

FLOOR 2 also moves a little along the railing so it clears the Floor 2
Gallery's number. From 576px up, nothing changes. File:
`_building-map.scss`.

### Building map on phones: numbered rooms and a tap hint (2026-10-18)

On phones (under 576px) the room names are hidden, so the drawing looked
blank and gave no clue what to tap. Now:
- **Numbered rooms:** each space in the list gets a number, in list order,
  and the same number sits on its room in the drawing, centred where the
  name would be. The markers stay 22px whatever the drawing's size. They
  pop in one after another once the rooms have drawn. A tapped or hovered
  space turns its number gold in both places.
- **Hint** under the drawing: "Tap a numbered room, or choose a space from
  the list." It is in the markup and takes its space from the start, so
  nothing moves when the script runs.
- Numbers are `aria-hidden`. The list entries and rooms keep their names
  for screen readers.
- From 576px up, nothing changes.
- Reduced motion shows the numbers without the pop.

Files: `building-map.js` (numbers), `_building-map.scss` (styles) and
`paragraph--building-map.html.twig` (hint).

### Hill Memorial map: Galleries and Reading Room are parallelograms (2026-10-18)

The hatched areas of three rooms had bits sticking out of their shapes.
They now follow the drawing's own corners, and the cut-off bits are plain
floor, like the Lobby:
- **Reading Room:** a thin strip ran left along the back wall. It is gone.
- **Floor 2 Gallery:** the hatch spilled past the left end of the railing
  into a wedge against Quiet Study, plus a sliver down the slab edge. It
  now stops square at the railing. Two hatch-mask cuts that only bounded
  the old wedge are removed.
- **Floor 1 Gallery:** its front corner ran past the floor's corner into
  the Lobby as a point. It now ends at that corner and at the back-wall
  corner.

File: `templates/includes/hill-memorial-map.svg.twig` (each room's hatch
and hit area). Labels, outlines and other rooms are unchanged.

### Building map: a tapped legend space stays lit after the scroll (2026-10-18)

A fix to the next entry, found by testing the real files in a browser.
Tapping a space in the legend scrolled the drawing into view, but the room
and its tooltip were lost:
- **The draw-on trace replayed.** The map comes back into view and
  redraws, which hid the rooms for about 1.5s. The drawing now arrives
  already drawn when the legend scrolls to it.
- **Hover closed the tooltip.** With a mouse (a narrow desktop window),
  the scroll slides other rooms and list entries under the pointer, and
  their hover took over or closed it. The tapped space now ignores hover
  in and out until a tap elsewhere, Escape, or a second tap.

File: `building-map.js`.

### Landing TOC hides on desktop only; building map fits phones (2026-10-18)

- **TOC:** the landing-page collapse over the hero and the events carousel
  now happens only from lg up, where closing the column gives the map the
  full width. Below lg the TOC is just the floating list button, which
  covers nothing, so it now shows from the top of the page and the drawer
  is always one tap away. (`_landing-hero.scss`)
- **Building map on phones (under 576px):**
  - The whole drawing fits the width. It used to be 40rem wide and scroll
    sideways, so a 320px screen showed half of it.
  - The room names inside the drawing are hidden at this size, because
    they would be about 4px. The legend lists every space, and the names
    were already `aria-hidden`.
  - The legend ("Legend & spaces") starts open on phones, as the main way
    in.
  - Tapping a space in the legend scrolls the drawing into view if it is
    off-screen, so the room lighting up and its tooltip can be seen. This
    happens in any stacked layout. It is instant with reduced motion, and
    a keyboard press doesn't scroll, so focus stays on the list.
  - Files: `_building-map.scss` and `building-map.js`.

### Landing pages: the TOC collapses instead of fading (2026-10-17)

On landing pages the TOC no longer just turns transparent over the hero
and the events carousel. From lg up it collapses the way the old hide/show
button did (removed in `5865c10`), but driven by scroll instead of a button:
- the column closes, so the hero and backgrounds run edge to edge;
- the sidebar slides out to the left (to the right in RTL), over .3s
  ease-in-out;
- its links leave the tab order once it has gone.

It opens again at the first other section. Opening narrows the content, so
the sections above get taller and push that section down. To stop the TOC
flipping back and forth, it only collapses again once that section is 20%
of the screen back above the line (`REVEAL_SLACK` in `modular-toc.js`).
Reduced motion switches it without animating. Files: `_landing-hero.scss`
and `modular-toc.js`.

### Landing pages: TOC waits past the hero; hero borders at 20% (2026-10-17)

- **TOC on landing pages** (`field_modular_type` = `landing`, so
  `<html data-pf-mode="landing">`): the table of contents stays out of
  sight over the landing hero and the events carousel. It fades in once
  the first other section reaches the activation line, and fades out again
  if you scroll back above it.
  - `modular-toc.js` sets `html.pf-toc-in`, and the styles are in
    `_landing-hero.scss`.
  - From lg up, the sidebar's column stays where it is, so nothing shifts,
    and the video shows through it.
  - Below lg, the list button that opens the TOC drawer waits the same way.
  - With reduced motion it switches without fading.
  - A landing page with no other section never shows it.
  - Other page types are unchanged.
- **Hero borders at 20%** of the line colour: the lead panel, the hairline
  over the next event, the event card and its thumbnail, the Visit strip
  and its mini map. The thumbnail stripes and the map drawing keep their
  own strength.

### Landing hero: "Next event" card is one link, edge-to-edge thumbnail (2026-10-17)

- **One link:** the whole card is the link to the event, not just the
  title (`hero-event.js` builds it as an `<a>`). Hovering or focusing
  anywhere on it brightens and underlines the title. On its own (no lead
  panel), the frame also brightens.
- **On its own (no lead panel):**
  - The thumbnail fills the frame's top, left and bottom edges, with a
    hairline between it and the text. It's square unless the text runs
    taller, then it grows with the card.
  - The thumbnail is a little larger here (`5.5rem` to `8rem`).
  - The card's background is clear, with a 10px blur of what's behind it.

Files: `_landing-hero.scss`.

### Landing hero: tighter "Next event" box, softer stripes (2026-10-17)

- When the card stands on its own (no lead panel), the "Next event" label
  now sits above the box on the page. Only the card itself is on glass,
  with less padding, so the box holds just the thumbnail and text.
- The thumbnail placeholder uses the Visit card's "Show map" stripes: thin
  lines 12px apart at 18% of the line colour. The old lines were 7px apart
  at full strength. The loading placeholder uses them too.

Files: `_landing-hero.scss`.

### Landing hero: "Next event" card over the map drawing (2026-10-17)

When the map is Hill Memorial (`field_map_building` = `hill_memorial`,
class `.pf-map--hill-memorial`) and its box is at least 48rem wide, the
card now lies over the drawing's empty top-left corner instead of pushing
the drawing down. Hill's outline leaves that corner clear. Other buildings
may not, so with them the card keeps its own row above the drawing.

- The card shares the drawing's grid cell and sits above it, so its link
  stays clickable.
- Its width is `min(36rem, 46%)` of the map, and it starts a little below
  the drawing's top edge.
- On narrower screens the drawing is wider than the screen and scrolls
  sideways, so the card keeps its own row above it.

Files: `_landing-hero.scss`, `@container pf-map`.

### Landing hero: "Next event" card under the map heading (2026-10-17)

When the hero has no lead panel but has a building map, the card now sits
inside the map: under its heading (for example "Hill Memorial Library")
and above the drawing. `hero-event.js` moves the card there before the
feed loads, so its space is held in that spot. The hero's map grid then
gains an "event" row (`head / event / stage / aside`), only while the card
is present (`_landing-hero.scss`). A hero without a map still shows the
card on its own above the Visit strip.

### Landing hero: "Next event" card without a lead panel (2026-10-17)

The card was printed only inside the lead panel. A hero with no lead panel
(no page title or intro, or "Hide lead panel" checked) therefore showed no
card, and its script never loaded. That was the case on /v2/special. Now
the card also shows in that case, on its own glass panel above the map
(`.pf-hero-event--solo`), with print and Windows High Contrast handled
like the lead panel. The template builds the card once (`hero_event`) and
prints it in whichever place applies.

### Landing hero: "Next event" card (2026-10-17)

The landing hero's lead panel now shows the next event under the intro,
across a hairline.

- **The card:** a square thumbnail on the left. Events without an image get
  a diagonal-hatch placeholder in the map's style. Next to it are the day
  and time ("Mon, Oct 19 · 11:30 AM–12:30 PM", or "Today" / "Tomorrow"),
  then the title, then a short line with the category and place, such as
  "Workshop · Hill Memorial: Lecture Hall" or "Workshop · Online".
- **Link:** the whole card links to the event on LibCal.
- **Data:** `js/hero-event.js` (library `palefire/hero-event`, attached
  only by the landing hero) reads the events carousel's own feed
  (`drupalSettings.libcalGanttCarousel.endpoint`). It picks the first timed
  event that hasn't ended and skips all-day items such as donation bins.
  - The card only appears on pages that also have the events carousel.
  - Its space is held while the feed loads, so nothing jumps.
  - With no upcoming event, or if the request fails, the card is removed.
  - The feed has no event descriptions, so the short line is built from
    the category and the location.
- **Styles:** `_landing-hero.scss` (`.pf-hero-event`). No config.

### Landing hero: an 80% wash of the reading colour (2026-10-17)

The hero's video now gets a wash of 80% of the reading swatch's page
colour (`$pf-hero-media-wash`), for example a deep purple on Twilight. The
wash stays the same while the map is in view or leading (first set at 30%,
raised to 80% the same day). Bento search keeps 60%, and 90% at the map. The hero sets the two
wash tokens (`--pf-media-wash`, `--pf-media-wash-map`) that every bento
wash reads, so High contrast still puts its heavy wash on top
(`_landing-hero.scss`).

### Landing hero paragraph (2026-10-17)

A new paragraph type, `landing_hero`, for pages that want the bento's
opening without a search box.

- **What it shows:** the background video, a lead panel with the page
  `<h1>` (when it's the first section) and the page intro, the building
  map, and the Visit strip.
- **How it differs from `bento_search`:**
  - No search form. The lead panel is left out when it would be empty,
    and editors can hide it with "Hide lead panel" when there's a map.
  - The video has no wash and isn't darkened when the map scrolls into
    view.
  - The map's legend always sits under the drawing, as the bento's map
    does on narrow screens.
- **Setup (once, on production):** run
  `drush php:script themes/custom/palefire/scripts/palefire_landing_hero_setup.php`,
  then `drush cr`.
  - The script creates the type and gives it the bento's existing fields:
    `field_bg_videos`, `field_bg_poster`, `field_bento_map` and
    `field_bento_hide_lead`, with the same widgets and settings.
  - It allows `landing_hero` wherever `bento_search` is allowed. It's safe
    to re-run.
- **Code:**
  - `paragraph--landing-hero.html.twig` includes the bento template with
    `hero: true`, so the two can't drift apart.
  - `palefire_preprocess_paragraph__landing_hero()` reuses the bento's
    preprocess.
  - `PalefireHooks::BENTO_BUNDLES` lets the hero print the page title and
    get its background videos.
  - Styles are in `_landing-hero.scss` (`.pf-bento--hero`).

### Reading colours: "More colours" as a tile; mobile menu without end rules (2026-10-17)

- **"More colours" is a tile:** it sits in the fourth cell, styled like
  the swatches, with "+" in its chip where they show "Aa". While the list
  is open it reads "−" and "Fewer colours" and sits after the last swatch.
  - `initThemesMore` moves the button into the grid. Without JavaScript
    the button stays hidden and every swatch shows.
  - Material replaces Dusk in the first three. The order is now Twilight,
    Material, Cocoa, then Forest, Dusk, Night, Midnight and Solarized,
    then the lights.
  - The same applies to the Settings drawer and the mobile menu.
- **Mobile and tablet menu:** hairlines now run only between the sections,
  so there's no rule above Help With and none below About (`_header.scss`).

### Tiles: one border for hover and selected (2026-10-17)

Hovering or choosing a reading colour, or hovering a mobile quick link,
used to draw a second ring outside the chip. Now the chip's own hairline
changes instead:
- hover: it brightens;
- chosen colour: it becomes a 2px accent line. The extra thickness means
  colour isn't the only mark, and the name also turns full-strength.

Windows High Contrast doesn't draw box shadows, so there the chosen chip
gets a 3px outline. Files: `_utility-panels.scss` and `_header.scss`.

### Mobile menu: quick links styled like the swatches (2026-10-17)

The quick-link tiles at the top of the mobile menu (Hours, Ask Us,
Accounts, Settings) now match the reading colour tiles below them:
- four in a row, with no box;
- the icon centred in a hairline chip where a swatch shows "Aa";
- the name under it in small tracked caps.

Hover and focus brighten the name and add the same faint ring around the
chip. CSS only (`_header.scss`).

### Mobile menu: text size above the reading colours (2026-10-17)

Below lg, the menu drawer now offers the four Settings text sizes above the
reading colours. Both are copies of the Settings tiles, made by
`initMenuSetting` (`js/utility-panels.js`, which replaces `initMenuThemes`)
for each `data-pf-menu-setting` fieldset in `page.html.twig` ("size" or
"theme"). Picking a tile ticks the matching Settings radio, and the
Settings form applies and saves the choice. `palefire:a11ychange` keeps
the copies in step. No CSS rebuild, no config.

### Settings: reading colours reordered, "More colours", quieter tiles (2026-10-17)

- **Order:** Twilight, Dusk, Cocoa and Forest come first, then the other
  darks (Night, Midnight, Solarized, Material), then the lights (Cream,
  Soft cream, Lavender, Sepia, Sage). This applies to the Settings drawer
  and the copy under the mobile menu (`utility-panels.html.twig`, `THEMES`
  in `utility-panels.js`).
- **More colours:** only the first four show. A "More colours" button
  (`aria-expanded`) opens the rest, which fade in one after another, the
  same as the accordions; with reduced motion they appear at once. The
  chosen colour always stays visible, even when it's one of the hidden
  ones. Without JS the button stays hidden and every swatch shows
  (`initThemesMore`).
- **Quieter selected state:** the boxes around every tile are gone.
  - Text sizes sit on one hairline, and the chosen size gets a 2px accent
    bar there.
  - The chosen colour gets an accent ring around its chip, and its name
    turns full-strength.
  - Hover only brightens the text or adds a faint ring.
  - Windows High Contrast keeps a 3px bar or ring.

### Mobile "On this page" drawer backdrop (2026-10-17)

Below lg the TOC opens as a Bootstrap offcanvas (`#pf-modular-sidebar`).
It used to keep Bootstrap's black backdrop, and iOS Safari tints its bottom
toolbar from the fixed layer at the bottom of the screen (ignoring that
layer's opacity), so the toolbar turned solid black while the TOC was open.
`modular-toc.js` now sets `<html data-pf-panel="toc">` while the drawer is
open, so it gets the same page-colour wash as the menu and utility drawers
(`_utility-panels.scss`) and the toolbar matches the page. The flag is
cleared on close, and on the next attach if a Swup visit swapped the drawer
out while it was open. JS only: no CSS rebuild, no config.

### Calendar events in "On this page"

`modular-toc.js` lists any element with `data-toc` under its section, even
inside a `data-toc-skip` area, with optional `data-toc-label` (link text),
`data-toc-detail` (extra screen-reader text) and `data-toc-passive` (listed
but not highlighted by scroll position). A component that renders after
the TOC dispatches a bubbling `palefire:tocrefresh` event from inside its
section, and that section's sub-list is rebuilt and opens like a section
change. The LibCal day carousel uses this for its next three timed events.

### Events carousel: Swiss styling (2026-10-16)

`scss/components/_events-carousel.scss` restyles the LibCal Day Carousel
block in the International Typographic Style, for a museum feel. Theme CSS
only: no change to `libcal_gantt_carousel`. Colours still go through its
`--ldc-*` properties, and every selector starts with `.pf-events-carousel`
so it out-ranks the block's own CSS.

- **One edge.** The block's own inset (`--ldc-pad`) is 0, so the day tabs
  and the first column start on the "Events" title's edge. In the
  three-day view the columns reach out by their padding and the rules
  between them sit in the gutter.
- **Strip, not pills.** Day tabs are a small monospace label over a large
  light numeral, muted until in view; the days in view get a 3px accent
  bar on the toolbar's hairline. The row never wraps: it scrolls sideways
  inside the toolbar on phones and tablets.
- **Square controls.** Previous/next are 48px squares sharing one hairline,
  with a drawn arrow in the text colour; they fill solid on hover/focus.
  This also fixes empty buttons on dark sections: Bootstrap's
  `[data-bs-theme=dark]` carousel filter inverted the white chevron to
  black.
- **Type and rules.** Medium date, light weather numerals, a ruled hours
  table and a ruled event list, with times as tracked monospace labels.
  Square-cornered 3:2 feature image. Event titles keep an underline (WCAG
  1.4.1) on a hairline in the border colour; it turns accent on hover/focus.
- **Accent.** LSU purple on light sections (11:1), the TOC gold on dark.
- **Title.** The Events section's `<h2>` is display size (up to 4rem),
  medium weight, tight tracking.
- **"On this day" (follow-up).** `libcal_gantt_carousel` now shows a
  Wikipedia anniversary on days with no timed events (see its README) and
  no longer features all-day events' images. Palefire styles the fact
  (`.libcal-day__fact`) as a wall caption: a 3px accent rule, a tracked
  monospace label, the text large and light, the "Wikipedia" source link as
  a label with an accent underline. Also the thin stroke for the module's
  newer inline-SVG chevrons (`.libcal-day-carousel__chevron`).
- **"On this day" toned down (later follow-up).** The fact is a fun add-on,
  not the day's programme, so the module now puts it last in the column,
  after the events, and Palefire styles it as a footnote instead of a wall
  caption: no accent rule, 0.875rem muted text (`--ldc-muted`, about 9:1 on
  the dark section), a smaller tracked label and a muted "Wikipedia" link
  that brightens on hover. Needs the matching `libcal_gantt` change (fact
  last in `buildSlide()`).

## Guide pages (LibGuides-style)

Added 2026-10-16. A Modular page with **Type = guide**
(`field_modular_type`) can hold a whole research guide on one page: every
LibGuide tab becomes a section, every box a block. "On this page" lists
the sections and, in guide mode, each block heading (h3) under them, so it
replaces the LibGuide tab menu. The first guide is the Systematic Reviews
LibGuide (`scripts/palefire_guide_demo.php`).

### Set up (production, once)

```bash
git pull
drush php:script themes/custom/palefire/scripts/palefire_guide_setup.php
drush cr
# Optional demo page (unpublished):
drush php:script themes/custom/palefire/scripts/palefire_guide_demo.php -- path=/v2/guides/systematic-reviews
# Optional "How to Request Research Help" page (unpublished):
drush php:script themes/custom/palefire/scripts/palefire_guide_research_help.php -- path=/v2/guides/research-help
# Optional "How to Use Special Collections" page (unpublished):
drush php:script themes/custom/palefire/scripts/palefire_guide_special_collections.php -- path=/v2/guides/special-collections
drush cr
```

The setup script only adds config, and it is safe to re-run. It finds the
Staff content type and the Subjects vocabulary by name. If it can't tell
which to use, it lists the candidates and leaves out the parts that need
them; re-run with `-- staff=<type> vocab=<vocabulary>`. It never changes
an existing view display, so later edits in Manage display stand.

After the script:

* **Permissions.** Give the editor roles that build guides "Resource:
  Create new content" and "Edit any content" (People > Permissions). The
  script doesn't touch roles.
* **Text format.** Guide text uses the WYSIWYG format, not Full HTML.
  Guide text uses tables, lists and Heading 4, so WYSIWYG must allow
  `<table>`, `<caption>`, `<th scope>`, `<h4>`, `<ol>`, `<ul>`, `<code>`
  and `<a href>` (CKEditor 5: Table, Heading, Code, Link). The guide
  scripts stop if there is no WYSIWYG format.
* **Guide card.** Check Staff > Manage display > Guide card. The script
  turns on the Staff fields that look like an email, phone, photo, job
  title, pronouns or office; add or remove fields there.

### Move existing guides to WYSIWYG

Guide pages built before 2026-10-20 have Full HTML text. To move all of
them (intro and every block, accordion items included) to WYSIWYG:

```bash
drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php
drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php -- apply=1
drush cr
```

The first run only lists what would change. With `apply=1` each changed
guide gets a new revision ("Guide text moved to the wysiwyg text format"),
so the Revisions tab can undo it. The script stops if WYSIWYG doesn't allow
the tags guides use (the list above), since the text would lose its tables
or headings on display; fix the format, or pass `force=1` to convert
anyway.

### Content model

`node.modular_page` (guide fields, all optional; printed in the page head,
hidden in the node's display):

| Field | Type | Used for |
| --- | --- | --- |
| `field_modular_type` | existing | `guide` turns on guide mode |
| `field_intro` | existing | The lede under the `<h1>` |
| `field_guide_owner` | Entity reference → Staff | "Guide by" link |
| `field_guide_subjects` | Entity reference → Subjects terms | Subject tags |
| `field_guide_source` | Link | The LibGuide it replaces (for redirects; not shown) |

**Resource** (`node.resource`). One link, database, book or tool, shared
by every guide that lists it, like a LibGuides asset: change a URL once and
every guide updates. Unpublish one to hide it everywhere. Its own page gets
`noindex` (`palefire_page_attachments_alter()`).

| Field | Type | Notes |
| --- | --- | --- |
| `title` | | Link text |
| `field_resource_link` | Link | The plain address |
| `field_resource_proxy` | Boolean | "Requires LSU login": adds the libezp prefix and an "LSU login" label (`PALEFIRE_PROXY_PREFIX`) |
| `field_resource_type` | List | Picks the icon (`PALEFIRE_RESOURCE_ICONS`, Bootstrap Icons in `images/bi/`) |
| `field_resource_citation` | Text | Short line under the title |
| `field_resource_desc` | Long text (plain) | One or two sentences |
| `field_resource_labels` | Text, up to 3 | Tags such as Free, Paid, Online, 30 min |

Paragraph types:

| Type | Fields | Template |
| --- | --- | --- |
| `guide_section` | palefire_modular's shared section fields (title, anchor, theme, TOC hide, background) + `field_guide_blocks` | `paragraph--guide-section.html.twig` |
| `guide_text` | `field_block_title`, `field_block_body`, `field_text_style` (plain / callout) | `paragraph--guide-text.html.twig` |
| `resource_list` | `field_block_title`, `field_block_intro`, `field_resources` (→ Resource) | `paragraph--resource-list.html.twig` |
| `guide_accordion` | `field_block_title`, `field_block_intro`, `field_accordion_items` | `paragraph--guide-accordion.html.twig` |
| `accordion_item` | `field_item_title`, `field_block_body`, `field_resources` | `paragraph--accordion-item.html.twig` |
| `librarian_card` | `field_block_title`, `field_librarian` (→ Staff) | `paragraph--librarian-card.html.twig` |
| `guide_video` | `field_block_title`, `field_video` (→ Remote video media) | `paragraph--guide-video.html.twig` |

View modes: `node.guide_item` (a Resource in a list,
`node--resource--guide-item.html.twig`) and `node.guide_card` (a staff
member, `node--guide-card.html.twig`, any Staff type).

`guide_section` has no snap or height fields on purpose: long text
sections don't scroll-snap well, and palefire_modular treats a section
without them as auto height, no snap. Its template must not extend
`paragraph.html.twig`: palefire_modular already prints the `<section>`
and `<h2>`, and `PalefireHooks::preprocessParagraph()` would add a second
pair (it treats any paragraph with `field_section_title` as a section).

### How to Request Research Help

`scripts/palefire_guide_research_help.php` (2026-10-16) builds a second
guide, owned by Kyle Tanglao, from `https://lib.lsu.edu/llms.txt` (the
library's guidance for AI systems), rewritten for patrons. Sections: Start
here (official records first, the source order), What we can help with,
What we can't do (an accordion: AI-supplied box and folder numbers,
availability, rights determinations, restricted or embargoed material, AI
summaries and OCR, missing details), Requesting archival materials, Using
AI tools with our collections, Citing and reusing our materials, and Get
help.

* It states only what llms.txt says. Service details it doesn't cover
  (consultations, the Special Collections request system, how far ahead to
  request) are left as `[Editor: …]` notes to fill in before publishing.
* Resources are found by link first, so the Scholarly Repository and Ask a
  librarian Resources from the Systematic Reviews demo are reused.
* The owner is the Staff node titled like "Kyle Tanglao"; the script says
  so if there isn't one. The subject is the "Research Guides" term when
  that term exists.
* Like the demo, it creates the page unpublished and stops if a page with
  the same title exists.
* If llms.txt changes, update the page itself; the script is a one-time
  starting point, not a sync.

### How to Use Special Collections

`scripts/palefire_guide_special_collections.php` (2026-10-20) builds a
beginner's guide to Special Collections, owned by Kyle Tanglao. Sections:
Requesting and visiting (the request-paths diagram, then Reading Room and
digital copy steps), What Special Collections is, How a collection is described,
What is a finding aid?, Finding your way by collection type (an accordion:
Manuscripts, University Archives, Oral History), Asking Special
Collections a question (Ask Us form fields, Do, Don't, an example), and
Get help.

* Service details (the Special Collections account, "Request Item", 10
  open requests, 5 to 15 minutes in the Reading Room, 3 to 7 days from
  remote storage, Reading Room rules, the Ask Us form fields, phone and
  email) come from lib.lsu.edu/special, /findingaids, /oralhistory and
  askus.lib.lsu.edu/special as of October 2026. If those change, update
  the page; the script is a one-time starting point.
* The structure follows the Special Collections records flow chart
  (catalog record, finding aid, description and item list, box, folder,
  item). Its deprecated record types are left out on purpose.
* The Ask Us example uses an invented collection and numbers, and says so,
  so the guide never shows box or folder numbers that aren't in a real
  finding aid.
* New Resources: Finding Aids, Special Collections account, Manuscript
  Subject Guides, University Archives, the Oral History center, the
  Louisiana Digital Library and Ask Us: Special Collections. Existing
  Resources with the same link are reused (the Library Catalog and Special
  Collections & Archives from the research-help guide).
* Like the other guides, it creates the page unpublished and stops if a
  page with the same title exists.

### Writing a guide

* One section per LibGuide tab. Subpages become blocks in their parent's
  section, so they show as sub-links under it.
* Every block heading is an h3 and a sub-link in "On this page". Inside a
  block, use **Heading 4**: an h3 typed into the text would be listed too.
  Accordion item titles and anything inside an open accordion are not
  listed (`data-toc-skip`).
* Add Resources first (Content > Add content > Resource), then pick them
  in a Resource list by title. Paste the plain address and tick "Requires
  LSU login" rather than pasting a libezp link.
* A paragraph that is only `[request-paths]` draws the Special Collections
  request diagram (two subway lines) after the block's text. Its labels
  are in `templates/includes/guide-request-paths.html.twig`.
* A bulleted list with a list inside it is drawn as a decision tree
  (indent bullets with Tab in WYSIWYG). Keep plain nested lists out of
  guides, or they get the tree look too.
* Leave the TOC to do navigation: no "Next / Previous" or "see the tab
  above" text.

### Markup, styles and behaviour

* Styles: `scss/components/_guide.scss`. Colours are the `--bs-*`
  properties (they switch in dark sections, which set
  `data-bs-theme="dark"`), with the dark section tokens first
  (`--pf-section-accent`, `--pf-section-muted`, `--pf-section-border`).
  No literal colours.
* Accordions are native `<details>`/`<summary>`: no JavaScript, keyboard
  and screen reader support from the browser, 44px rows, and the browser's
  find-in-page opens a closed item that contains the match.
  `.pf-accordion__item` resets the starterkit `details` box
  (`css/components/details.css`: full grey border, 1em margins), which
  otherwise draws every item as a separate bordered box. The `<summary>`
  stays `display: block` with a flex row inside it, and the item prints its
  stored text if its view display has `field_block_body` turned off, so a
  panel is never empty.
* Tables in guide text scroll inside their block on narrow screens (the
  block scrolls, not the table, so the table keeps its semantics).
* Icons are inline Bootstrap Icons SVGs, like the utility menu, so guide
  pages load no icon font. `build/copy-assets.js` lists the new ones.
* Video (`js/guide-video.js`, library `palefire/guide-video`): the block
  shows the media item's locally stored thumbnail and a "Play video" link.
  Pressing it swaps in the youtube-nocookie.com or Vimeo (`dnt=1`) player,
  so nothing loads from the provider until then. Other providers, and
  visitors without JavaScript, follow the link to the video's page.
* Page head: `templates/includes/guide-head.html.twig` under the `<h1>`
  (page.html.twig), built by `PalefireHooks::guideHead()`. It is left out
  when a bento search prints the `<h1>`. The owner's and subjects' cache
  tags are on the head, so renaming either updates the page.
* Access: unpublished Resources, staff nodes and media are left out for
  visitors who can't see them, with their cache tags kept, so publishing
  one shows it on every guide at once.

### Guide theme

Guide pages (`<html data-pf-mode="guide">`) get a look of their own,
matching the guide preview, in `scss/components/_guide.scss`. No other page
changes.

* **Palette:** purple-tinted neutrals (`--pf-g-*`: surface `#f6f4f9`, rules
  `#e2dfe7`, muted `#595959`, accent LSU purple). With Dark guides on,
  `--pf-g-*` and Bootstrap's `--bs-*` switch to the guide's purple-black
  (`#121014` page, `#1c1921` surfaces, `#b3adbb` muted, lavender accent)
  instead of Bootstrap's `#212529`. Text contrast: 7:1 and up in light,
  9:1 and up in dark.
* **Chrome:** the TOC sidebar is the page colour, not a glass panel (while a
  dark section is active on a light guide, Palefire's dark sidebar still
  takes over so the TOC text keeps its contrast). The TOC bar is LSU purple
  on light guides (gold is about 1.4:1 on white) and gold on dark. On dark
  guides the header is the guide's black; Reduce transparency and High
  contrast make it solid.
* **Type and spacing:** smaller TOC links (0.875rem, sub-links 0.6875rem,
  40px rows), section titles up to 2.375rem at weight 500, sections padded
  2.5 to 4.5rem with a hairline between them, and a hairline under the page
  head.
* **Text blocks:** a Heading 4 plus the paragraph or list after it reads as
  a term and its definition, on an accent rule. Numbered lists are steps,
  with a large light numeral and a hairline per item (a leading **bold**
  phrase becomes the step's title). Callouts use the accent tint.
* **Head:** values are bold, and "First published" shows the created month
  when it differs from the Updated month.
* **Breadcrumb:** the preview showed Home / Research guides / page. The live
  breadcrumb follows the URL, so it shows "Research guides" once
  `/v2/guides` exists as a page (for example a guides landing page).

Fix in the same change: `pf-solid-chrome` (`base/_mixins.scss`) nests its
class version under `:root`, so selectors starting with `html.pf-guide-dark`
inside it never matched; those rules are now written out beside the mixin.

**Cream light theme** (2026-10-16): light guides are cream instead of white,
with dark grey text instead of black, like a printed catalogue.

* `--pf-g-*` gains `bg #f7f4ec`, `fg #2b2a28` and `heading #1f1e1c`, and
  the surface and rules turn warm (`#efebe1`, `#e2ddd1`; accent tint
  `#ebe4f0`). Bootstrap's `--bs-body-bg` / `-color` / `-emphasis-color` /
  `-border-color` follow on light guides, so the page, the header glass
  (built from `--bs-body-bg-rgb`) and the page-coloured sidebar all turn
  cream. The page title and active TOC link use the heading grey, the TOC
  rail the rule colour. Contrast: text 13:1, muted `#595959` 6.4:1, accent
  10:1.
* **Scrollbar:** the page's own scrollbar on guides is now the same thin bar
  as the sidebar and drawers (`pf-thin-scrollbar` on `<html>`), on the page
  colour, instead of the system bar. On Dark guides its thumb is the
  guide's muted grey.
* **Scrollbars like the TOC rail** (follow-up): on guides, the page
  scrollbar and the sidebar's (which was a long dark bar, the TOC's full
  grey) are a 4px track in the rail colour (`--pf-g-rule`) with a lighter
  thumb (`--pf-g-scroll`, `#7a7772`; hover `#595959`). That thumb is as light
  as it can go and pass 1.4.11: about 3.3:1 on the rail, 4:1 on the cream
  (the rail colour itself would be about 1.2:1). Dark guides: `#b3adbb` on
  the dark rail. `pf-thin-scrollbar` gained `--pf-scroll-track`
  (transparent by default, so the drawers don't change). While a dark
  section is active on a light guide, the sidebar keeps the default bar.
* **Sidebar bar on hover only** (follow-up, every Modular page): a long TOC
  often overflows the sidebar by a few pixels while the header is
  expanded, so a nearly full-height thumb showed at the top of the page and
  vanished once the header compacted on scroll, which looked like a bug.
  The sidebar's bar is now invisible until the pointer is over the sidebar
  or focus is in it (`:not(:hover, :focus-within)` in `_modular.scss`; the
  guide colours apply on hover). `scrollbar-gutter: stable` keeps its space,
  so nothing shifts when it appears.

### TOC always shown on large screens (2026-10-16)

From lg up the table of contents is always shown, on every Modular page
(guides and hero pages alike): it is the page's main navigation, so the
hide/show button is gone. Below lg nothing changes: the list-icon button
opens the TOC offcanvas.

Removed with it: the button (`page.html.twig`), `js/modular-toc-collapse.js`
and its `palefire/modular-toc-collapse` library, the collapsed and
slide-out styles in `_modular.scss` (`html.pf-toc-collapsed`,
`.pf-modular__toc-collapse`, `html.pf-toc-instant`, the column and slide
transitions), the `data-pf-toc-default` attribute (hero pages used to open
with the TOC hidden) in `PalefireHooks::preprocessHtml()` and its Swup
sync. `data-pf-mode` stays (Dark guides uses it). Visitors' saved
`palefire.tocCollapsed*` keys in `localStorage` are simply ignored now.
One less script in `<head>` on Modular pages.

### Reading colours (2026-10-16, replaces Dark guides)

Settings > **Reading colours**: twelve page and text colour pairs popular in
reading modes, picked as tiles that show "Aa" in each swatch's own colours.
Defined once in `$pf-reading-themes` (`scss/components/_reading-themes.scss`).

| Swatch | Page | Text | Text contrast |
| --- | --- | --- | --- |
| Twilight (default since 2026-10-17) | #251d30 | #b2a6c1 | 7.0:1 |
| Cream (the default until 2026-10-17) | #f7f4ec | #2b2a28 | 13.1:1 |
| Soft cream (light grey on cream) | #f7f4ec | #676561 | 5.3:1 |
| Lavender (muted LSU purple on cream) | #f7f4ec | #6b5a8e | 5.5:1 |
| Sepia | #f4ecd8 | #5b4636 | 7.5:1 |
| Sage | #e3eadb | #3b4a3a | 7.7:1 |
| Dusk (soft dark) | #2b2a33 | #b7b3c0 | 6.9:1 |
| Night | #121014 | #e6e2ea | 14.8:1 |
| Solarized (Solarized dark) | #002b36 | #839496 | 4.7:1 |
| Cocoa (warm brown) | #26211c | #9c8e7d | 5.0:1 |
| Forest (green) | #1c2420 | #869a8d | 5.3:1 |
| Midnight (blue) | #1d2330 | #8a94a8 | 5.2:1 |
| Material (Atom Material Theme; replaced Mist) | #263238 | #eeffff | 12.8:1 |

Soft cream, Lavender and Dusk are the deliberately soft ones, closest to AA
(Soft cream added as a follow-up: the Cream page with a lighter grey). The
last four are low-contrast dark modes added later, all just above AA; their
code, chip and callout tints sit only just above the page colour so text
stays 4.5:1 on them too (callouts still have their accent bar). Every
swatch's muted text is 4.5:1 and up (on its surface colour too), its accent
(links) 4.7:1 and up, its scrollbar thumb 3:1 and up on page and rail.

- **Where:** every page once a swatch is picked, and guides always (Cream
  by default). Since "Reading colours everywhere" (below) that is the whole
  page; it used to be guides only, with just the main content elsewhere.
- **How:** `<html data-pf-theme="sepia">` (Cream: none), set before the
  first paint by the inline script in `html.html.twig` and live by
  `utility-panels.js` (`THEMES`). Each swatch sets the guide tokens
  (`--pf-g-*`) and Bootstrap's `--bs-body-*`, link and border colours. The
  dark swatches (`DARK_THEMES`: Dusk, Night, Solarized, Cocoa, Forest,
  Midnight) also add `.pf-reading-dark` (was `.pf-guide-dark`) and
  `data-bs-theme="dark"`. Saved as `theme` in `pf-a11y`; a saved "Dark
  guides" (`dark`) reads as Night.
- **High contrast** (Settings switch or the OS): see "Reading colours
  everywhere" below.
- **Logo:** the header logo's fill is now the bar's text colour by default
  (was a fixed #999), and the swatch's text colour on guides.
- **To add a swatch:** an entry in `$pf-reading-themes` (check contrast), its
  key in `reading_themes` (`utility-panels.html.twig`), `THEMES`
  (`utility-panels.js`) and the pattern in `html.html.twig`; dark ones in
  `DARK_THEMES` and the dark pattern too.

### Drawers in the guides' style (2026-10-16)

The utility drawers (Search, Hours, Ask Us, Accounts, Settings) now follow
the guides' art direction everywhere instead of being always dark: frosted
glass in the guide's page colour (84%, the existing 26px blur), dark grey
text, muted grey labels in tracked monospace, body text in the sans, hairline
rules, square corners, the accent (LSU purple) for hover, focus and the
selected marks. Control borders use the swatch's scrollbar grey (3:1 and
up). On guides they take the reader's swatch: light glass on light swatches,
a shade darker than the page on Dusk and Night. Elsewhere they are Cream.

- The backdrop is a 45% wash of the page colour instead of black, so the
  glass still reads as glass (Dusk and Night keep a dark wash). Worst case,
  a black pixel under the drawer still leaves it about #e0ddd5: text 11:1,
  muted 5:1.
- The Hours drawer keeps Origami's gold OPEN badges (black on gold, 13:1)
  and the dotted Handjet clock; cards, the closed badge and the closed
  stripes are now tints of the text colour, so they work on any swatch.
- The Settings switch's thumb is white on the light swatches' accent, black
  on lavender (`--pf-g-on-accent`).
- `data-bs-theme="dark"` is gone from the drawers' markup; on Dusk and Night
  guides they inherit it from `<html>`. Reduce transparency / High contrast
  still make them solid.
### Reader font for guides (2026-10-16)

Settings > Reading > **Reader font for guides** (key `reader`, class
`html.pf-a11y-reader`) sets guides in the book serif of Edge's Reader view:
Sitka (what Reader view uses, on Windows), Charter or Iowan Old Style on
Apple, Georgia elsewhere. System fonts only, so nothing is downloaded.

* Only on guide pages (`<html data-pf-mode="guide">`), through CSS alone
  (`_guide.scss`); other pages keep their fonts.
* The page title, guide head and sections change: body text 1.1875rem at
  1.7 line height, the intro 1.3125rem, headings bold serif (Sitka has no
  medium weight). The header, TOC, breadcrumb and the monospace labels keep
  their fonts.
* "Easy-to-read font" wins when both are on: it is the accessibility setting.
* Saved like the other switches: the key is in `SWITCHES`
  (`utility-panels.js`), the inline `<head>` script (`html.html.twig`, so
  there's no flash) and `settings_groups` (`utility-panels.html.twig`).

### Lighter drawers and "Back to top" (2026-10-16)

- **Drawers are more see-through:** the glass is 65% of the page colour
  (was 84%), so about 19% of the page shows through (was about 9%); Dusk
  and Night 78% (was 90%). Measured worst case, a black pixel under the
  drawer, the old 84% already let Soft cream and Lavender labels fall under
  4.5:1. So drawer text now uses each swatch's heading colour and labels a
  muted tone darkened just enough for 4.5:1 on the thinner glass
  (`--pf-g-panel-text` / `--pf-g-panel-muted`, `_reading-themes.scss`; on
  Cream #1f1e1c and #505050). The swatches' softest greys stay for long
  reading text on the page. Control borders (tiles, buttons, cards) use that
  muted tone too: the scrollbar grey fell to about 2.5:1 on the thinner
  glass.
- **"Back to top"** (TOC sidebar, every Modular page) in the guides' label
  style: a hairline in the rail colour, then a thin arrow and the label in
  one row, tracked monospace caps in the TOC's muted colour, so it follows
  the reading swatch on guides. It was #ccc with a 20%-opacity arrow, about
  1.6:1 on the light sidebar. Hover/focus: full-strength text, the arrow in
  the accent and lifted 2px (no lift with reduced motion). 44px tall.
  The unused `toc-top-color` token is gone.

### Hidden sidebar scrollbar, four dark swatches, drawers restyled (2026-10-16)

- **Modular sidebar (`#pf-modular-sidebar`) never shows a scrollbar.** It
  still scrolls the same way (wheel, touch, keyboard); only the bar is hidden
  (`scrollbar-width: none` and `::-webkit-scrollbar`, `_modular.scss`). The
  TOC list's own scrollbar (`.pf-toc__list`) is unchanged.
- **Four more low-contrast dark reading colours:** Solarized, Cocoa, Forest
  and Midnight (table above), all dark, so on guides they darken the whole
  page like Dusk and Night.
- **Drawers in the reader style, everything:** title in the sans (1.5rem
  medium, heading colour) over a hairline; footer link as a tracked
  monospace label over a hairline, its arrow nudging on hover; the Hours
  date as a muted label; Ask Us tabs and My Accounts links as ruled rows
  (no boxes), the selected tab marked by an accent bar; cards and legends on
  the swatch's rule colour. The Hours OPEN badge drops Origami's gold for the
  swatch's accent with its on-accent text (6.6:1 and up); the Handjet clock
  stays. The `--pf-hours-badge-*` tokens are gone.

### Reading colours everywhere; high contrast reworked (2026-10-16)

- **The swatch now colours every page and everything on it** (it was guides
  only, with just the main content elsewhere): header and its dropdowns,
  TOC sidebar, drawers, dark sections (they become the page colour), the
  bento lead panel and Visit strip, the building map and its tooltips, the
  events calendar and the footer. Background videos and maps keep playing
  under a wash of the page colour (60%; the map's in-view wash 90%), with
  the panels as 75% glass on top (80% on dark swatches). Text on that glass
  is the heading colour and labels the panel muted tone, so it stays 4.5:1
  over a black (light swatches) or white (dark) frame. Pages with no swatch
  picked look as before.
- **How:** each swatch only writes its `--pf-g-*` tokens on `<html>`.
  Bootstrap's colours come from those (`pf-reading-bs`), declared again on
  anything that resets them (`[data-bs-theme]`, dark sections, the footer),
  so the dark header and dropdowns no longer fall back to Bootstrap's grey.
  The chrome, bento, map and calendar read the same tokens. Background
  washes and the map's tooltip colours are now custom properties
  (`--pf-media-wash`, `--pf-media-wash-map`, `--pf-map-tip-*`,
  `--pf-map-on-hover`) with their old values as defaults. With a swatch,
  `modular-toc.js` keeps the chrome on the swatch instead of switching it
  for dark sections. All in `_reading-themes.scss`; the guide-only chrome
  rules in `_guide.scss` are gone. CSS is about 14 KB smaller.
- **Glass:** the header, dropdowns, TOC sidebar and footer are the page
  colour with a little black (3% on light swatches, 15% on dark), so they
  read as a quiet layer just under the page. Same opacity as before.
  (Changed the same day: see "Material swatch" below.)
- **High contrast** with a swatch: the swatch's own colours pushed apart.
  Light swatches get a lighter page and darker text, dark ones a darker
  page and lighter text (page 50% toward white or black, text 30% the
  other way). Text is 8.3:1 and up on every swatch (AAA), scrollbar thumbs
  gain contrast, and rules, borders and glass stay as they are. It used to
  swap in brighter text, make borders full strength (which darkened the
  scrollbar's track) and make the chrome solid in Palefire's grey, which
  clashed with the swatch. Pages without a swatch keep the old behaviour.
  "Reduce transparency" still makes the chrome solid (in the swatch's
  colour).
- **Solarized's accent** is a touch lighter (#33b5aa, was #2aa198), so links
  still pass over a background video (6.0:1 on its page).

### Material swatch; lighter chrome glass (2026-10-16)

- **Material replaces Mist.** The Atom Material Theme's colours, sampled
  from the editor: page #263238, text #eeffff, labels and TOC links
  #a7b6b8, links and the TOC bar cyan #89ddff. It is the first multicolour
  swatch: in the main content, headings are green (#c3e88d, 9.6:1), bold
  yellow (#ffcb6b, 8.8:1) and italics purple (#c792ea, 5.5:1; left plain on
  the bento's glass over a video, where it would fall under 4.5:1). The
  optional `title`, `strong` and `em` keys in `$pf-reading-themes` do
  this, so any swatch can use them. The Settings tile shows just its page
  and text colours. A visitor who had Mist saved gets Cream.
- **Material's header and TOC are its own sidebar colour** (#232e33, the
  editor's darker panel), set with the new optional `chrome` key instead of
  the mix below.
- **Chrome glass:** light swatches are back to the page colour itself (was
  3% black); dark swatches have half the added darkness (7.5% black, was
  15%). Dusk's panel label tone is a touch lighter (#a6a2af, was #a29eab)
  so labels on its glass stay 4.5:1 over a background video.

### Librarian card typography (2026-10-16)

The guide Librarian card (`.pf-guide-block--person`, `.pf-person`) is set
like a museum staff listing, CSS only (`_guide.scss`):

- The name (1.375rem medium, tight tracking, a thin accent underline), then
  the role straight under it in the muted colour. Any unlabelled text field
  (job title, pronouns) is moved up to that role line with `order`, so
  Manage display order no longer puts the job title last.
- A hairline, then the labelled fields (phone, email, office) as a
  two-column list: tracked monospace labels in a 7.5rem column, values in
  the body sans on the same baseline, tabular figures so numbers align.
  Email and phone links are body colour with an accent underline.
- Photo 5.5rem square, cropped toward the face, its top on the name.
  Below 576px the photo opens the card and each label sits over its value.

### Cache busting for CSS and JS (2026-10-16)

Drupal puts a library's version in its CSS and JS URLs (`?v=1.0.0`), and
every Palefire library had a fixed version, so after a deploy a changed
`style.css` or script kept its old URL and browsers (or a CDN) could keep
serving the old copy. `palefire_library_info_alter()` (`palefire.theme`)
now sets each theme file's version to a 10-character hash of its contents,
and each library's version to a hash of those. A changed file gets a new
URL; unchanged files keep theirs, so their caches stay warm.

- Takes effect on `drush cr` (already part of every deploy): library info
  is cached, so files are only hashed when caches are rebuilt.
- Files outside the theme (Font Awesome Pro in `/libraries`) and external
  URLs keep their declared versions.
- The versions in `palefire.libraries.yml` are now just labels; no need to
  bump them.
- To check after a deploy: with CSS/JS aggregation off (Configuration >
  Performance), the page source should show `style.css?v=` followed by a
  10-character hash that changes whenever the file does. With aggregation
  on, compare the `/sites/default/files/css/css_….css` name before and
  after a CSS change. If the hook ever finds nothing to change it does
  nothing, so it can't break asset loading.

### Active TOC link in the heading colour (2026-10-17)

The current section in the TOC (`.pf-toc__link[aria-current]` and its
sub-links) now takes the content's heading colour: on a multicolour swatch
its own (`--pf-g-title`, Material's green, 10:1 on its sidebar), otherwise
the usual active text colour as before. On swatches like Soft cream the
headings are the body grey, almost the TOC's muted grey, so matching them
there would hide which link is current. Other links, hover and focus are
unchanged (`_toc.scss`).

### Current heading lights up with the TOC (2026-10-17)

On guides, the heading of the section the TOC marks as current (and its
current sub-heading) takes the active TOC link's colour, so the page and
the TOC light up together: on Cocoa the heading goes from the body tone
#9c8e7d to cream #e6d9c6. `modular-toc.js` adds `.is-toc-current` to the
section's `.pf-section__title` and the matching h3 as it already updates
`aria-current` on the links; `_guide.scss` colours them. A 0.3s colour
ease, no movement or glow; no transition with reduced motion. On Material
the headings are already the TOC's green, so nothing changes there.

### TOC switches sections at mid-screen (2026-10-17)

The TOC (and on guides the lit heading, and the header's dark mode for dark
sections) now moves to a section once it reaches halfway down the screen
below the header, instead of 30% down (`ACTIVATION_LINE`,
`js/modular-toc.js`). Before, a section's heading could sit mid-screen,
the most prominent thing in view, while the TOC still marked the section
above it. Sub-headings follow the same line. The top and bottom of the
page are unaffected (the first and last sections still win there).

### Room above a heading after a TOC jump (2026-10-17)

Clicking a sub-heading in the TOC (an h3) used to land it a few pixels under
the header. Those targets now have `scroll-margin-top: 2rem`
(`_modular.scss`), so they stop with some air above them. Section links are
unchanged: they land on the section, whose top padding already spaces its
title, and sections must not take a scroll margin (it would double up with
scroll-snap's padding).

### Numbered steps: bold stays inline (2026-10-17)

In guide numbered lists (the "01, 02" steps), a `<strong>` that was the
first tag in an item was `display: block`, so anything after the closing
`</strong>`, even a full stop, dropped to its own line ("**systematized
review**" then "." underneath). `:first-child` ignores text, so it also
fired when text came before the bold. Bold in the steps is now always
inline, every bold phrase in the heading colour, with the sentence running
on after it, so editors don't need to put punctuation inside the bold. The
numbers were already positioned outside the text (`::before`, absolute),
so nothing else changes (`_guide.scss`).

### Phones: no doubled step numbers; headings light up sooner (2026-10-17)

- **Numbered steps on iOS** showed the browser's own "1." beside the styled
  "01": iOS Safari ignores `content` on `::marker`, which was how it was
  hidden. The steps now also set `list-style-type: "\200B"` (a zero-width
  space), which hides the number in every browser and, unlike
  `list-style: none`, keeps Safari announcing the list (`_guide.scss`).
- **Portrait screens** move to the next section (TOC, and the lit heading
  on guides) once it reaches three-quarters of the way down the screen
  instead of halfway, so a heading lights up as it arrives on a phone.
  Landscape and desktop stay at halfway (`activationLine()`,
  `js/modular-toc.js`; re-read on rotation).

### Mobile header and menu, redesigned (2026-10-17)

Below lg (phones and tablets) the header was hard to use: the logo, five
unlabelled icons butted together (40px wide each) and a hamburger shared one
row, and the menu opened as a short scrolling strip inside the bar with each
section a desktop dropdown to tap open. Desktop is unchanged.

- **Bar:** logo | Search | **Menu**. Search stays in the bar (the most used);
  the Menu button has a visible label, and its two lines turn into an X while
  open. Both targets are 48px with room between them, and the row keeps its
  height when the sheet opens.
- **Sheet:** the menu opens as a full-height sheet under the bar, in the
  header's glass (and reading swatch), and the page behind stops scrolling.
  At the top, **quick links**: Hours, Ask Us, My Accounts and Settings as
  labelled tiles (two a row, four on tablets) that open the same drawers.
  Then **every section expanded**: its name as a small monospace label, its
  pages as tall sentence-case rows, so any page is two taps away with no
  nested dropdowns. The current page is underlined.
- **Keyboard:** Escape closes the sheet from anywhere (the button or inside
  the sheet) and returns focus to the Menu button, unless a drawer is open.
- **How:** `page.html.twig` (button and sheet), `menu--main.html.twig` (a
  `.pf-primary-nav__heading` label beside each section's button, shown only
  in the sheet), `js/palefire.js` (`palefireMenuActions` copies the utility
  links into the sheet and only then hides them from the bar, so nothing
  goes missing without JavaScript; `palefireMenuEscape`), and the "Phones
  and tablets" part of `_header.scss`. The menu items and their order
  still come from the Main and Utility menus in Drupal.
- The phone-only 88px logo is gone (`$pf-logo-width-xs`): with two targets
  beside it, the 120px logo fits down to 320px.

### Mobile menu as a drawer, sections as toggles (2026-10-17)

Follow-up to "Mobile header and menu, redesigned" (above).

- **The menu is now a drawer** from the left, like Search, Hours and the
  other utility drawers: a Bootstrap offcanvas (`#pf-primary-menu`,
  `page.html.twig`) with the same "Menu" title, close button and backdrop
  wash. The in-bar sheet it replaces stuttered while opening: it grew the
  header's height, so the frosted glass re-blurred a larger area and the
  page re-measured the header every frame. The drawer slides with a
  transform. Bootstrap traps focus, closes it on Escape and returns focus
  to the Menu button, so `palefireMenuEscape` is gone. From lg up
  `.navbar-expand-lg` shows the same element inline as the desktop menu.
- **Sections are toggles:** tapping "Help with", "Visit"… opens that
  section's pages underneath it (a plus that turns into a minus) instead of
  everything being listed at once; several can be open. The section holding
  the current page starts open. Headings are disclosure buttons
  (`aria-expanded`, `menu--main.html.twig`), toggled by
  `palefireMenuDrawer` (`js/palefire.js`); closed sections are out of the
  tab order. The open/close is a CSS grid-row transition (0fr to 1fr), off
  with reduced motion.
- The drawer lives inside the header (it is the desktop menu too), and the
  header's glass makes it the containing block for fixed elements, so the
  drawer sets its own full-screen height and the header rises above the
  backdrop while it is open. The drawer is solid (the page colour): a blur
  inside the header's blur would have nothing to blur.
- Swup now closes the drawer before a page swap (`palefire-swup.js`); the
  old collapse leftovers (toggle reset, header-height subtraction, the
  open-menu glass rules) are gone.

### Mobile menu: reading colours, and sections that open cleanly (2026-10-17)

- **Reading colours under the menu** (phones and tablets): the twelve
  swatches from Settings, so they're one tap from the Menu button. They
  are copies of the Settings tiles made by `initMenuSetting`
  (`js/utility-panels.js`) into a fieldset in the menu drawer
  (`page.html.twig`). Picking one ticks the same swatch in the Settings
  form and lets that form apply and save it, so there is still one way
  settings are saved (including the "not saving" case when storage is
  blocked). Both stay in step through `palefire:a11ychange`. Hidden on
  desktop, where the menu is in the bar.
- **Opening a section** showed its pages at once while the section was
  still growing, so they spilled over the rows below for a moment. The
  pages now stay invisible until the section has mostly opened, then fade
  in (.2s, after .2s); closing fades them out first and then shrinks the
  section. No fade with reduced motion (`_header.scss`).

### Mobile menu in the drawers' glass (2026-10-17)

The mobile menu drawer now has the same glass as Search, Hours and the
other drawers: the same transparency over the same 26px blur, and their
text colours. It was solid because it lives inside the header (it is the
desktop menu too), and the header's own blur stopped anything inside it
from blurring the page.

- **Header blur on a layer:** the header's `backdrop-filter` moved to
  `.pf-header::before`, behind the bar (`--pf-header-filter`, set to
  `none` for the solid modes; `_header.scss`, `_chrome-dark.scss`). The
  bar looks the same, but no longer traps its contents.
- **One set of glass values:** `$pf-panel-glass` (.65, light) and
  `$pf-panel-glass-dark` (30%, dark swatches) in `_variables.scss`, used by
  the utility drawers and the menu drawer (`_utility-panels.scss`), so
  they stay matched. The menu drawer's glass, tokens and solid fallbacks
  (Reduce transparency, no blur support) sit next to the drawers' there,
  below lg only.

### Mobile menu sections: headings no longer bounce (2026-10-17)

Opening or closing a section in the mobile menu made its heading slide down
about 19px and back, and the sections below overshoot with it. Measured
frame by frame (headless Edge over the DevTools protocol): mid-animation
the pages row is between 0fr and 1fr and takes only part of the free
space, and the grid's default `align-content` (stretch) gave the rest to
the heading's row, which swelled from 56px to about 95px. The section is
now `align-content: start` (`_header.scss`): the heading holds still and
the sections below slide straight down and back.

### Mobile menu as the base layer; drawers open over it (2026-10-17)

Opening Hours (or Ask Us, My Accounts, Settings) from the mobile menu
closed the menu, with no way back to it. Below lg now:

- **The menu is full screen** (phones and tablets), the base layer.
- **A quick-link tile opens its drawer over the menu** instead of in its
  place (`openOverMenu`, `js/palefire.js`). The tiles no longer use
  Bootstrap's data API, which allows one offcanvas at a time and closed the
  menu first. While a drawer is over the menu, `html.pf-over-menu` lifts
  it and its backdrop above the menu, so the menu dims underneath
  (`_utility-panels.scss`).
- **"‹ Menu"** above the drawer's title goes back to the menu; so do Escape
  and a tap on the backdrop. Focus returns to the tile that opened it, and
  the menu's focus trap is restored (Bootstrap keeps one trap active at a
  time; this re-arms the menu's, `_focustrap`). The drawer's **×** closes
  both, back to the page, with focus on the Menu button.
- Drawers opened from the bar (Search) are unchanged: no back button.
- Browser back and swipe don't step through the layers (left out on
  purpose; they would need history entries that interact with Swup).

Tested in headless Edge over the DevTools protocol against the real
Bootstrap bundle and theme scripts: menu, Hours over it, Back, Escape, 40
Tabs staying in the menu, and × closing both.

### Cache busting, fixed (2026-10-17)

The cache busting added on 2026-10-16 ("Cache busting for CSS and JS",
above) never took effect: after a deploy, phones kept the old CSS and JS.
`palefire_library_info_alter()` expected each file in Drupal's processed
form (a `data` path), but Drupal calls the hook with the definitions as
written in `palefire.libraries.yml` (files keyed by path, CSS grouped
under `theme:`). It matched nothing, so every URL kept `?v=1.0.0`.

- It now reads that form (and the processed one, via
  `palefire_library_files()`), and sets each library's version to a
  10-character hash of all its files' contents. Drupal copies the version
  onto each file's URL and into the aggregate names, so a library gets a new
  URL whenever one of its files changes.
- Takes effect on the next `drush cr` after deploying.
- To check: view the page source. With CSS/JS aggregation off, look for
  `style.css?v=` followed by a hash instead of `?v=1.0.0`; with it on,
  the `css_….css` / `js_….js` names should change after a deploy that
  changed CSS or JS.

### Mobile menu tiles open their drawers again (2026-10-17)

After the layered menu (above), the quick-link tiles in the mobile menu
followed their links (Hours went to the hours page) instead of opening the
drawer. The tiles open drawers with their own click handler now, without
Bootstrap's `data-bs-toggle`, and Swup only skipped links carrying that
attribute, so it navigated. `palefire-swup.js` now also skips the tiles
(`[data-pf-over-menu]`) and, as a general rule, any link whose click a
script already handled (`event.defaultPrevented`; Swup passes the event
to `ignoreVisit` but doesn't check it itself). Reproduced and fixed in
headless Edge with Swup running.

### Drawers over the mobile menu: × goes back (2026-10-17)

Over the mobile menu, a drawer's × closed both it and the menu, next to a
separate "‹ Menu" back button, which was confusing. Now × (like Escape and
a tap on the backdrop) closes just that drawer and goes back to the menu,
with focus on the tile that opened it; the back button is gone
(`openOverMenu`, `js/palefire.js`; its styles left
`_utility-panels.scss`). To leave, close the menu itself.

### Mobile menu: pages fade in one after another (2026-10-17)

Opening a section in the mobile menu faded all its pages in at once. They
now arrive top to bottom, each 40ms after the one above with a slight 4px
drop (`$pf-menu-stagger` in `_variables.scss`; delays set per item up to 12
in `_header.scss`), starting once the section has mostly opened (.2s).
Measured in headless Edge: a section of four is fully in by about .55s.
Closing still fades them all out at once. No fade or movement with reduced
motion.

### Menu pages arrive in sequence, calmer, on desktop too (2026-10-17)

- **Mobile menu:** the page-by-page fade is slower, to match the calm of
  the rest of the site: 60ms apart (was 40ms), each fading in over .35s
  (was .25s). A section of four is fully in by about .73s.
- **Desktop dropdowns** now do the same, quicker: links start fading in
  straight away, 40ms apart over .25s, so a four-link dropdown is fully in
  by about .37s (the pointer is already on its way; links are clickable
  while they fade). A keyframe animation (`pf-dropdown-item-in`), since the
  sheet appears from `display: none`. Closing is instant.
- Timings: `$pf-menu-stagger` / `$pf-menu-fade` (mobile) and
  `$pf-dropdown-stagger` / `$pf-dropdown-fade` (desktop) in
  `_variables.scss`. None of it with reduced motion. Measured in headless
  Edge.

### Guide accordions open calmly (2026-10-17)

Guide accordions (native `<details>`) now open with the mobile menu's calm
timing (`$pf-menu-stagger` 60ms, `$pf-menu-fade` .35s; `_guide.scss`):

- **The panel glides open** in Chrome and Edge (`::details-content` with
  `interpolate-size`, behind `@supports`); Safari and Firefox open it at
  once, as before. Closing is instant everywhere.
- **Its blocks fade in one after another** with a slight drop: the text's
  paragraphs, lists and tables, then each resource. `palefire.js`
  (`palefireAccordionSequence`) numbers them in page order, capped at the
  sixth so long resource lists arrive together instead of trickling in.
  Without JavaScript they fade in together. Individual lines and list
  items inside the text don't stagger: it is reading content.
- Measured in headless Edge: an eleven-block panel glides open in about
  .35s and is fully in by about .6s; closing takes one frame.
- None of it with reduced motion (OS setting or Settings drawer). The
  Settings drawer's Reduce motion now also zeroes animation delays
  site-wide (`_a11y-settings.scss`), so staggered items don't wait and then
  pop in.

### Dropdown links: an underline that draws in (2026-10-17)

Desktop dropdown links (Main Library, Special Collections…) showed a
static underline on hover. It now draws in from the left under the words,
and when the pointer leaves it carries on out to the right instead of
snapping back: .35s, quick at first and settling softly
(`cubic-bezier(.22, 1, .36, 1)`). The current page keeps a full, still
underline; keyboard focus draws it the same way.

- The words are now wrapped in `<span class="pf-primary-nav__text">`
  (`menu--main.html.twig`), so the underline (a 2px background line under
  the span) runs under the text only, not the full width of the dropdown.
- No animation with reduced motion; in Windows High Contrast (which drops
  background images) a regular underline shows instead (`_header.scss`).
- Measured in headless Edge with a forced :hover: grows 0 to 100% in
  about .35s, then shrinks toward the right edge on leave.

### Dropdown links: a calmer colour change (2026-10-17)

Desktop dropdown links changed to the accent colour instantly on hover.
The colour now eases in over .2s and back out over .5s (the hover state's
transition sets the way in, the resting state's the way out;
`_header.scss`), alongside the drawn underline. None with reduced motion.
Measured in headless Edge with a forced :hover.

### Dropdown underline stays the hover colour (2026-10-17)

The dropdown links' drawn underline was in the text colour, so on its way
out it faded from the hover colour back to the text's while it shrank. It
is now always the hover colour (`--bs-dropdown-link-hover-color`,
`_header.scss`): it simply draws in and out. The text keeps its own calm
colour change (.2s in, .5s out).

### Twilight, the new default (2026-10-17)

(First named "Purple & Gold", key `purple`; renamed Twilight, key `twilight`,
the same day. The default is never saved, so no stored settings used the
old key.)

Every page now opens in a new reading swatch, **Twilight**, until a
visitor picks another in Settings > Reading colours. It has Midnight's
darkness in LSU purple's hue, with LSU gold as the accent:

| Role | Colour | Contrast on the page |
| --- | --- | --- |
| Page | #251d30 | |
| Body text | #b2a6c1 | 7.0:1 (AAA) |
| Headings | #e1d8ec | 11.8:1 |
| Muted labels | #9e92ad | 5.5:1 |
| Accent (links, TOC bar, hover underline, focus) | #fdd023 | 11.0:1 |
| Text on gold (Hours badge, switches) | black | 14.2:1 |

- Body text is stronger than Midnight's (rotating Midnight's grey would
  give 5.0:1): a default has to suit people who never open Settings.
- Over a background video, in the worst case (a white frame), panel
  headings are 9.5:1, labels 5.3:1 and gold 8.9:1. High contrast pushes
  it to 10.9:1 body text. Audited in the compiled CSS.
- **How:** `html.html.twig` puts `data-pf-theme="twilight"`, `pf-reading-dark`
  and `data-bs-theme="dark"` on `<html>`, so pages are right before any
  script runs and without JavaScript; the inline head script swaps in a
  saved choice (and clears the dark markers for light swatches).
  `DEFAULT_THEME` in `utility-panels.js` and `$pf-reading-default` in
  `_reading-themes.scss` name the same key. The default isn't saved; only
  a different pick is, so Reset goes back to it.
- **Cream** is now a regular choice (`data-pf-theme="cream"`), no longer
  "no attribute". Visitors who had picked Cream had nothing saved, so they
  now see Twilight until they pick Cream again.
- The Settings help text now says the colours apply to the whole site.

### Sections turn active three-quarters down, on every screen (2026-10-17)

Phones in portrait already switched sections (the TOC link, and on guides
the lit heading) once a section reached three-quarters of the way down the
screen; desktops and landscape screens switched halfway. Every screen now
uses three-quarters (`ACTIVATION_LINE = 0.75`, `js/modular-toc.js`), so a
heading lights up as it arrives rather than once it has climbed to the
middle. The orientation check is gone. The same line still switches the
header to dark for dark sections on pages without a swatch.

### Twilight: calm hovers, no gold (2026-10-17)

On Twilight, hovering used to turn menus, icons and drawer controls gold.
Hovers there now just brighten the text's own colour:

- Menu, dropdown and icon links, drawer controls, accordion titles: to a
  near-white lavender (`#f7f3fc`); the dropdown's drawn underline follows.
- Gold links: to a lighter gold (`#ffe27a`), not lavender.
- TOC links already brightened (muted to heading colour).

How: two new tokens, `--pf-g-hover` (text) and `--pf-g-link-hover` (links).
A swatch can set them (`hover` / `link-hover` in `$pf-reading-themes`);
otherwise they are the accent and the heading colour, so every other
swatch hovers as before. Hover rules read `--pf-g-hover` with the old
accent as fallback (`_reading-themes.scss`, `_utility-panels.scss`,
`_guide.scss`, `_toc.scss`, `_events-carousel.scss`). Focus rings, the TOC
bar and other markers that aren't hovers stay gold. Measured with a
forced :hover in headless Edge.

### One hover timing everywhere: .2s in, .5s out (2026-10-17)

Text colour on hover and focus now comes in over .2s and fades out over .5s,
with `ease`, everywhere: the bar's links, icons and logo, dropdowns, the
mobile menu, drawer controls and tiles, the TOC and "Back to top", the
breadcrumb, guide accordions and the librarian card, the bento search and
video buttons, the events carousel, plain links, and Bootstrap's buttons,
nav and pager links. They used .15s to .2s, some `ease-in-out`.

- `$pf-hover-in` / `$pf-hover-out` (`_variables.scss`). Each element's resting
  style has `color $pf-hover-out ease` and its own hover/focus style
  `color $pf-hover-in ease` (CSS uses the new state's transition). Not one
  shared variable: hovering a link also hovers its parents, so moving to the
  next link would have faded the last one out at .2s.
- Plain links (`:where(a)`) and Bootstrap's `.btn`, `.nav-link` and
  `.page-link` get it through `_base.scss` and Bootstrap's
  `$btn-transition` / `$nav-link-transition` / `$pagination-transition`.
- The events carousel's prev/next buttons swap text and fill on hover, so
  their fill and border keep the same timing (else the text would briefly
  match its background).
- Not hovers, so unchanged: the current-heading highlight on guides (.3s)
  and the light-to-dark map section (.45s). The building map's room names
  already used .2s in, .5s out.
- Measured with a forced :hover in headless Edge (bar, dropdown, TOC and
  body links).

### Twilight: hovers to a richer purple (2026-10-17)

Twilight's text hover (`hover` in `$pf-reading-themes`) was a near-white
(`#f7f3fc`), barely different from the pale lavender menu links, so the
dropdown links hardly changed. It is now a richer purple, `#c19df1` (LSU
purple's hue at 75% saturation, from the links' 34%): 7.2:1 on the page,
7.4:1 on the bar and dropdowns. Still never purple to gold. It applies to
all of Twilight's text hovers (bar, dropdowns, icons, drawer controls) and
the dropdowns' drawn underline; gold links still brighten to `#ffe27a`.

### LibGuides

The LibGuides site will be restyled to match later. Keep the class names
here (`.pf-resource`, `.pf-accordion__*`, `.pf-guide-block__title`,
`.pf-label`) stable: a LibGuides group-level stylesheet can reuse
`_guide.scss` almost as is.

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

### Hours drawer: Origami look (2026-10-16)

The Hours drawer now matches Origami's on every page:

- **Gold OPEN badges everywhere.** The drawers set their own dark tokens
  but took the accent from the page, so on light guides (purple accent)
  the badges, close hover and links turned purple. `.pf-panel.offcanvas`
  now sets `--pf-toc-accent` to gold itself.
- **Origami's panel:** 66% black with a 6px blur (was the sidebar's 50%
  glass with a 26px blur), so the page shows faintly through. Over the
  backdrop wash (72% light pages, 55% dark) muted text stays about 5:1.
  Reduce transparency / High contrast still make it solid black.
- **Type:** the date regular weight (was medium); the clock Handjet at
  5.625rem and weight 300, the weight that draws it as dots (was 400,
  solid); open hours 2rem at weight 200; the location name medium.
- **"Main Library"** for LibCal location 241 (LibCal calls it "LSU
  Library"), set in `hours_locations` in `utility-panels.html.twig`.

### Settings drawer

Each change applies and saves as soon as it's made, in this browser only
(`localStorage` key `pf-a11y`), and a status line tells screen-reader users
"Saved on this device." "Reset to default" clears it. Another open tab
follows along (`storage` event).

| Group | Setting | `<html>` | What it does |
| --- | --- | --- | --- |
| Text size | 100 / 115 / 130 / 150% | `data-pf-text-size` | Root font size, so the whole rem-based layout scales, not just the text. The breadcrumb, TOC title, TOC sub-links and "Back to top" are rem with em tracking too (they were 12px/10px with px letter-spacing, so they stayed small) |
| Reading | Easy-to-read font | `.pf-a11y-font` | Atkinson Hyperlegible Next (Google Fonts); only downloaded when someone turns it on |
| Reading | More text spacing | `.pf-a11y-spacing` | The WCAG 1.4.12 test values: line 1.5, paragraph 2em, letter .12em, word .16em |
| Reading | Underline all links | `.pf-a11y-underline` | Includes menus, dropdowns and link-buttons |
| Display | High contrast | `.pf-a11y-contrast` | Solid backgrounds, full-strength text, brighter borders, heavier video wash, no stripes |
| Display | Reduce transparency | `.pf-a11y-solid` | No see-through glass on the header, sidebar and drawers |
| Display | Strong focus outline | `.pf-a11y-focus` | 3px gold ring around a 3px black one |
| Display | Dark guides | `.pf-a11y-dark` (+ `.pf-guide-dark` and `data-bs-theme="dark"` on guide pages) | Guide-mode pages use the dark palette; other pages are unchanged. See "Dark guides" under Guide pages |
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
- **Scrollbar** (2026-10-16): every drawer body (`.pf-panel__body`) and
  the "On this page" sidebar (`.pf-modular__sidebar`, lg and up) scroll
  with the same thin bar: a 4px thumb in the TOC grey (`--pf-toc-color`,
  `--pf-toc-active` on hover) on no track, with no arrow buttons. One mixin,
  `pf-thin-scrollbar` in `scss/base/_mixins.scss`, so the two can't drift
  apart. It follows light and dark chrome (dark sections, Dark guides, the
  always-dark drawers) and is about 5:1 against each, for WCAG 1.4.11.
  Firefox gets `scrollbar-width: thin` and `scrollbar-color`; Chromium and
  Safari get `::-webkit-scrollbar` (the only way to drop the arrows;
  Chromium ignores it while the standard properties are set, so the mixin
  resets them there). The sidebar also keeps its gutter
  (`scrollbar-gutter: stable`), so the links don't shift when an opening
  sub-list makes it scroll.

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

Links with `data-bs-toggle` are skipped too (2026-10-16). The utility
menu's icons are real `<a href>` links that open offcanvas drawers; Bootstrap
prevents their default, but Swup 4 doesn't check `defaultPrevented`, so for
anonymous visitors it also followed the link and left the page.
