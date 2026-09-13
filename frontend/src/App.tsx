import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { LoginPage } from './features/auth/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { StaffPage } from './pages/StaffPage';
import { EmployeeDetailPage } from './pages/EmployeeDetailPage';
import { AgenciesPage } from './pages/AgenciesPage';
import { LinesPage } from './pages/LinesPage';
import { RolesPage } from './pages/RolesPage';
import { ShiftPatternsPage } from './pages/ShiftPatternsPage';
import { PayRateSurchargeRulesPage } from './pages/PayRateSurchargeRulesPage';
import { LeaveRequestsPage } from './pages/LeaveRequestsPage';
import { WeeklySchedulePage } from './pages/WeeklySchedulePage';
import { TimeAttendanceImportPage } from './pages/TimeAttendanceImportPage';
import { CompanySettingsPage } from './pages/CompanySettingsPage';
import { CompletePasswordPage } from './pages/CompletePasswordPage';
import { EmployeeSchedulePage } from './pages/EmployeeSchedulePage';
import { LeaveRequestPage } from './pages/LeaveRequestPage';
import { ShiftNoticePage } from './pages/ShiftNoticePage';
import { ProtectedRoute } from './components/layout/ProtectedRoute';

export function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/complete-invitation" element={<CompletePasswordPage />} />
        <Route
          path="/"
          element={
            <ProtectedRoute adminOnly>
              <DashboardPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/staff"
          element={
            <ProtectedRoute adminOnly>
              <StaffPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/staff/:id"
          element={
            <ProtectedRoute adminOnly>
              <EmployeeDetailPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/agencies"
          element={
            <ProtectedRoute adminOnly>
              <AgenciesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/lines"
          element={
            <ProtectedRoute adminOnly>
              <LinesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/roles"
          element={
            <ProtectedRoute adminOnly>
              <RolesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/shift-patterns"
          element={
            <ProtectedRoute adminOnly>
              <ShiftPatternsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/pay-rate-surcharge-rules"
          element={
            <ProtectedRoute adminOnly>
              <PayRateSurchargeRulesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/leave-requests"
          element={
            <ProtectedRoute adminOnly>
              <LeaveRequestsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/schedule"
          element={
            <ProtectedRoute adminOnly>
              <WeeklySchedulePage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/time-attendance"
          element={
            <ProtectedRoute adminOnly>
              <TimeAttendanceImportPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/company-settings"
          element={
            <ProtectedRoute adminOnly>
              <CompanySettingsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/me/schedule"
          element={
            <ProtectedRoute>
              <EmployeeSchedulePage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/me/leave-request"
          element={
            <ProtectedRoute>
              <LeaveRequestPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/me/shift-notice"
          element={
            <ProtectedRoute>
              <ShiftNoticePage />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
