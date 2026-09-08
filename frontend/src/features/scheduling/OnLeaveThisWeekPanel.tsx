import { useQuery } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { Card } from '../../components/ui/Card';
import { fetchLeaveRequests } from '../leave/api';

interface OnLeaveThisWeekPanelProps {
  weekStartDate: string;
}

/**
 * Compact "who's on leave this week" strip, grouped by day - the confirmed default for
 * showing Vakantie/ziek state in a line-grouped grid without inventing a phantom per-line
 * cell for someone not assigned anywhere that day (ProjectPlan.md §10A.2 point 3).
 */
export function OnLeaveThisWeekPanel({ weekStartDate }: OnLeaveThisWeekPanelProps) {
  const weekEnd = dayjs(weekStartDate).add(6, 'day').format('YYYY-MM-DD');

  const { data: leaveRequests } = useQuery({
    queryKey: ['leave-requests', { status: 'approved' }],
    queryFn: () => fetchLeaveRequests({ status: 'approved' }),
  });

  const thisWeek = (leaveRequests ?? []).filter(
    (request) => request.start_date <= weekEnd && request.end_date >= weekStartDate,
  );

  if (thisWeek.length === 0) {
    return null;
  }

  return (
    <Card>
      <h3 className="mb-3 text-sm font-extrabold text-x-brown">On Leave This Week</h3>
      <ul className="space-y-2">
        {thisWeek.map((request) => (
          <li key={request.id} className="flex items-center justify-between text-xs">
            <span className="font-semibold text-slate-700">
              {request.employee.first_name} {request.employee.last_name}
            </span>
            <span className="text-slate-500">
              {request.leave_type.name} · {request.start_date} – {request.end_date}
            </span>
          </li>
        ))}
      </ul>
    </Card>
  );
}
