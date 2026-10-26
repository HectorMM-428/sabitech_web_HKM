/** Primer error de un campo devuelto por la API (422). */
export function fieldError(fields: Record<string, string[]> | null | undefined, name: string): string | undefined {
  return fields?.[name]?.[0];
}
