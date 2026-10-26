import { useEffect } from 'react';

/** Cada página tiene un título propio: lo anuncian los lectores de pantalla al navegar. */
export function useDocumentTitle(title: string) {
  useEffect(() => {
    document.title = title ? `${title} · SABITECH` : 'SABITECH · Jornadas tecnológicas';
  }, [title]);
}
