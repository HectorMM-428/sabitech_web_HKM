import {
  useId,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
} from 'react';

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
export function Button({ variant = 'primary', loading = false, loadingText, children, disabled, className, type = 'button', ...rest }: ButtonProps) {
  // type="button" por defecto: un botón dentro de un formulario no lo envía salvo que se pida.
  return (
    <button
      type={type}
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


interface FieldShellProps {
  label: string;
  error?: string;
  hint?: string;
  required?: boolean;
  children: (props: { id: string; 'aria-invalid'?: true; 'aria-describedby'?: string }) => ReactNode;
}

/** Etiqueta, ayuda y error comunes a select y textarea (mismo patrón que TextField). */
function FieldShell({ label, error, hint, required, children }: FieldShellProps) {
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
      {children({ id, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy })}
      {error ? (
        <p id={errorId} className="field__error">
          <span aria-hidden="true">✕ </span>
          {error}
        </p>
      ) : null}
    </div>
  );
}

interface SelectFieldProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id'> {
  label: string;
  error?: string;
  hint?: string;
}

export function SelectField({ label, error, hint, required, children, ...select }: SelectFieldProps) {
  return (
    <FieldShell label={label} error={error} hint={hint} required={required}>
      {(a11y) => (
        <select className={`field__input${error ? ' field__input--invalid' : ''}`} required={required} {...a11y} {...select}>
          {children}
        </select>
      )}
    </FieldShell>
  );
}

interface TextAreaFieldProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'id'> {
  label: string;
  error?: string;
  hint?: string;
}

export function TextAreaField({ label, error, hint, required, ...textarea }: TextAreaFieldProps) {
  return (
    <FieldShell label={label} error={error} hint={hint} required={required}>
      {(a11y) => (
        <textarea className={`field__input${error ? ' field__input--invalid' : ''}`} required={required} {...a11y} {...textarea} />
      )}
    </FieldShell>
  );
}
