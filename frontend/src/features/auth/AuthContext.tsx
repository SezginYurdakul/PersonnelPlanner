import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { fetchCurrentUser, login as loginRequest, logout as logoutRequest } from './api';
import type { AuthUser, LoginPayload } from '../../types/auth';

interface AuthContextValue {
  user: AuthUser | null;
  isLoading: boolean;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchCurrentUser()
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setIsLoading(false));
  }, []);

  // The PWA install prompt is an employee (`/me`) feature only - an admin-only account
  // has no `/me` surface to install into, so the browser's own install banner is
  // suppressed for them here. A `user`-role account is left alone so a future
  // InstallPrompt component (Phase 8g) can capture and replay this event.
  useEffect(() => {
    if (!user || user.roles.includes('user')) {
      return;
    }

    function suppressInstallPrompt(event: Event) {
      event.preventDefault();
    }

    window.addEventListener('beforeinstallprompt', suppressInstallPrompt);
    return () => window.removeEventListener('beforeinstallprompt', suppressInstallPrompt);
  }, [user]);

  async function login(payload: LoginPayload) {
    const authenticatedUser = await loginRequest(payload);
    setUser(authenticatedUser);
  }

  async function logout() {
    await logoutRequest();
    setUser(null);
  }

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
