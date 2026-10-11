import { useState } from 'react';
import { sg } from '../sg.js';
import { findStage } from '../journey.js';

export default function JourneyPanel({ module, record, journey, onChange }) {
  const [error, setError] = useState('');
  const { phase, stage } = findStage(journey, record.journey_phase, record.journey_stage);
  const current = stage?.statuses.find((s) => s.key === record.journey_status);

  async function run(action, extra = {}) {
    setError('');
    try {
      onChange(await sg(action, { module: module.name, id: record.id, ...extra }));
    } catch (e) {
      setError(e.message);
    }
  }

  if (!stage) return null;

  return (
    <div className="panel journey">
      <div className="muted">Journey</div>
      <h2>
        {phase.label} · {stage.label}
      </h2>
      <div className="statuses">
        {stage.statuses.map((s) => (
          <button
            key={s.key}
            className={`status ${s.key === current?.key ? 'current' : ''} ${s.final ? 'final' : ''}`}
            disabled={s.key === current?.key || current?.final}
            onClick={() => run('move', { status: s.key })}
          >
            {s.label}
          </button>
        ))}
      </div>
      {current?.final && (
        <button className="btn" onClick={() => run('advance')}>Move to the next stage</button>
      )}
      {error && <p className="error">{error}</p>}
    </div>
  );
}
