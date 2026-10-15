# Palefire

Drupal 11 theme (starterkit-generated) built on Bootstrap 5.3 + Dart Sass.

## Build

    npm install        # bootstrap, bootstrap-icons, swup, sass
    npm run build      # scss/style.scss -> css/style.css, copies Bootstrap JS, Swup + icons
    npm run watch      # recompile on save

## Structure

- `palefire.info.yml` – regions, global libraries, `core_version_requirement: ^11`
- `palefire.breakpoints.yml` – Bootstrap 5 breakpoints (sm 576 / md 768 / lg 992 / xl 1200 / xxl 1400)
- `scss/` – `_variables.scss` (brand + Bootstrap overrides), `base/`, `layout/`, `components/`
- `components/toc/` – Single Directory Component: "On this page" navigation
- `src/Hook/PalefireHooks.php` – OOP theme hooks (TOC, section anchors, branding)
- `templates/paragraphs/` – section markup for Modular pages
- `scripts/palefire_modular_setup.php` – one-time Paragraphs/field setup (drush php:script)

## Modular pages

Any paragraph type with `field_section_title` placed in `field_sections` on a
`modular_page` node becomes a `<section>` with an H2, a stable anchor, and a TOC
entry. `field_full_page` turns on full-viewport scroll-snap (md and up).

### Building maps

A bento section can show a building floor map under its search form
(`bento_search.field_bento_map` -> `building_map` paragraph). Rooms show a
tooltip on hover (after a short wait, `--pf-map-tip-delay`, so sweeping the
pointer across the map doesn't flash tooltips), on keyboard focus, or on tap;
it fades in with a small bump (just a fade under reduced motion). The
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
(`paragraph--bento-search.html.twig`, `_bento-search.scss`). It is styled
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

### Calendar events in "On this page"

`modular-toc.js` lists any element with `data-toc` under its section, even
inside a `data-toc-skip` area, with optional `data-toc-label` (link text),
`data-toc-detail` (extra screen-reader text) and `data-toc-passive` (listed
but not highlighted by scroll position). A component that renders after
the TOC dispatches a bubbling `palefire:tocrefresh` event from inside its
section, and that section's sub-list is rebuilt and opens like a section
change. The LibCal day carousel uses this for its next three timed events.

## Page transitions (Swup 4)

`js/palefire-swup.js` swaps `#swup` (breadcrumb + body) and `#pf-primary-menu`
for anonymous visitors, re-running Drupal behaviors and syncing drupalSettings,
meta tags, and body classes. Pages that need new assets or contain inline
scripts fall back to a full load. Opt out per link with `data-no-swup`.
