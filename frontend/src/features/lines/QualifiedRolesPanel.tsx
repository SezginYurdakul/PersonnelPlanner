import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { fetchRoles, syncEmployeeQualifications } from './api';
import type { Employee } from '../../types/staff';
import type { SchedulingRole } from '../../types/lines';

function setsAreEqual(a: Set<number>, b: Set<number>): boolean {
  if (a.size !== b.size) return false;
  for (const value of a) {
    if (!b.has(value)) return false;
  }
  return true;
}

function groupRoles(roles: SchedulingRole[]) {
  const groups = new Map<string, SchedulingRole[]>();
  for (const role of roles) {
    const key = role.line ? role.line.name : 'line_independent';
    const list = groups.get(key) ?? [];
    list.push(role);
    groups.set(key, list);
  }
  return groups;
}

export function QualifiedRolesPanel({
  employee,
  qualifiedRoleIds,
  preferredRoleIds,
}: {
  employee: Employee;
  qualifiedRoleIds: number[];
  preferredRoleIds: number[];
}) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { data: allRoles } = useQuery({ queryKey: ['roles', {}], queryFn: () => fetchRoles() });
  const [selected, setSelected] = useState<Set<number>>(new Set(qualifiedRoleIds));
  const [preferred, setPreferred] = useState<Set<number>>(new Set(preferredRoleIds));

  useEffect(() => {
    setSelected(new Set(qualifiedRoleIds));
    setPreferred(new Set(preferredRoleIds));
  }, [qualifiedRoleIds, preferredRoleIds]);

  const mutation = useMutation({
    mutationFn: () => syncEmployeeQualifications(employee.id, [...selected], [...preferred]),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] }),
  });

  function toggle(roleId: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(roleId)) next.delete(roleId);
      else next.add(roleId);
      return next;
    });
    // Only a qualified station can be preferred.
    setPreferred((prev) => {
      const next = new Set(prev);
      next.delete(roleId);
      return next;
    });
  }

  function togglePreferred(roleId: number) {
    setPreferred((prev) => {
      const next = new Set(prev);
      if (next.has(roleId)) next.delete(roleId);
      else next.add(roleId);
      return next;
    });
  }

  const grouped = groupRoles(allRoles ?? []);
  const isDirty =
    !setsAreEqual(selected, new Set(qualifiedRoleIds)) || !setsAreEqual(preferred, new Set(preferredRoleIds));

  return (
    <div>
      <h2 className="mb-1 text-sm font-semibold uppercase text-slate-500">{t('staff.qualified_roles')}</h2>
      <p className="mb-3 text-xs text-slate-400">{t('staff.preferred_station.hint')}</p>

      <div className="space-y-4">
        {[...grouped.entries()].map(([groupName, roles]) => (
          <div key={groupName}>
            <p className="mb-1 text-xs font-semibold uppercase text-slate-400">
              {groupName === 'line_independent' ? t('roles.line_independent') : groupName}
            </p>
            {['station', 'secondary_task'].map((kind) => {
              const kindRoles = roles.filter((r) => r.role_kind === kind);
              if (kindRoles.length === 0) return null;
              return (
                <div key={kind} className="mb-2">
                  <p className="mb-1 text-xs text-slate-400">{t(`staff.qualified_roles.${kind}`)}</p>
                  <div className="flex flex-wrap gap-3">
                    {kindRoles.map((role) => (
                      <span key={role.id} className="flex items-center gap-1.5">
                        <label className="flex items-center gap-1.5 text-sm text-slate-700">
                          <input
                            type="checkbox"
                            checked={selected.has(role.id)}
                            onChange={() => toggle(role.id)}
                          />
                          {role.name}
                        </label>
                        {kind === 'station' && selected.has(role.id) && (
                          <button
                            type="button"
                            onClick={() => togglePreferred(role.id)}
                            aria-pressed={preferred.has(role.id)}
                            title={t('staff.preferred_station')}
                            aria-label={`${t('staff.preferred_station')}: ${role.name}`}
                          >
                            <i
                              className={
                                preferred.has(role.id)
                                  ? 'fa-solid fa-star text-x-gold-dark'
                                  : 'fa-regular fa-star text-slate-300 hover:text-x-gold-dark'
                              }
                            />
                          </button>
                        )}
                      </span>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        ))}
      </div>

      {isDirty && (
        <Button
          className="mt-4"
          onClick={() => mutation.mutate()}
          disabled={mutation.isPending}
        >
          {t('common.save')}
        </Button>
      )}
    </div>
  );
}
