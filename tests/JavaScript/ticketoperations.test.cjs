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

  assert.equal(window.GLPI.TicketOperations.version, '0.0.1');
  assert.equal(Object.isFrozen(window.GLPI.TicketOperations), true);
});

