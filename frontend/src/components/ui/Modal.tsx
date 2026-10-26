import { useEffect, useId, useRef, type ReactNode } from 'react';

interface ModalProps {
  title: string;
  children: ReactNode;
  actions: ReactNode;
  onClose: () => void;
  role?: 'dialog' | 'alertdialog';
}

/**
 * Ventana modal accesible: foco dentro al abrir, tabulación atrapada, Escape cierra
 * y el foco vuelve al elemento que la abrió (WCAG 2.4.3).
 */
export function Modal({ title, children, actions, onClose, role = 'dialog' }: ModalProps) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    const panel = panelRef.current;
    const first = panel?.querySelector<HTMLElement>('button, [href], input, select, textarea');
    (first ?? panel)?.focus();

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key !== 'Tab' || !panel) return;
      const focusables = Array.from(panel.querySelectorAll<HTMLElement>('button, [href], input, select, textarea')).filter(
        (el) => !el.hasAttribute('disabled'),
      );
      if (focusables.length === 0) return;
      const firstEl = focusables[0];
      const lastEl = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === firstEl) {
        event.preventDefault();
        lastEl.focus();
      } else if (!event.shiftKey && document.activeElement === lastEl) {
        event.preventDefault();
        firstEl.focus();
      }
    }

    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      previous?.focus?.();
    };
  }, [onClose]);

  return (
    <div className="modal-backdrop">
      <div ref={panelRef} className="modal" role={role} aria-modal="true" aria-labelledby={titleId} tabIndex={-1}>
        <h2 id={titleId} className="modal__title">
          {title}
        </h2>
        <div className="modal__body">{children}</div>
        <div className="modal__actions">{actions}</div>
      </div>
    </div>
  );
}
