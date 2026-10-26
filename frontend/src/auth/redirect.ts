/** Solo rutas internas: evita redirecciones abiertas a otros dominios con ?next=. */
export function safeNext(value: string | null): string | null {
  if (!value) return null;
  return value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/\\') ? value : null;
}
