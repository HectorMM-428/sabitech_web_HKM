import { Link } from 'react-router';
import type { TalkSummary } from '../../api/types';
import { formatDay, formatTimeRange } from '../../lib/datetime';
import { Availability } from './Availability';

export function TalkCard({ talk }: { talk: TalkSummary }) {
  return (
    <article className="talk-card">
      <h2 className="talk-card__title">
        <Link to={`/charlas/${talk.id}`} className="talk-card__link">
          {talk.title}
        </Link>
      </h2>
      <p className="talk-card__speaker">{talk.speaker.name}</p>
      <dl className="talk-card__meta">
        <div>
          <dt>Fecha</dt>
          <dd>{formatDay(talk.starts_at)}</dd>
        </div>
        <div>
          <dt>Horario</dt>
          <dd className="mono">{formatTimeRange(talk.starts_at, talk.ends_at)}</dd>
        </div>
        <div>
          <dt>Sala</dt>
          <dd>{talk.room.name}</dd>
        </div>
      </dl>
      <Availability available={talk.available_seats} capacity={talk.capacity} />
    </article>
  );
}
