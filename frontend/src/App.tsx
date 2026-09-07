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
import { ProtectedRoute } from './components/layout/ProtectedRoute';

export function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route
          path="/"
          element={
            <ProtectedRoute>
              <DashboardPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/staff"
          element={
            <ProtectedRoute>
              <StaffPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/staff/:id"
          element={
            <ProtectedRoute>
              <EmployeeDetailPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/agencies"
          element={
            <ProtectedRoute>
              <AgenciesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/lines"
          element={
            <ProtectedRoute>
              <LinesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/roles"
          element={
            <ProtectedRoute>
              <RolesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/shift-patterns"
          element={
            <ProtectedRoute>
              <ShiftPatternsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/pay-rate-surcharge-rules"
          element={
            <ProtectedRoute>
              <PayRateSurchargeRulesPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/leave-requests"
          element={
            <ProtectedRoute>
              <LeaveRequestsPage />
            </ProtectedRoute>
          }
        />
        <Route
          path="/schedule"
          element={
            <ProtectedRoute>
              <WeeklySchedulePage />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
