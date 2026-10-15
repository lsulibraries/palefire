(function (Drupal, once) {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  Drupal.behaviors.palefireBentoVideo = {
    attach(context) {
      once('bento-video', '[data-bento-video]', context).forEach((wrap) => {
        const video = wrap.querySelector('video');
        const btn = wrap.querySelector('[data-bento-video-toggle]');
        const sources = JSON.parse(wrap.dataset.sources || '[]');
        if (!sources.length) return;
        let i = 0;
        let paused = reduce.matches;
        const play = () => { if (!paused) video.play().catch(() => {}); };
        const load = (n) => { i = n % sources.length; video.src = sources[i]; };
        video.loop = sources.length === 1;
        video.addEventListener('ended', () => { load(i + 1); play(); });
        btn.addEventListener('click', () => {
          paused = !paused;
          btn.textContent = paused ? Drupal.t('Play background video') : Drupal.t('Pause background video');
          paused ? video.pause() : play();
        });
        if (paused) btn.textContent = Drupal.t('Play background video');
        load(0);
        new IntersectionObserver(([e]) => (e.isIntersecting ? play() : video.pause()),
          { threshold: 0.25 }).observe(wrap);
      });
    },
  };
})(Drupal, once);
