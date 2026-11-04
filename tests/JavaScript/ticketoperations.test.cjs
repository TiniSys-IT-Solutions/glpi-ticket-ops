'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

test('the bootstrap exposes an immutable namespaced version', () => {
  const source = fs.readFileSync(path.join(__dirname, '../../public/js/ticketoperations.js'), 'utf8');
  const window = {};
  vm.runInNewContext(source, {window});

  assert.equal(window.GLPI.TicketOperations.version, '0.2.0');
  assert.equal(Object.isFrozen(window.GLPI.TicketOperations), true);
});

test('mutations use GLPI security and native interface components', () => {
  const source = fs.readFileSync(path.join(__dirname, '../../public/js/ticketoperations.js'), 'utf8');

  assert.match(source, /X-Glpi-Csrf-Token/);
  assert.match(source, /credentials: 'same-origin'/);
  assert.match(source, /new bootstrap\.Modal/);
  assert.match(source, /\.select2\(/);
  assert.match(source, /ticketops-quick/);
  assert.doesNotMatch(source, /this\.mode/);
  assert.match(source, /nativeFieldsUrl/);
  assert.match(source, /navigationheader-title/);
  assert.match(source, /ticketops-field-alert/);
});

const makeDialog = (responses) => {
  const source = fs.readFileSync(path.join(__dirname, '../../public/js/ticketoperations.js'), 'utf8');
  const window = {};
  const calls = [];
  const redirects = [];
  const controls = [{disabled: false}, {disabled: true}];
  const button = {disabled: false};
  const feedback = {innerHTML: ''};
  window.location = {assign: (url) => redirects.push(url)};
  vm.runInNewContext(source.replace('  if (typeof document', '  window.Dialog = TicketOpsDialog;\n  if (typeof document'), {
    window,
    document: undefined,
    FormData,
    fetch: async (url, options) => {
      calls.push({url, data: Object.fromEntries(options.body), headers: options.headers});
      const response = responses.shift();
      if (response instanceof Error) throw response;
      return {
        ok: response.httpStatus === undefined || response.httpStatus < 400,
        status: response.httpStatus || 200,
        json: async () => response.httpStatus === undefined ? response : response.body,
      };
    },
  });
  const dialog = Object.create(window.Dialog.prototype);
  Object.assign(dialog, {
    data: {ticketId: 10, baseUrl: '/TicketOps', csrfToken: 'csrf'},
    fieldsReady: true,
    requesterLoading: false,
    selectedUser: {id: 0},
    busy: false,
    element: {
      querySelector: (selector) => ({'.ticketops-execute': button, '.ticketops-feedback': feedback}[selector] || null),
      querySelectorAll: () => controls,
      setAttribute: () => {},
    },
    form: () => {
      const form = new FormData();
      form.set('ticket_title', 'Corrected title');
      return form;
    },
  });
  return {dialog, calls, redirects, controls, button, feedback};
};

test('apply validates once and sends the same choices with the issued token', async () => {
  const {dialog, calls, redirects, controls, button} = makeDialog([
    {isExecutable: true, fingerprint: 'state', previewToken: 'token'},
    {redirect: '/ticket/10'},
  ]);
  const applying = dialog.execute();
  await dialog.execute(); // A second click during validation must not submit again.
  await applying;

  assert.equal(calls.length, 2);
  assert.match(calls[0].url, /\/Preview$/);
  assert.match(calls[1].url, /\/Execute$/);
  assert.deepEqual(calls[1].data, {ticket_title: 'Corrected title', fingerprint: 'state', preview_token: 'token'});
  assert.equal(calls[1].headers['X-Glpi-Csrf-Token'], 'csrf');
  assert.deepEqual(redirects, ['/ticket/10']);
  assert.deepEqual(controls.map((field) => field.disabled), [false, true]);
  assert.equal(button.disabled, false);
});

test('blocking validation displays the issues without executing', async () => {
  const plan = {isExecutable: false, blockers: ['Explicit removal required']};
  const {dialog, calls, redirects} = makeDialog([plan]);
  let shown;
  dialog.showBlockers = (value) => { shown = value; };
  await dialog.execute();

  assert.equal(shown, plan);
  assert.equal(calls.length, 1);
  assert.deepEqual(redirects, []);
  assert.equal(dialog.busy, false);
});

test('failed execution displays the error and allows a fresh validation', async () => {
  const {dialog, calls, redirects, button} = makeDialog([
    {isExecutable: true, fingerprint: 'state', previewToken: 'token'},
    new Error('The ticket changed'),
  ]);
  let error;
  dialog.showError = (message) => { error = message; };
  await dialog.execute();

  assert.equal(error, 'The ticket changed');
  assert.equal(calls.length, 2);
  assert.deepEqual(redirects, []);
  assert.equal(button.disabled, false);
});

test('native GLPI JSON errors display the message rather than the boolean flag', async () => {
  const {dialog, calls, redirects, button} = makeDialog([
    {httpStatus: 500, body: {error: true, title: 'Internal server error', message: 'An unexpected error occurred', trace: 'Private server details'}},
  ]);
  let error;
  dialog.showError = (message) => { error = message; };
  await dialog.execute();

  assert.equal(error, 'An unexpected error occurred');
  assert.equal(calls.length, 1);
  assert.deepEqual(redirects, []);
  assert.equal(button.disabled, false);
});

test('plugin errors retain their text and missing GLPI messages fall back to HTTP status', async () => {
  for (const [body, expected] of [
    [{ok: false, error: 'The ticket changed'}, 'The ticket changed'],
    [{error: true}, 'HTTP 409'],
  ]) {
    const {dialog} = makeDialog([{httpStatus: 409, body}]);
    let error;
    dialog.showError = (message) => { error = message; };
    await dialog.execute();
    assert.equal(error, expected);
  }
});


test('native choices preserve typed requester and removal identities', () => {
  const {dialog} = makeDialog([]);
  delete dialog.form;
  dialog.selectedUser = {id: 99};
  dialog.data.ticketTitle = 'Synthetic';
  const fields = {
    '.ticketops-actor': {value: 'actor:User:1:7'},
    '.ticketops-entity': {value: '6'},
    '.ticketops-title': {value: 'Corrected title'},
    '.ticketops-category': {value: '20'},
    '.ticketops-quick': {checked: false},
  };
  dialog.element.querySelector = (selector) => fields[selector] || null;
  dialog.element.querySelectorAll = () => [{dataset: {removeKey: 'actor:Group:2:7'}}];
  const form = dialog.form();

  assert.equal(form.get('replaced_actor_key'), 'actor:User:1:7');
  assert.equal(form.get('new_requester_id'), '99');
  assert.equal(form.get('category_id'), '20');
  assert.equal(form.get('ticket_title'), 'Corrected title');
  assert.deepEqual(form.getAll('remove_relation_keys[]'), ['actor:Group:2:7']);
});
