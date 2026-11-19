/**
 * Local preview proxy on http://localhost:8766: serves lib.lsu.edu pages
 * with the working tree's css/style.css added and js/ledger-photo.js
 * swapped in, so uncommitted theme changes can be checked on the live
 * content. /__local/<name> serves a file from css/, js/,
 * templates/includes/ (.twig) or this folder (sim.js).
 *
 * Usage: PF_DEBUG_TOKEN=<token> node build/preview/proxy.js
 * The token is the X-Debug-Token from CLAUDE.md; it is never kept here.
 */
const http = require('http'), https = require('https'), fs = require('fs'), path = require('path');
const REPO = path.resolve(__dirname, '../..');
const TOKEN = process.env.PF_DEBUG_TOKEN;
if (!TOKEN) { console.error('Set PF_DEBUG_TOKEN (the X-Debug-Token).'); process.exit(1); }
const reswap = `<script>
(function(){
  document.querySelectorAll('.pf-ledger-photo').forEach(function(l){l.remove();});
  document.querySelectorAll('.has-ledger-photos').forEach(function(s){s.classList.remove('has-ledger-photos');});
  document.querySelectorAll('.pf-ledger-photo__labels').forEach(function(b){var t=document.querySelector('.pf-tiles--ledger');t&&t.append(b);});
  document.querySelectorAll('[data-ledger-photos]').forEach(function(g){g.removeAttribute('data-once');});
  var s=document.createElement('script');s.src='/__local/ledger-photo.js';
  s.onload=function(){Drupal.behaviors.palefireLedgerPhoto.attach(document);window.__swapped=1;};
  document.body.append(s);
})();
</script>`;
http.createServer((req, res) => {
  if (req.url.startsWith('/__local/')) {
    const name = req.url.slice(9).split('?')[0];
    const file = name.endsWith('.css') ? REPO + '/css/' + name : name.endsWith('.twig') ? REPO + '/templates/includes/' + name : name === 'sim.js' ? path.join(__dirname, 'sim.js') : REPO + '/js/' + name;
    res.writeHead(200, { 'content-type': name.endsWith('.css') ? 'text/css' : 'text/javascript' });
    return res.end(fs.readFileSync(file));
  }
  const up = https.request({ host: 'lib.lsu.edu', path: req.url, method: 'GET', rejectUnauthorized: false,
    headers: { 'X-Debug-Token': TOKEN, 'accept-encoding': 'identity', 'user-agent': 'Mozilla/5.0 local-test' } }, (r) => {
    const h = { ...r.headers }; delete h['content-security-policy']; delete h['strict-transport-security'];
    if (h.location) h.location = h.location.replace(/^https?:\/\/lib\.lsu\.edu/, '');
    const isPage = (r.headers['content-type'] || '').includes('text/html');
    if (!isPage) { res.writeHead(r.statusCode, h); return r.pipe(res); }
    let body = ''; r.setEncoding('utf8'); r.on('data', (c) => body += c); r.on('end', () => {
      body = body.split('https://lib.lsu.edu/').join('/').split('http://lib.lsu.edu/').join('/');
      body = body.replace('</head>', () => '<link rel="stylesheet" href="/__local/style.css"></head>').replace('</body>', () => reswap + '</body>');
      delete h['content-length']; res.writeHead(r.statusCode, h); res.end(body);
    });
  });
  up.on('error', (e) => { res.writeHead(502); res.end(String(e)); });
  up.end();
}).listen(8766, () => console.log('proxy on 8766'));
