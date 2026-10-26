import { Link, useParams } from 'react-router';
import { ApiError } from '../../api/client';
import { getTalk } from '../../api/talks';
import type { TalkDetail as TalkDetailData } from '../../api/types';
import { EmptyState, ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTimeRange } from '../../lib/datetime';
import { Availability } from './Availability';

/** Detalle de una charla publicada (A02). */
export function TalkDetailPage() {
  const params = useParams();
  const id = Number(params.talkId);
  const validId = Number.isInteger(id) && id > 0;

  const { state, reload } = useApi(
    (signal) =>
      validId ? getTalk(id, signal) : Promise.reject(new ApiError(404, 'NOT_FOUND', 'No se ha encontrado la charla.')),
    String(params.talkId),
  );

  useDocumentTitle(state.status === 'success' ? state.data.data.title : 'Charla');

  return (
    <div className="page">
      <p className="back-link">
        <Link to="/">← Volver al programa</Link>
      </p>

      {state.status === 'loading' && <LoadingBlocks label="Cargando la charla…" count={1} height={320} />}

      {state.status === 'error' &&
        (state.error.isNotFound ? (
          <>
            <h1 className="visually-hidden">Charla no encontrada</h1>
            <EmptyState title="Esta charla no existe o no está publicada">
              <Link to="/">Consulta el programa completo</Link>
            </EmptyState>
          </>
        ) : (
          <ErrorState error={state.error} onRetry={reload} />
        ))}

      {state.status === 'success' && <TalkDetail talk={state.data.data} />}
    </div>
  );
}

function TalkDetail({ talk }: { talk: TalkDetailData }) {
  return (
    <article className="talk-detail">
      <header className="talk-detail__header">
        <h1>{talk.title}</h1>
        <Availability available={talk.available_seats} capacity={talk.capacity} />
      </header>

      <dl className="talk-detail__facts">
        <div>
          <dt>Fecha</dt>
          <dd>{formatDay(talk.starts_at)}</dd>
        </div>
        <div>
          <dt>Horario</dt>
          <dd className="mono">{formatTimeRange(talk.starts_at, talk.ends_at)}</dd>
        </div>
        <div>
          <dt>Ubicación</dt>
          <dd>
            {talk.room.name}
            {talk.room.location_details ? <span className="muted"> · {talk.room.location_details}</span> : null}
          </dd>
        </div>
        <div>
          <dt>Aforo</dt>
          <dd>{talk.capacity} plazas</dd>
        </div>
      </dl>

      <section className="talk-detail__section" aria-labelledby="descripcion">
        <h2 id="descripcion">Descripción</h2>
        <p className="talk-detail__description">{talk.description}</p>
      </section>

      <section className="talk-detail__section speaker" aria-labelledby="ponente">
        <h2 id="ponente">Ponente</h2>
        <div className="speaker__body">
          {talk.speaker.photo_url ? (
            <img className="speaker__photo" src={talk.speaker.photo_url} alt={talk.speaker.photo_alt ?? ''} width={96} height={96} />
          ) : null}
          <div>
            <p className="speaker__name">{talk.speaker.name}</p>
            {talk.speaker.affiliation ? <p className="muted">{talk.speaker.affiliation}</p> : null}
            {talk.speaker.bio ? <p className="speaker__bio">{talk.speaker.bio}</p> : null}
          </div>
        </div>
      </section>
    </article>
  );
}
