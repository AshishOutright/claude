import { useEffect, useState } from 'react';
import { hasToken, setToken, sg } from './sg.js';
import Login from './components/Login.jsx';
import ModulePage from './components/ModulePage.jsx';
import RecordPage from './components/RecordPage.jsx';

const APP_NAME = '__APP_NAME__';

// Screens are built from the Smart Gateway's "meta" action, so new fields and modules show up without code changes.
export default function App() {
  const [loggedIn, setLoggedIn] = useState(hasToken());
  const [meta, setMeta] = useState(null);
  const [error, setError] = useState('');
  const [view, setView] = useState({ module: null, id: null, editing: false });

  function handleError(e) {
    if (e.code === 'unauthenticated') {
      setLoggedIn(false);
      setMeta(null);
    } else {
      setError(e.message);
    }
  }

  useEffect(() => {
    if (!loggedIn) return;
    sg('meta')
      .then((m) => {
        setMeta(m);
        const first = m.modules.find((x) => x.journey) || m.modules[0];
        setView({ module: first?.name, id: null, editing: false });
      })
      .catch(handleError);
  }, [loggedIn]);

  if (!loggedIn) return <Login appName={APP_NAME} onLoggedIn={() => setLoggedIn(true)} />;
  if (!meta) return <p className="muted center">{error || 'Loading…'}</p>;

  const module = meta.modules.find((m) => m.name === view.module);

  return (
    <div className="shell">
      <aside>
        <div className="brand">{meta.app}</div>
        <nav>
          {meta.modules.map((m) => (
            <button key={m.name} className={m.name === view.module ? 'active' : ''} onClick={() => setView({ module: m.name, id: null, editing: false })}>
              {m.label}
            </button>
          ))}
        </nav>
        <button className="btn link" onClick={() => { setToken(null); setLoggedIn(false); setMeta(null); }}>Log out</button>
      </aside>
      <main>
        {error && <p className="error" onClick={() => setError('')}>{error}</p>}
        {module && !view.editing && (
          <ModulePage
            module={module}
            meta={meta}
            onError={handleError}
            onOpen={(row) => setView({ ...view, id: row.id, editing: true })}
            onNew={() => setView({ ...view, id: null, editing: true })}
          />
        )}
        {module && view.editing && (
          <RecordPage
            module={module}
            meta={meta}
            id={view.id}
            onSaved={(saved) => setView({ ...view, id: saved.id, editing: true })}
            onBack={() => setView({ ...view, id: null, editing: false })}
          />
        )}
      </main>
    </div>
  );
}
