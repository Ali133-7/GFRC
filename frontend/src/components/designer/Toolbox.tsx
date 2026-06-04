import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

export default function Toolbox() {
  const navigate = useNavigate();
  const [moreOpen, setMoreOpen] = useState(false);

  const handleSave = () => {
    alert('💾 تم حفظ التصميم');
  };

  return (
    <div className="flex space-x-2 items-center">
      <button
        onClick={handleSave}
        className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition font-medium text-sm"
        title="حفظ التصميم"
      >
        💾 حفظ
      </button>

      <button
        onClick={() => navigate('/registers')}
        className="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition text-sm"
        title="العودة للسجلات"
      >
        ← رجوع
      </button>

      {/* More dropdown */}
      <div className="relative">
        <button
          onClick={() => setMoreOpen(!moreOpen)}
          className="px-3 py-2 bg-gray-400 text-gray-900 rounded hover:bg-gray-500 transition font-medium text-sm"
        >
          المزيد ▼
        </button>
        {moreOpen && (
          <div className="absolute right-0 mt-1 w-48 bg-white border border-gray-300 rounded shadow-lg z-10">
            <button
              onClick={() => {
                alert('👁️ معاينة الوصل كما سيظهر للمستخدم');
                setMoreOpen(false);
              }}
              className="block w-full text-right px-4 py-2 text-sm hover:bg-blue-50 border-b border-gray-100"
            >
              👁️ معاينة
            </button>
            <button
              onClick={() => {
                alert('📖 دليل المساعدة:\n\n• اسحب العناصر لتحريكها\n• اضغط على الزوايا لتغيير الحجم\n• استخدم الشريط الجانبي للتنسيق');
                setMoreOpen(false);
              }}
              className="block w-full text-right px-4 py-2 text-sm hover:bg-blue-50 border-b border-gray-100"
            >
              ❓ مساعدة
            </button>
            <button
              onClick={() => {
                alert('ℹ️ مصمم القوالب v1.0\nنظام تصميم قوالب الوصولات المالية');
                setMoreOpen(false);
              }}
              className="block w-full text-right px-4 py-2 text-sm hover:bg-blue-50"
            >
              ℹ️ حول
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
