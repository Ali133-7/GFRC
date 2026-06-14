import { useQuery } from '@tanstack/react-query';
import type { DashboardWidgetItem } from '../types';

interface WeatherWidgetProps {
  widget: DashboardWidgetItem;
}

interface OpenMeteoCurrent {
  temperature: number;
  windspeed: number;
  weathercode: number;
}

async function fetchWeather(lat: number, lon: number): Promise<OpenMeteoCurrent> {
  const response = await fetch(
    `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`
  );
  const payload = await response.json();
  return payload.current_weather as OpenMeteoCurrent;
}

export default function WeatherWidget({ widget }: WeatherWidgetProps) {
  const location = widget.data_source?.location || {};
  const lat = Number(location.lat) || 33.3152;
  const lon = Number(location.lon) || 44.3661;

  const { data, isLoading, error } = useQuery({
    queryKey: ['weather', lat, lon],
    queryFn: () => fetchWeather(lat, lon),
  });

  if (isLoading) {
    return (
      <div className="flex h-full w-full items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="flex h-full w-full items-center justify-center text-sm text-red-500">
        تعذر تحميل بيانات الطقس
      </div>
    );
  }

  return (
    <div className="flex h-full w-full flex-col items-center justify-center p-4 text-center">
      <div className="text-4xl font-bold text-blue-600">{Math.round(data.temperature)}°C</div>
      <div className="mt-2 text-sm text-gray-600">
        سرعة الرياح: {data.windspeed} كم/س
      </div>
    </div>
  );
}
