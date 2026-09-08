const HOURS = Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0'));
const MINUTES = ['00', '15', '30', '45'];

interface TimeSelectProps {
  value: string;
  onChange: (value: string) => void;
  className?: string;
}

/**
 * Two plain <select>s (hour 00-23, minute 00/15/30/45) instead of a native
 * <input type="time">. The native picker renders 12-hour AM/PM in some browser/OS locale
 * combinations, which makes a value like "12:00" ambiguous (noon vs. midnight) at a
 * glance - a real point of confusion reported against this app. A pair of selects always
 * shows an explicit 24-hour value, so there's nothing to misread.
 */
export function TimeSelect({ value, onChange, className = '' }: TimeSelectProps) {
  const [hour, minute] = /^\d{2}:\d{2}$/.test(value) ? value.split(':') : ['', ''];

  function update(nextHour: string, nextMinute: string) {
    if (nextHour !== '' && nextMinute !== '') {
      onChange(`${nextHour}:${nextMinute}`);
    }
  }

  return (
    <div className={`flex items-center gap-1 ${className}`}>
      <select
        className="rounded border border-slate-300 px-2 py-2 text-sm tabular-nums"
        value={hour}
        onChange={(e) => update(e.target.value, minute || '00')}
      >
        <option value="" disabled>
          --
        </option>
        {HOURS.map((h) => (
          <option key={h} value={h}>
            {h}
          </option>
        ))}
      </select>
      <span className="text-sm text-slate-400">:</span>
      <select
        className="rounded border border-slate-300 px-2 py-2 text-sm tabular-nums"
        value={minute}
        onChange={(e) => update(hour || '00', e.target.value)}
      >
        <option value="" disabled>
          --
        </option>
        {MINUTES.map((m) => (
          <option key={m} value={m}>
            {m}
          </option>
        ))}
      </select>
    </div>
  );
}
