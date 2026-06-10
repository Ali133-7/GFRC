/**
 * Rule editor resolution — the single source of truth for mapping a persisted rule
 * to the editor that must open it. Pure and unit-tested; the editor must NEVER guess
 * a rule's type from actions/conditions counts or config shape heuristics.
 *
 * Rules live in two backend tables:
 *   - workflow_rules    : rule_type ∈ {simple, case_based}
 *   - validation_rules  : validation_type; rule_config ⇒ enterprise;
 *                         validation_type 'field_existence_check' (no rule_config) ⇒ routing
 */

export type RuleEditorKind =
  | "simple"
  | "case_based"
  | "validation"
  | "routing"
  | "enterprise"
  | "unknown";

export type RuleSource = "workflow_rules" | "validation_rules";

/**
 * Classify a raw rule (as returned by the API) into its editor kind, using ONLY
 * persisted discriminators — never inferred from actions/conditions counts.
 */
export function classifyRule(rule: any, source: RuleSource): RuleEditorKind {
  if (rule == null) return "unknown";

  if (source === "workflow_rules") {
    // workflow_rules only ever hold simple or case_based.
    if (rule.rule_type === "case_based") return "case_based";
    if (rule.rule_type === "simple") return "simple";
    return "unknown";
  }

  if (source === "validation_rules") {
    if (rule.rule_config != null) return "enterprise";
    if (rule.validation_type === "field_existence_check") return "routing";
    return "validation";
  }

  return "unknown";
}

/** All editor kinds that have a dedicated builder (excludes "unknown"). */
export const KNOWN_RULE_EDITOR_KINDS: ReadonlyArray<Exclude<RuleEditorKind, "unknown">> = [
  "simple",
  "case_based",
  "validation",
  "routing",
  "enterprise",
];

export function isKnownRuleEditorKind(kind: RuleEditorKind): boolean {
  return kind !== "unknown" && (KNOWN_RULE_EDITOR_KINDS as readonly string[]).includes(kind);
}
