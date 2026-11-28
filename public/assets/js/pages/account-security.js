import { PortalAuthClient } from '../core/auth-client.js';
import { initPasswordVisibility } from '../core/password-visibility.js';

const resetPasswordVisibility = initPasswordVisibility();
const form = document.getElementById('password-form');
const status = document.getElementById('status');
const button = document.getElementById('submit');
const client = new PortalAuthClient(document.documentElement.dataset.baseUrl);

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  status.textContent = '';
  const current = form.elements.current_password.value;
  const next = form.elements.new_password.value;
  const confirm = form.elements.confirm_password.value;
  if (next !== confirm) {
    status.textContent = 'The new passwords do not match.';
    return;
  }
  button.disabled = true;
  try {
    await client.refreshCsrf();
    const result = await client.request('api/v1/auth/password/change', {
      method:'POST', csrf:true,
      body: {current_password:current,new_password:next,confirm_password:confirm},
    });
    if (result.success && result.data?.password_changed) {
      form.reset();
      form.hidden = true;
      status.textContent = 'Password changed. All existing sessions have been invalidated. Sign in again with your new password and authenticator.';
    } else {
      status.textContent = result.error?.message || 'Password change was not completed.';
    }
  } catch (_) {
    status.textContent = 'Unable to complete the request. Check your connection and try again.';
  } finally {
    // Never retain passwords in input fields after any submission.
    form.reset();
    resetPasswordVisibility();
    button.disabled = false;
  }
});