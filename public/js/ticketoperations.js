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
      this.element = document.createElement('div');
      this.element.className = 'modal fade ticketops-modal';
      this.element.tabIndex = -1;
      this.element.setAttribute('aria-hidden', 'true');
      this.element.innerHTML = this.template();
      document.body.append(this.element);
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
        ? `<input type="hidden" class="ticketops-actor" value="${onlyRequester.link_id}"><div class="form-control bg-secondary-lt">${escapeHtml(onlyRequesterLabel)}</div>`
        : '<select class="form-select ticketops-actor"></select>';
      const requesterFields = this.data.modules.requester ? `<div class="card card-sm mb-3"><div class="card-header"><h4 class="card-title">${escapeHtml(l.requester_section)}</h4></div><div class="card-body"><div class="mb-3"><label class="form-label">${escapeHtml(l.requester)}</label>${requesterChoice}</div><div><label class="form-label">${escapeHtml(l.search)}</label><div class="ticketops-native-requester"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div></div></div></div>` : '';
      return `<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="ti ti-adjustments me-2"></i>${escapeHtml(l.title)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${escapeHtml(l.close)}"></button></div><div class="modal-body"><p class="mb-1"><strong>#${this.data.ticketId} — ${escapeHtml(this.data.ticketTitle)}</strong></p><p class="text-muted mb-3">${escapeHtml(l.current_entity)} : #${this.data.entityId}</p><div class="ticketops-target-entity"></div>${requesterFields}<div class="ticketops-organization-selection"></div><div class="ticketops-preview mt-3"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>${escapeHtml(l.cancel)}</button><button type="button" class="btn btn-outline-primary ticketops-analyse" disabled><i class="ti ti-eye me-1"></i>${escapeHtml(l.preview)}</button><button type="button" class="btn btn-primary ticketops-execute" disabled><i class="ti ti-check me-1"></i>${escapeHtml(l.execute)}</button></div></div></div>`;
    }

    bind() {
      const actor = this.element.querySelector('.ticketops-actor');
      if (!actor) {
        this.element.querySelector('.ticketops-analyse').addEventListener('click', () => this.preview());
        this.element.querySelector('.ticketops-execute').addEventListener('click', () => this.execute());
        this.element.addEventListener('hidden.bs.modal', () => this.element.remove(), {once: true});
        return;
      }
      if (actor.tagName === 'SELECT') {
        this.data.requesters.forEach((item) => {
          const option = document.createElement('option');
          const emailOnly = item.itemtype === 'User' && item.items_id === 0;
          option.value = item.link_id;
          option.textContent = `${emailOnly ? this.data.labels.email : item.itemtype}: ${emailOnly ? item.alternative_email : (item.name || item.alternative_email)}`;
          actor.append(option);
        });
        const modalNode = window.jQuery(this.element);
        window.jQuery(actor).select2({
          width: '100%', dropdownParent: modalNode, minimumResultsForSearch: 0,
          templateResult: typeof templateResult === 'function' ? templateResult : undefined,
          templateSelection: typeof templateSelection === 'function' ? templateSelection : undefined,
        });
      }
      this.element.querySelector('.ticketops-analyse').addEventListener('click', () => this.preview());
      this.element.querySelector('.ticketops-execute').addEventListener('click', () => this.execute());
      this.element.addEventListener('hidden.bs.modal', () => this.element.remove(), {once: true});
    }

    loadRequesterField() {
      const container = this.element.querySelector('.ticketops-native-requester');
      if (!container) return;
      window.jQuery(container).load(`${this.data.nativeFieldsUrl}?scope=requester`, () => {
        const field = window.jQuery(container).find('.ticketops-user-search');
        field.on('change', async () => {
          const userId = Number.parseInt(field.val() || '0', 10);
          if (userId <= 0) {
            this.setupOrganizationOnly();
            return;
          }
          try {
            const response = await fetch(`${this.data.baseUrl}/Users/${userId}`, {credentials: 'same-origin'});
            const user = await response.json();
            if (!response.ok) throw new Error(user.error || `HTTP ${response.status}`);
            this.select(user);
          } catch (error) {
            this.showError(error.message);
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
      const target = this.element.querySelector('.ticketops-target-entity');
      const organization = this.element.querySelector('.ticketops-organization-selection');
      const showOrganization = this.data.modules.organization || this.data.modules.quick;
      const quick = this.data.modules.quick ? `<label class="form-check mb-3"><input class="form-check-input ticketops-quick" type="checkbox"><span class="form-check-label"><i class="ti ti-user-check me-1"></i>${escapeHtml(this.data.labels.assign_me)}</span></label>` : '';
      target.innerHTML = `<div class="card card-sm mb-3"><div class="card-header"><h4 class="card-title">${escapeHtml(this.data.labels.target_entity)}</h4></div><div class="card-body ticketops-native-entity"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div></div>`;
      organization.innerHTML = `<div class="ticketops-organization ${showOrganization ? '' : 'd-none'}"><h4>${escapeHtml(this.data.labels.optional_organization)}</h4>${quick}<div class="row"></div></div>`;
      organization.querySelector('.ticketops-quick')?.addEventListener('change', () => {
        this.plan = null;
        this.element.querySelector('.ticketops-execute').disabled = true;
      });
      const nativeEntity = target.querySelector('.ticketops-native-entity');
      const url = `${this.data.nativeFieldsUrl}?scope=entity&user_id=${encodeURIComponent(user.id || 0)}`;
      window.jQuery(nativeEntity).load(url, () => {
        const entityField = window.jQuery(nativeEntity).find('.ticketops-entity');
        entityField.on('change', () => {
          this.plan = null;
          this.element.querySelector('.ticketops-analyse').disabled = !entityField.val();
          this.element.querySelector('.ticketops-execute').disabled = true;
          this.mountOrganizationFields();
        });
        this.mountOrganizationFields();
        this.element.querySelector('.ticketops-analyse').disabled = !entityField.val();
      });
      this.element.querySelector('.ticketops-execute').disabled = true;
    }

    form(includeFingerprint = false) {
      const form = new FormData();
      form.set('replaced_actor_id', this.selectedUser.id > 0 ? (this.element.querySelector('.ticketops-actor')?.value || 0) : 0);
      form.set('new_requester_id', this.selectedUser.id);
      form.set('target_entity_id', this.element.querySelector('.ticketops-entity').value);
      ['category', 'location', 'technician', 'observer'].forEach((kind) => {
        const value = this.element.querySelector(`.ticketops-${kind}`)?.value;
        if (value) form.set(`${kind}_id`, value);
      });
      if (this.element.querySelector('.ticketops-quick')?.checked) {
        form.set('technician_id', this.data.currentUser.id);
        form.set('status_id', 2);
      }
      this.element.querySelectorAll('[data-remove-id]:checked').forEach((field) => form.append('remove_relation_ids[]', field.dataset.removeId));
      if (includeFingerprint) {
        form.set('fingerprint', this.plan.fingerprint);
        form.set('preview_token', this.plan.previewToken);
      }
      return form;
    }

    mountOrganizationFields() {
      const entityId = this.element.querySelector('.ticketops-entity')?.value;
      const container = this.element.querySelector('.ticketops-organization');
      if (!entityId || !container) return;
      container.classList.remove('d-none');
      const row = container.querySelector('.row');
      row.innerHTML = '<div class="col-12 text-center py-3"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div>';
      if (!this.data.modules.organization) return;
      window.jQuery(row).load(`${this.data.nativeFieldsUrl}?entity_id=${encodeURIComponent(entityId)}`);
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
        const requester = this.plan.newRequesterId > 0 ? `; ${escapeHtml(this.data.labels.requester)} #${this.plan.newRequesterId}` : `; ${escapeHtml(this.data.labels.no_requester_change)}`;
        this.element.querySelector('.ticketops-preview').innerHTML = `<div class="card"><div class="card-header"><strong>${escapeHtml(this.data.labels.expected)}</strong></div><div class="card-body"><p>${escapeHtml(this.data.labels.summary)}</p><p>${escapeHtml(this.data.labels.current_entity)} #${this.plan.sourceEntityId} → #${this.plan.targetEntityId}${requester}; ${this.plan.preservedRelations.length}</p>${organization ? `<ul>${organization}</ul>` : ''}${incompatible}<ul class="mb-0">${messages}</ul></div></div>`;
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
  window.GLPI.TicketOperations = Object.freeze({version: '0.1.7'});
})();
