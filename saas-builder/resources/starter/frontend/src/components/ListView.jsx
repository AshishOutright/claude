import { parseUtc, relativeTime } from '../relativeTime.js';

// L-UI-001: every list starts with Date Created, shown as relative time. All lists use this component.
export default function ListView({ rows, columns, onOpen, empty = 'Nothing here yet.' }) {
  return (
    <div className="table-wrap">
      <table className="list">
        <thead>
          <tr>
            <th>Date Created</th>
            {columns.map((c) => (
              <th key={c.key}>{c.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 && (
            <tr>
              <td colSpan={columns.length + 1} className="muted">{empty}</td>
            </tr>
          )}
          {rows.map((row) => (
            <tr key={row.id} onClick={() => onOpen?.(row)} className={onOpen ? 'clickable' : ''}>
              <td title={parseUtc(row.date_entered)?.toLocaleString()}>{relativeTime(row.date_entered)}</td>
              {columns.map((c) => (
                <td key={c.key}>{c.render ? c.render(row) : String(row[c.key] ?? '')}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
