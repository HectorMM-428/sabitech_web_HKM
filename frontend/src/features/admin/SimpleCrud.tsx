import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import { ApiError } from '../../api/client';
import { fieldError } from '../../components/ui/fieldError';
import { Button, FormAlert, TextAreaField, TextField } from '../../components/ui/Form';
import { Modal } from '../../components/ui/Modal';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';

export interface CrudField {
  name: string;
  label: string;
  required?: boolean;
  maxLength: number;
  minLength?: number;
  multiline?: boolean;
}

interface Props<T extends { id: number; name: string }> {
  title: string;
  singular: string;
  fields: CrudField[];
  load: (signal: AbortSignal) => Promise<{ data: T[] }>;
  save: (input: Record<string, string | null>, id?: number) => Promise<unknown>;
  remove: (id: number) => Promise<unknown>;
  describe: (item: T) => ReactNode;
}

/**
 * Listado corto con alta, edición y borrado (ponentes y salas, A21 y A22).
 * El servidor rechaza borrar lo que tiene charlas (HAS_TALKS) y aquí se muestra su motivo.
 */
export function SimpleCrud<T extends { id: number; name: string }>({ title, singular, fields, load, save, remove, describe }: Props<T>) {
  useDocumentTitle(`${title} · Panel`);
  const { state, reload } = useApi(load, `crud-${title}`);
  const [editing, setEditing] = useState<T | 'new' | null>(null);
  const [deleting, setDeleting] = useState<T | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const formRef = useRef<HTMLDivElement>(null);

  function startEdit(item: T | 'new') {
    setNotice(null);
    setEditing(item);
    // El formulario aparece arriba: se lleva el foco a su primer campo.
    requestAnimationFrame(() => formRef.current?.querySelector<HTMLElement>('input, textarea')?.focus());
  }

  return (
    <>
      <div className="admin-header">
        <h1>{title}</h1>
        {editing === null ? (
          <Button onClick={() => startEdit('new')}>Añadir {singular}</Button>
        ) : null}
      </div>

      {notice ? <FormAlert tone="success">{notice}</FormAlert> : null}

      <div ref={formRef}>
        {editing !== null ? (
          <CrudForm
            key={editing === 'new' ? 'new' : editing.id}
            fields={fields}
            singular={singular}
            item={editing === 'new' ? null : editing}
            save={save}
            onCancel={() => setEditing(null)}
            onSaved={(name) => {
              setEditing(null);
              setNotice(`Se ha guardado «${name}».`);
              reload();
            }}
          />
        ) : null}
      </div>

      {state.status === 'loading' && <LoadingBlocks label={`Cargando ${title.toLowerCase()}…`} count={3} height={56} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' &&
        (state.data.data.length === 0 ? (
          <EmptyState title={`Todavía no hay ${title.toLowerCase()}`} />
        ) : (
          <ul className="manual-results">
            {state.data.data.map((item) => (
              <li key={item.id} className="manual-results__item">
                <div>
                  <p className="manual-results__name">{item.name}</p>
                  <p className="field__hint">{describe(item)}</p>
                </div>
                <div className="row-actions">
                  <Button variant="secondary" className="button--small" onClick={() => startEdit(item)} aria-label={`Editar ${item.name}`}>
                    Editar
                  </Button>
                  <Button
                    variant="danger"
                    className="button--small"
                    onClick={() => {
                      setNotice(null);
                      setDeleting(item);
                    }}
                    aria-label={`Borrar ${item.name}`}
                  >
                    Borrar
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        ))}

      {deleting ? (
        <DeleteDialog
          name={deleting.name}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove(deleting.id)}
          onDone={() => {
            setNotice(`Se ha borrado «${deleting.name}».`);
            setDeleting(null);
            reload();
          }}
        />
      ) : null}
    </>
  );
}

function CrudForm<T extends { id: number; name: string }>({
  fields,
  singular,
  item,
  save,
  onCancel,
  onSaved,
}: {
  fields: CrudField[];
  singular: string;
  item: T | null;
  save: Props<T>['save'];
  onCancel: () => void;
  onSaved: (name: string) => void;
}) {
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(fields.map((f) => [f.name, item ? String((item as Record<string, unknown>)[f.name] ?? '') : ''])),
  );
  const [error, setError] = useState<ApiError | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const input = Object.fromEntries(fields.map((f) => [f.name, values[f.name].trim() === '' && !f.required ? null : values[f.name]]));
    try {
      await save(input, item?.id);
      onSaved(values.name.trim());
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setSubmitting(false);
    }
  }

  return (
    <form className="form-card form-card--wide" onSubmit={onSubmit} noValidate aria-label={item ? `Editar ${item.name}` : `Nuevo ${singular}`}>
      {error ? <FormAlert>{error.fields ? 'Revisa los campos marcados.' : error.message}</FormAlert> : null}
      {fields.map((f) => {
        const common = {
          label: f.label,
          required: f.required,
          maxLength: f.maxLength,
          minLength: f.minLength,
          value: values[f.name],
          error: fieldError(error?.fields, f.name),
        };
        return f.multiline ? (
          <TextAreaField key={f.name} rows={4} {...common} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))} />
        ) : (
          <TextField key={f.name} {...common} onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))} />
        );
      })}
      <div className="reserve-box__actions">
        <Button type="submit" loading={submitting} loadingText="Guardando…">
          Guardar
        </Button>
        <Button variant="secondary" onClick={onCancel} disabled={submitting}>
          Cancelar
        </Button>
      </div>
    </form>
  );
}

function DeleteDialog({
  name,
  onClose,
  onConfirm,
  onDone,
}: {
  name: string;
  onClose: () => void;
  onConfirm: () => Promise<unknown>;
  onDone: () => void;
}) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function confirm() {
    setSubmitting(true);
    setError(null);
    try {
      await onConfirm();
      onDone();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Ha ocurrido un error inesperado.');
      setSubmitting(false);
    }
  }

  return (
    <Modal
      title={`¿Borrar «${name}»?`}
      role="alertdialog"
      onClose={onClose}
      actions={
        <>
          <Button variant="danger" onClick={confirm} loading={submitting} loadingText="Borrando…" disabled={error !== null}>
            Borrar
          </Button>
          <Button variant="secondary" onClick={onClose} disabled={submitting}>
            Volver
          </Button>
        </>
      }
    >
      <p>Esta acción no se puede deshacer.</p>
      {error ? <FormAlert>{error}</FormAlert> : null}
    </Modal>
  );
}
