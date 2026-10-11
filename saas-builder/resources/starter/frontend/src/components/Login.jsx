import { useState } from 'react';
import { sg, setToken } from '../sg.js';

export default function Login({ appName, onLoggedIn }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const { token } = await sg('login', { username, password });
      setToken(token);
      onLoggedIn();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="panel login" onSubmit={submit}>
      <h1>{appName}</h1>
      <p className="muted">Log in with your CRM username and password.</p>
      {error && <p className="error">{error}</p>}
      <label htmlFor="username">Username</label>
      <input id="username" value={username} onChange={(e) => setUsername(e.target.value)} autoFocus required />
      <label htmlFor="password">Password</label>
      <input id="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
      <button className="btn" disabled={busy}>{busy ? 'Logging in…' : 'Log in'}</button>
    </form>
  );
}
