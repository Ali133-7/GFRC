import React from "react";
import { useSearchParams } from "react-router-dom";
import { EnterpriseReportDesigner } from "@/components/reports/designer/EnterpriseReportDesigner";

export default function ReportBuilderPage() {
  const [searchParams] = useSearchParams();
  const editId = searchParams.get("id") || undefined;

  const handleSave = (design: any) => {
    console.log("Saving report design:", design);
    alert("تم حفظ التقرير بنجاح ✓");
  };

  return (
    <div style={{ height: "100vh", overflow: "hidden" }}>
      <EnterpriseReportDesigner 
        reportId={editId} 
        onSave={handleSave} 
      />
    </div>
  );
}
