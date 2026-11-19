/**
 * @file
 * Ask Special Collections: the question form
 * (templates/form/libanswers-ask-form.html.twig, styled in _ask.scss).
 * Also the Ask Us drawer's copies (Main Library and Special Collections),
 * which utility-panels.js fetches and places when first shown; each posts
 * to its own route. The form names its desk (data-pf-ask-to) and its AskUs
 * page (data-pf-askus); follow-up fields show as their rule allows.
 *
 * Checks the required fields and the email, asks the visitor to agree to
 * "Before you send" (a modal <dialog>: ask in your own words, staff may
 * follow up to confirm), then posts the form to Drupal
 * in the background, so the visitor stays on the page. The libanswers
 * module sends it on to LibAnswers as a ticket in its queue (Special Collections or the Main Library)
 * (through the API; Honeypot keeps bots out) and, for this header
 * (X-LibAnswers-Fetch), answers JSON: 200 { ok } once the ticket exists,
 * 422 { errors: { field: message } } for fields to fix, 502 when
 * LibAnswers refuses it. A failure points to AskUs instead. Without this
 * script the form posts as usual and the page returns with a message.
 *
 * Errors: each invalid field gets aria-invalid and its message under it
 * (aria-describedby), and focus moves to the first. The outcome is said in
 * the status card (role="status"), which takes focus so it is read; it
 * rises from behind the Send bar and sinks again once the visitor types.
 * It also dates the slip's stamp with today's date, as the visitor's own
 * clock has it (a date in the markup would freeze in Drupal's page cache).
 */
