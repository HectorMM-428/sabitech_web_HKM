/** Operaciones A04–A07 (autenticación de la SPA con sesión de Sanctum). */
import { apiFetch, ensureCsrfCookie } from './client';
import type { Single, User } from './types';

export interface RegisterInput {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export async function login(email: string, password: string): Promise<User> {
  await ensureCsrfCookie();
  const res = await apiFetch<Single<User>>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
  return res.data;
}

export async function register(input: RegisterInput): Promise<User> {
  await ensureCsrfCookie();
  const res = await apiFetch<Single<User>>('/auth/register', { method: 'POST', body: JSON.stringify(input) });
  return res.data;
}

export function logout(): Promise<void> {
  return apiFetch<void>('/auth/logout', { method: 'POST' });
}

export async function me(signal?: AbortSignal): Promise<User> {
  const res = await apiFetch<Single<User>>('/auth/me', { signal });
  return res.data;
}
