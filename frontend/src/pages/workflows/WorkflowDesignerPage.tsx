import { useState, useEffect, useCallback, useMemo } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { toBoolean } from "@/lib/boolean";
import { useNavigate, useParams } from "react-router-dom";
import {
  useWorkflow,
  useWorkflowVersions,
  useWorkflowVersion,
  usePublishVersion,
  useCloneVersion,
  useCreateVersion,
  useCreateStep,
  useUpdateStep,
  useDeleteStep,
  useCreateWorkflowField,
  useUpdateWorkflowField,
  useDeleteWorkflowField,
  useReorderWorkflowFields,
  useCreateWorkflowRule,
  useUpdateWorkflowRule,
  useDeleteWorkflowRule,
  usePreviewExecution,
} from "@/hooks/useWorkflows";
import { useDeleteEnterpriseRule } from "@/hooks/useEnterpriseRules";
import { useRegisterFields, useRegisters } from "@/hooks/useRegisters";
import { useOfficialFees } from "@/hooks/useFees";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { PageHeader } from "@/components/layout/PageHeader";
import { LoadingSpinner } from "@/components/ui/LoadingSpinner";
import { GovSelect, GovSelectMulti } from "@/components/ui/GovSelect";
import CaseRuleBuilder from "@/components/rules/CaseRuleBuilder";
import SimpleRuleBuilder from "@/components/rules/SimpleRuleBuilder";
import RoutingRuleBuilder from "@/components/rules/RoutingRuleBuilder";
import { classifyRule, type RuleEditorKind } from "@/components/rules/ruleEditorResolver";
import ValidationRuleBuilder from "@/components/validation/ValidationRuleBuilder";
import EnterpriseRuleBuilder from "@/components/validation/EnterpriseRuleBuilder";
import type {
  WorkflowVersion,
  WorkflowStep,
  WorkflowField,
  WorkflowRule,
  ConditionLogic,
  RuleAction,
} from "@/types/workflow";

type Tab = "versions" | "steps" | "fields" | "rules" | "preview";

export default function WorkflowDesignerPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<Tab>("versions");
  const [selectedVersionId, setSelectedVersionId] = useState<string | null>(null);

  const { data: workflow, isLoading } = useWorkflow(id ?? "");
  const { data: versions, isLoading: versionsLoading } = useWorkflowVersions(id ?? "");
  const { data: versionDetail, error: versionDetailError } = useWorkflowVersion(id ?? "", selectedVersionId ?? "");
  const { data: registerFields } = useRegisterFields(workflow?.register_id ?? "");
  const { data: allRegisters } = useRegisters();

  // CRITICAL: Clear ALL cache and reset on workflow change
  useEffect(() => {
    setSelectedVersionId(null);
    // Clear EVERYTHING related to workflows
    queryClient.clear();
  }, [id]);

  // CRITICAL: If version detail fetch fails (404), reset to versions list
  useEffect(() => {
    if (versionDetailError) {
      console.warn('[WorkflowDesigner] Version detail fetch failed, resetting selection');
      setSelectedVersionId(null);
      queryClient.removeQueries({ queryKey: ["workflows", id, "versions", selectedVersionId] });
    }
  }, [versionDetailError, id, selectedVersionId, queryClient]);

  // Auto-select active or first version when versions load
  useEffect(() => {
    if (!selectedVersionId && versions && versions.length > 0) {
      const active = versions.find((v: WorkflowVersion) => v.status === "active");
      setSelectedVersionId((active ?? versions[0]).id);
    }
  }, [versions, selectedVersionId]);

  const publishMut = usePublishVersion();
  const cloneMut = useCloneVersion();
  const createVersionMut = useCreateVersion();
  const createStepMut = useCreateStep();
  const updateStepMut = useUpdateStep();
  const deleteStepMut = useDeleteStep();
  const createFieldMut = useCreateWorkflowField();
  const updateFieldMut = useUpdateWorkflowField();
  const deleteFieldMut = useDeleteWorkflowField();
  const reorderFieldMut = useReorderWorkflowFields();
  const createRuleMut = useCreateWorkflowRule();
  const updateRuleMut = useUpdateWorkflowRule();
  const deleteRuleMut = useDeleteWorkflowRule();
  const previewMut = usePreviewExecution();

  // Authoritative selection: the version-list entry matching the chosen id (carries the
  // correct, up-to-date status). versionDetail (a separately-fetched, possibly-stale query)
  // is used only for rich tab content, and ONLY when it matches the selected id — otherwise
  // status/id could disagree and the publish button would target the wrong version.
  const selectedVersionMeta =
    versions?.find((v: WorkflowVersion) => v.id === selectedVersionId) ??
    versions?.find((v: WorkflowVersion) => v.status === "active") ??
    versions?.[0];

  const selectedVersion =
    versionDetail && versionDetail.id === selectedVersionMeta?.id ? versionDetail : selectedVersionMeta;

  // isDraft is derived from the authoritative list status, never from the rich detail.
  const isDraft = selectedVersionMeta?.status === "draft";

  const tabBtn = (tab: Tab, label: string) => (
    <button
      onClick={() => setActiveTab(tab)}
      style={{
        padding: "8px 16px",
        fontSize: "13px",
        fontWeight: activeTab === tab ? 500 : 400,
        color: activeTab === tab ? "var(--color-text-info)" : "var(--color-text-secondary)",
        background: activeTab === tab ? "var(--color-background-info)" : "transparent",
        border: "none",
        borderRadius: "var(--border-radius-md)",
        cursor: "pointer",
        fontFamily: "inherit",
        borderBottom: activeTab === tab ? "2px solid var(--color-border-info)" : "2px solid transparent",
      }}
    >
      {label}
    </button>
  );

  if (isLoading || !workflow) {
    return (
      <div dir="rtl" style={{ padding: "48px", textAlign: "center" }}>
        <LoadingSpinner />
      </div>
    );
  }

  return (
    <div dir="rtl" style={{ padding: "24px", fontFamily: "'Noto Sans Arabic', sans-serif" }}>
      <PageHeader
        title={workflow.name_ar}
        subtitle={`${workflow.code} · ${workflow.register?.name_ar}`}
        back={{ label: "← رجوع", onClick: () => navigate("/workflows") }}
        action={{
          label: "تشغيل",
          onClick: () => {
            const active = versions?.find((v: WorkflowVersion) => v.status === "active");
            if (active) navigate(`/workflows/${workflow.id}/execute?version=${active.id}`);
          },
          variant: "primary",
        }}
      />

      {/* Version selector */}
      <div style={{ display: "flex", gap: "8px", marginBottom: "16px", overflowX: "auto", paddingBottom: "4px" }}>
        {versions?.map((v: WorkflowVersion) => (
          <button
            key={v.id}
            onClick={() => setSelectedVersionId(v.id)}
            style={{
              padding: "6px 12px",
              fontSize: "12px",
              borderRadius: "6px",
              border: `0.5px solid ${selectedVersionId === v.id ? "var(--color-border-info)" : "var(--color-border-secondary)"}`,
              background: selectedVersionId === v.id ? "var(--color-background-info)" : "var(--color-background-primary)",
              color: selectedVersionId === v.id ? "var(--color-text-info)" : "var(--color-text-secondary)",
              cursor: "pointer",
              fontFamily: "inherit",
              whiteSpace: "nowrap",
            }}
          >
            V{v.version} · {v.status === "active" ? "منشورة" : v.status === "draft" ? "مسودة" : "مؤرشفة"}
          </button>
        ))}
        <button
          onClick={() => {
            if (confirm('هل أنت متأكد من إنشاء نسخة جديدة؟ سيتم إنشاء نسخة مسودة من النسخة الحالية.')) {
              createVersionMut.mutate({ workflowId: workflow.id });
            }
          }}
          disabled={createVersionMut.isPending}
          style={{
            padding: "6px 12px",
            fontSize: "12px",
            borderRadius: "6px",
            border: "0.5px dashed var(--color-border-info)",
            background: "transparent",
            color: "var(--color-text-info)",
            cursor: "pointer",
            fontFamily: "inherit",
            whiteSpace: "nowrap",
          }}
        >
          + نسخة جديدة
        </button>
      </div>

      {/* Version actions */}
      {selectedVersion && (
        <div style={{ display: "flex", gap: "8px", marginBottom: "16px", alignItems: "center" }}>
          <span style={{ fontSize: "13px", color: "var(--color-text-primary)", fontWeight: 500 }}>
            النسخة المختارة: V{selectedVersion.version}
          </span>
          {isDraft && (
            <button
              onClick={() =>
                publishMut.mutate(
                  { workflowId: workflow.id, versionId: selectedVersion.id },
                  {
                    onError: (err: any) => {
                      const msg = err?.response?.data?.message || "فشل نشر النسخة";
                      alert(msg);
                    },
                  }
                )
              }
              disabled={publishMut.isPending}
              style={{
                padding: "5px 12px",
                fontSize: "12px",
                background: "var(--color-background-success)",
                color: "var(--color-text-success)",
                border: "0.5px solid var(--color-border-success)",
                borderRadius: "6px",
                cursor: "pointer",
                fontFamily: "inherit",
              }}
            >
              نشر
            </button>
          )}
          <button
            onClick={() => cloneMut.mutate({ workflowId: workflow.id, versionId: selectedVersion.id })}
            disabled={cloneMut.isPending}
            style={{
              padding: "5px 12px",
              fontSize: "12px",
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "6px",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            استنساخ
          </button>
        </div>
      )}

      {/* Tabs */}
      <div style={{ display: "flex", gap: "4px", borderBottom: "0.5px solid var(--color-border-tertiary)", marginBottom: "16px" }}>
        {tabBtn("versions", "الإصدارات")}
        {tabBtn("steps", "الخطوات")}
        {tabBtn("fields", "الحقول")}
        {tabBtn("rules", "القواعد")}
        {tabBtn("preview", "معاينة")}
      </div>

      {/* Tab content */}
      <div>
        {activeTab === "versions" && <VersionsTab versions={versions} />}
        {activeTab === "steps" && (
          <StepsTab
            workflowId={workflow.id}
            version={selectedVersion}
            isDraft={isDraft}
            createStep={createStepMut}
            updateStep={updateStepMut}
            deleteStep={deleteStepMut}
          />
        )}
        {activeTab === "fields" && (
          <FieldsTab
            workflowId={workflow.id}
            version={selectedVersion}
            isDraft={isDraft}
            registerFields={registerFields}
            createField={createFieldMut}
            updateField={updateFieldMut}
            deleteField={deleteFieldMut}
            reorderFields={reorderFieldMut}
          />
        )}
        {activeTab === "rules" && (
          <UnifiedRulesTab
            workflowId={workflow.id}
            version={selectedVersion}
            isDraft={isDraft}
            fields={selectedVersion?.fields ?? []}
            registers={allRegisters ?? []}
            createRule={createRuleMut}
            updateRule={updateRuleMut}
            deleteRule={deleteRuleMut}
          />
        )}
        {activeTab === "preview" && (
          <PreviewTab version={selectedVersion} previewMut={previewMut} />
        )}
      </div>
    </div>
  );
}

// --- Sub-components ---

function VersionsTab({ versions }: { versions?: WorkflowVersion[] }) {
  if (!versions || versions.length === 0) return <div style={{ color: "var(--color-text-tertiary)", fontSize: "13px" }}>لا توجد إصدارات</div>;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
      {versions.map((v) => (
        <div
          key={v.id}
          style={{
            padding: "12px 14px",
            background: "var(--color-background-primary)",
            border: "0.5px solid var(--color-border-tertiary)",
            borderRadius: "var(--border-radius-md)",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
          }}
        >
          <div>
            <div style={{ fontSize: "14px", fontWeight: 500, color: "var(--color-text-primary)" }}>
              الإصدار {v.version}
            </div>
            <div style={{ fontSize: "11px", color: "var(--color-text-secondary)", marginTop: "2px" }}>
              {v.change_summary || "بدون وصف"} · {v.publisher?.name ?? "—"}
            </div>
          </div>
          <div style={{ fontSize: "12px", color: "var(--color-text-tertiary)" }}>
            {v.status === "active" ? "🟢 منشورة" : v.status === "draft" ? "🟡 مسودة" : "⚪ مؤرشفة"}
          </div>
        </div>
      ))}
    </div>
  );
}

