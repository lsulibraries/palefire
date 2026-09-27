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
