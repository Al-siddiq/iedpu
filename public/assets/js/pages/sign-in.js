import { PortalAuthClient } from '../core/auth-client.js';
import { initPasswordVisibility } from '../core/password-visibility.js';

const client = new PortalAuthClient(document.documentElement.dataset.baseUrl);
const resetPasswordVisibility = initPasswordVisibility();
const $ = id => document.getElementById(id);
const loginForm = $('login-form');
const mfaSection = $('mfa-section');
const setupSection = $('setup-section');
const mfaForm = $('mfa-form');
const complete = $('complete-section');
const notice = $('notice');
const loginButton = $('login-submit');
const mfaButton = $('mfa-submit');

function message(value) { notice.textContent = value || ''; }
function clearSecrets() {
  $('password').value = '';
  resetPasswordVisibility();
  $('mfa-code').value = '';
  $('setup-key').textContent = '';
  $('recovery-codes').textContent = '';
  $('qr').replaceChildren();
}
function showLogin() {
  clearSecrets();
  loginForm.hidden = false;
  mfaSection.hidden = true;
  setupSection.hidden = true;
  complete.hidden = true;
  $('recovery-section').hidden = true;
}
function errorText(result) {
  if (result.status === 429) return 'Too many attempts. Please wait a few minutes before trying again.';
  if (result.status === 403) return 'Security token expired. Please try again.';
  return result.error?.message || 'We could not complete your request. Please try again.';
}
function finish(data) {
  clearSecrets();
  loginForm.hidden = true;
  mfaSection.hidden = true;
  complete.hidden = false;
  if (Array.isArray(data.new_recovery_codes) && data.new_recovery_codes.length > 0) {
    $('recovery-codes').textContent = data.new_recovery_codes.join('\n');
    $('recovery-section').hidden = false;
    message('Authenticator setup complete. Save your one-time recovery codes before signing out.');
  } else {
    $('recovery-section').hidden = true;
    message('Signed in securely.');
  }
}
loginForm.addEventListener('submit', async event => {
  event.preventDefault();
  message('');
  loginButton.disabled = true;
  try {
    const identifier = $('identifier').value.trim();
    const password = $('password').value;
    const result = await client.login(identifier, password);
    $('password').value = '';
    resetPasswordVisibility();
    if (!result.success) {
      message(errorText(result));
      return;
    }
    if (result.data?.mfa_required) {
      loginForm.hidden = true;
      mfaSection.hidden = false;
      setupSection.hidden = true;
      $('mfa-title').textContent = result.data.enrolment_required
        ? 'Set up your authenticator' : 'Enter your security code';
      if (result.data.enrolment_required) {
        const setup = await client.mfaSetup();
        if (!setup.success) { message(errorText(setup)); showLogin(); return; }
        setupSection.hidden = false;
        $('setup-key').textContent = setup.data.manual_key;
        if (typeof window.QRCode === 'function') {
          new window.QRCode($('qr'), {
            text: setup.data.otpauth_uri, width: 200, height: 200,
            correctLevel: window.QRCode.CorrectLevel.M,
          });
        } else {
          message('QR code unavailable. Enter the displayed setup key manually in your authenticator app.');
        }
      }
      $('mfa-code').focus();
    } else {
      finish(result.data);
    }
  } catch {
    message('Unable to connect securely. Check your internet connection and try again.');
  } finally {
    loginButton.disabled = false;
  }
});
mfaForm.addEventListener('submit', async event => {
  event.preventDefault();
  message('');
  mfaButton.disabled = true;
  try {
    const result = await client.mfaVerify($('mfa-code').value.trim());
    $('mfa-code').value = '';
    if (!result.success) {
      message(errorText(result));
      if (result.status === 401 && result.error?.code === 'mfa_session_expired') showLogin();
      return;
    }
    finish(result.data);
  } catch {
    message('Verification could not be completed. Please try again.');
  } finally {
    mfaButton.disabled = false;
  }
});
$('restart').addEventListener('click', () => {
  showLogin();
  message('Start again by entering your email or phone number and password.');
});
$('logout').addEventListener('click', async () => {
  try {
    const result = await client.logout();
    if (!result.success) { message(errorText(result)); return; }
    showLogin();
    message('Signed out.');
  } catch {
    message('Could not sign out. Please retry. For safety, close this browser if you are using a shared device.');
  }
});
(async () => {
  try {
    const result = await client.session();
    if (result.success && result.data?.authenticated) finish(result.data);
  } catch {
    // Network errors do not reveal whether an account exists.
  }
})();
