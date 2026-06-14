import type { DashboardWidgetItem } from '../types';
import { getWidgetTitle } from '../types';

interface TextBlockWidgetProps {
  widget: DashboardWidgetItem;
}

export default function TextBlockWidget({ widget }: TextBlockWidgetProps) {
  const content = widget.data_source?.content || getWidgetTitle(widget);

  return (
    <div className="h-full w-full overflow-auto p-4">
      <div className="whitespace-pre-wrap text-sm leading-relaxed text-gray-800">{content}</div>
    </div>
  );
}
