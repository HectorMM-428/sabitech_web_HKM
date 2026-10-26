import { createContext } from 'react';
import type { RegisterInput } from '../api/auth';
import type { User, UserRole } from '../api/types';

export type AuthStatus = 'loading' | 'guest' | 'authenticated';

export interface AuthContextValue {
  status: AuthStatus;
  user: User | null;
  /** true cuando la API respondió 401 a alguien que tenía sesión: se muestra el aviso. */
  sessionExpired: boolean;
  login: (email: string, password: string) => Promise<User>;
  register: (input: RegisterInput) => Promise<User>;
  logout: () => Promise<void>;
  dismissSessionExpired: () => void;
  hasRole: (...roles: UserRole[]) => boolean;
}

export const AuthContext = createContext<AuthContextValue | null>(null);
