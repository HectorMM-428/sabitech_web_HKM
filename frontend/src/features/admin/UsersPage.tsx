import { useState, type FormEvent } from 'react';
import { changeRole, listUsers } from '../../api/admin';
import { ApiError } from '../../api/client';
import type { User, UserRole } from '../../api/types';
import { useAuth } from '../../auth/useAuth';
import { Button, FormAlert, SelectField, TextField } from '../../components/ui/Form';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { ROLE_LABEL } from './labels';
import { Pager, ResultCount } from './Pager';
import { useListParams } from './useListParams';

/** A30 y A27 · Usuarios y roles. Es la única forma de crear personal de acceso o administradores. */
export function UsersPage() {
  useDocumentTitle('Usuarios · Panel');
  const { user: me } = useAuth();
  const { values, page, setFilters, setPage, key } = useListParams(['q', 'role'] as const);
  const [draftQ, setDraftQ] = useState(values.q);
  const role = values.role as UserRole | '';
  const { state, reload } = useApi((signal) => listUsers({ q: values.q, role, page }, signal), `admin-users-${key}`);
  const [message, setMessage] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

  function onSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFilters({ q: draftQ.trim() });
  }

  return (
    <>
      <h1>Usuarios</h1>
      <form className="toolbar" role="search" aria-label="Buscar usuarios" onSubmit={onSearch}>
        <TextField label="Buscar" type="search" maxLength={100} placeholder="Nombre o correo" value={draftQ} onChange={(e) => setDraftQ(e.target.value)} />
        <SelectField label="Rol" value={role} onChange={(e) => setFilters({ role: e.target.value })}>
          <option value="">Todos</option>
          {(Object.keys(ROLE_LABEL) as UserRole[]).map((r) => (
            <option key={r} value={r}>
              {ROLE_LABEL[r]}
            </option>
          ))}
        </SelectField>
        <button type="submit" className="button button--primary">
          Buscar
        </button>
      </form>

      {message ? <FormAlert tone={message.tone}>{message.text}</FormAlert> : null}

      {state.status === 'loading' && <LoadingBlocks label="Cargando usuarios…" count={4} height={48} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && (
        <>
          <ResultCount total={state.data.meta.total} noun={['usuario', 'usuarios']} />
          {state.data.data.length === 0 ? (
            <EmptyState title="No hay usuarios con estos filtros" />
          ) : (
            <ul className="manual-results">
              {state.data.data.map((u) => (
                <UserRow
                  key={u.id}
                  user={u}
                  isMe={u.id === me?.id}
                  onChanged={(updated) => {
                    setMessage({ tone: 'success', text: `${updated.name} ahora tiene el rol «${ROLE_LABEL[updated.role]}».` });
                    reload();
                  }}
                  onError={(text) => setMessage({ tone: 'error', text })}
                />
              ))}
            </ul>
          )}
          <Pager meta={state.data.meta} onPage={setPage} />
        </>
      )}
    </>
  );
}

function UserRow({ user, isMe, onChanged, onError }: { user: User; isMe: boolean; onChanged: (u: User) => void; onError: (t: string) => void }) {
  const [role, setRole] = useState<UserRole>(user.role);
  const [saving, setSaving] = useState(false);

  async function save() {
    setSaving(true);
    try {
      const res = await changeRole(user.id, role);
      onChanged(res.data);
    } catch (e) {
      onError(e instanceof ApiError ? (e.fields?.role?.[0] ?? e.message) : 'Ha ocurrido un error inesperado.');
      setRole(user.role);
    } finally {
      setSaving(false);
    }
  }

  return (
    <li className="manual-results__item">
      <div>
        <p className="manual-results__name">
          {user.name}
          {isMe ? ' (tú)' : ''}
        </p>
        <p className="field__hint">{user.email}</p>
      </div>
      {isMe ? (
        <span className="badge badge--info">{ROLE_LABEL[user.role]}</span>
      ) : (
        <div className="row-actions">
          <SelectField label={`Rol de ${user.name}`} value={role} onChange={(e) => setRole(e.target.value as UserRole)}>
            {(Object.keys(ROLE_LABEL) as UserRole[]).map((r) => (
              <option key={r} value={r}>
                {ROLE_LABEL[r]}
              </option>
            ))}
          </SelectField>
          <Button variant="secondary" className="button--small" onClick={save} loading={saving} loadingText="Guardando…" disabled={role === user.role}>
            Guardar rol
          </Button>
        </div>
      )}
    </li>
  );
}
