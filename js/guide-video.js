/**
 * @file
 * Guide: Video. Click to load.
 *
 * The block renders a link to the video over its local thumbnail
 * (paragraph--guide-video.html.twig). Pressing it replaces the link with the
 * player (data-pf-video: a youtube-nocookie.com or player.vimeo.com URL with
 * autoplay), so nothing loads from the provider until someone asks for it.
 * Blocks with no data-pf-video (other providers) keep the plain link.
 */
((Drupal, once) => {
  Drupal.behaviors.palefireGuideVideo = {
    attach(context) {
      once('pf-video', '.pf-video[data-pf-video]', context).forEach((box) => {
        const link = box.querySelector('.pf-video__link');
        if (!link) {
          return;
        }
        link.addEventListener('click', (event) => {
          // Let modified clicks (new tab, etc.) open the video's page.
          if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
          }
          event.preventDefault();
          const frame = document.createElement('iframe');
          frame.src = box.dataset.pfVideo;
          frame.title = box.dataset.title || Drupal.t('Video');
          frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
          frame.allowFullscreen = true;
          frame.referrerPolicy = 'strict-origin-when-cross-origin';
          link.replaceWith(frame);
          // Keep keyboard focus where it was: on the player.
          frame.focus();
        });
      });
    },
  };
})(Drupal, once);
