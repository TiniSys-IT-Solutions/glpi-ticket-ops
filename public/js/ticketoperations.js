(() => {
  'use strict';

  const escapeHtml = (value) => {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  };

  const responseError = (body, status) => {
    const messages = [body?.error, body?.message, body?.title];
    return messages.find((message) => typeof message === 'string' && message.trim() !== '') || `HTTP ${status}`;
  };

  const post = async (url, data, csrfToken) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Accept': 'application/json', 'X-Glpi-Csrf-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest'},
      body: data,
      credentials: 'same-origin',
    });
    const body = await response.json().catch(() => null);
    if (!response.ok || body?.ok === false || body?.error === true) throw new Error(responseError(body, response.status));
    if (body === null || typeof body !== 'object') throw new Error(`HTTP ${response.status}`);
    return body;
  };

  const healthPresentation = {
    success: {icon: 'ti-circle-check', colour: 'success'},
    information: {icon: 'ti-info-circle', colour: 'info'},
    warning: {icon: 'ti-alert-triangle', colour: 'warning'},
    blocking: {icon: 'ti-alert-octagon', colour: 'danger'},
  };

  const findingTargets = {
    email_requester: 'requester',
    inactive_requester: 'requester',
    invalid_requester_entity: 'requester',
    missing_category: 'category',
    unqualified_email: 'category',
    missing_location: 'location',
    missing_technician: 'technician',
    incompatible_actor: 'entity',
  };

  const findingPriority = {success: 0, information: 1, warning: 2, blocking: 3};

  const mountHealthShortcut = () => {
    const panel = document.querySelector('[data-ticketops]');
    const title = document.querySelector('#navigationheader .navigationheader-title');
    if (!panel || !title || title.querySelector('.ticketops-health-shortcut')) return;
    const level = panel.dataset.ticketopsHealth || 'success';
    const count = Number.parseInt(panel.dataset.ticketopsCount || '0', 10);
    const presentation = healthPresentation[level] || healthPresentation.information;
    const shortcut = document.createElement('span');
    shortcut.className = `ticketops-health-shortcut badge bg-${presentation.colour}-lt text-${presentation.colour} ms-2`;
    shortcut.setAttribute('role', 'button');
    shortcut.setAttribute('tabindex', '0');
    shortcut.setAttribute('title', panel.querySelector('.item-title')?.textContent?.trim() || 'TicketOps');
    shortcut.innerHTML = `<i class="ti ${presentation.icon}" aria-hidden="true"></i><span class="ticketops-health-label">TicketOps</span>${count > 0 ? `<span class="ticketops-health-count">${count}</span>` : ''}`;
    const openPanel = (event) => {
      event.preventDefault();
      event.stopPropagation();
      const data = JSON.parse(panel.dataset.ticketops);
      if (data.canOperate) {
        new TicketOpsDialog(panel);
        return;
      }
      const collapse = panel.querySelector('.accordion-collapse');
      if (collapse && typeof bootstrap !== 'undefined') bootstrap.Collapse.getOrCreateInstance(collapse, {toggle: false}).show();
      panel.scrollIntoView({behavior: 'smooth', block: 'center'});
      panel.classList.add('ticketops-focus');
      window.setTimeout(() => panel.classList.remove('ticketops-focus'), 1400);
    };
    shortcut.addEventListener('click', openPanel);
    shortcut.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') openPanel(event);
    });
    title.append(shortcut);
  };

  class TicketOpsDialog {
    constructor(panel) {
      this.data = JSON.parse(panel.dataset.ticketops);
      this.selectedUser = null;
      this.plan = null;
      this.busy = false;
      this.fieldsReady = false;
      this.requesterLoading = false;
      this.element = document.createElement('div');
      this.element.className = 'modal fade ticketops-modal';
      this.element.tabIndex = -1;
      this.element.setAttribute('aria-hidden', 'true');
      this.element.innerHTML = this.template();
      document.body.append(this.element);
      this.markDiagnosticFields();
      this.modal = new bootstrap.Modal(this.element);
      this.bind();
      this.setupOrganizationOnly();
      this.modal.show();
      this.loadRequesterField();
    }

    template() {
      const l = this.data.labels;
      const onlyRequester = this.data.requesters.length === 1 ? this.data.requesters[0] : null;
      const onlyRequesterLabel = onlyRequester ? `${onlyRequester.itemtype === 'User' && onlyRequester.items_id === 0 ? l.email : onlyRequester.itemtype}: ${onlyRequester.itemtype === 'User' && onlyRequester.items_id === 0 ? onlyRequester.alternative_email : (onlyRequester.name || onlyRequester.alternative_email)}` : '';
      const requesterChoice = onlyRequester
        ? `<input type="hidden" class="ticketops-actor" value="${escapeHtml(onlyRequester.key)}"><div class="form-control bg-secondary-lt">${escapeHtml(onlyRequesterLabel)}</div>`
        : '<select class="form-select ticketops-actor"></select>';
      const requesterFields = this.data.modules.requester ? `<section class="mb-3"><h4>${escapeHtml(l.requester_section)}</h4><div class="mb-3 ticketops-field-wrapper"><label class="form-label">${escapeHtml(l.requester)}</label>${requesterChoice}</div><div><label class="form-label">${escapeHtml(l.search)}</label><div class="ticketops-native-requester"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div></div></section>` : '';
      const heading = `${l.title} — #${this.data.ticketId} — ${this.data.ticketTitle}`;
      const titleField = this.data.modules.organization ? `<div class="mb-3"><label class="form-label" for="ticketops-title-${this.data.ticketId}">${escapeHtml(l.ticket_title)}</label><input type="text" id="ticketops-title-${this.data.ticketId}" class="form-control ticketops-title" value="${escapeHtml(this.data.ticketTitle)}" maxlength="255" required></div>` : '';
      return `<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title ticketops-dialog-title" title="${escapeHtml(heading)}"><i class="ti ti-adjustments me-2" aria-hidden="true"></i><span class="text-truncate">${escapeHtml(heading)}</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${escapeHtml(l.close)}"></button></div><div class="modal-body">${titleField}<div class="ticketops-target-entity"></div>${requesterFields}<div class="ticketops-organization-selection"></div><div class="ticketops-feedback mt-3" role="alert" aria-live="polite"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>${escapeHtml(l.cancel)}</button><button type="button" class="btn btn-primary ticketops-execute" disabled><i class="ti ti-check me-1"></i>${escapeHtml(l.execute)}</button></div></div></div>`;
    }

    bind() {
      const actor = this.element.querySelector('.ticketops-actor');
      if (actor?.tagName === 'SELECT') {
        this.data.requesters.forEach((item) => {
          const option = document.createElement('option');
          const emailOnly = item.itemtype === 'User' && item.items_id === 0;
          option.value = item.key;
          option.textContent = `${emailOnly ? this.data.labels.email : item.itemtype}: ${emailOnly ? item.alternative_email : (item.name || item.alternative_email)}`;
          actor.append(option);
        });
        window.jQuery(actor).select2({
          width: '100%', dropdownParent: window.jQuery(this.element), minimumResultsForSearch: 0,
          templateResult: typeof templateResult === 'function' ? templateResult : undefined,
          templateSelection: typeof templateSelection === 'function' ? templateSelection : undefined,
        });
      }
      this.element.querySelector('.ticketops-execute').addEventListener('click', () => this.execute());
      this.element.addEventListener('hidden.bs.modal', () => this.element.remove(), {once: true});
    }

    loadRequesterField() {
      const container = this.element.querySelector('.ticketops-native-requester');
      if (!container) return;
      window.jQuery(container).load(`${this.data.nativeFieldsUrl}?scope=requester`, (_response, status) => {
        if (status === 'error') {
          this.showError(this.data.labels.validation_failed);
          return;
        }
        const field = window.jQuery(container).find('.ticketops-user-search');
        field.on('change', async () => {
          const revision = this.requesterRevision = (this.requesterRevision || 0) + 1;
          const userId = Number.parseInt(field.val() || '0', 10);
          if (userId <= 0) {
            this.requesterLoading = false;
            this.setupOrganizationOnly();
            return;
          }
          this.selectedUser = null;
          this.requesterLoading = true;
          this.updateApplyState();
          try {
            const response = await fetch(`${this.data.baseUrl}/Users/${userId}`, {credentials: 'same-origin'});
            const user = await response.json();
            if (revision !== this.requesterRevision) return;
            if (!response.ok) throw new Error(responseError(user, response.status));
            this.select(user);
          } catch (error) {
            if (revision !== this.requesterRevision) return;
            this.fieldsReady = false;
            this.showError(error.message);
          } finally {
            if (revision === this.requesterRevision) {
              this.requesterLoading = false;
              this.updateApplyState();
            }
          }
        });
      });
    }

    setupOrganizationOnly() {
      const entities = this.data.operatorEntities || [];
      this.selectedUser = {id: 0};
      this.select({id: 0, entities, suggested_entity_id: entities.some((entity) => entity.id === this.data.entityId) ? this.data.entityId : null});
    }

    select(user) {
      this.selectedUser = user;
      this.plan = null;
      this.fieldsReady = false;
      this.updateApplyState();
      this.element.querySelector('.ticketops-feedback').innerHTML = '';
      const target = this.element.querySelector('.ticketops-target-entity');
      const organization = this.element.querySelector('.ticketops-organization-selection');
      const showOrganization = this.data.modules.organization || this.data.modules.quick;
      const quick = this.data.modules.quick ? `<label class="form-check mb-3"><input class="form-check-input ticketops-quick" type="checkbox"><span class="form-check-label"><i class="ti ti-user-check me-1"></i>${escapeHtml(this.data.labels.assign_me)}</span></label>` : '';
      target.innerHTML = `<div class="card card-sm mb-3"><div class="card-header"><h4 class="card-title">${escapeHtml(this.data.labels.replace_entity.replace('%s', this.data.entityName))}</h4></div><div class="card-body ticketops-native-entity"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div></div>`;
      organization.innerHTML = `<div class="ticketops-organization ${showOrganization ? '' : 'd-none'}"><h4>${escapeHtml(this.data.labels.optional_organization)}</h4>${quick}<div class="row"></div></div>`;
      this.markDiagnosticFields();
      const nativeEntity = target.querySelector('.ticketops-native-entity');
      const url = `${this.data.nativeFieldsUrl}?scope=entity&user_id=${encodeURIComponent(user.id || 0)}`;
      window.jQuery(nativeEntity).load(url, (_response, status) => {
        if (!this.element.contains(nativeEntity)) return;
        if (status === 'error') {
          this.showError(this.data.labels.validation_failed);
          return;
        }
        const entityField = window.jQuery(nativeEntity).find('.ticketops-entity');
        entityField.on('change', () => {
          this.plan = null;
          this.element.querySelector('.ticketops-feedback').innerHTML = '';
          this.mountOrganizationFields();
        });
        this.mountOrganizationFields();
      });
    }

    updateApplyState() {
      this.element.querySelector('.ticketops-execute').disabled = this.busy || this.requesterLoading || !this.selectedUser || !this.fieldsReady;
    }

    form() {
      const form = new FormData();
      form.set('replaced_actor_key', this.selectedUser.id > 0 ? (this.element.querySelector('.ticketops-actor')?.value || '') : '');
      form.set('new_requester_id', this.selectedUser.id);
      form.set('target_entity_id', this.element.querySelector('.ticketops-entity').value);
      const title = this.element.querySelector('.ticketops-title');
      if (title && title.value !== this.data.ticketTitle) form.set('ticket_title', title.value);
      ['category', 'location', 'technician', 'observer'].forEach((kind) => {
        const value = this.element.querySelector(`.ticketops-${kind}`)?.value;
        if (value) form.set(`${kind}_id`, value);
      });
      if (this.element.querySelector('.ticketops-quick')?.checked) {
        form.set('technician_id', this.data.currentUser.id);
        form.set('status_id', 2);
      }
      this.element.querySelectorAll('[data-remove-key]:checked').forEach((field) => form.append('remove_relation_keys[]', field.dataset.removeKey));
      return form;
    }

    mountOrganizationFields() {
      this.fieldsReady = false;
      this.updateApplyState();
      const entityId = this.element.querySelector('.ticketops-entity')?.value;
      const container = this.element.querySelector('.ticketops-organization');
      if (!entityId || !container) return;
      const row = document.createElement('div');
      row.className = 'row';
      container.querySelector('.row').replaceWith(row);
      if (!this.data.modules.organization) {
        this.fieldsReady = true;
        this.updateApplyState();
        return;
      }
      row.innerHTML = '<div class="col-12 text-center py-3"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div>';
      const revision = this.organizationRevision = (this.organizationRevision || 0) + 1;
      window.jQuery(row).load(`${this.data.nativeFieldsUrl}?entity_id=${encodeURIComponent(entityId)}`, (_response, status) => {
        if (revision !== this.organizationRevision || !this.element.contains(row)) return;
        if (status === 'error') {
          this.showError(this.data.labels.validation_failed);
          return;
        }
        this.markDiagnosticFields();
        this.fieldsReady = true;
        this.updateApplyState();
      });
    }

    markDiagnosticFields() {
      const targets = {};
      (this.data.findings || []).forEach((finding) => {
        const target = findingTargets[finding.code];
        if (!target || (findingPriority[finding.level] || 0) <= (findingPriority[targets[target]?.level] || 0)) return;
        targets[target] = finding;
      });
      Object.entries(targets).forEach(([target, finding]) => {
        let element = null;
        if (target === 'requester') element = this.element.querySelector('.ticketops-actor')?.closest('.ticketops-field-wrapper');
        else if (target === 'entity') element = this.element.querySelector('.ticketops-target-entity .card');
        else element = this.element.querySelector(`.ticketops-${target}`)?.closest('.col-md-6');
        if (!element) return;
        element.classList.remove('ticketops-information', 'ticketops-warning', 'ticketops-blocking');
        element.classList.add('ticketops-field-alert', `ticketops-${finding.level}`);
        element.setAttribute('title', finding.message);
      });
    }

    showBlockers(plan) {
      const confirmed = new Set([...this.element.querySelectorAll('[data-remove-key]:checked')].map((field) => field.dataset.removeKey));
      const incompatible = plan.incompatibilities.filter((relation) => relation.removable).map((relation) => `<label class="form-check"><input class="form-check-input" type="checkbox" data-remove-key="${escapeHtml(relation.key)}" ${confirmed.has(relation.key) ? 'checked' : ''}><span class="form-check-label">${escapeHtml(this.data.labels.remove)} — ${escapeHtml(relation.itemtype)}: ${escapeHtml(relation.name)}</span></label>`).join('');
      const messages = plan.blockers.map((message) => `<li>${escapeHtml(message)}</li>`).join('');
      this.element.querySelector('.ticketops-feedback').innerHTML = `<div class="alert alert-danger"><div>${escapeHtml(this.data.labels.validation_failed)}</div><ul class="mb-0">${messages}</ul></div>${incompatible}`;
    }

    async execute() {
      if (this.busy || this.requesterLoading || !this.selectedUser || !this.fieldsReady) return;
      const title = this.element.querySelector('.ticketops-title');
      if (title && title.value !== this.data.ticketTitle && !title.reportValidity()) return;
      const form = this.form();
      this.busy = true;
      this.updateApplyState();
      const controls = [...this.element.querySelectorAll('.modal-body input, .modal-body select')];
      const disabled = controls.map((field) => field.disabled);
      controls.forEach((field) => { field.disabled = true; });
      this.element.setAttribute('aria-busy', 'true');
      try {
        this.plan = await post(`${this.data.baseUrl}/Ticket/${this.data.ticketId}/Preview`, form, this.data.csrfToken);
        if (!this.plan.isExecutable) {
          this.showBlockers(this.plan);
          return;
        }
        form.set('fingerprint', this.plan.fingerprint);
        form.set('preview_token', this.plan.previewToken);
        const body = await post(`${this.data.baseUrl}/Ticket/${this.data.ticketId}/Execute`, form, this.data.csrfToken);
        window.location.assign(body.redirect);
      } catch (error) {
        this.showError(error.message);
      } finally {
        controls.forEach((field, index) => { field.disabled = disabled[index]; });
        this.busy = false;
        this.element.setAttribute('aria-busy', 'false');
        this.updateApplyState();
      }
    }

    showError(message) {
      this.element.querySelector('.ticketops-feedback').innerHTML = `<div class="alert alert-danger">${escapeHtml(message)}</div>`;
    }
  }

  if (typeof document !== 'undefined') {
    const start = () => {
      mountHealthShortcut();
      new MutationObserver(mountHealthShortcut).observe(document.body, {childList: true, subtree: true});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.ticketops-open');
      if (button) new TicketOpsDialog(button.closest('[data-ticketops]'));
    });
  }

  window.GLPI = window.GLPI || {};
  window.GLPI.TicketOperations = Object.freeze({version: '0.1.11'});
})();
