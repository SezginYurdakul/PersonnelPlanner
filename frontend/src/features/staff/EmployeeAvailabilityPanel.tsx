import { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { updateEmployeeUnavailability } from './api';
import type { Employee, SlotType, UnavailableSlot } from '../../types/staff';

const DAYS: { iso: number; labelKey: string }[] = [
  { iso: 1, labelKey: 'common.weekday.monday' },
  { iso: 2, labelKey: 'common.weekday.tuesday' },
  { iso: 3, labelKey: 'common.weekday.wednesday' },
  { iso: 4, labelKey: 'common.weekday.thursday' },
  { iso: 5, labelKey: 'common.weekday.friday' },
  { iso: 6, labelKey: 'common.weekday.saturday' },
  { iso: 7, labelKey: 'common.weekday.sunday' },
];

const SLOT_TYPES: SlotType[] = ['day', 'afternoon', 'night'];

function key(dayOfWeek: number, slotType: SlotType): string {
  return `${dayOfWeek}|${slotType}`;
}

function setsAreEqual(a: Set<string>, b: Set<string>): boolean {
  if (a.size !== b.size) return false;
  for (const value of a) {
    if (!b.has(value)) return false;
  }
  return true;
}

export function EmployeeAvailabilityPanel({ employee }: { employee: Employee }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [unavailable, setUnavailable] = useState<Set<string>>(
    () => new Set(employee.unavailable_slots.map((s) => key(s.day_of_week, s.slot_type))),
  );

  useEffect(() => {
    setUnavailable(new Set(employee.unavailable_slots.map((s) => key(s.day_of_week, s.slot_type))));
  }, [employee.unavailable_slots]);

  const mutation = useMutation({
    mutationFn: (slots: UnavailableSlot[]) => updateEmployeeUnavailability(employee.id, slots),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] }),
  });

  function toggle(dayOfWeek: number, slotType: SlotType) {
    setUnavailable((prev) => {
      const next = new Set(prev);
      const k = key(dayOfWeek, slotType);
      next.has(k) ? next.delete(k) : next.add(k);
      return next;
    });
  }

  function handleSave() {
    const slots: UnavailableSlot[] = [...unavailable].map((k) => {
      const [dayOfWeek, slotType] = k.split('|');
      return { day_of_week: Number(dayOfWeek), slot_type: slotType as SlotType };
    });
    mutation.mutate(slots);
  }

  const originalUnavailable = new Set(employee.unavailable_slots.map((s) => key(s.day_of_week, s.slot_type)));
  const isDirty = !setsAreEqual(unavailable, originalUnavailable);

  return (
    <div>
      <h2 className="mb-1 text-sm font-semibold uppercase text-slate-500">{t('staff.availability.title')}</h2>
      <p className="mb-3 text-xs text-slate-400">{t('staff.availability.hint')}</p>

      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr>
              <th className="p-2 text-left text-xs font-semibold uppercase text-slate-400">
                {t('staff.availability.shift')}
              </th>
              {DAYS.map((day) => (
                <th key={day.iso} className="p-2 text-center text-xs font-semibold uppercase text-slate-400">
                  {t(day.labelKey)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {SLOT_TYPES.map((slotType) => (
              <tr key={slotType}>
                <td className="p-2 text-xs font-semibold capitalize text-slate-600">{slotType}</td>
                {DAYS.map((day) => {
                  const isUnavailable = unavailable.has(key(day.iso, slotType));
                  return (
                    <td key={day.iso} className="p-1 text-center">
                      <button
                        type="button"
                        onClick={() => toggle(day.iso, slotType)}
                        className={`h-8 w-8 rounded-lg border text-xs font-bold transition-colors ${
                          isUnavailable
                            ? 'border-rose-300 bg-rose-50 text-rose-600'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-600'
                        }`}
                        title={isUnavailable ? t('staff.availability.unavailable') : t('staff.availability.available')}
                      >
                        <i className={`fa-solid ${isUnavailable ? 'fa-xmark' : 'fa-check'}`} />
                      </button>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {isDirty && (
        <Button className="mt-4" onClick={handleSave} disabled={mutation.isPending}>
          {t('common.save')}
        </Button>
      )}
    </div>
  );
}
