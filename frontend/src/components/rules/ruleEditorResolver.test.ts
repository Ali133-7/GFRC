import { describe, it, expect } from "vitest";
import { classifyRule, isKnownRuleEditorKind } from "./ruleEditorResolver";

describe("classifyRule", () => {
  it("classifies a simple workflow rule as simple", () => {
    expect(classifyRule({ rule_type: "simple", condition_logic: {}, actions: [] }, "workflow_rules")).toBe("simple");
  });

  it("classifies a case_based workflow rule as case_based", () => {
    expect(classifyRule({ rule_type: "case_based", cases: [] }, "workflow_rules")).toBe("case_based");
  });

  it("REGRESSION: a simple rule is never classified as enterprise/advanced", () => {
    expect(classifyRule({ rule_type: "simple" }, "workflow_rules")).not.toBe("enterprise");
  });

  it("classifies a validation rule with rule_config as enterprise", () => {
    expect(
      classifyRule({ rule_config: { conditions: [], actions: [] }, validation_type: "field_existence_check" }, "validation_rules")
    ).toBe("enterprise");
  });

  it("classifies field_existence_check (no rule_config) as routing", () => {
    expect(
      classifyRule({ validation_type: "field_existence_check", route_config: { on_match: {} } }, "validation_rules")
    ).toBe("routing");
  });

  it("classifies a plain validation rule as validation", () => {
    expect(classifyRule({ validation_type: "duplicate_check" }, "validation_rules")).toBe("validation");
  });

  it("returns unknown for a malformed workflow rule (no silent fallback to a builder)", () => {
    expect(classifyRule({}, "workflow_rules")).toBe("unknown");
  });

  it("returns unknown for null", () => {
    expect(classifyRule(null, "workflow_rules")).toBe("unknown");
  });

  it("isKnownRuleEditorKind rejects unknown", () => {
    expect(isKnownRuleEditorKind("unknown")).toBe(false);
    expect(isKnownRuleEditorKind("simple")).toBe(true);
    expect(isKnownRuleEditorKind("routing")).toBe(true);
  });
});
