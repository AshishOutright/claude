import { useEffect, useState } from 'react';
import { sg } from '../sg.js';
import ListView from './ListView.jsx';
import { journeyLabel } from '../journey.js';

const PER_PAGE = 25;

export default function ModulePage({ module, meta, onOpen, onNew, onError }) {
  const [data, setData] = useState({ records: [], total: 0 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setPage(1);
  }, [module.name]);

  useEffect(() => {
    let live = true;
    setLoading(true);
    sg('list', { module: module.name, page, per_page: PER_PAGE })
      .then((result) => live && setData(result))
      .catch(onError)
      .finally(() => live && setLoading(false));
    return () => {
      live = false;
    };
  }, [module.name, page]);

  const columns = module.fields.slice(0, 4).map((f) => ({
    key: f.type === 'relate' ? `${f.name}_name` : f.key,
    label: f.label,
  }));

  if (module.journey) {
    columns.push({ key: 'journey', label: 'Journey', render: (row) => journeyLabel(meta.journey, row) });
  }

  const pages = Math.max(1, Math.ceil(data.total / PER_PAGE));

  return (
    <section>
      <div className="page-head">
        <h1>{module.label}</h1>
        <button className="btn" onClick={onNew}>New {module.label.replace(/s$/, '')}</button>
      </div>
      {loading ? <p className="muted">Loading…</p> : <ListView rows={data.records} columns={columns} onOpen={onOpen} />}
      {pages > 1 && (
        <div className="pager">
          <button className="btn secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span className="muted">Page {page} of {pages}</span>
          <button className="btn secondary" disabled={page >= pages} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      )}
    </section>
  );
}
