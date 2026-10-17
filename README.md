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
- `scripts/palefire_guide_demo.php` – builds the Systematic Reviews LibGuide as an unpublished guide page, as a demo

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
* **Text format.** Guide text uses tables, lists and Heading 4. Make sure
  the format editors use allows `<table>`, `<caption>`, `<th scope>`,
  `<h4>`, `<ol>`, `<ul>` and `<code>` (CKEditor 5: Table, Heading, Code).
* **Guide card.** Check Staff > Manage display > Guide card. The script
  turns on the Staff fields that look like an email, phone, photo, job
  title, pronouns or office; add or remove fields there.

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
| Cream (default) | #f7f4ec | #2b2a28 | 13.1:1 |
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
  are copies of the Settings tiles made by `initMenuThemes`
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
