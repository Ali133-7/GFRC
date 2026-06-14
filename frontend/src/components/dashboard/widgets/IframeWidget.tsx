import type { DashboardWidgetItem } from '../types';
import { getWidgetTitle } from '../types';

interface IframeWidgetProps {
  widget: DashboardWidgetItem;
}

export default function IframeWidget({ widget }: IframeWidgetProps) {
  const url = widget.data_source?.url || '';

  if (!url) {
    return (
      <div className="flex h-full w-full items-center justify-center text-sm text-gray-500">
        يرجى إعداد عنوان URL
      </div>
    );
  }

  return (
    <div className="h-full w-full p-1">
      <iframe
        src={url}
        title={getWidgetTitle(widget)}
        className="h-full w-full rounded border border-gray-200"
        allow="fullscreen"
      />
    </div>
  );
}
