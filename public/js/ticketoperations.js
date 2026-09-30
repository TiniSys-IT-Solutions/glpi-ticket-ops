(() => {
  'use strict';

  const escapeHtml = (value) => {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  };

  const post = async (url, data, csrfToken) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'X-Glpi-Csrf-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest'},
      body: data,
      credentials: 'same-origin',
    });
    const body = await response.json();
    if (!response.ok) throw new Error(body.error || `HTTP ${response.status}`);
    return body;
  };

  const healthPresentation = {
    success: {icon: 'ti-circle-check', colour: 'success'},
    information: {icon: 'ti-info-circle', colour: 'info'},
    warning: {icon: 'ti-alert-triangle', colour: 'warning'},
    blocking: {icon: 'ti-alert-octagon', colour: 'danger'},
  };

  const mountHealthShortcut = () => {
    const panel = document.querySelector('[data-ticketops]');
    const mainHeader = document.querySelector('#heading-main-item .accordion-button');
    if (!panel || !mainHeader || mainHeader.querySelector('.ticketops-health-shortcut')) return;
    const level = panel.dataset.ticketopsHealth || 'success';
    const count = Number.parseInt(panel.dataset.ticketopsCount || '0', 10);
    const presentation = healthPresentation[level] || healthPresentation.information;
    const shortcut = document.createElement('span');
    shortcut.className = `ticketops-health-shortcut badge bg-${presentation.colour}-lt text-${presentation.colour} ms-auto`;
    shortcut.setAttribute('role', 'button');
    shortcut.setAttribute('tabindex', '0');
    shortcut.setAttribute('title', panel.querySelector('.item-title')?.textContent?.trim() || 'TicketOps');
    shortcut.innerHTML = `<i class="ti ${presentation.icon}" aria-hidden="true"></i><span>TicketOps</span>${count > 0 ? `<span class="ticketops-health-count">${count}</span>` : ''}`;
    const openPanel = (event) => {
      event.preventDefault();
      event.stopPropagation();
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
    mainHeader.append(shortcut);
  };

  class TicketOpsDialog {
    constructor(panel, mode = 'requester') {
      this.data = JSON.parse(panel.dataset.ticketops);
      this.mode = mode;
      this.selectedUser = null;
      this.plan = null;
      this.element = document.createElement('div');
      this.element.className = 'modal fade ticketops-modal';
      this.element.tabIndex = -1;
      this.element.setAttribute('aria-hidden', 'true');
      this.element.innerHTML = this.template();
      document.body.append(this.element);
      this.modal = new bootstrap.Modal(this.element);
      this.bind();
      if (this.mode !== 'requester') this.setupOrganizationOnly();
      this.modal.show();
    }

    template() {
      const l = this.data.labels;
      const requesterFields = this.mode === 'requester' ? `<div class="mb-3"><label class="form-label">${escapeHtml(l.requester)}</label><select class="form-select ticketops-actor"></select></div><div class="mb-3"><label class="form-label">${escapeHtml(l.search)}</label><select class="form-select ticketops-user-search"></select></div>` : '';
      return `<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="ti ti-user-edit me-2"></i>${escapeHtml(this.mode === 'requester' ? l.title : l.organize_title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${escapeHtml(l.close)}"></button></div><div class="modal-body"><p class="mb-1"><strong>#${this.data.ticketId} — ${escapeHtml(this.data.ticketTitle)}</strong></p><p class="text-muted mb-3">${escapeHtml(l.current_entity)} : #${this.data.entityId}</p>${requesterFields}<div class="ticketops-selection"></div><div class="ticketops-preview mt-3"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>${escapeHtml(l.cancel)}</button><button type="button" class="btn btn-outline-primary ticketops-analyse" disabled><i class="ti ti-eye me-1"></i>${escapeHtml(l.preview)}</button><button type="button" class="btn btn-primary ticketops-execute" disabled><i class="ti ti-check me-1"></i>${escapeHtml(this.mode === 'requester' ? l.execute : l.apply)}</button></div></div></div>`;
    }

    bind() {
      const actor = this.element.querySelector('.ticketops-actor');
      if (!actor) {
        this.element.querySelector('.ticketops-analyse').addEventListener('click', () => this.preview());
        this.element.querySelector('.ticketops-execute').addEventListener('click', () => this.execute());
        this.element.addEventListener('hidden.bs.modal', () => this.element.remove(), {once: true});
        return;
      }
      this.data.requesters.forEach((item) => {
        const option = document.createElement('option');
        const emailOnly = item.itemtype === 'User' && item.items_id === 0;
        option.value = item.link_id;
        option.textContent = `${emailOnly ? this.data.labels.email : item.itemtype}: ${emailOnly ? item.alternative_email : (item.name || item.alternative_email)}`;
        actor.append(option);
      });
      const modalNode = window.jQuery(this.element);
      window.jQuery(actor).select2({width: '100%', dropdownParent: modalNode});
      window.jQuery(this.element.querySelector('.ticketops-user-search')).select2({
        width: '100%',
        dropdownParent: modalNode,
        minimumInputLength: 2,
        ajax: {
          url: `${this.data.baseUrl}/Users`,
          dataType: 'json',
          delay: 350,
          data: (params) => ({q: params.term, page: params.page || 1}),
          processResults: (body) => ({results: body.results.map((user) => ({id: user.id, text: `${user.label} — ${user.email}`, user})), pagination: {more: Boolean(body.more)}}),
        },
      }).on('select2:select', (event) => this.select(event.params.data.user));
      this.element.querySelector('.ticketops-analyse').addEventListener('click', () => this.preview());
      this.element.querySelector('.ticketops-execute').addEventListener('click', () => this.execute());
      this.element.addEventListener('hidden.bs.modal', () => this.element.remove(), {once: true});
    }

    setupOrganizationOnly() {
      const entities = this.data.operatorEntities || [];
      this.selectedUser = {id: 0};
      this.select({id: 0, entities, suggested_entity_id: entities.some((entity) => entity.id === this.data.entityId) ? this.data.entityId : null});
    }

    select(user) {
      this.selectedUser = user;
      this.plan = null;
      const options = user.entities.map((entity) => `<option value="${entity.id}" ${entity.id === user.suggested_entity_id ? 'selected' : ''}>${escapeHtml(entity.name)}</option>`).join('');
      const selection = this.element.querySelector('.ticketops-selection');
      selection.innerHTML = `<div class="mb-3"><label class="form-label">${escapeHtml(this.data.labels.target_entity)}</label><select class="form-select ticketops-entity"><option value="">${escapeHtml(this.data.labels.choose)}</option>${options}</select></div><div class="ticketops-organization d-none"><h4>${escapeHtml(this.data.labels.optional_organization)}</h4><div class="row"></div></div>`;
      window.jQuery(selection.querySelector('.ticketops-entity')).select2({width: '100%', dropdownParent: window.jQuery(this.element)});
      window.jQuery(selection.querySelector('.ticketops-entity')).on('change', () => this.mountOrganizationFields());
      this.mountOrganizationFields();
      this.element.querySelector('.ticketops-analyse').disabled = false;
      this.element.querySelector('.ticketops-execute').disabled = true;
    }

    form(includeFingerprint = false) {
      const form = new FormData();
      form.set('replaced_actor_id', this.element.querySelector('.ticketops-actor')?.value || 0);
      form.set('new_requester_id', this.selectedUser.id);
      form.set('target_entity_id', this.element.querySelector('.ticketops-entity').value);
      ['category', 'location', 'group', 'technician'].forEach((kind) => {
        const value = this.element.querySelector(`.ticketops-${kind}`)?.value;
        if (value) form.set(`${kind}_id`, value);
      });
      if (this.mode === 'quick') form.set('status_id', 2);
      this.element.querySelectorAll('[data-remove-id]:checked').forEach((field) => form.append('remove_relation_ids[]', field.dataset.removeId));
      if (includeFingerprint) form.set('fingerprint', this.plan.fingerprint);
      return form;
    }

    mountOrganizationFields() {
      const entityId = this.element.querySelector('.ticketops-entity')?.value;
      const container = this.element.querySelector('.ticketops-organization');
      if (!entityId || !container) return;
      container.classList.remove('d-none');
      const row = container.querySelector('.row');
      row.innerHTML = '';
      ['category', 'location', 'group', 'technician'].forEach((kind) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'col-md-6 mb-3';
        wrapper.innerHTML = `<label class="form-label">${escapeHtml(this.data.labels[kind])}</label><select class="form-select ticketops-${kind}"><option value="">${escapeHtml(this.data.labels.keep)}</option></select>`;
        row.append(wrapper);
        const field = window.jQuery(wrapper.querySelector('select')).select2({
          width: '100%', dropdownParent: window.jQuery(this.element), minimumInputLength: 2, allowClear: true,
          ajax: {
            url: this.data.organizationUrl, dataType: 'json', delay: 350,
            data: (params) => ({kind, entity_id: entityId, q: params.term, page: params.page || 1}),
            processResults: (body) => ({results: body.results, pagination: {more: Boolean(body.more)}}),
          },
        });
        if (this.mode === 'quick' && kind === 'technician') {
          const current = this.data.currentUser;
          field.append(new Option(current.name, current.id, true, true)).trigger('change');
        }
      });
    }

    async preview() {
      if (!this.element.querySelector('.ticketops-entity')?.value) return;
      try {
        this.plan = await post(`${this.data.baseUrl}/Ticket/${this.data.ticketId}/Preview`, this.form(), this.data.csrfToken);
        const incompatible = this.plan.incompatibilities.map((relation) => relation.removable ? `<label class="form-check"><input class="form-check-input" type="checkbox" data-remove-id="${relation.link_id}"> <span class="form-check-label">${escapeHtml(this.data.labels.remove)} — ${escapeHtml(relation.itemtype)}: ${escapeHtml(relation.name)}</span></label>` : `<div class="alert alert-danger">${escapeHtml(relation.itemtype)}: ${escapeHtml(relation.name)}</div>`).join('');
        const messages = [...this.plan.warnings, ...this.plan.blockers].map((message) => `<li>${escapeHtml(message)}</li>`).join('');
        const organization = Object.keys(this.plan.organizationChanges || {}).map((kind) => {
          const field = this.element.querySelector(`.ticketops-${kind}`);
          return `<li>${escapeHtml(this.data.labels[kind])}: ${escapeHtml(field?.selectedOptions?.[0]?.textContent || `#${this.plan.organizationChanges[kind]}`)}</li>`;
        }).join('');
        this.element.querySelector('.ticketops-preview').innerHTML = `<div class="card"><div class="card-header"><strong>${escapeHtml(this.data.labels.expected)}</strong></div><div class="card-body"><p>${escapeHtml(this.data.labels.summary)}</p><p>${escapeHtml(this.data.labels.current_entity)} #${this.plan.sourceEntityId} → #${this.plan.targetEntityId}; ${escapeHtml(this.data.labels.requester)} #${this.plan.newRequesterId}; ${this.plan.preservedRelations.length}</p>${organization ? `<ul>${organization}</ul>` : ''}${incompatible}<ul class="mb-0">${messages}</ul></div></div>`;
        this.element.querySelectorAll('[data-remove-id]').forEach((field) => field.addEventListener('change', () => this.preview()));
        this.element.querySelector('.ticketops-execute').disabled = !this.plan.isExecutable;
      } catch (error) {
        this.showError(error.message);
      }
    }

    async execute() {
      try {
        const body = await post(`${this.data.baseUrl}/Ticket/${this.data.ticketId}/Execute`, this.form(true), this.data.csrfToken);
        window.location.assign(body.redirect);
      } catch (error) {
        this.showError(error.message);
      }
    }

    showError(message) {
      this.element.querySelector('.ticketops-preview').innerHTML = `<div class="alert alert-danger">${escapeHtml(message)}</div>`;
    }
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountHealthShortcut, {once: true});
    else mountHealthShortcut();
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.ticketops-open');
      if (button) new TicketOpsDialog(button.closest('[data-ticketops]'), button.dataset.ticketopsMode || 'requester');
    });
  }

  window.GLPI = window.GLPI || {};
  window.GLPI.TicketOperations = Object.freeze({version: '0.1.0'});
})();
