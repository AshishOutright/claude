// Smart Gateway client. Every screen gets and saves data only through here.
const SG_URL = import.meta.env.VITE_SG_URL;
const TOKEN_KEY = 'sg_token';

function readToken() {
  try {
    return sessionStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

let token = readToken();

export function setToken(value) {
  token = value;
  try {
    if (value) sessionStorage.setItem(TOKEN_KEY, value);
    else sessionStorage.removeItem(TOKEN_KEY);
  } catch {
    // Storage can be blocked; the token then lasts until the page reloads.
  }
}

export function hasToken() {
  return Boolean(token);
}

export class GatewayError extends Error {
  constructor(error) {
    super(error?.message || 'The Smart Gateway could not do that.');
    this.code = error?.code || 'unknown';
    this.fields = error?.fields || {};
  }
}

export async function sg(action, payload = {}) {
  if (!SG_URL) {
    throw new GatewayError({ code: 'not_configured', message: 'Set VITE_SG_URL in .env to your Smart Gateway.' });
  }

  let response;
  try {
    response = await fetch(SG_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ action, ...payload }),
    });
  } catch {
    throw new GatewayError({ code: 'offline', message: 'Could not reach the Smart Gateway.' });
  }

  const body = await response.json().catch(() => null);
  if (!body) throw new GatewayError({ code: 'bad_response', message: 'The Smart Gateway did not answer with JSON.' });

  if (!body.ok) {
    if (body.error?.code === 'unauthenticated') setToken(null);
    throw new GatewayError(body.error);
  }

  return body.data;
}
