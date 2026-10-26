import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import * as authApi from '../api/auth';
import { UNAUTHENTICATED_EVENT } from '../api/client';
import type { User } from '../api/types';
import { AuthContext, type AuthContextValue, type AuthStatus } from './context';

/**
 * Estado de la sesión en el cliente. Es solo una copia para pintar la interfaz:
 * quien decide si una operación está permitida es siempre el servidor.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading');
  const [user, setUser] = useState<User | null>(null);
  const [sessionExpired, setSessionExpired] = useState(false);
  const statusRef = useRef<AuthStatus>('loading');
  useEffect(() => {
    statusRef.current = status;
  }, [status]);

  // Al cargar la aplicación se pregunta al servidor si ya hay sesión.
  useEffect(() => {
    const controller = new AbortController();
    authApi
      .me(controller.signal)
      .then((u) => {
        setUser(u);
        setStatus('authenticated');
      })
      .catch(() => {
        if (controller.signal.aborted) return;
        setUser(null);
        setStatus('guest');
      });
    return () => controller.abort();
  }, []);

  // Cualquier 401 de la API con la sesión iniciada = sesión caducada.
  useEffect(() => {
    function onUnauthenticated() {
      if (statusRef.current !== 'authenticated') return;
      setUser(null);
      setStatus('guest');
      setSessionExpired(true);
    }
    window.addEventListener(UNAUTHENTICATED_EVENT, onUnauthenticated);
    return () => window.removeEventListener(UNAUTHENTICATED_EVENT, onUnauthenticated);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const u = await authApi.login(email, password);
    setUser(u);
    setStatus('authenticated');
    setSessionExpired(false);
    return u;
  }, []);

  const register = useCallback(async (input: authApi.RegisterInput) => {
    const u = await authApi.register(input);
    setUser(u);
    setStatus('authenticated');
    setSessionExpired(false);
    return u;
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      setUser(null);
      setStatus('guest');
    }
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      sessionExpired,
      login,
      register,
      logout,
      dismissSessionExpired: () => setSessionExpired(false),
      hasRole: (...roles) => user !== null && roles.includes(user.role),
    }),
    [status, user, sessionExpired, login, register, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

