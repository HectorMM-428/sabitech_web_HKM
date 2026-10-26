/**
 * Tipos de la API derivados del contrato docs/api/openapi.yaml.
 * schema.d.ts se genera con `npm run gen:api`; no se edita a mano.
 */
import type { components } from './schema';

type Schemas = components['schemas'];

export type TalkSummary = Schemas['TalkSummary'];
export type TalkDetail = Schemas['TalkDetail'];
export type Speaker = Schemas['Speaker'];
export type Room = Schemas['Room'];
export type PageMeta = Schemas['PageMeta'];
export type User = Schemas['User'];
export type Reservation = Schemas['Reservation'];
export type NotificationStatus = Schemas['NotificationStatus'];
export type UserRole = User['role'];
export type ApiErrorBody = Schemas['Error'];
export type ErrorCode = ApiErrorBody['error']['code'];

export interface Paginated<T> {
  data: T[];
  meta: PageMeta;
}

export interface Single<T> {
  data: T;
}
