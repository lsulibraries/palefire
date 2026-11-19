// Run in the proxied /v2/oralhistory page (build/preview/proxy.js): rebuilds
// the Record panel as the Twig now prints it (D/E/F paths, Before you record
// notes, the recorder specimen) from the working tree's recorder-line twig
// and recorder.css, for checking before the Twig is deployed. Once the
// deployed page matches, it is only needed for new experiments.
(async () => {
  const add = (href) => new Promise((r) => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href + '?' + Date.now(); l.onload = r; document.head.append(l); });
  await add('/__local/recorder.css');
  const panel = document.querySelector('#record .pf-quick-guide__panel');
  const intro = panel.querySelector('.pf-quick-guide__intro');
  intro.innerHTML = 'Record an oral history yourself or with the Williams Center, so what you record is kept. Choose one of <strong>three ways</strong>: learn how (D), start a project (E), or get help (F).';
  const src = document.querySelector('#research .pf-paths');
  const paths = src.cloneNode(true);
  paths.classList.add('pf-paths--services');
  const data = [
    ['D', 'Learn', 'how', [['Workshops', 'Planning to interviewing'], ['Seminars', 'Taught at LSU']]],
    ['E', 'Start a', 'project', [['Create your own', 'Our guide to getting started'], ['Collaborate', 'A partnership with the Center']]],
    ['F', 'Get', 'help', [['Reach out', 'AskUs or email'], ['Consultations', 'By appointment']]],
  ];
  const lines = paths.querySelectorAll('.pf-paths__line');
  lines.forEach((line, i) => {
    const d = data[i];
    const L = line.querySelector('.pf-paths__letter'); L.lastChild.previousSibling.textContent = d[0];
    line.querySelector('.pf-paths__name-text').innerHTML = d[1] + ' <span class="pf-paths__name-rest">' + d[2] + '</span>';
    const stops = [...line.querySelectorAll('.pf-paths__stop')];
    stops.forEach((st, j) => {
      if (j >= d[3].length) { st.remove(); return; }
      st.querySelectorAll('.pf-paths__pop').forEach((p) => p.remove());
      const lab = st.querySelector('.pf-paths__label');
      const a = document.createElement('a'); a.className = 'pf-paths__label pf-paths__peek'; a.href = '#'; a.textContent = d[3][j][0];
      lab.replaceWith(a);
      st.querySelector('.pf-paths__note').textContent = d[3][j][1];
    });
  });
  panel.querySelector(':scope > .pf-tabs__content').replaceWith(paths);
  const visit = panel.querySelector(':scope > .pf-quick-guide__visit');
  visit.querySelector('.pf-quick-guide__recorder')?.remove();
  const notes = document.createElement('div');
  notes.innerHTML = '<h3 class="pf-quick-guide__visit-title">Before you record</h3><ul role="list"><li>Workshops follow the Oral History Association’s best practices, from planning to what happens after the interview.</li><li>Start small: decide what you want as a final product, then plan your timeline around it.</li><li>Research consultations are by appointment, in Room 201 at Hill.</li></ul>';
  visit.prepend(...notes.childNodes);
  const tw = await (await fetch('/__local/recorder-line.html.twig')).text();
  const svg = tw.replace(/\{#[\s\S]*?#\}\n/, '').replace(/\{\{ attach_library[^\n]*\n/, '').replace(/\{% set id[^\n]*\n/, '').split('{{ id }}').join('sim-rec');
  const spec = document.createElement('div'); spec.className = 'pf-quick-guide__specimen'; spec.setAttribute('aria-hidden', 'true'); spec.innerHTML = svg;
  visit.after(spec);
  document.querySelector('[aria-controls="record"], #record-tab')?.click();
  window.__sim = 1;
})();
