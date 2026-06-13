import React, { useState, useEffect, useCallback } from "react";
import { useRegisterRelationships } from "@/hooks/useBusinessReportData";
import type { BusinessRegister, RegisterRelationship } from "@/types/report";

interface DataModelDesignerProps {
  registers: BusinessRegister[];
  selectedRegisterIds: string[];
  relationships: RegisterRelationship[];
  onRelationshipsChange: (relationships: RegisterRelationship[]) => void;
}

export function DataModelDesigner({
  registers,
  selectedRegisterIds,
  relationships,
  onRelationshipsChange,
}: DataModelDesignerProps) {
  const { data: analyzedRelationships = [], isLoading } = useRegisterRelationships(selectedRegisterIds);
  const [localJoins, setLocalJoins] = useState<RegisterRelationship[]>(relationships);

  useEffect(() => {
    if (analyzedRelationships.length > 0) {
      // Merge auto-detected relationships with existing ones, preferring existing overrides
      const existingIds = new Set(relationships.map((r) => r.id));
      const merged = [
        ...relationships,
        ...analyzedRelationships.filter((r) => !existingIds.has(r.id)),
      ];
      setLocalJoins(merged);
      onRelationshipsChange(merged);
    }
  }, [analyzedRelationships]);

  useEffect(() => {
    setLocalJoins(relationships);
  }, [relationships]);

  const handleRemoveJoin = useCallback(
    (id: string) => {
      const next = localJoins.filter((j) => j.id !== id);
      setLocalJoins(next);
      onRelationshipsChange(next);
    },
    [localJoins, onRelationshipsChange]
  );

  const selectedRegisters = registers.filter((r) => selectedRegisterIds.includes(r.id));

  return (
    <div style={{ padding: "20px", height: "100%", overflow: "auto" }}>
      <h3 style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px", color: "var(--color-text-primary)" }}>
        🔗 نموذج البيانات
      </h3>

      {/* Selected Registers */}
      <div style={{ marginBottom: "24px" }}>
        <h4
          style={{
            fontSize: "14px",
            fontWeight: 600,
            marginBottom: "12px",
            color: "var(--color-text-secondary)",
          }}
        >
          📁 السجلات المختارة ({selectedRegisters.length})
        </h4>
        {selectedRegisters.length === 0 ? (
          <div
            style={{
              padding: "16px",
              textAlign: "center",
              color: "var(--color-text-tertiary)",
              fontSize: "13px",
              background: "var(--color-background-secondary)",
              borderRadius: "6px",
            }}
          >
            لم يتم اختيار أي سجل. اختر سجلاً واحداً على الأقل من لوحة السجلات.
          </div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))", gap: "12px" }}>
            {selectedRegisters.map((register) => (
              <div
                key={register.id}
                style={{
                  padding: "12px",
                  background: "var(--color-background-primary)",
                  border: "1px solid var(--color-border-tertiary)",
                  borderRadius: "6px",
                }}
              >
                <div
                  style={{
                    fontSize: "13px",
                    fontWeight: 600,
                    marginBottom: "4px",
                    color: "var(--color-text-primary)",
                  }}
                >
                  {register.name}
                </div>
                <div style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>
                  {register.record_count} سجل · Alias: {register.table_alias}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Auto Relationships */}
      <div style={{ marginBottom: "24px" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
          <h4 style={{ fontSize: "14px", fontWeight: 600, color: "var(--color-text-secondary)" }}>
            🔗 العلاقات التلقائية ({localJoins.length})
          </h4>
          {isLoading && (
            <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)" }}>جاري تحليل العلاقات...</span>
          )}
        </div>

        {localJoins.length === 0 ? (
          <div
            style={{
              padding: "24px",
              textAlign: "center",
              color: "var(--color-text-tertiary)",
              fontSize: "13px",
              background: "var(--color-background-secondary)",
              borderRadius: "6px",
            }}
          >
            لا توجد علاقات. اختر سجلين أو أكثر ليتم تحليل الربط التلقائي بينها.
          </div>
        ) : (
          <div style={{ display: "grid", gap: "12px" }}>
            {localJoins.map((join) => (
              <div
                key={join.id}
                style={{
                  padding: "12px",
                  background: "var(--color-background-info)",
                  border: "1px solid var(--color-border-info)",
                  borderRadius: "6px",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                  <div style={{ fontSize: "12px", fontWeight: 600, color: "var(--color-text-primary)" }}>
                    {join.left_register_name}{" "}
                    <span style={{ color: "var(--color-text-info)" }}>{join.join_type} JOIN</span>{" "}
                    {join.right_register_name}
                  </div>
                  <button
                    onClick={() => handleRemoveJoin(join.id)}
                    style={{
                      background: "none",
                      border: "none",
                      cursor: "pointer",
                      fontSize: "16px",
                      color: "var(--color-text-danger)",
                    }}
                  >
                    ×
                  </button>
                </div>
                <div
                  style={{
                    marginTop: "6px",
                    fontSize: "11px",
                    color: "var(--color-text-secondary)",
                    fontFamily: "var(--font-mono)",
                  }}
                >
                  {join.left_table_alias}.{join.relationship_key} = {join.right_table_alias}.{join.relationship_key}
                </div>
                {join.auto_generated && (
                  <div
                    style={{
                      marginTop: "4px",
                      fontSize: "10px",
                      color: "var(--color-text-success)",
                    }}
                  >
                    ✓ تم التوليد تلقائياً
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
