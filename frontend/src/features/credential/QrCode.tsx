import QRCode from 'qrcode';
import { useMemo } from 'react';

const QUIET_ZONE = 4;

/**
 * Dibuja el QR como SVG de forma síncrona a partir del token del servidor.
 * Al ser síncrono, el código cambia de una vez al renovarse, sin parpadeos.
 *
 * role="img" con etiqueta fija: el lector de pantalla no anuncia cada renovación.
 */
export function QrCode({ value, label, size = 280 }: { value: string; label: string; size?: number }) {
  const { path, dimension } = useMemo(() => {
    const qr = QRCode.create(value, { errorCorrectionLevel: 'M' });
    const n = qr.modules.size;
    let d = '';
    for (let row = 0; row < n; row++) {
      for (let col = 0; col < n; col++) {
        if (qr.modules.get(row, col)) d += `M${col + QUIET_ZONE} ${row + QUIET_ZONE}h1v1h-1z`;
      }
    }
    return { path: d, dimension: n + QUIET_ZONE * 2 };
  }, [value]);

  return (
    <svg
      className="credential__qr"
      role="img"
      aria-label={label}
      width={size}
      height={size}
      viewBox={`0 0 ${dimension} ${dimension}`}
      shapeRendering="crispEdges"
    >
      <rect width={dimension} height={dimension} fill="#ffffff" />
      <path d={path} fill="#000000" />
    </svg>
  );
}
