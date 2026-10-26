import { useState, type ChangeEvent, type FormEvent } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router';
import { ApiError } from '../../api/client';
import { useAuth } from '../../auth/useAuth';
import { safeNext } from '../../auth/redirect';
import { homeFor } from '../../auth/roles';
import { fieldError } from '../../components/ui/fieldError';
import { Button, FormAlert, TextField } from '../../components/ui/Form';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';

/**
 * A05 · Registro de asistentes. La validación del navegador solo ayuda: la decisión
 * final la toma el servidor y sus mensajes se muestran junto a cada campo.
 */
export function RegisterPage() {
  useDocumentTitle('Crear cuenta');
  const { register, status, user } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const next = safeNext(params.get('next'));

  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  if (status === 'authenticated' && user && !submitting) {
    return <Navigate to={next ?? homeFor(user.role)} replace />;
  }

  const set = (key: keyof typeof form) => (e: ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setLocalError(null);

    // Ayuda inmediata en el cliente; el servidor vuelve a comprobarlo todo.
    if (form.password !== form.password_confirmation) {
      setLocalError('Las contraseñas no coinciden.');
      return;
    }

    setSubmitting(true);
    try {
      const u = await register(form);
      navigate(next ?? homeFor(u.role), { replace: true });
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setSubmitting(false);
    }
  }

  const errors = {
    name: fieldError(error?.fields, 'name'),
    email: fieldError(error?.fields, 'email'),
    password: localError ?? fieldError(error?.fields, 'password'),
  };
  const generalError = error && !error.fields ? error.message : null;

  return (
    <div className="page page--narrow">
      <h1>Crear cuenta</h1>
      <p className="muted page-intro">Con una cuenta puedes reservar plaza en las charlas y acceder con tu acreditación QR.</p>

      <form className="form-card" onSubmit={onSubmit} noValidate>
        {generalError ? <FormAlert>{generalError}</FormAlert> : null}
        <TextField label="Nombre y apellidos" name="name" autoComplete="name" required value={form.name} onChange={set('name')} error={errors.name} />
        <TextField label="Email" type="email" name="email" autoComplete="email" required value={form.email} onChange={set('email')} error={errors.email} />
        <TextField
          label="Contraseña"
          type="password"
          name="password"
          autoComplete="new-password"
          required
          hint="Mínimo 10 caracteres, con letras y números."
          value={form.password}
          onChange={set('password')}
          error={errors.password}
        />
        <TextField
          label="Repite la contraseña"
          type="password"
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={form.password_confirmation}
          onChange={set('password_confirmation')}
        />
        <Button type="submit" loading={submitting} loadingText="Creando cuenta…">
          Crear cuenta
        </Button>
      </form>

      <p className="form-footer">
        ¿Ya tienes cuenta? <Link to={`/login${next ? `?next=${encodeURIComponent(next)}` : ''}`}>Inicia sesión</Link>
      </p>
    </div>
  );
}
