import { lazy, Suspense, useCallback, useRef, useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router';
import { checkIn } from '../../api/access';
import { ApiError } from '../../api/client';
import { getTalk } from '../../api/talks';
import { Button } from '../../components/ui/Form';
import { ErrorState, LoadingBlocks } from '../../components/ui/StateViews';
import { useApi } from '../../hooks/useApi';
import { useDocumentTitle } from '../../hooks/useDocumentTitle';
import { formatDay, formatTimeRange } from '../../lib/datetime';
import { CheckInResultPanel, type CheckInOutcome } from './CheckInResultPanel';
import { ManualCheckIn } from './ManualCheckIn';

const CameraScanner = lazy(() => import('./CameraScanner'));

type Mode = 'camera' | 'manual';

/** A14 · Control de una charla: escáner por cámara, código pegado o registro manual. */
export function ScannerPage() {
  const talkId = Number(useParams().talkId);
  const talk = useApi((signal) => getTalk(talkId, signal), `access-talk-${talkId}`);
  useDocumentTitle(talk.state.status === 'success' ? `Control · ${talk.state.data.data.title}` : 'Control de acceso');

  const [mode, setMode] = useState<Mode>('camera');
  const [busy, setBusy] = useState(false);
  const [outcome, setOutcome] = useState<CheckInOutcome | null>(null);
  const busyRef = useRef(false);

  const validate = useCallback(
    async (token: string) => {
      if (busyRef.current) return; // una validación cada vez
      busyRef.current = true;
      setBusy(true);
      try {
        const res = await checkIn(token.trim(), talkId);
        setOutcome({ kind: 'ok', result: res.data });
      } catch (e) {
        setOutcome({ kind: 'error', error: e instanceof ApiError ? e : new ApiError(0, 'UNKNOWN', 'Ha ocurrido un error inesperado.') });
      } finally {
        busyRef.current = false;
        setBusy(false);
      }
    },
    [talkId],
  );

  return (
    <div className="page">
      <p className="back-link">
        <Link to="/acceso">← Charlas del día</Link>
      </p>

      {talk.state.status === 'loading' && <LoadingBlocks label="Cargando la charla…" count={1} height={80} />}
      {talk.state.status === 'error' && <ErrorState error={talk.state.error} onRetry={talk.reload} />}
      {talk.state.status === 'success' && (
        <>
          <h1>{talk.state.data.data.title}</h1>
          <p className="muted page-intro">
            {formatDay(talk.state.data.data.starts_at)} ·{' '}
            <span className="mono">{formatTimeRange(talk.state.data.data.starts_at, talk.state.data.data.ends_at)}</span> ·{' '}
            {talk.state.data.data.room.name}
          </p>

          <div className="segmented" role="group" aria-label="Modo de registro">
            <button type="button" className="segmented__option" aria-pressed={mode === 'camera'} onClick={() => setMode('camera')}>
              Escanear QR
            </button>
            <button type="button" className="segmented__option" aria-pressed={mode === 'manual'} onClick={() => setMode('manual')}>
              Registro manual
            </button>
          </div>

          <div className="scanner-layout">
            <div>
              {mode === 'camera' ? (
                <>
                  <Suspense fallback={<LoadingBlocks label="Preparando la cámara…" count={1} height={240} />}>
                    <CameraScanner onCode={validate} paused={busy} />
                  </Suspense>
                  <PasteToken onSubmit={validate} busy={busy} />
                </>
              ) : (
                <ManualCheckIn talkId={talkId} onOutcome={setOutcome} />
              )}
            </div>
            <CheckInResultPanel outcome={outcome} />
          </div>
        </>
      )}
    </div>
  );
}

/** Alternativa sin cámara: pegar el texto del código (lector externo o pruebas). */
function PasteToken({ onSubmit, busy }: { onSubmit: (token: string) => void; busy: boolean }) {
  const [token, setToken] = useState('');

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (token.trim()) onSubmit(token);
    setToken('');
  }

  return (
    <details className="paste-token">
      <summary>¿Usas un lector externo? Introduce el código</summary>
      <form onSubmit={submit} className="toolbar">
        <div className="field" style={{ flex: 1 }}>
          <label className="field__label" htmlFor="paste-token">
            Código leído
          </label>
          <input id="paste-token" className="field__input" value={token} onChange={(e) => setToken(e.target.value)} autoComplete="off" />
        </div>
        <Button type="submit" loading={busy} loadingText="Validando…" disabled={!token.trim()}>
          Validar
        </Button>
      </form>
    </details>
  );
}
