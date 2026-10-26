import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { createTalk, getAdminTalk, listRooms, listSpeakers, updateTalk } from '../../api/admin';
import { ApiError } from '../../api/client';
import type { Room, Speaker, TalkDetail } from '../../api/types';
import { fieldError } from '../../components/ui/fieldError';
import { Button, FormAlert, SelectField, TextAreaField, TextField } from '../../components/ui/Form';
import { ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { fromEventLocalInput, toEventLocalInput } from '../../lib/datetime';

interface Lists {
  speakers: Speaker[];
  rooms: Room[];
  talk: TalkDetail | null;
}

/** A18 y A19 · Alta y edición de charlas. Las horas se escriben en hora de Madrid. */
export function TalkFormPage() {
  const params = useParams();
  const id = params.talkId ? Number(params.talkId) : null;
  useDocumentTitle(id ? 'Editar charla · Panel' : 'Nueva charla · Panel');

  const { state, reload } = useApi<Lists>(
    async (signal) => {
      const [speakers, rooms, talk] = await Promise.all([
        listSpeakers(signal),
        listRooms(signal),
        id ? getAdminTalk(id, signal) : Promise.resolve(null),
      ]);
      return { speakers: speakers.data, rooms: rooms.data, talk: talk?.data ?? null };
    },
    `talk-form-${id ?? 'new'}`,
  );

  return (
    <>
      <p className="back-link">
        <Link to="/admin/charlas">← Charlas</Link>
      </p>
      <h1>{id ? 'Editar charla' : 'Nueva charla'}</h1>
      {state.status === 'loading' && <LoadingBlocks label="Cargando el formulario…" count={1} height={420} />}
      {state.status === 'error' && <ErrorState error={state.error} onRetry={reload} />}
      {state.status === 'success' && <TalkForm lists={state.data} id={id} />}
    </>
  );
}

function TalkForm({ lists, id }: { lists: Lists; id: number | null }) {
  const navigate = useNavigate();
  const { talk, speakers, rooms } = lists;
  const [values, setValues] = useState({
    title: talk?.title ?? '',
    description: talk?.description ?? '',
    speaker_id: talk ? String(talk.speaker.id) : '',
    room_id: talk ? String(talk.room.id) : '',
    starts_at: talk ? toEventLocalInput(talk.starts_at) : '',
    ends_at: talk ? toEventLocalInput(talk.ends_at) : '',
    capacity: talk ? String(talk.capacity) : '40',
  });
  const [error, setError] = useState<ApiError | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const set = (field: keyof typeof values) => (e: { target: { value: string } }) => setValues((v) => ({ ...v, [field]: e.target.value }));
  const err = (field: string) => fieldError(error?.fields, field);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const input = {
      title: values.title,
      description: values.description,
      speaker_id: Number(values.speaker_id),
      room_id: Number(values.room_id),
      starts_at: fromEventLocalInput(values.starts_at),
      ends_at: fromEventLocalInput(values.ends_at),
      capacity: Number(values.capacity),
    };
    try {
      if (id) await updateTalk(id, input);
      else await createTalk(input);
      navigate('/admin/charlas', { state: { saved: values.title } });
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.'));
      setSubmitting(false);
    }
  }

  const reservations = talk?.reservations_confirmed ?? 0;

  return (
    <form className="form-card form-card--wide" onSubmit={onSubmit} noValidate>
      {error ? <FormAlert>{topMessage(error)}</FormAlert> : null}

      <TextField label="Título" required minLength={3} maxLength={160} value={values.title} onChange={set('title')} error={err('title')} />
      <TextAreaField
        label="Descripción"
        required
        rows={5}
        maxLength={5000}
        value={values.description}
        onChange={set('description')}
        error={err('description')}
      />

      <div className="form-grid">
        <SelectField label="Ponente" required value={values.speaker_id} onChange={set('speaker_id')} error={err('speaker_id')}>
          <option value="">Elige un ponente</option>
          {speakers.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </SelectField>
        <SelectField label="Sala" required value={values.room_id} onChange={set('room_id')} error={err('room_id')}>
          <option value="">Elige una sala</option>
          {rooms.map((r) => (
            <option key={r.id} value={r.id}>
              {r.name}
            </option>
          ))}
        </SelectField>
        <TextField
          label="Inicio"
          hint="Hora de Madrid"
          type="datetime-local"
          required
          value={values.starts_at}
          onChange={set('starts_at')}
          error={err('starts_at')}
        />
        <TextField
          label="Fin"
          hint="Hora de Madrid"
          type="datetime-local"
          required
          value={values.ends_at}
          onChange={set('ends_at')}
          error={err('ends_at')}
        />
        <TextField
          label="Aforo"
          hint={reservations > 0 ? `Mínimo ${reservations}: hay ${reservations} reservas confirmadas.` : 'Entre 1 y 1000 plazas.'}
          type="number"
          inputMode="numeric"
          required
          min={Math.max(1, reservations)}
          max={1000}
          value={values.capacity}
          onChange={set('capacity')}
          error={err('capacity') ?? (error?.code === 'AFORO_BELOW_CONFIRMED' ? error.message : undefined)}
        />
      </div>

      {!id ? <p className="field__hint">La charla se guarda como borrador; publícala desde el listado cuando esté lista.</p> : null}

      <div className="reserve-box__actions">
        <Button type="submit" loading={submitting} loadingText="Guardando…">
          {id ? 'Guardar cambios' : 'Crear charla'}
        </Button>
        <Link className="button button--secondary" to="/admin/charlas">
          Cancelar
        </Link>
      </div>
    </form>
  );
}

function topMessage(error: ApiError): string {
  if (error.fields?.status) return error.fields.status[0];
  if (error.fields || error.code === 'AFORO_BELOW_CONFIRMED') return 'Revisa los campos marcados.';
  return error.message;
}
