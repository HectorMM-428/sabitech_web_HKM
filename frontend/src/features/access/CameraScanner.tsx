import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { useEffect, useRef, useState } from 'react';

interface Props {
  /** Se llama con el texto leído. Mientras `paused` sea true, las lecturas se ignoran. */
  onCode: (text: string) => void;
  paused: boolean;
}

/** Ignorar el mismo código durante 3 s: la cámara lo lee muchas veces por segundo. */
const SAME_CODE_COOLDOWN_MS = 3000;

/**
 * Lector de QR con la cámara trasera. Se carga aparte (import dinámico) para no
 * aumentar el tamaño de la aplicación para quien no es personal de acceso.
 * La cámara solo funciona en HTTPS o en localhost.
 */
export default function CameraScanner({ onCode, paused }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [error, setError] = useState<string | null>(() =>
    window.isSecureContext ? null : 'La cámara necesita una conexión segura (HTTPS). Usa el registro manual o abre la web por https.',
  );
  const pausedRef = useRef(paused);
  const onCodeRef = useRef(onCode);
  const last = useRef<{ text: string; at: number } | null>(null);

  useEffect(() => {
    pausedRef.current = paused;
    onCodeRef.current = onCode;
  }, [paused, onCode]);

  useEffect(() => {
    if (!window.isSecureContext || !videoRef.current) return;
    let controls: IScannerControls | null = null;
    let stopped = false;
    const reader = new BrowserQRCodeReader();

    reader
      .decodeFromConstraints({ video: { facingMode: 'environment' } }, videoRef.current, (result) => {
        if (!result || pausedRef.current) return;
        const text = result.getText();
        const now = Date.now();
        if (last.current && last.current.text === text && now - last.current.at < SAME_CODE_COOLDOWN_MS) return;
        last.current = { text, at: now };
        onCodeRef.current(text);
      })
      .then((c) => {
        if (stopped) c.stop();
        else controls = c;
      })
      .catch((e: unknown) => {
        const name = e instanceof DOMException ? e.name : '';
        setError(
          name === 'NotAllowedError'
            ? 'Permiso de cámara denegado. Actívalo en los ajustes del navegador o usa el registro manual.'
            : name === 'NotFoundError'
              ? 'No se ha encontrado ninguna cámara. Usa el registro manual.'
              : 'No se pudo iniciar la cámara. Usa el registro manual.',
        );
      });

    return () => {
      stopped = true;
      controls?.stop();
    };
  }, []);

  if (error) {
    return (
      <div className="alert alert--warning" role="alert">
        <span className="alert__icon" aria-hidden="true">
          !
        </span>
        <div>{error}</div>
      </div>
    );
  }

  return (
    <div className="scanner">
      <video ref={videoRef} className="scanner__video" muted playsInline aria-label="Vista de la cámara para leer el código QR" />
      <p className="field__hint">{paused ? 'Validando…' : 'Apunta la cámara al código QR de la persona.'}</p>
    </div>
  );
}