function StepsTab({
  workflowId,
  version,
  isDraft,
  createStep,
  updateStep,
  deleteStep,
}: {
  workflowId: string;
  version?: WorkflowVersion;
  isDraft: boolean;
  createStep: ReturnType<typeof useCreateStep>;
  updateStep: ReturnType<typeof useUpdateStep>;
  deleteStep: ReturnType<typeof useDeleteStep>;
}) {
  const [newTitle, setNewTitle] = useState("");

  if (!version) return <LoadingSpinner />;

  const handleAdd = () => {
    if (!newTitle.trim()) return;
    createStep.mutate({
      workflowId,
      versionId: version.id,
      payload: { title_ar: newTitle, sort_order: (version.steps?.length ?? 0) },
    });
    setNewTitle("");
  };

  return (
    <div>
      {isDraft && (
        <div style={{ display: "flex", gap: "8px", marginBottom: "12px" }}>
          <input
            value={newTitle}
            onChange={(e) => setNewTitle(e.target.value)}
            placeholder="عنوان الخطوة الجديدة..."
            style={{
              flex: 1,
              padding: "6px 10px",
              fontSize: "13px",
              border: "0.5px solid var(--color-border-secondary)",
              borderRadius: "6px",
              background: "var(--color-background-primary)",
              color: "var(--color-text-primary)",
              fontFamily: "inherit",
            }}
            onKeyDown={(e) => e.key === "Enter" && handleAdd()}
          />
          <button
            onClick={handleAdd}
            disabled={createStep.isPending}
            style={{
              padding: "6px 14px",
              fontSize: "12px",
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "6px",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            إضافة
          </button>
        </div>
      )}

      <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
        {(version.steps ?? []).map((step: WorkflowStep, idx: number) => (
          <StepItem
            key={step.id}
            step={step}
            index={idx}
            workflowId={workflowId}
            versionId={version.id}
            isDraft={isDraft}
            updateStep={updateStep}
            deleteStep={deleteStep}
          />
        ))}
      </div>
    </div>
  );
}

function StepItem({
  step,
  index,
  workflowId,
  versionId,
  isDraft,
  updateStep,
  deleteStep,
}: {
  step: WorkflowStep;
  index: number;
  workflowId: string;
  versionId: string;
  isDraft: boolean;
  updateStep: ReturnType<typeof useUpdateStep>;
  deleteStep: ReturnType<typeof useDeleteStep>;
}) {
  const [editing, setEditing] = useState(false);
  const [title, setTitle] = useState(step.title_ar);

  const save = () => {
    updateStep.mutate({ workflowId, versionId, stepId: step.id, payload: { title_ar: title } });
    setEditing(false);
  };

  return (
    <div
      style={{
        padding: "10px 14px",
        background: "var(--color-background-primary)",
        border: "0.5px solid var(--color-border-tertiary)",
        borderRadius: "var(--border-radius-md)",
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
        <span
          style={{
            width: "24px",
            height: "24px",
            borderRadius: "50%",
            background: "var(--color-background-info)",
            color: "var(--color-text-info)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            fontSize: "12px",
            fontWeight: 500,
            flexShrink: 0,
          }}
        >
          {index + 1}
        </span>
        {editing ? (
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            onBlur={save}
            onKeyDown={(e) => e.key === "Enter" && save()}
            autoFocus
            style={{
              padding: "4px 8px",
              fontSize: "13px",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "4px",
              fontFamily: "inherit",
              background: "var(--color-background-primary)",
              color: "var(--color-text-primary)",
            }}
          />
        ) : (
          <span style={{ fontSize: "14px", color: "var(--color-text-primary)" }}>{step.title_ar}</span>
        )}
      </div>
      {isDraft && (
        <div style={{ display: "flex", gap: "6px" }}>
          <button
            onClick={() => setEditing(true)}
            style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-info)" }}
          >
            ✎
          </button>
          <button
            onClick={() => {
              if (confirm("حذف الخطوة؟")) deleteStep.mutate({ workflowId, versionId, stepId: step.id });
            }}
            style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)" }}
          >
            🗑️
          </button>
        </div>
      )}
    </div>
  );
}

function FieldsTab({
  workflowId,
  version,
  isDraft,
  registerFields,
  createField,
  updateField,
  deleteField,
  reorderFields,
}: {
  workflowId: string;
  version?: WorkflowVersion;
  isDraft: boolean;
  registerFields?: any[];
  createField: ReturnType<typeof useCreateWorkflowField>;
  updateField: ReturnType<typeof useUpdateWorkflowField>;
  deleteField: ReturnType<typeof useDeleteWorkflowField>;
  reorderFields: ReturnType<typeof useReorderWorkflowFields>;
}) {
  const [selectedFieldId, setSelectedFieldId] = useState("");
  const [selectedStepId, setSelectedStepId] = useState("");
  const [showCustomFieldModal, setShowCustomFieldModal] = useState(false);
  const [localFields, setLocalFields] = useState<WorkflowField[]>([]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  useEffect(() => {
    if (version?.fields) {
      setLocalFields([...version.fields].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
    }
  }, [version?.fields]);

  if (!version) return <LoadingSpinner />;

  const usedFieldIds = new Set(localFields.map((f: WorkflowField) => f.register_field_id).filter(Boolean));

  const handleAdd = () => {
    if (!selectedFieldId) return;
    createField.mutate({
      workflowId,
      versionId: version.id,
      payload: {
        register_field_id: selectedFieldId,
        step_id: selectedStepId || null,
        is_visible: true,
        is_required: false,
      },
    });
    setSelectedFieldId("");
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = localFields.findIndex((f) => f.id === active.id);
    const newIndex = localFields.findIndex((f) => f.id === over.id);
    if (oldIndex === -1 || newIndex === -1) return;
    const reordered = arrayMove(localFields, oldIndex, newIndex);
    const updated = reordered.map((f, idx) => ({ ...f, sort_order: idx + 1 }));
    setLocalFields(updated);
    reorderFields.mutate({
      workflowId,
      versionId: version.id,
      fields: updated.map((f) => ({ workflow_field_id: f.id, sort_order: f.sort_order ?? 1 })),
    });
  };

  const moveField = (fieldId: string, direction: "up" | "down" | "top" | "bottom") => {
    const idx = localFields.findIndex((f) => f.id === fieldId);
    if (idx === -1) return;
    let newIdx = idx;
    if (direction === "up" && idx > 0) newIdx = idx - 1;
    else if (direction === "down" && idx < localFields.length - 1) newIdx = idx + 1;
    else if (direction === "top") newIdx = 0;
    else if (direction === "bottom") newIdx = localFields.length - 1;
    if (newIdx === idx) return;
    const reordered = arrayMove(localFields, idx, newIdx);
    const updated = reordered.map((f, i) => ({ ...f, sort_order: i + 1 }));
    setLocalFields(updated);
    reorderFields.mutate({
      workflowId,
      versionId: version.id,
      fields: updated.map((f) => ({ workflow_field_id: f.id, sort_order: f.sort_order ?? 1 })),
    });
  };

  const groupedByStep = useMemo(() => {
    const groups: Map<string, WorkflowField[]> = new Map();
    localFields.forEach((f) => {
      const key = f.step_id ?? "no-step";
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key)!.push(f);
    });
    return groups;
  }, [localFields]);

  return (
    <div>
      {isDraft && (
        <div style={{ display: "flex", gap: "8px", marginBottom: "12px", flexWrap: "wrap", alignItems: "center" }}>
          <select value={selectedFieldId} onChange={(e) => setSelectedFieldId(e.target.value)} style={inputStyleSmall}>
            <option value="">اختر حقل من السجل</option>
            {registerFields
              ?.filter((f: any) => !usedFieldIds.has(f.id))
              .map((f: any) => (
                <option key={f.id} value={f.id}>{f.label_ar ?? f.name}</option>
              ))}
          </select>
          <select value={selectedStepId} onChange={(e) => setSelectedStepId(e.target.value)} style={{ ...inputStyleSmall, minWidth: "160px" }}>
            <option value="">بدون خطوة</option>
            {version.steps?.map((s: WorkflowStep) => (
              <option key={s.id} value={s.id}>{s.title_ar}</option>
            ))}
          </select>
          <button onClick={handleAdd} disabled={createField.isPending || !selectedFieldId} style={btnPrimarySmall}>
            إضافة حقل من السجل
          </button>
          <button
            onClick={() => setShowCustomFieldModal(true)}
            style={{
              padding: "6px 14px",
              fontSize: "12px",
              background: "var(--color-background-success)",
              color: "var(--color-text-success)",
              border: "0.5px solid var(--color-border-success)",
              borderRadius: "6px",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            + حقل مخصص
          </button>
        </div>
      )}

      {localFields.length === 0 && (
        <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
          لا توجد حقول بعد. أضف حقولاً من السجل أو أنشئ حقولاً مخصصة.
        </div>
      )}

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={localFields.map((f) => f.id)} strategy={verticalListSortingStrategy}>
          {localFields.map((field: WorkflowField, idx: number) => (
            <SortableFieldItem
              key={field.id}
              field={field}
              index={idx}
              version={version}
              workflowId={workflowId}
              isDraft={isDraft}
              updateField={updateField}
              deleteField={deleteField}
              onMove={moveField}
              isFirst={idx === 0}
              isLast={idx === localFields.length - 1}
            />
          ))}
        </SortableContext>
      </DndContext>

      {showCustomFieldModal && (
        <CustomFieldModal
          workflowId={workflowId}
          versionId={version.id}
          steps={version.steps ?? []}
          createField={createField}
          onClose={() => setShowCustomFieldModal(false)}
        />
      )}
    </div>
  );
}

function SortableFieldItem({
  field,
  index,
  version,
  workflowId,
  isDraft,
  updateField,
  deleteField,
  onMove,
  isFirst,
  isLast,
}: {
  field: WorkflowField;
  index: number;
  version: WorkflowVersion;
  workflowId: string;
  isDraft: boolean;
  updateField: ReturnType<typeof useUpdateWorkflowField>;
  deleteField: ReturnType<typeof useDeleteWorkflowField>;
  onMove: (fieldId: string, direction: "up" | "down" | "top" | "bottom") => void;
  isFirst: boolean;
  isLast: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: field.id });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
    zIndex: isDragging ? 10 : 1,
  };

  return (
    <div ref={setNodeRef} style={{ ...style, marginBottom: "6px" }}>
      <div
        style={{
          padding: "10px 14px",
          background: "var(--color-background-primary)",
          border: `0.5px solid ${field.register_field_id ? "var(--color-border-tertiary)" : "var(--color-border-success)"}`,
          borderRadius: "var(--border-radius-md)",
          display: "flex",
          alignItems: "center",
          gap: "8px",
          boxShadow: isDragging ? "0 4px 12px rgba(0,0,0,0.15)" : "none",
        }}
      >
        {/* Drag handle */}
        <div
          {...attributes}
          {...listeners}
          style={{
            cursor: "grab",
            fontSize: "16px",
            color: "var(--color-text-tertiary)",
            padding: "4px 2px",
            flexShrink: 0,
            userSelect: "none",
          }}
          title="اسحب لإعادة الترتيب"
        >
          ⠿
        </div>

        {/* Position number */}
        <span
          style={{
            width: "22px",
            height: "22px",
            borderRadius: "50%",
            background: "var(--color-background-info)",
            color: "var(--color-text-info)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            fontSize: "11px",
            fontWeight: 600,
            flexShrink: 0,
          }}
        >
          {index + 1}
        </span>

        {/* Field label */}
        <FieldLabelContent
          field={field}
          version={version}
          workflowId={workflowId}
          isDraft={isDraft}
          updateField={updateField}
          deleteField={deleteField}
        />

        {/* Move buttons */}
        {isDraft && (
          <div style={{ display: "flex", gap: "2px", flexShrink: 0 }}>
            <button
              onClick={() => onMove(field.id, "top")}
              disabled={isFirst}
              style={{ ...moveBtnStyle, opacity: isFirst ? 0.3 : 1 }}
              title="نقل للبداية"
            >
              ⤒
            </button>
            <button
              onClick={() => onMove(field.id, "up")}
              disabled={isFirst}
              style={{ ...moveBtnStyle, opacity: isFirst ? 0.3 : 1 }}
              title="نقل للأعلى"
            >
              ↑
            </button>
            <button
              onClick={() => onMove(field.id, "down")}
              disabled={isLast}
              style={{ ...moveBtnStyle, opacity: isLast ? 0.3 : 1 }}
              title="نقل للأسفل"
            >
              ↓
            </button>
            <button
              onClick={() => onMove(field.id, "bottom")}
              disabled={isLast}
              style={{ ...moveBtnStyle, opacity: isLast ? 0.3 : 1 }}
              title="نقل للنهاية"
            >
              ⤓
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

const moveBtnStyle: React.CSSProperties = {
  width: "24px",
  height: "24px",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  background: "none",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "4px",
  cursor: "pointer",
  fontSize: "12px",
  color: "var(--color-text-secondary)",
  padding: "0",
  fontFamily: "inherit",
};

function FieldLabelContent({
  field,
  version,
  workflowId,
  isDraft,
  updateField,
  deleteField,
}: {
  field: WorkflowField;
  version: WorkflowVersion;
  workflowId: string;
  isDraft: boolean;
  updateField: ReturnType<typeof useUpdateWorkflowField>;
  deleteField: ReturnType<typeof useDeleteWorkflowField>;
}) {
  const { data: officialFees } = useOfficialFees();
  const [editing, setEditing] = useState(false);
  const [label, setLabel] = useState(field.label_override ?? field.custom_label ?? field.label ?? "");
  const [localFieldType, setLocalFieldType] = useState(
    field.field_type && field.field_type !== ''
      ? field.field_type
      : (field.registerField?.field_type ?? 'text')
  );

  const saveLabel = () => {
    updateField.mutate(
      { workflowId, versionId: version.id, fieldId: field.id, payload: { label_override: label || null, custom_label: field.register_field_id ? null : (label || null) } },
      { onSuccess: () => setEditing(false) }
    );
  };

  const saveFieldType = () => {
    updateField.mutate(
      { workflowId, versionId: version.id, fieldId: field.id, payload: { field_type: localFieldType } },
      { onSuccess: () => {} }
    );
  };

  const handleFeeChange = (feeCode: string) => {
    updateField.mutate({
      workflowId,
      versionId: version.id,
      fieldId: field.id,
      payload: { fee_code: feeCode || null, is_financial: !!feeCode },
    });
  };

  const handleToggle = (key: "is_required" | "is_visible" | "is_financial") => {
    updateField.mutate({
      workflowId,
      versionId: version.id,
      fieldId: field.id,
      payload: { [key]: !field[key] },
    });
  };

  const currentFieldType = field.field_type && field.field_type !== ''
    ? field.field_type
    : (field.registerField?.field_type ?? 'text');

  return (
    <div style={{ flex: 1, minWidth: 0 }}>
      <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
        {field.register_field_id === null && (
          <span style={{ fontSize: "9px", padding: "1px 5px", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "4px", fontWeight: 500 }}>مخصص</span>
        )}
        {field.is_required && (
          <span style={{ color: "#dc2626", fontSize: "14px", fontWeight: 700, lineHeight: 1 }} title="حقل إلزامي">*</span>
        )}
        {editing ? (
          <input
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            onBlur={saveLabel}
            onKeyDown={(e) => e.key === "Enter" && saveLabel()}
            autoFocus
            style={{
              flex: 1,
              padding: "4px 8px",
              fontSize: "13px",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "4px",
              fontFamily: "inherit",
              background: "var(--color-background-primary)",
              color: "var(--color-text-primary)",
            }}
          />
        ) : (
          <div style={{ fontSize: "13px", color: "var(--color-text-primary)", fontWeight: field.is_required ? 500 : 400 }}>
            {field.label}
          </div>
        )}
        {isDraft && !editing && (
          <button onClick={() => setEditing(true)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-info)", padding: "0" }} title="تعديل الاسم">✎</button>
        )}
        {isDraft && (
          <button
            onClick={() => { if (confirm("حذف الحقل؟")) deleteField.mutate({ workflowId, versionId: version.id, fieldId: field.id }); }}
            style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)", padding: "0" }}
            title="حذف"
          >🗑️</button>
        )}
      </div>
      <div style={{ fontSize: "11px", color: "var(--color-text-secondary)", marginTop: "4px", display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
        {isDraft ? (
          <select
            value={localFieldType}
            onChange={(e) => setLocalFieldType(e.target.value)}
            onBlur={saveFieldType}
            style={{ ...inputStyleSmall, fontSize: "11px", padding: "2px 6px", minWidth: "120px" }}
          >
            {FIELD_TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        ) : (
          <span>{currentFieldType}</span>
        )}
        <span>·</span>
        <span>{field.step_id ? version.steps?.find((s: WorkflowStep) => s.id === field.step_id)?.title_ar ?? "—" : "بدون خطوة"}</span>
        {isDraft && (
          <>
            <span>·</span>
            <select
              value={field.fee_code ?? ""}
              onChange={(e) => handleFeeChange(e.target.value)}
              style={{ ...inputStyleSmall, fontSize: "11px", padding: "2px 6px", minWidth: "120px" }}
            >
              <option value="">بدون رسم</option>
              {officialFees?.map((fee: any) => (
                <option key={fee.id} value={fee.fee_code}>
                  {fee.name_ar} ({fee.fee_code}) — {fee.amount?.toLocaleString("en")} د.ع
                </option>
              ))}
            </select>
          </>
        )}
        {!isDraft && field.fee_code && <><span>·</span><span style={{ color: "var(--color-text-success)" }}>رسوم: {field.fee_code}</span></>}
        {isDraft && (
          <>
            <button onClick={() => handleToggle("is_required")} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "11px", color: field.is_required ? "var(--color-text-danger)" : "var(--color-text-tertiary)", padding: "0" }} title="إلزامي">
              {field.is_required ? "* إلزامي" : "o اختياري"}
            </button>
            <button onClick={() => handleToggle("is_visible")} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "11px", color: field.is_visible ? "var(--color-text-success)" : "var(--color-text-tertiary)", padding: "0" }} title="مرئي">
              {field.is_visible ? "👁 مرئي" : "🚫 مخفي"}
            </button>
            <button onClick={() => handleToggle("is_financial")} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "11px", color: field.is_financial ? "var(--color-text-success)" : "var(--color-text-tertiary)", padding: "0" }} title="مالي">
              {field.is_financial ? "💰 مالي" : "— عادي"}
            </button>
          </>
        )}
        {!isDraft && field.is_financial && <span>· 💰 مالي</span>}
        {!isDraft && field.is_required && <span>· *</span>}
      </div>
    </div>
  );
}

const FIELD_TYPES = [
  { value: "text", label: "نص" },
  { value: "textarea", label: "نص طويل" },
  { value: "number", label: "رقم" },
  { value: "decimal", label: "رقم عشري" },
  { value: "select", label: "قائمة منسدلة" },
  { value: "multi_select", label: "اختيار متعدد" },
  { value: "checkbox", label: "مربع اختيار" },
  { value: "radio", label: "زر اختيار" },
  { value: "date", label: "تاريخ" },
  { value: "datetime", label: "تاريخ ووقت" },
  { value: "email", label: "بريد إلكتروني" },
  { value: "phone", label: "هاتف" },
  { value: "url", label: "رابط" },
];

function CustomFieldModal({
  workflowId,
  versionId,
  steps,
  createField,
  onClose,
}: {
  workflowId: string;
  versionId: string;
  steps: WorkflowStep[];
  createField: ReturnType<typeof useCreateWorkflowField>;
  onClose: () => void;
}) {
  const [customName, setCustomName] = useState("");
  const [customLabel, setCustomLabel] = useState("");
  const [fieldType, setFieldType] = useState("text");
  const [defaultValue, setDefaultValue] = useState("");
  const [isRequired, setIsRequired] = useState(false);
  const [isVisible, setIsVisible] = useState(true);
  const [isEditable, setIsEditable] = useState(true);
  const [isLocked, setIsLocked] = useState(false);
  const [isFinancial, setIsFinancial] = useState(false);
  const [isInsured, setIsInsured] = useState(false);
  const [insuranceValue, setInsuranceValue] = useState("");
  const [stepId, setStepId] = useState("");
  const [options, setOptions] = useState<Array<{ label: string; value: string }>>([]);
  const [newOptionLabel, setNewOptionLabel] = useState("");
  const [newOptionValue, setNewOptionValue] = useState("");
  const [validationRules, setValidationRules] = useState<string[]>([]);
  const [newRule, setNewRule] = useState("");

  const handleSubmit = () => {
    if (!customName.trim() || !customLabel.trim()) {
      alert("يجب إدخال اسم الحقل وعنوان الحقل");
      return;
    }
    createField.mutate(
      {
        workflowId,
        versionId,
        payload: {
          custom_name: customName.trim(),
          custom_label: customLabel.trim(),
          field_type: fieldType,
          default_value: defaultValue || null,
          is_required: isRequired,
          is_visible: isVisible,
          is_editable: isEditable,
          is_locked: isLocked,
          is_financial: isFinancial,
          is_insured: isInsured,
          insurance_value: insuranceValue ? insuranceValue : null,
          step_id: stepId || null,
          options: ["select", "multi_select", "radio"].includes(fieldType) ? options : null,
          validation_rules: validationRules.length > 0 ? validationRules : null,
        },
      },
      { onSuccess: onClose }
    );
  };

  const addOption = () => {
    if (!newOptionLabel.trim() || !newOptionValue.trim()) return;
    setOptions([...options, { label: newOptionLabel.trim(), value: newOptionValue.trim() }]);
    setNewOptionLabel("");
    setNewOptionValue("");
  };

  const removeOption = (index: number) => {
    setOptions(options.filter((_, i) => i !== index));
  };

  const moveOption = (index: number, direction: "up" | "down") => {
    const newOptions = [...options];
    const targetIndex = direction === "up" ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= newOptions.length) return;
    [newOptions[index], newOptions[targetIndex]] = [newOptions[targetIndex], newOptions[index]];
    setOptions(newOptions);
  };

  const addValidationRule = () => {
    if (!newRule.trim()) return;
    setValidationRules([...validationRules, newRule.trim()]);
    setNewRule("");
  };

  const removeValidationRule = (index: number) => {
    setValidationRules(validationRules.filter((_, i) => i !== index));
  };

  const showOptions = ["select", "multi_select", "radio"].includes(fieldType);

  return (
    <div style={{
      position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 1000,
    }}>
      <div style={{
        background: "var(--color-background-primary)", borderRadius: "var(--border-radius-lg)", padding: "24px", width: "90%", maxWidth: "600px", maxHeight: "80vh", overflowY: "auto",
      }}>
        <div style={{ fontSize: "16px", fontWeight: 600, marginBottom: "16px" }}>حقل مخصص جديد</div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px", marginBottom: "16px" }}>
          <div>
            <label style={labelStyle}>اسم الحقل (Name)</label>
            <input value={customName} onChange={(e) => setCustomName(e.target.value)} placeholder="payment_method" style={inputStyleSmall} />
          </div>
          <div>
            <label style={labelStyle}>عنوان الحقل (Label)</label>
            <input value={customLabel} onChange={(e) => setCustomLabel(e.target.value)} placeholder="طريقة الدفع" style={inputStyleSmall} />
          </div>
        </div>

        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>نوع الحقل</label>
          <select value={fieldType} onChange={(e) => setFieldType(e.target.value)} style={{ ...inputStyleSmall, width: "100%" }}>
            {FIELD_TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </div>

        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>الخطوة (اختياري)</label>
          <select value={stepId} onChange={(e) => setStepId(e.target.value)} style={{ ...inputStyleSmall, width: "100%" }}>
            <option value="">بدون خطوة</option>
            {steps.map((s) => (
              <option key={s.id} value={s.id}>{s.title_ar}</option>
            ))}
          </select>
        </div>

        <div style={{ marginBottom: "16px" }}>
          <label style={labelStyle}>القيمة الافتراضية</label>
          <input value={defaultValue} onChange={(e) => setDefaultValue(e.target.value)} style={inputStyleSmall} />
        </div>

        {showOptions && (
          <div style={{ marginBottom: "16px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "8px" }}>
            <div style={{ fontSize: "13px", fontWeight: 500, marginBottom: "8px" }}>خيارات القائمة</div>
            {options.map((opt, idx) => (
              <div key={idx} style={{ display: "flex", gap: "4px", marginBottom: "4px", alignItems: "center" }}>
                <span style={{ fontSize: "11px", color: "var(--color-text-tertiary)", minWidth: "20px" }}>{idx + 1}.</span>
                <span style={{ flex: 1, fontSize: "12px" }}>{opt.label}</span>
                <span style={{ fontSize: "11px", color: "var(--color-text-secondary)" }}>({opt.value})</span>
                <button onClick={() => moveOption(idx, "up")} disabled={idx === 0} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "10px", color: "var(--color-text-info)" }}>↑</button>
                <button onClick={() => moveOption(idx, "down")} disabled={idx === options.length - 1} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "10px", color: "var(--color-text-info)" }}>↓</button>
                <button onClick={() => removeOption(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)" }}>×</button>
              </div>
            ))}
            <div style={{ display: "flex", gap: "4px", marginTop: "8px" }}>
              <input value={newOptionLabel} onChange={(e) => setNewOptionLabel(e.target.value)} placeholder="التسمية" style={{ ...inputStyleSmall, flex: 1 }} />
              <input value={newOptionValue} onChange={(e) => setNewOptionValue(e.target.value)} placeholder="القيمة" style={{ ...inputStyleSmall, flex: 1 }} />
              <button onClick={addOption} style={btnPrimarySmall}>إضافة</button>
            </div>
          </div>
        )}

        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px", marginBottom: "16px" }}>
          <Toggle label="مطلوب" checked={isRequired} onChange={setIsRequired} highlight={isRequired} highlightColor="#dc2626" />
          <Toggle label="مرئي" checked={isVisible} onChange={setIsVisible} />
          <Toggle label="قابل للتعديل" checked={isEditable} onChange={setIsEditable} />
          <Toggle label="مقفل" checked={isLocked} onChange={setIsLocked} />
          <Toggle label="مالي" checked={isFinancial} onChange={setIsFinancial} />
          <Toggle label="مؤمن" checked={isInsured} onChange={setIsInsured} />
        </div>

        {isInsured && (
          <div style={{ marginBottom: "16px" }}>
            <label style={labelStyle}>قيمة التأمين</label>
            <input value={insuranceValue} onChange={(e) => setInsuranceValue(e.target.value)} type="number" step="0.001" style={inputStyleSmall} />
          </div>
        )}

        <div style={{ marginBottom: "16px", padding: "12px", background: "var(--color-background-secondary)", borderRadius: "8px" }}>
          <div style={{ fontSize: "13px", fontWeight: 500, marginBottom: "8px" }}>قواعد التحقق</div>
          {validationRules.map((rule, idx) => (
            <div key={idx} style={{ display: "flex", gap: "4px", marginBottom: "4px", alignItems: "center" }}>
              <span style={{ flex: 1, fontSize: "12px", fontFamily: "monospace" }}>{rule}</span>
              <button onClick={() => removeValidationRule(idx)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)" }}>×</button>
            </div>
          ))}
          <div style={{ display: "flex", gap: "4px" }}>
            <input value={newRule} onChange={(e) => setNewRule(e.target.value)} placeholder="required|min:3" style={{ ...inputStyleSmall, flex: 1 }} />
            <button onClick={addValidationRule} style={btnPrimarySmall}>إضافة</button>
          </div>
        </div>

        <div style={{ display: "flex", gap: "8px", justifyContent: "flex-end" }}>
          <button onClick={onClose} style={btnSecondarySmall}>إلغاء</button>
          <button onClick={handleSubmit} disabled={createField.isPending} style={btnPrimarySmall}>إنشاء</button>
        </div>
      </div>
    </div>
  );
}

function Toggle({ label, checked, onChange, highlight, highlightColor }: { label: string; checked: boolean; onChange: (v: boolean) => void; highlight?: boolean; highlightColor?: string }) {
  return (
    <label style={{
      display: "flex",
      alignItems: "center",
      gap: "6px",
      fontSize: "12px",
      fontWeight: highlight && checked ? 600 : 400,
      color: highlight && checked ? (highlightColor ?? "var(--color-text-danger)") : "var(--color-text-primary)",
      cursor: "pointer",
      padding: "4px 8px",
      borderRadius: "6px",
      background: highlight && checked ? "rgba(220, 38, 38, 0.06)" : "transparent",
      border: highlight && checked ? `1px solid ${highlightColor ?? "#dc2626"}33` : "1px solid transparent",
    }}>
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
      {label}
      {highlight && checked && (
        <span style={{ color: highlightColor ?? "#dc2626", fontSize: "14px", fontWeight: 700, lineHeight: 1, marginLeft: "2px" }}>*</span>
      )}
    </label>
  );
}

function UnknownRuleTypeError({ rule, onCancel }: { rule: any; onCancel: () => void }) {
  return (
    <div style={{ background: "var(--color-background-danger)", border: "1px solid var(--color-border-danger)", borderRadius: "var(--border-radius-lg)", padding: "18px" }}>
      <div style={{ fontSize: "15px", fontWeight: 600, color: "var(--color-text-danger)", marginBottom: "8px" }}>
        ⚠️ نوع قاعدة غير معروف
      </div>
      <div style={{ fontSize: "13px", color: "var(--color-text-danger)", marginBottom: "12px" }}>
        تعذّر تحديد محرّر مناسب لهذه القاعدة من النوع المخزَّن. لم يتم فتح أي محرّر لتفادي إفساد البنية.
      </div>
      <div style={{ fontSize: "12px", fontFamily: "monospace", color: "var(--color-text-secondary)", background: "var(--color-background-primary)", padding: "8px", borderRadius: "6px", marginBottom: "12px" }}>
        id: {rule?.id ?? "—"} · source: {rule?.source ?? "—"} · type: {String(rule?.type ?? "—")}
      </div>
      <button onClick={onCancel} style={{ padding: "6px 14px", fontSize: "13px", cursor: "pointer", fontFamily: "inherit", border: "0.5px solid var(--color-border-secondary)", borderRadius: "6px", background: "var(--color-background-primary)", color: "var(--color-text-primary)" }}>
        إغلاق
      </button>
    </div>
  );
}

function UnifiedRulesTab({
  workflowId,
  version,
  isDraft,
  fields,
  registers,
  createRule,
  updateRule,
  deleteRule,
}: {
  workflowId: string;
  version?: WorkflowVersion;
  isDraft: boolean;
  fields: WorkflowField[];
  registers?: any[];
  createRule: ReturnType<typeof useCreateWorkflowRule>;
  updateRule: ReturnType<typeof useUpdateWorkflowRule>;
  deleteRule: ReturnType<typeof useDeleteWorkflowRule>;
}) {
  const [editingRule, setEditingRule] = useState<any | null>(null);
  const [showNewRule, setShowNewRule] = useState(false);
  const [newRuleType, setNewRuleType] = useState<"enterprise" | "case_based" | "simple" | "validation" | "routing" | null>(null);
  const [filterType, setFilterType] = useState<"all" | "enterprise" | "case_based" | "simple" | "validation" | "routing">("all");
  const deleteValidationRule = useDeleteEnterpriseRule();

  if (!version) return <LoadingSpinner />;

  const workflowRules = version.rules ?? [];
  const validationRules = version.validation_rules ?? [];

  const allRules = useMemo(() => {
    const merged: Array<{
      id: string;
      name: string;
      type: RuleEditorKind;
      source: "workflow_rules" | "validation_rules";
      is_active: boolean;
      priority?: number;
      sort_order?: number;
      data: any;
    }> = [];

    workflowRules.forEach((r: WorkflowRule) => {
      merged.push({
        id: r.id,
        name: r.name || "قاعدة بدون اسم",
        type: classifyRule(r, "workflow_rules"),
        source: "workflow_rules",
        is_active: r.is_active,
        priority: (r as any).priority,
        sort_order: r.sort_order,
        data: r,
      });
    });

    validationRules.forEach((r: any) => {
      merged.push({
        id: r.id,
        name: r.name || "قاعدة بدون اسم",
        type: classifyRule(r, "validation_rules"),
        source: "validation_rules",
        is_active: r.is_active,
        priority: r.priority ?? 5000,
        sort_order: r.sort_order,
        data: r,
      });
    });

    merged.sort((a, b) => {
      const pA = a.priority ?? (a.sort_order ?? 0) * 100;
      const pB = b.priority ?? (b.sort_order ?? 0) * 100;
      return pB - pA;
    });

    return merged;
  }, [workflowRules, validationRules]);

  const filteredRules = filterType === "all" ? allRules : allRules.filter((r) => r.type === filterType);

  const handleCancel = () => {
    setEditingRule(null);
    setShowNewRule(false);
    setNewRuleType(null);
  };

  const typeConfig = {
    enterprise: { label: "متقدمة", color: "info", icon: "⚡", bg: "var(--color-background-info)", text: "var(--color-text-info)", border: "var(--color-border-info)" },
    case_based: { label: "Switch/Case", color: "info", icon: "🔀", bg: "var(--color-background-info)", text: "var(--color-text-info)", border: "var(--color-border-info)" },
    simple: { label: "بسيطة", color: "secondary", icon: "📋", bg: "var(--color-background-secondary)", text: "var(--color-text-secondary)", border: "var(--color-border-secondary)" },
    validation: { label: "تحقق", color: "warning", icon: "✓", bg: "var(--color-background-warning)", text: "var(--color-text-warning)", border: "var(--color-border-warning)" },
    routing: { label: "توجيه", color: "warning", icon: "🔄", bg: "var(--color-background-warning)", text: "var(--color-text-warning)", border: "var(--color-border-warning)" },
    unknown: { label: "غير معروف", color: "danger", icon: "⚠️", bg: "var(--color-background-danger)", text: "var(--color-text-danger)", border: "var(--color-border-danger)" },
  } as const;

  const renderRuleSummary = (rule: typeof allRules[number]) => {
    switch (rule.type) {
      case "enterprise":
        const rc = rule.data.rule_config ?? rule.data.data?.rule_config ?? {};
        const condCount = rc.conditions?.length ?? 0;
        const actCount = rc.actions?.length ?? 0;
        const caseCount = rc.cases?.length ?? 0;
        return caseCount > 0
          ? `حالات: ${caseCount} · إجراءات: ${actCount}`
          : `شروط: ${condCount} · إجراءات: ${actCount}`;
      case "case_based":
        return `الحقل: ${rule.data.trigger_field_id} · الحالات: ${(rule.data.cases ?? []).length} · إجراءات: ${(rule.data.default_actions ?? []).length}`;
      case "simple":
        return `IF ${JSON.stringify(rule.data.condition_logic).substring(0, 60)}... · ${(rule.data.actions ?? []).length} إجراء`;
      case "validation":
        return `النوع: ${rule.data.validation_type} · الاستجابة: ${rule.data.response_type}${rule.data.error_message_ar ? ` · ${rule.data.error_message_ar}` : ""}`;
      case "routing":
        return `توجيه · السجل: ${rule.data.target_register_id ?? "—"} · ${rule.data.route_config?.on_match?.action ?? "warn"}`;
      default:
        return "";
    }
  };

  const renderBuilder = () => {
    const rule = editingRule?.data ?? null;
    const commonProps = {
      workflowId,
      versionId: version.id,
      rule,
      fields,
      registers,
      onSave: handleCancel,
      onCancel: handleCancel,
    };

    if (showNewRule && newRuleType) {
      switch (newRuleType) {
        case "enterprise":
          return <EnterpriseRuleBuilder {...commonProps} />;
        case "case_based":
          return <CaseRuleBuilder {...commonProps} />;
        case "validation":
          return <ValidationRuleBuilder {...commonProps} />;
        case "routing":
          return <RoutingRuleBuilder {...commonProps} />;
        case "simple":
          return <SimpleRuleBuilder {...commonProps} />;
      }
    }

    if (editingRule) {
      // The editor is chosen ONLY from the persisted, classified type — never guessed.
      switch (editingRule.type as RuleEditorKind) {
        case "enterprise":
          return <EnterpriseRuleBuilder {...commonProps} />;
        case "case_based":
          return <CaseRuleBuilder {...commonProps} />;
        case "validation":
          return <ValidationRuleBuilder {...commonProps} />;
        case "routing":
          return <RoutingRuleBuilder {...commonProps} />;
        case "simple":
          return <SimpleRuleBuilder {...commonProps} />;
        default:
          return <UnknownRuleTypeError rule={editingRule} onCancel={handleCancel} />;
      }
    }

    return null;
  };

  return (
    <div>
      {/* Add Rule Button */}
      {isDraft && !editingRule && !showNewRule && (
        <div style={{ display: "flex", gap: "8px", marginBottom: "12px", alignItems: "center", flexWrap: "wrap" }}>
          <div style={{ position: "relative", display: "inline-block" }}>
            <button
              onClick={() => setShowNewRule(true)}
              style={{
                padding: "8px 16px",
                fontSize: "13px",
                background: "var(--color-background-info)",
                color: "var(--color-text-info)",
                border: "0.5px solid var(--color-border-info)",
                borderRadius: "6px",
                cursor: "pointer",
                fontFamily: "inherit",
                fontWeight: 500,
              }}
            >
              + قاعدة جديدة
            </button>
          </div>
        </div>
      )}

      {/* Rule Type Selector */}
      {showNewRule && !newRuleType && isDraft && (
        <div style={{ marginBottom: "16px", padding: "16px", background: "var(--color-background-secondary)", borderRadius: "var(--border-radius-lg)", border: "0.5px solid var(--color-border-tertiary)" }}>
          <div style={{ fontSize: "14px", fontWeight: 600, marginBottom: "12px", color: "var(--color-text-primary)" }}>اختر نوع القاعدة</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: "10px" }}>
            {([
              { type: "enterprise" as const, icon: "⚡", title: "قاعدة متقدمة", desc: "شروط متداخلة، 35+ إجراء، محاكاة، توجيه" },
              { type: "case_based" as const, icon: "🔀", title: "Switch/Case", desc: "قائمة حالات مع إجراءات لكل حالة" },
              { type: "validation" as const, icon: "✓", title: "قاعدة تحقق", desc: "فحص تكرار، بحث في السجلات، استعلامات" },
              { type: "routing" as const, icon: "🔄", title: "قاعدة توجيه", desc: "بحث في سجل وتوجيه سير العمل" },
              { type: "simple" as const, icon: "📋", title: "قاعدة بسيطة", desc: "شرط واحد مع إجراءات" },
            ]).map((opt) => (
              <button
                key={opt.type}
                onClick={() => setNewRuleType(opt.type)}
                style={{
                  padding: "14px",
                  background: "var(--color-background-primary)",
                  border: "0.5px solid var(--color-border-tertiary)",
                  borderRadius: "var(--border-radius-md)",
                  cursor: "pointer",
                  fontFamily: "inherit",
                  textAlign: "right",
                  transition: "border-color 0.15s",
                }}
                onMouseEnter={(e) => (e.currentTarget.style.borderColor = "var(--color-border-info)")}
                onMouseLeave={(e) => (e.currentTarget.style.borderColor = "var(--color-border-tertiary)")}
              >
                <div style={{ fontSize: "24px", marginBottom: "6px" }}>{opt.icon}</div>
                <div style={{ fontSize: "13px", fontWeight: 600, color: "var(--color-text-primary)", marginBottom: "4px" }}>{opt.title}</div>
                <div style={{ fontSize: "11px", color: "var(--color-text-secondary)" }}>{opt.desc}</div>
              </button>
            ))}
          </div>
          <button onClick={handleCancel} style={{ ...btnSecondarySmall, marginTop: "12px" }}>إلغاء</button>
        </div>
      )}

      {/* Rule Builder */}
      {(showNewRule || editingRule) && isDraft && renderBuilder() && (
        <div style={{ marginBottom: "16px" }}>
          {renderBuilder()}
        </div>
      )}

      {/* Filter Tabs */}
      {allRules.length > 0 && (
        <div style={{ display: "flex", gap: "4px", marginBottom: "12px", overflowX: "auto", paddingBottom: "4px" }}>
          {([
            { key: "all" as const, label: "الكل", count: allRules.length },
            { key: "enterprise" as const, label: "متقدمة", count: allRules.filter((r) => r.type === "enterprise").length },
            { key: "case_based" as const, label: "Switch/Case", count: allRules.filter((r) => r.type === "case_based").length },
            { key: "validation" as const, label: "تحقق", count: allRules.filter((r) => r.type === "validation").length },
            { key: "routing" as const, label: "توجيه", count: allRules.filter((r) => r.type === "routing").length },
            { key: "simple" as const, label: "بسيطة", count: allRules.filter((r) => r.type === "simple").length },
          ]).map((f) => (
            <button
              key={f.key}
              onClick={() => setFilterType(f.key)}
              style={{
                padding: "5px 10px",
                fontSize: "12px",
                borderRadius: "6px",
                border: `0.5px solid ${filterType === f.key ? "var(--color-border-info)" : "var(--color-border-secondary)"}`,
                background: filterType === f.key ? "var(--color-background-info)" : "transparent",
                color: filterType === f.key ? "var(--color-text-info)" : "var(--color-text-secondary)",
                cursor: "pointer",
                fontFamily: "inherit",
                whiteSpace: "nowrap",
              }}
            >
              {f.label} ({f.count})
            </button>
          ))}
        </div>
      )}

      {/* Rules List */}
      <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
        {filteredRules.map((rule) => {
          const cfg = typeConfig[rule.type];
          return (
            <div
              key={rule.id}
              style={{
                padding: "10px 14px",
                background: "var(--color-background-primary)",
                border: `0.5px solid ${rule.is_active ? cfg.border : "var(--color-border-tertiary)"}`,
                borderRadius: "var(--border-radius-md)",
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
              }}
            >
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: "13px", color: "var(--color-text-primary)", display: "flex", alignItems: "center", gap: "8px" }}>
                  <span style={{ fontSize: "14px" }}>{cfg.icon}</span>
                  {rule.name}
                  <span style={{ fontSize: "10px", padding: "2px 6px", background: cfg.bg, color: cfg.text, borderRadius: "4px", fontWeight: 500 }}>
                    {cfg.label}
                  </span>
                  {rule.priority !== undefined && (
                    <span style={{ fontSize: "10px", padding: "2px 6px", background: "var(--color-background-secondary)", color: "var(--color-text-secondary)", borderRadius: "4px" }}>
                      أولوية: {rule.priority}
                    </span>
                  )}
                  {!rule.is_active && (
                    <span style={{ fontSize: "10px", padding: "2px 6px", background: "var(--color-background-warning)", color: "var(--color-text-warning)", borderRadius: "4px" }}>معطّلة</span>
                  )}
                </div>
                <div style={{ fontSize: "11px", color: "var(--color-text-secondary)", marginTop: "2px" }}>
                  {renderRuleSummary(rule)}
                </div>
              </div>
              <div style={{ display: "flex", gap: "6px" }}>
                {isDraft && (
                  <>
                    <button
                      onClick={() => setEditingRule(rule)}
                      style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-info)" }}
                      title="تعديل"
                    >
                      ✎
                    </button>
                    <button
                      onClick={() => {
                        if (confirm("حذف القاعدة؟")) {
                          if (rule.source === "validation_rules") {
                            deleteValidationRule.mutate({ workflowId, versionId: version.id, ruleId: rule.id });
                          } else {
                            deleteRule.mutate({ workflowId, versionId: version.id, ruleId: rule.id });
                          }
                        }
                      }}
                      style={{ background: "none", border: "none", cursor: "pointer", fontSize: "12px", color: "var(--color-text-danger)" }}
                      title="حذف"
                    >
                      🗑️
                    </button>
                  </>
                )}
              </div>
            </div>
          );
        })}
        {filteredRules.length === 0 && (
          <div style={{ textAlign: "center", padding: "32px", color: "var(--color-text-tertiary)", fontSize: "13px" }}>
            {filterType === "all" ? "لا توجد قواعد بعد. أضف قاعدة جديدة للبدء." : `لا توجد قواعد من نوع "${typeConfig[filterType]?.label}"`}
          </div>
        )}
      </div>
    </div>
  );
}

