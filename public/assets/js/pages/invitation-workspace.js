'use strict';

(() => {
  const root = document.documentElement;
  const baseUrl = root.dataset.baseUrl;
  const canRequest = root.dataset.canRequest === '1';
  const canApprove = root.dataset.canApprove === '1';
  const notice = document.getElementById('notice');
  const candidate = document.getElementById('candidate');
  const requestForm = document.getElementById('request-form');
  const pendingList = document.getElementById('pending-list');
  const myRequestsList = document.getElementById('my-requests-list');
  let csrf = null;
  let state = { candidates: [], pending_approvals: [], my_requests: [] };

  const url = path => new URL(path.replace(/^\//, ''), baseUrl).toString();
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[char]);

  function message(text, type = '') {
    notice.textContent = text;
    notice.className = 'notice' + (type ? ' ' + type : '');
  }

  async function refreshCsrf() {
    const response = await fetch(url('api/v1/auth/csrf'), {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok || payload?.success !== true || !payload.data?.header || !payload.data?.token) {
      throw new Error('Security token unavailable. Reload this page and sign in again.');
    }
    csrf = { header: payload.data.header, token: payload.data.token };
  }

  async function api(path, method = 'GET', body = undefined) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
      if (!csrf) await refreshCsrf();
      headers['Content-Type'] = 'application/json';
      headers[csrf.header] = csrf.token;
    }
    const response = await fetch(url(path), {
      method, credentials: 'same-origin', cache: 'no-store',
      headers, body: body === undefined ? undefined : JSON.stringify(body)
    });
    const payload = await response.json();
    if (!response.ok || payload?.success !== true) {
      throw new Error(payload?.error?.message || 'The request could not be completed. Check your permissions and try again.');
    }
    if (body !== undefined) csrf = null;
    return payload.data;
  }

  function renderCandidates() {
    if (!candidate) return;
    candidate.replaceChildren();
    if (!state.candidates.length) {
      candidate.add(new Option('No eligible invitation recipients found', ''));
      candidate.disabled = true;
      return;
    }
    candidate.disabled = false;
    candidate.add(new Option('Select an account', ''));
    for (const item of state.candidates) {
      candidate.add(new Option('User ' + item.target_user_id + ' · ' + item.email,
        item.target_user_id + ':' + item.identifier_id));
    }
  }

  function renderPending() {
    if (!pendingList) return;
    if (!state.pending_approvals.length) {
      pendingList.innerHTML = '<p class="empty">There are no invitation requests waiting for your approval.</p>';
      return;
    }
    pendingList.innerHTML = state.pending_approvals.map(item => `
      <article class="record" data-request-id="${Number(item.id)}">
        <div class="record-top"><div><strong>Invitation for User ${Number(item.target_user_id)}</strong>
          <p>Recipient: ${escapeHtml(item.email)}</p><p>Submitted by Officer ${Number(item.requested_by_user_id)} · ${escapeHtml(item.request_reason)}</p></div>
          <span class="status">Awaiting approval</span></div>
        <label for="decision-${Number(item.id)}">Decision note</label>
        <input class="decision-reason" id="decision-${Number(item.id)}" minlength="5" maxlength="500"
          value="Reviewed for local invitation acceptance." aria-label="Decision note">
        <div class="record-actions">
          <button class="action approve" type="button" data-action="approve" data-id="${Number(item.id)}">Approve request</button>
          <button class="action reject" type="button" data-action="reject" data-id="${Number(item.id)}">Reject request</button>
        </div>
      </article>`).join('');
  }

  function renderMyRequests() {
    if (!myRequestsList) return;
    if (!state.my_requests.length) {
      myRequestsList.innerHTML = '<p class="empty">You have not submitted an invitation request yet.</p>';
      return;
    }
    myRequestsList.innerHTML = state.my_requests.map(item => {
      const approved = item.approval_status === 'approved';
      const label = String(item.approval_status || 'unknown');
      return `<article class="record" data-request-id="${Number(item.id)}">
        <div class="record-top"><div><strong>Invitation for User ${Number(item.target_user_id)}</strong>
          <p>Recipient: ${escapeHtml(item.email)}</p><p>${escapeHtml(item.request_reason)}</p></div>
          <span class="status ${approved ? 'approved' : ''}">${escapeHtml(label)}</span></div>
        <div class="record-actions">
          ${approved && item.execution_status === 'pending' ? `<button class="action issue" type="button" data-action="issue" data-id="${Number(item.id)}">Issue invitation</button>` : ''}
          ${approved && item.execution_status === 'executed' ? `<button class="action" type="button" data-action="preview" data-id="${Number(item.id)}">Get local activation token</button>` : ''}
        </div>
        <div class="local-result" id="local-result-${Number(item.id)}"></div>
      </article>`;
    }).join('');
  }

  async function load() {
    message('Loading your invitation workspace…');
    const data = await api('api/v1/auth/invitations/workspace');
    state = data;
    renderCandidates();
    renderPending();
    renderMyRequests();
    if (!data.can_request && !data.can_approve) {
      message('Your account can view this workspace but has no invitation action assigned.', 'error');
    } else {
      message('Your invitation workspace is ready. Use the section assigned to your officer role.', 'success');
    }
  }

  if (requestForm) {
    requestForm.addEventListener('submit', async event => {
      event.preventDefault();
      const selected = String(candidate.value || '').split(':');
      if (selected.length !== 2 || !selected[0] || !selected[1]) {
        message('Select an eligible invitation recipient first.', 'error');
        return;
      }
      const submit = requestForm.querySelector('button[type="submit"]');
      submit.disabled = true;
      try {
        await api('api/v1/auth/invitations/request', 'POST', {
          target_user_id: Number(selected[0]), identifier_id: Number(selected[1]),
          scope: 'global', zone_id: null, branch_id: null,
          reason: requestForm.elements.reason.value.trim()
        });
        requestForm.reset();
        requestForm.elements.reason.value = 'Local IEDPU invitation workflow acceptance test.';
        message('Invitation request submitted. Sign in as Officer B to approve it.', 'success');
        await load();
      } catch (error) {
        message(error.message, 'error');
      } finally {
        submit.disabled = false;
      }
    });
  }

  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const action = button.dataset.action;
    const requestId = Number(button.dataset.id);
    button.disabled = true;
    try {
      if (action === 'approve' || action === 'reject') {
        const record = button.closest('.record');
        const reason = record.querySelector('.decision-reason').value.trim();
        if (reason.length < 5) throw new Error('Enter a short decision note.');
        await api('api/v1/auth/invitations/decide', 'POST', {
          approval_request_id: requestId, approve: action === 'approve', reason
        });
        message(action === 'approve'
          ? 'Request approved. Officer A can now issue the invitation.'
          : 'Request rejected.', 'success');
        await load();
      } else if (action === 'issue') {
        await api('api/v1/auth/invitations/issue', 'POST', { approval_request_id: requestId });
        message('Invitation issued and placed in the local delivery outbox. Retrieve the local activation token below.', 'success');
        await load();
        await showPreview(requestId);
      } else if (action === 'preview') {
        await showPreview(requestId);
      }
    } catch (error) {
      message(error.message, 'error');
    } finally {
      button.disabled = false;
    }
  });

  async function showPreview(requestId) {
    const result = await api('api/v1/auth/invitations/local-preview', 'POST', {
      approval_request_id: requestId
    });
    const box = document.getElementById('local-result-' + requestId);
    if (!box) return;
    box.innerHTML = `<div class="token-box"><strong>Local invitation details</strong>
      <p>Account ID: <strong>${Number(result.user_id)}</strong></p>
      <p>Reserved test email: ${escapeHtml(result.email)}</p>
      <p>Invitation token:</p><code>${escapeHtml(result.token)}</code>
      <div><button class="copy-button" type="button" data-copy-token="${escapeHtml(result.token)}">Copy token</button>
      <a class="secondary" target="_blank" rel="noopener noreferrer" href="${escapeHtml(url('invitation/activate'))}">Open activation page</a></div>
      <p class="small">On the activation page, enter the account ID and token above, then set the recipient's password. This local preview does not send email.</p></div>`;
  }

  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-copy-token]');
    if (!button) return;
    try {
      await navigator.clipboard.writeText(button.dataset.copyToken);
      button.textContent = 'Copied';
    } catch {
      button.textContent = 'Copy unavailable — select the token above';
    }
  });

  load().catch(error => message(error.message, 'error'));
})();
