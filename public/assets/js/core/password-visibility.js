/**
 * Accessible opt-in password visibility. No persistence, network requests,
 * storage or mutation of the password value.
 */
export function initPasswordVisibility(root = document) {
  const controls = [...root.querySelectorAll('[data-password-toggle]')];
  for (const button of controls) {
    const target = button.getAttribute('aria-controls');
    const input = target ? document.getElementById(target) : null;
    if (!input || input.type !== 'password') continue;
    const label = input.dataset.passwordLabel || 'password';
    const setVisible = (visible) => {
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + label);
    };
    setVisible(false);
    button.addEventListener('click', () => setVisible(input.type === 'password'));
  }
  // Always re-hide fields after form reset or a deliberate secret clear.
  return () => {
    for (const button of controls) {
      const input = document.getElementById(button.getAttribute('aria-controls'));
      if (!input) continue;
      input.type = 'password';
      button.textContent = 'Show';
      button.setAttribute('aria-pressed', 'false');
      button.setAttribute('aria-label', 'Show ' + (input.dataset.passwordLabel || 'password'));
    }
  };
}
