/**
 * Copies vendor assets into the theme (cross-platform; no shell globbing).
 * - Bootstrap JS bundle -> js/vendor/
 * - Swup 4 UMD build -> js/vendor/swup.umd.js (global window.Swup)
 * - Selected Bootstrap Icons (individual SVGs) -> images/bi/
 *   Add icon names here when you use a new one in the utility menu.
 *   Browse names at https://icons.getbootstrap.com/
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const nm = path.join(root, 'node_modules');

const icons = [
  'search', 'person-circle', 'chat-dots', 'clock', 'calendar-event',
  'envelope', 'question-circle', 'box-arrow-in-right', 'geo-alt',
  'telephone', 'link-45deg', 'chevron-down',
  // Solid versions for the utility menu (Origami look; menu--utility.html.twig
  // falls back to the outline icon when one is missing).
  'person-fill', 'chat-dots-fill', 'clock-fill', 'question-circle-fill',
  'calendar-event-fill', 'envelope-fill', 'geo-alt-fill', 'telephone-fill',
  'gear', 'gear-fill',
  // Accounts drawer (utility-panels.html.twig).
  'book', 'journal-bookmark', 'truck', 'bank', 'mortarboard', 'credit-card',
  // Guide pages: resource type icons, accordion and video
  // (PALEFIRE_RESOURCE_ICONS in palefire.theme, paragraph templates).
  'file-earmark-text', 'database', 'tools', 'card-checklist', 'easel',
  'play-fill', 'file-earmark-pdf', 'file-earmark-word', 'download', 'person',
  'globe2', 'plus-lg',
];

function copy(from, to) {
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
  console.log(`copied ${path.relative(root, to)}`);
}

copy(
  path.join(nm, 'bootstrap/dist/js/bootstrap.bundle.min.js'),
  path.join(root, 'js/vendor/bootstrap.bundle.min.js'),
);

const swupDir = path.join(nm, 'swup');
if (!fs.existsSync(swupDir)) {
  console.warn('swup not installed; run `npm install` to enable page transitions.');
} else {
  // Use the package's browser (UMD) entry rather than hard-coding its name.
  const pkg = JSON.parse(fs.readFileSync(path.join(swupDir, 'package.json'), 'utf8'));
  const entry = [pkg.unpkg, pkg.jsdelivr, 'dist/Swup.umd.js'].find((f) => typeof f === 'string');
  copy(path.join(swupDir, entry), path.join(root, 'js/vendor/swup.umd.js'));
}

const iconSrc = path.join(nm, 'bootstrap-icons/icons');
if (!fs.existsSync(iconSrc)) {
  console.warn('bootstrap-icons not installed; run `npm install` to copy icons.');
} else {
  icons.forEach((name) => {
    const file = path.join(iconSrc, `${name}.svg`);
    if (fs.existsSync(file)) {
      copy(file, path.join(root, 'images/bi', `${name}.svg`));
    } else {
      console.warn(`icon not found: ${name}`);
    }
  });
}
