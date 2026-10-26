import { useState, type FormEvent } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router';
import { ApiError } from '../../api/client';
import { useAuth } from '../../auth/useAuth';
import { safeNext } from '../../auth/redirect';
import { homeFor } from '../../auth/roles';
import { fieldError } from '../../components/ui/fieldError';
import { Button, FormAlert, TextField } from '../../components/ui/Form';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';

/** A04 · Inicio de sesión. Tras entrar vuelve a la página de origen (?next=) o a la de su rol. */
export function LoginPage() {
  useDocumentTitle('Iniciar sesión');
  const { login, status, user } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const next = safeNext(params.get('next'));

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  if (status === 'authenticated' && user && !submitting) {
    return <Navigate to={next ?? homeFor(user.role)} replace />;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const u = await login(email, password);
      navigate(next ?? homeFor(u.role), { replace: true });
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setSubmitting(false);
    }
  }

  const emailError = fieldError(error?.fields, 'email');
  const passwordError = fieldError(error?.fields, 'password');
  const generalError = error && !emailError && !passwordError ? error.message : null;

  return (
    <div className="page page--narrow">
      <h1>Iniciar sesión</h1>
      <p className="muted page-intro">Entra para reservar plaza y ver tus reservas.</p>

      <form className="form-card" onSubmit={onSubmit} noValidate>
        {generalError ? <FormAlert>{generalError}</FormAlert> : null}
        <TextField
          label="Email"
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          error={emailError}
        />
        <TextField
          label="Contraseña"
          type="password"
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          error={passwordError}
        />
        <Button type="submit" loading={submitting} loadingText="Entrando…">
          Entrar
        </Button>
      </form>

      <p className="form-footer">
        ¿No tienes cuenta? <Link to={`/registro${next ? `?next=${encodeURIComponent(next)}` : ''}`}>Crea una</Link>
      </p>
    </div>
  );
}
