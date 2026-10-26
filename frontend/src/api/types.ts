/**
 * Tipos de la API derivados del contrato docs/api/openapi.yaml.
 * schema.d.ts se genera con `npm run gen:api`; no se edita a mano.
 */
import type { components, operations } from './schema';

type Schemas = components['schemas'];

export type TalkSummary = Schemas['TalkSummary'];
export type TalkDetail = Schemas['TalkDetail'];
export type Speaker = Schemas['Speaker'];
export type Room = Schemas['Room'];
export type PageMeta = Schemas['PageMeta'];
export type User = Schemas['User'];
export type Reservation = Schemas['Reservation'];
export type NotificationStatus = Schemas['NotificationStatus'];
export type Credential = Schemas['Credential'];
export type StaffTalk = Schemas['StaffTalk'];
export type CheckInResult = Schemas['CheckInResult'];
export type StaffReservationRow = Schemas['StaffReservationRow'];
export type AdminReservationRow = Schemas['AdminReservationRow'];
export type Attendance = Schemas['Attendance'];
export type Notification = Schemas['Notification'];
export type TalkStatus = Schemas['TalkStatus'];
export type TalkInput = Schemas['TalkInput'];
export type SpeakerInput = Schemas['SpeakerInput'];
export type RoomInput = Schemas['RoomInput'];
export type AdminStats = operations['adminStats']['responses']['200']['content']['application/json']['data'];
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
