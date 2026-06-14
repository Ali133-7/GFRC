import { useMemo, useState } from 'react';
import { Play, Pause } from 'lucide-react';
import type { DashboardWidgetItem } from '../types';

interface YoutubeAudioWidgetProps {
  widget: DashboardWidgetItem;
}

function extractYoutubeId(input: string): string | null {
  if (!input) return null;
  const trimmed = input.trim();
  if (/^[a-zA-Z0-9_-]{11}$/.test(trimmed)) return trimmed;
  const match = trimmed.match(
    /(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
  );
  return match?.[1] ?? null;
}

export default function YoutubeAudioWidget({ widget }: YoutubeAudioWidgetProps) {
  const ds = widget.data_source || {};
  const videoId = useMemo(
    () => extractYoutubeId(String(ds.video_id || '')),
    [ds.video_id]
  );
  const [playing, setPlaying] = useState(false);

  if (!videoId) {
    return (
      <div className="flex h-full w-full items-center justify-center text-sm text-gray-500">
        يرجى إعداد رابط YouTube صالح
      </div>
    );
  }

  return (
    <div className="flex h-full w-full flex-col items-center justify-center p-4">
      <iframe
        className="hidden"
        src={`https://www.youtube.com/embed/${videoId}?autoplay=${playing ? 1 : 0}&mute=0`}
        title="YouTube audio"
        allow="autoplay"
      />
      <button
        type="button"
        onClick={() => setPlaying((p) => !p)}
        className="inline-flex items-center gap-2 rounded-full bg-red-600 px-5 py-2.5 text-white shadow hover:bg-red-700"
      >
        {playing ? <Pause size={18} /> : <Play size={18} />}
        <span>{playing ? 'إيقاف' : 'تشغيل'}</span>
      </button>
      <div className="mt-2 text-xs text-gray-500">YouTube Audio</div>
    </div>
  );
}
