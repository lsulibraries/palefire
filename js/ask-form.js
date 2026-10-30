/**
 * @file
 * Ask Special Collections: the question form
 * (templates/includes/ask-special.html.twig, styled in _ask.scss).
 *
 * Checks the required fields and the email, then posts the form to
 * LibAnswers (its action, the Special Collections widget's endpoint) in
 * the background, so the question lands in the same queue as one sent
 * from AskUs and the visitor stays on the page. LibAnswers allows the
 * cross-origin post and answers JSON: { message } when the question is
 * in, { error } (and a 4xx) when not; 449 means it wants a CAPTCHA, which
 * the widget it copies has off. Any failure points to AskUs instead.
 *
 * Errors: each invalid field gets aria-invalid and its message under it
 * (aria-describedby), and focus moves to the first. The outcome is said in
 * the status line (role="status"), which takes focus so it is read.
 * A filled honeypot (contact_me_by_fax_only) is a bot: it is thanked and
 * nothing is sent.
 */
(function (Drupal, once) {
  'use strict';

  const ASKUS = 'https://askus.lib.lsu.edu/special';

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

  const say = (form, text, kind, link) => {
    const status = form.querySelector('.pf-inquiry__status');
    status.textContent = text;
    status.dataset.kind = kind;
    if (link) {
      const a = document.createElement('a');
      a.href = ASKUS;
      a.textContent = Drupal.t('Ask on AskUs instead');
      status.append(' ', a, '.');
    }
    if (kind !== 'busy') {
      status.focus();
    }
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

  const send = async (form) => {
    const button = form.querySelector('.pf-inquiry__submit');
    if (form.elements.contact_me_by_fax_only.value) {
      form.reset();
      say(form, Drupal.t('Thank you. Your question has been sent.'), 'done');
      return;
    }
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    say(form, Drupal.t('Sending…'), 'busy');
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.error) {
        throw new Error(response.status === 449 ? 'captcha' : 'error');
      }
      const email = form.elements.pemail.value;
      form.reset();
      say(form, Drupal.t('Thank you. Your question is with Special Collections; we’ll reply to @email.', { '@email': email }), 'done');
    }
    catch (e) {
      say(form, Drupal.t('Sorry, your question couldn’t be sent.'), 'error', true);
    }
    finally {
      button.disabled = false;
      form.removeAttribute('aria-busy');
    }
  };

  Drupal.behaviors.palefireAskForm = {
    attach(context) {
      once('pf-inquiry-form', '.pf-inquiry__form', context).forEach((form) => {
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          if (form.getAttribute('aria-busy')) {
            return;
          }
          const invalid = validate(form);
          if (invalid.length) {
            say(form, Drupal.formatPlural(invalid.length, 'Check 1 field above.', 'Check @count fields above.'), 'error');
            invalid[0].focus();
            return;
          }
          send(form);
        });
        // Clear a field's error once it is fixed, not on every key.
        form.addEventListener('change', (event) => {
          const field = event.target;
          if (field.getAttribute('aria-invalid') === 'true' && !messageFor(field)) {
            setError(field, '');
          }
        });
      });
    },
  };
})(Drupal, once);
