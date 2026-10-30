/**
 * @file
 * Ask Special Collections: the question form
 * (templates/form/libanswers-ask-form.html.twig, styled in _ask.scss).
 *
 * Checks the required fields and the email, then posts the form to Drupal
 * in the background, so the visitor stays on the page. The libanswers
 * module sends it on to LibAnswers as a ticket in the Special Collections
 * queue (through the API; Honeypot keeps bots out) and, for this header
 * (X-LibAnswers-Fetch), answers JSON: 200 { ok } once the ticket exists,
 * 422 { errors: { field: message } } for fields to fix, 502 when
 * LibAnswers refuses it. A failure points to AskUs instead. Without this
 * script the form posts as usual and the page returns with a message.
 *
 * Errors: each invalid field gets aria-invalid and its message under it
 * (aria-describedby), and focus moves to the first. The outcome is said in
 * the status line (role="status"), which takes focus so it is read.
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
            showErrors(form, invalid);
            return;
          }
          send(form, event.submitter);
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
