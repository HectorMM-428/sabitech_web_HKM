import { deleteRoom, listRooms, saveRoom } from '../../api/admin';
import type { Room, RoomInput } from '../../api/types';
import { SimpleCrud } from './SimpleCrud';

/** A22 · Salas. */
export function RoomsPage() {
  return (
    <SimpleCrud<Room>
      title="Salas"
      singular="sala"
      fields={[
        { name: 'name', label: 'Nombre', required: true, minLength: 2, maxLength: 80 },
        { name: 'location_details', label: 'Ubicación', maxLength: 255 },
      ]}
      load={listRooms}
      save={(input, id) => saveRoom(input as unknown as RoomInput, id)}
      remove={deleteRoom}
      describe={(r) => r.location_details ?? 'Sin indicaciones'}
    />
  );
}
