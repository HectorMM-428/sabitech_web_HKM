import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach } from 'vitest';

// Con varias pruebas en paralelo (y en la CI) 1 s de espera por defecto se queda corto.
configure({ asyncUtilTimeout: 3000 });

afterEach(() => {
  cleanup();
});