(function (Drupal, once) {
  'use strict';

  const ASKUS = 'https://askus.lib.lsu.edu/special';

  // Follow-ups (a cell with data-pf-when, from the libanswers module's
  // rules): shown, and switched on so they're sent, only while the field
  // they follow has their value; a follow-up of a hidden one stays hidden.
  const follow = (form) => {
    form.querySelectorAll('[data-pf-when]').forEach((cell) => {
      const parent = form.elements[cell.dataset.pfWhen];
      const show = !!parent && !parent.disabled && parent.value === cell.dataset.pfWhenValue;
      cell.hidden = !show;
      cell.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !show;
      });
    });
  };

  const messageFor = (field) => {
    if (field.validity.valueMissing) {
      return field.tagName === 'SELECT'
        ? Drupal.t('Choose an option.')
        : Drupal.t('Enter your @field.', { '@field': field.labels[0].firstChild.textContent.trim().toLowerCase() });
    }
    if (field.validity.typeMismatch) {
      return Drupal.t('Enter an email address like name@example.com.');
    }
    return '';
  };

  const setError = (field, text) => {
    const error = document.getElementById(`${field.id}-error`);
    field.setAttribute('aria-invalid', text ? 'true' : 'false');
    if (text) {
      field.setAttribute('aria-describedby', error.id);
    }
    else {
      field.removeAttribute('aria-describedby');
    }
    error.textContent = text;
    error.hidden = !text;
  };

  // The status card (_ask.scss): data-kind raises it from behind the Send
  // key; the words go in its text, so the icons stay.
  const timers = new WeakMap();

  const say = (form, text, kind, link) => {
    const status = form.querySelector('.pf-inquiry__status');
    const words = status.querySelector('.pf-inquiry__status-text') || status;
    clearTimeout(timers.get(status));
    words.textContent = text;
    status.dataset.kind = kind;
    if (link) {
      const a = document.createElement('a');
      a.href = form.dataset.pfAskus || ASKUS;
      a.textContent = Drupal.t('Ask on AskUs instead');
      words.append(' ', a, '.');
    }
    if (kind !== 'busy') {
      status.focus();
    }
  };

  // The card sinks back behind the key once the visitor types again, so it
  // never sits over a field they're filling in. Its words go after the
  // slide, so nothing stale is left to read.
  const unsay = (form) => {
    const status = form.querySelector('.pf-inquiry__status');
    if (!status.dataset.kind || status.dataset.kind === 'busy') {
      return;
    }
    delete status.dataset.kind;
    timers.set(status, setTimeout(() => {
      (status.querySelector('.pf-inquiry__status-text') || status).textContent = '';
    }, 400));
  };

  const showErrors = (form, invalid) => {
    say(form, Drupal.formatPlural(invalid.length, 'Check 1 field above.', 'Check @count fields above.'), 'error');
    invalid[0].focus();
  };

  const validate = (form) => {
    const invalid = [];
    form.querySelectorAll('[required]').forEach((field) => {
      const text = messageFor(field);
      setError(field, text);
      if (text) {
        invalid.push(field);
      }
    });
    return invalid;
  };

  // The form's fields with the button that sent it (Form API reads which).
  const fieldsOf = (form, submitter) => {
    try {
      return new FormData(form, submitter);
    }
    catch (e) {
      const data = new FormData(form);
      if (submitter && submitter.name) {
        data.append(submitter.name, submitter.value);
      }
      return data;
    }
  };

  const send = async (form, submitter) => {
    const button = form.querySelector('.pf-inquiry__submit');
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    say(form, Drupal.t('Sending…'), 'busy');
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: fieldsOf(form, submitter),
        headers: { 'X-LibAnswers-Fetch': '1' },
        credentials: 'same-origin',
      });
      const data = await response.json().catch(() => ({}));
      if (response.status === 422 && data.errors) {
        // The server's word on a field, under that field; anything else
        // (Honeypot) as a failure.
        const invalid = Object.entries(data.errors).map(([name, text]) => {
          const field = form.elements[name];
          if (field && field.id && document.getElementById(`${field.id}-error`)) {
            setError(field, text);
            return field;
          }
          return null;
        }).filter(Boolean);
        if (invalid.length) {
          showErrors(form, invalid);
          return;
        }
      }
      if (!response.ok || !data.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const email = form.elements.pemail.value;
      form.reset();
      follow(form);
      // Analytics: that a question was sent, never what it said
      // (Drupal.palefireTrack(), js/palefire.js).
      Drupal.palefireTrack?.('ask-form', 'sent');
      say(form, Drupal.t('Thank you. Your question is with @desk; we’ll reply to @email.', { '@desk': form.dataset.pfAskTo || Drupal.t('Special Collections'), '@email': email }), 'done');
    }
    catch (e) {
      say(form, Drupal.t('Sorry, your question couldn’t be sent.'), 'error', true);
      Drupal.palefireTrack?.('ask-form', 'failed');
    }
    finally {
      button.disabled = false;
      form.removeAttribute('aria-busy');
    }
  };

  // Before you send (.pf-ask-confirm, in the form's template): a modal
  // <dialog> with a statement against AI-written requests. Only "Agree and
  // send" sends; Cancel, Escape and a click on the backdrop close it and
  // put focus back on Send. Escape stops here, so the Ask Us drawer
  // (Bootstrap's offcanvas, which also closes on Escape) stays open.
  // Without showModal() (very old browsers) the form simply sends.
  const confirmThen = (form, submitter) => {
    const dialog = form.querySelector('.pf-ask-confirm');
    if (!dialog || typeof dialog.showModal !== 'function') {
      send(form, submitter);
      return;
    }
    dialog.pfSubmitter = submitter;
    dialog.returnValue = '';
    // The pledge in the visitor's own name, as typed in the form (required,
    // so there by now); the template's wording stays if it's blank.
    // Drupal.t() escapes @name, so the markup is safe to set.
    const pledge = dialog.querySelector('.pf-ask-confirm__pledge');
    const name = form.elements.pname?.value.trim();
    if (pledge && name) {
      pledge.innerHTML = Drupal.t('I, <strong>@name</strong>, confirm my question is accurate to the best of my knowledge.', { '@name': name });
    }
    dialog.showModal();
    // The dialog itself, not Agree: its name and statement are read first,
    // and the Enter that pressed Send (or a held key repeating) can't agree
    // at once. Tab reaches Cancel, then Agree. On a short phone screen the
    // sheet scrolls, so it opens at its top, the title showing.
    dialog.focus({ preventScroll: true });
    dialog.scrollTop = 0;
  };

  const wireConfirm = (form) => {
    const dialog = form.querySelector('.pf-ask-confirm');
    if (!dialog) {
      return;
    }
    dialog.addEventListener('click', (event) => {
      const choice = event.target.closest('[data-pf-confirm]')?.dataset.pfConfirm;
      if (choice) {
        dialog.close(choice);
      }
      // The backdrop: a click on the dialog itself, outside its sheet.
      else if (event.target === dialog) {
        dialog.close();
      }
    });
    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.stopPropagation();
      }
    });
    dialog.addEventListener('close', () => {
      if (dialog.returnValue === 'agree') {
        send(form, dialog.pfSubmitter);
      }
      else {
        form.querySelector('.pf-inquiry__submit')?.focus();
      }
      dialog.pfSubmitter = null;
    });
  };

  Drupal.behaviors.palefireAskForm = {
    attach(context) {
      once('pf-inquiry-form', '.pf-inquiry__form', context).forEach((form) => {
        const today = form.closest('.pf-inquiry__slip')?.querySelector('[data-pf-today]');
        if (today) {
          const now = new Date();
          today.textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          today.setAttribute('datetime', now.toISOString().slice(0, 10));
        }
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          if (form.getAttribute('aria-busy')) {
            return;
          }
          const invalid = validate(form);
          if (invalid.length) {
            showErrors(form, invalid);
            return;
          }
          confirmThen(form, event.submitter);
        });
        wireConfirm(form);
        follow(form);
        form.addEventListener('input', () => unsay(form));
        // Clear a field's error once it is fixed, not on every key.
        form.addEventListener('change', (event) => {
          follow(form);
          const field = event.target;
          if (field.getAttribute('aria-invalid') === 'true' && !messageFor(field)) {
            setError(field, '');
          }
        });
      });
    },
  };
})(Drupal, once);
