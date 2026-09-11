const secret = window.location.hash.slice(1);
history.replaceState(null, '', window.location.pathname);
if (/^[a-f0-9]{64}$/.test(secret)) {
  document.querySelector('#login-token').value = secret;
  document.querySelector('#login-form').hidden = false;
}