function PreviewTab({
  version,
  previewMut,
}: {
  version?: WorkflowVersion;
  previewMut: ReturnType<typeof usePreviewExecution>;
}) {
  const [values, setValues] = useState<Record<string, string>>({});
  const [result, setResult] = useState<any>(null);

  if (!version) return <LoadingSpinner />;

  const fields = [...(version.fields ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

  const handleChange = (fieldId: string, value: string) => {
    setValues((prev) => ({ ...prev, [fieldId]: value }));
  };

  const handlePreview = () => {
    previewMut.mutate(
      { workflow_version_id: version.id, values },
      { onSuccess: (data) => setResult(data) }
    );
  };

  const resolveFieldId = (field: WorkflowField): string => {
    return field.register_field_id ?? `custom_${field.id}`;
  };

  const renderInput = (field: WorkflowField) => {
    const fieldId = resolveFieldId(field);
    const val = values[fieldId] ?? field.default_value ?? "";
    const label = field.label;
    const fieldType = field.field_type ?? field.registerField?.field_type ?? "text";
    const options = field.options ?? field.registerField?.options ?? [];

    switch (fieldType) {
      case "select":
      case "radio":
        return (
          <GovSelect
            options={options.map((opt: string | { value: string; label?: string; label_ar?: string }) => ({
              value: typeof opt === "string" ? opt : opt.value,
              label: typeof opt === "string" ? opt : (opt.label_ar ?? opt.label ?? opt.value),
            }))}
            value={val}
            onChange={(v) => handleChange(fieldId, v)}
            placeholder="اختر..."
          />
        );
      case "multi_select":
        return (
          <GovSelectMulti
            options={options.map((opt: string | { value: string; label?: string; label_ar?: string }) => ({
              value: typeof opt === "string" ? opt : opt.value,
              label: typeof opt === "string" ? opt : (opt.label_ar ?? opt.label ?? opt.value),
            }))}
            value={Array.isArray(val) ? val : val ? JSON.parse(val) : []}
            onChange={(vals) => handleChange(fieldId, JSON.stringify(vals))}
            placeholder="اختر..."
          />
        );
      case "checkbox":
        return (
          <label style={{ display: "flex", alignItems: "center", gap: "8px", fontSize: "13px" }}>
            <input
              type="checkbox"
              checked={toBoolean(val)}
              onChange={(e) => handleChange(fieldId, e.target.checked ? "1" : "0")}
            />
            {label}
          </label>
        );
      case "number":
      case "decimal":
        return (
          <input
            type="number"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
            placeholder={field.placeholder ?? ""}
            step={fieldType === "decimal" ? "0.001" : "1"}
          />
        );
      case "date":
        return (
          <input
            type="date"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
          />
        );
      case "datetime":
        return (
          <input
            type="datetime-local"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
          />
        );
      case "email":
        return (
          <input
            type="email"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "phone":
        return (
          <input
            type="tel"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "url":
        return (
          <input
            type="url"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
            placeholder={field.placeholder ?? ""}
          />
        );
      case "textarea":
        return (
          <textarea
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={{ ...previewInputStyle, minHeight: "60px", resize: "vertical" }}
            placeholder={field.placeholder ?? ""}
          />
        );
      default:
        return (
          <input
            type="text"
            value={val}
            onChange={(e) => handleChange(fieldId, e.target.value)}
            style={previewInputStyle}
            placeholder={field.placeholder ?? ""}
          />
        );
    }
  };

  return (
    <div>
      <div
        style={{
          background: "var(--color-background-primary)",
          border: "0.5px solid var(--color-border-tertiary)",
          borderRadius: "var(--border-radius-lg)",
          padding: "16px",
          marginBottom: "16px",
        }}
      >
        <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>
          قيم الاختبار — {version.steps?.length ? `الخطوات: ${version.steps.length}` : "بدون خطوات"}
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: "12px" }}>
          {fields.map((field: WorkflowField) => (
            <div key={field.id}>
              <label style={{ fontSize: "13px", fontWeight: 500, color: "var(--color-text-primary)", display: "flex", alignItems: "center", gap: "4px", marginBottom: "6px", flexWrap: "wrap" }}>
                {field.label}
                {field.is_required && (
                  <span
                    style={{
                      color: "#dc2626",
                      fontSize: "14px",
                      fontWeight: 700,
                      lineHeight: 1,
                    }}
                    title="حقل إلزامي"
                  >
                    *
                  </span>
                )}
                {field.is_financial && (
                  <span style={{ fontSize: "10px", padding: "1px 6px", background: "var(--color-background-success)", color: "var(--color-text-success)", borderRadius: "4px", fontWeight: 500 }}>مالي</span>
                )}
                {field.register_field_id === null && (
                  <span style={{ fontSize: "9px", padding: "1px 5px", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "4px", fontWeight: 500 }}>مخصص</span>
                )}
              </label>
              {renderInput(field)}
            </div>
          ))}
        </div>
        <div style={{ marginTop: "14px" }}>
          <button
            onClick={handlePreview}
            disabled={previewMut.isPending}
            style={{
              padding: "8px 20px",
              fontSize: "13px",
              fontWeight: 500,
              background: "var(--color-background-info)",
              color: "var(--color-text-info)",
              border: "0.5px solid var(--color-border-info)",
              borderRadius: "var(--border-radius-md)",
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            {previewMut.isPending ? "جارٍ المعاينة..." : "🔍 معاينة النتيجة"}
          </button>
        </div>
      </div>

      {result && (
        <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
          {/* Calculated items */}
          <div
            style={{
              background: "var(--color-background-primary)",
              border: "0.5px solid var(--color-border-tertiary)",
              borderRadius: "var(--border-radius-lg)",
              padding: "16px",
            }}
          >
            <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>📋 نتيجة الحساب</div>
            {result.items?.length ? (
              <>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
                  <tbody>
                    {result.items.map((item: any, i: number) => (
                      <tr key={i} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                        <td style={{ padding: "8px 0", color: "var(--color-text-secondary)" }}>
                          {item.label}
                          {item.action && (
                            <span style={{ fontSize: "10px", marginRight: "6px", padding: "1px 5px", background: "var(--color-background-info)", color: "var(--color-text-info)", borderRadius: "3px" }}>
                              {item.action}
                            </span>
                          )}
                        </td>
                        <td style={{ padding: "8px 0", textAlign: "left", fontWeight: 500, direction: "ltr" }}>
                          {item.amount > 0 ? `${item.amount.toLocaleString("en")} د.ع` : item.text_value ?? "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <div
                  style={{
                    marginTop: "12px",
                    paddingTop: "12px",
                    borderTop: "1px solid var(--color-border-secondary)",
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: "16px",
                    fontWeight: 500,
                  }}
                >
                  <span>الإجمالي</span>
                  <span style={{ direction: "ltr" }}>{result.total_amount?.toLocaleString("en")} د.ع</span>
                </div>
              </>
            ) : (
              <div style={{ fontSize: "13px", color: "var(--color-text-tertiary)" }}>لا توجد عناصر محسوبة</div>
            )}
          </div>

          {/* Modified values (set_value actions) */}
          {result.modified_values && Object.keys(result.modified_values).length > 0 && (
            <div
              style={{
                background: "var(--color-background-primary)",
                border: "0.5px solid var(--color-border-tertiary)",
                borderRadius: "var(--border-radius-lg)",
                padding: "16px",
              }}
            >
              <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>✏️ القيم المعدّلة بالقواعد</div>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
                <tbody>
                  {Object.entries(result.modified_values).map(([fieldId, value]: [string, any], i: number) => {
                    const field = fields.find((f: WorkflowField) => (f.register_field_id ?? `custom_${f.id}`) === fieldId);
                    const original = result.values?.[fieldId];
                    const changed = original !== value;
                    return (
                      <tr key={i} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                        <td style={{ padding: "8px 0", color: "var(--color-text-secondary)" }}>
                          {field?.label ?? fieldId}
                          {field?.register_field_id === null && <span style={{ fontSize: "9px", padding: "1px 4px", background: "var(--color-background-success)", color: "var(--color-text-success)", borderRadius: "3px", marginRight: "4px" }}>مخصص</span>}
                        </td>
                        <td style={{ padding: "8px 0", textAlign: "left", direction: "ltr" }}>
                          {changed && (
                            <span style={{ textDecoration: "line-through", color: "var(--color-text-tertiary)", marginLeft: "8px" }}>
                              {original ?? "—"}
                            </span>
                          )}
                          <span style={{ fontWeight: 500, color: changed ? "var(--color-text-success)" : "var(--color-text-primary)" }}>
                            {value ?? "—"}
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Field states (visibility / required / readonly) */}
          {result.field_states && Object.keys(result.field_states).length > 0 && (
            <div
              style={{
                background: "var(--color-background-primary)",
                border: "0.5px solid var(--color-border-tertiary)",
                borderRadius: "var(--border-radius-lg)",
                padding: "16px",
              }}
            >
              <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "12px" }}>🔒 حالة الحقول بعد القواعد</div>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "13px" }}>
                <thead>
                  <tr style={{ borderBottom: "1px solid var(--color-border-secondary)" }}>
                    <th style={{ padding: "6px 0", textAlign: "right", fontWeight: 500, color: "var(--color-text-secondary)" }}>الحقل</th>
                    <th style={{ padding: "6px 0", textAlign: "center", fontWeight: 500, color: "var(--color-text-secondary)" }}>مرئي</th>
                    <th style={{ padding: "6px 0", textAlign: "center", fontWeight: 500, color: "var(--color-text-secondary)" }}>إلزامي</th>
                    <th style={{ padding: "6px 0", textAlign: "center", fontWeight: 500, color: "var(--color-text-secondary)" }}>للقراءة فقط</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(result.field_states).map(([fieldId, state]: [string, any], i: number) => {
                    const field = fields.find((f: WorkflowField) => f.register_field_id === fieldId);
                    const original = field ? { is_visible: field.is_visible, is_required: field.is_required, is_readonly: field.is_readonly } : null;
                    const visibleChanged = original && original.is_visible !== state.is_visible;
                    const requiredChanged = original && original.is_required !== state.is_required;
                    const readonlyChanged = original && original.is_readonly !== state.is_readonly;
                    return (
                      <tr key={i} style={{ borderBottom: "0.5px solid var(--color-border-tertiary)" }}>
                        <td style={{ padding: "8px 0" }}>{field?.label ?? fieldId}</td>
                        <td style={{ padding: "8px 0", textAlign: "center", color: state.is_visible ? "var(--color-text-success)" : "var(--color-text-danger)", fontWeight: visibleChanged ? 600 : 400 }}>
                          {state.is_visible ? "👁" : "🚫"}
                        </td>
                        <td style={{ padding: "8px 0", textAlign: "center", color: state.is_required ? "var(--color-text-danger)" : "var(--color-text-secondary)", fontWeight: requiredChanged ? 600 : 400 }}>
                          {state.is_required ? "*" : "o"}
                        </td>
                        <td style={{ padding: "8px 0", textAlign: "center", color: state.is_readonly ? "var(--color-text-warning)" : "var(--color-text-secondary)", fontWeight: readonlyChanged ? 600 : 400 }}>
                          {state.is_readonly ? "🔒" : "✎"}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Matched rules */}
          {result.matched_rules?.length > 0 && (
            <div
              style={{
                background: "var(--color-background-primary)",
                border: "0.5px solid var(--color-border-tertiary)",
                borderRadius: "var(--border-radius-lg)",
                padding: "16px",
              }}
            >
              <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "10px" }}>⚡ القواعد المنطبقة</div>
              <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
                {result.matched_rules.map((mr: any, i: number) => (
                  <div key={i} style={{ fontSize: "13px", color: "var(--color-text-success)", padding: "6px 10px", background: "var(--color-background-success)", borderRadius: "4px" }}>
                    ✓ {mr.name || `قاعدة #${i + 1}`}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Actions */}
          {result.actions?.length > 0 && (
            <div
              style={{
                background: "var(--color-background-primary)",
                border: "0.5px solid var(--color-border-tertiary)",
                borderRadius: "var(--border-radius-lg)",
                padding: "16px",
              }}
            >
              <div style={{ fontSize: "14px", fontWeight: 500, marginBottom: "10px" }}>🔧 الإجراءات المنفذة</div>
              <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
                {result.actions.map((action: any, i: number) => (
                  <div key={i} style={{ fontSize: "12px", color: "var(--color-text-secondary)", fontFamily: "monospace", direction: "ltr", textAlign: "left" }}>
                    {JSON.stringify(action)}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

const previewInputStyle: React.CSSProperties = {
  padding: "8px 10px",
  fontSize: "13px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "6px",
  background: "var(--color-background-primary)",
  color: "var(--color-text-primary)",
  fontFamily: "inherit",
  width: "100%",
};

const inputStyleSmall: React.CSSProperties = {
  padding: "6px 8px",
  fontSize: "12px",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "4px",
  fontFamily: "inherit",
};

const btnPrimarySmall: React.CSSProperties = {
  padding: "5px 12px",
  fontSize: "12px",
  background: "var(--color-background-info)",
  color: "var(--color-text-info)",
  border: "0.5px solid var(--color-border-info)",
  borderRadius: "4px",
  cursor: "pointer",
  fontFamily: "inherit",
};

const btnSecondarySmall: React.CSSProperties = {
  padding: "5px 12px",
  fontSize: "12px",
  background: "none",
  color: "var(--color-text-secondary)",
  border: "0.5px solid var(--color-border-secondary)",
  borderRadius: "4px",
  cursor: "pointer",
  fontFamily: "inherit",
};

const labelStyle: React.CSSProperties = { display: "block", fontSize: "12px", color: "var(--color-text-secondary)", marginBottom: "4px" };
