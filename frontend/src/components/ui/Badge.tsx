import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const toneClasses: Record<Tone, string> = {
  neutral: 'bg-amber-50 text-visser-brown border border-amber-200/60',
  success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
  warning: 'bg-amber-100 text-amber-900 border border-amber-300',
  danger: 'bg-rose-50 text-rose-700 border border-rose-200',
  info: 'bg-blue-50 text-blue-800 border border-blue-200',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <span className={`inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold ${toneClasses[tone]}`}>
      {children}
    </span>
  );
}
