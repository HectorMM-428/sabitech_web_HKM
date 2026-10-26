import { useId, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode } from 'react';

interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string;
  error?: string;
  hint?: string;
}

/**
 * Campo de texto accesible: etiqueta visible asociada, ayuda y error enlazados con
 * aria-describedby, y aria-invalid cuando hay error (WCAG 1.3.1, 3.3.1).
 */
export function TextField({ label, error, hint, required, ...input }: TextFieldProps) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div className="field">
      <label className="field__label" htmlFor={id}>
        {label}
        {required ? <span aria-hidden="true"> *</span> : null}
      </label>
      {hint ? (
        <p id={hintId} className="field__hint">
          {hint}
        </p>
      ) : null}
      <input
        id={id}
        className={`field__input${error ? ' field__input--invalid' : ''}`}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        required={required}
        {...input}
      />
      {error ? (
        <p id={errorId} className="field__error">
          <span aria-hidden="true">✕ </span>
          {error}
        </p>
      ) : null}
    </div>
  );
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  loading?: boolean;
  loadingText?: string;
}

/** Botón con estado de carga en texto (no solo un spinner) y desactivado mientras trabaja. */
export function Button({ variant = 'primary', loading = false, loadingText, children, disabled, className, ...rest }: ButtonProps) {
  return (
    <button
      className={`button button--${variant}${className ? ` ${className}` : ''}`}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...rest}
    >
      {loading ? (loadingText ?? 'Procesando…') : children}
    </button>
  );
}

/** Mensaje general de un formulario. role="alert" lo anuncia al aparecer. */
export function FormAlert({ tone = 'error', children }: { tone?: 'error' | 'success' | 'info'; children: ReactNode }) {
  const icon = tone === 'error' ? '✕' : tone === 'success' ? '✓' : 'i';
  return (
    <div className={`alert alert--${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <span aria-hidden="true" className="alert__icon">
        {icon}
      </span>
      <div>{children}</div>
    </div>
  );
}

