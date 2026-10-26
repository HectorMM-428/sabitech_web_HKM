import { deleteSpeaker, listSpeakers, saveSpeaker } from '../../api/admin';
import type { Speaker, SpeakerInput } from '../../api/types';
import { SimpleCrud } from './SimpleCrud';

/** A21 · Ponentes. */
export function SpeakersPage() {
  return (
    <SimpleCrud<Speaker>
      title="Ponentes"
      singular="ponente"
      fields={[
        { name: 'name', label: 'Nombre', required: true, minLength: 2, maxLength: 120 },
        { name: 'affiliation', label: 'Organización', maxLength: 160 },
        { name: 'bio', label: 'Biografía', maxLength: 2000, multiline: true },
      ]}
      load={listSpeakers}
      save={(input, id) => saveSpeaker(input as unknown as SpeakerInput, id)}
      remove={deleteSpeaker}
      describe={(s) => s.affiliation ?? 'Sin organización'}
    />
  );
}
