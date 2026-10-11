import { useEffect, useState } from 'react';
import { sg } from '../sg.js';
import JourneyPanel from './JourneyPanel.jsx';
import { relativeTime } from '../relativeTime.js';

function inputType(type) {
  return { int: 'number', decimal: 'number', date: 'date', datetime: 'datetime-local', email: 'email', url: 'url', phone: 'tel' }[type] || 'text';
}

function toInput(field, value) {
  if (value === null || value === undefined) return '';
  if (field.type === 'datetime') return String(value).replace(' ', 'T').slice(0, 16);
  if (field.type === 'bool') return Boolean(Number(value)) || value === true;
  return value;
}

function fromInput(field, value) {
  if (field.type === 'datetime' && value) return value.replace('T', ' ') + ':00';
  return value;
}

export default function RecordPage({ module, meta, id, onSaved, onBack }) {
  const [record, setRecord] = useState(null);
  const [values, setValues] = useState({});
  const [options, setOptions] = useState({});
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!id) {
      setRecord(null);
      setValues({});
      return;
    }
    sg('get', { module: module.name, id }).then((r) => {
      setRecord(r);
      setValues(Object.fromEntries(module.fields.map((f) => [f.key, toInput(f, r[f.key])])));
    }).catch((e) => setMessage(e.message));
  }, [module.name, id]);

  useEffect(() => {
    module.fields.filter((f) => f.type === 'relate').forEach((f) => {
      sg('list', { module: f.relates_to, per_page: 100 })
        .then((r) => setOptions((o) => ({ ...o, [f.key]: r.records })))
        .catch(() => {});
    });
  }, [module.name]);

  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setErrors({});
    setMessage('');
    const data = Object.fromEntries(module.fields.map((f) => [f.key, fromInput(f, values[f.key] ?? '')]));
    try {
      const saved = id ? await sg('update', { module: module.name, id, data }) : await sg('create', { module: module.name, data });
      onSaved(saved);
    } catch (e) {
      setErrors(e.fields);
      setMessage(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    if (!confirm(`Delete this ${module.label.replace(/s$/, '')}?`)) return;
    try {
      await sg('delete', { module: module.name, id });
      onBack();
    } catch (e) {
      setMessage(e.message);
    }
  }

  function field(f) {
    const value = values[f.key] ?? '';
    const set = (v) => setValues({ ...values, [f.key]: v });
    let input;

    if (f.type === 'text') input = <textarea id={f.key} value={value} onChange={(e) => set(e.target.value)} />;
    else if (f.type === 'bool') input = <input id={f.key} type="checkbox" checked={Boolean(value)} onChange={(e) => set(e.target.checked)} />;
    else if (f.type === 'enum' || f.type === 'relate') {
      const choices = f.type === 'enum' ? f.options.map((o) => ({ id: o, name: o })) : options[f.key] || [];
      input = (
        <select id={f.key} value={value} onChange={(e) => set(e.target.value)}>
          <option value="">Choose…</option>
          {choices.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      );
    } else input = <input id={f.key} type={inputType(f.type)} step={f.type === 'decimal' ? '0.01' : undefined} value={value} onChange={(e) => set(e.target.value)} />;

    return (
      <div key={f.key} className="field">
        <label htmlFor={f.key}>{f.label}{f.required && <span className="req"> *</span>}</label>
        {input}
        {errors[f.key] && <div className="error">{errors[f.key]}</div>}
      </div>
    );
  }

  return (
    <section>
      <button className="btn link" onClick={onBack}>← {module.label}</button>
      <h1>{id ? record?.name || '…' : `New ${module.label.replace(/s$/, '')}`}</h1>
      {record && <p className="muted">Created {relativeTime(record.date_entered)}</p>}
      {record && module.journey && <JourneyPanel module={module} record={record} journey={meta.journey} onChange={setRecord} />}
      <form className="panel" onSubmit={save}>
        {message && <p className="error">{message}</p>}
        {module.fields.map(field)}
        <div className="actions">
          <button className="btn" disabled={busy}>{busy ? 'Saving…' : 'Save'}</button>
          {id && <button type="button" className="btn danger" onClick={remove}>Delete</button>}
        </div>
      </form>
    </section>
  );
}
