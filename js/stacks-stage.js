/**
 * @file
 * From the stacks' stage (paragraph--landing-stacks.html.twig): the
 * section's large picture follows the item under the pointer, or with
 * keyboard focus, in its lists. An item names its picture by data-pf-stage
 * (a frame the template rendered: the feature, a post) or data-pf-stage-src
 * (an https picture, an event's from js/upcoming.js, loaded on first use).
 * An item without one, and leaving the lists, bring back the home frame
 * (Instagram's), after a moment so moving between items doesn't flash
 * it. The frames crossfade (CSS, _stacks.scss; a cut with reduced motion).
 * The stage is decorative (aria-hidden): the lists carry the words.
 */
(function (Drupal, once) {
  'use strict';

  // Every link in the lists: one without a picture brings the home frame.
  const ITEMS = 'a[href]';

  Drupal.behaviors.palefireStacksStage = {
    attach(context) {
      once('pf-stacks-stage', '.pf-stacks', context).forEach((stacks) => {
        const stage = stacks.querySelector('.pf-stacks__stage');
        const lists = stacks.querySelector('.pf-stacks__lists');
        if (!stage || !lists) {
          return;
        }
        const frames = new Map(Array.from(stage.querySelectorAll('[data-pf-frame]'), (frame) => [frame.dataset.pfFrame, frame]));
        const home = stage.querySelector('.is-home');
        let timer = 0;

        const show = (frame) => {
          window.clearTimeout(timer);
          stage.querySelectorAll('.pf-stacks__frame.is-shown').forEach((shown) => {
            if (shown !== frame) {
              shown.classList.remove('is-shown');
            }
          });
          if (frame) {
            frame.classList.add('is-shown');
          }
          stage.classList.toggle('is-empty', !frame);
        };

        const frameFor = (item) => {
          if (item.dataset.pfStage && frames.has(item.dataset.pfStage)) {
            return frames.get(item.dataset.pfStage);
          }
          const src = item.dataset.pfStageSrc || '';
          if (!/^https:\/\//.test(src)) {
            return home;
          }
          if (!frames.has(src)) {
            const frame = document.createElement('span');
            frame.className = 'pf-stacks__frame';
            frame.dataset.pfFrame = src;
            const img = document.createElement('img');
            img.alt = '';
            img.decoding = 'async';
            img.src = src;
            frame.append(img);
            stage.append(frame);
            frames.set(src, frame);
          }
          return frames.get(src);
        };

        const follow = (event) => {
          const item = event.target.closest(ITEMS);
          if (item && lists.contains(item)) {
            show(frameFor(item));
          }
        };
        const back = () => {
          window.clearTimeout(timer);
          timer = window.setTimeout(() => show(home), 300);
        };

        lists.addEventListener('pointerover', follow);
        lists.addEventListener('focusin', follow);
        lists.addEventListener('pointerleave', back);
        lists.addEventListener('focusout', (event) => {
          if (!lists.contains(event.relatedTarget)) {
            back();
          }
        });
      });
    },
  };
})(Drupal, once);
