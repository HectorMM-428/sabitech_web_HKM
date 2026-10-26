import { useState, type FormEvent } from 'react';
import { manualCheckIn, searchTalkReservations } from '../../api/access';
import { ApiError } from '../../api/client';
import type { StaffReservationRow } from '../../api/types';
import { fieldError } from '../../components/ui/fieldError';
import { Button, FormAlert, TextField } from '../../components/ui/Form';
import type { CheckInOutcome } from './CheckInResultPanel';

/**
 * A15 + A16 · Registro manual cuando la persona no puede mostrar el QR (R20).
 * El motivo es obligatorio y queda guardado junto a quién validó.
 */
export function ManualCheckIn({ talkId, onOutcome }: { talkId: number; onOutcome: (o: CheckInOutcome) => void }) {
  const [q, setQ] = useState('');
  const [rows, setRows] = useState<StaffReservationRow[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<ApiError | null>(null);
  const [selected, setSelected] = useState<StaffReservationRow | null>(null);

  async function onSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSearching(true);
    setSearchError(null);
    setSelected(null);
    try {
      const res = await searchTalkReservations(talkId, q.trim());
      setRows(res.data);
    } catch (e) {
      setSearchError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setRows(null);
    } finally {
      setSearching(false);
    }
  }

  return (
    <div className="manual-checkin">
      <form className="toolbar" role="search" aria-label="Buscar reserva" onSubmit={onSearch}>
        <TextField
          label="Nombre o referencia"
          value={q}
          minLength={2}
          maxLength={100}
          onChange={(e) => setQ(e.target.value)}
          error={fieldError(searchError?.fields, 'q')}
        />
        <Button type="submit" loading={searching} loadingText="Buscando…" disabled={q.trim().length < 2}>
          Buscar
        </Button>
      </form>

      {searchError && !searchError.fields ? <FormAlert>{searchError.message}</FormAlert> : null}

      {rows !== null && (
        <div role="status" aria-live="polite">
          {rows.length === 0 ? (
            <p className="muted">No hay reservas confirmadas que coincidan con «{q}».</p>
          ) : (
            <p className="muted">{rows.length === 1 ? '1 resultado' : `${rows.length} resultados`}</p>
          )}
        </div>
      )}

      {rows && rows.length > 0 ? (
        <ul className="manual-results">
          {rows.map((row) => (
            <li key={row.reservation_id} className="manual-results__item">
              <div>
                <p className="manual-results__name">{row.attendee_name}</p>
                <p className="mono field__hint">{row.reference}</p>
              </div>
              {row.attended ? (
                <span className="badge badge--info">
                  <span aria-hidden="true">●</span> Ya registrado
                </span>
              ) : (
                <Button variant="secondary" className="button--small" onClick={() => setSelected(row)} aria-label={`Registrar a ${row.attendee_name}`}>
                  Registrar
                </Button>
              )}
            </li>
          ))}
        </ul>
      ) : null}

      {selected ? (
        <ReasonForm
          row={selected}
          onCancel={() => setSelected(null)}
          onDone={(o) => {
            setSelected(null);
            if (o.kind === 'ok') {
              setRows((prev) => prev?.map((r) => (r.reservation_id === selected.reservation_id ? { ...r, attended: true } : r)) ?? null);
            }
            onOutcome(o);
          }}
        />
      ) : null}
    </div>
  );
}

function ReasonForm({ row, onCancel, onDone }: { row: StaffReservationRow; onCancel: () => void; onDone: (o: CheckInOutcome) => void }) {
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const res = await manualCheckIn(row.reservation_id, reason.trim());
      onDone({ kind: 'ok', result: res.data });
    } catch (e) {
      const err = e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.');
      if (err.fields) {
        setError(err);
        setSubmitting(false);
      } else {
        onDone({ kind: 'error', error: err });
      }
    }
  }

  return (
    <form className="form-card" onSubmit={onSubmit} aria-label={`Registro manual de ${row.attendee_name}`}>
      <p>
        Registrar la asistencia de <strong>{row.attendee_name}</strong> (<span className="mono">{row.reference}</span>).
      </p>
      <div className="field">
        <label className="field__label" htmlFor="manual-reason">
          Motivo <span aria-hidden="true">*</span>
        </label>
        <p id="manual-reason-hint" className="field__hint">
          Por ejemplo: «Móvil sin batería; identidad comprobada con DNI».
        </p>
        <textarea
          id="manual-reason"
          className="field__input"
          required
          minLength={3}
          maxLength={255}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          aria-describedby={fieldError(error?.fields, 'reason') ? 'manual-reason-hint manual-reason-error' : 'manual-reason-hint'}
          aria-invalid={fieldError(error?.fields, 'reason') ? true : undefined}
        />
        {fieldError(error?.fields, 'reason') ? (
          <p id="manual-reason-error" className="field__error">
            <span aria-hidden="true">✕ </span>
            {fieldError(error?.fields, 'reason')}
          </p>
        ) : null}
      </div>
      <div className="reserve-box__actions">
        <Button type="submit" loading={submitting} loadingText="Registrando…" disabled={reason.trim().length < 3}>
          Registrar asistencia
        </Button>
        <Button variant="secondary" onClick={onCancel} disabled={submitting}>
          Cancelar
        </Button>
      </div>
    </form>
  );
}
