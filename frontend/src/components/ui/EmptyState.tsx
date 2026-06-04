export function EmptyState({ message = 'لا توجد بيانات' }: { message?: string }) {
  return (
    <div className="flex h-48 flex-col items-center justify-center text-gray-500">
      <p>{message}</p>
    </div>
  );
}
